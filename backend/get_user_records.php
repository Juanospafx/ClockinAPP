<?php
require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

$role = require_authenticated_role(['admin', 'special']);
$pdo = getPDO();

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$limitParam = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : null;

if ($role === 'special' && !$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar un usuario.']);
    exit;
}

$limit = $limitParam;
if ($role === 'special') {
    $limit = $limit ? min($limit, 15) : 15;
}

try {
    $sql = 'SELECT ar.id, ar.user_id, u.username, ar.location, ar.type, ar.original_time, ar.rounded_time,
                   ar.total_duration, ar.lunch_duration, ar.created_at, COALESCE(p.name, "Sin proyecto") AS project_name
            FROM attendance_records ar
            JOIN users u ON u.id = ar.user_id
            LEFT JOIN project_qrs pq ON ar.project_qr_id = pq.id
            LEFT JOIN projects p ON pq.project_id = p.id';
    $params = [];

    if ($userId) {
        $sql .= ' WHERE ar.user_id = ?';
        $params[] = $userId;
    }

    $sql .= ' ORDER BY ar.created_at DESC';

    if ($limit) {
        $sql .= ' LIMIT ' . (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'records' => $records]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
