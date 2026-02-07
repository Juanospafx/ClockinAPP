<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();

// Simple authentication check (replace with proper token validation in production)
function isAuthenticated() {
    // For this example, we'll rely on session storage on the frontend.
    // In a real app, you'd validate a token sent in the Authorization header.
    return true; // Assuming frontend handles basic auth for now
}

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get the latest attendance record for each user
        $stmt = $pdo->prepare('
            SELECT ar.user_id, u.username, ar.location, ar.created_at
            FROM attendance_records ar
            JOIN users u ON ar.user_id = u.id
            WHERE (ar.user_id, ar.created_at) IN (
                SELECT user_id, MAX(created_at)
                FROM attendance_records
                GROUP BY user_id
            )
            ORDER BY ar.created_at DESC
        ');
        $stmt->execute();
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'locations' => $locations]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
}
?>