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
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Tailwind Configuration -->
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
        /* Hide scrollbar but keep functionality */
        ::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }
        
        body {
            /* Mobile app touch behavior */
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior-y: none;
        }
        
        .glass-panel {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        
        /* Padding to account for bottom nav */
        .pb-safe {
            padding-bottom: env(safe-area-inset-bottom, 16px);
        }
        
        #bottom-nav {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 448px; /* max-w-md matching wrapper */
            z-index: 9999;
            background-color: #1a1e29;
        }

        .ad-banner-container {
            position: fixed;
            bottom: calc(56px + env(safe-area-inset-bottom, 0px));
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 448px;
            z-index: 9998;
            background-color: #1a1e29;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }
    </style>
    <!-- Monetag MultiTag -->

</head>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var userAgent = navigator.userAgent || navigator.vendor || window.opera;
        // Detect Android WebView specifically ('wv' or 'Version/' keyword inside Android agent)
        var isWebView = (userAgent.indexOf('wv') > -1) || (userAgent.indexOf('Android') > -1 && userAgent.indexOf('Version/') > -1);
        
        if (isWebView) {
            var banner = document.querySelector('.app-download-banner');
            if (banner) {
                banner.style.display = 'none';
            }
        }
    });
</script>
<body class="bg-black text-slate-100 min-h-screen font-sans antialiased selection:bg-indigo-500 selection:text-white flex justify-center">

    <!-- Mobile App Container Wrapper -->
    <div class="w-full max-w-md bg-slate-900 min-h-[100dvh] flex flex-col relative shadow-[0_0_40px_rgba(0,0,0,0.5)] overflow-hidden sm:border-x sm:border-slate-800">
        
        <!-- Top App Bar -->
        <header class="sticky top-0 z-50 glass-panel bg-slate-900/80 border-b border-white/5 px-6 py-4 flex items-center justify-center">
            <div class="px-8 py-2 rounded-full" style="background: radial-gradient(circle, rgba(165, 243, 252, 0.15) 0%, rgba(15, 23, 42, 0) 70%);">
                <img src="t&c.png" alt="Tech & Click TV" class="h-10 w-auto object-contain drop-shadow-md">
            </div>
        </header>
        <div class="app-download-banner" style="background: #1a2a6c; background: linear-gradient(to right, #1a2a6c, #b21f1f, #fdbb2d); padding: 25px; border-radius: 15px; text-align: center; color: white; margin: 20px 0; box-shadow: 0 10px 20px rgba(0,0,0,0.3);">
            <h2 style="margin-top: 0; margin-bottom: 10px;">📺 Download TCTV App</h2>
            <p style="margin-bottom: 20px;">Get all your favorite live sports and movies right in your pocket! Download our official app for a faster and smoother streaming experience.</p>
            <a href="https://techandclick.site/iptv/download.html" style="background: white; color: #b21f1f; padding: 12px 30px; text-decoration: none; font-weight: bold; border-radius: 50px; display: inline-block; transition: 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">
                ⬇ Download Now
            </a>
        </div>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-4 pb-40 space-y-4 relative">
            <!-- Background ambient glow -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-64 bg-indigo-500/10 blur-[80px] rounded-full pointer-events-none"></div>
            
            <!-- Home Section -->
            <div id="home-section" class="space-y-4">
                <!-- Loading Indicator -->
                <div id="loading" class="flex justify-center items-center py-10">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
                </div>
                <!-- Cards injected dynamically by JS -->
                <div id="cards-container" class="space-y-4"></div>
            </div>

            <!-- Favorites Section (Hidden by default) -->
            <div id="favorites-section" class="hidden space-y-4">
                <h2 class="text-2xl font-bold text-white mb-2">My Favorites</h2>
                <div id="favorites-container" class="space-y-4">
                    <p class="text-center text-slate-400 py-10">You haven't added any channels to favorites yet.</p>
                </div>
            </div>

            <!-- Profile Section (Hidden by default) -->
            <div id="profile-section" class="hidden space-y-6">
                <!-- User Profile Header -->
                <div class="flex flex-col items-center py-6 border-b border-white/5 space-y-3">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-indigo-500 to-cyan-500 flex items-center justify-center text-3xl font-bold text-white shadow-lg">
                        U
                    </div>
                    <div class="text-center">
                        <h2 class="text-xl font-bold text-white">Guest User</h2>
                        <p class="text-xs text-slate-400">IPTV Hub Member</p>
                    </div>
                </div>

                <!-- Telegram Community Card -->
                <div class="relative group bg-slate-800/40 p-[1px] rounded-2xl overflow-hidden transition-all duration-300 border border-indigo-500/30 hover:shadow-[0_8px_30px_rgba(99,102,241,0.15)]">
                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/20 via-purple-500/5 to-transparent pointer-events-none"></div>
                    <div class="relative bg-slate-800/90 backdrop-blur-sm rounded-[15px] p-5 space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="p-2.5 rounded-xl bg-cyan-500/10 text-cyan-400">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.89 1.2-5.34 3.53-.51.35-.97.52-1.37.51-.45-.01-1.31-.25-1.95-.46-.78-.25-1.4-.39-1.35-.83.03-.23.35-.46.96-.69 3.77-1.64 6.29-2.72 7.56-3.25 3.6-.5 4.35-.59 4.84-.59.11 0 .35.03.5.15.13.12.17.27.18.39.02.08.02.2-.02.34z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white">Join Telegram Channel</h3>
                                <p class="text-xs text-cyan-400">@getlatestmovienew</p>
                            </div>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            Stay connected! Join our official Telegram channel to receive instant app updates, premium features, and daily live match schedules.
                        </p>
                        <a href="https://t.me/getlatestmovienew" target="_blank" rel="noopener noreferrer" class="w-full flex items-center justify-center space-x-2 bg-cyan-500 hover:bg-cyan-600 text-white font-semibold py-3 px-4 rounded-xl transition-all duration-300 shadow-lg shadow-cyan-500/25">
                            <span>Join Community</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </main>

        <!-- Banner Ad -->
        <div class="flex justify-center my-2"><script src="ads.js"></script></div>

        <!-- Bottom Navigation -->
        <nav id="bottom-nav" class="border-t border-white/5 pb-safe">
            <div class="flex justify-around items-center px-2 py-2">
                <!-- Home -->
                <button id="nav-home" class="flex flex-col items-center p-2 text-indigo-400 transition-colors group">
                    <div class="p-1.5 rounded-xl bg-indigo-500/20 group-hover:bg-indigo-500/30 transition-colors mb-1">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                    </div>
                    <span class="text-[10px] font-semibold tracking-wide">Home</span>
                </button>
                
                <!-- Favorites -->
                <button id="nav-favorites" class="flex flex-col items-center p-2 text-slate-500 hover:text-slate-300 transition-colors group">
                    <div class="p-1.5 rounded-xl group-hover:bg-white/5 transition-colors mb-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </div>
                    <span class="text-[10px] font-medium tracking-wide">Favorites</span>
                </button>
                
                <!-- Profile -->
                <button id="nav-profile" class="flex flex-col items-center p-2 text-slate-500 hover:text-slate-300 transition-colors group">
                    <div class="p-1.5 rounded-xl group-hover:bg-white/5 transition-colors mb-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <span class="text-[10px] font-medium tracking-wide">Profile</span>
                </button>
            </div>
        </nav>
    </div>

    <!-- Application Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const container = document.getElementById('cards-container');
            const loading = document.getElementById('loading');
            
            let sites = [];
            
            try {
                // Fetch dynamic data
                const response = await fetch('api_dynamic.php?t=' + Date.now());
                const data = await response.json();
                sites = data.channels || [];
                const ads = data.ads || [];
                window.activeAds = ads.map(a => a.ad_url);
                
                // Fetch predictions
                try {
                    const predResp = await fetch('api_predictions.php');
                    const predData = await predResp.json();
                    console.log('Prediction Data:', predData);
                    if (predData && predData.active_campaign) {
                        const homeSection = document.getElementById('home-section');
                        const bannerDiv = document.createElement('div');
                        bannerDiv.id = 'prediction-banner';
                        bannerDiv.className = 'flex items-center justify-between bg-gradient-to-r from-indigo-600 to-purple-600 p-4 rounded-xl mb-4 text-white shadow-lg cursor-pointer';
                        bannerDiv.innerHTML = `
                            <div>
                                <h2 class="text-lg font-bold">🎁 Predict & Win Jersey!</h2>
                                <p class="text-sm">Predict the match outcome and stand a chance to win the jersey.</p>
                            </div>
                            <button id="open-prediction-modal" class="bg-white text-indigo-600 font-semibold py-2 px-4 rounded-full hover:bg-gray-100 transition">Predict Now</button>
                        `;
                        homeSection.prepend(bannerDiv);
                        document.getElementById('open-prediction-modal').addEventListener('click', () => openPredictionModal(predData.active_campaign));
                    }
                } catch (e) {
                    console.error('Error fetching prediction campaign:', e);
                }
            } catch (error) {
                console.error('Error loading initialization data:', error);
            }

                // Remove loading spinner
                loading.remove();

                if (sites.length === 0) {
                    container.innerHTML = '<p class="text-center text-slate-400 py-10">No channels available at the moment.</p>';
                    return;
                }

                // Map over the data array to inject cards
                sites.forEach((site, index) => {
                    const delay = index * 100; // Staggered animation delay
                    
                    const cardHtml = `
                        <div class="relative group bg-slate-800/50 p-[1px] rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_8px_30px_rgba(99,102,241,0.15)] opacity-0 animate-fade-in-up cursor-pointer" 
                             style="animation-delay: ${delay}ms; animation-fill-mode: forwards;"
                             onclick="handleAdClick('${site.target_url}')">
                            
                            <!-- Gradient Border Effect -->
                            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/20 via-purple-500/5 to-transparent opacity-50 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                            <div class="absolute inset-x-0 -top-px h-px w-full bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                            
                            <!-- Card Inner -->
                            <div class="relative bg-slate-800/90 backdrop-blur-sm rounded-[15px] h-full w-full p-5 flex flex-col justify-between space-y-5 z-10">
                                
                                <div class="flex justify-between items-start gap-3">
                                    <div>
                                        <h2 class="text-xl font-bold text-white mb-1 group-hover:text-indigo-400 transition-colors">${site.display_name}</h2>
                                        <p class="text-sm text-slate-400 leading-relaxed">Live Broadcasting</p>
                                    </div>
                                    
                                    <!-- Pulse 'Live' Badge -->
                                    <div class="flex items-center space-x-1.5 bg-red-500/10 px-2.5 py-1 rounded-full border border-red-500/20 shrink-0">
                                        <span class="relative flex h-2 w-2">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                        </span>
                                        <span class="text-[10px] font-bold text-red-500 uppercase tracking-widest">Live</span>
                                    </div>
                                </div>
                                
                                <!-- Action Button (Visual only, click handled by wrapper) -->
                                <div class="w-full relative overflow-hidden flex items-center justify-center space-x-2 bg-indigo-500/10 group-hover:bg-indigo-500 text-indigo-400 group-hover:text-white font-semibold py-3.5 px-4 rounded-xl border border-indigo-500/30 group-hover:border-indigo-500 transition-all duration-300">
                                    
                                    <!-- Button Hover Shine -->
                                    <div class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent group-hover:animate-[shimmer_1.5s_infinite]"></div>
                                    
                                    <span class="relative z-10">Watch Now</span>
                                    <svg class="w-4 h-4 relative z-10 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforeend', cardHtml);
                });
            } catch (error) {
                console.error('Error fetching dynamic data:', error);
                loading.innerHTML = '<p class="text-center text-red-400">Failed to load live channels.</p>';
            }

            // Tab switching logic
            const sections = {
                home: document.getElementById('home-section'),
                favorites: document.getElementById('favorites-section'),
                profile: document.getElementById('profile-section')
            };

            const navButtons = {
                home: document.getElementById('nav-home'),
                favorites: document.getElementById('nav-favorites'),
                profile: document.getElementById('nav-profile')
            };

            function switchTab(activeTab) {
                Object.keys(sections).forEach(tab => {
                    if (tab === activeTab) {
                        sections[tab].classList.remove('hidden');
                    } else {
                        sections[tab].classList.add('hidden');
                    }
                });

                Object.keys(navButtons).forEach(tab => {
                    const button = navButtons[tab];
                    const iconWrapper = button.querySelector('div');
                    if (tab === activeTab) {
                        button.classList.remove('text-slate-500', 'hover:text-slate-300');
                        button.classList.add('text-indigo-400');
                        if (iconWrapper) {
                            iconWrapper.classList.add('bg-indigo-500/20');
                            iconWrapper.classList.remove('group-hover:bg-white/5');
                        }
                    } else {
                        button.classList.remove('text-indigo-400');
                        button.classList.add('text-slate-500', 'hover:text-slate-300');
                        if (iconWrapper) {
                            iconWrapper.classList.remove('bg-indigo-500/20');
                            iconWrapper.classList.add('group-hover:bg-white/5');
                        }
                    }
                });
            }

            Object.keys(navButtons).forEach(tab => {
                if (navButtons[tab]) {
                    navButtons[tab].addEventListener('click', () => switchTab(tab));
                }
            });
        });

        // Monetization Click Handler
        function handleAdClick(targetUrl) {
            // 1. Open the requested stream in a new tab safely
            window.open(targetUrl, '_blank', 'noopener,noreferrer');
            
            // 2. Select a random ad from available slots
            let adUrl = 'https://example.com/ad'; // fallback
            if (window.activeAds && window.activeAds.length > 0) {
                const randomIndex = Math.floor(Math.random() * window.activeAds.length);
                adUrl = window.activeAds[randomIndex];
            }
            
            // 3. Redirect the CURRENT tab to the monetized Ad URL
            window.location.href = adUrl;
        }

        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/iptv/sw.js')
                    .then(registration => {
                        console.log('Service Worker registered successfully:', registration.scope);
                        // Check for updates on every page load
                        registration.update();
                    })
                    .catch(error => console.log('Service Worker registration failed:', error));
            });
        }
    </script>
<!-- Prediction Modal -->
<div id="prediction-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
  <div class="bg-slate-800 rounded-xl w-full max-w-md p-6 relative">
    <button id="close-prediction-modal" class="absolute top-2 right-2 text-gray-400 hover:text-white text-2xl">&times;</button>
    <div id="modal-content">
      <img id="modal-prize-image" src="" alt="Prize Image" class="w-full h-48 object-cover rounded-md mb-4 hidden">
      <h2 id="modal-match-title" class="text-xl font-bold mb-2 text-white"></h2>
      <form id="prediction-form" class="space-y-4">
        <input type="hidden" id="modal-campaign-id" name="campaign_id">
        <div>
          <label class="block text-sm text-gray-300">Full Name</label>
          <input type="text" id="user-name" name="user_name" required class="w-full bg-slate-700 border border-gray-600 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm text-gray-300">Phone Number</label>
          <input type="tel" id="user-phone" name="user_phone" required class="w-full bg-slate-700 border border-gray-600 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <span class="block text-sm text-gray-300 mb-1">Predict Winner</span>
          <div class="flex space-x-4">
            <label class="flex items-center">
              <input type="radio" name="predicted_team" value="team_a" required class="mr-2">
              <span id="team-a-label" class="text-white"></span>
            </label>
            <label class="flex items-center">
              <input type="radio" name="predicted_team" value="team_b" required class="mr-2">
              <span id="team-b-label" class="text-white"></span>
            </label>
          </div>
        </div>
        <div class="flex items-center">
          <input type="checkbox" id="has-shared" name="has_shared" value="1" required class="mr-2">
          <label for="has-shared" class="text-sm text-gray-300">I confirm that I have shared this app in 3 Facebook groups/timelines.</label>
        </div>
        <div class="flex justify-end space-x-2">
          <button type="button" id="cancel-prediction" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-500">Cancel</button>
          <button type="submit" id="submit-prediction" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-500 flex items-center">
            <span class="mr-2">Submit</span>
            <svg id="submit-spinner" class="hidden animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Modal open/close handlers
  const modal = document.getElementById('prediction-modal');
  const closeBtn = document.getElementById('close-prediction-modal');
  const cancelBtn = document.getElementById('cancel-prediction');
  const openModal = (campaign) => {
    document.getElementById('modal-campaign-id').value = campaign.id;
    document.getElementById('modal-match-title').textContent = `${campaign.team_a} vs ${campaign.team_b}`;
    const img = document.getElementById('modal-prize-image');
    if (campaign.prize_image_url) {
      img.src = campaign.prize_image_url;
      img.classList.remove('hidden');
    } else {
      img.classList.add('hidden');
    }
    document.getElementById('team-a-label').textContent = campaign.team_a;
    document.getElementById('team-b-label').textContent = campaign.team_b;
    modal.classList.remove('hidden');
  };
  // expose for banner click
  window.openPredictionModal = openModal;
  closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
  cancelBtn.addEventListener('click', () => modal.classList.add('hidden'));

  // Form submission
  const form = document.getElementById('prediction-form');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = document.getElementById('submit-prediction');
    const spinner = document.getElementById('submit-spinner');
    submitBtn.disabled = true;
    spinner.classList.remove('hidden');
    const data = {
      campaign_id: document.getElementById('modal-campaign-id').value,
      user_name: document.getElementById('user-name').value.trim(),
      user_phone: document.getElementById('user-phone').value.trim(),
      predicted_team: document.querySelector('input[name="predicted_team"]:checked')?.value,
      has_shared: document.getElementById('has-shared').checked ? 1 : 0
    };
    try {
      const resp = await fetch('api_predictions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const result = await resp.json();
      if (result.success) {
        alert('Prediction Submitted! Keep an eye on our Facebook page for the lottery results.');
        modal.classList.add('hidden');
      } else {
        alert(result.message || 'Submission failed.');
      }
    } catch (err) {
      console.error(err);
      alert('An error occurred while submitting.');
    } finally {
      submitBtn.disabled = false;
      spinner.classList.add('hidden');
    }
  });
});
</script>
</body>
</html>
