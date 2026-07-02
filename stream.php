<?php
/**
 * stream.php — Live Stream Extraction API
 *
 * Fetches fifalive.click, parses the M3U8, and returns all servers instantly.
 * No per-server validation — HLS.js handles that at play time.
 *
 * Each server entry carries a "direct" flag:
 *   "direct": true  → CDN allows browser CORS; index.php sends raw_url straight
 *                      to HLS.js, bypassing proxy.php (avoids datacenter-IP block)
 *   "direct": false → Must route through proxy.php (applies Referer spoofing)
 *
 * ToffeeLive CDN blocks datacenter IPs with HTTP 502, but it serves with
 * Access-Control-Allow-Origin: * so the browser can fetch it directly.
 */

set_time_limit(20);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

// ── Fallback stream ───────────────────────────────────────────────────────────
$FALLBACK = [
    'name'        => 'Test Stream',
    'group'       => 'Fallback',
    'logo'        => '',
    'raw_url'     => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
    'proxy_url'   => 'proxy.php?url=' . rawurlencode('https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8') . '&raw=true',
    'direct'      => true,   // mux test CDN has open CORS
    'is_fallback' => true,
];

// ── Hosts that block datacenter IPs but allow browser CORS ───────────────────
// These must be fetched DIRECTLY by the browser, not proxied through cPanel.
$DIRECT_HOSTS = [
    'toffeelive.com',
    'prod-cdn01-live.toffeelive.com',
    'prod-cdn02-live.toffeelive.com',
    'prod-cdn03-live.toffeelive.com',
];

function isDirect(string $url, array $directHosts): bool {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    foreach ($directHosts as $h) {
        if ($host === $h || str_ends_with($host, '.' . $h)) return true;
    }
    return false;
}

// ── Fix malformed URLs (e.g. workers*dev -> workers.dev) ─────────────────────
function sanitiseUrl(string $raw): string {
    $url = trim($raw);
    if (preg_match('@^(https?://)([^/?#]+)(.*)$@i', $url, $m)) {
        return $m[1] . str_replace('*', '.', $m[2]) . $m[3];
    }
    return $url;
}

// ── Detect M3U8 response ─────────────────────────────────────────────────────
function isM3u8(string $body, string $ctype): bool {
    if (stripos($ctype, 'mpegurl')   !== false) return true;
    if (stripos($ctype, 'vnd.apple') !== false) return true;
    $t = ltrim($body);
    if (stripos($t, '#EXTM3U') === 0) return true;
    if (stripos($t, '#EXT-X-') === 0) return true;
    return false;
}

// ── Parse master M3U8 ────────────────────────────────────────────────────────
function parseMaster(string $body, array $directHosts): array {
    $lines   = preg_split('/\r?\n/', trim($body));
    $servers = [];
    $pending = ['name' => '', 'group' => 'Live', 'logo' => ''];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (stripos($line, '#EXTINF') === 0) {
            $name  = '';
            $group = 'Live';
            $logo  = '';
            if (preg_match('/tvg-name=["\']([^"\']+)["\']/i',    $line, $m)) $name  = $m[1];
            if (preg_match('/group-title=["\']([^"\']+)["\']/i', $line, $m)) $group = $m[1];
            if (preg_match('/tvg-logo=["\']([^"\']+)["\']/i',    $line, $m)) $logo  = $m[1];
            if ($name === '' && preg_match('/,(.+)$/', $line, $m))           $name  = trim($m[1]);
            $pending = [
                'name'  => $name  ?: ('Server ' . (count($servers) + 1)),
                'group' => $group ?: 'Live',
                'logo'  => $logo,
            ];
            continue;
        }

        if (isset($line[0]) && $line[0] !== '#') {
            $clean  = sanitiseUrl($line);
            $direct = isDirect($clean, $directHosts);

            if (filter_var($clean, FILTER_VALIDATE_URL)) {
                $servers[] = [
                    'name'      => $pending['name']  ?: ('Server ' . (count($servers) + 1)),
                    'group'     => $pending['group'] ?: 'Live',
                    'logo'      => $pending['logo']  ?? '',
                    'raw_url'   => $clean,
                    // proxy_url still built for non-direct servers
                    'proxy_url' => 'proxy.php?url=' . rawurlencode($clean) . '&raw=true',
                    // direct=true means index.php will use raw_url instead of proxy_url
                    'direct'    => $direct,
                ];
            }
            $pending = ['name' => '', 'group' => 'Live', 'logo' => ''];
        }
    }

    return $servers;
}

// ── Fetch fifalive.click ─────────────────────────────────────────────────────
$ch = curl_init('https://fifalive.click/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_ENCODING       => '',
    CURLOPT_HTTPHEADER     => [
        'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: https://fifalive.click/',
        'Origin: https://fifalive.click',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
    ],
]);

$body    = curl_exec($ch);
$code    = (int)    curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype   = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlErr = curl_error($ch);
curl_close($ch);

// ── Build response ────────────────────────────────────────────────────────────
$errors  = [];
$servers = [];

if ($body === false || $body === '') {
    $errors[] = 'cURL failed: ' . ($curlErr ?: 'empty response');
} elseif ($code < 200 || $code >= 300) {
    $errors[] = "fifalive.click returned HTTP $code";
} elseif (!isM3u8($body, $ctype)) {
    $errors[] = 'fifalive.click returned non-M3U8 (CT=' . $ctype . ') — possible paywall/captcha';
} else {
    $servers = parseMaster($body, $DIRECT_HOSTS);
}

$servers[] = $FALLBACK;

echo json_encode([
    'ok'      => true,
    'source'  => count($servers) > 1 ? 'fifalive' : 'fallback',
    'count'   => max(0, count($servers) - 1),
    'servers' => $servers,
    'errors'  => $errors,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
