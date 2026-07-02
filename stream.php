<?php
/**
 * stream.php — Multi-Source Stream Extraction API
 *
 * Tries each source in order, returns the first one that yields
 * valid M3U8 URLs. Falls back to a hardcoded test stream so the
 * player never shows a permanent blank screen.
 *
 * Response:
 *  { "ok": true,  "source": "fifalive", "servers": [ {name, raw_url, proxy_url, group}, ... ] }
 *  { "ok": false, "error": "...", "servers": [ fallback entries ] }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

// ─────────────────────────────────────────────────────────────────────────────
// Hardcoded fallback — always available, used when all live sources fail
// Apple's public HLS test stream, genuinely free to use
// ─────────────────────────────────────────────────────────────────────────────
$FALLBACK_SERVERS = [
    [
        'name'      => 'Test Stream (HD)',
        'group'     => 'Fallback',
        'raw_url'   => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        'proxy_url' => 'proxy.php?url=' . rawurlencode('https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8') . '&raw=true',
        'is_fallback' => true,
    ],
];

// ─────────────────────────────────────────────────────────────────────────────
// cURL factory — realistic browser headers, configurable per-source
// ─────────────────────────────────────────────────────────────────────────────
function makeCurlHandle(string $url, array $options = []) {
    $referer    = $options['referer']  ?? 'https://fifalive.click/';
    $origin     = $options['origin']   ?? 'https://fifalive.click';
    $ua         = $options['ua']       ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
    $timeout    = $options['timeout']  ?? 15;
    $accept     = $options['accept']   ?? 'application/vnd.apple.mpegurl, application/x-mpegurl, */*';
    $extra_hdrs = $options['headers']  ?? [];

    $base_hdrs = [
        'Accept: '          . $accept,
        'Accept-Language: en-US,en;q=0.9',
        'Referer: '         . $referer,
        'Origin: '          . $origin,
        'Connection: keep-alive',
        'Cache-Control: no-cache',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 6,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => array_merge($base_hdrs, $extra_hdrs),
    ]);
    return $ch;
}

// ─────────────────────────────────────────────────────────────────────────────
// Parse a raw M3U8 text into server entries
// ─────────────────────────────────────────────────────────────────────────────
function parseM3u8(string $body, string $defaultGroup = 'Live'): array {
    $lines   = preg_split('/\r?\n/', trim($body));
    $servers = [];
    $pending = ['name' => null, 'group' => $defaultGroup, 'logo' => ''];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (stripos($line, '#EXTINF') === 0) {
            $name  = '';
            $group = $defaultGroup;
            $logo  = '';

            if (preg_match('/tvg-name=["\']([^"\']+)["\']/i', $line, $m)) {
                $name = $m[1];
            }
            if (preg_match('/group-title=["\']([^"\']+)["\']/i', $line, $m)) {
                $group = $m[1];
            }
            if (preg_match('/tvg-logo=["\']([^"\']+)["\']/i', $line, $m)) {
                $logo = $m[1];
            }
            if ($name === '' && preg_match('/,(.+)$/', $line, $m)) {
                $name = trim($m[1]);
            }

            $pending = [
                'name'  => $name ?: ('Server ' . (count($servers) + 1)),
                'group' => $group,
                'logo'  => $logo,
            ];
            continue;
        }

        // URL line
        if ($line[0] !== '#' && filter_var($line, FILTER_VALIDATE_URL)) {
            $servers[] = [
                'name'      => $pending['name']  ?? ('Server ' . (count($servers) + 1)),
                'group'     => $pending['group'] ?? $defaultGroup,
                'logo'      => $pending['logo']  ?? '',
                'raw_url'   => $line,
                'proxy_url' => 'proxy.php?url=' . rawurlencode($line) . '&raw=true',
            ];
            $pending = ['name' => null, 'group' => $defaultGroup, 'logo' => ''];
        }
    }

    return $servers;
}

// ─────────────────────────────────────────────────────────────────────────────
// SOURCE 1 — fifalive.click (primary)
// Returns raw M3U8 playlist containing CDN-signed stream URLs
// ─────────────────────────────────────────────────────────────────────────────
function fetchFifalive(): array {
    $ch   = makeCurlHandle('https://fifalive.click/', [
        'referer' => 'https://fifalive.click/',
        'origin'  => 'https://fifalive.click',
        'accept'  => 'application/vnd.apple.mpegurl, application/x-mpegurl, */*',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $body === '') {
        return ['ok' => false, 'error' => 'fifalive: cURL failed — ' . $err];
    }
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'error' => "fifalive: HTTP $code"];
    }

    // Detect paywall — if we got HTML back instead of M3U8, the JS paywall triggered
    $isM3u8 = stripos($ctype, 'mpegurl') !== false
           || stripos(ltrim($body), '#EXTM3U') === 0;

    if (!$isM3u8) {
        // Check for the Bengali paywall text or any HTML tag
        if (stripos($body, '<html') !== false || stripos($body, 'প্রিমিয়াম') !== false) {
            return ['ok' => false, 'error' => 'fifalive: paywall active (Facebook follow gate detected)'];
        }
        return ['ok' => false, 'error' => 'fifalive: unexpected content-type — ' . $ctype];
    }

    $servers = parseM3u8($body, 'FIFA Live');

    if (empty($servers)) {
        return ['ok' => false, 'error' => 'fifalive: M3U8 parsed but no URLs found', 'raw' => substr($body, 0, 300)];
    }

    return ['ok' => true, 'source' => 'fifalive', 'servers' => $servers];
}

// ─────────────────────────────────────────────────────────────────────────────
// SOURCE 2 — Cloudflare Worker Relays
// These were extracted in the previous session as entries 2–4 in the fifalive
// master M3U8. Try them independently since they may still return a valid stream
// even when fifalive itself shows a paywall.
// ─────────────────────────────────────────────────────────────────────────────
function fetchWorkerRelays(): array {
    $workers = [
        ['name' => 'Worker Server 1', 'url' => 'https://live3.nextgoal.workers.dev/'],
        ['name' => 'Worker Server 2', 'url' => 'https://fx.cinecdn.workers.dev/'],
        ['name' => 'Worker Server 3', 'url' => 'https://live.smtahmidx.workers.dev/'],
    ];

    $servers = [];
    foreach ($workers as $w) {
        $ch   = makeCurlHandle($w['url'], [
            'referer' => 'https://fifalive.click/',
            'origin'  => 'https://fifalive.click',
            'timeout' => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) continue;

        $isM3u8 = stripos($ctype, 'mpegurl') !== false
               || stripos(ltrim($body), '#EXTM3U') === 0;

        if ($isM3u8) {
            // This worker returned a full M3U8 — parse its sub-entries
            $sub = parseM3u8($body, 'Worker');
            $servers = array_merge($servers, $sub);
        } else {
            // Worker returns a single stream URL — wrap it directly
            $url = trim($body);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $servers[] = [
                    'name'      => $w['name'],
                    'group'     => 'Worker',
                    'logo'      => '',
                    'raw_url'   => $w['url'],   // proxy the worker URL itself (it redirects)
                    'proxy_url' => 'proxy.php?url=' . rawurlencode($w['url']) . '&raw=true',
                ];
            }
        }
    }

    if (empty($servers)) {
        return ['ok' => false, 'error' => 'Worker relays: all offline or no valid streams'];
    }

    return ['ok' => true, 'source' => 'workers', 'servers' => $servers];
}

// ─────────────────────────────────────────────────────────────────────────────
// Main fallback chain
// ─────────────────────────────────────────────────────────────────────────────
$errors  = [];
$results = null;

// Try Source 1
$r1 = fetchFifalive();
if ($r1['ok']) {
    $results = $r1;
} else {
    $errors[] = $r1['error'];

    // Try Source 2
    $r2 = fetchWorkerRelays();
    if ($r2['ok']) {
        $results = $r2;
    } else {
        $errors[] = $r2['error'];
    }
}

if ($results !== null) {
    // Append fallback at the end so users always have something to try
    $results['servers'][] = $FALLBACK_SERVERS[0];
    $results['errors']    = $errors;   // include any non-fatal errors for debugging
    echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} else {
    // All live sources failed — return fallback-only response
    // This is still "ok" from the player's perspective (it will show the test stream)
    echo json_encode([
        'ok'      => true,
        'source'  => 'fallback',
        'warning' => 'All live sources failed. Showing test stream. Errors: ' . implode(' | ', $errors),
        'errors'  => $errors,
        'servers' => $FALLBACK_SERVERS,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
