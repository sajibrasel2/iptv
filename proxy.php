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

    $overlayCss = '<style id="proxy-iframe-overlay-style">#socialWidget, .social-box, .social-btn, .close-social, #sticky-header-notice, .sticky-notice, .marquee-wrapper, .marquee-text, #fbLockerBtn, .locker-fb-btn, #lockerCountdown, .countdown-text, #countNumber, .facebook-follow, .telegram-follow, #telegram-bar, .promo-text, .overlay, .popup, .announcement-bar, .top-bar, .follow-container, .social-buttons { display: none !important; visibility: hidden !important; opacity: 0 !important; height: 0 !important; min-height: 0 !important; overflow: hidden !important; pointer-events: none !important; }</style>';

    $observerScript = '<script id="proxy-iframe-observer-script">
    (function() {
        const selectors = [
            "#socialWidget", ".social-box", ".social-btn", ".close-social",
            "#sticky-header-notice", ".sticky-notice",
            ".marquee-wrapper", ".marquee-text", ".scrolling-marquee", ".marquee-container", ".marquee",
            "#fbLockerBtn", ".locker-fb-btn", "#lockerCountdown", ".countdown-text", "#countNumber",
            ".facebook-follow", ".telegram-follow", "#telegram-bar",
            ".promo-text", ".overlay", ".popup", ".announcement-bar", ".top-bar",
            ".follow-container", ".social-buttons"
        ];

        function cleanDOM() {
            selectors.forEach(selector => {
                document.querySelectorAll(selector).forEach(el => {
                    el.style.setProperty("display", "none", "important");
                    el.style.setProperty("visibility", "hidden", "important");
                    el.style.setProperty("opacity", "0", "important");
                    el.style.setProperty("pointer-events", "none", "important");
                    el.style.setProperty("height", "0", "important");
                    el.style.setProperty("width", "0", "important");
                    try { el.remove(); } catch(e) {}
                });
            });

            // Deep text matching for dynamic elements
            const allDivs = document.getElementsByTagName("div");
            for (let i = 0; i < allDivs.length; i++) {
                const div = allDivs[i];
                const text = div.textContent || "";
                if (text.includes("Follow on Facebook") || text.includes("Follow on Telegram") || text.includes("খেলা শুরু আগে")) {
                    div.style.setProperty("display", "none", "important");
                    div.style.setProperty("visibility", "hidden", "important");
                    div.style.setProperty("opacity", "0", "important");
                    div.style.setProperty("pointer-events", "none", "important");
                    try { div.remove(); } catch(e) {}
                }
            }

            const allLinks = document.getElementsByTagName("a");
            for (let i = 0; i < allLinks.length; i++) {
                const link = allLinks[i];
                const text = link.textContent || "";
                if (text.includes("Follow on Facebook") || text.includes("Follow on Telegram")) {
                    link.style.setProperty("display", "none", "important");
                    link.style.setProperty("visibility", "hidden", "important");
                    link.style.setProperty("opacity", "0", "important");
                    try { link.remove(); } catch(e) {}
                }
            }
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
