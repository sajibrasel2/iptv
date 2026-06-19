<?php
session_start();
require_once __DIR__.'/config.php';

// Authentication (same as original admin.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === 'admin') {
        $_SESSION['loggedin'] = true;
    } else {
        $error = "Invalid password!";
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

$conn = null;
if ($is_logged_in) {
    try {
        $dsn = "mysql:host=$servername;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $conn = new PDO($dsn, $username, $password, $options);

        // ---------- Existing Channel & Ad Handlers ----------
        if (isset($_POST['add_channel'])) {
            $stmt = $conn->prepare("INSERT INTO custom_channels (display_name, target_url) VALUES (:name, :url)");
            $stmt->execute([':name' => $_POST['display_name'], ':url' => $_POST['target_url']]);
        }
        if (isset($_GET['delete_channel'])) {
            $stmt = $conn->prepare("DELETE FROM custom_channels WHERE id = :id");
            $stmt->execute([':id' => $_GET['delete_channel']]);
            header('Location: admin.php');
            exit;
        }
        if (isset($_POST['update_ads'])) {
            $stmt = $conn->prepare("UPDATE ad_settings SET ad_name = :name, ad_url = :url WHERE id = :id");
            foreach ($_POST['ad_id'] as $index => $id) {
                $stmt->execute([
                    ':name' => $_POST['ad_name'][$index],
                    ':url'  => $_POST['ad_url'][$index],
                    ':id'   => $id,
                ]);
            }
        }

        // ---------- Prediction Admin Handlers ----------
        if (isset($_POST['create_campaign'])) {
            $raw = $_POST['match_time'];
            $match_time = str_replace('T', ' ', $raw) . ':00';
            $stmt = $conn->prepare("INSERT INTO prediction_campaigns (title, team_a, team_b, match_time, prize_image_url, fb_post_link, status) VALUES (:title, :team_a, :team_b, :match_time, :prize_image_url, :fb_post_link, 'active')");
            $stmt->execute([
                ':title' => $_POST['title'],
                ':team_a' => $_POST['team_a'],
                ':team_b' => $_POST['team_b'],
                ':match_time' => $match_time,
                ':prize_image_url' => $_POST['prize_image_url'],
                ':fb_post_link' => $_POST['fb_post_link'],
            ]);
            $newId = $conn->lastInsertId();
            $conn->exec("UPDATE prediction_campaigns SET status='closed' WHERE id <> $newId AND status='active'");
        }
        if (isset($_POST['toggle_status'])) {
            $campaignId = (int)$_POST['campaign_id'];
            $newStatus = $_POST['new_status'];
            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE prediction_campaigns SET status=:status WHERE id=:id");
            $stmt->execute([':status' => $newStatus, ':id' => $campaignId]);
            if ($newStatus === 'active') {
                $conn->exec("UPDATE prediction_campaigns SET status='closed' WHERE id <> $campaignId AND status='active'");
            }
            $conn->commit();
        }
        if (isset($_POST['delete_campaign'])) {
            $campaignId = (int)$_POST['campaign_id'];
            $conn->beginTransaction();
            $stmt = $conn->prepare("DELETE FROM prediction_entries WHERE campaign_id = :cid");
            $stmt->execute([':cid' => $campaignId]);
            $stmt = $conn->prepare("DELETE FROM prediction_campaigns WHERE id = :id");
            $stmt->execute([':id' => $campaignId]);
            $conn->commit();
            header('Location: admin.php');
            exit;
        }
        if (isset($_POST['draw_winners'])) {
            $campaignId = (int)$_POST['campaign_id'];
            $actualResult = $_POST['actual_result'] ?? '';
            $actualGoalsA = isset($_POST['actual_goals_a']) ? (int)$_POST['actual_goals_a'] : null;
            $actualGoalsB = isset($_POST['actual_goals_b']) ? (int)$_POST['actual_goals_b'] : null;
            $stmt = $conn->prepare("SELECT status FROM prediction_campaigns WHERE id=:id");
            $stmt->execute([':id' => $campaignId]);
            $status = $stmt->fetchColumn();
            if ($status !== 'closed') {
                $drawError = "Campaign must be closed before drawing winners.";
            } elseif ($actualResult === '' || !is_numeric($actualGoalsA) || !is_numeric($actualGoalsB)) {
                $drawError = "Please provide the actual match result and exact score.";
            } else {
                $matchOutcome = $actualResult;
                $stmt = $conn->prepare("SELECT id FROM prediction_entries WHERE campaign_id=:cid AND has_shared=1 AND is_winner=0 AND predicted_team=:predicted_team AND predicted_score_a=:score_a AND predicted_score_b=:score_b");
                $stmt->execute([
                    ':cid' => $campaignId,
                    ':predicted_team' => $matchOutcome,
                    ':score_a' => $actualGoalsA,
                    ':score_b' => $actualGoalsB,
                ]);
                $eligible = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (count($eligible) < 3) {
                    $drawError = "Not enough eligible entries to draw 3 winners.";
                } else {
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
    <title>Admin Dashboard - Live Sports Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: {} } };
    </script>
    <style>
        .select2-container--default .select2-selection--single {
            background-color: #0f172a;
            border-color: #334155;
            color: #f8fafc;
            border-radius: 0.75rem;
            min-height: 3rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f8fafc;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #f8fafc transparent transparent transparent;
        }
    </style>
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
    <header class="flex justify-between items-center py-6 mb-8 border-b border-slate-800">
        <h1 class="text-2xl font-bold text-slate-300">Admin Dashboard</h1>
        <a href="?logout=1" class="text-sm font-semibold text-slate-400 hover:text-white">Logout</a>
    </header>
    <!-- Tab Navigation -->
    <div class="flex space-x-4 mb-6">
        <button id="tabBtnChannels" class="px-4 py-2 rounded bg-indigo-600 text-white" onclick="showTab('channels')">Live Channels & Ads</button>
        <button id="tabBtnPredictions" class="px-4 py-2 rounded bg-slate-700 text-slate-200" onclick="showTab('predictions')">Prediction Campaigns</button>
    </div>

    <!-- Channels Tab -->
    <div id="tab-channels">
        <div class="grid md:grid-cols-2 gap-8">
            <!-- Ad Settings Section -->
            <div class="bg-slate-800/50 p-6 rounded-2xl border border-white/5 shadow-xl h-fit">
                <h2 class="text-xl font-bold mb-6 flex items-center space-x-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Ad Monetization</span>
                </h2>
                <form method="POST" class="space-y-4">
                    <div class="space-y-4 max-h-[350px] overflow-y-auto pr-2">
                        <?php
                        $stmt = $conn->query("SELECT * FROM ad_settings ORDER BY id ASC");
                        while ($ad = $stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                        <div class="p-4 bg-slate-900/50 rounded-xl border border-slate-700/50">
                            <input type="hidden" name="ad_id[]" value="<?php echo $ad['id']; ?>">
                            <div class="mb-3">
                                <label class="block text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider">Ad Label</label>
                                <input type="text" name="ad_name[]" value="<?php echo htmlspecialchars($ad['ad_name']); ?>" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider">Direct Link URL</label>
                                <input type="url" name="ad_url[]" value="<?php echo htmlspecialchars($ad['ad_url']); ?>" placeholder="Leave empty to disable" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <button type="submit" name="update_ads" class="w-full bg-indigo-500/10 hover:bg-indigo-500 text-indigo-400 hover:text-white border border-indigo-500/30 hover:border-indigo-500 font-semibold py-3 px-4 rounded-xl transition-all mt-4">Save All Ad Links</button>
                </form>
            </div>
            <!-- Channels Section -->
            <div class="space-y-6">
                <div class="bg-slate-800/50 p-6 rounded-2xl border border-white/5 shadow-xl">
                    <h2 class="text-xl font-bold mb-6">Add New Channel</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1">Display Name (Brand)</label>
                            <input type="text" name="display_name" required placeholder="e.g. Sports 1 HD" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-pink-500 focus:ring-1 focus:ring-pink-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1">Target Stream URL</label>
                            <input type="url" name="target_url" required placeholder="https://..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-pink-500 focus:ring-1 focus:ring-pink-500">
                        </div>
                        <button type="submit" name="add_channel" class="w-full bg-pink-500/10 hover:bg-pink-500 text-pink-400 hover:text-white border border-pink-500/30 hover:border-pink-500 font-semibold py-3 px-4 rounded-xl transition-all">Add Channel</button>
                    </form>
                </div>
                <div class="bg-slate-800/50 p-6 rounded-2xl border border-white/5 shadow-xl">
                    <h2 class="text-xl font-bold mb-4">Manage Channels</h2>
                    <div class="space-y-3">
                        <?php
                        $stmt = $conn->query("SELECT * FROM custom_channels ORDER BY id DESC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                        <div class="flex items-center justify-between p-4 bg-slate-900/50 rounded-xl border border-white/5">
                            <div class="overflow-hidden">
                                <h3 class="font-bold text-slate-200 truncate"><?php echo htmlspecialchars($row['display_name']); ?></h3>
                                <p class="text-xs text-slate-500 truncate mt-1"><?php echo htmlspecialchars($row['target_url']); ?></p>
                            </div>
                            <a href="?delete_channel=<?php echo $row['id']; ?>" class="ml-4 p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg" onclick="return confirm('Delete this channel?');">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </a>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Predictions Tab -->
    <div id="tab-predictions" class="hidden">
        <section class="bg-slate-800/50 p-6 rounded-2xl mb-8">
            <h2 class="text-xl font-bold mb-4">Create New Campaign</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="text" name="title" placeholder="Campaign Title" required class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
                <select name="team_a" id="team_a" required class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
                    <option value="" disabled selected>Team A</option>
                    <option value="Argentina">🇦🇷 Argentina</option>
                    <option value="Brazil">🇧🇷 Brazil</option>
                    <option value="Uruguay">🇺🇾 Uruguay</option>
                    <option value="Colombia">🇨🇴 Colombia</option>
                    <option value="Ecuador">🇪🇨 Ecuador</option>
                    <option value="Venezuela">🇻🇪 Venezuela</option>
                    <option value="USA">🇺🇸 USA</option>
                    <option value="Canada">🇨🇦 Canada</option>
                    <option value="Mexico">🇲🇽 Mexico</option>
                    <option value="Costa Rica">🇨🇷 Costa Rica</option>
                    <option value="Panama">🇵🇦 Panama</option>
                    <option value="Jamaica">🇯🇲 Jamaica</option>
                    <option value="Haiti">🇭🇹 Haiti</option>
                    <option value="France">🇫🇷 France</option>
                    <option value="England">🇬🇧 England</option>
                    <option value="Spain">🇪🇸 Spain</option>
                    <option value="Germany">🇩🇪 Germany</option>
                    <option value="Portugal">🇵🇹 Portugal</option>
                    <option value="Italy">🇮🇹 Italy</option>
                    <option value="Netherlands">🇳🇱 Netherlands</option>
                    <option value="Croatia">🇭🇷 Croatia</option>
                    <option value="Belgium">🇧🇪 Belgium</option>
                    <option value="Switzerland">🇨🇭 Switzerland</option>
                    <option value="Denmark">🇩🇰 Denmark</option>
                    <option value="Serbia">🇷🇸 Serbia</option>
                    <option value="Austria">🇦🇹 Austria</option>
                    <option value="Ukraine">🇺🇦 Ukraine</option>
                    <option value="Turkey">🇹🇷 Turkey</option>
                    <option value="Poland">🇵🇱 Poland</option>
                    <option value="Morocco">🇲🇦 Morocco</option>
                    <option value="Senegal">🇸🇳 Senegal</option>
                    <option value="Egypt">🇪🇬 Egypt</option>
                    <option value="Algeria">🇩🇿 Algeria</option>
                    <option value="Ivory Coast">🇨🇮 Ivory Coast</option>
                    <option value="Nigeria">🇳🇬 Nigeria</option>
                    <option value="Cameroon">🇨🇲 Cameroon</option>
                    <option value="Mali">🇲🇱 Mali</option>
                    <option value="Tunisia">🇹🇳 Tunisia</option>
                    <option value="Japan">🇯🇵 Japan</option>
                    <option value="South Korea">🇰🇷 South Korea</option>
                    <option value="Iran">🇮🇷 Iran</option>
                    <option value="Australia">🇦🇺 Australia</option>
                    <option value="Saudi Arabia">🇸🇦 Saudi Arabia</option>
                    <option value="Qatar">🇶🇦 Qatar</option>
                    <option value="Uzbekistan">🇺🇿 Uzbekistan</option>
                    <option value="UAE">🇦🇪 UAE</option>
                    <option value="New Zealand">🇳🇿 New Zealand</option>
                    <option value="Chile">🇨🇱 Chile</option>
                    <option value="Peru">🇵🇪 Peru</option>
                </select>
                <select name="team_b" id="team_b" required class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
                    <option value="" disabled selected>Team B</option>
                    <option value="Argentina">🇦🇷 Argentina</option>
                    <option value="Brazil">🇧🇷 Brazil</option>
                    <option value="Uruguay">🇺🇾 Uruguay</option>
                    <option value="Colombia">🇨🇴 Colombia</option>
                    <option value="Ecuador">🇪🇨 Ecuador</option>
                    <option value="Venezuela">🇻🇪 Venezuela</option>
                    <option value="USA">🇺🇸 USA</option>
                    <option value="Canada">🇨🇦 Canada</option>
                    <option value="Mexico">🇲🇽 Mexico</option>
                    <option value="Costa Rica">🇨🇷 Costa Rica</option>
                    <option value="Panama">🇵🇦 Panama</option>
                    <option value="Jamaica">🇯🇲 Jamaica</option>
                    <option value="Haiti">🇭🇹 Haiti</option>
                    <option value="France">🇫🇷 France</option>
                    <option value="England">🇬🇧 England</option>
                    <option value="Spain">🇪🇸 Spain</option>
                    <option value="Germany">🇩🇪 Germany</option>
                    <option value="Portugal">🇵🇹 Portugal</option>
                    <option value="Italy">🇮🇹 Italy</option>
                    <option value="Netherlands">🇳🇱 Netherlands</option>
                    <option value="Croatia">🇭🇷 Croatia</option>
                    <option value="Belgium">🇧🇪 Belgium</option>
                    <option value="Switzerland">🇨🇭 Switzerland</option>
                    <option value="Denmark">🇩🇰 Denmark</option>
                    <option value="Serbia">🇷🇸 Serbia</option>
                    <option value="Austria">🇦🇹 Austria</option>
                    <option value="Ukraine">🇺🇦 Ukraine</option>
                    <option value="Turkey">🇹🇷 Turkey</option>
                    <option value="Poland">🇵🇱 Poland</option>
                    <option value="Morocco">🇲🇦 Morocco</option>
                    <option value="Senegal">🇸🇳 Senegal</option>
                    <option value="Egypt">🇪🇬 Egypt</option>
                    <option value="Algeria">🇩🇿 Algeria</option>
                    <option value="Ivory Coast">🇨🇮 Ivory Coast</option>
                    <option value="Nigeria">🇳🇬 Nigeria</option>
                    <option value="Cameroon">🇨🇲 Cameroon</option>
                    <option value="Mali">🇲🇱 Mali</option>
                    <option value="Tunisia">🇹🇳 Tunisia</option>
                    <option value="Japan">🇯🇵 Japan</option>
                    <option value="South Korea">🇰🇷 South Korea</option>
                    <option value="Iran">🇮🇷 Iran</option>
                    <option value="Australia">🇦🇺 Australia</option>
                    <option value="Saudi Arabia">🇸🇦 Saudi Arabia</option>
                    <option value="Qatar">🇶🇦 Qatar</option>
                    <option value="Uzbekistan">🇺🇿 Uzbekistan</option>
                    <option value="UAE">🇦🇪 UAE</option>
                    <option value="New Zealand">🇳🇿 New Zealand</option>
                    <option value="Chile">🇨🇱 Chile</option>
                    <option value="Peru">🇵🇪 Peru</option>
                </select>
                <input type="datetime-local" name="match_time" required class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
                <input type="url" name="prize_image_url" placeholder="Prize Image URL" class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
                <input type="url" name="fb_post_link" placeholder="Facebook Post Link" class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white">
                <button type="submit" name="create_campaign" class="col-span-2 bg-green-500 hover:bg-green-600 text-white font-semibold py-2 rounded-xl transition-colors">Create Campaign</button>
            </form>
        </section>
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
                        <td class="px-4 py-2 text-center space-x-2">
                            <a href="?view_entries=<?php echo $c['id']; ?>" class="text-sm bg-slate-600 hover:bg-slate-500 text-white px-2 py-1 rounded">View Entries</a>
                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this campaign and all its predictions?');">
                                <input type="hidden" name="campaign_id" value="<?php echo $c['id']; ?>">
                                <button type="submit" name="delete_campaign" class="text-sm bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded">Delete</button>
                            </form>
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
                            <th class="px-3 py-2">Score</th>
                            <th class="px-3 py-2">Shared?</th>
                            <th class="px-3 py-2">Winner</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare("SELECT * FROM prediction_entries WHERE campaign_id=:cid ORDER BY id DESC");
                        $stmt->execute([':cid'=>$campaignId]);
                        while ($e = $stmt->fetch()):
                            $predictionLabel = $e['predicted_team'] === 'team_a' ? $campaign['team_a'] : ($e['predicted_team'] === 'team_b' ? $campaign['team_b'] : 'Draw');
                        ?>
                        <tr class="border-b border-slate-600/30">
                            <td class="px-3 py-2 text-center"><?php echo $e['id']; ?></td>
                            <td class="px-3 py-2"><?php echo htmlspecialchars($e['user_name']); ?></td>
                            <td class="px-3 py-2"><?php echo htmlspecialchars($e['user_phone']); ?></td>
                            <td class="px-3 py-2"><?php echo htmlspecialchars($predictionLabel); ?></td>
                            <td class="px-3 py-2 text-center"><?php echo htmlspecialchars($e['predicted_score_a'] . ' - ' . $e['predicted_score_b']); ?></td>
                            <td class="px-3 py-2 text-center"><?php echo $e['has_shared'] ? 'Yes' : 'No'; ?></td>
                            <td class="px-3 py-2 text-center"><?php echo $e['is_winner'] ? '🏆' : '-'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php if ($campaign['status'] === 'closed'): ?>
                    <form method="POST" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                        <input type="hidden" name="campaign_id" value="<?php echo $campaignId; ?>">
                        <div>
                            <label class="block text-xs text-slate-400 uppercase tracking-wider mb-2">Actual Result</label>
                            <select name="actual_result" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white">
                                <option value="team_a"><?php echo htmlspecialchars($campaign['team_a']); ?> Win</option>
                                <option value="draw">Draw</option>
                                <option value="team_b"><?php echo htmlspecialchars($campaign['team_b']); ?> Win</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 uppercase tracking-wider mb-2">Team A Goals</label>
                            <input type="number" min="0" name="actual_goals_a" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white" placeholder="0">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 uppercase tracking-wider mb-2">Team B Goals</label>
                            <input type="number" min="0" name="actual_goals_b" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white" placeholder="0">
                        </div>
                        <div class="col-span-1 md:col-span-3">
                            <button type="submit" name="draw_winners" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-3 rounded-xl">Draw 3 Winners</button>
                        </div>
                    </form>
                    <?php if (isset($drawError)) echo "<p class='mt-2 text-red-400'>".$drawError."</p>"; ?>
                    <?php if (isset($drawSuccess)) echo "<p class='mt-2 text-green-400'>".$drawSuccess."</p>"; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJ+YOTui3gWkH+17Qb/l7ZtC7x3KSkdEXd6a0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        function showTab(tab) {
            const channelsDiv = document.getElementById('tab-channels');
            const predictionsDiv = document.getElementById('tab-predictions');
            const btnChannels = document.getElementById('tabBtnChannels');
            const btnPredictions = document.getElementById('tabBtnPredictions');
            if (tab === 'channels') {
                channelsDiv.classList.remove('hidden');
                predictionsDiv.classList.add('hidden');
                btnChannels.classList.replace('bg-slate-700', 'bg-indigo-600');
                btnChannels.classList.replace('text-slate-200', 'text-white');
                btnPredictions.classList.replace('bg-indigo-600', 'bg-slate-700');
                btnPredictions.classList.replace('text-white', 'text-slate-200');
            } else {
                predictionsDiv.classList.remove('hidden');
                channelsDiv.classList.add('hidden');
                btnPredictions.classList.replace('bg-slate-700', 'bg-indigo-600');
                btnPredictions.classList.replace('text-slate-200', 'text-white');
                btnChannels.classList.replace('bg-indigo-600', 'bg-slate-700');
                btnChannels.classList.replace('text-white', 'text-slate-200');
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (window.jQuery && window.jQuery.fn.select2) {
                $('#team_a').select2({
                    width: '100%',
                    placeholder: 'Select Team A',
                    minimumResultsForSearch: 0,
                    dropdownParent: $(document.body)
                });
                $('#team_b').select2({
                    width: '100%',
                    placeholder: 'Select Team B',
                    minimumResultsForSearch: 0,
                    dropdownParent: $(document.body)
                });
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
