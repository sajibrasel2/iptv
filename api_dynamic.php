<?php
// api_dynamic.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

require_once 'config.php';

$response = [
    'channels' => [],
    'ads' => []
];

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=$charset", $username, $password);
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
