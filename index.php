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
        
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }
    </style>
    <!-- Monetag MultiTag -->
    <script src="https://quge5.com/88/tag.min.js" data-zone="249976" async data-cfasync="false"></script>
</head>
<body class="bg-black text-slate-100 min-h-screen font-sans antialiased selection:bg-indigo-500 selection:text-white flex justify-center">

    <!-- Mobile App Container Wrapper -->
    <div class="w-full max-w-md bg-slate-900 min-h-[100dvh] flex flex-col relative shadow-[0_0_40px_rgba(0,0,0,0.5)] overflow-hidden sm:border-x sm:border-slate-800">
        
        <!-- Top App Bar -->
        <header class="sticky top-0 z-50 glass-panel bg-slate-900/80 border-b border-white/5 px-6 py-4 flex items-center justify-center">
            <div class="px-8 py-2 rounded-full" style="background: radial-gradient(circle, rgba(165, 243, 252, 0.15) 0%, rgba(15, 23, 42, 0) 70%);">
                <img src="t&c.png" alt="Tech & Click TV" class="h-10 w-auto object-contain drop-shadow-md">
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-4 pb-6 space-y-4 relative" id="cards-container">
            <!-- Background ambient glow -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-64 bg-indigo-500/10 blur-[80px] rounded-full pointer-events-none"></div>
            
            <!-- Loading Indicator -->
            <div id="loading" class="flex justify-center items-center py-10">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
            </div>
            
            <!-- Cards injected dynamically by JS -->
        </main>

        <!-- Bottom Navigation -->
        <nav class="sticky bottom-0 z-50 glass-panel bg-slate-900/85 border-t border-white/5 pb-safe">
            <div class="flex justify-around items-center px-2 py-2">
                <!-- Home -->
                <button class="flex flex-col items-center p-2 text-indigo-400 transition-colors group">
                    <div class="p-1.5 rounded-xl bg-indigo-500/20 group-hover:bg-indigo-500/30 transition-colors mb-1">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                    </div>
                    <span class="text-[10px] font-semibold tracking-wide">Home</span>
                </button>
                
                <!-- Favorites -->
                <button class="flex flex-col items-center p-2 text-slate-500 hover:text-slate-300 transition-colors group">
                    <div class="p-1.5 rounded-xl group-hover:bg-white/5 transition-colors mb-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </div>
                    <span class="text-[10px] font-medium tracking-wide">Favorites</span>
                </button>
                
                <!-- Profile -->
                <button class="flex flex-col items-center p-2 text-slate-500 hover:text-slate-300 transition-colors group">
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
            
            try {
                // Fetch dynamic data from API
                const response = await fetch('api_dynamic.php');
                const data = await response.json();
                
                const sites = data.channels || [];
                const ads = data.ads || [];
                
                // Store active ads globally for the click handler
                window.activeAds = ads.map(a => a.ad_url);

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
    </script>
</body>
</html>
