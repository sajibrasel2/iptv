<?php
/**
 * install_db.php — One-time database setup
 *
 * Creates all tables and seeds default data.
 * DELETE THIS FILE from the server after running it once.
 *
 * stream_sources table design
 * ─────────────────────────────────────────────────────────────────────────────
 * raw_url_enc  — AES-256-CBC encrypted URL (base64-encoded ciphertext + IV)
 *                Decrypted at runtime by stream.php using $DB_ENCRYPT_KEY.
 *                If the database is ever exposed, URLs are not immediately
 *                readable without the key (stored separately in .env.php).
 *
 * status       — 'active' | 'disabled'
 *                Audit-confirmed working sources get 'active'.
 *                Broken/rate-limited sources get 'disabled' and are skipped.
 *
 * priority     — Lower number = tried first in the player.
 *                Audit result: cinecdn=1 (HTTP 200), others disabled.
 *
 * source_type  — 'scraped'  : URL comes from fifalive.click M3U8 (dynamic)
 *                'manual'   : URL entered manually via admin panel
 */

require_once 'config.php';

// ── Encryption helpers ────────────────────────────────────────────────────────

function encryptUrl(string $url, string $keyB64): string {
    $key = base64_decode($keyB64);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($url, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    // Store as base64(iv):base64(ciphertext)
    return base64_encode($iv) . ':' . base64_encode($enc);
}

// ── Connect ───────────────────────────────────────────────────────────────────

try {
    $pdo = new PDO(
        "mysql:host={$servername};dbname={$dbname};charset={$charset}",
        $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ── 1. custom_channels ────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_channels (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        display_name VARCHAR(255) NOT NULL,
        target_url   VARCHAR(1000) NOT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── 2. ad_settings ────────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS ad_settings (
        id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ad_name VARCHAR(100) NOT NULL,
        ad_url  VARCHAR(1000) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── 3. app_settings ───────────────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key   VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── 4. stream_sources — new DB-driven source list ─────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS stream_sources (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(255)  NOT NULL,
        grp         VARCHAR(100)  NOT NULL DEFAULT 'Live',
        raw_url_enc TEXT          NOT NULL COMMENT 'AES-256-CBC encrypted URL',
        status      ENUM('active','disabled') NOT NULL DEFAULT 'active',
        priority    TINYINT UNSIGNED NOT NULL DEFAULT 10
                        COMMENT 'Lower = higher priority in player',
        source_type ENUM('scraped','manual') NOT NULL DEFAULT 'manual',
        last_seen   TIMESTAMP NULL DEFAULT NULL
                        COMMENT 'Last time this URL was confirmed reachable',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status_priority (status, priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Seed default data (only if tables are empty) ──────────────────────────

    // custom_channels
    if ($pdo->query("SELECT COUNT(*) FROM custom_channels")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO custom_channels (display_name, target_url) VALUES
            ('Tech & Click TV - Server 1', 'https://famelack.com/tv'),
            ('Tech & Click TV - Server 2', 'https://www.crichd.tv/'),
            ('Tech & Click TV - Server 3', 'https://sportzfytvlive.xyz/'),
            ('Tech & Click TV - Server 4', 'https://txreca.movielinkbd.pw/')");
    }

    // ad_settings
    if ($pdo->query("SELECT COUNT(*) FROM ad_settings")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO ad_settings (ad_name, ad_url) VALUES
            ('Adsterra Link 1',  'https://omg10.com/4/11017767'),
            ('Monetag Link 1',   'https://www.effectivecpmnetwork.com/mgtqwzbp?key=5c4003e0ae2b0ebd387daded087bc9aa'),
            ('Ad Slot 3', ''),
            ('Ad Slot 4', ''),
            ('Ad Slot 5', '')");
    }

    // stream_sources — seed with audit-confirmed results
    // ┌─────────────────────────────────────────────────────────────────────┐
    // │  Audit findings (2026-07-02, cPanel IP 148.251.35.206):            │
    // │  fx.cinecdn.workers.dev  → HTTP 200 ✓  priority 1  status=active  │
    // │  live3.nextgoal.workers  → HTTP 429    priority 2  status=disabled │
    // │  live.smtahmidx.workers  → HTTP 403    priority 3  status=disabled │
    // │  toffeelive.com          → DNS failure priority 4  status=disabled │
    // └─────────────────────────────────────────────────────────────────────┘
    if ($pdo->query("SELECT COUNT(*) FROM stream_sources")->fetchColumn() == 0) {

        // Encrypt each URL with the key from config.php / .env.php
        $sources = [
            [
                'name'     => 'FIFA Live (Server 4 — CineCDN)',
                'grp'      => 'Sports',
                'url'      => 'https://fx.cinecdn.workers.dev/',
                'status'   => 'active',
                'priority' => 1,
                'type'     => 'scraped',
            ],
            [
                'name'     => 'FIFA Live (Server 2 — NextGoal)',
                'grp'      => 'Sports',
                'url'      => 'https://live3.nextgoal.workers.dev/',
                'status'   => 'disabled',   // rate-limited 429
                'priority' => 2,
                'type'     => 'scraped',
            ],
            [
                'name'     => 'FIFA Live (Server 3 — SmtAhmidx)',
                'grp'      => 'Sports',
                'url'      => 'https://live.smtahmidx.workers.dev/',
                'status'   => 'disabled',   // hard 403
                'priority' => 3,
                'type'     => 'scraped',
            ],
            [
                'name'     => 'FIFA Live (Server 1 — ToffeeLive)',
                'grp'      => 'Sports',
                'url'      => 'https://prod-cdn01-live.toffeelive.com/',
                'status'   => 'disabled',   // DNS failure
                'priority' => 4,
                'type'     => 'scraped',
            ],
        ];

        $stmt = $pdo->prepare(
            "INSERT INTO stream_sources (name, grp, raw_url_enc, status, priority, source_type)
             VALUES (:name, :grp, :enc, :status, :priority, :type)"
        );

        foreach ($sources as $s) {
            $stmt->execute([
                ':name'     => $s['name'],
                ':grp'      => $s['grp'],
                ':enc'      => encryptUrl($s['url'], $DB_ENCRYPT_KEY),
                ':status'   => $s['status'],
                ':priority' => $s['priority'],
                ':type'     => $s['type'],
            ]);
        }
    }

    // ── Output ────────────────────────────────────────────────────────────────
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>
    <title>DB Install</title>
    <style>body{font-family:sans-serif;max-width:640px;margin:40px auto;padding:20px}</style>
    </head><body>";
    echo "<h2 style='color:#22c55e'>Database installation successful</h2>";
    echo "<p>Tables created: <code>custom_channels</code>, <code>ad_settings</code>,
         <code>app_settings</code>, <code>stream_sources</code></p>";
    echo "<p><code>stream_sources</code> seeded with audit results
         (CineCDN active, others disabled until re-verified).</p>";
    echo "<p style='color:#ef4444;font-weight:bold;padding:12px;background:#fef2f2;
         border-radius:6px'>
         ⚠ Delete <code>install_db.php</code> from your server immediately.
         </p></body></html>";

} catch (PDOException $e) {
    http_response_code(500);
    echo "<!DOCTYPE html><html><body style='font-family:sans-serif;color:#c62828'>";
    echo "<h2>Installation failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</body></html>";
}
