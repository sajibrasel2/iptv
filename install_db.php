<?php
require_once 'config.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=$charset", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create custom_channels table
    $sql = "CREATE TABLE IF NOT EXISTS custom_channels (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        display_name VARCHAR(255) NOT NULL,
        target_url VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    
    // Create ad_settings table
    $sql = "CREATE TABLE IF NOT EXISTS ad_settings (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ad_name VARCHAR(100) NOT NULL,
        ad_url VARCHAR(500) NOT NULL
    )";
    $conn->exec($sql);

    // Create app_settings table for global portal configuration
    $sql = "CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL
    )";
    $conn->exec($sql);

    // Insert default channels if table is empty
    $stmt = $conn->query("SELECT COUNT(*) FROM custom_channels");
    if ($stmt->fetchColumn() == 0) {
        $sql = "INSERT INTO custom_channels (display_name, target_url) VALUES 
                ('Tech & Click TV - Server 1', 'https://famelack.com/tv'),
                ('Tech & Click TV - Server 2', 'https://www.crichd.tv/'),
                ('Tech & Click TV - Server 3', 'https://sportzfytvlive.xyz/'),
                ('Tech & Click TV - Server 4', 'https://txreca.movielinkbd.pw/')";
        $conn->exec($sql);
    }

    // Insert 5 ad slots with provided links if table is empty
    $stmt = $conn->query("SELECT COUNT(*) FROM ad_settings");
    if ($stmt->fetchColumn() == 0) {
        $sql = "INSERT INTO ad_settings (ad_name, ad_url) VALUES 
                ('Adsterra Link 1', 'https://omg10.com/4/11017767'),
                ('Monetag Link 1', 'https://www.effectivecpmnetwork.com/mgtqwzbp?key=5c4003e0ae2b0ebd387daded087bc9aa'),
                ('Ad Slot 3', ''),
                ('Ad Slot 4', ''),
                ('Ad Slot 5', '')";
        $conn->exec($sql);
    }

    echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; text-align: center;'>";
    echo "<h2 style='color: #4CAF50;'>Database Installation Successful!</h2>";
    echo "<p>The custom_channels and ad_settings tables were created and populated with your default links.</p>";
    echo "<p style='color: red; font-weight: bold; margin-top: 20px; padding: 10px; background-color: #ffebee; border-radius: 4px;'>";
    echo "⚠️ CRITICAL SECURITY WARNING: <br>Please delete this file (install_db.php) immediately from your server to prevent unauthorized database resets.";
    echo "</p>";
    echo "</div>";

} catch(PDOException $e) {
    echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ffcdd2; background-color: #ffebee; border-radius: 8px; text-align: center; color: #c62828;'>";
    echo "<h2>Installation Failed</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
$conn = null;
?>
