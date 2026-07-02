<?php
/**
 * livecheck.php — Worker connectivity audit from cPanel server.
 * DELETE after use.
 */
set_time_limit(35);
header('Content-Type: text/plain; charset=utf-8');

$tests = [
    'cinecdn (Server 4 DB)'    => 'https://fx.cinecdn.workers.dev/',
    'fox-fhd (Server 4 scrape)'=> 'https://fox-fhd.nextgoalfox.workers.dev/',
    'fx-4k (Server 5)'         => 'https://fx-4k.fifalive-app.workers.dev/',
    'tahmidx (Server 2)'       => 'https://tahmidx.shusanta-project.workers.dev/',
    'smtahmidx (Server 3)'     => 'https://live.smtahmidx.workers.dev/',
];

foreach ($tests as $label => $url) {
    echo "=== $label ===\n";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        CURLOPT_REFERER        => 'https://fifalive.click/',
        CURLOPT_HTTPHEADER     => [
            'Origin: https://fifalive.click',
            'Referer: https://fifalive.click/',
            'Accept: application/vnd.apple.mpegurl, */*',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $status = ($code === 200 && str_contains($body, '#EXTM3U')) ? '✓ M3U8 OK' :
              ($code === 200 ? '~ HTTP 200 but no M3U8' :
              "✗ HTTP $code");
    echo "$status | err: " . ($err ?: 'none') . "\n";
    if ($code === 200 && str_contains($body, '#EXTM3U')) {
        echo "Preview: " . substr($body, 0, 120) . "\n";
    } else {
        echo "Body: " . substr($body, 0, 80) . "\n";
    }
    echo "\n";
}
