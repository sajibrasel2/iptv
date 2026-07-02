<?php
/**
 * stream.php — Dynamic Stream Extraction API
 *
 * Fetches fifalive.click on every request (no caching).
 * Parses all server entries, validates each one is reachable,
 * then returns them all with proxy_url wrappers.
 *
 * Response (always HTTP 200):
 *   { "ok": true,  "source": "fifalive", "servers": [...], "errors": [...] }
 *   { "ok": true,  "source": "fallback", "warning": "...", "servers": [...] }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────
const SOURCE_URL = 'https://fifalive.click/';
const UA_BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

// Fallback — always appended at end so the player is never completely empty
$FALLBACK = [
    'name'        => 'Test Stream',
    'group'       => 'Fallback',
    'logo'        => '',
    'raw_url'     => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
    'proxy_url'   => 'proxy.php?url=' . rawurlencode('https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8') . '&raw=true',
    'is_fallback' => true,
];

// ─────────────────────────────────────────────────────────────────────────────
// cURL helper — shared browser-like headers
// ─────────────────────────────────────────────────────────────────────────────
function curlGet(string $url, array $extraHeaders = [], int $timeout = 15): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 6,
        CURLOPT_USERAGENT      => UA_BROWSER,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_ENCODING       => '',           // Accept gzip/deflate
        CURLOPT_HTTPHEADER     => array_merge([
            'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: ' . SOURCE_URL,
            'Origin: https://fifalive.click',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
        ], $extraHeaders),
    ]);

    $body  = curl_exec($ch);
    $code  = (int)    curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    $err   = curl_error($ch);
    curl_close($ch);

    return [
        'body'  => $body,
        'code'  => $code,
        'ctype' => $ctype,
        'final' => $final,
        'err'   => $err,
        'ok'    => ($body !== false && $code >= 200 && $code < 300),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// URL sanitiser — fixes typos in the source M3U8
// e.g.  workers*dev  →  workers.dev
// ─────────────────────────────────────────────────────────────────────────────
function sanitiseUrl(string $raw): string {
    $url = trim($raw);
    if (preg_match('@^(https?://)([^/?#]+)(.*)$@i', $url, $m)) {
        return $m[1] . str_replace('*', '.', $m[2]) . $m[3];
    }
    return $url;
}

// ─────────────────────────────────────────────────────────────────────────────
// Detect whether a response body is an M3U8 playlist
// ─────────────────────────────────────────────────────────────────────────────
function isM3u8Response(string $body, string $ctype, string $url): bool {
    if (stripos($ctype, 'mpegurl')        !== false) return true;
    if (stripos($ctype, 'vnd.apple')      !== false) return true;
    $trimmed = ltrim($body);
    if (stripos($trimmed, '#EXTM3U')      === 0)     return true;
    if (stripos($trimmed, '#EXT-X-')      === 0)     return true;
    if (preg_match('/\.m3u8(\?|$)/i', parse_url($url, PHP_URL_PATH) ?? '')) return true;
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Parse a raw M3U8 master playlist into an array of server entries
// ─────────────────────────────────────────────────────────────────────────────
function parseMasterM3u8(string $body, string $defaultGroup = 'Live'): array {
    $lines   = preg_split('/\r?\n/', trim($body));
    $servers = [];
    $pending = ['name' => '', 'group' => $defaultGroup, 'logo' => ''];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (stripos($line, '#EXTINF') === 0) {
            $name  = '';
            $group = $defaultGroup;
            $logo  = '';

            if (preg_match('/tvg-name=["\']([^"\']+)["\']/i', $line, $m))    $name  = $m[1];
            if (preg_match('/group-title=["\']([^"\']+)["\']/i', $line, $m)) $group = $m[1];
            if (preg_match('/tvg-logo=["\']([^"\']+)["\']/i', $line, $m))    $logo  = $m[1];
            if ($name === '' && preg_match('/,(.+)$/', $line, $m))           $name  = trim($m[1]);

            $pending = [
                'name'  => $name  ?: ('Server ' . (count($servers) + 1)),
                'group' => $group ?: $defaultGroup,
                'logo'  => $logo,
            ];
            continue;
        }

        if ($line[0] !== '#') {
            $clean = sanitiseUrl($line);
            if (filter_var($clean, FILTER_VALIDATE_URL)) {
                $servers[] = [
                    'name'      => $pending['name']  ?: ('Server ' . (count($servers) + 1)),
                    'group'     => $pending['group'] ?: $defaultGroup,
                    'logo'      => $pending['logo']  ?? '',
                    'raw_url'   => $clean,
                    'proxy_url' => 'proxy.php?url=' . rawurlencode($clean) . '&raw=true',
                ];
            }
            // Reset regardless — each URL line consumes the pending metadata
            $pending = ['name' => '', 'group' => $defaultGroup, 'logo' => ''];
        }
    }

    return $servers;
}

// ─────────────────────────────────────────────────────────────────────────────
// Validate that a server URL actually returns a playable M3U8 right now.
// Returns true/false + a debug message.
// ─────────────────────────────────────────────────────────────────────────────
function validateServer(array $server): array {
    $r = curlGet($server['raw_url'], [], 10);

    if (!$r['ok']) {
        return [false, "HTTP {$r['code']} / cURL: {$r['err']}"];
    }

    $body = (string)($r['body'] ?? '');
    if (!isM3u8Response($body, $r['ctype'], $r['final'])) {
        $preview = substr(str_replace(["\r","\n"], ' ', $body), 0, 100);
        return [false, "Not M3U8 (CT={$r['ctype']}) preview=$preview"];
    }

    // Check it has at least one playable segment line
    $lines = preg_split('/\r?\n/', $body);
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l !== '' && $l[0] !== '#' && strlen($l) > 5) {
            return [true, "OK — first segment: " . substr($l, 0, 80)];
        }
    }

    return [false, 'M3U8 has no segment lines'];
}

// ─────────────────────────────────────────────────────────────────────────────
// MAIN — fetch master, parse, validate, return JSON
// ─────────────────────────────────────────────────────────────────────────────
$errors  = [];
$servers = [];

// ── Step 1: Fetch the master M3U8 from fifalive.click ────────────────────────
$master = curlGet(SOURCE_URL);

if (!$master['ok']) {
    $errors[] = 'Master fetch failed: HTTP ' . $master['code'] . ' / ' . $master['err'];
} else {
    $body  = (string)($master['body'] ?? '');
    $ctype = $master['ctype'];

    if (!isM3u8Response($body, $ctype, SOURCE_URL)) {
        // Likely paywall HTML — log a meaningful error
        if (stripos($body, '<html') !== false) {
            $errors[] = 'fifalive returned HTML (paywall/redirect active). CT=' . $ctype;
        } else {
            $errors[] = 'fifalive returned non-M3U8 content. CT=' . $ctype . ' body_start=' . substr($body, 0, 80);
        }
    } else {
        // ── Step 2: Parse all server entries from the master playlist ─────────
        $parsed = parseMasterM3u8($body, 'FIFA Live');

        if (empty($parsed)) {
            $errors[] = 'Master M3U8 parsed but contained no server URLs. body_start=' . substr($body, 0, 200);
        } else {
            // ── Step 3: Validate each server (parallel cURL multi-exec) ──────
            // We use curl_multi so all 4 servers are checked simultaneously
            // instead of sequentially (which would add ~40s of timeout waits).
            $mh      = curl_multi_init();
            $handles = [];

            foreach ($parsed as $i => $srv) {
                $ch = curl_init($srv['raw_url']);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 4,
                    CURLOPT_USERAGENT      => UA_BROWSER,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_CONNECTTIMEOUT => 6,
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_ENCODING       => '',
                    CURLOPT_HTTPHEADER     => [
                        'Accept: application/vnd.apple.mpegurl, application/x-mpegurl, */*',
                        'Accept-Language: en-US,en;q=0.9',
                        'Referer: ' . SOURCE_URL,
                        'Origin: https://fifalive.click',
                        'Cache-Control: no-cache',
                    ],
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$i] = $ch;
            }

            // Execute all handles concurrently
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.5);
            } while ($running > 0);

            // Collect results
            foreach ($parsed as $i => $srv) {
                $ch      = $handles[$i];
                $rbody   = (string)(curl_multi_getcontent($ch) ?? '');
                $rcode   = (int)   curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $rctype  = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $rfinal  = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $srv['raw_url'];
                $rerr    = curl_error($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                if ($rcode < 200 || $rcode >= 300) {
                    $errors[] = "{$srv['name']}: HTTP $rcode" . ($rerr ? " / $rerr" : '');
                    continue;
                }

                if (!isM3u8Response($rbody, $rctype, $rfinal)) {
                    $errors[] = "{$srv['name']}: not M3U8 (CT=$rctype)";
                    continue;
                }

                // Has at least one segment line?
                $hasSegment = false;
                foreach (preg_split('/\r?\n/', $rbody) as $l) {
                    $l = trim($l);
                    if ($l !== '' && $l[0] !== '#' && strlen($l) > 5) {
                        $hasSegment = true;
                        break;
                    }
                }

                if (!$hasSegment) {
                    $errors[] = "{$srv['name']}: M3U8 has no segment lines";
                    continue;
                }

                // ✅ Server is live and playable
                $servers[] = $srv;
            }

            curl_multi_close($mh);
        }
    }
}

// ── Always append fallback so the player is never completely stuck ────────────
$servers[] = $FALLBACK;

// ── Build response ────────────────────────────────────────────────────────────
$liveCount = count($servers) - 1; // subtract the fallback

if ($liveCount > 0) {
    echo json_encode([
        'ok'      => true,
        'source'  => 'fifalive',
        'count'   => $liveCount,
        'servers' => $servers,
        'errors'  => $errors,    // non-fatal: some servers may have failed validation
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} else {
    // All live sources failed — fallback only
    echo json_encode([
        'ok'      => true,
        'source'  => 'fallback',
        'count'   => 0,
        'warning' => 'All live sources are currently offline. Showing test stream. Errors: ' . implode(' | ', $errors),
        'errors'  => $errors,
        'servers' => $servers,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
