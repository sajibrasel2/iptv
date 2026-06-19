<?php
// setup_prediction_db.php
// Run this script once to create the required tables for the Prediction & Giveaway feature.

require_once __DIR__.'/config.php'; // PDO connection variables: $servername, $username, $password, $dbname, $charset

$dsn = "mysql:host=$servername;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Create prediction_campaigns table
$pdo->exec("CREATE TABLE IF NOT EXISTS prediction_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    team_a VARCHAR(100) NOT NULL,
    team_b VARCHAR(100) NOT NULL,
    match_time DATETIME NOT NULL,
    prize_image_url VARCHAR(255) NULL,
    fb_post_link VARCHAR(255) NULL,
    status ENUM('active','closed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Create prediction_entries table
$pdo->exec("CREATE TABLE IF NOT EXISTS prediction_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    user_name VARCHAR(150) NOT NULL,
    user_phone VARCHAR(30) NOT NULL,
    predicted_team ENUM('team_a','team_b') NOT NULL,
    has_shared BOOLEAN NOT NULL,
    is_winner BOOLEAN NOT NULL DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES prediction_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

echo "Database tables for Prediction & Giveaway have been created successfully.\n";
?>
