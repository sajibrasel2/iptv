<?php
/**
 * proxy.php — Pure HLS Media Proxy
 *
 * ONLY handles:
 *   1. M3U8 playlists  → rewrites all segment/key URLs to route through this proxy
 *   2. TS segments     → passes bytes straight through with correct Content-Type
 *   3. Encryption keys → passes bytes straight through
 *
 * No HTML proxying. No page scraping. No iframes.
 *
 * Usage:
 *   proxy.php?url=<encoded_url>&raw=true   (called by HLS.js for segments/playlists)
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------------------
// Validate & sanitise the ?url= parameter
// ---------------------------------------------------------------------------
$url = isset($_GET['url']) ? trim($_GET['url']) : '';
if ($url === '') {
    http_response_code(400);
    echo 'Missing url parameter.';
    exit;
}

$targetUrl = filter_var($url, FILTER_VALIDATE_URL);
if ($targetUrl === false) {
    http_response_code(400);
    echo 'Invalid url parameter.';
    exit;
}

$scheme = parse_url($targetUrl, PHP_URL_SCHEME);
if (!in_array($scheme, ['http', 'https'], true)) {
    http_response_code(400);
    echo 'Only http/https URLs are allowed.';
    exit;
}

$host = strtolower(parse_url($targetUrl, PHP_URL_HOST) ?? '');
// Block SSRF to local/internal hosts
$blocked = ['localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254'];
foreach ($blocked as $b) {
    if ($host === $b || substr($host, -strlen('.' . $b)) === '.' . $b) {
        http_response_code(403);
        echo 'Blocked.';
        exit;
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build an absolute URL from a (possibly relative) URL and the base URL
 * of the document that contains it.
 */
function makeAbsolute(string $href, string $baseUrl): string {
    if (stripos($href, 'http://') === 0 || stripos($href, 'https://') === 0) {
        return $href;
    }
    $p      = parse_url($baseUrl);
    $scheme = $p['scheme'] ?? 'https';
    $host   = $p['host']   ?? '';
    $path   = $p['path']   ?? '/';

    if ($href[0] === '/') {
        return $scheme . '://' . $host . $href;
    }

    // Relative path — resolve against the directory of $baseUrl
    $dir = (substr($path, -1) === '/' || strpos(basename($path), '.') === false)
        ? rtrim($path, '/') . '/'
        : dirname($path) . '/';

    return $scheme . '://' . $host . $dir . $href;
}

/**
 * Rewrite every line in an M3U8 so all URLs route back through this proxy.
 */
function rewriteM3u8(string $content, string $baseUrl): string {
    $lines = preg_split('/\r?\n/', $content);

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        if ($trimmed[0] !== '#') {
            // Segment / sub-playlist URL
            $abs         = makeAbsolute($trimmed, $baseUrl);
            $lines[$i]   = 'proxy.php?url=' . rawurlencode($abs) . '&raw=true';
        } else {
            // Tag line — rewrite URI="..." attributes (keys, maps, etc.)
            $lines[$i] = preg_replace_callback(
                '/URI=["\']([^"\']+)["\']/i',
                function (array $m) use ($baseUrl): string {
                    $abs = makeAbsolute($m[1], $baseUrl);
                    return 'URI="proxy.php?url=' . rawurlencode($abs) . '&raw=true"';
                },
                $trimmed
            );
        }
    }

    return implode("\n", $lines);
}

/**
 * Create a cURL handle pre-configured to look like a real browser request.
 * $referer: the page that would logically be requesting this resource.
 */
function makeCurl(string $targetUrl, string $referer = 'https://fifalive.click/', bool $isMedia = true) {
    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 8,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => ($isMedia ? 30 : 15),
        CURLOPT_ENCODING       => '',          // Accept-Encoding: identity,gzip,deflate
        CURLOPT_HTTPHEADER     => [
            'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, video/MP2T, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: ' . $referer,
            'Origin: '  . rtrim(preg_replace('#(https?://[^/]+).*#', '$1', $referer), '/'),
            'Connection: keep-alive',
        ],
    ]);
    return $ch;
}

// ---------------------------------------------------------------------------
// Main — fetch and forward the requested resource
// ---------------------------------------------------------------------------
$ch      = makeCurl($targetUrl);
$content = curl_exec($ch);
$status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
$curlErr = curl_error($ch);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $targetUrl; // may differ after redirects
curl_close($ch);

if ($content === false) {
    http_response_code(502);
    echo 'Upstream fetch failed: ' . htmlspecialchars($curlErr);
    exit;
}

if ($status >= 400) {
    http_response_code($status);
    echo 'Upstream returned HTTP ' . $status;
    exit;
}

// ---------------------------------------------------------------------------
// Detect content type: M3U8 vs binary media
// ---------------------------------------------------------------------------
$looksLikeM3u8 =
    stripos($ctype, 'mpegurl') !== false ||
    stripos($ctype, 'application/vnd.apple') !== false ||
    (is_string($content) && stripos(ltrim($content), '#EXTM3U') === 0) ||
    // URL itself hints at M3U8
    preg_match('/\.m3u8(\?|$)/i', $finalUrl);

if ($looksLikeM3u8) {
    $rewritten = rewriteM3u8($content, $finalUrl);
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    http_response_code($status ?: 200);
    echo $rewritten;
    exit;
}

// Binary media (TS segment, encryption key, etc.)
// Preserve the upstream Content-Type; fall back to sensible defaults
if ($ctype === '' || stripos($ctype, 'text/html') !== false) {
    // A TS segment misidentified as HTML, or no type — detect by URL
    if (preg_match('/\.(ts|aac|mp4|m4s|m4v|fmp4)(\?|$)/i', $finalUrl)) {
        $ctype = 'video/MP2T';
    } elseif (preg_match('/\.(key|bin)(\?|$)/i', $finalUrl)) {
        $ctype = 'application/octet-stream';
    } else {
        $ctype = 'application/octet-stream';
    }
}

header('Content-Type: ' . $ctype);
http_response_code($status ?: 200);
echo $content;
