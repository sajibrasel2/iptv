<?php
/**
 * proxy.php — Pure HLS Media Proxy
 *
 * Routes ALL stream URLs (M3U8 playlists + TS segments + encryption keys)
 * through this server-side proxy so the browser never hits the upstream
 * CDN directly. This keeps Referer/Origin spoofing intact for every request.
 *
 * Segment URLs are NOT passed direct to the browser — the previous
 * "direct bypass" approach failed because:
 *   - nextgoal CF Worker blocks DC-IP OPTIONS requests (no CORS at all)
 *   - TikTok CDN segment tokens expire before the browser fetches them
 *
 * CORS headers are set on every response so HLS.js can read the data.
 * PHP chunked output flushes bytes to the browser as they arrive, which
 * prevents cPanel's 30-second max_execution_time from killing a long
 * binary-segment transfer mid-stream.
 */

// Give PHP enough time for large segment transfers
set_time_limit(60);

// ── CORS + cache headers ─────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Origin, Accept, Accept-Language');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Validate ?url= ───────────────────────────────────────────────────────────
$rawInput = isset($_GET['url']) ? trim($_GET['url']) : '';
if ($rawInput === '') {
    http_response_code(400);
    die('Missing url parameter.');
}

$targetUrl = filter_var($rawInput, FILTER_VALIDATE_URL);
if ($targetUrl === false) {
    http_response_code(400);
    die('Invalid url parameter.');
}

$scheme = strtolower(parse_url($targetUrl, PHP_URL_SCHEME) ?? '');
if (!in_array($scheme, ['http', 'https'], true)) {
    http_response_code(400);
    die('Only http/https URLs allowed.');
}

// ── SSRF block-list ──────────────────────────────────────────────────────────
$targetHost = strtolower(parse_url($targetUrl, PHP_URL_HOST) ?? '');
$ownHost    = strtolower($_SERVER['HTTP_HOST'] ?? '');

foreach (['localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254',
          '100.100.100.200', 'metadata.google.internal'] as $b) {
    if ($targetHost === $b) { http_response_code(403); die('Blocked.'); }
}
if ($targetHost !== '' && $targetHost === $ownHost) {
    http_response_code(403); die('Self-request blocked.');
}
if (preg_match('/^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|169\.254\.)/', $targetHost)) {
    http_response_code(403); die('Private address blocked.');
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Resolve a relative href against a base URL. */
function absoluteUrl(string $href, string $baseUrl): string {
    if (preg_match('#^https?://#i', $href)) return $href;

    $p      = parse_url($baseUrl);
    $scheme = $p['scheme'] ?? 'https';
    $host   = $p['host']   ?? '';
    $path   = $p['path']   ?? '/';

    if (substr($href, 0, 2) === '//') return $scheme . ':' . $href;
    if ($href[0] === '/')              return $scheme . '://' . $host . $href;

    $dir = (substr($path, -1) === '/' || strpos(basename($path), '.') === false)
        ? rtrim($path, '/') . '/'
        : rtrim(dirname($path), '/') . '/';

    return $scheme . '://' . $host . $dir . ltrim($href, './');
}

/**
 * Rewrite every URL in an M3U8 so ALL requests route through this proxy.
 * No direct-to-CDN bypass — every byte comes through here so we can
 * inject the correct Referer/Origin on every request.
 */
function rewriteM3u8(string $body, string $baseUrl): string {
    $lines = preg_split('/\r?\n/', $body);

    foreach ($lines as $i => $line) {
        $t = trim($line);
        if ($t === '') continue;

        if ($t[0] !== '#') {
            // Segment or sub-playlist — always proxy
            $abs       = absoluteUrl($t, $baseUrl);
            $lines[$i] = 'proxy.php?url=' . rawurlencode($abs) . '&raw=true';
        } else {
            // Tag lines — rewrite URI="..." attributes (keys, maps)
            $lines[$i] = preg_replace_callback(
                '/URI=["\']([^"\']+)["\']/i',
                function (array $m) use ($baseUrl): string {
                    $abs = absoluteUrl($m[1], $baseUrl);
                    return 'URI="proxy.php?url=' . rawurlencode($abs) . '&raw=true"';
                },
                $t
            );
        }
    }

    return implode("\n", $lines);
}

/**
 * Pick the correct Referer/Origin for each upstream host.
 * CDNs validate this header — sending the wrong value causes 403.
 */
function inferReferer(string $url): string {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

    $map = [
        'prod-cdn01-live.toffeelive' => 'https://toffeelive.com/',
        'toffeelive.com'             => 'https://toffeelive.com/',
        'nextgoal.workers.dev'       => 'https://fifalive.click/',
        'cinecdn.workers.dev'        => 'https://fifalive.click/',
        'smtahmidx.workers.dev'      => 'https://fifalive.click/',
        // TikTok CDN segments served via fx.cinecdn.workers.dev
        'tiktokcdn.com'              => 'https://fx.cinecdn.workers.dev/',
        'tiktok.com'                 => 'https://fx.cinecdn.workers.dev/',
        // Rockstreamer CDN segments served via nextgoal / smtahmidx workers
        'rockstreamer.com'           => 'https://live3.nextgoal.workers.dev/',
        'livecdn.rockstreamer'       => 'https://live3.nextgoal.workers.dev/',
        'tc-sg.rockstreamer'         => 'https://live.smtahmidx.workers.dev/',
    ];

    foreach ($map as $needle => $referer) {
        if (str_contains($host, $needle)) return $referer;
    }

    return 'https://fifalive.click/';
}

// ── Determine client IP to forward ───────────────────────────────────────────
// Forward the visitor's real IP in X-Forwarded-For etc.
// Note: Cloudflare checks the actual TCP IP, not these headers, but some
// CF Worker scripts do inspect them for additional logic.
$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP']
         ?? $_SERVER['HTTP_X_FORWARDED_FOR']
         ?? $_SERVER['HTTP_X_REAL_IP']
         ?? $_SERVER['REMOTE_ADDR']
         ?? '';

if (str_contains($clientIp, ',')) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}
if (!filter_var($clientIp, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $clientIp = '';
}

// ── Build request headers ─────────────────────────────────────────────────────
$referer = inferReferer($targetUrl);
$parsed  = parse_url($referer);
$origin  = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

$headers = [
    'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, video/MP2T, */*',
    'Accept-Language: en-US,en;q=0.9',
    'Referer: '  . $referer,
    'Origin: '   . $origin,
    'Connection: keep-alive',
];
if ($clientIp !== '') {
    $headers[] = 'X-Forwarded-For: '  . $clientIp;
    $headers[] = 'CF-Connecting-IP: ' . $clientIp;
    $headers[] = 'True-Client-IP: '   . $clientIp;
    $headers[] = 'X-Real-IP: '        . $clientIp;
}

// ── Fetch the upstream resource ───────────────────────────────────────────────
$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 8,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 45,   // generous for large TS segments
    CURLOPT_ENCODING       => '',
    CURLOPT_HTTPHEADER     => $headers,
]);

$content  = curl_exec($ch);
$httpCode = (int)    curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype    = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlErr  = curl_error($ch);
$finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $targetUrl;
curl_close($ch);

// ── Error responses ───────────────────────────────────────────────────────────
if ($content === false) {
    http_response_code(502);
    die('Upstream fetch failed: ' . htmlspecialchars($curlErr));
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    die('Upstream HTTP ' . $httpCode);
}

// ── Detect M3U8 ──────────────────────────────────────────────────────────────
// Some CDNs (toffeelive) send M3U8 as text/plain — check body content too.
$looksLikeM3u8 =
    stripos($ctype, 'mpegurl')               !== false ||
    stripos($ctype, 'application/vnd.apple') !== false ||
    (is_string($content) && stripos(ltrim($content), '#EXTM3U') === 0) ||
    (is_string($content) && stripos(ltrim($content), '#EXT-X-')  === 0) ||
    preg_match('/\.m3u8(\?|$)/i', parse_url($finalUrl, PHP_URL_PATH) ?? '');

if ($looksLikeM3u8) {
    $rewritten = rewriteM3u8($content, $finalUrl);
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    http_response_code($httpCode ?: 200);
    echo $rewritten;
    exit;
}

// ── Binary media pass-through ─────────────────────────────────────────────────
// Determine the correct Content-Type for TS/AAC/MP4 segments.
// Many CDNs send wrong or missing types; detect by URL extension first,
// then fall back to checking the raw TS sync byte (0x47).
$ext = strtolower(pathinfo(parse_url($finalUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
$extMap = [
    'ts'    => 'video/MP2T',
    'aac'   => 'audio/aac',
    'mp4'   => 'video/mp4',
    'm4s'   => 'video/iso.segment',
    'fmp4'  => 'video/mp4',
    'm4v'   => 'video/mp4',
    'key'   => 'application/octet-stream',
    'bin'   => 'application/octet-stream',
    'mp3'   => 'audio/mpeg',
    // TikTok CDN sends TS bytes with .image extension
    'image' => 'video/MP2T',
];

if (isset($extMap[$ext])) {
    $ctype = $extMap[$ext];
} elseif (
    $ctype === '' ||
    stripos($ctype, 'text/')       !== false ||
    stripos($ctype, 'image/')      !== false ||
    stripos($ctype, 'octet-stream') !== false
) {
    // Last resort: check TS sync byte (0x47 = 71 decimal)
    $ctype = (strlen($content) > 0 && ord($content[0]) === 0x47)
        ? 'video/MP2T'
        : ($ctype ?: 'application/octet-stream');
}

// Stream the binary content to the browser
// ob_end_clean ensures no buffering swallows the bytes
if (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $ctype);
header('Content-Length: ' . strlen($content));
http_response_code($httpCode ?: 200);
echo $content;
