<?php
/**
 * stream.php — Live Stream Extraction API with caching
 *
 * HOW IT WORKS
 * ─────────────────────────────────────────────────────────────────────────────
 * fifalive.click returns a raw M3U8 playlist directly (confirmed via network
 * audit — not an HTML page). We fetch it with cURL, parse every #EXTINF entry
 * into a server object, then serve the JSON to index.php.
 *
 * CACHING  (links_cache.json, 5-minute TTL)
 * ─────────────────────────────────────────────────────────────────────────────
 * On every request:
 *   1. If a valid cache file exists and is < CACHE_TTL seconds old → serve it.
 *   2. Otherwise → fetch fifalive.click, parse, write cache, serve fresh data.
 *   3. If the upstream fetch fails but a stale cache exists → serve stale data
 *      with a warning rather than returning an empty server list.
 *
 * This means the upstream is hit at most once every CACHE_TTL seconds
 * regardless of how many visitors load the page simultaneously.
 */

set_time_limit(45);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

// ── Configuration ─────────────────────────────────────────────────────────────
const SOURCE_URL = 'https://fifalive.click/';
const CACHE_FILE = __DIR__ . '/links_cache.json';
const CACHE_TTL  = 300;   // seconds — re-scrape after 5 minutes

// ── Fallback stream ───────────────────────────────────────────────────────────
$FALLBACK = [
    'name'        => 'Test Stream',
    'group'       => 'Fallback',
    'logo'        => '',
    'raw_url'     => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
    'proxy_url'   => 'proxy.php?url=' . rawurlencode('https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8') . '&raw=true',
    'is_fallback' => true,
];

// ── Fix malformed URLs (e.g. workers*dev → workers.dev) ──────────────────────
function sanitiseUrl(string $raw): string {
    $url = trim($raw);
    if (preg_match('@^(https?://)([^/?#]+)(.*)$@i', $url, $m)) {
        return $m[1] . str_replace('*', '.', $m[2]) . $m[3];
    }
    return $url;
}

// ── Detect M3U8 content ───────────────────────────────────────────────────────
function isM3u8(string $body, string $ctype): bool {
    if (stripos($ctype, 'mpegurl')   !== false) return true;
    if (stripos($ctype, 'vnd.apple') !== false) return true;
    $t = ltrim($body);
    if (stripos($t, '#EXTM3U') === 0) return true;
    if (stripos($t, '#EXT-X-') === 0) return true;
    return false;
}

// ── Parse master M3U8 into server array ──────────────────────────────────────
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

// ── Fetch live M3U8 from source ───────────────────────────────────────────────
function fetchSource(): array {
    $ch = curl_init(SOURCE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: ' . SOURCE_URL,
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

    if ($body === false || $body === '') {
        return ['servers' => [], 'error' => 'cURL failed: ' . ($curlErr ?: 'empty response')];
    }
    if ($code < 200 || $code >= 300) {
        return ['servers' => [], 'error' => 'HTTP ' . $code . ' from source'];
    }
    if (!isM3u8($body, $ctype)) {
        return ['servers' => [], 'error' => 'Source returned non-M3U8 (CT=' . $ctype . ')'];
    }

    return ['servers' => parseMaster($body), 'error' => null];
}

// ── Read cache file ───────────────────────────────────────────────────────────
function readCache(): ?array {
    if (!file_exists(CACHE_FILE)) return null;

    $age = time() - filemtime(CACHE_FILE);
    if ($age > CACHE_TTL) return null;            // expired

    $raw = file_get_contents(CACHE_FILE);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    return (is_array($data) && !empty($data['servers'])) ? $data : null;
}

// ── Write cache file ──────────────────────────────────────────────────────────
function writeCache(array $servers): void {
    $payload = json_encode([
        'servers'   => $servers,
        'cached_at' => time(),
    ], JSON_UNESCAPED_SLASHES);

    // Write atomically: temp file → rename
    $tmp = CACHE_FILE . '.tmp';
    if (file_put_contents($tmp, $payload, LOCK_EX) !== false) {
        rename($tmp, CACHE_FILE);
    }
}

// ── Main logic ────────────────────────────────────────────────────────────────
$errors  = [];
$servers = [];
$fromCache = false;
$warning   = null;

// 1. Try serving from a fresh cache first (fast path — no upstream hit)
$cached = readCache();
if ($cached !== null) {
    $servers   = $cached['servers'];
    $fromCache = true;
} else {
    // 2. Cache is missing or expired — fetch fresh data from source
    $result = fetchSource();

    if (!empty($result['servers'])) {
        // Fresh fetch succeeded — update the cache
        $servers = $result['servers'];
        writeCache($servers);
    } else {
        // Fresh fetch failed — record the error
        $errors[] = $result['error'];

        // 3. Stale cache fallback: serve outdated data with a warning
        //    rather than returning an empty server list
        $staleRaw = file_exists(CACHE_FILE) ? file_get_contents(CACHE_FILE) : false;
        if ($staleRaw !== false) {
            $stale = json_decode($staleRaw, true);
            if (is_array($stale) && !empty($stale['servers'])) {
                $servers = $stale['servers'];
                $age     = time() - ($stale['cached_at'] ?? 0);
                $warning = 'Using cached data (' . round($age / 60) . ' min old) — source unreachable.';
            }
        }
    }
}

// Always append fallback at the end
$servers[] = $FALLBACK;

// ── Response ──────────────────────────────────────────────────────────────────
$liveCount = max(0, count($servers) - 1);   // excludes fallback

echo json_encode([
    'ok'         => true,
    'source'     => $liveCount > 0 ? 'fifalive' : 'fallback',
    'count'      => $liveCount,
    'cached'     => $fromCache,
    'cached_age' => $fromCache && file_exists(CACHE_FILE)
                    ? (time() - filemtime(CACHE_FILE))
                    : 0,
    'servers'    => $servers,
    'errors'     => $errors,
    'warning'    => $warning,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
