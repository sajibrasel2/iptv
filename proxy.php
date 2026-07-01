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

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (compatible; IPTV Proxy/1.0)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HEADER => false,
    CURLOPT_FAILONERROR => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);

$content = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 200;
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html; charset=utf-8';
$curlError = curl_error($ch);
curl_close($ch);

if ($content === false) {
    http_response_code(502);
    echo 'Unable to fetch the remote resource.';
    exit;
}

if (stripos($contentType, 'text/html') !== false) {
    $content = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/iu', function ($matches) {
        $script = $matches[0];
        $patterns = ['startUnlockProcess', 'socialWidget', 'sticky-header-notice', 'fbLockerBtn', 'lockerCountdown', 'follow on facebook', 'follow on telegram', 'marquee-wrapper', 'baseViewsAmount'];
        foreach ($patterns as $pattern) {
            if (stripos($script, $pattern) !== false) {
                return '';
            }
        }
        return $script;
    }, $content);

    $content = preg_replace('/<(?:div|section|aside|article|p|span|button|a)[^>]*(?:id|class)=["\'][^"\']*(?:socialWidget|social-box|social-btn|close-social|sticky-header-notice|sticky-notice|marquee-wrapper|marquee-text|fbLockerBtn|lockerCountdown|countNumber|overlay|popup|modal|announcement-bar|top-bar|follow-container|social-buttons|marquee|marquee-container)[^"\']*["\'][^>]*>[\s\S]*?<\/(?:div|section|aside|article|p|span|button|a)>/iu', '', $content);
    $content = preg_replace('/<(?:div|section|aside|article|p|span|button|a)[^>]*(?:id|class)=["\'][^"\']*(?:socialWidget|social-box|social-btn|close-social|sticky-header-notice|sticky-notice|marquee-wrapper|marquee-text|fbLockerBtn|lockerCountdown|countNumber|overlay|popup|modal|announcement-bar|top-bar|follow-container|social-buttons|marquee|marquee-container)[^"\']*["\'][^>]*\/>/iu', '', $content);

    $overlayCss = '<style id="proxy-iframe-overlay-style">#socialWidget, .social-box, .social-btn, .close-social, #sticky-header-notice, .sticky-notice, .marquee-wrapper, .marquee-text, #fbLockerBtn, #lockerCountdown, #countNumber, .modal, .popup, .overlay, [class*="overlay"], [id*="overlay"], .announcement-bar, .top-bar, .follow-container, .social-buttons, .marquee, .marquee-container { display: none !important; visibility: hidden !important; opacity: 0 !important; height: 0 !important; min-height: 0 !important; overflow: hidden !important; pointer-events: none !important; }</style>';
    $overlayScript = '<script id="proxy-overlay-guard">(function(){const selectors=["#socialWidget",".social-box","#sticky-header-notice",".sticky-notice",".marquee-wrapper",".marquee-text","#fbLockerBtn","#lockerCountdown","#countNumber",".social-btn",".close-social",".modal",".popup",".overlay","[class*=\"overlay\"]","[id*=\"overlay\"]",".announcement-bar",".top-bar",".follow-container",".social-buttons",".marquee",".marquee-container"];const removeNow=()=>{selectors.forEach(sel=>document.querySelectorAll(sel).forEach(el=>el.remove()));document.querySelectorAll("body *").forEach(el=>{const text=(el.textContent||"").toLowerCase();if((text.includes("follow on facebook")||text.includes("follow on telegram")||text.includes("t.me/")||text.includes("facebook.com"))&&(el.className||el.id)){const name=(el.className||"").toString()+" "+(el.id||"");if(/social|marquee|overlay|popup|notice|locker/i.test(name))el.remove();}})};removeNow();const observer=new MutationObserver(()=>removeNow());observer.observe(document.documentElement||document.body,{childList:true,subtree:true});window.addEventListener("load",removeNow);})();</script>';

    if (stripos($content, '<head') !== false) {
        $content = preg_replace('/<head[^>]*>/i', '$0' . $overlayCss . $overlayScript, $content, 1);
    } else {
        $content = $overlayCss . $overlayScript . $content;
    }

    if (stripos($content, '<head') !== false && stripos($content, '<base') === false) {
        $content = preg_replace_callback(
            '/<head[^>]*>/i',
            function ($matches) use ($targetUrl) {
                return $matches[0] . '<base href="' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '">';
            },
            $content,
            1
        );
    }
}

http_response_code($statusCode);
header('Content-Type: ' . $contentType);
echo $content;
