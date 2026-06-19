<?php
// admin_predictions.php
// Admin interface for Prediction & Giveaway feature
// Authentication logic copied from admin.php
session_start();
require_once __DIR__.'/config.php';

// Simple login handling (same as admin.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === 'admin') {
        $_SESSION['loggedin'] = true;
    } else {
        $error = "Invalid password!";
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_predictions.php');
    exit;
}
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

$conn = null;
if ($is_logged_in) {
    try {
        $dsn = "mysql:host=$servername;dbname=$dbname;charset=$charset";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
        $conn = new PDO($dsn, $username, $password, $options);

        // ----------- Handle Create Campaign ------------
        if (isset($_POST['create_campaign'])) {
            $stmt = $conn->prepare("INSERT INTO prediction_campaigns (title, team_a, team_b, match_time, prize_image_url, fb_post_link, status) VALUES (:title, :team_a, :team_b, :match_time, :prize_image_url, :fb_post_link, 'active')");
            $stmt->execute([
                ':title' => $_POST['title'],
                ':team_a' => $_POST['team_a'],
                ':team_b' => $_POST['team_b'],
                ':match_time' => $_POST['match_time'],
                ':prize_image_url' => $_POST['prize_image_url'],
                ':fb_post_link' => $_POST['fb_post_link']
            ]);
            // Ensure only this campaign is active
            $newId = $conn->lastInsertId();
            $conn->exec("UPDATE prediction_campaigns SET status='closed' WHERE id <> $newId AND status='active'");
        }

        // ----------- Handle Status Toggle ------------
        if (isset($_POST['toggle_status'])) {
            $campaignId = (int)$_POST['campaign_id'];
            $newStatus = $_POST['new_status']; // 'active' or 'closed'
            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE prediction_campaigns SET status=:status WHERE id=:id");
            $stmt->execute([':status' => $newStatus, ':id' => $campaignId]);
            if ($newStatus === 'active') {
                // close others
                $conn->exec("UPDATE prediction_campaigns SET status='closed' WHERE id <> $campaignId AND status='active'");
            }
            $conn->commit();
        }

        // ----------- Handle Draw Winners ------------
        if (isset($_POST['draw_winners'])) {
            $campaignId = (int)$_POST['campaign_id'];
            // Ensure campaign is closed
            $stmt = $conn->prepare("SELECT status FROM prediction_campaigns WHERE id=:id");
            $stmt->execute([':id' => $campaignId]);
            $status = $stmt->fetchColumn();
            if ($status !== 'closed') {
                $drawError = "Campaign must be closed before drawing winners.";
            } else {
                // Get eligible entries (has_shared = 1)
                $stmt = $conn->prepare("SELECT id FROM prediction_entries WHERE campaign_id=:cid AND has_shared=1 AND is_winner=0");
                $stmt->execute([':cid' => $campaignId]);
                $eligible = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (count($eligible) < 3) {
                    $drawError = "Not enough eligible entries to draw 3 winners.";
                } else {
                    // Randomly pick 3 distinct ids
                    shuffle($eligible);
                    $winners = array_slice($eligible, 0, 3);
                    $in = implode(',', $winners);
                    $conn->exec("UPDATE prediction_entries SET is_winner=1 WHERE id IN ($in)");
                    $drawSuccess = "Winners drawn successfully.";
                }
            }
        }
    } catch (PDOException $e) {
        die('Connection failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Prediction Campaigns</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: {} } };
    </script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen font-sans antialiased p-6">
<?php if (!$is_logged_in): ?>
    <div class="max-w-md mx-auto bg-slate-800/50 p-8 rounded-2xl border border-white/5 shadow-2xl mt-20">
        <h2 class="text-xl font-bold mb-6 text-center">Login Required</h2>
        <?php if (isset($error)): ?>
            <div class="bg-red-500/20 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg mb-6 text-sm"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Password</label>
                <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-indigo-500">
            </div>
            <button type="submit" name="login" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 rounded-xl transition-colors">Login</button>
        </form>
    </div>
<?php else: ?>
    <header class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold text-slate-300">Prediction & Giveaway Admin</h1>
        <a href="?logout=1" class="text-sm font-semibold text-slate-400 hover:text-white">Logout</a>
    </header>

    <!-- Create Campaign Form -->
    <section class="bg-slate-800/50 p-6 rounded-2xl mb-8">
        <h2 class="text-xl font-bold mb-4">Create New Campaign</h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="title" placeholder="Campaign Title" required class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
            <input type="text" name="team_a" placeholder="Team A" required class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
            <input type="text" name="team_b" placeholder="Team B" required class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
            <input type="datetime-local" name="match_time" required class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
            <input type="url" name="prize_image_url" placeholder="Prize Image URL" class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
            <input type="url" name="fb_post_link" placeholder="Facebook Post Link" class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
            <button type="submit" name="create_campaign" class="col-span-2 bg-green-500 hover:bg-green-600 text-white font-semibold py-2 rounded-xl transition-colors">Create Campaign</button>
        </form>
    </section>

    <!-- Campaigns Table -->
    <section class="overflow-x-auto mb-8">
        <h2 class="text-xl font-bold mb-4">Existing Campaigns</h2>
        <table class="min-w-full bg-slate-800/30 rounded-xl">
            <thead class="bg-slate-700/50">
                <tr>
                    <th class="px-4 py-2">ID</th>
                    <th class="px-4 py-2">Title</th>
                    <th class="px-4 py-2">Teams</th>
                    <th class="px-4 py-2">Match Time</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->query("SELECT * FROM prediction_campaigns ORDER BY id DESC");
                while ($c = $stmt->fetch()):
                ?>
                <tr class="border-b border-slate-700/30">
                    <td class="px-4 py-2 text-center"><?php echo $c['id']; ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($c['title']); ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($c['team_a']).' vs '.htmlspecialchars($c['team_b']); ?></td>
                    <td class="px-4 py-2"><?php echo $c['match_time']; ?></td>
                    <td class="px-4 py-2 text-center">
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="campaign_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $c['status'] === 'active' ? 'closed' : 'active'; ?>">
                            <button type="submit" name="toggle_status" class="px-3 py-1 rounded <?php echo $c['status'] === 'active' ? 'bg-indigo-600' : 'bg-gray-600'; ?> text-white">
                                <?php echo ucfirst($c['status']); ?>
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-2 text-center">
                        <a href="?view_entries=<?php echo $c['id']; ?>" class="text-sm bg-slate-600 hover:bg-slate-500 text-white px-2 py-1 rounded">View Entries</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </section>

    <?php if (isset($_GET['view_entries'])): ?>
        <?php
        $campaignId = (int)$_GET['view_entries'];
        $campaign = $conn->query("SELECT * FROM prediction_campaigns WHERE id=$campaignId")->fetch();
        ?>
        <section class="bg-slate-800/50 p-6 rounded-2xl mb-8">
            <h2 class="text-xl font-bold mb-4">Entries for: <?php echo htmlspecialchars($campaign['title']); ?></h2>
            <table class="min-w-full bg-slate-700/30 rounded-xl">
                <thead class="bg-slate-600/50">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Phone</th>
                        <th class="px-3 py-2">Prediction</th>
                        <th class="px-3 py-2">Shared?</th>
                        <th class="px-3 py-2">Winner</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM prediction_entries WHERE campaign_id=:cid ORDER BY id DESC");
                    $stmt->execute([':cid'=>$campaignId]);
                    while ($e = $stmt->fetch()):
                    ?>
                    <tr class="border-b border-slate-600/30">
                        <td class="px-3 py-2 text-center"><?php echo $e['id']; ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($e['user_name']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($e['user_phone']); ?></td>
                        <td class="px-3 py-2"><?php echo $e['predicted_team']; ?></td>
                        <td class="px-3 py-2 text-center"><?php echo $e['has_shared'] ? 'Yes' : 'No'; ?></td>
                        <td class="px-3 py-2 text-center"><?php echo $e['is_winner'] ? '🏆' : '-'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php if ($campaign['status'] === 'closed'): ?>
                <form method="POST" class="mt-4">
                    <input type="hidden" name="campaign_id" value="<?php echo $campaignId; ?>">
                    <button type="submit" name="draw_winners" class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-2 px-4 rounded">Draw 3 Winners</button>
                </form>
                <?php if (isset($drawError)) echo "<p class='mt-2 text-red-400'>".$drawError."</p>"; ?>
                <?php if (isset($drawSuccess)) echo "<p class='mt-2 text-green-400'>".$drawSuccess."</p>"; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

<?php endif; ?>
</body>
</html>
