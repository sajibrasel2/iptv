<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL);
if (!$url) {
    http_response_code(400);
    echo 'Missing url parameter.';
    exit;
}

$targetUrl = filter_var($url, FILTER_VALIDATE_URL);
if (!$targetUrl || !in_array(parse_url($targetUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
    http_response_code(400);
    echo 'Invalid url parameter.';
    exit;
}

$host = parse_url($targetUrl, PHP_URL_HOST);
if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
    http_response_code(400);
    echo 'Invalid url parameter.';
    exit;
}

// -----------------------------------------------------------------------
// Helper: rewrite M3U8 content so all segment/key URLs go through proxy
// -----------------------------------------------------------------------
function rewriteM3u8($m3u8Content, $targetUrl) {
    $parsedUrl = parse_url($targetUrl);
    $scheme    = $parsedUrl['scheme'] ?? 'https';
    $urlHost   = $parsedUrl['host']   ?? '';
    $path      = $parsedUrl['path']   ?? '/';

    // Base directory of the M3U8 URL
    if (substr($path, -1) !== '/' && strpos(basename($path), '.') !== false) {
        $path = dirname($path);
    }
    $path = rtrim($path, '/\\');
    $baseUrl = $scheme . '://' . $urlHost . ($path !== '' ? $path . '/' : '/');

    $lines = explode("\n", $m3u8Content);
    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        if ($trimmed[0] !== '#') {
            // Segment or sub-playlist line — make absolute then proxy
            if (stripos($trimmed, 'http://') !== 0 && stripos($trimmed, 'https://') !== 0) {
                $trimmed = ($trimmed[0] === '/')
                    ? $scheme . '://' . $urlHost . $trimmed
                    : $baseUrl . $trimmed;
            }
            $lines[$i] = 'proxy.php?url=' . rawurlencode($trimmed) . '&raw=true';
        } else {
            // Tag lines: rewrite URI="..." attributes (encryption keys, maps, etc.)
            if (preg_match('/URI=["\']([^"\']+)["\']/i', $trimmed, $uriMatches)) {
                $uri = $uriMatches[1];
                if (stripos($uri, 'http://') !== 0 && stripos($uri, 'https://') !== 0) {
                    $absUri = ($uri[0] === '/')
                        ? $scheme . '://' . $urlHost . $uri
                        : $baseUrl . $uri;
                } else {
                    $absUri = $uri;
                }
                $proxiedUri = 'proxy.php?url=' . rawurlencode($absUri) . '&raw=true';
                $lines[$i] = str_replace($uri, $proxiedUri, $trimmed);
            }
        }
    }
    return implode("\n", $lines);
}

// -----------------------------------------------------------------------
// Helper: serve the clean HLS.js player page
// -----------------------------------------------------------------------
function serveHlsPlayer($streamUrl) {
    $proxiedStream = 'proxy.php?url=' . rawurlencode($streamUrl) . '&raw=true';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Stream</title>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: 100%; height: 100%;
            background: #000;
            overflow: hidden;
            display: flex; justify-content: center; align-items: center;
        }
        video { width: 100%; height: 100%; background: #000; }
        #error-msg {
            display: none; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            color: #f87171; font-family: sans-serif; font-size: 14px;
            text-align: center; padding: 12px;
        }
    </style>
</head>
<body>
    <video id="video" controls autoplay playsinline></video>
    <div id="error-msg"></div>
    <script>
        const RAW_URL   = <?php echo json_encode($streamUrl); ?>;
        const STREAM    = <?php echo json_encode($proxiedStream); ?>;
        const video     = document.getElementById('video');
        const errorDiv  = document.getElementById('error-msg');

        function showError(msg) {
            errorDiv.style.display = 'block';
            errorDiv.textContent   = msg;
        }

        function tryNative() {
            // iOS Safari / native HLS
            if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = STREAM;
                video.load();
                video.play().catch(function(e) { console.log('Autoplay blocked:', e); });
            } else {
                showError('Your browser does not support HLS playback.');
            }
        }

        if (Hls.isSupported()) {
            var hls = new Hls({
                maxMaxBufferLength:  60,
                enableWorker:        true,
                lowLatencyMode:      true,
                fragLoadingMaxRetry: 6,
                manifestLoadingMaxRetry: 6,
                levelLoadingMaxRetry: 6,
                xhrSetup: function(xhr, url) {
                    // Nothing special needed — segments already go through proxy
                }
            });
            hls.loadSource(STREAM);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                video.play().catch(function(e) { console.log('Autoplay blocked:', e); });
            });
            hls.on(Hls.Events.ERROR, function(event, data) {
                console.error('HLS error:', data.type, data.details, data);
                if (data.fatal) {
                    switch (data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            console.warn('Fatal network error — retrying...');
                            hls.startLoad();
                            break;
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            console.warn('Fatal media error — recovering...');
                            hls.recoverMediaError();
                            break;
                        default:
                            console.error('Unrecoverable error, destroying HLS instance.');
                            hls.destroy();
                            showError('Stream failed to load. The source may be offline or geo-blocked.');
                            break;
                    }
                }
            });
        } else {
            tryNative();
        }
    </script>
</body>
</html>
    <?php
    exit;
}

// -----------------------------------------------------------------------
// Shared curl factory — always sends realistic headers including Referer
// -----------------------------------------------------------------------
function makeCurl($targetUrl, $rawMedia = false) {
    $parsedHost  = parse_url($targetUrl, PHP_URL_HOST) ?? '';
    $refererBase = parse_url($targetUrl, PHP_URL_SCHEME) . '://' . $parsedHost . '/';

    $ch = curl_init($targetUrl);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 8,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => ($rawMedia ? 30 : 20),
        CURLOPT_HTTPHEADER     => [
            'Accept: ' . ($rawMedia
                ? 'application/vnd.apple.mpegurl, application/x-mpegurl, video/MP2T, */*'
                : 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
            'Accept-Language: en-US,en;q=0.9',
            'Referer: ' . $refererBase,
            'Origin: '  . rtrim($refererBase, '/'),
            'Connection: keep-alive',
        ],
    ];
    if (!$rawMedia) {
        $opts[CURLOPT_HEADER] = false;
        $opts[CURLOPT_FAILONERROR] = false;
    }
    curl_setopt_array($ch, $opts);
    return $ch;
}

// -----------------------------------------------------------------------
// PATH 1 — ?raw=true  →  pass media bytes / rewritten M3U8 straight through
// -----------------------------------------------------------------------
$isRaw  = isset($_GET['raw']) && $_GET['raw'] === 'true';
$isM3u8 = stripos($targetUrl, '.m3u8') !== false
       || stripos(parse_url($targetUrl, PHP_URL_PATH) ?? '', '.m3u8') !== false;
$isTs   = stripos($targetUrl, '.ts')   !== false
       || stripos(parse_url($targetUrl, PHP_URL_PATH) ?? '', '.ts') !== false
       || stripos($targetUrl, 'seg-')  !== false
       || stripos($targetUrl, 'chunk') !== false
       || stripos($targetUrl, 'media_') !== false;

if ($isRaw) {
    $ch      = makeCurl($targetUrl, true);
    $content = curl_exec($ch);
    $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 200;
    $ctype   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
    curl_close($ch);

    header('Access-Control-Allow-Origin: *');

    // Detect M3U8 by content-type OR by actual content
    $looksLikeM3u8 = $isM3u8
        || stripos($ctype, 'mpegurl') !== false
        || stripos($ctype, 'application/vnd.apple') !== false
        || (is_string($content) && stripos(trim($content), '#EXTM3U') === 0);

    if ($looksLikeM3u8 && $content !== false) {
        $rewritten = rewriteM3u8($content, $targetUrl);
        header('Content-Type: application/vnd.apple.mpegurl');
        http_response_code($status);
        echo $rewritten;
        exit;
    }

    // TS segment or other binary media
    $mediaType = $isTs ? 'video/MP2T' : ($ctype ?: 'application/octet-stream');
    header('Content-Type: ' . $mediaType);
    http_response_code($status);
    echo $content;
    exit;
}

// -----------------------------------------------------------------------
// PATH 2 — normal page proxy (no ?raw)
// -----------------------------------------------------------------------
$ch      = makeCurl($targetUrl, false);
$content = curl_exec($ch);
$status  = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 200;
$ctype   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html; charset=utf-8';
$curlErr = curl_error($ch);
curl_close($ch);

if ($content === false || $content === '') {
    http_response_code(502);
    echo 'Unable to fetch the remote resource. cURL: ' . htmlspecialchars($curlErr);
    exit;
}

// -----------------------------------------------------------------------
// CASE A — upstream returned M3U8 directly (e.g. fifalive.click/)
// -----------------------------------------------------------------------
$upstreamIsM3u8 = stripos($ctype, 'mpegurl') !== false
               || stripos($ctype, 'application/vnd.apple') !== false
               || stripos(trim($content), '#EXTM3U') === 0;

if ($upstreamIsM3u8) {
    // Serve a proper HLS player with this M3U8 going through the proxy
    serveHlsPlayer($targetUrl);
    // serveHlsPlayer() calls exit — never reaches here
}

// -----------------------------------------------------------------------
// CASE B — upstream returned HTML; try to extract an embedded .m3u8 URL
// -----------------------------------------------------------------------
$streamUrl = '';
if (preg_match('/https?:\/\/[^\s\'"\\\\]+\.m3u8(?:[^\s\'"\\\\]*)?/i', $content, $matches)) {
    $streamUrl = str_replace('\/', '/', $matches[0]);
    $streamUrl = html_entity_decode($streamUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

if ($streamUrl !== '') {
    serveHlsPlayer($streamUrl);
}

// -----------------------------------------------------------------------
// CASE C — proxied HTML page (fallback for complex player pages)
// -----------------------------------------------------------------------
if (stripos($ctype, 'text/html') !== false) {
    // Strip social-locker / marquee overlays from the proxied HTML
    $content = preg_replace(
        '/<(?:div|section|aside|article|p|span|button|a)[^>]*(?:id|class)=["\'][^"\']*(?:socialWidget|social-box|social-btn|close-social|sticky-header-notice|sticky-notice|marquee-wrapper|marquee-text|fbLockerBtn|locker-fb-btn|lockerCountdown|countdown-text|countNumber)[^"\']*["\'][^>]*>[\s\S]*?<\/(?:div|section|aside|article|p|span|button|a)>/iu',
        '',
        $content
    );

    $overlayCss = '<style id="proxy-iframe-overlay-style">
        #socialWidget,.social-box,.social-btn,.close-social,
        #sticky-header-notice,.sticky-notice,
        .marquee-wrapper,.marquee-text,.scrolling-marquee,.marquee-container,.marquee,
        #fbLockerBtn,.locker-fb-btn,#lockerCountdown,.countdown-text,#countNumber,
        .facebook-follow,.telegram-follow,#telegram-bar{
            display:none!important;visibility:hidden!important;
            opacity:0!important;height:0!important;overflow:hidden!important;
            pointer-events:none!important;
        }
        video,#player,.jwplayer,.video-js,.plyr{width:100%!important;height:100%!important;}
    </style>';

    $observerScript = '<script id="proxy-iframe-observer-script">
    (function(){
        var safe=[
            "#socialWidget",".social-box",".social-btn",".close-social",
            "#sticky-header-notice",".sticky-notice",
            ".marquee-wrapper",".marquee-text",".scrolling-marquee",".marquee-container",".marquee",
            "#fbLockerBtn",".locker-fb-btn","#lockerCountdown",".countdown-text","#countNumber",
            ".facebook-follow",".telegram-follow","#telegram-bar"
        ];
        function ok(el){
            if(!el)return false;
            var t=el.tagName;
            if(t==="VIDEO"||t==="IFRAME"||t==="CANVAS"||t==="BODY"||t==="HTML")return false;
            if(el.id==="player"||el.classList.contains("jwplayer")||el.classList.contains("video-js"))return false;
            if(el.querySelector("video,iframe,canvas,#player,.jwplayer,.video-js"))return false;
            return true;
        }
        function clean(){
            safe.forEach(function(s){
                document.querySelectorAll(s).forEach(function(el){
                    if(ok(el)){el.style.setProperty("display","none","important");try{el.remove();}catch(e){}}
                });
            });
        }
        if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",clean);}else{clean();}
        window.addEventListener("load",clean);
        new MutationObserver(function(){clean();}).observe(document.documentElement,{childList:true,subtree:true});
    })();
    <\/script>';

    if (stripos($content, '<head') !== false) {
        $content = preg_replace('/<head[^>]*>/i', "$0" . $overlayCss . $observerScript, $content, 1);
    } else {
        $content = $overlayCss . $observerScript . $content;
    }

    // Inject <base> tag so relative asset URLs resolve correctly
    if (stripos($content, '<head') !== false && stripos($content, '<base') === false) {
        $content = preg_replace_callback(
            '/<head[^>]*>/i',
            function ($m) use ($targetUrl) {
                return $m[0] . '<base href="' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '">';
            },
            $content,
            1
        );
    }
}

http_response_code($status);
header('Content-Type: ' . $ctype);
echo $content;
