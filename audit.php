<?php
/**
 * audit.php — one-shot diagnostic. DELETE AFTER USE.
 * Visit: https://techandclick.site/iptv/audit.php
 */
set_time_limit(25);
header('Content-Type: text/plain; charset=utf-8');

$tests = [
    'cinecdn (Origin=fifalive, Referer=fifalive)' => [
        'url'     => 'https://fx.cinecdn.workers.dev/',
        'origin'  => 'https://fifalive.click',
        'referer' => 'https://fifalive.click/',
    ],
    'cinecdn (no Origin header at all)' => [
        'url'     => 'https://fx.cinecdn.workers.dev/',
        'origin'  => null,
        'referer' => 'https://fifalive.click/',
    ],
    'nextgoal (Origin=fifalive)' => [
        'url'     => 'https://live3.nextgoal.workers.dev/',
        'origin'  => 'https://fifalive.click',
        'referer' => 'https://fifalive.click/',
    ],
    'toffeelive (Origin=toffeelive)' => [
        'url'     => 'https://prod-cdn01-live.toffeelive.com/live/FIFA-2026-3/sst/0/master_1500.m3u8?hdntl=Expires=1783041148~_GO=Generated~URLPrefix=aHR0cHM6Ly9wcm9kLWNkbjAxLWxpdmUudG9mZmVlbGl2ZS5jb20~Signature=AeQsclDmkNXkDTQQGXnCCjWd5QHkSDMbhqlxjQMXVAkRhhpmNcibqgqbrtrtkAj3Tw2XG3KAUkV2Id5Q4q-XNVHiooUF',
        'origin'  => 'https://toffeelive.com',
        'referer' => 'https://toffeelive.com/',
    ],
];

foreach ($tests as $label => $cfg) {
    echo "\n=== $label ===\n";

    $hdrs = [
        'Accept: application/vnd.apple.mpegurl, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: ' . $cfg['referer'],
        'Connection: keep-alive',
    ];
    if ($cfg['origin'] !== null) {
        $hdrs[] = 'Origin: ' . $cfg['origin'];
    }

    $ch = curl_init($cfg['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        CURLOPT_REFERER        => $cfg['referer'],
        CURLOPT_HTTPHEADER     => $hdrs,
    ]);

    $resp    = curl_exec($ch);
    $hs      = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err     = curl_error($ch);
    $ip      = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    curl_close($ch);

    $body = substr($resp, $hs, 300);
    echo "HTTP $code | Server IP: $ip | cURL err: " . ($err ?: 'none') . "\n";
    echo "Body: $body\n";
}

// Also show this server's outbound IP
echo "\n=== This server's outbound IP ===\n";
$ch = curl_init('https://api.ipify.org');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>5, CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4]);
echo curl_exec($ch) . "\n";
curl_close($ch);
