<?php
/**
 * stream.php — Live Stream API (DB-driven + live-scrape with cache)
 *
 * SERVER LIST ASSEMBLY (in priority order)
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. stream_sources DB table (status='active', ordered by priority ASC)
 *      → URLs stored AES-256-CBC encrypted; decrypted at runtime
 *      → source_type='scraped'  means the URL came from fifalive.click
 *      → source_type='manual'   means it was entered via admin panel
 *
 * 2. Live scrape of fifalive.click (cached for CACHE_TTL seconds)
 *      → Only used to UPDATE the DB rows whose source_type='scraped'
 *      → Keeps tokens fresh without manual DB edits
 *      → If scrape fails, DB rows retain whatever URL was last saved
 *
 * 3. Fallback test stream (always appended last)
 *
 * ENCRYPTION
 * ─────────────────────────────────────────────────────────────────────────────
 * raw_url_enc format:  base64(iv) : base64(ciphertext)
 * Algorithm:           AES-256-CBC
 * Key source:          $DB_ENCRYPT_KEY in config.php / .env.php
 *
 * AUDIT FINDINGS (2026-07-02, cPanel IP 148.251.35.206)
 * ─────────────────────────────────────────────────────────────────────────────
 * fx.cinecdn.workers.dev        → HTTP 200 ✓  active   priority 1
 * live3.nextgoal.workers.dev    → HTTP 429    disabled priority 2
 * live.smtahmidx.workers.dev    → HTTP 403    disabled priority 3
 * prod-cdn01-live.toffeelive.com→ DNS fail    disabled priority 4
 */

set_time_limit(45);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

// ── Configuration ─────────────────────────────────────────────────────────────
const SOURCE_URL = 'https://fifalive.click/';
const CACHE_FILE = __DIR__ . '/links_cache.json';
const CACHE_TTL  = 60;    // seconds — re-scrape after 1 minute (tokens expire fast)
const WORKER_BASE_URL = 'https://purple-queen-88f6.sajibrasel92.workers.dev';

// ── Encryption helpers ────────────────────────────────────────────────────────

function encryptUrl(string $url, string $keyB64): string {
    $key = base64_decode($keyB64);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($url, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv) . ':' . base64_encode($enc);
}

function decryptUrl(string $enc, string $keyB64): ?string {
    $parts = explode(':', $enc, 2);
    if (count($parts) !== 2) return null;
    $key  = base64_decode($keyB64);
    $iv   = base64_decode($parts[0]);
    $data = base64_decode($parts[1]);
    if ($iv === false || $data === false) return null;
    $plain = openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return ($plain === false) ? null : $plain;
}

// ── URL helpers ───────────────────────────────────────────────────────────────

function sanitiseUrl(string $raw): string {
    $url = trim($raw);
    if (preg_match('~^(https?://)([^/?#]+)(.*)$~i', $url, $m)) {
        return $m[1] . str_replace('*', '.', $m[2]) . $m[3];
    }
    return $url;
}

function makeProxyUrl(string $rawUrl): string {
    return WORKER_BASE_URL . '?url=' . rawurlencode($rawUrl);
}

function isM3u8(string $body, string $ctype): bool {
    if (stripos($ctype, 'mpegurl')   !== false) return true;
    if (stripos($ctype, 'vnd.apple') !== false) return true;
    $t = ltrim($body);
    if (stripos($t, '#EXTM3U') === 0) return true;
    if (stripos($t, '#EXT-X-') === 0) return true;
    return false;
}

// ── Parse master M3U8 → array of {name, group, url} ──────────────────────────

function parseMaster(string $body): array {
    $lines   = preg_split('~\r?\n~', trim($body));
    $entries = [];
    $pending = ['name' => '', 'group' => 'Live'];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (stripos($line, '#EXTINF') === 0) {
            $name = $group = '';
            if (preg_match('~tvg-name=["\']([^"\']+)["\']~i',    $line, $m)) $name  = $m[1];
            if (preg_match('~group-title=["\']([^"\']+)["\']~i', $line, $m)) $group = $m[1];
            if ($name === '' && preg_match('~,(.+)$~', $line, $m))           $name  = trim($m[1]);
            $pending = [
                'name'  => $name  ?: ('Server ' . (count($entries) + 1)),
                'group' => $group ?: 'Live',
            ];
            continue;
        }

        if (isset($line[0]) && $line[0] !== '#') {
            $clean = sanitiseUrl($line);
            if (filter_var($clean, FILTER_VALIDATE_URL)) {
                $entries[] = [
                    'name'  => $pending['name']  ?: ('Server ' . (count($entries) + 1)),
                    'group' => $pending['group'] ?: 'Live',
                    'url'   => $clean,
                ];
            }
            $pending = ['name' => '', 'group' => 'Live'];
        }
    }
    return $entries;
}

// ── Fetch fifalive.click ──────────────────────────────────────────────────────

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
        ],
    ]);

    $body    = curl_exec($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype   = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $body === '') {
        return ['entries' => [], 'error' => 'cURL failed: ' . ($curlErr ?: 'empty response')];
    }
    if ($code < 200 || $code >= 300) {
        return ['entries' => [], 'error' => "Source returned HTTP $code"];
    }
    if (!isM3u8($body, $ctype)) {
        return ['entries' => [], 'error' => "Source returned non-M3U8 (CT=$ctype)"];
    }

    return ['entries' => parseMaster($body), 'error' => null];
}

// ── Cache helpers ─────────────────────────────────────────────────────────────

function readCache(): ?array {
    if (!file_exists(CACHE_FILE)) return null;
    if ((time() - filemtime(CACHE_FILE)) > CACHE_TTL) {
        // Explicitly purge stale cache — forces a fresh scrape every time
        @unlink(CACHE_FILE);
        return null;
    }
    $raw  = file_get_contents(CACHE_FILE);
    $data = $raw !== false ? json_decode($raw, true) : null;
    return (is_array($data) && !empty($data['entries'])) ? $data : null;
}

function writeCache(array $entries): void {
    $payload = json_encode(['entries' => $entries, 'cached_at' => time()], JSON_UNESCAPED_SLASHES);
    $tmp = CACHE_FILE . '.tmp';
    if (file_put_contents($tmp, $payload, LOCK_EX) !== false) {
        rename($tmp, CACHE_FILE);
    }
}

// ── DB helpers ────────────────────────────────────────────────────────────────

function getDb(string $servername, string $username, string $password,
                string $dbname, string $charset): ?PDO {
    try {
        return new PDO(
            "mysql:host={$servername};dbname={$dbname};charset={$charset}",
            $username, $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException) {
        return null;
    }
}

/**
 * Load active rows from stream_sources, decrypt URLs, return as server objects.
 * All URLs now route through Cloudflare Worker.
 */
function loadDbSources(PDO $pdo, string $encKey): array {
    $rows = $pdo->query(
        "SELECT id, name, grp, raw_url_enc FROM stream_sources
         WHERE status = 'active'
         ORDER BY priority ASC"
    )->fetchAll();

    $servers = [];
    foreach ($rows as $row) {
        $url = decryptUrl($row['raw_url_enc'], $encKey);
        if ($url === null || !filter_var($url, FILTER_VALIDATE_URL)) continue;

        $servers[] = [
            'name'      => $row['name'],
            'group'     => $row['grp'],
            'logo'      => '',
            'raw_url'   => $url,
            'proxy_url' => makeProxyUrl($url),
            'db_id'     => (int) $row['id'],
        ];
    }
    return $servers;
}

/**
 * After a fresh scrape, re-encrypt and save updated URLs back into DB rows
 * that have source_type='scraped'. Matches by name (tvg-name from M3U8).
 * Also stamps last_seen on successfully matched rows.
 */
function syncScrapedUrlsToDb(PDO $pdo, array $scrapedEntries, string $encKey): void {
    if (empty($scrapedEntries)) return;

    $stmt = $pdo->prepare(
        "UPDATE stream_sources
         SET raw_url_enc = :enc, last_seen = NOW(), updated_at = NOW()
         WHERE source_type = 'scraped' AND name = :name
         LIMIT 1"
    );

    foreach ($scrapedEntries as $entry) {
        $stmt->execute([
            ':enc'  => encryptUrl($entry['url'], $encKey),
            ':name' => $entry['name'],
        ]);
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  MAIN
// ══════════════════════════════════════════════════════════════════════════════

$errors  = [];
$warning = null;

// ── Step 1: connect to DB ─────────────────────────────────────────────────────
$pdo = getDb($servername, $username, $password, $dbname, $charset);
if ($pdo === null) {
    $errors[] = 'DB connection failed — serving from cache/scrape only';
}

// ── Step 2: live scrape (cached) ──────────────────────────────────────────────
$scrapedEntries = [];
$scrapeError    = null;

$cached = readCache();
if ($cached !== null) {
    $scrapedEntries = $cached['entries'];
} else {
    $result = fetchSource();
    if (!empty($result['entries'])) {
        $scrapedEntries = $result['entries'];
        writeCache($scrapedEntries);
    } else {
        $scrapeError = $result['error'];
        $errors[]    = $scrapeError;

        // stale cache fallback
        $staleRaw = file_exists(CACHE_FILE) ? file_get_contents(CACHE_FILE) : false;
        if ($staleRaw !== false) {
            $stale = json_decode($staleRaw, true);
            if (is_array($stale) && !empty($stale['entries'])) {
                $scrapedEntries = $stale['entries'];
                $age     = time() - ($stale['cached_at'] ?? 0);
                $warning = 'Using cached data (' . round($age / 60) . ' min old) — source unreachable.';
            }
        }
    }
}

// ── Step 3: sync fresh scrape → DB (keep tokens up-to-date) ──────────────────
if ($pdo !== null && !empty($scrapedEntries)) {
    try {
        syncScrapedUrlsToDb($pdo, $scrapedEntries, $DB_ENCRYPT_KEY);
    } catch (PDOException $e) {
        $errors[] = 'DB sync warning: ' . $e->getMessage();
    }
}

// ── Step 4: build final server list ──────────────────────────────────────────
// ONLY use freshly scraped servers from fifalive.click
// DB servers are disabled to prevent stale/dead URLs from being served

$servers  = [];
$seenUrls = [];

// 4a. DB-sourced servers — DISABLED (commented out to force fresh scrape only)
/*
if ($pdo !== null) {
    try {
        $dbServers = loadDbSources($pdo, $DB_ENCRYPT_KEY);
        foreach ($dbServers as $srv) {
            $servers[]  = $srv;
            $seenUrls[] = rtrim($srv['raw_url'], '/');
        }
    } catch (PDOException $e) {
        $errors[] = 'DB read error: ' . $e->getMessage();
    }
}
*/

// 4b. Scraped servers from fifalive.click (ONLY source now)
foreach ($scrapedEntries as $i => $entry) {
    $clean = rtrim($entry['url'], '/');
    if (in_array($clean, $seenUrls, true)) continue;  // skip duplicates

    $servers[] = [
        'name'      => $entry['name'],
        'group'     => $entry['group'],
        'logo'      => '',
        'raw_url'   => $entry['url'],
        'proxy_url' => makeProxyUrl($entry['url']),
    ];
    $seenUrls[] = $clean;
}

// 4c. Fallback removed — ONLY show live scraped servers

// ── Step 5: response ──────────────────────────────────────────────────────────
$liveCount  = count($servers);
$fromCache  = ($cached !== null);
$cachedAge  = ($fromCache && file_exists(CACHE_FILE))
            ? (time() - filemtime(CACHE_FILE)) : 0;

echo json_encode([
    'ok'         => $liveCount > 0,
    'source'     => $liveCount > 0 ? 'fifalive' : 'none',
    'count'      => $liveCount,
    'cached'     => $fromCache,
    'cached_age' => $cachedAge,
    'servers'    => $servers,
    'errors'     => $errors,
    'warning'    => $warning,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
