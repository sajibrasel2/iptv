<?php
/**
 * admin.php — Stream Sources Manager
 *
 * Password protected. Set ADMIN_PASS below before deploying.
 * Usage: https://techandclick.site/iptv/admin.php
 */

define('ADMIN_PASS', 'tctv2026admin');  // ← change this

session_start();

// ── Auth ──────────────────────────────────────────────────────────────────────
if ($_POST['logout'] ?? false) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
if ($_POST['pass'] ?? false) {
    if ($_POST['pass'] === ADMIN_PASS) {
        $_SESSION['auth'] = true;
    } else {
        $loginError = 'Wrong password.';
    }
}
if (!($_SESSION['auth'] ?? false)) {
    echo '<!DOCTYPE html><html><head><title>Admin Login</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>body{font-family:sans-serif;background:#0a0e1a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    form{background:#1e293b;padding:32px;border-radius:16px;min-width:300px;text-align:center}
    h2{margin:0 0 20px;color:#3b82f6}
    input[type=password]{width:100%;padding:10px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#e2e8f0;font-size:14px;box-sizing:border-box}
    button{margin-top:12px;width:100%;padding:10px;background:#3b82f6;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px}
    .err{color:#f87171;margin-top:8px;font-size:13px}</style></head><body>
    <form method="POST"><h2>🔒 Admin</h2>
    <input type="password" name="pass" placeholder="Password" autofocus>
    <button type="submit">Login</button>' .
    (isset($loginError) ? '<div class="err">' . $loginError . '</div>' : '') .
    '</form></body></html>';
    exit;
}

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host={$servername};dbname={$dbname};charset={$charset}",
        $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('<p style="color:red;font-family:sans-serif;padding:20px">DB Error: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

$msg = '';

// ── Handle actions ────────────────────────────────────────────────────────────

// Toggle status
if (isset($_POST['toggle_id'])) {
    $id  = (int) $_POST['toggle_id'];
    $cur = $pdo->prepare("SELECT status FROM stream_sources WHERE id=?");
    $cur->execute([$id]);
    $row = $cur->fetch();
    if ($row) {
        $new = $row['status'] === 'active' ? 'disabled' : 'active';
        $pdo->prepare("UPDATE stream_sources SET status=? WHERE id=?")->execute([$new, $id]);
        $msg = "Server #$id set to <strong>$new</strong>.";
    }
}

// Update priority
if (isset($_POST['update_priority'])) {
    $id  = (int) $_POST['priority_id'];
    $pri = (int) $_POST['priority_val'];
    $pdo->prepare("UPDATE stream_sources SET priority=? WHERE id=?")->execute([$pri, $id]);
    $msg = "Priority updated for #$id.";
}

// Add new source
if (isset($_POST['add_url'])) {
    $name = trim($_POST['add_name'] ?? '');
    $url  = trim($_POST['add_url']);
    $grp  = trim($_POST['add_grp'] ?? 'Sports');
    $pri  = (int) ($_POST['add_priority'] ?? 10);
    if ($url && $name) {
        // Encrypt the URL
        function encryptUrl(string $url, string $keyB64): string {
            $key = base64_decode($keyB64);
            $iv  = random_bytes(16);
            $enc = openssl_encrypt($url, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return base64_encode($iv) . ':' . base64_encode($enc);
        }
        $enc = encryptUrl($url, $DB_ENCRYPT_KEY);
        $pdo->prepare(
            "INSERT INTO stream_sources (name, grp, raw_url_enc, status, priority, source_type)
             VALUES (?, ?, ?, 'active', ?, 'manual')"
        )->execute([$name, $grp, $enc, $pri]);
        $msg = "Added: <strong>" . htmlspecialchars($name) . "</strong>.";
    }
}

// Delete source
if (isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    $pdo->prepare("DELETE FROM stream_sources WHERE id=?")->execute([$id]);
    $msg = "Deleted #$id.";
}

// Clear cache
if (isset($_POST['clear_cache'])) {
    $cacheFile = __DIR__ . '/links_cache.json';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
        $msg = 'Cache cleared.';
    } else {
        $msg = 'No cache file found.';
    }
}

// ── Fetch all sources ─────────────────────────────────────────────────────────
$rows = $pdo->query(
    "SELECT id, name, grp, status, priority, source_type, last_seen FROM stream_sources ORDER BY priority ASC"
)->fetchAll();

// Decrypt for display
function decryptUrl(string $enc, string $keyB64): string {
    $parts = explode(':', $enc, 2);
    if (count($parts) !== 2) return '(decrypt error)';
    $key  = base64_decode($keyB64);
    $iv   = base64_decode($parts[0]);
    $data = base64_decode($parts[1]);
    $plain = openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain ?: '(decrypt error)';
}

$rowsWithUrls = array_map(function($r) use ($pdo, $DB_ENCRYPT_KEY) {
    $enc = $pdo->prepare("SELECT raw_url_enc FROM stream_sources WHERE id=?");
    $enc->execute([$r['id']]);
    $e = $enc->fetch();
    $r['url'] = $e ? decryptUrl($e['raw_url_enc'], $DB_ENCRYPT_KEY) : '';
    return $r;
}, $rows);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stream Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0a0e1a;color:#e2e8f0;padding:16px;min-height:100vh}
h1{color:#3b82f6;margin-bottom:16px;font-size:20px}
.msg{background:#1e3a5f;border:1px solid #3b82f6;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px}
.card{background:#1e293b;border-radius:12px;padding:16px;margin-bottom:12px;border:1px solid #334155}
.row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.badge{padding:3px 8px;border-radius:20px;font-size:11px;font-weight:700}
.active{background:rgba(34,197,94,.2);color:#4ade80;border:1px solid rgba(34,197,94,.3)}
.disabled{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.2)}
.name{font-weight:600;font-size:14px;flex:1}
.url{font-size:11px;color:#64748b;word-break:break-all;margin-top:4px}
.type{font-size:10px;color:#475569;background:#0f172a;padding:2px 6px;border-radius:4px}
button{padding:6px 14px;border-radius:8px;border:none;cursor:pointer;font-size:12px;font-weight:600}
.btn-toggle{background:#334155;color:#94a3b8}
.btn-toggle:hover{background:#3b82f6;color:#fff}
.btn-del{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.2)}
.btn-del:hover{background:#ef4444;color:#fff}
.btn-green{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.2)}
section{margin-bottom:20px}
h2{color:#94a3b8;font-size:14px;margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em}
input,select{background:#0f172a;border:1px solid #334155;color:#e2e8f0;padding:8px 10px;border-radius:8px;font-size:13px;width:100%}
label{font-size:12px;color:#64748b;display:block;margin-bottom:4px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.full{grid-column:1/-1}
.pri-form{display:flex;gap:6px;align-items:center}
.pri-form input{width:60px}
.logout{float:right;background:#334155;color:#94a3b8;font-size:12px;padding:6px 12px;border-radius:8px;border:none;cursor:pointer}
</style>
</head>
<body>
<h1>📡 Stream Sources Admin
  <form method="POST" style="display:inline">
    <button name="logout" value="1" class="logout">Logout</button>
  </form>
</h1>

<?php if ($msg): ?>
<div class="msg"><?= $msg ?></div>
<?php endif ?>

<section>
<h2>Stream Sources (<?= count($rows) ?>)</h2>

<?php foreach ($rowsWithUrls as $r): ?>
<div class="card">
  <div class="row">
    <span class="name"><?= htmlspecialchars($r['name']) ?></span>
    <span class="badge <?= $r['status'] ?>"><?= $r['status'] ?></span>
    <span class="type"><?= $r['source_type'] ?></span>
    <span style="font-size:11px;color:#475569">P:<?= $r['priority'] ?></span>

    <form method="POST" style="display:inline">
      <input type="hidden" name="toggle_id" value="<?= $r['id'] ?>">
      <button class="btn-toggle" type="submit">
        <?= $r['status'] === 'active' ? '⏸ Disable' : '▶ Enable' ?>
      </button>
    </form>

    <form method="POST" class="pri-form">
      <input type="hidden" name="priority_id" value="<?= $r['id'] ?>">
      <input type="number" name="priority_val" value="<?= $r['priority'] ?>" min="1" max="99">
      <button name="update_priority" value="1" class="btn-toggle" type="submit">Set P</button>
    </form>

    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this source?')">
      <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
      <button class="btn-del" type="submit">🗑</button>
    </form>
  </div>
  <div class="url"><?= htmlspecialchars($r['url']) ?></div>
  <?php if ($r['last_seen']): ?>
  <div style="font-size:10px;color:#334155;margin-top:2px">Last seen: <?= $r['last_seen'] ?></div>
  <?php endif ?>
</div>
<?php endforeach ?>
</section>

<section>
<h2>Add New Source</h2>
<div class="card">
<form method="POST">
  <div class="grid">
    <div>
      <label>Name</label>
      <input type="text" name="add_name" placeholder="FIFA Live (Server X)" required>
    </div>
    <div>
      <label>Group</label>
      <input type="text" name="add_grp" value="Sports">
    </div>
    <div class="full">
      <label>Stream URL</label>
      <input type="url" name="add_url" placeholder="https://..." required>
    </div>
    <div>
      <label>Priority (1 = first)</label>
      <input type="number" name="add_priority" value="10" min="1" max="99">
    </div>
    <div style="display:flex;align-items:flex-end">
      <button type="submit" class="btn-green" style="width:100%">+ Add Source</button>
    </div>
  </div>
</form>
</div>
</section>

<section>
<h2>Cache</h2>
<div class="card">
  <form method="POST">
    <button name="clear_cache" value="1" class="btn-del" type="submit">🗑 Clear links_cache.json</button>
    <span style="font-size:12px;color:#475569;margin-left:10px">Forces stream.php to re-scrape fifalive.click on next load</span>
  </form>
</div>
</section>

</body>
</html>
