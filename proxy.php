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
    $content = str_replace(
        'প্রিমিয়াম স্ট্রিমিং আনলক করুন! 🔐 লাইভ খেলা FHD/4K কোয়ালিটিতে দেখতে বাটনে ক্লিক করে আমাদের ফেইসবুক পেইজ ফলো করুন, ৫ সেকেন্ড অপেক্ষা করুন!',
        '',
        $content
    );

    $content = preg_replace(
        '/<(?:(?:div|section|aside|article|p|span|button|a)[^>]*)>[\s\S]*?প্রিমিয়াম স্ট্রিমিং আনলক করুন![\s\S]*?<\/(?:div|section|aside|article|p|span|button|a)>/iu',
        '',
        $content
    );

    $content = preg_replace(
        '/<script[^>]*>[\s\S]*?ফেইসবুক পেইজ ফলো করুন[\s\S]*?<\/script>/iu',
        '',
        $content
    );

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
