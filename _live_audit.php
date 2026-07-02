<?php
/**
 * Live frontend audit — fetches the proxy URLs exactly as HLS.js would,
 * inspects the rewritten M3U8, then probes the first segment URL.
 * Run on the LIVE server via: https://techandclick.site/iptv/_live_audit.php
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$BASE = 'https://techandclick.site/iptv/';

function fetch_url(string $url, array $hdrs = [], int $timeout = 15): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 6,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => $hdrs,
    ]);
    $body  = curl_exec($ch);
    $code  = (int)    curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    $err   = curl_error($ch);
    curl_close($ch);
    return compact('body','code','ctype','final','err');
}

echo "=== LIVE FRONTEND AUDIT ===\n";
echo "Server IP: " . gethostbyname(gethostname()) . "\n";
echo "Time: " . date('Y-m-d H:i:s T') . "\n\n";

// ── 1. Fetch stream.php from the live server
echo "--- 1. stream.php ---\n";
$sr = fetch_url($BASE . 'stream.php');
echo "HTTP: {$sr['code']}  CT: {$sr['ctype']}\n";
$stream_data = json_decode((string)$sr['body'], true);
if (!$stream_data) {
    echo "FATAL: stream.php did not return valid JSON\nBody: ".substr((string)$sr['body'],0,500)."\n";
    exit;
}
echo "source: {$stream_data['source']}  count: {$stream_data['count']}\n";
if (!empty($stream_data['errors'])) {
    echo "errors: " . implode(' | ', $stream_data['errors']) . "\n";
}
echo "\n";

// ── 2. For each live server, fetch its M3U8 through proxy.php
$live_servers = array_filter($stream_data['servers'] ?? [], fn($s) => empty($s['is_fallback']));

foreach ($live_servers as $srv) {
    echo "--- Server: {$srv['name']} ---\n";
    echo "proxy_url: {$srv['proxy_url']}\n";

    $proxy_url = $BASE . $srv['proxy_url'];
    $pr = fetch_url($proxy_url, [
        'Accept: */*',
        'Origin: https://techandclick.site',
        'Referer: https://techandclick.site/iptv/',
    ]);
    echo "HTTP: {$pr['code']}  CT: {$pr['ctype']}\n";
    echo "FINAL: {$pr['final']}\n";

    $m3u8 = (string)($pr['body'] ?? '');
    if ($pr['code'] >= 400) {
        echo "ERROR BODY: " . substr($m3u8, 0, 300) . "\n\n";
        continue;
    }

    // Show first 600 chars of the rewritten M3U8
    echo "M3U8_PREVIEW:\n" . substr($m3u8, 0, 600) . "\n\n";

    // Extract the FIRST segment proxy URL from the M3U8
    $lines = preg_split('/\r?\n/', $m3u8);
    $first_seg = null;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '' && $line[0] !== '#') {
            $first_seg = $line;
            break;
        }
    }

    if (!$first_seg) {
        echo "NO SEGMENT LINES FOUND IN M3U8\n\n";
        continue;
    }

    echo "FIRST_SEGMENT_PROXY_URL: $first_seg\n";

    // If it's a relative proxy.php URL, make it absolute
    if (strpos($first_seg, 'http') !== 0) {
        $first_seg = $BASE . $first_seg;
    }

    // ── 3. Probe the first segment through the proxy
    echo "\n-- Probing segment --\n";
    $seg_r = fetch_url($first_seg, [
        'Accept: */*',
        'Origin: https://techandclick.site',
        'Referer: https://techandclick.site/iptv/',
    ], 20);

    echo "HTTP: {$seg_r['code']}  CT: {$seg_r['ctype']}\n";
    echo "FINAL_URL: {$seg_r['final']}\n";
    $seg_body = (string)($seg_r['body'] ?? '');
    $seg_len  = strlen($seg_body);
    echo "BYTES_RECEIVED: $seg_len\n";

    if ($seg_r['code'] >= 400) {
        echo "SEGMENT ERROR BODY: " . substr($seg_body, 0, 400) . "\n";
    } elseif ($seg_len > 0) {
        // Check TS sync byte
        $first_byte = ord($seg_body[0]);
        echo "FIRST_BYTE: 0x" . strtoupper(dechex($first_byte)) . " (0x47=valid TS)\n";
        // Show raw text if not binary
        if ($first_byte < 32 || $first_byte === 0x47) {
            echo "Looks like binary/TS data — GOOD\n";
        } else {
            echo "WARNING: First byte 0x".dechex($first_byte)." — may be HTML/text error\n";
            echo "Body preview: " . substr($seg_body, 0, 300) . "\n";
        }
    } else {
        echo "EMPTY BODY\n";
    }

    if ($seg_r['err']) echo "CURL_ERR: {$seg_r['err']}\n";
    echo "\n";
}

echo "=== AUDIT COMPLETE ===\n";
