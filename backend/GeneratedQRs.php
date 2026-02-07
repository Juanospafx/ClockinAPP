<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../uploads/php_errors.log');

require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare(
            'SELECT pq.id, pq.action_type, pq.location, pq.qr_content, pq.created_at, p.name as project_name 
             FROM project_qrs pq 
             JOIN projects p ON pq.project_id = p.id 
             ORDER BY pq.created_at DESC'
        );
        $stmt->execute();
        $qrs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'qrs' => $qrs]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get the raw POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $qr_id = $data['id'] ?? null;

    if (!$qr_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'QR ID is required.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM project_qrs WHERE id = :id');
        $stmt->bindParam(':id', $qr_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'QR deleted successfully.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'QR not found or already deleted.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
}
?>