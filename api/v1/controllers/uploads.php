<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/UploadService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';

function handle_upload_profile(): void {
    require_role(['admin', 'special', 'user']);
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $file = $_FILES['profile_pic'] ?? null;
    if (!$file) {
        json_error('validation_error', 'No file uploaded.', 400);
        return;
    }
    $result = UploadService::uploadProfilePic($userId, $file);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}
