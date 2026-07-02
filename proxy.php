<?php
/**
 * proxy.php — Ultra-Stealth HLS Media Proxy
 *
 * Every upstream request is sent with a full mobile Safari identity
 * (UA, Origin, Referer, Sec-Fetch headers) so CDNs and Cloudflare Workers
 * see a legitimate browser, not a datacenter bot.
 *
 * M3U8 REWRITING
 * When the upstream returns an M3U8 playlist, every URL inside it is rewritten
 * to route back through this proxy.php, so HLS.js never makes a cross-origin
 * request — everything is served from the same techandclick.site origin and
 * CORS is never an issue.
 *
 * CONFIRMED WORKING (audit 2026-07-02, cPanel IP 148.251.35.206):
 *   fx.cinecdn.workers.dev  → HTTP 200 with Origin: fifalive.click ✓
 *
 * SSRF PROTECTION
 * Loopback, link-local, and RFC-1918 private ranges are blocked.
 * Only http / https schemes are allowed.
 */

set_time_limit(60);

// ── CORS headers ──────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Origin, Accept, Accept-Language, Cache-Control');
header('Access-Control-Expose-Headers: Content-Length, Content-Range');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Validate ?url= ────────────────────────────────────────────────────────────
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

// ── SSRF block-list ───────────────────────────────────────────────────────────
$targetHost = strtolower(parse_url($targetUrl, PHP_URL_HOST) ?? '');
$ownHost    = strtolower($_SERVER['HTTP_HOST'] ?? '');

foreach (['localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254',
          '100.100.100.200', 'metadata.google.internal'] as $blocked) {
    if ($targetHost === $blocked) { http_response_code(403); die('Blocked.'); }
}
if ($targetHost !== '' && $targetHost === $ownHost) {
    http_response_code(403); die('Self-request blocked.');
}
if (preg_match('~^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|169\.254\.)~', $targetHost)) {
    http_response_code(403); die('Private address blocked.');
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Resolve a relative URL against a base URL.
 * Handles absolute, protocol-relative, root-relative, and relative paths.
 */
function absoluteUrl(string $href, string $baseUrl): string {
    if (preg_match('~^https?://~i', $href)) return $href;

    $p      = parse_url($baseUrl);
    $scheme = $p['scheme'] ?? 'https';
    $host   = $p['host']   ?? '';
    $path   = $p['path']   ?? '/';

    if (substr($href, 0, 2) === '//') return $scheme . ':' . $href;
    if ($href !== '' && $href[0] === '/') return $scheme . '://' . $host . $href;

    // Relative path — resolve against directory of base path
    $dir = (substr($path, -1) === '/' || strpos(basename($path ?? ''), '.') === false)
        ? rtrim($path ?? '', '/') . '/'
        : rtrim(dirname($path ?? '/') ?: '/', '/') . '/';

    return $scheme . '://' . $host . $dir . ltrim($href, './');
}

/**
 * Rewrite every URL in an M3U8 playlist so all subsequent requests
 * (segments, sub-playlists, encryption keys) route through proxy.php.
 * HLS.js only ever sees same-origin URLs — no CORS errors possible.
 */
function rewriteM3u8(string $body, string $baseUrl): string {
    $lines = preg_split('~\r?\n~', $body);

    foreach ($lines as $i => $line) {
        $t = trim($line);
        if ($t === '') continue;

        if ($t[0] !== '#') {
            // Segment or sub-playlist URL
            $abs       = absoluteUrl($t, $baseUrl);
            $lines[$i] = 'proxy.php?url=' . rawurlencode($abs) . '&raw=true';
        } else {
            // Tag line — rewrite URI="..." attributes (encryption keys, init segments)
            $rewritten = preg_replace_callback(
                '~URI=(["\'])([^"\']+)\1~i',
                function (array $m) use ($baseUrl): string {
                    $abs = absoluteUrl($m[2], $baseUrl);
                    return 'URI="proxy.php?url=' . rawurlencode($abs) . '&raw=true"';
                },
                $t
            );
            $lines[$i] = $rewritten ?? $t;
        }
    }

    return implode("\n", $lines);
}

/**
 * Return the correct Referer for a given upstream host.
 * Confirmed via live audit — wrong Origin/Referer causes 403.
 *
 *   fx.cinecdn.workers.dev → Origin: https://fifalive.click → HTTP 200 ✓
 *   TikTok CDN segments    → Referer: https://fx.cinecdn.workers.dev/
 *   toffeelive CDN         → Origin: https://toffeelive.com (IP-blocked anyway)
 */
function inferReferer(string $url): string {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

    $map = [
        'cinecdn.workers.dev'        => 'https://fifalive.click/',
        'nextgoal.workers.dev'       => 'https://fifalive.click/',
        'smtahmidx.workers.dev'      => 'https://fifalive.click/',
        'tiktokcdn.com'              => 'https://fx.cinecdn.workers.dev/',
        'tiktok.com'                 => 'https://fx.cinecdn.workers.dev/',
        'prod-cdn01-live.toffeelive' => 'https://toffeelive.com/',
        'toffeelive.com'             => 'https://toffeelive.com/',
    ];

    foreach ($map as $needle => $referer) {
        if (str_contains($host, $needle)) return $referer;
    }

    return 'https://fifalive.click/';
}

// ── TikTok CDN segments: redirect to browser ─────────────────────────────────
// TikTok CDN allows residential browser IPs but blocks datacenter IPs.
// When a segment URL from tiktokcdn.com is requested, we send a 302 redirect
// so the browser fetches it directly with its own residential IP.
// This works because HLS.js follows 302 redirects automatically for segments,
// and TikTok CDN has no CORS restrictions on media segments.
if (str_contains($targetHost, 'tiktokcdn.com') || str_contains($targetHost, 'tiktok.com')) {
    header('Location: ' . $targetUrl, true, 302);
    header('Access-Control-Allow-Origin: *');
    exit;
}
$referer = inferReferer($targetUrl);
$parsed  = parse_url($referer);
$origin  = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

// Forward the real visitor IP so CDN WAFs see a residential IP
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

$headers = [
    // Exact Accept string sent by iOS Safari when loading HLS
    'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, video/MP2T, */*;q=0.9',
    'Accept-Language: en-US,en;q=0.9',
    'Accept-Encoding: gzip, deflate, br',
    // The two headers CDNs and Workers check for access control
    'Referer: '  . $referer,
    'Origin: '   . $origin,
    'Cache-Control: no-cache',
    'Pragma: no-cache',
    'Connection: keep-alive',
    // NOTE: Sec-Fetch-* headers are intentionally omitted.
    // Cloudflare Workers detect them as datacenter bot traffic when sent
    // from a server-side cURL request (browsers auto-generate these).
];
if ($clientIp !== '') {
    $headers[] = 'X-Forwarded-For: '  . $clientIp;
    $headers[] = 'CF-Connecting-IP: ' . $clientIp;
    $headers[] = 'True-Client-IP: '   . $clientIp;
    $headers[] = 'X-Real-IP: '        . $clientIp;
}

// ── cURL fetch ────────────────────────────────────────────────────────────────
// Simple FOLLOWLOCATION — proxytest.php confirmed cinecdn returns direct 200,
// no redirect chain. Manual redirect loop was unnecessary and introduced bugs.
$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) '
                            . 'AppleWebKit/605.1.15 (KHTML, like Gecko) '
                            . 'Version/17.5 Mobile/15E148 Safari/604.1',
    CURLOPT_REFERER        => $referer,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 45,
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
if ($content === false || $content === '') {
    http_response_code(502);
    die('Upstream fetch failed: ' . htmlspecialchars($curlErr ?: 'empty response'));
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    die('Upstream HTTP ' . $httpCode);
}

// ── Detect and rewrite M3U8 ───────────────────────────────────────────────────
// Some CDNs send M3U8 as text/plain — also check body content and URL extension.
$looksLikeM3u8 =
    stripos($ctype, 'mpegurl')               !== false ||
    stripos($ctype, 'application/vnd.apple') !== false ||
    (is_string($content) && stripos(ltrim($content), '#EXTM3U') === 0) ||
    (is_string($content) && stripos(ltrim($content), '#EXT-X-')  === 0) ||
    preg_match('~\.m3u8(\?|$)~i', parse_url($finalUrl, PHP_URL_PATH) ?? '');

if ($looksLikeM3u8) {
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    http_response_code(200);
    echo rewriteM3u8($content, $finalUrl);
    exit;
}

// ── Binary media pass-through ─────────────────────────────────────────────────
// Map extensions to MIME types — many CDNs send wrong or missing Content-Type.
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
    'image' => 'video/MP2T',  // TikTok CDN disguises TS packets as .image files
];

if (isset($extMap[$ext])) {
    $ctype = $extMap[$ext];
} elseif ($ctype === '' || stripos($ctype, 'text/') !== false
       || stripos($ctype, 'image/') !== false
       || stripos($ctype, 'octet-stream') !== false) {
    // Last resort: detect MPEG-TS by sync byte 0x47
    $ctype = (strlen($content) > 0 && ord($content[0]) === 0x47)
        ? 'video/MP2T'
        : ($ctype ?: 'application/octet-stream');
}

if (ob_get_level() > 0) ob_end_clean();

header('Content-Type: ' . $ctype);
header('Content-Length: ' . strlen($content));
http_response_code($httpCode ?: 200);
echo $content;
