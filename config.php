<?php
/**
 * config.php — Database credentials + encryption key
 *
 * SECURITY RULES
 * ──────────────────────────────────────────────────────────────────────────────
 * 1. This file is committed to git — DO NOT put real credentials here.
 *    The live server overrides values from a secrets file that is git-ignored.
 * 2. The secrets file lives at:  __DIR__ . '/.env.php'
 *    Copy .env.example.php → .env.php on the server and fill in real values.
 * 3. DB_ENCRYPT_KEY is used to AES-256-CBC encrypt raw_url in stream_sources.
 *    Generate a strong key once:  php -r "echo base64_encode(random_bytes(32));"
 */

// ── Defaults (local dev / fallback) ──────────────────────────────────────────
$servername    = 'localhost';
$username      = 'root';
$password      = '';
$dbname        = 'livetv_db';
$charset       = 'utf8mb4';
$DB_ENCRYPT_KEY = 'CHANGE_ME_generate_with_random_bytes_32';

// ── Load secrets file if present (production) ─────────────────────────────────
$secretsFile = __DIR__ . '/.env.php';
if (file_exists($secretsFile)) {
    require $secretsFile;
    // .env.php is expected to set:
    //   $servername, $username, $password, $dbname, $charset, $DB_ENCRYPT_KEY
}
