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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#0a0e1a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Tech &amp; Click TV — Live Sports</title>
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="manifest" href="manifest.json">
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
  z-index:100;
}
.nav-inner{display:flex;justify-content:space-around;align-items:center;padding:4px 0}
.nav-btn{
  flex:1;display:flex;flex-direction:column;align-items:center;
  gap:2px;padding:8px 4px;border:none;background:none;
  color:#475569;cursor:pointer;transition:color .2s;
  font-size:10px;font-weight:600;letter-spacing:.02em;
}
.nav-btn.active{color:#3b82f6}
.nav-btn svg{width:20px;height:20px}
.nav-btn .nav-icon-wrap{
  padding:5px;border-radius:10px;
  transition:background .2s;
}
.nav-btn.active .nav-icon-wrap{background:rgba(59,130,246,.15)}

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
          <video id="player" playsinline autoplay muted></video>

          <!-- LIVE badge -->
          <div id="player-live-badge"><div class="plb-dot"></div>LIVE</div>

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
      // All URLs route through Cloudflare Worker proxy.
    });
    hls.loadSource(streamUrl);
    hls.attachMedia(this.player);

    hls.on(Hls.Events.ERROR,(ev,data)=>{
      if(!data.fatal) return;
      console.error('[HLS fatal]',data.type,data.details,streamUrl);
      if(data.type===Hls.ErrorTypes.NETWORK_ERROR){
        if(netRetries < MAX_NET){ netRetries++; hls.startLoad(); }
        else onFatal(data);
      } else if(data.type===Hls.ErrorTypes.MEDIA_ERROR){
        if(mediaRetries < MAX_MEDIA){ mediaRetries++; hls.recoverMediaError(); }
        else onFatal(data);
      } else {
        onFatal(data);
      }
    });
    return hls;
  },

  attemptPlay() {
    this.player.play().catch(e => {
      // Modern browsers require unmuted autoplay to have a user gesture.
      // If blocked, fallback to muted autoplay.
      this.player.muted = true;
      this.player.play().catch(() => {});
    });
  },

  loadServer(idx){
    if(!this.servers[idx])return;
    this.currentIdx=idx;
    this.markActive(idx);
    this.hideError();
    this.cancelAutoSwitch();
    this.hideWarning();

    const srv=this.servers[idx];
    this.showSpinner('Connecting to '+srv.name,srv.group||'');
    this.destroyHls();

    if(Hls.isSupported()){
      const onManifest = () => {
        this.hideSpinner();
        this.attemptPlay();
        this.setBadge(srv.name);
      };

      const onFatal = () => {
        this.destroyHls();
        this.failedServers.add(idx);
        this.markFailed(idx);
        this.showError(srv.name+' unavailable','Trying next server…',true);
      };

      // All requests route through Cloudflare Worker proxy.
      this.hls = this._makeHls(srv.proxy_url, onFatal);
      this.hls.on(Hls.Events.MANIFEST_PARSED, onManifest);

    }else if(this.player.canPlayType('application/vnd.apple.mpegurl')){
      // Native HLS (iOS Safari)
      this.player.src = srv.proxy_url;
      this.player.addEventListener('loadedmetadata',()=>{
        this.hideSpinner();
        this.attemptPlay();
        this.setBadge(srv.name);
      },{once:true});
      this.player.addEventListener('error',()=>{
        this.failedServers.add(idx); this.markFailed(idx);
        this.showError(srv.name+' failed','Trying next server…',true);
      },{once:true});
    }else{
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

  // ── Init ────────────────────────────────────────────────────────────────────
  async init(){
    this.initControls();

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
    const fetchTimeout = setTimeout(() => controller.abort(), 8000);

    try{
      const res = await fetch('stream.php', { signal: controller.signal });
      clearTimeout(fetchTimeout);

      if(!res.ok) throw new Error('stream.php HTTP ' + res.status);
      const data = await res.json();

      // Only show dynamically scraped live servers (no fallback VOD)
      const liveServers   = (data.servers || []);
      const customServers = this.customChannelsFromPHP || [];

      // Order: live scraped → custom DB channels
      this.servers = [...liveServers, ...customServers];

      if(this.servers.length === 0){
        throw new Error('No streams available.');
      }

      // Show match info card if FIFA streams are present
      const hasFifa = liveServers.some(s => (s.name||'').toLowerCase().includes('fifa') ||
                                             (s.name||'').toLowerCase().includes('server'));
      if(hasFifa){
        this.showMatchCard('FIFA World Cup 2026', 'Live • Sports', 'June 11 – July 19 2026');
      }

      this.buildPills();
      this.loadServer(0);  // always the first LIVE server

      if(data.warning) this.setWarning(data.warning);

      // Start background token refresh
      this.startAutoRefresh();

    } catch(err){
      clearTimeout(fetchTimeout);
      console.error('[stream.php error]', err);

      // Only use DB channels as emergency fallback — and only if we have them
      if(this.customChannelsFromPHP && this.customChannelsFromPHP.length > 0){
        this.servers = [...this.customChannelsFromPHP];
        this.buildPills();
        this.loadServer(0);
        this.setWarning('⚠ Live source unavailable. Showing saved channels.');
      } else {
        const reason = err.name === 'AbortError'
          ? 'Stream list timed out. Check back shortly.'
          : 'Could not load streams: ' + err.message;
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
