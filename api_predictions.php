<?php
// api_predictions.php
// Public API for Prediction & Giveaway feature
require_once __DIR__.'/config.php';
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

function normalizeBanglaDigits($value) {
    return strtr($value, [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'
    ]);
}

function ensurePredictionSchema(PDO $pdo) {
    try {
        $hasScoreA = $pdo->query("SHOW COLUMNS FROM prediction_entries LIKE 'predicted_score_a'")->fetch();
        if (!$hasScoreA) {
            $pdo->exec("ALTER TABLE prediction_entries ADD COLUMN predicted_score_a INT NULL");
        }
        $hasScoreB = $pdo->query("SHOW COLUMNS FROM prediction_entries LIKE 'predicted_score_b'")->fetch();
        if (!$hasScoreB) {
            $pdo->exec("ALTER TABLE prediction_entries ADD COLUMN predicted_score_b INT NULL");
        }
        $predTeam = $pdo->query("SHOW COLUMNS FROM prediction_entries LIKE 'predicted_team'")->fetch();
        if ($predTeam && stripos($predTeam['Type'], 'enum(') === 0) {
            $pdo->exec("ALTER TABLE prediction_entries MODIFY predicted_team VARCHAR(20) NULL");
        }
    } catch (PDOException $e) {
        // Ignore schema update failures in environments without permissions.
    }
}

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

ensurePredictionSchema($pdo);

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
    $required = ['campaign_id','user_name','user_phone','predicted_team','predicted_score_a','predicted_score_b','has_shared'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing field: $field"]);
            exit;
        }
    }
    $data['user_phone'] = normalizeBanglaDigits($data['user_phone']);
    $data['predicted_score_a'] = is_numeric($data['predicted_score_a']) ? (int)$data['predicted_score_a'] : null;
    $data['predicted_score_b'] = is_numeric($data['predicted_score_b']) ? (int)$data['predicted_score_b'] : null;
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
    $stmt = $pdo->prepare("INSERT INTO prediction_entries (campaign_id, user_name, user_phone, predicted_team, predicted_score_a, predicted_score_b, has_shared) VALUES (:campaign_id, :user_name, :user_phone, :predicted_team, :predicted_score_a, :predicted_score_b, :has_shared)");
    $stmt->execute([
        ':campaign_id'   => $data['campaign_id'],
        ':user_name'     => $data['user_name'],
        ':user_phone'    => $data['user_phone'],
        ':predicted_team'=> $data['predicted_team'],
        ':predicted_score_a' => $data['predicted_score_a'],
        ':predicted_score_b' => $data['predicted_score_b'],
        ':has_shared'    => $data['has_shared'] ? 1 : 0,
    ]);
    echo json_encode(['success' => true, 'message' => 'Prediction recorded']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
