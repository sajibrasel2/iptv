<?php
/**
 * proxy.php — Pure HLS Media Proxy
 *
 * Handles THREE content types only — no HTML, no iframes, no scraping:
 *   1. M3U8 playlists  — rewrites every segment / key URL through this proxy
 *   2. TS segments     — passes raw bytes with correct Content-Type
 *   3. Encryption keys — passes raw bytes as application/octet-stream
 *
 * SSRF protections:
 *   - Allows only http / https schemes
 *   - Blocks loopback and link-local addresses
 *   - Blocks requests to the proxy's own host
 *
 * Usage (always called with &raw=true from HLS.js):
 *   proxy.php?url=<rawurlencode(absolute_url)>&raw=true
 */

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
$rawInput  = isset($_GET['url']) ? trim($_GET['url']) : '';
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

// SSRF block-list
$targetHost = strtolower(parse_url($targetUrl, PHP_URL_HOST) ?? '');
$ownHost    = strtolower($_SERVER['HTTP_HOST'] ?? '');
$ssrfBlock  = [
    'localhost', '127.0.0.1', '::1', '0.0.0.0',
    '169.254.169.254',   // AWS metadata
    '100.100.100.200',   // Alibaba metadata
    'metadata.google.internal',
];
foreach ($ssrfBlock as $b) {
    if ($targetHost === $b) { http_response_code(403); die('Blocked.'); }
}
// Block requests back to ourselves
if ($targetHost !== '' && $targetHost === $ownHost) {
    http_response_code(403);
    die('Self-request blocked.');
}
// Block RFC-1918 / link-local
if (preg_match('/^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|169\.254\.)/', $targetHost)) {
    http_response_code(403);
    die('Private address blocked.');
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Resolve a potentially relative $href against $baseUrl.
 */
function absoluteUrl(string $href, string $baseUrl): string {
    if (preg_match('#^https?://#i', $href)) return $href;

    $p      = parse_url($baseUrl);
    $scheme = $p['scheme'] ?? 'https';
    $host   = $p['host']   ?? '';
    $path   = $p['path']   ?? '/';

    // Scheme-relative
    if (substr($href, 0, 2) === '//') return $scheme . ':' . $href;
    // Root-relative
    if ($href[0] === '/') return $scheme . '://' . $host . $href;

    // Relative — resolve against directory of base path
    $dir = (substr($path, -1) === '/' || strpos(basename($path), '.') === false)
        ? rtrim($path, '/') . '/'
        : rtrim(dirname($path), '/') . '/';

    return $scheme . '://' . $host . $dir . ltrim($href, './');
}

/**
 * Hosts whose TS segments have CORS headers (Access-Control-Allow-Origin: *)
 * and can therefore be fetched DIRECTLY by the browser.
 *
 * Confirmed by live CORS probe:
 *   - p16-common-sign.tiktokcdn.com  → ACAO: *       ✓
 *   - p19-common-sign.tiktokcdn.com  → ACAO: *       ✓
 *   - fx.cinecdn.workers.dev         → ACAO: techandclick.site ✓
 *   - Any *.tiktokcdn.com            → ACAO: *       ✓
 *
 * Confirmed BLOCKED for direct fetch (CF blocks residential simulation from DC IP):
 *   - live3.nextgoal.workers.dev     → OPTIONS=403   ✗  (use proxy or skip)
 *   - live.smtahmidx.workers.dev     → no CORS       ✗
 */
function isCorsOpen(string $absUrl): bool {
    $host = strtolower(parse_url($absUrl, PHP_URL_HOST) ?? '');
    $openHosts = [
        'tiktokcdn.com',          // TikTok CDN — all subdomains send ACAO: *
        'tiktok.com',
        'cinecdn.workers.dev',    // fx.cinecdn sends ACAO: techandclick.site
    ];
    foreach ($openHosts as $needle) {
        if (str_contains($host, $needle)) return true;
    }
    return false;
}

/**
 * Determine whether a URL line in a playlist is a media segment
 * (TS / fMP4 / AAC chunk) vs a sub-playlist (.m3u8).
 */
function isMediaSegment(string $absUrl): bool {
    $path = strtolower(parse_url($absUrl, PHP_URL_PATH) ?? '');
    $ext  = pathinfo($path, PATHINFO_EXTENSION);

    // Explicit media extensions
    if (in_array($ext, ['ts', 'aac', 'm4s', 'mp4', 'fmp4', 'm4v', 'mp3', 'key', 'bin', 'image'], true)) {
        return true;
    }
    // .m3u8 is always a sub-playlist — must go through proxy for header injection
    if ($ext === 'm3u8') return false;

    // No recognisable extension — heuristic: if the URL contains query params
    // like ?type=ts or base64-looking ?url=, it's a segment proxy URL
    $query = parse_url($absUrl, PHP_URL_QUERY) ?? '';
    if (str_contains($query, 'type=ts') || str_contains($query, 'url=')) return true;

    // TikTok CDN uses .image extension-less URLs that are actually TS
    if (str_contains(strtolower(parse_url($absUrl, PHP_URL_HOST) ?? ''), 'tiktokcdn')) return true;

    // Default: proxy it (safe)
    return false;
}

/**
 * Rewrite every URL in an M3U8 playlist.
 *
 * Strategy (based on live CORS audit):
 *   - Sub-playlists (.m3u8)   → always proxy.php (need header injection)
 *   - Segments from CORS-open CDNs → write RAW URL (browser fetches directly)
 *   - Segments from CORS-blocked hosts → proxy.php (server fetches, forwards)
 */
function rewriteM3u8(string $body, string $baseUrl): string {
    $lines = preg_split('/\r?\n/', $body);

    foreach ($lines as $i => $line) {
        $t = trim($line);
        if ($t === '') continue;

        if ($t[0] !== '#') {
            // Segment or sub-playlist URL
            $abs = absoluteUrl($t, $baseUrl);

            if (isMediaSegment($abs) && isCorsOpen($abs)) {
                // CORS-open segment → browser fetches directly, no proxy hop
                // This bypasses the datacenter IP block entirely.
                $lines[$i] = $abs;
            } else {
                // Sub-playlist or non-CORS segment → route through proxy
                $lines[$i] = 'proxy.php?url=' . rawurlencode($abs) . '&raw=true';
            }
        } else {
            // Tag line — rewrite URI="..." attributes (EXT-X-KEY, EXT-X-MAP, etc.)
            // Encryption keys MUST go through proxy (they need auth headers)
            $lines[$i] = preg_replace_callback(
                '/URI=["\']([^"\']+)["\']/i',
                function (array $m) use ($baseUrl): string {
                    $abs = absoluteUrl($m[1], $baseUrl);
                    // Keys always proxied — never direct
                    return 'URI="proxy.php?url=' . rawurlencode($abs) . '&raw=true"';
                },
                $t
            );
        }
    }

    return implode("\n", $lines);
}

/**
 * Build a cURL handle with spoofed browser headers.
 * $sourceHint : the logical origin site (Referer / Origin).
 * $clientIp   : real visitor IP to forward (helps with some CF Worker auth checks).
 */
function buildCurl(string $url, string $sourceHint = 'https://fifalive.click/', string $clientIp = ''): \CurlHandle|false {
    $p      = parse_url($sourceHint);
    $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');

    $extraHdrs = [];
    if ($clientIp !== '') {
        $extraHdrs[] = 'X-Forwarded-For: '   . $clientIp;
        $extraHdrs[] = 'CF-Connecting-IP: '  . $clientIp;
        $extraHdrs[] = 'True-Client-IP: '    . $clientIp;
        $extraHdrs[] = 'X-Real-IP: '         . $clientIp;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 8,
        CURLOPT_USERAGENT      =>
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' .
            '(KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => array_merge([
            'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, video/MP2T, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: '  . $sourceHint,
            'Origin: '   . $origin,
            'Connection: keep-alive',
        ], $extraHdrs),
    ]);
    return $ch;
}

/**
 * Pick the correct Referer/Origin for each upstream CDN/worker.
 *
 * Worker segments are served through CF Workers which act as the player,
 * so the segment CDN sees the worker URL as Referer — NOT fifalive.click.
 * Getting this wrong causes 403s on signed CDN URLs.
 */
function inferReferer(string $url): string {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

    // Ordered most-specific first
    $map = [
        // toffeelive CDN — Server 1 segments come from here
        'prod-cdn01-live.toffeelive' => 'https://toffeelive.com/',
        'toffeelive.com'             => 'https://toffeelive.com/',

        // Cloudflare Workers (master playlists AND their own segment proxies)
        'nextgoal.workers.dev'       => 'https://fifalive.click/',
        'cinecdn.workers.dev'        => 'https://fifalive.click/',
        'smtahmidx.workers.dev'      => 'https://fifalive.click/',

        // Rockstreamer CDN — underlying source for Worker 2 & 3 segments
        // Segments are fetched by the Worker, so Referer must be the worker host
        'rockstreamer.com'           => 'https://live3.nextgoal.workers.dev/',
        'livecdn.rockstreamer'       => 'https://live3.nextgoal.workers.dev/',

        // TikTok CDN — underlying source for Worker 4 (fx.cinecdn.workers.dev)
        'tiktokcdn.com'              => 'https://fx.cinecdn.workers.dev/',
        'tiktok.com'                 => 'https://fx.cinecdn.workers.dev/',

        // tc-sg rockstreamer (Worker 3 segments)
        'tc-sg.rockstreamer'         => 'https://live.smtahmidx.workers.dev/',
    ];

    foreach ($map as $needle => $referer) {
        if (str_contains($host, $needle)) return $referer;
    }

    return 'https://fifalive.click/';
}

// ── Fetch the target URL ─────────────────────────────────────────────────────
// Forward the real visitor IP so CF Workers see a residential IP in the headers
// (CF still checks the TCP connection IP, but some Workers also check these headers)
$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP']   // already forwarded by CF if site is proxied
         ?? $_SERVER['HTTP_X_FORWARDED_FOR']
         ?? $_SERVER['HTTP_X_REAL_IP']
         ?? $_SERVER['REMOTE_ADDR']
         ?? '';
// Sanitise: take only the first IP from a comma-separated list
if (str_contains($clientIp, ',')) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}
// Only pass if it looks like a real public IP (not loopback/private)
if (!filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $clientIp = '';
}

$referer  = inferReferer($targetUrl);
$ch       = buildCurl($targetUrl, $referer, $clientIp);
$content  = curl_exec($ch);
$httpCode = (int)    curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype    = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlErr  = curl_error($ch);
$finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $targetUrl;
curl_close($ch);

// ── Error handling ───────────────────────────────────────────────────────────
if ($content === false) {
    http_response_code(502);
    die('Upstream fetch failed: ' . htmlspecialchars($curlErr));
}

if ($httpCode >= 400) {
    http_response_code($httpCode);
    die('Upstream HTTP ' . $httpCode);
}

// ── Detect whether the response is M3U8 ─────────────────────────────────────
// NOTE: Some CDNs (e.g. prod-cdn01-live.toffeelive.com) return M3U8 playlists
// with Content-Type: text/plain instead of application/x-mpegurl.  We must
// always check the body content, not just the Content-Type header.
$looksLikeM3u8 =
    stripos($ctype, 'mpegurl')               !== false ||
    stripos($ctype, 'application/vnd.apple') !== false ||
    // Body starts with the M3U8 magic tag (covers text/plain CDN responses)
    (is_string($content) && stripos(ltrim($content), '#EXTM3U') === 0) ||
    (is_string($content) && stripos(ltrim($content), '#EXT-X-')  === 0) ||
    // URL path contains .m3u8
    preg_match('/\.m3u8(\?|$)/i', parse_url($finalUrl, PHP_URL_PATH) ?? '');

if ($looksLikeM3u8) {
    $rewritten = rewriteM3u8($content, $finalUrl);
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    http_response_code($httpCode ?: 200);
    echo $rewritten;
    exit;
}

// ── Binary media pass-through ────────────────────────────────────────────────
// Some CDNs (TikTok, rockstreamer) return TS bytes with wrong Content-Type
// (e.g. image/jpeg, text/html, or application/octet-stream).
// Detect media type by URL extension first, then fall back to ctype.
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
    // TikTok CDN serves TS segments with .image or no recognisable extension
    'image' => 'video/MP2T',
];

if (isset($extMap[$ext])) {
    $ctype = $extMap[$ext];
} elseif ($ctype === '' || stripos($ctype, 'text/') !== false || stripos($ctype, 'image/') !== false || stripos($ctype, 'octet-stream') !== false) {
    // Unknown extension and suspicious ctype — check if it looks like TS bytes
    // TS packets start with sync byte 0x47
    if (strlen($content) > 0 && ord($content[0]) === 0x47) {
        $ctype = 'video/MP2T';
    } else {
        $ctype = $ctype ?: 'application/octet-stream';
    }
}

header('Content-Type: ' . $ctype);
http_response_code($httpCode ?: 200);
echo $content;
