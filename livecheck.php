<?php
/**
 * livecheck.php — Worker connectivity audit with redirect tracing.
 * DELETE after use.
 */
set_time_limit(35);
header('Content-Type: text/plain; charset=utf-8');

$tests = [
    'cinecdn (Server 4 DB)'     => 'https://fx.cinecdn.workers.dev/',
    'fox-fhd (Server 4 scrape)' => 'https://fox-fhd.nextgoalfox.workers.dev/',
    'fx-4k (Server 5)'          => 'https://fx-4k.fifalive-app.workers.dev/',
    'tahmidx (Server 2)'        => 'https://tahmidx.shusanta-project.workers.dev/',
    'smtahmidx (Server 3)'      => 'https://live.smtahmidx.workers.dev/',
];

foreach ($tests as $label => $url) {
    echo "=== $label ===\n";

    // Step 1: no follow, check if there's a redirect
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        CURLOPT_REFERER        => 'https://fifalive.click/',
        CURLOPT_HTTPHEADER     => [
            'Origin: https://fifalive.click',
            'Referer: https://fifalive.click/',
            'Accept: application/vnd.apple.mpegurl, */*',
            'Cache-Control: no-cache',
        ],
    ]);
    $resp = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $resHdr  = substr($resp, 0, $hs);
    $resBody = substr($resp, $hs, 200);

    echo "HTTP: $code | err: " . ($err ?: 'none') . "\n";

    // Extract Location header if redirect
    preg_match('~^location:\s*(.+)$~im', $resHdr, $loc);
    $location = trim($loc[1] ?? '');
    if ($location) {
        echo "REDIRECTS TO: $location\n";

        // Step 2: follow redirect with same headers
        $ch2 = curl_init($location);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
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
        $body2 = curl_exec($ch2);
        $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $err2  = curl_error($ch2);
        curl_close($ch2);

        echo "After redirect HTTP: $code2 | err: " . ($err2 ?: 'none') . "\n";
        $preview = substr($body2, 0, 150);
        echo "Body: $preview\n";
    } else {
        $status = str_contains($resBody, '#EXTM3U') ? '✓ M3U8 OK' : '~ No M3U8';
        echo "$status | Body: " . substr($resBody, 0, 150) . "\n";
    }
    echo "\n";
}

echo "=== Server IP ===\n";
$ch = curl_init('https://api.ipify.org');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
echo curl_exec($ch) . "\n";
curl_close($ch);
