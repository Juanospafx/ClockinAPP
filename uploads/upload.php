<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/QR-app/uploads/php_errors.log');

set_exception_handler(function ($exception) {
    error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    exit();
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    error_log("PHP error: [" . $severity . "] " . $message . " in " . $file . " on line " . $line);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    exit();
});

require_once __DIR__ . '/../backend/includes/cors.php';
require_once __DIR__ . '/../backend/includes/db.php';

$pdo = getPDO();

header('Content-Type: application/json');

// Simple authentication check (replace with proper token validation in production)
function isAuthenticated() {
    // In a real app, you'd validate a token sent in the Authorization header.
    return true; // Assuming frontend handles basic auth for now
}

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? null;

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required.']);
        exit();
    }

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
        $fileName = $_FILES['profile_pic']['name'];
        $fileSize = $_FILES['profile_pic']['size'];
        $fileType = $_FILES['profile_pic']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $uploadFileDir = __DIR__ . '/users/';
        $dest_path = $uploadFileDir . $newFileName;

        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');

        if (in_array($fileExtension, $allowedfileExtensions)) {
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $profilePicUrl = 'uploads/users/' . $newFileName; // Path relative to web root

                try {
                    $stmt = $pdo->prepare('UPDATE users SET profile_pic_url = ? WHERE id = ?');
                    $stmt->execute([$profilePicUrl, $userId]);

                    echo json_encode(['success' => true, 'message' => 'File uploaded and profile updated successfully.', 'url' => $profilePicUrl]);
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Database error updating profile: ' . $e->getMessage()]);
                }
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'There was an error moving the uploaded file.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedfileExtensions)]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
}
?>
