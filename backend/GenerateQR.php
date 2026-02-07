<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../uploads/php_errors.log');

require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$projectId = $data['project_id'] ?? null;

if (!$projectId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project ID is required.']);
    exit();
}

$initialPayload = json_encode([
    'project_id' => (int)$projectId
]);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO project_qrs (project_id, qr_content) VALUES (?, ?)');
    $stmt->execute([$projectId, $initialPayload]);
    $projectQrId = (int)$pdo->lastInsertId();

    $qrPayload = [
        'project_id' => (int)$projectId,
        'project_qr_id' => $projectQrId
    ];
    $qrContent = json_encode($qrPayload);

    $update = $pdo->prepare('UPDATE project_qrs SET qr_content = ? WHERE id = ?');
    $update->execute([$qrContent, $projectQrId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'qr_content' => $qrContent, 'project_qr_id' => $projectQrId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
