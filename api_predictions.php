<?php
// api_predictions.php
// Public API for Prediction & Giveaway feature
require_once __DIR__.'/config.php';
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

// Create PDO connection
$dsn = "mysql:host=$servername;dbname=$dbname;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($method === 'GET') {
    // Return the currently active campaign
    $stmt = $pdo->prepare("SELECT * FROM prediction_campaigns WHERE status='active' LIMIT 1");
    $stmt->execute();
    $campaign = $stmt->fetch();
    echo json_encode($campaign ?? []);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $required = ['campaign_id','user_name','user_phone','predicted_team','has_shared'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing field: $field"]);
            exit;
        }
    }
    // Prevent duplicate submissions for the same campaign and phone number
    $duplicateStmt = $pdo->prepare("SELECT COUNT(*) AS count FROM prediction_entries WHERE campaign_id = :campaign_id AND user_phone = :user_phone");
    $duplicateStmt->execute([
        ':campaign_id' => $data['campaign_id'],
        ':user_phone'  => $data['user_phone'],
    ]);
    $duplicateCount = $duplicateStmt->fetchColumn();
    if ($duplicateCount > 0) {
        http_response_code(400);
        echo json_encode([
            'error' => 'duplicate_phone',
            'message' => 'This phone number has already submitted a prediction for this match.'
        ]);
        exit;
    }

    // Basic validation (you can extend this)
    $stmt = $pdo->prepare("INSERT INTO prediction_entries (campaign_id, user_name, user_phone, predicted_team, has_shared) VALUES (:campaign_id, :user_name, :user_phone, :predicted_team, :has_shared)");
    $stmt->execute([
        ':campaign_id'   => $data['campaign_id'],
        ':user_name'     => $data['user_name'],
        ':user_phone'    => $data['user_phone'],
        ':predicted_team'=> $data['predicted_team'],
        ':has_shared'    => $data['has_shared'] ? 1 : 0,
    ]);
    echo json_encode(['success' => true, 'message' => 'Prediction recorded']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
