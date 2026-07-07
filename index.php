<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once 'config.php';

$customChannels = [];
try {
    $pdo = new PDO(
        "mysql:host={$servername};dbname={$dbname};charset={$charset}",
        $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $stmt = $pdo->query("SELECT id, display_name, target_url FROM custom_channels WHERE target_url != '' ORDER BY id ASC");
    $customChannels = $stmt->fetchAll();
    $pdo = null;
} catch (PDOException $e) { $pdo = null; }

$customChannelsJson = json_encode(array_map(fn($ch) => [
    'name'      => $ch['display_name'] ?: 'Channel',
    'group'     => 'Custom',
    'logo'      => '',
    'raw_url'   => $ch['target_url'],
    'proxy_url' => 'proxy.php?url=' . rawurlencode($ch['target_url']) . '&raw=true',
], $customChannels), JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-FHBSF7YR8L"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-FHBSF7YR8L');
</script>

<!-- ══════════════════════════════════════════════════════════════════════════
     SEO META TAGS — Search Engine Optimization
     ══════════════════════════════════════════════════════════════════════════ -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#0a0e1a">

<!-- Primary Meta Tags -->
<title>TCTV Live - Watch Free Live Sports & Football Streaming Online | ফ্রি লাইভ খেলা</title>
<meta name="title" content="TCTV Live - Watch Free Live Sports & Football Streaming Online | ফ্রি লাইভ খেলা">
<meta name="description" content="Watch live football, cricket, and sports streaming for free on TCTV Live. Win VIP FIFA World Cup 2026 Tickets and Free Merchandise! No subscription required. ফ্রি লাইভ ফুটবল দেখুন ও বিশ্বকাপ টিকিট জিতুন!">
<meta name="keywords" content="free live football, watch sports online, live football streaming, live cricket streaming, FIFA World Cup 2026 live, Premier League live stream, Champions League free, live sports streaming, win world cup tickets, free FIFA merchandise, world cup ticket giveaway, বিশ্বকাপ টিকিট, ফ্রি লাইভ খেলা, ফুটবল লাইভ দেখুন, বিশ্বকাপ লাইভ স্ট্রিমিং, TCTV live, Tech and Click TV, free sports streaming, watch live sports free">
<meta name="author" content="Tech & Click TV">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="googlebot" content="index, follow">
<meta name="language" content="English, Bengali">
<link rel="canonical" href="https://techandclick.site/iptv/">

<!-- Open Graph / Facebook / Messenger -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Tech & Click TV">
<meta property="og:title" content="TCTV Live - Watch Free Live Sports & Football Streaming">
<meta property="og:description" content="Stream all major sports live for free. Enter our giveaway to Win VIP World Cup 2026 Tickets and Free Merchandise! No ads, no subscription. ফ্রি লাইভ ফুটবল ও বিশ্বকাপ টিকিট জিতুন!">
<meta property="og:url" content="https://techandclick.site/iptv/">
<meta property="og:image" content="https://techandclick.site/iptv/wc2026.jpg">
<meta property="og:image:secure_url" content="https://techandclick.site/iptv/wc2026.jpg">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="TCTV Live - Free Sports Streaming">
<meta property="og:locale" content="en_US">
<meta property="og:locale:alternate" content="bn_BD">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="TCTV Live - Watch Free Live Sports & Football Streaming">
<meta name="twitter:description" content="Stream all major sports live for free. Enter our giveaway to Win VIP World Cup 2026 Tickets and Free Merchandise! No ads, no subscription.">
<meta name="twitter:image" content="https://techandclick.site/iptv/wc2026.jpg">

<!-- Schema.org Structured Data (JSON-LD) -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "Tech & Click TV",
  "alternateName": "TCTV Live",
  "url": "https://techandclick.site/iptv/",
  "description": "Watch live football, cricket, and sports streaming for free. FIFA World Cup 2026, Premier League, Champions League, and more.",
  "inLanguage": ["en", "bn"],
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://techandclick.site/iptv/?q={search_term_string}",
    "query-input": "required name=search_term_string"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Tech & Click TV",
    "logo": {
      "@type": "ImageObject",
      "url": "https://techandclick.site/iptv/icon-512.png"
    }
  }
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BroadcastService",
  "name": "TCTV Live Sports Streaming",
  "description": "Free live streaming service for football, cricket, and major sports events worldwide",
  "broadcastDisplayName": "Tech & Click TV Live",
  "url": "https://techandclick.site/iptv/",
  "image": "https://techandclick.site/iptv/wc2026.jpg",
  "provider": {
    "@type": "Organization",
    "name": "Tech & Click TV"
  },
  "genre": ["Sports", "Football", "Cricket", "Live Streaming"],
  "potentialAction": {
    "@type": "WatchAction",
    "target": "https://techandclick.site/iptv/"
  }
}
</script>

<!-- PWA & Mobile App Tags -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="TCTV Live">
<meta name="application-name" content="TCTV Live">

<!-- Icons & Manifest -->
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="icon-512.png">
<link rel="manifest" href="manifest.json">

<!-- Preconnect to External Resources for Performance -->
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="dns-prefetch" href="https://www.googletagmanager.com">

<!-- HLS.js Library -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js"></script>

<style>
/* ── Reset & Base ─────────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%;height:-webkit-fill-available}
body{
  min-height:100vh;min-height:-webkit-fill-available;
  background:#0a0e1a;color:#e2e8f0;
  font-family:'Inter',system-ui,sans-serif;
  -webkit-tap-highlight-color:transparent;
  overscroll-behavior:none;overflow:hidden;
}
::-webkit-scrollbar{width:0;background:transparent}

/* ── SEO: Hidden H1 (visible to search engines, hidden visually) ─── */
.seo-h1{
  position:absolute;
  width:1px;height:1px;
  padding:0;margin:-1px;
  overflow:hidden;
  clip:rect(0,0,0,0);
  white-space:nowrap;
  border-width:0;
  /* Alternative method: */
  /* position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden; */
}

/* ── Layout shell ─────────────────────────────────────────────── */
#app{
  display:flex;flex-direction:column;
  height:100vh;height:100dvh;
  max-width:480px;margin:0 auto;
  position:relative;overflow:hidden;
  background:linear-gradient(180deg,#0d1220 0%,#0a0e1a 100%);
  border-left:1px solid rgba(255,255,255,.04);
  border-right:1px solid rgba(255,255,255,.04);
}

/* ── Header ───────────────────────────────────────────────────── */
#hdr{
  flex-shrink:0;padding:14px 18px 12px;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(10,14,26,.92);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(255,255,255,.06);
  position:relative;z-index:50;
}
#hdr-logo{height:36px;object-fit:contain}
#live-badge{
  display:flex;align-items:center;gap:6px;
  background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);
  border-radius:20px;padding:4px 10px;
}
.live-dot{
  width:7px;height:7px;border-radius:50%;background:#ef4444;
  animation:livePulse 1.4s ease-in-out infinite;
}
@keyframes livePulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}
#live-badge span{font-size:10px;font-weight:700;color:#ef4444;letter-spacing:.08em;text-transform:uppercase}

/* ── Scrollable main area ─────────────────────────────────────── */
#main{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:0 0 80px}

/* ── Match info card ──────────────────────────────────────────── */
#match-card{
  margin:14px 12px 10px;padding:14px 16px;
  background:linear-gradient(135deg,rgba(59,130,246,.12),rgba(99,102,241,.08));
  border:1px solid rgba(96,165,250,.18);
  border-radius:18px;
  display:flex;align-items:center;gap:12px;
  transition:opacity .3s;
}
#match-card.hidden{display:none}
#match-icon{
  width:42px;height:42px;border-radius:12px;object-fit:cover;flex-shrink:0;
  background:rgba(59,130,246,.15);
  display:flex;align-items:center;justify-content:center;font-size:20px;
}
#match-info{flex:1;min-width:0}
#match-title{font-size:14px;font-weight:700;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#match-sub{font-size:11px;color:#64748b;margin-top:2px}
#match-time{font-size:11px;font-weight:600;color:#38bdf8;margin-top:4px}

/* ── Video card ───────────────────────────────────────────────── */
#video-card{
  margin:0 12px 10px;
  border-radius:22px;overflow:hidden;
  border:1px solid rgba(255,255,255,.07);
  background:#000;
  box-shadow:0 20px 60px rgba(0,0,0,.6);
  position:relative;
}
#video-wrap{position:relative;width:100%;aspect-ratio:16/9;background:#000;cursor:pointer}
#player{width:100%;height:100%;display:block;background:#000}

/* ── Custom video controls ────────────────────────────────────── */
#custom-controls{
  position:absolute;bottom:0;left:0;right:0;z-index:8;
  display:flex;align-items:center;gap:8px;
  padding:10px 14px;
  background:linear-gradient(0deg,rgba(0,0,0,.85) 0%,transparent 100%);
  opacity:1;transition:opacity .35s;
}
#video-wrap:not(:hover):not(.controls-locked) #custom-controls{opacity:0}
#video-wrap.controls-locked #custom-controls{opacity:1}
.ctrl-btn{
  background:none;border:none;color:#fff;cursor:pointer;
  padding:4px;display:flex;align-items:center;justify-content:center;
  opacity:.9;transition:opacity .2s,transform .15s;
}
.ctrl-btn:hover{opacity:1;transform:scale(1.1)}
.ctrl-btn svg{width:22px;height:22px;fill:#fff}
#ctrl-play svg{width:26px;height:26px}
.ctrl-spacer{flex:1}
#ctrl-volume-wrap{display:flex;align-items:center;gap:4px}
#ctrl-volume{
  -webkit-appearance:none;appearance:none;
  width:60px;height:3px;border-radius:2px;
  background:rgba(255,255,255,.25);outline:none;
  cursor:pointer;transition:width .2s;
}
#ctrl-volume::-webkit-slider-thumb{
  -webkit-appearance:none;width:12px;height:12px;
  border-radius:50%;background:#fff;cursor:pointer;
}
#ctrl-volume::-moz-range-thumb{
  width:12px;height:12px;border:none;
  border-radius:50%;background:#fff;cursor:pointer;
}

/* ── In-player LIVE badge ─────────────────────────────────────── */
#player-live-badge{
  position:absolute;top:12px;left:12px;z-index:8;
  display:flex;align-items:center;gap:5px;
  background:rgba(220,38,38,.9);border-radius:4px;
  padding:3px 8px;font-size:10px;font-weight:800;
  color:#fff;letter-spacing:.06em;text-transform:uppercase;
  box-shadow:0 2px 10px rgba(220,38,38,.4);
  pointer-events:none;
}
#player-live-badge .plb-dot{
  width:6px;height:6px;border-radius:50%;background:#fff;
  animation:livePulse 1.4s ease-in-out infinite;
}
#viewer-count{
  margin-left:8px;padding-left:8px;
  border-left:1px solid rgba(255,255,255,.3);
  font-size:9px;font-weight:700;
  opacity:.9;
}

/* ── Player Watermark Logo ────────────────────────────────────── */
#player-watermark {
  position: absolute;
  bottom: 52px;
  right: 15px;
  height: 25px;
  width: auto;
  opacity: 0.4;
  z-index: 7;
  pointer-events: none;
  user-select: none;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
  transition: opacity 0.35s;
}
#video-wrap:not(:hover):not(.controls-locked) #player-watermark {
  opacity: 0.25; /* dimmer when controls are hidden to stay non-intrusive */
}
#video-wrap.controls-locked #player-watermark,
#video-wrap:hover #player-watermark {
  opacity: 0.55;
}

/* ── In-player server overlay ─────────────────────────────────── */
#player-servers{
  position:absolute;top:12px;right:12px;z-index:8;
  display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;
  max-width:70%;
  opacity:1;transition:opacity .35s;
}
#video-wrap:not(:hover):not(.controls-locked) #player-servers{opacity:0}
#video-wrap.controls-locked #player-servers{opacity:1}
.psrv-btn{
  padding:5px 12px;border-radius:6px;
  border:1px solid rgba(255,255,255,.2);
  background:rgba(0,0,0,.55);
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  color:rgba(255,255,255,.85);font-size:11px;font-weight:700;
  cursor:pointer;white-space:nowrap;
  transition:all .2s;display:flex;align-items:center;gap:5px;
}
.psrv-btn:hover{background:rgba(59,130,246,.4);border-color:rgba(96,165,250,.5)}
.psrv-btn.active{
  background:rgba(59,130,246,.85);border-color:rgba(99,102,241,.7);
  color:#fff;box-shadow:0 2px 12px rgba(59,130,246,.4);
}
.psrv-btn .psrv-dot{
  width:5px;height:5px;border-radius:50%;
  background:rgba(255,255,255,.4);
}
.psrv-btn.active .psrv-dot{background:#fff}

/* ── Overlay states (spinner / error) ────────────────────────── */
.overlay{
  position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  background:rgba(0,0,0,.85);z-index:5;gap:10px;
  transition:opacity .25s;
}
.overlay.gone{opacity:0;pointer-events:none}

.spin-ring{
  width:46px;height:46px;border-radius:50%;
  border:3px solid rgba(255,255,255,.12);
  border-top-color:#3b82f6;
  animation:spin .75s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
#spin-label{font-size:12px;color:#64748b;font-weight:500}
#spin-source{font-size:10px;color:#475569;margin-top:2px}

/* error overlay */
#err-overlay{display:none}
#err-overlay.visible{display:flex}
#err-icon{font-size:36px;margin-bottom:2px}
#err-msg{font-size:13px;color:#f87171;font-weight:600;text-align:center;max-width:220px}
#err-sub{font-size:11px;color:#475569;text-align:center;max-width:240px;margin-top:3px}
.err-actions{display:flex;gap:8px;margin-top:10px}
.btn{
  padding:8px 18px;border-radius:20px;border:none;
  font-size:12px;font-weight:700;cursor:pointer;
  transition:transform .1s,opacity .2s;
}
.btn:active{transform:scale(.95)}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff}
.btn-ghost{background:rgba(255,255,255,.08);color:#94a3b8;border:1px solid rgba(255,255,255,.1)}

/* ── Countdown to auto-switch ─────────────────────────────────── */
#auto-switch{
  font-size:10px;color:#64748b;text-align:center;
  margin-top:6px;
}

/* ── Promo buttons (Telegram + App download) ─────────────────── */
#promo-area{
  margin:10px 12px 8px;display:flex;flex-direction:column;gap:8px;
}
.promo-btn{
  display:flex;align-items:center;gap:10px;
  padding:12px 16px;border-radius:14px;
  text-decoration:none;color:#e2e8f0;
  font-size:12px;font-weight:600;
  transition:transform .15s,box-shadow .2s;
  border:1px solid rgba(255,255,255,.08);
}
.promo-btn:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(0,0,0,.4)}
.promo-btn:active{transform:scale(.98)}
.promo-btn .promo-icon{
  width:36px;height:36px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;font-size:18px;
}
.promo-btn .promo-text{flex:1;min-width:0}
.promo-btn .promo-title{font-size:13px;font-weight:700;color:#f1f5f9}
.promo-btn .promo-sub{font-size:10px;color:#94a3b8;margin-top:2px}
.promo-btn .promo-arrow{
  color:#64748b;font-size:16px;font-weight:700;flex-shrink:0;
  transition:transform .2s;
}
.promo-btn:hover .promo-arrow{transform:translateX(3px)}

#promo-telegram{
  background:linear-gradient(135deg,rgba(0,136,204,.15),rgba(0,136,204,.05));
  border-color:rgba(0,136,204,.25);
}
#promo-telegram .promo-icon{background:rgba(0,136,204,.2);color:#0088cc}

#promo-app{
  background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(59,130,246,.06));
  border-color:rgba(99,102,241,.2);
}
#promo-app .promo-icon{background:rgba(99,102,241,.2);color:#818cf8}

/* Ticket Gold/Red Pulse Button */
#promo-tickets {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.25), rgba(245, 158, 11, 0.2));
  border-color: rgba(245, 158, 11, 0.45);
  box-shadow: 0 0 10px rgba(245, 158, 11, 0.15);
  animation: goldRedPulse 2s infinite alternate;
}
#promo-tickets .promo-icon { background: rgba(245, 158, 11, 0.25); color: #f59e0b; font-size: 20px; }
#promo-tickets .promo-title { color: #f59e0b; text-shadow: 0 0 8px rgba(245,158,11,0.3); }

/* Merchandise Vibrant Green/Blue Button */
#promo-merch {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(59, 130, 246, 0.15));
  border-color: rgba(16, 185, 129, 0.4);
}
#promo-merch .promo-icon { background: rgba(16, 185, 129, 0.25); color: #10b981; font-size: 20px; }
#promo-merch .promo-title { color: #10b981; }

@keyframes goldRedPulse {
  0% {
    box-shadow: 0 0 4px rgba(245, 158, 11, 0.2);
    border-color: rgba(245, 158, 11, 0.45);
  }
  100% {
    box-shadow: 0 0 15px rgba(239, 68, 68, 0.45);
    border-color: rgba(239, 68, 68, 0.7);
  }
}

/* ── Social share buttons ─────────────────────────────────────── */
#share-area{
  margin:10px 12px 8px;
}
#share-label{
  font-size:11px;font-weight:700;color:#64748b;
  margin-bottom:8px;letter-spacing:.05em;text-transform:uppercase;
}
.share-btns{
  display:flex;gap:8px;
}
.share-btn{
  flex:1;display:flex;align-items:center;justify-content:center;gap:8px;
  padding:12px 16px;border-radius:14px;
  text-decoration:none;font-size:13px;font-weight:700;
  transition:transform .15s,box-shadow .2s;
  border:1px solid rgba(255,255,255,.08);
}
.share-btn:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(0,0,0,.4)}
.share-btn:active{transform:scale(.98)}
#share-fb{
  background:linear-gradient(135deg,rgba(24,119,242,.15),rgba(24,119,242,.05));
  border-color:rgba(24,119,242,.25);color:#1877f2;
}
#share-messenger{
  background:linear-gradient(135deg,rgba(0,132,255,.15),rgba(0,132,255,.05));
  border-color:rgba(0,132,255,.25);color:#0084ff;
}
#share-wa{
  background:linear-gradient(135deg,rgba(37,211,102,.15),rgba(37,211,102,.05));
  border-color:rgba(37,211,102,.25);color:#25d366;
}

/* ── External server area (hidden — servers now shown on player) ── */
#server-area{display:none}

/* ── Desktop: widen app shell ─────────────────────────────────── */
@media (min-width:768px){
  #app{max-width:860px}
}

/* ── Manual URL input panel ───────────────────────────────────── */
#custom-panel{
  margin:0 12px 10px;
  border:1px solid rgba(255,255,255,.07);
  border-radius:18px;overflow:hidden;
}
#custom-toggle{
  width:100%;padding:12px 16px;
  background:rgba(255,255,255,.03);
  display:flex;align-items:center;justify-content:space-between;
  cursor:pointer;border:none;color:#64748b;
  font-size:12px;font-weight:600;
}
#custom-toggle svg{transition:transform .25s}
#custom-toggle.open svg{transform:rotate(180deg)}
#custom-body{
  display:none;padding:12px 14px;
  background:rgba(0,0,0,.2);
  border-top:1px solid rgba(255,255,255,.05);
}
#custom-body.open{display:block}
#custom-url{
  width:100%;background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.1);
  border-radius:10px;color:#e2e8f0;
  font-size:12px;padding:10px 12px;
  outline:none;
}
#custom-url:focus{border-color:rgba(96,165,250,.5)}
#custom-url::placeholder{color:#475569}
#custom-play{
  margin-top:8px;width:100%;
  padding:10px;border-radius:10px;
  background:linear-gradient(135deg,#3b82f6,#6366f1);
  color:#fff;font-size:12px;font-weight:700;
  border:none;cursor:pointer;
}

/* ── Bottom navigation ───────────────────────────────────────── */
#bottom-nav{
  position:fixed;bottom:0;left:50%;transform:translateX(-50%);
  width:100%;max-width:480px;
  background:rgba(10,14,26,.95);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-top:1px solid rgba(255,255,255,.06);
  padding-bottom:env(safe-area-inset-bottom,0px);
  z-index:9999;  /* Increased z-index for WebView visibility */
  /* Ensure it stays above video player and all other elements */
}
.nav-inner{
  display:flex;justify-content:space-around;align-items:center;padding:4px 0;
  /* Prevent overflow on small screens */
  overflow:hidden;
}
.nav-btn{
  flex:1;display:flex;flex-direction:column;align-items:center;
  gap:2px;padding:8px 4px;border:none;background:none;
  color:#475569;cursor:pointer;transition:color .2s;
  font-size:10px;font-weight:600;letter-spacing:.02em;
  /* Ensure buttons are tappable in WebView */
  min-height:56px;
  -webkit-tap-highlight-color:transparent;
  touch-action:manipulation;
}
.nav-btn.active{color:#3b82f6}
.nav-btn svg{width:20px;height:20px}
.nav-btn .nav-icon-wrap{
  padding:5px;border-radius:10px;
  transition:background .2s;
}
.nav-btn.active .nav-icon-wrap{background:rgba(59,130,246,.15)}

/* Mobile-specific fixes for WebView */
@media (max-width: 768px){
  #bottom-nav{
    /* Force full width on mobile */
    width:100%;
    max-width:100%;
    left:0;
    transform:none;
    /* Ensure it's always visible */
    display:block !important;
    visibility:visible !important;
  }
  .nav-btn{
    /* Larger tap targets for mobile */
    min-height:60px;
    padding:10px 4px;
  }
  .nav-btn svg{
    width:22px;
    height:22px;
  }
}

/* ── Tab panels ───────────────────────────────────────────────── */
.tab-panel{display:none}
.tab-panel.active{display:block}

/* Favorites + Profile placeholder */
.empty-state{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:48px 24px;gap:12px;text-align:center;
}
.empty-state svg{opacity:.25}
.empty-state h3{font-size:15px;font-weight:700;color:#475569}
.empty-state p{font-size:12px;color:#334155;max-width:200px}

/* ── Scroll-fade background art ──────────────────────────────── */
#bg-art{
  position:fixed;inset:0;z-index:-1;
  background-image:url('wc2026.jpg');
  background-size:cover;background-position:center;
  filter:blur(28px) brightness(.3);
  transform:scale(1.08);
  max-width:480px;left:50%;transform:translateX(-50%) scale(1.08);
}

/* ── Welcome popup/modal ──────────────────────────────────────── */
#welcome-overlay{
  position:fixed;inset:0;z-index:9999;
  background:rgba(0,0,0,.85);backdrop-filter:blur(8px);
  display:flex;align-items:center;justify-content:center;
  opacity:0;pointer-events:none;
  transition:opacity .35s ease-out;
}
#welcome-overlay.show{opacity:1;pointer-events:all}
#welcome-modal{
  background:linear-gradient(135deg,#1a1f35 0%,#0f1420 100%);
  border:1px solid rgba(255,255,255,.1);
  border-radius:20px;
  max-width:420px;width:calc(100% - 32px);
  padding:28px 24px 24px;
  position:relative;
  box-shadow:0 20px 60px rgba(0,0,0,.6);
  transform:scale(.92);
  transition:transform .35s ease-out;
}
#welcome-overlay.show #welcome-modal{transform:scale(1)}
#welcome-close{
  position:absolute;top:12px;right:12px;
  width:32px;height:32px;border-radius:50%;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.1);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;color:#94a3b8;font-size:18px;
  transition:all .2s;
}
#welcome-close:hover{background:rgba(255,255,255,.15);color:#e2e8f0;transform:rotate(90deg)}
#welcome-icon{
  font-size:48px;text-align:center;margin-bottom:16px;
  filter:drop-shadow(0 4px 12px rgba(59,130,246,.3));
}
#welcome-title{
  font-size:20px;font-weight:800;color:#f1f5f9;
  text-align:center;margin-bottom:12px;letter-spacing:-.02em;
}
#welcome-message{
  font-size:14px;line-height:1.6;color:#cbd5e1;
  text-align:center;margin-bottom:24px;
}
#welcome-button{
  width:100%;padding:14px;border-radius:12px;
  background:linear-gradient(135deg,#3b82f6,#6366f1);
  border:none;color:#fff;font-size:14px;font-weight:700;
  cursor:pointer;transition:all .2s;
  box-shadow:0 4px 12px rgba(59,130,246,.3);
}
#welcome-button:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(59,130,246,.4)}
#welcome-button:active{transform:scale(.98)}

/* ── Announcement bar ────────────────────────────────────────── */
#warn-bar{
  margin:0 12px 10px;padding:10px 14px;
  border-radius:12px;
  background:rgba(251,191,36,.08);
  border:1px solid rgba(251,191,36,.2);
  font-size:11px;color:#fbbf24;
  display:none;
  align-items:flex-start;gap:8px;
}
#warn-bar.visible{display:flex}
#warn-bar svg{flex-shrink:0;margin-top:1px}

/* ── Source badge on video ───────────────────────────────────── */
#src-badge{
  position:absolute;top:10px;left:10px;z-index:6;
  background:rgba(0,0,0,.6);backdrop-filter:blur(6px);
  border:1px solid rgba(255,255,255,.1);
  border-radius:8px;padding:3px 8px;
  font-size:9px;font-weight:700;color:#94a3b8;letter-spacing:.06em;
  text-transform:uppercase;
  display:none;
}
#src-badge.visible{display:block}
</style>
</head>
<body>

<!-- ── Welcome popup/modal ────────────────────────────────────── -->
<div id="welcome-overlay">
  <div id="welcome-modal">
    <div id="welcome-close" onclick="TCTV.closeWelcome()">✕</div>
    <div id="welcome-icon">📢</div>
    <div id="welcome-title">Important Notice</div>
    <div id="welcome-message">
      If the stream appears offline or unavailable right now, please don't worry! 
      Our live servers will automatically activate as soon as the match begins. 
      Please stay tuned!
    </div>
    <button id="welcome-button" onclick="TCTV.closeWelcome()">Got it!</button>
  </div>
</div>

<div id="bg-art"></div>

<div id="app">
  <!-- ── Header ─────────────────────────────────────────────────── -->
  <div id="hdr">
    <img id="hdr-logo" src="t&amp;c.png" alt="Tech &amp; Click TV">
    <div id="live-badge">
      <div class="live-dot"></div>
      <span>Live</span>
    </div>
  </div>

  <!-- SEO: Semantic H1 (hidden visually, visible to search engines) -->
  <h1 class="seo-h1">Watch Free Live Football & Sports Streaming Online - FIFA World Cup 2026, Premier League, Champions League | ফ্রি লাইভ ফুটবল স্ট্রিমিং</h1>

  <!-- ── Scrollable body ────────────────────────────────────────── -->
  <div id="main">

    <!-- Tab: Home -->
    <div id="tab-home" class="tab-panel active">

      <!-- Match info card -->
      <div id="match-card" class="hidden">
        <div id="match-icon">⚽</div>
        <div id="match-info">
          <div id="match-title">FIFA World Cup 2026</div>
          <div id="match-sub">Live • Sports</div>
          <div id="match-time" id="match-time"></div>
        </div>
      </div>

      <!-- Warning bar (shown when fallback is active) -->
      <div id="warn-bar">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
        </svg>
        <span id="warn-text"></span>
      </div>

      <!-- Video card -->
      <div id="video-card">
        <div id="video-wrap">
          <video id="player" playsinline muted></video>

          <!-- LIVE badge with viewer count -->
          <div id="player-live-badge">
            <div class="plb-dot"></div>LIVE
            <span id="viewer-count">👁️ 2.5K</span>
          </div>

          <!-- In-player server buttons -->
          <div id="player-servers"></div>

          <!-- Source badge -->
          <div id="src-badge"></div>

          <!-- Video Watermark Logo -->
          <img id="player-watermark" src="t&amp;c.png" alt="Watermark">

          <!-- Custom controls (no timeline/duration) -->
          <div id="custom-controls">
            <button class="ctrl-btn" id="ctrl-play" title="Play / Pause">
              <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </button>
            <div id="ctrl-volume-wrap">
              <button class="ctrl-btn" id="ctrl-mute" title="Mute / Unmute">
                <svg viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3z"/><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/><path d="M19 12c0 2.76-1.61 5.13-3.93 6.28V20.4C18.19 19.14 21 15.88 21 12s-2.81-7.14-5.93-8.4v2.12A7.998 7.998 0 0119 12z"/></svg>
              </button>
              <input type="range" id="ctrl-volume" min="0" max="1" step="0.05" value="0">
            </div>
            <div class="ctrl-spacer"></div>
            <button class="ctrl-btn" id="ctrl-fullscreen" title="Fullscreen">
              <svg viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
            </button>
          </div>

          <!-- Spinner -->
          <div id="spin-overlay" class="overlay">
            <div class="spin-ring"></div>
            <div id="spin-label">Connecting to stream…</div>
            <div id="spin-source"></div>
          </div>

          <!-- Error -->
          <div id="err-overlay" class="overlay">
            <div id="err-icon">📡</div>
            <div id="err-msg">Stream unavailable</div>
            <div id="err-sub">Try a different server or check back later</div>
            <div id="auto-switch"></div>
            <div class="err-actions">
              <button class="btn btn-primary" onclick="TCTV.retry()">↺ Retry</button>
              <button class="btn btn-ghost" onclick="TCTV.nextServer()">Next Server →</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Promo buttons -->
      <div id="promo-area">
        <a id="promo-telegram" class="promo-btn" href="https://t.me/getlatestmovienew" target="_blank" rel="noopener">
          <div class="promo-icon">✈️</div>
          <div class="promo-text">
            <div class="promo-title">Join our Telegram Channel</div>
            <div class="promo-sub">For a buffer-free streaming experience</div>
          </div>
          <span class="promo-arrow">›</span>
        </a>
        <a id="promo-app" class="promo-btn" href="https://techandclick.site/iptv/download.html" target="_blank" rel="noopener">
          <div class="promo-icon">📲</div>
          <div class="promo-text">
            <div class="promo-title">Download our Mobile App</div>
            <div class="promo-sub">Watch live streams on the go</div>
          </div>
          <span class="promo-arrow">›</span>
        </a>
        <a id="promo-tickets" class="promo-btn" href="https://omg10.com/4/11017767" target="_blank" rel="noopener">
          <div class="promo-icon">🎟️</div>
          <div class="promo-text">
            <div class="promo-title">Win VIP World Cup Tickets!</div>
            <div class="promo-sub">Enter the giveaway draw now</div>
          </div>
          <span class="promo-arrow">›</span>
        </a>
        <a id="promo-merch" class="promo-btn" href="https://www.effectivecpmnetwork.com/mgtqwzbp?key=5c4003e0ae2b0ebd387daded087bc9aa" target="_blank" rel="noopener">
          <div class="promo-icon">🎁</div>
          <div class="promo-text">
            <div class="promo-title">Claim Free FIFA Merchandise!</div>
            <div class="promo-sub">Get jerseys, footballs, and gear</div>
          </div>
          <span class="promo-arrow">›</span>
        </a>
      </div>

      <!-- Social share buttons -->
      <div id="share-area">
        <div id="share-label">📢 Share Live:</div>
        <div class="share-btns">
          <a id="share-fb" class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u=https://techandclick.site/iptv/" target="_blank" rel="noopener">
            <span>📘</span> Facebook
          </a>
          <a id="share-messenger" class="share-btn" href="https://www.facebook.com/dialog/send?link=https://techandclick.site/iptv/&app_id=966242223397117" target="_blank" rel="noopener">
            <span>💬</span> Messenger
          </a>
          <a id="share-wa" class="share-btn" href="https://api.whatsapp.com/send?text=Watch%20Live%20Stream%20here:%20https://techandclick.site/iptv/" target="_blank" rel="noopener">
            <span>💬</span> WhatsApp
          </a>
        </div>
      </div>

      <!-- Server selector -->
      <div id="server-area">
        <div id="server-label-row">
          <div id="server-label">Select Server</div>
          <div id="cache-age"></div>
        </div>
        <div id="server-scroll"></div>
      </div>

      <!-- Manual URL input -->
      <div id="custom-panel">
        <button id="custom-toggle" onclick="TCTV.toggleCustom(this)">
          <span>⚡ Play Custom Stream URL</span>
          <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
          </svg>
        </button>
        <div id="custom-body">
          <input id="custom-url" type="url" placeholder="https://example.com/stream.m3u8 or paste any stream URL">
          <button id="custom-play" onclick="TCTV.playCustomUrl()">▶ Play Stream</button>
        </div>
      </div>

    </div><!-- /tab-home -->

    <!-- Tab: Favorites -->
    <div id="tab-fav" class="tab-panel">
      <div class="empty-state">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
        </svg>
        <h3>No Favorites Yet</h3>
        <p>Tap the ♥ on any stream to save it here</p>
      </div>
    </div>

    <!-- Tab: Profile -->
    <div id="tab-profile" class="tab-panel">
      <div class="empty-state">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
        </svg>
        <h3>Tech &amp; Click TV</h3>
        <p>Version 2.0 · Free Live Sports Streaming</p>
      </div>
    </div>

  </div><!-- /main -->

  <!-- ── Bottom Nav ──────────────────────────────────────────────── -->
  <nav id="bottom-nav">
    <div class="nav-inner">

      <button class="nav-btn active" id="nav-home" onclick="TCTV.switchTab('home',this)">
        <div class="nav-icon-wrap">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z"/>
            <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.43z"/>
          </svg>
        </div>
        Home
      </button>

      <button class="nav-btn" id="nav-fav" onclick="TCTV.switchTab('fav',this)">
        <div class="nav-icon-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
          </svg>
        </div>
        Favorites
      </button>

      <button class="nav-btn" id="nav-profile" onclick="TCTV.switchTab('profile',this)">
        <div class="nav-icon-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
          </svg>
        </div>
        Profile
      </button>

    </div>
  </nav>
</div><!-- /app -->

<script>
(function(){
'use strict';

// ══════════════════════════════════════════════════════════════════════════════
//  TCTV — Tech & Click TV Player Core
// ══════════════════════════════════════════════════════════════════════════════

const $ = id => document.getElementById(id);

const TCTV = window.TCTV = {
  // ── State ───────────────────────────────────────────────────────────────────
  hls: null,
  servers: [],
  currentIdx: 0,
  failedServers: new Set(),
  autoSwitchTimer: null,
  autoSwitchCountdown: null,
  refreshTimer: null,
  controlsTimeout: null,
  customChannelsFromPHP: <?= $customChannelsJson ?>,

  // ── DOM refs ────────────────────────────────────────────────────────────────
  player: $('player'),
  videoWrap: $('video-wrap'),
  spinOverlay: $('spin-overlay'),
  spinLabel: $('spin-label'),
  spinSource: $('spin-source'),
  errOverlay: $('err-overlay'),
  errMsg: $('err-msg'),
  errSub: $('err-sub'),
  autoSwitch: $('auto-switch'),
  serverScroll: $('server-scroll'),
  playerServers: $('player-servers'),
  cacheAge:     $('cache-age'),
  srcBadge: $('src-badge'),
  warnBar: $('warn-bar'),
  warnText: $('warn-text'),
  matchCard: $('match-card'),
  matchTitle: $('match-title'),
  matchSub: $('match-sub'),
  matchTime: $('match-time'),
  customToggle: $('custom-toggle'),
  customBody: $('custom-body'),
  customUrl: $('custom-url'),
  // Custom controls
  ctrlPlay: $('ctrl-play'),
  ctrlMute: $('ctrl-mute'),
  ctrlVolume: $('ctrl-volume'),
  ctrlFullscreen: $('ctrl-fullscreen'),

  // ── SVG icons for play/pause and mute ───────────────────────────────────────
  ICON_PLAY:  '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>',
  ICON_PAUSE: '<svg viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>',
  ICON_VOL:   '<svg viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3z"/><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/><path d="M19 12c0 2.76-1.61 5.13-3.93 6.28V20.4C18.19 19.14 21 15.88 21 12s-2.81-7.14-5.93-8.4v2.12A7.998 7.998 0 0119 12z"/></svg>',
  ICON_MUTE:  '<svg viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>',

  // ── UI helpers ──────────────────────────────────────────────────────────────
  showSpinner(label='Loading stream…',src=''){
    this.spinOverlay.classList.remove('gone');
    this.errOverlay.classList.remove('visible');
    this.spinLabel.textContent=label;
    this.spinSource.textContent=src;
  },
  hideSpinner(){this.spinOverlay.classList.add('gone')},
  showError(msg,sub='',autoNext=false){
    this.hideSpinner();
    this.errMsg.textContent=msg;
    this.errSub.textContent=sub;
    this.errOverlay.classList.add('visible');
    if(autoNext) this.scheduleAutoSwitch();
  },
  hideError(){this.errOverlay.classList.remove('visible');this.cancelAutoSwitch()},
  setBadge(text){this.srcBadge.textContent=text;this.srcBadge.classList.add('visible')},
  hideBadge(){this.srcBadge.classList.remove('visible')},
  setWarning(text){this.warnText.textContent=text;this.warnBar.classList.add('visible')},
  hideWarning(){this.warnBar.classList.remove('visible')},

  // ── Custom controls logic ──────────────────────────────────────────────────
  initControls(){
    const v = this.player;

    // Show controls on interaction, auto-hide after 3s
    const showControls = () => {
      this.videoWrap.classList.add('controls-locked');
      clearTimeout(this.controlsTimeout);
      this.controlsTimeout = setTimeout(() => {
        this.videoWrap.classList.remove('controls-locked');
      }, 3000);
    };

    this.videoWrap.addEventListener('mousemove', showControls);
    this.videoWrap.addEventListener('touchstart', showControls, {passive:true});
    // Show controls initially for 4s
    this.videoWrap.classList.add('controls-locked');
    this.controlsTimeout = setTimeout(() => {
      this.videoWrap.classList.remove('controls-locked');
    }, 4000);

    // Play / Pause
    this.ctrlPlay.onclick = () => {
      if(v.paused) v.play().catch(()=>{});
      else v.pause();
    };
    v.addEventListener('play',  () => { this.ctrlPlay.innerHTML = this.ICON_PAUSE; });
    v.addEventListener('pause', () => { this.ctrlPlay.innerHTML = this.ICON_PLAY;  });

    // Click video to toggle play/pause
    v.addEventListener('click', (e) => {
      e.preventDefault();
      if(v.paused) v.play().catch(()=>{});
      else v.pause();
      showControls();
    });

    // Mute / Unmute
    this.ctrlMute.onclick = () => {
      v.muted = !v.muted;
      if(!v.muted && v.volume === 0) v.volume = 0.5;
      this.ctrlVolume.value = v.muted ? 0 : v.volume;
      this.ctrlMute.innerHTML = v.muted ? this.ICON_MUTE : this.ICON_VOL;
    };

    // Volume slider
    this.ctrlVolume.oninput = () => {
      const val = parseFloat(this.ctrlVolume.value);
      v.volume = val;
      v.muted = val === 0;
      this.ctrlMute.innerHTML = val === 0 ? this.ICON_MUTE : this.ICON_VOL;
    };

    // Sync volume UI when video changes
    v.addEventListener('volumechange', () => {
      this.ctrlVolume.value = v.muted ? 0 : v.volume;
      this.ctrlMute.innerHTML = (v.muted || v.volume === 0) ? this.ICON_MUTE : this.ICON_VOL;
    });

    // Fullscreen
    this.ctrlFullscreen.onclick = () => {
      const wrap = this.videoWrap;
      if(document.fullscreenElement){
        document.exitFullscreen().catch(()=>{});
      } else {
        (wrap.requestFullscreen || wrap.webkitRequestFullscreen || wrap.msRequestFullscreen).call(wrap);
      }
    };

    // Initial muted state
    this.ctrlMute.innerHTML = this.ICON_MUTE;
    this.ctrlVolume.value = 0;
  },

  // ── Auto-switch countdown ───────────────────────────────────────────────────
  scheduleAutoSwitch(){
    this.cancelAutoSwitch();
    let sec=5;
    this.autoSwitch.textContent=`Switching to next server in ${sec}s...`;
    this.autoSwitchCountdown=setInterval(()=>{
      sec--;
      if(sec<=0){this.cancelAutoSwitch();this.nextServer();return}
      this.autoSwitch.textContent=`Switching to next server in ${sec}s...`;
    },1000);
    this.autoSwitchTimer=setTimeout(()=>this.nextServer(),5000);
  },
  cancelAutoSwitch(){
    if(this.autoSwitchTimer){clearTimeout(this.autoSwitchTimer);this.autoSwitchTimer=null}
    if(this.autoSwitchCountdown){clearInterval(this.autoSwitchCountdown);this.autoSwitchCountdown=null}
    this.autoSwitch.textContent='';
  },

  // ── Server cycling ──────────────────────────────────────────────────────────
  nextServer(){
    let tried=0;
    while(tried<this.servers.length){
      this.currentIdx=(this.currentIdx+1)%this.servers.length;
      if(!this.failedServers.has(this.currentIdx)){
        this.loadServer(this.currentIdx);
        return;
      }
      tried++;
    }
    this.showError('All servers exhausted','Please retry or use a different source');
  },

  // ── HLS lifecycle ───────────────────────────────────────────────────────────
  destroyHls(){if(this.hls){this.hls.destroy();this.hls=null}},

  // ── Build an HLS instance for a given URL ──────────────────────────────────
  // onFatal() is called when all recovery attempts are exhausted.
  _makeHls(streamUrl, onFatal){
    let netRetries = 0, mediaRetries = 0;
    const MAX_NET   = 0;   // no retries — switch servers immediately on network error
    const MAX_MEDIA = 2;

    console.log('[HLS] Loading stream:', streamUrl);

    const hls = new Hls({
      enableWorker:true,
      lowLatencyMode:true,
      maxMaxBufferLength:60,
      fragLoadingMaxRetry:4,
      manifestLoadingMaxRetry:3,
      levelLoadingMaxRetry:3,
      fragLoadingRetryDelay:1500,
      manifestLoadingRetryDelay:1500,
      levelLoadingRetryDelay:1500,
      xhrSetup: function(xhr, url) {
        // Add detailed logging for network requests
        console.log('[HLS] XHR request:', url);
      },
      // All URLs route through Cloudflare Worker proxy.
    });
    
    hls.on(Hls.Events.MANIFEST_LOADING, () => {
      console.log('[HLS] Manifest loading started');
    });
    
    hls.on(Hls.Events.MANIFEST_LOADED, (event, data) => {
      console.log('[HLS] Manifest loaded successfully:', data);
    });
    
    hls.on(Hls.Events.FRAG_LOADING, (event, data) => {
      console.log('[HLS] Fragment loading:', data.frag.url);
    });
    
    hls.on(Hls.Events.FRAG_LOADED, (event, data) => {
      console.log('[HLS] Fragment loaded:', data.frag.url);
    });

    hls.loadSource(streamUrl);
    hls.attachMedia(this.player);

    hls.on(Hls.Events.ERROR,(ev,data)=>{
      console.error('[HLS ERROR]', {
        type: data.type,
        details: data.details,
        fatal: data.fatal,
        url: streamUrl,
        networkDetails: data.response,
        error: data.error
      });
      
      if(!data.fatal) return;
      
      if(data.type===Hls.ErrorTypes.NETWORK_ERROR){
        console.error('[HLS NETWORK ERROR] Status:', data.response?.code, 'URL:', data.response?.url);
        if(netRetries < MAX_NET){ netRetries++; hls.startLoad(); }
        else onFatal(data);
      } else if(data.type===Hls.ErrorTypes.MEDIA_ERROR){
        console.error('[HLS MEDIA ERROR] Details:', data.details);
        if(mediaRetries < MAX_MEDIA){ mediaRetries++; hls.recoverMediaError(); }
        else onFatal(data);
      } else {
        console.error('[HLS OTHER ERROR] Type:', data.type);
        onFatal(data);
      }
    });
    return hls;
  },

  attemptPlay() {
    // Force muted autoplay on page load to bypass browser restrictions
    this.player.muted = true;
    this.ctrlVolume.value = 0;
    this.ctrlMute.innerHTML = this.ICON_MUTE;
    
    const playPromise = this.player.play();
    if (playPromise !== undefined) {
      playPromise.catch(err => {
        console.warn('[autoplay blocked]', err);
        // If still blocked, try again after a tiny delay
        setTimeout(() => {
          this.player.play().catch(() => {});
        }, 100);
      });
    }
  },

  async loadServer(idx){
    if(!this.servers[idx])return;
    this.currentIdx=idx;
    this.markActive(idx);
    this.hideError();
    this.cancelAutoSwitch();
    this.hideWarning();

    const srv=this.servers[idx];
    console.log('[loadServer] Starting load for:', {
      index: idx,
      name: srv.name,
      proxy_url: srv.proxy_url,
      raw_url: srv.raw_url
    });

    this.showSpinner('Connecting to '+srv.name,srv.group||'');
    this.destroyHls();

    // Pre-flight check: test if the proxy URL is reachable
    try {
      console.log('[loadServer] Pre-flight check: fetching proxy URL');
      const testResponse = await fetch(srv.proxy_url, {
        method: 'HEAD',
        cache: 'no-cache'
      });
      console.log('[loadServer] Pre-flight response:', {
        status: testResponse.status,
        statusText: testResponse.statusText,
        headers: Object.fromEntries(testResponse.headers.entries())
      });
      
      if (!testResponse.ok) {
        console.error('[loadServer] Pre-flight FAILED:', testResponse.status);
        throw new Error(`HTTP ${testResponse.status}: ${testResponse.statusText}`);
      }
    } catch (prefErr) {
      console.error('[loadServer] Pre-flight check failed:', prefErr);
      this.destroyHls();
      this.failedServers.add(idx);
      this.markFailed(idx);
      this.showError(srv.name+' unreachable', `Connection failed: ${prefErr.message}`, true);
      return;
    }

    if(Hls.isSupported()){
      const onManifest = () => {
        console.log('[loadServer] Manifest parsed successfully!');
        this.hideSpinner();
        this.attemptPlay();
        this.setBadge(srv.name);
      };

      const onFatal = (data) => {
        console.error('[loadServer] Fatal error callback triggered:', data);
        this.destroyHls();
        this.failedServers.add(idx);
        this.markFailed(idx);
        this.showError(srv.name+' unavailable',`Error: ${data.details || 'Unknown error'}`,true);
      };

      // All requests route through Cloudflare Worker proxy.
      this.hls = this._makeHls(srv.proxy_url, onFatal);
      this.hls.on(Hls.Events.MANIFEST_PARSED, onManifest);

    }else if(this.player.canPlayType('application/vnd.apple.mpegurl')){
      // Native HLS (iOS Safari)
      console.log('[loadServer] Using native HLS playback');
      this.player.src = srv.proxy_url;
      this.player.addEventListener('loadedmetadata',()=>{
        console.log('[loadServer] Native HLS metadata loaded');
        this.hideSpinner();
        this.attemptPlay();
        this.setBadge(srv.name);
      },{once:true});
      this.player.addEventListener('error',(e)=>{
        console.error('[loadServer] Native HLS error:', e);
        this.failedServers.add(idx); this.markFailed(idx);
        this.showError(srv.name+' failed','Native playback error',true);
      },{once:true});
    }else{
      console.error('[loadServer] HLS not supported in this browser');
      this.showError('Your browser does not support HLS','Try Chrome, Safari, or Edge');
    }
  },

  // ── In-player server button UI ─────────────────────────────────────────────
  buildPills(){
    // Build in-player overlay buttons
    this.playerServers.innerHTML='';
    this.servers.forEach((srv,i)=>{
      const btn=document.createElement('button');
      btn.className='psrv-btn'+(i===0?' active':'');
      btn.dataset.idx=i;
      btn.innerHTML=`<div class="psrv-dot"></div>${srv.name}`;
      btn.onclick=()=>this.loadServer(i);
      this.playerServers.appendChild(btn);
    });
  },
  markActive(idx){
    document.querySelectorAll('.psrv-btn').forEach((el,i)=>{
      el.classList.toggle('active',i===idx);
    });
  },
  markFailed(idx){
    // Hide failed server buttons from overlay (clean UI)
    const btns=document.querySelectorAll('.psrv-btn');
    if(btns[idx]) btns[idx].style.display='none';
  },

  // ── Match info card ─────────────────────────────────────────────────────────
  showMatchCard(title,sub,time){
    this.matchCard.classList.remove('hidden');
    this.matchTitle.textContent=title;
    this.matchSub.textContent=sub;
    this.matchTime.textContent=time;
  },
  hideMatchCard(){this.matchCard.classList.add('hidden')},

  // ── Manual URL input ────────────────────────────────────────────────────────
  toggleCustom(btn){
    const open=this.customBody.classList.toggle('open');
    btn.classList.toggle('open',open);
  },
  playCustomUrl(){
    const url=this.customUrl.value.trim();
    if(!url){alert('Please enter a stream URL');return}
    if(!/^https?:\/\//i.test(url)){alert('URL must start with http:// or https://');return}
    this.servers=[{
      name:'Custom Stream',
      group:'Manual',
      logo:'',
      raw_url:url,
      proxy_url:'proxy.php?url='+encodeURIComponent(url)+'&raw=true',
    }];
    this.currentIdx=0;
    this.failedServers.clear();
    this.buildPills();
    this.loadServer(0);
  },

  // ── Tab switching ───────────────────────────────────────────────────────────
  switchTab(name,btn){
    document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(b=>b.classList.remove('active'));
    $('tab-'+name).classList.add('active');
    btn.classList.add('active');
  },

  // ── Retry ───────────────────────────────────────────────────────────────────
  retry(){
    this.failedServers.delete(this.currentIdx);
    // Re-show the button we may have hidden
    const btns=document.querySelectorAll('.psrv-btn');
    if(btns[this.currentIdx]) btns[this.currentIdx].style.display='';
    this.loadServer(this.currentIdx);
  },

  // ── Background token refresh ───────────────────────────────────────────────
  // Silently re-fetch stream.php every 55s to get fresh CDN tokens.
  // Updates proxy_url on existing server objects so the next segment fetch
  // picks up the new token without interrupting current playback.
  startAutoRefresh(){
    if(this.refreshTimer) clearInterval(this.refreshTimer);
    this.refreshTimer = setInterval(async () => {
      try{
        const res = await fetch('stream.php');
        if(!res.ok) return;
        const data = await res.json();
        const freshServers = (data.servers || []);
        if(freshServers.length === 0) return;

        // Update proxy_url for matching servers by name
        freshServers.forEach(fresh => {
          const existing = this.servers.find(s => s.name === fresh.name);
          if(existing){
            existing.proxy_url = fresh.proxy_url;
            existing.raw_url   = fresh.raw_url;
          }
        });

        // Add any new servers that weren't in our list
        freshServers.forEach(fresh => {
          if(!this.servers.find(s => s.name === fresh.name)){
            this.servers.push(fresh);
            // Add button to overlay
            const btn = document.createElement('button');
            btn.className = 'psrv-btn';
            btn.dataset.idx = this.servers.length - 1;
            btn.innerHTML = `<div class="psrv-dot"></div>${fresh.name}`;
            btn.onclick = () => this.loadServer(this.servers.length - 1);
            this.playerServers.appendChild(btn);
          }
        });

        console.log('[auto-refresh] Token refresh OK, servers:', freshServers.length);
      }catch(e){
        console.warn('[auto-refresh] Failed:', e.message);
      }
    }, 55000); // every 55 seconds (cache TTL is 60s)
  },

  // ── Viewer count simulator ──────────────────────────────────────────────────
  startViewerCounter(){
    const viewerEl = document.getElementById('viewer-count');
    if(!viewerEl) return;

    // Base count: 2.5K (2500)
    let baseCount = 2500;
    
    const updateCount = () => {
      // Random fluctuation: ±200 viewers
      const variation = Math.floor(Math.random() * 400) - 200;
      const newCount = baseCount + variation;
      
      // Format: 2.5K, 2.6K, etc.
      const formatted = (newCount / 1000).toFixed(1) + 'K';
      viewerEl.textContent = '👁️ ' + formatted;
      
      // Schedule next update (5-10 seconds)
      const nextUpdate = 5000 + Math.random() * 5000;
      setTimeout(updateCount, nextUpdate);
    };
    
    // Start after 3 seconds
    setTimeout(updateCount, 3000);
  },

  // ── Welcome popup with smart popunder ──────────────────────────────────────
  welcomeAdClicked: false,  // Track if ad has been shown
  adsterraUrls: [
    'https://omg10.com/4/11017767',  // Win VIP Tickets
    'https://www.effectivecpmnetwork.com/mgtqwzbp?key=5c4003e0ae2b0ebd387daded087bc9aa'  // Free Merchandise
  ],

  showWelcome(){
    // Check if already shown this session
    if(sessionStorage.getItem('welcomeShown') === 'true') return;
    
    const overlay = document.getElementById('welcome-overlay');
    if(!overlay) return;
    
    // Show popup after a short delay for smoother UX
    setTimeout(() => {
      overlay.classList.add('show');
    }, 800);
  },

  closeWelcome(){
    const overlay = document.getElementById('welcome-overlay');
    if(!overlay) return;
    
    // STRICT WEBVIEW DETECTION: Ad-free experience for Android app users
    const isWebView = /(wv|WebView|Android.*AppleWebKit.*Version\/[\d\.]+|FBAN|FBAV|Instagram)/i.test(navigator.userAgent);
    
    if(isWebView){
      // ANDROID APP DETECTED: Close immediately, no ads, no redirects
      console.log('[Welcome Popup] Android WebView detected - Ad-free experience enabled');
      overlay.classList.remove('show');
      sessionStorage.setItem('welcomeShown', 'true');
      
      // Remove from DOM after animation
      setTimeout(() => {
        overlay.style.display = 'none';
      }, 400);
      return;  // Exit immediately - no ad logic
    }
    
    // Detect mobile browser (NOT WebView, just mobile)
    const isMobile = /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    
    // REGULAR BROWSER USERS: Smart popunder logic (two-click ad system)
    if(!this.welcomeAdClicked){
      const randomUrl = this.adsterraUrls[Math.floor(Math.random() * this.adsterraUrls.length)];
      const welcomeButton = document.getElementById('welcome-button');
      
      // MOBILE BROWSER: Show countdown notice before opening ad
      if(isMobile && welcomeButton){
        // Disable button during countdown
        welcomeButton.disabled = true;
        welcomeButton.style.opacity = '0.7';
        welcomeButton.style.cursor = 'not-allowed';
        
        console.log('[Welcome Popup] Mobile browser detected - Starting 3-second countdown');
        
        // Original button text
        const originalText = welcomeButton.textContent;
        
        // Countdown from 3 to 1
        let countdown = 3;
        welcomeButton.textContent = `Sponsor ad opening... Return to previous tab for stream (${countdown}s)`;
        
        const countdownInterval = setInterval(() => {
          countdown--;
          if(countdown > 0){
            welcomeButton.textContent = `Sponsor ad opening... Return to previous tab for stream (${countdown}s)`;
          } else {
            clearInterval(countdownInterval);
            
            // Open ad after countdown
            let adWindow = window.open(randomUrl, '_blank', 'noopener,noreferrer');
            
            // Check if window.open was blocked
            if(!adWindow || adWindow.closed || typeof adWindow.closed === 'undefined'){
              // POPUP BLOCKER: Close normally
              console.log('[Welcome Popup] Popup blocker detected, closing normally');
              overlay.classList.remove('show');
              sessionStorage.setItem('welcomeShown', 'true');
              setTimeout(() => {
                overlay.style.display = 'none';
              }, 400);
              return;
            }
            
            // SUCCESS: Ad opened, restore button
            this.welcomeAdClicked = true;
            welcomeButton.disabled = false;
            welcomeButton.style.opacity = '1';
            welcomeButton.style.cursor = 'pointer';
            welcomeButton.textContent = originalText;
            console.log('[Welcome Popup] Ad opened, click again to close');
          }
        }, 1000); // 1 second interval
        
        return;  // Exit - countdown handles the rest
      }
      
      // DESKTOP BROWSER: Instant ad (no countdown needed)
      console.log('[Welcome Popup] Desktop browser - Opening ad instantly');
      let adWindow = window.open(randomUrl, '_blank', 'noopener,noreferrer');
      
      // Check if window.open was blocked
      if(!adWindow || adWindow.closed || typeof adWindow.closed === 'undefined'){
        // POPUP BLOCKER: Close normally
        console.log('[Welcome Popup] Popup blocker detected, closing normally');
        overlay.classList.remove('show');
        sessionStorage.setItem('welcomeShown', 'true');
        setTimeout(() => {
          overlay.style.display = 'none';
        }, 400);
        return;
      }
      
      // SUCCESS: Ad opened, wait for second click
      this.welcomeAdClicked = true;
      console.log('[Welcome Popup] Ad opened in new tab, click again to close');
      return;  // Do NOT close the popup yet
    }
    
    // SECOND ATTEMPT: Actually close the popup (all users)
    overlay.classList.remove('show');
    
    // Mark as shown for this session
    sessionStorage.setItem('welcomeShown', 'true');
    
    // Remove from DOM after animation
    setTimeout(() => {
      overlay.style.display = 'none';
    }, 400);
  },

  initWelcomeListeners(){
    const overlay = document.getElementById('welcome-overlay');
    if(!overlay) return;
    
    // Close when clicking outside the modal (with smart popunder)
    overlay.addEventListener('click', (e) => {
      if(e.target === overlay){
        this.closeWelcome();
      }
    });
    
    // Close with Escape key (with smart popunder)
    document.addEventListener('keydown', (e) => {
      if(e.key === 'Escape' && overlay.classList.contains('show')){
        this.closeWelcome();
      }
    });
  },

  // ── Real-time viewer tracker ────────────────────────────────────────────────
  startViewerPing(){
    // Send ping immediately
    this.sendPing();
    
    // Then every 30 seconds
    setInterval(() => {
      this.sendPing();
    }, 30000);
  },

  sendPing(){
    fetch('ping.php', {
      method: 'GET',
      cache: 'no-cache'
    }).catch(() => {
      // Silent fail — don't disturb user experience
    });
  },

  // ── Smart native share button routing ──────────────────────────────────────
  initSmartShareButtons(){
    // Detect mobile device
    const isMobile = /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    
    const shareUrl = 'https://techandclick.site/iptv/';
    const shareText = 'Watch Live Stream here: ' + shareUrl;
    
    // Facebook share button
    const fbBtn = document.getElementById('share-fb');
    if(fbBtn){
      fbBtn.addEventListener('click', (e) => {
        e.preventDefault();
        let url;
        
        if(isMobile){
          // Try native Facebook app first
          url = 'fb://share/?href=' + encodeURIComponent(shareUrl);
          window.location.href = url;
          
          // Fallback to web sharer after 1 second if app didn't open
          setTimeout(() => {
            window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl), '_blank');
          }, 1000);
        } else {
          // Desktop: use web sharer
          window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl), '_blank');
        }
      });
    }
    
    // Messenger share button
    const messengerBtn = document.getElementById('share-messenger');
    if(messengerBtn){
      messengerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        let url;
        
        if(isMobile){
          // Mobile: try native Messenger app
          url = 'fb-messenger://share/?link=' + encodeURIComponent(shareUrl);
          window.location.href = url;
          
          // Fallback to web version after 1 second
          setTimeout(() => {
            window.open('https://www.facebook.com/dialog/send?link=' + encodeURIComponent(shareUrl) + '&app_id=966242223397117&redirect_uri=' + encodeURIComponent(shareUrl), '_blank');
          }, 1000);
        } else {
          // Desktop: use web version
          window.open('https://www.facebook.com/dialog/send?link=' + encodeURIComponent(shareUrl) + '&app_id=966242223397117&redirect_uri=' + encodeURIComponent(shareUrl), '_blank');
        }
      });
    }
    
    // WhatsApp share button
    const waBtn = document.getElementById('share-wa');
    if(waBtn){
      waBtn.addEventListener('click', (e) => {
        e.preventDefault();
        let url;
        
        if(isMobile){
          // Mobile: use native WhatsApp app protocol
          url = 'whatsapp://send?text=' + encodeURIComponent(shareText);
        } else {
          // Desktop: use WhatsApp Web
          url = 'https://web.whatsapp.com/send?text=' + encodeURIComponent(shareText);
        }
        
        window.open(url, '_blank');
      });
    }
    
    console.log('[Share Buttons] Initialized with native app routing for', isMobile ? 'mobile' : 'desktop');
  },

  // ── Init ────────────────────────────────────────────────────────────────────
  async init(){
    console.log('[TCTV] Initializing player...');
    
    this.initControls();
    this.startViewerCounter();  // Start viewer count simulation
    this.initWelcomeListeners(); // Setup welcome popup event listeners
    this.showWelcome();          // Show welcome popup (once per session)
    this.startViewerPing();      // Start real-time viewer tracking
    this.initSmartShareButtons(); // Setup native mobile app share routing

    // WebView Detection logic — hide mobile app download button if already inside a WebView
    const ua = navigator.userAgent || navigator.vendor || window.opera;
    const isWebView = /wv|WebView|FBAN|FBAV|Instagram/i.test(ua) || 
                      (/Android/i.test(ua) && /Version\/[0-9.]+/i.test(ua) && !/Chrome\/\d+/i.test(ua)) ||
                      (/iPhone|iPad|iPod/i.test(ua) && !/Safari/i.test(ua));
    if(isWebView){
      const appBtn = $('promo-app');
      if(appBtn) appBtn.style.display = 'none';
    }

    this.showSpinner('Fetching stream list…','');

    // Hard 8-second timeout — never spin forever waiting for stream.php
    const controller = new AbortController();
    const fetchTimeout = setTimeout(() => {
      console.error('[TCTV] stream.php fetch timeout after 8 seconds');
      controller.abort();
    }, 8000);

    try{
      console.log('[TCTV] Fetching stream.php...');
      const res = await fetch('stream.php', { signal: controller.signal });
      clearTimeout(fetchTimeout);

      console.log('[TCTV] stream.php response:', {
        ok: res.ok,
        status: res.status,
        statusText: res.statusText,
        contentType: res.headers.get('content-type')
      });

      if(!res.ok) {
        console.error('[TCTV] stream.php returned error status:', res.status);
        throw new Error('stream.php HTTP ' + res.status);
      }
      
      const data = await res.json();
      console.log('[TCTV] stream.php data:', data);

      // Only show dynamically scraped live servers (no fallback VOD)
      const liveServers   = (data.servers || []);
      const customServers = this.customChannelsFromPHP || [];

      console.log('[TCTV] Parsed servers:', {
        liveCount: liveServers.length,
        customCount: customServers.length
      });

      // Order: live scraped → custom DB channels
      this.servers = [...liveServers, ...customServers];

      if(this.servers.length === 0){
        console.error('[TCTV] No servers available in response');
        throw new Error('No streams available.');
      }

      // Show match info card if FIFA streams are present
      const hasFifa = liveServers.some(s => (s.name||'').toLowerCase().includes('fifa') ||
                                             (s.name||'').toLowerCase().includes('server'));
      if(hasFifa){
        this.showMatchCard('FIFA World Cup 2026', 'Live • Sports', 'June 11 – July 19 2026');
      }

      this.buildPills();
      
      // Safety check: only load if we have valid servers
      if(this.servers.length > 0 && this.servers[0] && this.servers[0].proxy_url){
        console.log('[TCTV] Loading first server:', this.servers[0]);
        await this.loadServer(0);  // always the first LIVE server
      } else {
        console.error('[TCTV] No valid servers available');
        throw new Error('No valid servers available.');
      }

      if(data.warning) {
        console.warn('[TCTV] Warning from stream.php:', data.warning);
        this.setWarning(data.warning);
      }

      // Start background token refresh
      this.startAutoRefresh();

    } catch(err){
      clearTimeout(fetchTimeout);
      console.error('[TCTV] Init error:', err);

      // Only use DB channels as emergency fallback — and only if we have them
      if(this.customChannelsFromPHP && this.customChannelsFromPHP.length > 0){
        console.log('[TCTV] Using fallback DB channels:', this.customChannelsFromPHP.length);
        this.servers = [...this.customChannelsFromPHP];
        this.buildPills();
        
        // Safety check before loading
        if(this.servers.length > 0 && this.servers[0] && this.servers[0].proxy_url){
          await this.loadServer(0);
        }
        this.setWarning('⚠ Live source unavailable. Showing saved channels.');
      } else {
        const reason = err.name === 'AbortError'
          ? 'Stream list timed out. Check back shortly.'
          : 'Could not load streams: ' + err.message;
        console.error('[TCTV] Fatal error:', reason);
        this.showError('Stream source offline', reason);
      }
    }
  }
};

// ── Page cleanup on hide (mobile background tab) ──────────────────────────────
document.addEventListener('visibilitychange',()=>{
  if(document.hidden){
    TCTV.destroyHls();
    TCTV.cancelAutoSwitch();
    if(TCTV.refreshTimer){ clearInterval(TCTV.refreshTimer); TCTV.refreshTimer=null; }
  }else if(TCTV.servers.length>0){
    TCTV.loadServer(TCTV.currentIdx);
    TCTV.startAutoRefresh();
  }
});

// ══════════════════════════════════════════════════════════════════════════════
//  GO
// ══════════════════════════════════════════════════════════════════════════════
TCTV.init();

})();
</script>
</body>
</html>
