<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

class UploadService {
    public static function uploadProfilePic(int $userId, array $file): array {
        if (!$userId) {
            return ['error' => ['code' => 'validation_error', 'message' => 'User ID is required.'], 'status' => 400];
        }
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => ['code' => 'validation_error', 'message' => 'No file uploaded or upload error.'], 'status' => 400];
        }

        $fileName = $file['name'] ?? '';
        $fileTmpPath = $file['tmp_name'] ?? '';
        $fileNameCmps = explode('.', $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedExtensions = ['jpg', 'gif', 'png', 'jpeg'];
        if (!in_array($fileExtension, $allowedExtensions, true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid file type.'], 'status' => 400];
        }

        $newFileName = md5((string)time() . $fileName) . '.' . $fileExtension;
        $uploadFileDir = APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0775, true);
        }
        $destPath = $uploadFileDir . $newFileName;

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            return ['error' => ['code' => 'server_error', 'message' => 'There was an error moving the uploaded file.'], 'status' => 500];
        }

        $profilePicUrl = 'uploads/users/' . $newFileName;
        $pdo = get_pdo();
        $stmt = $pdo->prepare('UPDATE users SET profile_pic_url = ? WHERE id = ?');
        $stmt->execute([$profilePicUrl, $userId]);

        return ['data' => ['message' => 'File uploaded and profile updated successfully.', 'url' => $profilePicUrl]];
    }
}
