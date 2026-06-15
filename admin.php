<?php
session_start();

require_once 'config.php';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === 'admin') {
        $_SESSION['loggedin'] = true;
    } else {
        $error = "Invalid password!";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Check if logged in
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

$conn = null;
if ($is_logged_in) {
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=$charset", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Handle Add Channel
        if (isset($_POST['add_channel'])) {
            $stmt = $conn->prepare("INSERT INTO custom_channels (display_name, target_url) VALUES (:name, :url)");
            $stmt->execute(['name' => $_POST['display_name'], 'url' => $_POST['target_url']]);
        }
        
        // Handle Delete Channel
        if (isset($_GET['delete_channel'])) {
            $stmt = $conn->prepare("DELETE FROM custom_channels WHERE id = :id");
            $stmt->execute(['id' => $_GET['delete_channel']]);
            header("Location: admin.php");
            exit;
        }

        // Handle Update Ads
        if (isset($_POST['update_ads'])) {
            $stmt = $conn->prepare("UPDATE ad_settings SET ad_name = :name, ad_url = :url WHERE id = :id");
            foreach ($_POST['ad_id'] as $index => $id) {
                $stmt->execute([
                    'name' => $_POST['ad_name'][$index],
                    'url' => $_POST['ad_url'][$index],
                    'id' => $id
                ]);
            }
        }

    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
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
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            900: '#0f172a',
                            800: '#1e293b',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen font-sans antialiased selection:bg-indigo-500 selection:text-white">
    <div class="max-w-4xl mx-auto p-6">
        
        <header class="flex justify-between items-center py-6 mb-8 border-b border-slate-800">
            <div class="flex items-center space-x-3 px-6 py-2 rounded-full" style="background: radial-gradient(circle, rgba(165, 243, 252, 0.15) 0%, rgba(15, 23, 42, 0) 70%);">
                <img src="t&c.png" alt="Tech & Click TV" class="h-8 w-auto object-contain">
                <h1 class="text-xl font-bold text-slate-300">Admin</h1>
            </div>
            <?php if ($is_logged_in): ?>
                <a href="?logout=1" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors">Logout</a>
            <?php endif; ?>
        </header>

        <?php if (!$is_logged_in): ?>
            <div class="max-w-md mx-auto bg-slate-800/50 p-8 rounded-2xl border border-white/5 shadow-2xl mt-20">
                <h2 class="text-xl font-bold mb-6 text-center">Login Required</h2>
                <?php if (isset($error)): ?>
                    <div class="bg-red-500/20 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg mb-6 text-sm">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Password</label>
                        <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors">
                    </div>
                    <button type="submit" name="login" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 px-4 rounded-xl transition-colors">
                        Login
                    </button>
                </form>
            </div>
        <?php else: ?>
            
            <div class="grid md:grid-cols-2 gap-8">
                
                <!-- Ad Settings Section -->
                <div class="bg-slate-800/50 p-6 rounded-2xl border border-white/5 shadow-xl h-fit">
                    <h2 class="text-xl font-bold mb-6 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
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
                                    <input type="text" name="ad_name[]" value="<?php echo htmlspecialchars($ad['ad_name']); ?>" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-1 uppercase tracking-wider">Direct Link URL</label>
                                    <input type="url" name="ad_url[]" value="<?php echo htmlspecialchars($ad['ad_url']); ?>" placeholder="Leave empty to disable" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 transition-colors">
                                </div>
                            </div>
                        <?php endwhile; ?>
                        </div>
                        <button type="submit" name="update_ads" class="w-full bg-indigo-500/10 hover:bg-indigo-500 text-indigo-400 hover:text-white border border-indigo-500/30 hover:border-indigo-500 font-semibold py-3 px-4 rounded-xl transition-all mt-4">
                            Save All Ad Links
                        </button>
                    </form>
                </div>

                <!-- Channels Section -->
                <div class="space-y-6">
                    <!-- Add New Channel -->
                    <div class="bg-slate-800/50 p-6 rounded-2xl border border-white/5 shadow-xl">
                        <h2 class="text-xl font-bold mb-6">Add New Channel</h2>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Display Name (Brand)</label>
                                <input type="text" name="display_name" required placeholder="e.g. Sports 1 HD" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-pink-500 focus:ring-1 focus:ring-pink-500 transition-colors">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Target Stream URL</label>
                                <input type="url" name="target_url" required placeholder="https://..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-pink-500 focus:ring-1 focus:ring-pink-500 transition-colors">
                            </div>
                            <button type="submit" name="add_channel" class="w-full bg-pink-500/10 hover:bg-pink-500 text-pink-400 hover:text-white border border-pink-500/30 hover:border-pink-500 font-semibold py-3 px-4 rounded-xl transition-all">
                                Add Channel
                            </button>
                        </form>
                    </div>

                    <!-- List Channels -->
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
                                <a href="?delete_channel=<?php echo $row['id']; ?>" class="ml-4 p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-colors" onclick="return confirm('Delete this channel?');">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </a>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </div>
</body>
</html>
