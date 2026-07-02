<?php
/**
 * .env.example.php — copy this to .env.php on the server and fill in real values.
 * .env.php is git-ignored and never committed.
 *
 * Generate DB_ENCRYPT_KEY once:
 *   php -r "echo base64_encode(random_bytes(32));"
 */

$servername     = 'localhost';
$username       = 'YOUR_DB_USER';
$password       = 'YOUR_DB_PASSWORD';
$dbname         = 'YOUR_DB_NAME';
$charset        = 'utf8mb4';
$DB_ENCRYPT_KEY = 'YOUR_BASE64_32_BYTE_KEY_HERE';
