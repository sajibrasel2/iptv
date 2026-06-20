<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
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
        body { height: 100dvh; overflow: hidden; -webkit-tap-highlight-color: transparent; overscroll-behavior-y: none; }
        .glass-panel { backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .pb-safe { padding-bottom: env(safe-area-inset-bottom, 16px); }
        #bottom-nav { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 448px; z-index: 9999; background-color: #1a1e29; }
        body { height: 100dvh; overflow: hidden; }
        .watermark-logo {
            position: absolute !important;
            top: 20px !important;
            right: 20px !important;
            width: 72px;
            max-width: 80px;
            opacity: 0.72;
            z-index: 2147483647 !important;
            pointer-events: none !important;
            animation: watermarkPulse 4.5s ease-in-out infinite;
            filter: drop-shadow(0 0 18px rgba(56, 189, 248, 0.22));
        }
        @keyframes watermarkPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 rgba(255,255,255,0); }
            50% { transform: scale(1.05); box-shadow: 0 0 18px rgba(56, 189, 248, 0.22); }
        }
        @media (max-width: 640px) {
            .watermark-logo { top: 0.75rem; right: 0.75rem; width: 60px; }
        }
        @keyframes shimmer { 100% { transform: translateX(100%); } }
    </style>
</head>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var userAgent = navigator.userAgent || navigator.vendor || window.opera;
        var isWebView = (userAgent.indexOf('wv') > -1) || (userAgent.indexOf('Android') > -1 && userAgent.indexOf('Version/') > -1);
        if (isWebView) {
            var banner = document.querySelector('.app-download-banner');
            if (banner) banner.style.display = 'none';
        }
    });
</script>
<body class="bg-black text-slate-100 h-[100dvh] overflow-hidden font-sans antialiased selection:bg-indigo-500 selection:text-white flex justify-center relative">
    <div class="fixed inset-0 -z-10 overflow-hidden" style="pointer-events: none;">
        <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('wc2026.jpg'); filter: blur(20px); transform: scale(1.05);"></div>
        <div class="absolute inset-0 bg-black/50"></div>
    </div>

    <div class="w-full max-w-md bg-slate-900/80 h-[100dvh] flex flex-col relative z-10 shadow-[0_0_40px_rgba(0,0,0,0.5)] overflow-hidden sm:border-x sm:border-slate-800 backdrop-blur-xl" style="overscroll-behavior-y: none;">
        
        <header class="sticky top-0 z-50 glass-panel bg-slate-900/80 border-b border-white/5 px-6 py-4 flex items-center justify-center">
            <div class="px-8 py-2 rounded-full" style="background: radial-gradient(circle, rgba(165, 243, 252, 0.15) 0%, rgba(15, 23, 42, 0) 70%);">
                <img src="t&c.png" alt="Tech & Click TV" class="h-10 w-auto object-contain drop-shadow-md">
            </div>
        </header>

        <div id="watermark-wrapper" class="absolute inset-0 pointer-events-none">
            <img src="t&c.png" alt="Premium watermark" class="watermark-logo" aria-hidden="true">
        </div>

        <div class="app-download-banner" style="background: linear-gradient(to right, #1a2a6c, #b21f1f, #fdbb2d); padding: 25px; border-radius: 15px; text-align: center; color: white; margin: 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.3);">
            <h2 style="margin-top: 0; margin-bottom: 10px; font-weight:bold;">📺 Download TCTV App</h2>
            <p style="margin-bottom: 20px; font-size:14px;">Get all your favorite live sports and movies right in your pocket! Download our official app for a faster and smoother streaming experience.</p>
            <a href="https://techandclick.site/iptv/download.html" style="background: white; color: #b21f1f; padding: 12px 30px; text-decoration: none; font-weight: bold; border-radius: 50px; display: inline-block; transition: 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">
                ⬇ Download Now
            </a>
        </div>

        <main class="flex-1 overflow-y-auto p-4 pb-40 space-y-4 relative" style="-webkit-overflow-scrolling: touch;">
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-64 bg-indigo-500/10 blur-[80px] rounded-full pointer-events-none"></div>
            
            <div id="home-section" class="space-y-4">
                <div id="loading" class="flex justify-center items-center py-10">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
                </div>
                <div id="cards-container" class="space-y-4"></div>
            </div>

            <div id="favorites-section" class="hidden space-y-4">
                <h2 class="text-2xl font-bold text-white mb-2">My Favorites</h2>
                <div id="favorites-container" class="space-y-4">
                    <p class="text-center text-slate-400 py-10">You haven't added any channels to favorites yet.</p>
                </div>
            </div>

            <div id="profile-section" class="hidden space-y-6">
                <div class="flex flex-col items-center py-6 border-b border-white/5 space-y-3">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-indigo-500 to-cyan-500 flex items-center justify-center text-3xl font-bold text-white shadow-lg">U</div>
                    <div class="text-center">
                        <h2 class="text-xl font-bold text-white">Guest User</h2>
                        <p class="text-xs text-slate-400">IPTV Hub Member</p>
                    </div>
                </div>
            </div>
        </main>

        <div class="flex justify-center my-2"><script src="ads.js"></script></div>

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

    <div id="prediction-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center bg-black/80 backdrop-blur-sm px-4">
        <div class="bg-slate-800 border border-indigo-500/30 rounded-2xl w-full max-w-sm overflow-hidden shadow-2xl relative">
            <button onclick="closePredictionModal()" class="absolute top-3 right-3 text-slate-400 hover:text-white bg-slate-900/50 rounded-full p-1.5 z-10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <div class="p-5">
                <div class="text-center mb-4">
                    <img id="modal-prize-img" src="" class="h-24 mx-auto object-contain drop-shadow-md hidden mb-2">
                    <h3 class="text-xl font-bold text-white mb-1">Make Your Prediction</h3>
                    <p id="modal-match-title" class="text-indigo-400 text-sm font-semibold"></p>
                    <span id="prediction-status-badge" class="hidden mt-2 inline-flex items-center justify-center rounded-full border px-3 py-1 text-xs font-semibold tracking-wide"></span>
                </div>
                <form id="prediction-form" class="space-y-4">
                    <input type="hidden" id="pred-campaign-id">
                    <input type="text" id="pred-name" required placeholder="Your Full Name" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm focus:border-indigo-500 outline-none">
                    <input type="tel" id="pred-phone" required placeholder="Your Phone Number" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm focus:border-indigo-500 outline-none">
                    <input type="text" id="pred-country" required placeholder="Enter your Country" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm focus:border-indigo-500 outline-none">
                    <input type="text" id="pred-district" required placeholder="Enter your District/City" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm focus:border-indigo-500 outline-none">
                    <div class="space-y-2">
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Prediction</p>
                        <div class="grid grid-cols-3 gap-3">
                            <label class="flex items-center justify-center p-3 border border-slate-700 rounded-xl cursor-pointer hover:bg-slate-700/50 has-[:checked]:bg-indigo-500/20 has-[:checked]:border-indigo-500">
                                <input type="radio" name="predicted_team" value="team_a" required class="hidden">
                                <span id="label-team-a" class="text-sm font-semibold text-white"></span>
                            </label>
                            <label class="flex items-center justify-center p-3 border border-slate-700 rounded-xl cursor-pointer hover:bg-slate-700/50 has-[:checked]:bg-indigo-500/20 has-[:checked]:border-indigo-500">
                                <input type="radio" name="predicted_team" value="draw" required class="hidden">
                                <span class="text-sm font-semibold text-white">Draw</span>
                            </label>
                            <label class="flex items-center justify-center p-3 border border-slate-700 rounded-xl cursor-pointer hover:bg-slate-700/50 has-[:checked]:bg-indigo-500/20 has-[:checked]:border-indigo-500">
                                <input type="radio" name="predicted_team" value="team_b" required class="hidden">
                                <span id="label-team-b" class="text-sm font-semibold text-white"></span>
                            </label>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label id="label-score-a" class="block text-xs text-slate-400 uppercase tracking-wider mb-2">Team A Goals</label>
                                <input type="number" id="pred-score-a" min="0" required class="w-full bg-slate-900 border border-slate-700 rounded-2xl px-4 py-4 text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none" placeholder="0">
                            </div>
                            <div>
                                <label id="label-score-b" class="block text-xs text-slate-400 uppercase tracking-wider mb-2">Team B Goals</label>
                                <input type="number" id="pred-score-b" min="0" required class="w-full bg-slate-900 border border-slate-700 rounded-2xl px-4 py-4 text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none" placeholder="0">
                            </div>
                        </div>
                        <div class="space-y-3">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Share to unlock:</p>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-2">
                                <button type="button" onclick="handleShareClick('whatsapp')" class="flex items-center justify-center gap-2 px-3 py-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 transition text-white text-[11px] font-semibold">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M20.52 3.48A11.94 11.94 0 0012 0C5.37 0 0 5.37 0 12c0 2.1.55 4.16 1.6 5.96L0 24l6.2-1.62A11.94 11.94 0 0012 24c6.63 0 12-5.37 12-12 0-3.2-1.24-6.2-3.48-8.52zM12 21.6c-1.84 0-3.65-.5-5.2-1.45l-.37-.22-3.68.96.98-3.57-.24-.37A9.54 9.54 0 012.4 12c0-5.24 4.26-9.5 9.5-9.5 2.54 0 4.92.99 6.72 2.8A9.44 9.44 0 0121.5 12c0 5.24-4.26 9.6-9.5 9.6zm5.35-7.17c-.27-.14-1.6-.79-1.85-.89-.24-.11-.42-.15-.6.14-.18.28-.7.89-.85 1.07-.16.18-.32.21-.59.07-.27-.14-1.15-.42-2.18-1.35-.81-.72-1.36-1.61-1.52-1.88-.16-.28-.02-.43.12-.57.12-.12.27-.3.41-.45.13-.15.17-.27.26-.45.08-.18.04-.33-.02-.46-.07-.13-.6-1.44-.82-1.97-.22-.52-.45-.45-.61-.46-.16-.01-.35-.01-.54-.01-.18 0-.46.07-.7.33-.24.26-.92.9-.92 2.2s.94 2.55 1.07 2.72c.12.17 1.86 2.85 4.52 3.99 2.51 1.08 2.84.93 3.35.87.5-.05 1.6-.66 1.83-1.3.23-.64.23-1.19.16-1.3-.07-.12-.24-.18-.51-.31z"/></svg>
                                    WhatsApp
                                </button>
                                <button type="button" onclick="handleShareClick('telegram')" class="flex items-center justify-center gap-2 px-3 py-2 rounded-2xl bg-sky-500 hover:bg-sky-600 transition text-white text-[11px] font-semibold">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.25 3.35 9.69 8.06 11.32.59.11.8-.26.8-.58 0-.29-.01-1.05-.01-2.06-3.28.71-3.98-1.58-3.98-1.58-.53-1.35-1.28-1.71-1.28-1.71-1.05-.72.08-.71.08-.71 1.16.08 1.77 1.2 1.77 1.2 1.03 1.76 2.7 1.25 3.36.96.1-.75.4-1.25.73-1.54-2.62-.3-5.38-1.31-5.38-5.82 0-1.29.46-2.35 1.22-3.18-.12-.3-.53-1.5.12-3.13 0 0 .99-.32 3.24 1.21a11.32 11.32 0 012.95-.4c1 .01 2.01.14 2.95.4 2.24-1.53 3.23-1.21 3.23-1.21.65 1.63.24 2.83.12 3.13.76.83 1.22 1.9 1.22 3.18 0 4.52-2.76 5.51-5.39 5.81.41.35.77 1.04.77 2.1 0 1.52-.01 2.75-.01 3.12 0 .32.2.7.81.58A12.01 12.01 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                                Telegram
                            </button>
                        </div>
                        <p class="text-xs text-slate-300 text-center">Shares completed: <span id="share-count" class="font-bold text-indigo-400">0</span>/3</p>
                    </div>
                    <div class="hidden">
                        <input type="checkbox" id="pred-share" required class="mt-1 w-4 h-4 rounded border-slate-700 text-indigo-500 bg-slate-900">
                        <label for="pred-share" class="text-xs text-slate-400 leading-relaxed">I confirm I have shared this app via WhatsApp or Telegram.</label>
                    </div>
                    <button type="submit" id="pred-submit-btn" disabled class="w-full bg-indigo-500 disabled:bg-slate-600 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-indigo-600 text-white font-bold py-3.5 rounded-xl transition-colors shadow-lg shadow-indigo-500/25 flex justify-center items-center">
                        Submit Prediction
                    </button>
                </form>
                <div id="pred-success" class="hidden text-center py-6">
                    <div class="text-5xl mb-3">🎉</div>
                    <h4 class="text-lg font-bold text-green-400 mb-2">Prediction Submitted!</h4>
                    <p class="text-sm text-slate-300">Keep an eye on our Facebook page for the lottery results.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Share State
        let shareClicks = 0;
        let predictionMatchTime = null;
        let predictionExpired = false;

        const predictionStateKeys = {
            name: 'prediction_name',
            phone: 'prediction_phone',
            country: 'prediction_country',
            district: 'prediction_district',
            team: 'prediction_team',
            scoreA: 'prediction_score_a',
            scoreB: 'prediction_score_b',
            shares: 'prediction_shareClicks'
        };

        function normalizePhoneDigits(value) {
            if (!value) return '';
            const banglaDigits = '০১২৩৪৫৬৭৮৯';
            const normalized = value.split('').map((char) => {
                const index = banglaDigits.indexOf(char);
                return index >= 0 ? String(index) : char;
            }).join('');
            return normalized.replace(/[^\d\+]/g, '').trim();
        }

        const countryFlagMap = {
            "Argentina": "🇦🇷", "Brazil": "🇧🇷", "Uruguay": "🇺🇾", "Colombia": "🇨🇴", "Ecuador": "🇪🇨", 
            "Venezuela": "🇻🇪", "USA": "🇺🇸", "Canada": "🇨🇦", "Mexico": "🇲🇽", "Costa Rica": "🇨🇷", 
            "Panama": "🇵🇦", "Jamaica": "🇯🇲", "Haiti": "🇭🇹", "France": "🇫🇷", "England": "🇬🇧", 
            "Spain": "🇪🇸", "Germany": "🇩🇪", "Portugal": "🇵🇹", "Italy": "🇮🇹", "Netherlands": "🇳🇱", 
            "Croatia": "🇭🇷", "Belgium": "🇧🇪", "Switzerland": "🇨🇭", "Denmark": "🇩🇰", "Serbia": "🇷🇸", 
            "Austria": "🇦🇹", "Ukraine": "🇺🇦", "Turkey": "🇹🇷", "Poland": "🇵🇱", "Morocco": "🇲🇦", 
            "Senegal": "🇸🇳", "Egypt": "🇪🇬", "Algeria": "🇩🇿", "Ivory Coast": "🇨🇮", "Nigeria": "🇳🇬", 
            "Cameroon": "🇨🇲", "Mali": "🇲🇱", "Tunisia": "🇹🇳", "Japan": "🇯🇵", "South Korea": "🇰🇷", 
            "Iran": "🇮🇷", "Australia": "🇦🇺", "Saudi Arabia": "🇸🇦", "Qatar": "🇶🇦", "Uzbekistan": "🇺🇿", 
            "UAE": "🇦🇪", "New Zealand": "🇳🇿", "Chile": "🇨🇱", "Peru": "🇵🇪"
        };

        function getCountryFlag(countryName) {
            if (!countryName) return '';
            const name = countryName.trim();
            return countryFlagMap[name] || '';
        }

        function teamLabelWithFlag(countryName) {
            const flag = getCountryFlag(countryName);
            const safeName = countryName || '';
            if (flag) {
                return `<span class="animated-flag">${flag}</span>${safeName}`;
            }
            return safeName;
        }

        function parseDhakaMatchTime(matchTime) {
            if (!matchTime) return null;
            const utcIso = matchTime.replace(' ', 'T') + '+06:00';
            const dt = new Date(utcIso);
            return isNaN(dt.getTime()) ? null : dt;
        }

        function isPredictionExpired(matchTime) {
            const deadline = parseDhakaMatchTime(matchTime);
            return deadline ? Date.now() > deadline.getTime() : false;
        }

        function formatDeadlineLabel(matchTime) {
            const deadline = parseDhakaMatchTime(matchTime);
            if (!deadline) return 'Deadline unavailable';
            const options = { day: 'numeric', month: 'long', hour: 'numeric', minute: '2-digit', hour12: true };
            return `⏱️ Deadline: ${deadline.toLocaleString('en-US', options)}`;
        }

        function updatePredictionDeadlineState(matchTime) {
            predictionMatchTime = matchTime;
            predictionExpired = isPredictionExpired(matchTime);
            const badge = document.getElementById('prediction-status-badge');
            const btn = document.getElementById('pred-submit-btn');
            if (badge) {
                if (predictionExpired) {
                    badge.innerText = 'Prediction Closed';
                    badge.classList.remove('hidden', 'bg-emerald-500/15', 'text-emerald-300', 'border-emerald-500/20');
                    badge.classList.add('inline-flex', 'bg-red-500/15', 'text-red-300', 'border-red-500/20');
                } else {
                    badge.innerText = formatDeadlineLabel(matchTime);
                    badge.classList.remove('hidden', 'bg-red-500/15', 'text-red-300', 'border-red-500/20');
                    badge.classList.add('inline-flex', 'bg-emerald-500/15', 'text-emerald-300', 'border-emerald-500/20');
                }
            }
            if (btn) {
                if (predictionExpired) {
                    btn.disabled = true;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    btn.innerText = 'Time Expired';
                } else if (shareClicks >= 3) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    btn.innerText = 'Submit Prediction';
                } else {
                    btn.disabled = true;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    btn.innerText = 'Submit Prediction';
                }
            }
        }

        function savePredictionState() {
            const name = document.getElementById('pred-name')?.value || '';
            const phoneField = document.getElementById('pred-phone');
            const phone = phoneField ? normalizePhoneDigits(phoneField.value) : '';
            if (phoneField) phoneField.value = phone;
            const country = document.getElementById('pred-country')?.value || '';
            const district = document.getElementById('pred-district')?.value || '';
            const selectedTeam = document.querySelector('input[name="predicted_team"]:checked');
            const team = selectedTeam ? selectedTeam.value : '';
            const scoreA = document.getElementById('pred-score-a')?.value || '';
            const scoreB = document.getElementById('pred-score-b')?.value || '';
            localStorage.setItem(predictionStateKeys.name, name);
            localStorage.setItem(predictionStateKeys.phone, phone);
            localStorage.setItem(predictionStateKeys.country, country);
            localStorage.setItem(predictionStateKeys.district, district);
            localStorage.setItem(predictionStateKeys.team, team);
            localStorage.setItem(predictionStateKeys.scoreA, scoreA);
            localStorage.setItem(predictionStateKeys.scoreB, scoreB);
            localStorage.setItem(predictionStateKeys.shares, String(shareClicks));
        }

        function bindPredictionStateListeners() {
            document.getElementById('pred-name')?.addEventListener('input', savePredictionState);
            const phoneInput = document.getElementById('pred-phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', (event) => {
                    const normalized = normalizePhoneDigits(event.target.value);
                    if (normalized !== event.target.value) {
                        event.target.value = normalized;
                    }
                    savePredictionState();
                });
            }
            document.querySelectorAll('input[name="predicted_team"]').forEach(el => el.addEventListener('change', savePredictionState));
            document.getElementById('pred-score-a')?.addEventListener('input', savePredictionState);
            document.getElementById('pred-score-b')?.addEventListener('input', savePredictionState);
            document.getElementById('pred-country')?.addEventListener('input', savePredictionState);
            document.getElementById('pred-district')?.addEventListener('input', savePredictionState);
        }

        // Tab Logic
        const sections = { home: document.getElementById('home-section'), favorites: document.getElementById('favorites-section'), profile: document.getElementById('profile-section') };
        const navButtons = { home: document.getElementById('nav-home'), favorites: document.getElementById('nav-favorites'), profile: document.getElementById('nav-profile') };
        
        function switchTab(activeTab) {
            Object.keys(sections).forEach(tab => sections[tab].classList.toggle('hidden', tab !== activeTab));
            Object.keys(navButtons).forEach(tab => {
                const btn = navButtons[tab];
                const icon = btn.querySelector('div');
                if (tab === activeTab) {
                    btn.classList.replace('text-slate-500', 'text-indigo-400');
                    icon.classList.add('bg-indigo-500/20');
                    icon.classList.remove('group-hover:bg-white/5');
                } else {
                    btn.classList.replace('text-indigo-400', 'text-slate-500');
                    icon.classList.remove('bg-indigo-500/20');
                    icon.classList.add('group-hover:bg-white/5');
                }
            });
        }
        Object.keys(navButtons).forEach(tab => navButtons[tab].addEventListener('click', () => switchTab(tab)));

        // Ad Logic
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

        function createWatermarkElement() {
            let watermark = document.querySelector('.watermark-logo');
            if (watermark) return watermark;

            watermark = document.createElement('img');
            watermark.src = 't&c.png';
            watermark.alt = 'Premium watermark';
            watermark.className = 'watermark-logo';
            watermark.setAttribute('aria-hidden', 'true');
            watermark.style.position = 'absolute';
            watermark.style.top = '20px';
            watermark.style.right = '20px';
            watermark.style.zIndex = '2147483647';
            watermark.style.pointerEvents = 'none';
            return watermark;
        }

        function findPlayerContainer() {
            const selectors = [
                '.plyr',
                '.video-js',
                '.clappr-player',
                '.html5-video-player',
                '.jwplayer',
                '.player-wrapper',
                '.player',
                '#player',
                'div[id*="player"]',
                'div[class*="player"]',
                'video',
                'iframe'
            ];
            const candidates = Array.from(document.querySelectorAll(selectors.join(','))).filter(el => {
                if (!el || el.classList.contains('watermark-logo')) return false;
                const rect = el.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0;
            });
            return candidates[0] || null;
        }

        function attachWatermarkToPlayer() {
            const watermark = createWatermarkElement();
            const playerContainer = findPlayerContainer();
            const fullScreenElement = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;
            let target = playerContainer || fullScreenElement || document.body;

            if (target && (target.tagName === 'VIDEO' || target.tagName === 'IFRAME')) {
                target = target.parentElement || document.body;
            }

            if (target && !['relative', 'absolute', 'fixed', 'sticky'].includes(getComputedStyle(target).position)) {
                target.style.position = 'relative';
            }

            if (!target.contains(watermark)) {
                target.appendChild(watermark);
            }

            return target;
        }

        function handleFullscreenChange() {
            attachWatermarkToPlayer();
        }

        document.addEventListener('fullscreenchange', handleFullscreenChange);
        document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
        document.addEventListener('mozfullscreenchange', handleFullscreenChange);
        document.addEventListener('MSFullscreenChange', handleFullscreenChange);

        window.handleShareClick = (platform) => {
            const shareText = `🎯 Predict the match, share this with 3 friends, and stand a chance to WIN an exclusive Jersey! 🎽

🔥 Watch Live Sports, Movies, and Premium TV Channels for FREE on TCTV! 📺
🚀 Ultra-Fast Streaming, In-built Powerful Ad Blocker, and Premium Features.

👇 Download the official app instantly from here:
https://techandclick.site/iptv/download.html`;
            const message = encodeURIComponent(shareText);
            const shareMap = {
                whatsapp: {
                    intent: 'whatsapp://send?text=' + message,
                    fallback: 'https://api.whatsapp.com/send?text=' + message
                },
                telegram: {
                    intent: 'tg://msg?text=' + message,
                    fallback: 'https://t.me/share/url?text=' + message
                }
            };
            const target = shareMap[platform];
            if (!target) return;

            window.location.href = target.intent;
            setTimeout(() => {
                window.location.href = target.fallback;
            }, 1200);

            if (shareClicks < 3) {
                shareClicks += 1;
                const shareCountEl = document.getElementById('share-count');
                const predShare = document.getElementById('pred-share');
                const submitBtn = document.getElementById('pred-submit-btn');
                if (shareCountEl) shareCountEl.innerText = shareClicks;
                if (shareClicks >= 3) {
                    if (predShare) predShare.checked = true;
                    if (submitBtn && !predictionExpired) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                }
                savePredictionState();
            }
        };

        // Main App Logic
        document.addEventListener('DOMContentLoaded', async () => {
            const container = document.getElementById('cards-container');
            const loading = document.getElementById('loading');
            bindPredictionStateListeners();
            attachWatermarkToPlayer();
            
            // 1. Fetch Prediction Banner
            try {
                const predRes = await fetch('api_predictions.php');
                const predData = await predRes.json();
                if (predData && predData.id && predData.status === 'active') {
                    const expired = predData.is_expired === true;
                    const statusLabel = expired ? 'CLOSED' : 'PLAY';
                    const statusClasses = expired ? 'bg-slate-500 text-slate-200' : 'bg-white text-indigo-600';
                    const deadlineLabel = !expired ? formatDeadlineLabel(predData.match_time) : 'Prediction Closed';
                    const bannerHtml = `
                        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl p-4 mb-4 shadow-[0_8px_30px_rgba(99,102,241,0.3)] ${expired ? 'cursor-default opacity-90' : 'cursor-pointer hover:scale-[1.02]'} transition transform" 
                             ${expired ? '' : `onclick="openPredictionModal('${predData.id}', '${predData.team_a}', '${predData.team_b}', '${predData.prize_image_url || ''}', '${predData.match_time || ''}', true)"`}>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <span class="text-3xl drop-shadow-md">🎁</span>
                                    <div>
                                        <h3 class="text-white font-bold text-lg leading-tight">Predict & Win Jersey!</h3>
                                        <p class="text-indigo-100 text-xs mt-0.5">${teamLabelWithFlag(predData.team_a)} vs ${teamLabelWithFlag(predData.team_b)}</p>
                                        <p class="text-slate-200 text-[11px] mt-2">${deadlineLabel}</p>
                                    </div>
                                </div>
                                <span class="${statusClasses} text-xs font-extrabold px-4 py-2 rounded-full shadow-md">${statusLabel}</span>
                            </div>
                        </div>
                    `;
                    document.getElementById('home-section').insertAdjacentHTML('afterbegin', bannerHtml);
                }
            } catch (e) { console.error('No active prediction', e); }

            // 2. Fetch Channels
            try {
                const response = await fetch('api_dynamic.php?t=' + Date.now());
                const data = await response.json();
                window.activeAds = data.ads ? data.ads.map(a => a.ad_url) : [];
                loading.remove();

                if (!data.channels || data.channels.length === 0) {
                    container.innerHTML = '<p class="text-center text-slate-400 py-10">No channels available at the moment.</p>';
                    return;
                }

                data.channels.forEach((site, index) => {
                    const delay = index * 100;
                    container.insertAdjacentHTML('beforeend', `
                        <div class="relative group bg-slate-800/50 p-[1px] rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_8px_30px_rgba(99,102,241,0.15)] opacity-0 animate-fade-in-up cursor-pointer" 
                             style="animation-delay: ${delay}ms; animation-fill-mode: forwards;" onclick="handleAdClick('${site.target_url}')">
                            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/20 via-purple-500/5 to-transparent opacity-50 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="relative bg-slate-800/90 backdrop-blur-sm rounded-[15px] p-5 flex flex-col space-y-5">
                                <div class="flex justify-between items-start gap-3">
                                    <div>
                                        <h2 class="text-xl font-bold text-white mb-1 group-hover:text-indigo-400 transition-colors">${site.display_name}</h2>
                                        <p class="text-sm text-slate-400">Live Broadcasting</p>
                                    </div>
                                    <div class="flex items-center space-x-1.5 bg-red-500/10 px-2.5 py-1 rounded-full border border-red-500/20">
                                        <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span></span>
                                        <span class="text-[10px] font-bold text-red-500 uppercase tracking-widest">Live</span>
                                    </div>
                                </div>
                                <div class="w-full relative overflow-hidden flex items-center justify-center space-x-2 bg-indigo-500/10 group-hover:bg-indigo-500 text-indigo-400 group-hover:text-white font-semibold py-3.5 px-4 rounded-xl border border-indigo-500/30 group-hover:border-indigo-500 transition-all duration-300">
                                    <span class="relative z-10">Watch Now</span>
                                    <svg class="w-4 h-4 relative z-10 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </div>
                            </div>
                        </div>
                    `);
                });
            } catch (error) {
                loading.innerHTML = '<p class="text-center text-red-400">Failed to load live channels.</p>';
            }
        });

        // Modal Functions
        window.openPredictionModal = (id, tA, tB, img, matchTime = null) => {
            predictionMatchTime = matchTime;
            document.getElementById('pred-campaign-id').value = id;
            document.getElementById('modal-match-title').innerText = tA + ' vs ' + tB;
            updatePredictionDeadlineState(matchTime);
            document.getElementById('label-team-a').innerHTML = teamLabelWithFlag(tA);
            document.getElementById('label-team-b').innerHTML = teamLabelWithFlag(tB);
            document.getElementById('label-score-a').innerText = tA + ' Goals';
            document.getElementById('label-score-b').innerText = tB + ' Goals';
            const imgEl = document.getElementById('modal-prize-img');
            if(img) { imgEl.src = img; imgEl.classList.remove('hidden'); } else { imgEl.classList.add('hidden'); }

            const savedName = localStorage.getItem(predictionStateKeys.name);
            const savedPhone = localStorage.getItem(predictionStateKeys.phone);
            const savedTeam = localStorage.getItem(predictionStateKeys.team);
            const savedShares = parseInt(localStorage.getItem(predictionStateKeys.shares), 10) || 0;

            if (savedName) document.getElementById('pred-name').value = savedName;
            if (savedPhone) document.getElementById('pred-phone').value = normalizePhoneDigits(savedPhone);
            if (localStorage.getItem(predictionStateKeys.country) !== null) {
                document.getElementById('pred-country').value = localStorage.getItem(predictionStateKeys.country);
            }
            if (localStorage.getItem(predictionStateKeys.district) !== null) {
                document.getElementById('pred-district').value = localStorage.getItem(predictionStateKeys.district);
            }
            if (savedTeam) {
                const savedRadio = document.querySelector(`input[name="predicted_team"][value="${savedTeam}"]`);
                if (savedRadio) savedRadio.checked = true;
            }
            if (localStorage.getItem(predictionStateKeys.scoreA) !== null) {
                document.getElementById('pred-score-a').value = localStorage.getItem(predictionStateKeys.scoreA);
            }
            if (localStorage.getItem(predictionStateKeys.scoreB) !== null) {
                document.getElementById('pred-score-b').value = localStorage.getItem(predictionStateKeys.scoreB);
            }

            shareClicks = Math.min(savedShares, 3);
            const shareCountEl = document.getElementById('share-count');
            const predShare = document.getElementById('pred-share');
            const submitBtn = document.getElementById('pred-submit-btn');
            if (shareCountEl) shareCountEl.innerText = shareClicks;
            if (shareClicks >= 3) {
                if (predShare) predShare.checked = true;
                if (submitBtn) {
                    if (!predictionExpired) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    } else {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                }
            } else {
                if (predShare) predShare.checked = false;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }

            document.getElementById('prediction-form').classList.remove('hidden');
            document.getElementById('pred-success').classList.add('hidden');
            document.getElementById('prediction-modal').classList.remove('hidden');
        };

        window.closePredictionModal = () => {
            document.getElementById('prediction-modal').classList.add('hidden');
            document.getElementById('prediction-form').reset();
            shareClicks = 0;
            const shareCountEl = document.getElementById('share-count');
            const predShare = document.getElementById('pred-share');
            const submitBtn = document.getElementById('pred-submit-btn');
            if (shareCountEl) shareCountEl.innerText = '0';
            if (predShare) predShare.checked = false;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
            localStorage.removeItem(predictionStateKeys.name);
            localStorage.removeItem(predictionStateKeys.phone);
            localStorage.removeItem(predictionStateKeys.country);
            localStorage.removeItem(predictionStateKeys.district);
            localStorage.removeItem(predictionStateKeys.team);
            localStorage.removeItem(predictionStateKeys.scoreA);
            localStorage.removeItem(predictionStateKeys.scoreB);
            localStorage.removeItem(predictionStateKeys.shares);
        };

        document.getElementById('prediction-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (predictionExpired) {
                alert('Prediction time is over.');
                return;
            }
            const btn = document.getElementById('pred-submit-btn');
            btn.innerHTML = 'Submitting...'; btn.disabled = true;
            try {
                const res = await fetch('api_predictions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        campaign_id: document.getElementById('pred-campaign-id').value,
                        user_name: document.getElementById('pred-name').value,
                        user_phone: document.getElementById('pred-phone').value,
                        predicted_team: document.querySelector('input[name="predicted_team"]:checked').value,
                        country: document.getElementById('pred-country').value,
                        district: document.getElementById('pred-district').value,
                        predicted_score_a: document.getElementById('pred-score-a').value,
                        predicted_score_b: document.getElementById('pred-score-b').value,
                        has_shared: document.getElementById('pred-share').checked
                    })
                });
                if (res.ok) {
                    localStorage.removeItem(predictionStateKeys.name);
                    localStorage.removeItem(predictionStateKeys.phone);
                    localStorage.removeItem(predictionStateKeys.team);
                    localStorage.removeItem(predictionStateKeys.scoreA);
                    localStorage.removeItem(predictionStateKeys.scoreB);
                    localStorage.removeItem(predictionStateKeys.shares);
                    document.getElementById('prediction-form').classList.add('hidden');
                    document.getElementById('pred-success').classList.remove('hidden');
                    setTimeout(closePredictionModal, 3500);
                } else {
                    const errorData = await res.json().catch(() => null);
                    if (res.status === 400 && errorData?.message === 'This mobile number has already submitted a prediction for this match.') {
                        alert('This mobile number has already submitted a prediction for this match.');
                    } else {
                        alert('Error submitting prediction.');
                    }
                }
            } catch(err) { alert('Network error.'); }
            btn.innerHTML = 'Submit Prediction'; btn.disabled = false;
        });
    </script>
</body>
</html>