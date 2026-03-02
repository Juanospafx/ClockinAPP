<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/services/ManualAttendanceService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';
require_once __DIR__ . '/../../../core/services/AuthService.php';

function handle_manual_attendance_create(): void
{
    $currentUserId = require_login();
    $currentUserRole = AuthService::getCurrentUserRole() ?? 'user';

    $data = read_json_body();
    $isBulk = isset($data['user_ids']) && is_array($data['user_ids']) && count($data['user_ids']) > 0;

    $result = $isBulk
        ? ManualAttendanceService::createManualBulk($currentUserId, $currentUserRole, $data)
        : ManualAttendanceService::createManual($currentUserId, $currentUserRole, $data);

    if (isset($result['warning']) && $result['warning']) {
        // Return as a special warning (not a hard error) so frontend can ask for confirmation
        http_response_code(200);
        echo json_encode([
            'ok' => false,
            'warning' => true,
            'error' => $result['error'],
        ]);
        return;
    }

    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }

    json_ok($result['data']);
}
