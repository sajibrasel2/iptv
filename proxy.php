<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL);
if (!$url) {
    http_response_code(400);
    echo 'Missing url parameter.';
    exit;
}

$targetUrl = filter_var($url, FILTER_VALIDATE_URL);
if (!$targetUrl || !in_array(parse_url($targetUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
    http_response_code(400);
    echo 'Invalid url parameter.';
    exit;
}

$host = parse_url($targetUrl, PHP_URL_HOST);
if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
    http_response_code(400);
    echo 'Invalid url parameter.';
    exit;
}

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (compatible; IPTV Proxy/1.0)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HEADER => false,
    CURLOPT_FAILONERROR => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);

$content = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 200;
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html; charset=utf-8';
$curlError = curl_error($ch);
curl_close($ch);

if ($content === false) {
    http_response_code(502);
    echo 'Unable to fetch the remote resource.';
    exit;
}

if (stripos($contentType, 'text/html') !== false) {
    $content = preg_replace('/<(?:div|section|aside|article|p|span|button|a)[^>]*(?:id|class)=["\'][^"\']*(?:socialWidget|social-box|social-btn|close-social|sticky-header-notice|sticky-notice|marquee-wrapper|marquee-text|fbLockerBtn|locker-fb-btn|lockerCountdown|countdown-text|countNumber)[^"\']*["\'][^>]*>[\s\S]*?<\/(?:div|section|aside|article|p|span|button|a)>/iu', '', $content);
    $content = preg_replace('/<(?:div|section|aside|article|p|span|button|a)[^>]*(?:id|class)=["\'][^"\']*(?:socialWidget|social-box|social-btn|close-social|sticky-header-notice|sticky-notice|marquee-wrapper|marquee-text|fbLockerBtn|locker-fb-btn|lockerCountdown|countdown-text|countNumber)[^"\']*["\'][^>]*\/>/iu', '', $content);

    $overlayCss = '<style id="proxy-iframe-overlay-style">
        #socialWidget, .social-box, .social-btn, .close-social, 
        #sticky-header-notice, .sticky-notice, 
        .marquee-wrapper, .marquee-text, .scrolling-marquee, .marquee-container, .marquee,
        #fbLockerBtn, .locker-fb-btn, #lockerCountdown, .countdown-text, #countNumber,
        .facebook-follow, .telegram-follow, #telegram-bar { 
            display: none !important; 
            visibility: hidden !important; 
            opacity: 0 !important; 
            height: 0 !important; 
            min-height: 0 !important; 
            overflow: hidden !important; 
            pointer-events: none !important; 
        }
        video, #player, .jwplayer, .video-js, .plyr {
            width: 100% !important;
            height: 100% !important;
        }
    </style>';

    $observerScript = '<script id="proxy-iframe-observer-script">
    (function() {
        const safeSelectors = [
            "#socialWidget", ".social-box", ".social-btn", ".close-social",
            "#sticky-header-notice", ".sticky-notice",
            ".marquee-wrapper", ".marquee-text", ".scrolling-marquee", ".marquee-container", ".marquee",
            "#fbLockerBtn", ".locker-fb-btn", "#lockerCountdown", ".countdown-text", "#countNumber",
            ".facebook-follow", ".telegram-follow", "#telegram-bar"
        ];

        const genericSelectors = [
            ".promo-text", ".overlay", ".popup", ".announcement-bar", ".top-bar",
            ".follow-container", ".social-buttons"
        ];

        function isSafeToRemove(el) {
            if (!el) return false;
            const tag = el.tagName;
            if (tag === "VIDEO" || tag === "IFRAME" || tag === "CANVAS" || tag === "BODY" || tag === "HTML") {
                return false;
            }
            if (el.id === "player" || el.id === "main-player" || el.classList.contains("jwplayer") || el.classList.contains("video-js") || el.classList.contains("plyr")) {
                return false;
            }
            if (el.querySelector("video, iframe, canvas, #player, .jwplayer, .video-js, .plyr")) {
                return false;
            }
            return true;
        }

        function cleanDOM() {
            safeSelectors.forEach(selector => {
                document.querySelectorAll(selector).forEach(el => {
                    if (isSafeToRemove(el)) {
                        el.style.setProperty("display", "none", "important");
                        try { el.remove(); } catch(e) {}
                    }
                });
            });

            genericSelectors.forEach(selector => {
                document.querySelectorAll(selector).forEach(el => {
                    if (isSafeToRemove(el)) {
                        el.style.setProperty("display", "none", "important");
                        try { el.remove(); } catch(e) {}
                    }
                });
            });

            const elementsToCheck = document.querySelectorAll("p, span, a, div");
            elementsToCheck.forEach(el => {
                if (el.tagName === "DIV" && el.children.length > 0) {
                    return;
                }
                
                const text = el.textContent || "";
                if (text.includes("Follow on Facebook") || text.includes("Follow on Telegram") || text.includes("খেলা শুরু আগে")) {
                    if (isSafeToRemove(el)) {
                        el.style.setProperty("display", "none", "important");
                        try { el.remove(); } catch(e) {}
                    }
                }
            });
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", cleanDOM);
        } else {
            cleanDOM();
        }
        window.addEventListener("load", cleanDOM);

        const observer = new MutationObserver((mutations) => {
            let shouldClean = false;
            for (let mutation of mutations) {
                if (mutation.addedNodes.length) {
                    shouldClean = true;
                    break;
                }
            }
            if (shouldClean) {
                cleanDOM();
            }
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
    })();
    </script>';

    if (stripos($content, "<head") !== false) {
        $content = preg_replace("/<head[^>]*>/i", "$0" . $overlayCss . $observerScript, $content, 1);
    } else {
        $content = $overlayCss . $observerScript . $content;
    }

    if (stripos($content, '<head') !== false && stripos($content, '<base') === false) {
        $content = preg_replace_callback(
            '/<head[^>]*>/i',
            function ($matches) use ($targetUrl) {
                return $matches[0] . '<base href="' . htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') . '">';
            },
            $content,
            1
        );
    }
}

http_response_code($statusCode);
header('Content-Type: ' . $contentType);
echo $content;
