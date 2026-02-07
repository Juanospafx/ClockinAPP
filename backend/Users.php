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
            $stmt = $pdo->query('
                SELECT u.id, u.username, u.role_id, r.name AS role, u.profile_pic_url
                FROM users u
                JOIN roles r ON u.role_id = r.id
            ');
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'users' => $users]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? null;

        if ($action === 'create') {
            $username = $data['username'] ?? null;
            $password = $data['password'] ?? null;
            $role = $data['role'] ?? null;      // nombre del rol opcional
            $role_id = $data['role_id'] ?? null; // o id directo

            if (!$username || !$password) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
                exit();
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Determinar role_id
            if ($role_id === null && $role !== null) {
                $stmtRole = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
                $stmtRole->execute([$role]);
                $role_id = $stmtRole->fetchColumn();
            }
            if ($role_id === null) {
                $role_id = 2; // por defecto user
            }

            try {
                $stmt = $pdo->prepare('INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)');
                $stmt->execute([$username, $hashedPassword, $role_id]);
                echo json_encode(['success' => true, 'message' => 'User created successfully.']);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } elseif ($action === 'update') {
            $id = $data['id'] ?? null;
            $username = $data['username'] ?? null;
            $password = $data['password'] ?? null;
            $role = $data['role'] ?? null;
            $role_id = $data['role_id'] ?? null;

            if (!$id || (!$username && !$password && !$role && !$role_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing ID or data to update.']);
                exit();
            }

            $sql = 'UPDATE users SET';
            $params = [];
            $updates = [];

            if ($username) {
                $updates[] = 'username = ?';
                $params[] = $username;
            }
            if ($password) {
                $updates[] = 'password = ?';
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            if ($role_id !== null) {
                $updates[] = 'role_id = ?';
                $params[] = $role_id;
            } elseif ($role) {
                $stmtRole = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
                $stmtRole->execute([$role]);
                $foundRoleId = $stmtRole->fetchColumn();
                if ($foundRoleId) {
                    $updates[] = 'role_id = ?';
                    $params[] = $foundRoleId;
                }
            }

            if (!$updates) {
                echo json_encode(['success' => true, 'message' => 'No changes to apply.']);
                exit();
            }

            $sql .= ' ' . implode(', ', $updates) . ' WHERE id = ?';
            $params[] = $id;

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } elseif ($action === 'delete') {
            $id = $data['id'] ?? null;

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing user ID.']);
                exit();
            }

            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
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
