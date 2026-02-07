<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../uploads/php_errors.log');

require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? null;
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

if (!$userId || $latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID, latitude, and longitude are required.']);
    exit();
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'INSERT INTO location_history (user_id, latitude, longitude, timestamp) VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$userId, $latitude, $longitude]);

    echo json_encode(['success' => true, 'message' => 'Location logged successfully.']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Database error in LogLocation.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>
