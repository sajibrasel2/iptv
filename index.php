<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once 'config.php';

// Pull any custom channels from the DB (used for the server-switcher pills)
$customChannels = [];
try {
    $conn = new PDO(
        "mysql:host={$servername};dbname={$dbname};charset={$charset}",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $conn->query(
        "SELECT id, display_name, target_url FROM custom_channels
         WHERE target_url != ''
         ORDER BY id ASC"
    );
    $customChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $conn = null;
} catch (PDOException $e) {
    $conn = null;
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <title>Tech &amp; Click TV</title>
    <link rel="manifest" href="manifest.json">

    <!-- HLS.js — the only third-party script we need -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js"></script>

    <!-- Tailwind (utility-only, no custom JS framework) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    animation: { 'fade-in': 'fadeIn .4s ease-out forwards' },
                    keyframes:  { fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } } }
                }
            }
        };
    </script>

    <style>
        /* Scrollbar */
        ::-webkit-scrollbar { width: 0; background: transparent; }

        html, body { height: 100%; overflow: hidden; }
        body { -webkit-tap-highlight-color: transparent; overscroll-behavior-y: none; }

        /* Glass nav */
        #bottom-nav {
            position: fixed; bottom: 0; left: 50%; transform: translateX(-50%);
            width: 100%; max-width: 448px; z-index: 9999;
            background: #1a1e29;
            padding-bottom: env(safe-area-inset-bottom, 12px);
        }

        /* Server pills */
        .server-pill {
            white-space: nowrap;
            padding: .65rem 1.2rem;
            border: 1px solid rgba(148,163,184,.18);
            border-radius: 9999px;
            background: rgba(15,23,42,.8);
            color: #cbd5e1;
            font-size: .9rem;
            font-weight: 600;
            transition: all .2s ease;
            cursor: pointer;
        }
        .server-pill:hover  { background: rgba(59,130,246,.12); border-color: rgba(96,165,250,.35); }
        .server-pill.active {
            background: linear-gradient(135deg, rgba(59,130,246,.95), rgba(99,102,241,.95));
            color: #fff;
            border-color: rgba(96,165,250,.85);
            box-shadow: 0 0 0 1px rgba(96,165,250,.35), 0 20px 60px -20px rgba(59,130,246,.7);
        }

        /* Pill scroll strip */
        #server-list { scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; }
        #server-list .server-pill { scroll-snap-align: start; }

        /* Video container */
        #video-wrap {
            position: relative;
            width: 100%;
            background: #000;
        }
        #live-video {
            width: 100%;
            height: 100%;
            display: block;
            background: #000;
        }

        /* Spinner overlay */
        #spinner {
            position: absolute; inset: 0;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background: #000;
            z-index: 10;
            gap: 12px;
        }
        #spinner.hidden { display: none; }
        .spin-ring {
            width: 44px; height: 44px;
            border: 3px solid rgba(255,255,255,.15);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Error message */
        #error-box {
            position: absolute; inset: 0;
            display: none; flex-direction: column;
            align-items: center; justify-content: center;
            background: #000;
            z-index: 11;
            padding: 1.5rem;
            text-align: center;
        }
        #error-box.visible { display: flex; }

        /* Retry button */
        #retry-btn {
            margin-top: .75rem;
            padding: .5rem 1.4rem;
            border-radius: 9999px;
            background: #3b82f6;
            color: #fff;
            font-size: .85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-black text-slate-100 h-[100dvh] overflow-hidden font-sans antialiased flex justify-center">

    <!-- Background art -->
    <div class="fixed inset-0 -z-10 pointer-events-none">
        <div class="absolute inset-0 bg-cover bg-center"
             style="background-image:url('wc2026.jpg');filter:blur(20px);transform:scale(1.05)"></div>
        <div class="absolute inset-0 bg-black/55"></div>
    </div>

    <!-- App shell -->
    <div class="w-full max-w-md md:max-w-none bg-slate-900/80 h-[100dvh] flex flex-col relative z-10
                shadow-[0_0_40px_rgba(0,0,0,.5)] overflow-hidden sm:border-x sm:border-slate-800
                backdrop-blur-xl" style="overscroll-behavior-y:none">

        <!-- Header -->
        <header class="sticky top-0 z-50 bg-slate-900/80 backdrop-blur border-b border-white/5 px-6 py-4 flex items-center justify-center">
            <div class="px-8 py-2 rounded-full"
                 style="background:radial-gradient(circle,rgba(165,243,252,.15) 0%,rgba(15,23,42,0) 70%)">
                <img src="t&amp;c.png" alt="Tech &amp; Click TV" class="h-10 w-auto object-contain drop-shadow-md">
            </div>
        </header>

        <!-- Main content -->
        <main class="flex-1 overflow-y-auto p-4 pb-28" style="-webkit-overflow-scrolling:touch">
            <section class="rounded-[28px] border border-slate-800 bg-slate-950/95 shadow-[0_24px_64px_rgba(0,0,0,.45)] overflow-hidden">

                <!-- ═══════════════════════════════════════════
                     VIDEO PLAYER  (no iframe, no third-party DOM)
                     ═══════════════════════════════════════ -->
                <div id="video-wrap" class="aspect-video bg-black rounded-t-[28px] overflow-hidden">
                    <video id="live-video" controls playsinline autoplay></video>

                    <!-- Loading spinner -->
                    <div id="spinner">
                        <div class="spin-ring"></div>
                        <span class="text-slate-400 text-sm font-medium">Loading stream…</span>
                    </div>

                    <!-- Error state -->
                    <div id="error-box">
                        <svg class="w-10 h-10 text-red-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        <p id="error-msg" class="text-red-400 text-sm font-medium">Stream unavailable.</p>
                        <p class="text-slate-500 text-xs mt-1">Try a different server below.</p>
                        <button id="retry-btn" onclick="loadServer(currentServerIndex)">Retry</button>
                    </div>
                </div>

                <!-- Server switcher pills -->
                <div class="pt-4 pb-4 px-4">
                    <div class="overflow-x-auto">
                        <div id="server-list" class="flex gap-3 px-1 py-1"></div>
                    </div>
                </div>

            </section>
        </main>

        <!-- Bottom nav -->
        <nav id="bottom-nav" class="border-t border-white/5">
            <div class="flex justify-around items-center px-2 py-2">
                <button class="flex flex-col items-center p-2 text-indigo-400">
                    <div class="p-1.5 rounded-xl bg-indigo-500/20 mb-1">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-semibold tracking-wide">Home</span>
                </button>
                <button class="flex flex-col items-center p-2 text-slate-500">
                    <div class="p-1.5 rounded-xl mb-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-medium tracking-wide">Favorites</span>
                </button>
                <button class="flex flex-col items-center p-2 text-slate-500">
                    <div class="p-1.5 rounded-xl mb-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-medium tracking-wide">Profile</span>
                </button>
            </div>
        </nav>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         PLAYER LOGIC
         ═══════════════════════════════════════════════════════ -->
    <script>
    (function () {
        'use strict';

        // ── DOM refs ────────────────────────────────────────────────────
        const video      = document.getElementById('live-video');
        const spinner    = document.getElementById('spinner');
        const errorBox   = document.getElementById('error-box');
        const errorMsg   = document.getElementById('error-msg');
        const serverList = document.getElementById('server-list');

        // ── State ────────────────────────────────────────────────────────
        let hlsInstance        = null;
        let servers            = [];   // filled by stream.php
        window.currentServerIndex = 0;

        // ── Custom channels injected from PHP (DB) ────────────────────
        const customChannels = <?php echo json_encode(
            array_map(function($ch) {
                return [
                    'name'      => $ch['display_name'] ?: 'Channel',
                    'raw_url'   => $ch['target_url'],
                    'proxy_url' => 'proxy.php?url=' . rawurlencode($ch['target_url']) . '&raw=true',
                ];
            }, $customChannels)
        , JSON_UNESCAPED_SLASHES); ?>;

        // ── UI helpers ───────────────────────────────────────────────────
        function showSpinner()  { spinner.classList.remove('hidden'); errorBox.classList.remove('visible'); }
        function hideSpinner()  { spinner.classList.add('hidden'); }
        function showError(msg) { hideSpinner(); errorMsg.textContent = msg || 'Stream unavailable.'; errorBox.classList.add('visible'); }
        function hideError()    { errorBox.classList.remove('visible'); }

        function markActive(idx) {
            document.querySelectorAll('#server-list .server-pill').forEach((el, i) => {
                el.classList.toggle('active', i === idx);
            });
        }

        // ── Destroy any existing HLS instance ───────────────────────────
        function destroyHls() {
            if (hlsInstance) {
                hlsInstance.destroy();
                hlsInstance = null;
            }
        }

        // ── Load a stream by its index in the servers array ─────────────
        window.loadServer = function loadServer(idx) {
            if (!servers[idx]) return;
            window.currentServerIndex = idx;
            markActive(idx);
            hideError();
            showSpinner();

            const proxyUrl = servers[idx].proxy_url;
            destroyHls();

            if (Hls.isSupported()) {
                hlsInstance = new Hls({
                    enableWorker:             true,
                    lowLatencyMode:           true,
                    maxMaxBufferLength:       60,
                    fragLoadingMaxRetry:      6,
                    manifestLoadingMaxRetry:  6,
                    levelLoadingMaxRetry:     6,
                    fragLoadingRetryDelay:    1000,
                    manifestLoadingRetryDelay: 1000,
                });

                hlsInstance.loadSource(proxyUrl);
                hlsInstance.attachMedia(video);

                hlsInstance.on(Hls.Events.MANIFEST_PARSED, function () {
                    hideSpinner();
                    video.play().catch(() => {/* autoplay policy — user will tap play */});
                });

                hlsInstance.on(Hls.Events.ERROR, function (event, data) {
                    if (!data.fatal) return;
                    console.error('[HLS fatal]', data.type, data.details);
                    switch (data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            hlsInstance.startLoad();          // retry once
                            break;
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            hlsInstance.recoverMediaError();
                            break;
                        default:
                            destroyHls();
                            showError('Server ' + (idx + 1) + ' failed. Try another server.');
                    }
                });

            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                // Native HLS (iOS Safari)
                video.src = proxyUrl;
                video.addEventListener('loadedmetadata', function onMeta() {
                    video.removeEventListener('loadedmetadata', onMeta);
                    hideSpinner();
                    video.play().catch(() => {});
                }, { once: true });
                video.addEventListener('error', function onErr() {
                    video.removeEventListener('error', onErr);
                    showError('Server ' + (idx + 1) + ' failed. Try another server.');
                }, { once: true });
            } else {
                showError('Your browser does not support HLS playback.');
            }
        };

        // ── Build the server pill buttons ────────────────────────────────
        function buildPills() {
            serverList.innerHTML = '';
            servers.forEach(function (srv, idx) {
                const btn = document.createElement('button');
                btn.type      = 'button';
                btn.className = 'server-pill' + (idx === 0 ? ' active' : '');
                btn.textContent = srv.name;
                btn.onclick   = () => loadServer(idx);
                serverList.appendChild(btn);
            });
        }

        // ── Fetch stream list from stream.php then kick off playback ─────
        function init() {
            showSpinner();
            fetch('stream.php')
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    if (!data.ok || !data.servers || data.servers.length === 0) {
                        throw new Error(data.error || 'No streams returned.');
                    }

                    // Merge: live streams first, then any admin-added custom channels
                    servers = data.servers.concat(customChannels);
                    buildPills();
                    loadServer(0);   // autoplay the first server
                })
                .catch(function (err) {
                    console.error('[stream.php]', err);

                    // Fallback: if DB has custom channels, use those directly
                    if (customChannels.length > 0) {
                        servers = customChannels;
                        buildPills();
                        loadServer(0);
                    } else {
                        showError('Could not load stream list. ' + err.message);
                    }
                });
        }

        // ── Clean up on page hide (mobile background tab) ────────────────
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                destroyHls();
            } else if (servers.length > 0) {
                loadServer(window.currentServerIndex);
            }
        });

        // ── Go ───────────────────────────────────────────────────────────
        init();
    })();
    </script>
</body>
</html>
