<?php
/**
 * ping.php — Real-Time Viewer Tracker
 * 
 * Receives silent pings from active viewers every 30 seconds.
 * Stores active sessions in active_users.json with timestamp.
 * Auto-cleans stale sessions (>60 seconds old).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

const DATA_FILE = __DIR__ . '/active_users.json';
const SESSION_TTL = 60; // seconds — consider user inactive after 60s

// ── Get visitor identifier ───────────────────────────────────────────────────
function getVisitorId(): string {
    // Use IP + User-Agent as unique identifier
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return md5($ip . '|' . $ua);
}

// ── Load active users ─────────────────────────────────────────────────────────
function loadUsers(): array {
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    $raw = file_get_contents(DATA_FILE);
    $data = $raw ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

// ── Save active users ─────────────────────────────────────────────────────────
function saveUsers(array $users): void {
    $json = json_encode($users, JSON_PRETTY_PRINT);
    file_put_contents(DATA_FILE, $json, LOCK_EX);
}

// ── Clean stale sessions ──────────────────────────────────────────────────────
function cleanStale(array $users): array {
    $now = time();
    return array_filter($users, fn($ts) => ($now - $ts) <= SESSION_TTL);
}

// ══════════════════════════════════════════════════════════════════════════════
//  MAIN
// ══════════════════════════════════════════════════════════════════════════════

$users = loadUsers();
$users = cleanStale($users);

// Update this visitor's timestamp
$visitorId = getVisitorId();
$users[$visitorId] = time();

saveUsers($users);

// Return current active count
echo json_encode([
    'ok' => true,
    'active' => count($users),
    'timestamp' => time(),
]);
