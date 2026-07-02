<?php
/**
 * proxy.php — Smart HLS Media Proxy
 *
 * ROUTING LOGIC (based on live network audit, July 2026):
 *
 *   M3U8 playlists  → always fetched server-side (proxy handles Referer/Origin spoofing)
 *   TS segments     → two paths:
 *     a) TikTok CDN (p*-common-sign.tiktokcdn.com) — these are served as global media
 *        with no CORS restriction on browsers. However, datacenter IPs get blocked.
 *        Solution: return a 302 redirect so the browser fetches them directly.
 *     b) All other segments — proxy server-side as normal.
 *
 * Why Server 1 (ToffeeLive) is NOT proxied:
 *   - ToffeeLive CDN blocks datacenter IPs (returns connection error → 502)
 *   - ToffeeLive CDN sends NO Access-Control-Allow-Origin header
 *   - Both proxy AND direct browser fetch fail — stream.php marks it direct=false
 *     and index.php skips it; only Server 4 (cinecdn) is viable
 *
 * SSRF protections: loopback, link-local, and private ranges are blocked.
 */

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
if (preg_match('~^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|169\.254\.)~', $targetHost)) {
    http_response_code(403); die('Private address blocked.');
}

// NOTE: TikTok CDN segments (.image URLs from tiktokcdn.com) are proxied
// server-side like everything else. A 302 redirect cannot be used here because
// HLS.js fetches segments via XHR/fetch with mode:cors — the browser would
// follow the redirect to tiktokcdn.com, which has no CORS headers, and block
// the response. Full server-side proxying is the only viable path.

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Resolve a relative href against a base URL. */
function absoluteUrl(string $href, string $baseUrl): string {
    if (preg_match('~^https?://~i', $href)) return $href;

    $p      = parse_url($baseUrl);
    $scheme = $p['scheme'] ?? 'https';
    $host   = $p['host']   ?? '';
    $path   = $p['path']   ?? '/';

    if (substr($href, 0, 2) === '//') return $scheme . ':' . $href;
    if ($href[0] === '/')              return $scheme . '://' . $host . $href;

    // dirname() can return false on malformed paths in PHP 8 — null-coalesce to '/'
    $dir = (substr($path, -1) === '/' || strpos(basename($path ?? ''), '.') === false)
        ? rtrim($path ?? '', '/') . '/'
        : rtrim(dirname($path ?? '/') ?: '/', '/') . '/';

    return $scheme . '://' . $host . $dir . ltrim($href, './');
}

/**
 * Rewrite every URL in an M3U8 to route through this proxy.
 */
function rewriteM3u8(string $body, string $baseUrl): string {
    $lines = preg_split('~\r?\n~', $body);

    foreach ($lines as $i => $line) {
        $t = trim($line);
        if ($t === '') continue;

        if ($t[0] !== '#') {
            $abs       = absoluteUrl($t, $baseUrl);
            $lines[$i] = 'proxy.php?url=' . rawurlencode($abs) . '&raw=true';
        } else {
            // Use ~ delimiter so URLs inside URI="..." never conflict with the pattern.
            // Capture group 1 = quote char, group 2 = URI value, \1 = matching close quote.
            // Falls back to original line if regex errors or finds no match.
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
 * Pick the correct Referer/Origin for each upstream host.
 * CDNs validate this header — sending the wrong value causes 403.
 */
function inferReferer(string $url): string {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

    $map = [
        // Server 4 — cinecdn Cloudflare Worker requires fifalive origin
        'cinecdn.workers.dev'        => 'https://fifalive.click/',
        'nextgoal.workers.dev'       => 'https://fifalive.click/',
        'smtahmidx.workers.dev'      => 'https://fifalive.click/',
        // TikTok CDN segments — served via fx.cinecdn.workers.dev
        // Must use the Worker URL as referer, not fifalive directly
        'tiktokcdn.com'              => 'https://fx.cinecdn.workers.dev/',
        'tiktok.com'                 => 'https://fx.cinecdn.workers.dev/',
        // ToffeeLive CDN (kept for completeness; blocked by IP regardless)
        'prod-cdn01-live.toffeelive' => 'https://toffeelive.com/',
        'toffeelive.com'             => 'https://toffeelive.com/',
    ];

    foreach ($map as $needle => $referer) {
        if (str_contains($host, $needle)) return $referer;
    }

    return 'https://fifalive.click/';
}

// ── Determine client IP to forward ───────────────────────────────────────────
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
    CURLOPT_FOLLOWLOCATION => true,          // follow 301/302 redirects
    CURLOPT_MAXREDIRS      => 8,
    // Mobile Safari UA — some CDNs (ToffeeLive, Workers) serve different
    // responses or skip bot-detection for mobile browser user agents
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,  // force IPv4 — broken IPv6 on some cPanel hosts causes timeouts
    CURLOPT_CONNECTTIMEOUT => 15,            // raised from 10s — gives slow CDNs time to respond
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
if ($content === false) {
    http_response_code(502);
    die('Upstream fetch failed: ' . htmlspecialchars($curlErr));
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    die('Upstream HTTP ' . $httpCode);
}

// ── Detect M3U8 ──────────────────────────────────────────────────────────────
$looksLikeM3u8 =
    stripos($ctype, 'mpegurl')               !== false ||
    stripos($ctype, 'application/vnd.apple') !== false ||
    (is_string($content) && stripos(ltrim($content), '#EXTM3U') === 0) ||
    (is_string($content) && stripos(ltrim($content), '#EXT-X-')  === 0) ||
    preg_match('~\.m3u8(\?|$)~i', parse_url($finalUrl, PHP_URL_PATH) ?? '');

if ($looksLikeM3u8) {
    $rewritten = rewriteM3u8($content, $finalUrl);
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    http_response_code($httpCode ?: 200);
    echo $rewritten;
    exit;
}

// ── Binary media pass-through ─────────────────────────────────────────────────
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
    // TikTok CDN sends TS bytes disguised as .image
    'image' => 'video/MP2T',
];

if (isset($extMap[$ext])) {
    $ctype = $extMap[$ext];
} elseif (
    $ctype === '' ||
    stripos($ctype, 'text/')        !== false ||
    stripos($ctype, 'image/')       !== false ||
    stripos($ctype, 'octet-stream') !== false
) {
    $ctype = (strlen($content) > 0 && ord($content[0]) === 0x47)
        ? 'video/MP2T'
        : ($ctype ?: 'application/octet-stream');
}

if (ob_get_level() > 0) ob_end_clean();

header('Content-Type: ' . $ctype);
header('Content-Length: ' . strlen($content));
http_response_code($httpCode ?: 200);
echo $content;
