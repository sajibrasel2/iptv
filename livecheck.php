<?php
/**
 * livecheck.php — Quick connectivity audit from the cPanel server.
 * DELETE after use. Visit: https://techandclick.site/iptv/livecheck.php
 */
set_time_limit(30);
header('Content-Type: text/plain; charset=utf-8');

$tests = [
    'cinecdn'   => 'https://fx.cinecdn.workers.dev/',
    'tahmidx'   => 'https://tahmidx.shusanta-project.workers.dev/',
    'smtahmidx' => 'https://live.smtahmidx.workers.dev/',
    'mux-test'  => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
];

$referers = [
    'cinecdn'   => 'https://fifalive.click/',
    'tahmidx'   => 'https://fifalive.click/',
    'smtahmidx' => 'https://fifalive.click/',
    'mux-test'  => 'https://test-streams.mux.dev/',
];

foreach ($tests as $label => $url) {
    echo "=== $label ===\n";
    $ref = $referers[$label];
    $org = rtrim($ref, '/');
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        CURLOPT_REFERER        => $ref,
        CURLOPT_HTTPHEADER     => [
            'Origin: ' . $org,
            'Referer: ' . $ref,
            'Accept: application/vnd.apple.mpegurl, */*',
        ],
    ]);
    $resp = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    $ip   = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    curl_close($ch);

    $body = substr($resp, $hs, 200);
    echo "HTTP: $code | IP: $ip | err: " . ($err ?: 'none') . "\n";
    echo "Body: " . $body . "\n\n";
}

// Server's own outbound IP
$ch = curl_init('https://api.ipify.org');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
echo "=== Server outbound IP ===\n" . curl_exec($ch) . "\n";
curl_close($ch);
