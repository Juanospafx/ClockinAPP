<?php
ob_start(); // Start output buffering
error_log("TEST: Login.php started execution in new log directory.");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../uploads/php_errors.log');

require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';

$pdo = getPDO();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$user = $data['user'] ?? '';
$pass = $data['pass'] ?? '';

error_log("Login attempt for user: " . $user);

if (empty($user) || empty($pass)) {
    error_log("Login failed: Missing username or password.");
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit();
}

try {
    // Traer el rol desde la tabla roles
    $stmt = $pdo->prepare('
        SELECT u.id, u.username, u.password, r.name AS role, u.profile_pic_url
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.username = ?
    ');
    $stmt->execute([$user]);
    $row = $stmt->fetch();

    if ($row) {
        error_log("User found: " . $user);
        if (password_verify($pass, $row['password'])) {
            error_log("Password verified for user: " . $user);
            $session->login($row['id']);
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'id' => $row['id'],
                'username' => $row['username'],
                'role' => $row['role'], // ahora viene de la tabla roles
                'profile_pic_url' => $row['profile_pic_url'] ?? ''
            ]);
        } else {
            error_log("Login failed: Incorrect password for user: " . $user);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Incorrect username or password.']);
        }
    } else {
        error_log("Login failed: User not found: " . $user);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Incorrect username or password.']);
    }
} catch (PDOException $e) {
    error_log("Login PDOException: " . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
ob_end_flush(); // End output buffering and send output
?>
