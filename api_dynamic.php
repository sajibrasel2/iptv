<?php
// api_dynamic.php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "livetv_db";

$response = [
    'channels' => [],
    'ads' => []
];

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch channels
    $stmt = $conn->query("SELECT id, display_name, target_url FROM custom_channels ORDER BY id DESC");
    $response['channels'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch ads
    $stmt = $conn->query("SELECT id, ad_name, ad_url FROM ad_settings WHERE ad_url != ''");
    $response['ads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($response);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => "Connection failed: " . $e->getMessage()]);
}
$conn = null;
?>
