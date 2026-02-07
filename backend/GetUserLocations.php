<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../uploads/php_errors.log');

require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$pdo = getPDO();

// Si se solicita el historial de un usuario específico
if (isset($_GET['user_id']) && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $userId = $_GET['user_id'];
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];

    try {
        $stmt = $pdo->prepare(
            'SELECT latitude, longitude, timestamp FROM location_history '
            . 'WHERE user_id = ? AND timestamp BETWEEN ? AND ? ORDER BY timestamp ASC'
        );
        $stmt->execute([$userId, $startDate, $endDate]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'history' => $history]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Database error in GetUserLocations.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
} else {
    // Si no, obtener la última ubicación de todos los usuarios activos
    try {
        // Subconsulta para encontrar el ID del último registro de cada usuario
        $subQuery = 'SELECT MAX(id) as max_id FROM location_history GROUP BY user_id';
        
        // Consulta principal para obtener los detalles de esos últimos registros
        $stmt = $pdo->prepare(
            'SELECT u.id, u.username, lh.latitude, lh.longitude, lh.timestamp ' 
            . 'FROM location_history lh ' 
            . 'JOIN users u ON lh.user_id = u.id ' 
            . 'WHERE lh.id IN (' . $subQuery . ')'
        );
        $stmt->execute();
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'locations' => $locations]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Database error in GetUserLocations.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
}
?>
