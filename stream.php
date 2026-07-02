<?php
/**
 * stream.php — Live Stream Extraction + Validation API
 *
 * Fetches fifalive.click on every request and validates each server URL
 * by making a quick HEAD request to ensure it returns a valid M3U8 response.
 * 
 * Skips:
 *  - HTTP 429 (rate limited)
 *  - HTTP 403 (forbidden / blocked)
 *  - Non-M3U8 responses
 *  - Timeouts / connection failures
 *
 * Response (always HTTP 200 so fetch() never throws):
 *   { "ok": true,  "source": "fifalive", "servers": [...validated...] }
 *   { "ok": true,  "source": "fallback", "servers": [test_stream] }
 */

// Generous time limit for validation requests
set_time_limit(45);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

// ── Fallback stream (always appended last) ───────────────────────────────────
$FALLBACK = [
    'name'        => 'Test Stream',
    'group'       => 'Fallback',
    'logo'        => '',
    'raw_url'     => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
    'proxy_url'   => 'proxy.php?url=' . rawurlencode('https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8') . '&raw=true',
    'is_fallback' => true,
];

// ── Fix malformed URLs from the source M3U8 ──────────────────────────────────
// e.g. workers*dev → workers.dev
function sanitiseUrl(string $raw): string {
    $url = trim($raw);
    if (preg_match('@^(https?://)([^/?#]+)(.*)$@i', $url, $m)) {
        return $m[1] . str_replace('*', '.', $m[2]) . $m[3];
    }
    return $url;
}

// ── Detect M3U8 response ─────────────────────────────────────────────────────
function isM3u8(string $body, string $ctype): bool {
    if (stripos($ctype, 'mpegurl') !== false)        return true;
    if (stripos($ctype, 'vnd.apple') !== false)      return true;
    $t = ltrim($body);
    if (stripos($t, '#EXTM3U')  === 0)               return true;
    if (stripos($t, '#EXT-X-')  === 0)               return true;
    return false;
}

// ── Parse master M3U8 into server entry array ────────────────────────────────
function parseMaster(string $body): array {
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
            if (preg_match('/tvg-name=["\']([^"\']+)["\']/i', $line, $m))    $name  = $m[1];
            if (preg_match('/group-title=["\']([^"\']+)["\']/i', $line, $m)) $group = $m[1];
            if (preg_match('/tvg-logo=["\']([^"\']+)["\']/i', $line, $m))    $logo  = $m[1];
            if ($name === '' && preg_match('/,(.+)$/', $line, $m))           $name  = trim($m[1]);
            $pending = [
                'name'  => $name  ?: ('Server ' . (count($servers) + 1)),
                'group' => $group ?: 'Live',
                'logo'  => $logo,
            ];
            continue;
        }

        if ($line[0] !== '#') {
            $clean = sanitiseUrl($line);
            if (filter_var($clean, FILTER_VALIDATE_URL)) {
                $servers[] = [
                    'name'      => $pending['name']  ?: ('Server ' . (count($servers) + 1)),
                    'group'     => $pending['group'] ?: 'Live',
                    'logo'      => $pending['logo']  ?? '',
                    'raw_url'   => $clean,
                    'proxy_url' => 'proxy.php?url=' . rawurlencode($clean) . '&raw=true',
                ];
            }
            $pending = ['name' => '', 'group' => 'Live', 'logo' => ''];
        }
    }

    return $servers;
}

// ── Validate a single server URL ─────────────────────────────────────────────
// Returns: ['ok' => bool, 'reason' => string]
function validateServer(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_NOBODY         => false,  // GET request to fetch body
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, */*',
            'Referer: https://fifalive.click/',
            'Origin: https://fifalive.click',
        ],
    ]);

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err  = curl_error($ch);
    curl_close($ch);

    // Connection failure
    if ($body === false || $code === 0) {
        return ['ok' => false, 'reason' => $err ?: 'Connection failed'];
    }

    // Rate limited
    if ($code === 429) {
        return ['ok' => false, 'reason' => 'Rate limited (429)'];
    }

    // Forbidden / blocked
    if ($code === 403) {
        return ['ok' => false, 'reason' => 'Forbidden (403)'];
    }

    // Server error
    if ($code >= 500) {
        return ['ok' => false, 'reason' => "Server error ($code)"];
    }

    // Not 2xx
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'reason' => "HTTP $code"];
    }

    // Check if response is M3U8
    if (!isM3u8($body, $type)) {
        return ['ok' => false, 'reason' => 'Not M3U8 (got ' . $type . ')'];
    }

    // Success
    return ['ok' => true, 'reason' => 'OK'];
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
    CURLOPT_TIMEOUT        => 12,   // hard cap — must leave time for PHP to respond
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
$errors = [];

if ($body === false || $body === '') {
    $errors[] = 'cURL failed: ' . $curlErr;
} elseif ($code < 200 || $code >= 300) {
    $errors[] = "fifalive.click returned HTTP $code";
    $body = '';
} elseif (!isM3u8($body, $ctype)) {
    // Got HTML — paywall or redirect
    $errors[] = 'fifalive.click returned non-M3U8 (CT=' . $ctype . '). Possible paywall.';
    $body = '';
}

if ($body !== false && $body !== '') {
    $parsed = parseMaster($body);
    
    // Validate each server in parallel with curl_multi
    $validServers = [];
    $validationErrors = [];
    
    foreach ($parsed as $srv) {
        $result = validateServer($srv['raw_url']);
        
        if ($result['ok']) {
            $validServers[] = $srv;
        } else {
            $validationErrors[] = $srv['name'] . ': ' . $result['reason'];
        }
    }
    
    $servers = $validServers;
    $errors = array_merge($errors, $validationErrors);
} else {
    $servers = [];
}

// Always append fallback at the end
$servers[] = $FALLBACK;

echo json_encode([
    'ok'      => true,
    'source'  => count($servers) > 1 ? 'fifalive' : 'fallback',
    'count'   => max(0, count($servers) - 1), // live count excludes fallback
    'servers' => $servers,
    'errors'  => $errors,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
