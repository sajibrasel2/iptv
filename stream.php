<?php
/**
 * stream.php — Direct Stream Extraction API
 *
 * Fetches the master M3U8 from fifalive.click/ on every request (tokens expire),
 * parses all server entries, and returns a JSON array of available streams.
 *
 * Response shape:
 * {
 *   "ok": true,
 *   "servers": [
 *     { "name": "Server 1", "raw_url": "https://...", "proxy_url": "proxy.php?url=...&raw=true" },
 *     ...
 *   ]
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');

$SOURCE = 'https://fifalive.click/';

// ---------------------------------------------------------------------------
// Fetch the master playlist from fifalive.click
// ---------------------------------------------------------------------------
$ch = curl_init($SOURCE);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 6,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: https://fifalive.click/',
        'Origin: https://fifalive.click',
        'Connection: keep-alive',
    ],
]);

$body    = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($body === false || $body === '' || $httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    echo json_encode([
        'ok'    => false,
        'error' => 'Failed to fetch source playlist. HTTP ' . $httpCode . '. cURL: ' . $curlErr,
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// Parse the M3U8 playlist into server entries
// ---------------------------------------------------------------------------
$lines   = preg_split('/\r?\n/', trim($body));
$servers = [];
$pending = null; // holds EXTINF name while we wait for the URL line

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') continue;

    if (stripos($line, '#EXTINF') === 0) {
        // Extract tvg-name or fall back to the trailing label after the comma
        $name = '';
        if (preg_match('/tvg-name=["\']([^"\']+)["\']/i', $line, $m)) {
            $name = $m[1];
        } elseif (preg_match('/,(.+)$/', $line, $m)) {
            $name = trim($m[1]);
        }
        $pending = $name !== '' ? $name : ('Server ' . (count($servers) + 1));
        continue;
    }

    if ($line[0] !== '#' && filter_var($line, FILTER_VALIDATE_URL)) {
        $rawUrl    = $line;
        $proxyUrl  = 'proxy.php?url=' . rawurlencode($rawUrl) . '&raw=true';
        $servers[] = [
            'name'      => $pending ?? ('Server ' . (count($servers) + 1)),
            'raw_url'   => $rawUrl,
            'proxy_url' => $proxyUrl,
        ];
        $pending = null;
    }
}

if (empty($servers)) {
    http_response_code(502);
    echo json_encode([
        'ok'    => false,
        'error' => 'Source returned no playable stream URLs.',
        'raw'   => substr($body, 0, 500),
    ]);
    exit;
}

echo json_encode(['ok' => true, 'servers' => $servers], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
