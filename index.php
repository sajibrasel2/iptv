<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once 'config.php';
$serverRows = [];
$autoplayUrl = '';
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=$charset", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");
    $stmt = $conn->query("SELECT id, display_name, target_url FROM custom_channels WHERE target_url != '' ORDER BY id ASC");
    $serverRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'default_autoplay_url' LIMIT 1");
    $stmt->execute();
    $defaultAutoplayUrl = trim($stmt->fetchColumn() ?: '');
    $autoplayUrl = $defaultAutoplayUrl !== '' ? $defaultAutoplayUrl : 'https://fifalive.click/';
} catch (PDOException $e) {
    $autoplayUrl = 'https://fifalive.click/';
}
$conn = null;
$initialPlayerSrc = 'proxy.php?url=' . rawurlencode($autoplayUrl);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <title>Tech & Click TV</title>
    <link rel="manifest" href="manifest.json">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            900: '#0f172a',
                            800: '#1e293b',
                        }
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(15px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        ::-webkit-scrollbar { width: 0px; background: transparent; }
        html, body { min-height: 100vh; height: 100vh; }
        body { overflow: hidden; -webkit-tap-highlight-color: transparent; overscroll-behavior-y: none; }
        .glass-panel { backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .pb-safe { padding-bottom: env(safe-area-inset-bottom, 16px); }
        #bottom-nav { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 448px; z-index: 9999; background-color: #1a1e29; }
        .server-pill { white-space: nowrap; padding: 0.75rem 1.25rem; border: 1px solid rgba(148,163,184,.18); border-radius: 9999px; background: rgba(15,23,42,.8); color: #cbd5e1; font-size: 0.95rem; font-weight: 600; transition: all .25s ease; }
        .server-pill:hover { background: rgba(59,130,246,.12); border-color: rgba(96,165,250,.35); }
        .server-pill.active { background: linear-gradient(135deg, rgba(59,130,246,.95), rgba(99,102,241,.95)); color: #ffffff; border-color: rgba(96,165,250,.85); box-shadow: 0 0 0 1px rgba(96,165,250,.35), 0 24px 90px -35px rgba(59,130,246,.85); }
        #server-list { scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; position: relative; z-index: 1000; }
        #server-list .server-pill { scroll-snap-align: start; }
        section.rounded-[32px] { overflow: visible !important; }
        iframe#main-player { position: relative; z-index: 10; }
        .social-buttons,
        #social-buttons,
        .marquee,
        #marquee,
        .promo-overlay,
        #promo-overlay,
        .footer-banner,
        #footer-banner,
        .follow-btn,
        .follow-link,
        .fb-follow,
        .telegram-follow,
        .scrolling-marquee,
        .overlay-wrapper,
        .top-banner,
        .live-overlay,
        .promo-text,
        .overlay,
        .popup,
        #telegram-bar,
        .marquee-container {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }
        #top-overlay-mask,
        #bottom-right-overlay-mask {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        .iframe-container {
            position: relative;
            width: 100%;
            background-color: #000;
        }
        @media (min-width: 768px) {
            .iframe-container {
                aspect-ratio: auto !important;
                height: calc(100vh - 280px) !important;
            }
        }
        @keyframes shimmer { 100% { transform: translateX(100%); } }
    </style>
</head>
<body class="bg-black text-slate-100 h-[100dvh] overflow-hidden font-sans antialiased selection:bg-indigo-500 selection:text-white flex justify-center relative">
    <div class="fixed inset-0 -z-10 overflow-hidden" style="pointer-events: none;">
        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('wc2026.jpg'); filter: blur(20px); transform: scale(1.05);"></div>
        <div class="absolute inset-0 bg-black/50"></div>
    </div>

    <div class="w-full max-w-md md:max-w-none bg-slate-900/80 h-[100dvh] flex flex-col relative z-10 shadow-[0_0_40px_rgba(0,0,0,0.5)] overflow-hidden sm:border-x sm:border-slate-800 backdrop-blur-xl" style="overscroll-behavior-y: none;">
        
        <header class="sticky top-0 z-50 glass-panel bg-slate-900/80 border-b border-white/5 px-6 py-4 flex items-center justify-center">
            <div class="px-8 py-2 rounded-full" style="background: radial-gradient(circle, rgba(165, 243, 252, 0.15) 0%, rgba(15, 23, 42, 0) 70%);">
                <img src="t&amp;c.png" alt="Tech & Click TV" class="h-10 w-auto object-contain drop-shadow-md">
            </div>
        </header>


        <main class="flex-1 overflow-y-auto p-4 pb-8 relative" style="-webkit-overflow-scrolling: touch;">
            <section class="rounded-[32px] border border-slate-800 bg-slate-950/95 shadow-[0_30px_80px_rgba(0,0,0,0.45)] overflow-hidden">
                <div class="iframe-container relative aspect-[16/9] bg-black">
                    <div class="relative w-full h-full overflow-hidden rounded-[28px] shadow-[0_35px_120px_rgba(0,0,0,0.55)]">
                        <div id="top-overlay-mask" class="absolute inset-x-0 top-0 h-16 z-[1000] pointer-events-none bg-black/95"></div>
                        <div id="bottom-right-overlay-mask" class="absolute bottom-0 right-0 w-[240px] h-[140px] z-[1000] pointer-events-none bg-black/95"></div>
                        <iframe id="main-player" src="<?= htmlspecialchars($initialPlayerSrc, ENT_QUOTES); ?>" sandbox="allow-scripts allow-popups allow-forms allow-presentation" allowfullscreen class="relative z-[2] w-full h-full border-0 bg-black"></iframe>
                    </div>
                </div>
                <div class="pt-4 pb-4 px-4">
                    <div class="overflow-x-auto">
                        <div id="server-list" class="flex gap-3 px-1 py-1 min-w-[320px]">
                            <?php if (count($serverRows)): ?>
                                <?php foreach ($serverRows as $idx => $server): ?>
                                    <?php
                                        $buttonName = htmlspecialchars($server['display_name'] ?: 'Server ' . ($idx + 1), ENT_QUOTES);
                                        $buttonUrl = htmlspecialchars($server['target_url'], ENT_QUOTES);
                                    ?>
                                    <button type="button" class="server-pill <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="switchServer('<?php echo $buttonUrl; ?>', this)">
                                        <?php echo $buttonName; ?>
                                    </button>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-slate-400 text-sm">No servers configured yet. Please add channels from the admin panel.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <nav id="bottom-nav" class="border-t border-white/5 pb-safe">
            <div class="flex justify-around items-center px-2 py-2">
                <button id="nav-home" class="flex flex-col items-center p-2 text-indigo-400 transition-colors group">
                    <div class="p-1.5 rounded-xl bg-indigo-500/20 mb-1">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                    </div>
                    <span class="text-[10px] font-semibold tracking-wide">Home</span>
                </button>
                <button id="nav-favorites" class="flex flex-col items-center p-2 text-slate-500 transition-colors group">
                    <div class="p-1.5 rounded-xl group-hover:bg-white/5 mb-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    </div>
                    <span class="text-[10px] font-medium tracking-wide">Favorites</span>
                </button>
                <button id="nav-profile" class="flex flex-col items-center p-2 text-slate-500 transition-colors group">
                    <div class="p-1.5 rounded-xl group-hover:bg-white/5 mb-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <span class="text-[10px] font-medium tracking-wide">Profile</span>
                </button>
            </div>
        </nav>
    </div>

    <script>
        function handleAdClick(targetUrl) {
            const adUrls = Array.isArray(window.activeAds) ? window.activeAds : [];
            const validAdUrls = adUrls
                .filter(url => typeof url === 'string')
                .map(url => url.trim())
                .filter(url => url.length > 0);

            if (validAdUrls.length > 0) {
                const adUrl = validAdUrls[Math.floor(Math.random() * validAdUrls.length)];
                window.open(adUrl, '_blank', 'noopener,noreferrer');
            }

            window.location.href = targetUrl;
        }

        window.switchServer = (streamUrl, button) => {
            const iframe = document.getElementById('main-player');
            if (!iframe || !streamUrl) return;
            iframe.src = 'proxy.php?url=' + encodeURIComponent(streamUrl);
            document.querySelectorAll('#server-list .server-pill').forEach(el => el.classList.remove('active'));
            if (button) button.classList.add('active');
        };

        function observeIframeDOM() {
            const iframe = document.getElementById('main-player');
            if (!iframe) return;
            
            iframe.addEventListener('load', () => {
                try {
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    if (!doc || !doc.body) return;
                    
                    const safeSelectors = [
                        '#socialWidget', '.social-box', '.social-btn', '.close-social',
                        '#sticky-header-notice', '.sticky-notice',
                        '.marquee-wrapper', '.marquee-text', '.scrolling-marquee', '.marquee-container', '.marquee',
                        '#fbLockerBtn', '.locker-fb-btn', '#lockerCountdown', '.countdown-text', '#countNumber',
                        '.facebook-follow', '.telegram-follow', '#telegram-bar'
                    ];

                    const genericSelectors = [
                        '.promo-text', '.overlay', '.popup', '.announcement-bar', '.top-bar',
                        '.follow-container', '.social-buttons'
                    ];

                    const isSafeToRemove = (el) => {
                        if (!el) return false;
                        const tag = el.tagName;
                        if (tag === 'VIDEO' || tag === 'IFRAME' || tag === 'CANVAS' || tag === 'BODY' || tag === 'HTML') {
                            return false;
                        }
                        if (el.id === 'player' || el.id === 'main-player' || el.classList.contains('jwplayer') || el.classList.contains('video-js') || el.classList.contains('plyr')) {
                            return false;
                        }
                        if (el.querySelector('video, iframe, canvas, #player, .jwplayer, .video-js, .plyr')) {
                            return false;
                        }
                        return true;
                    };

                    const clean = () => {
                        safeSelectors.forEach(sel => {
                            doc.querySelectorAll(sel).forEach(el => {
                                if (isSafeToRemove(el)) {
                                    el.style.setProperty('display', 'none', 'important');
                                    try { el.remove(); } catch(e) {}
                                }
                            });
                        });

                        genericSelectors.forEach(sel => {
                            doc.querySelectorAll(sel).forEach(el => {
                                if (isSafeToRemove(el)) {
                                    el.style.setProperty('display', 'none', 'important');
                                    try { el.remove(); } catch(e) {}
                                }
                            });
                        });
                        
                        doc.querySelectorAll('p, span, a, div').forEach(el => {
                            if (el.tagName === 'DIV' && el.children.length > 0) {
                                return;
                            }
                            const text = el.textContent || '';
                            if (text.includes('Follow on Facebook') || text.includes('Follow on Telegram') || text.includes('খেলা শুরু আগে')) {
                                if (isSafeToRemove(el)) {
                                    el.style.setProperty('display', 'none', 'important');
                                    try { el.remove(); } catch(e) {}
                                }
                            }
                        });
                    };

                    clean();

                    const observer = new MutationObserver((mutations) => {
                        clean();
                    });
                    
                    observer.observe(doc.documentElement, {
                        childList: true,
                        subtree: true
                    });
                } catch (err) {
                    console.warn('Parent-side iframe observation failed:', err);
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', observeIframeDOM);
    </script>
</body>
</html>