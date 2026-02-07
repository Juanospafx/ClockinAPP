<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../uploads/php_errors.log');

require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query('SELECT id, name, created_at FROM projects ORDER BY created_at DESC');
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'projects' => $projects]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? null;

        if ($action === 'create') {
            $name = $data['name'] ?? null;
            if (!$name) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Project name is required.']);
                exit();
            }
            try {
                $stmt = $pdo->prepare('INSERT INTO projects (name) VALUES (?)');
                $stmt->execute([$name]);
                echo json_encode(['success' => true, 'message' => 'Project created successfully.', 'id' => $pdo->lastInsertId()]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } elseif ($action === 'delete') {
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Project ID is required.']);
                exit();
            }
            try {
                $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ?');
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Project deleted successfully.']);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        break;
}
?>