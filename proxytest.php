<?php
/**
 * proxytest.php — shows exactly what proxy.php does step by step.
 * DELETE after use.
 */
set_time_limit(20);
header('Content-Type: text/plain; charset=utf-8');

$targetUrl = 'https://fx.cinecdn.workers.dev/';
$referer   = 'https://fifalive.click/';
$origin    = 'https://fifalive.click';

$headers = [
    'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, video/MP2T, */*;q=0.9',
    'Accept-Language: en-US,en;q=0.9',
    'Referer: ' . $referer,
    'Origin: '  . $origin,
    'Cache-Control: no-cache',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: cross-site',
];

echo "Testing: $targetUrl\n";
echo "Origin: $origin\n";
echo "Referer: $referer\n\n";

// Step 1: NO follow location (like livecheck.php)
$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    CURLOPT_REFERER        => $referer,
    CURLOPT_HTTPHEADER     => $headers,
]);
$resp = curl_exec($ch);
$hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ip   = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
$err  = curl_error($ch);
curl_close($ch);

$responseHeaders = substr($resp, 0, $hs);
$body            = substr($resp, $hs, 300);

echo "=== Attempt (FOLLOWLOCATION=false) ===\n";
echo "HTTP: $code | IP: $ip | err: " . ($err ?: 'none') . "\n";
echo "Response headers:\n$responseHeaders\n";
echo "Body: $body\n\n";

// Step 2: Check if it's a redirect
if ($code >= 300 && $code < 400) {
    preg_match('~^location:\s*(.+)$~im', $responseHeaders, $m);
    $location = trim($m[1] ?? '');
    echo "REDIRECT to: $location\n\n";

    if ($location) {
        $ch2 = curl_init($location);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            CURLOPT_REFERER        => $referer,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $resp2 = curl_exec($ch2);
        $hs2   = curl_getinfo($ch2, CURLINFO_HEADER_SIZE);
        $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $ip2   = curl_getinfo($ch2, CURLINFO_PRIMARY_IP);
        $err2  = curl_error($ch2);
        curl_close($ch2);
        $body2 = substr($resp2, $hs2, 300);
        echo "=== After redirect ===\n";
        echo "HTTP: $code2 | IP: $ip2 | err: " . ($err2 ?: 'none') . "\n";
        echo "Body: $body2\n";
    }
} elseif ($code === 200) {
    echo "Direct 200 — no redirect needed.\n";
}
