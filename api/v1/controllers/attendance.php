<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/AttendanceService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';
require_once __DIR__ . '/../../../core/services/AuthService.php';

function handle_attendance_list(): void {
    $currentUserId = require_login();
    $currentUserRole = AuthService::getCurrentUserRole() ?? 'user';

    if (isset($_GET['summary'])) {
        $userId = null;
        if ($currentUserRole !== 'admin') {
            $userId = $currentUserId;
        } elseif (isset($_GET['user_id'])) {
            $userId = (int)$_GET['user_id'];
        }
        $summary = AttendanceService::getSummary($userId);
        json_ok(['summary' => $summary]);
        return;
    }

    if (isset($_GET['dashboard'])) {
        if ($currentUserRole !== 'admin') {
            json_error('forbidden', 'Only administrators can view dashboard metrics.', 403);
            return;
        }
        $metrics = AttendanceService::getDashboardMetrics();
        json_ok(['metrics' => $metrics]);
        return;
    }

    $queryUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $limitParam = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : null;
    $records = [];

    if ($currentUserRole === 'admin') {
        if ($queryUserId) {
            $records = AttendanceService::fetchRecords($queryUserId, $limitParam);
        } elseif (isset($_GET['all'])) {
            $records = AttendanceService::fetchRecords(null, $limitParam);
        } else {
            $records = AttendanceService::fetchRecords($currentUserId, $limitParam);
        }
    } elseif ($currentUserRole === 'special') {
        $targetUserId = $queryUserId ?: $currentUserId;
        $limit = $limitParam;
        if ($targetUserId !== $currentUserId) {
            $limit = $limit ? min($limit, 15) : 15;
        }
        $records = AttendanceService::fetchRecords($targetUserId, $limit);
    } else {
        $records = AttendanceService::fetchRecords($currentUserId, null);
    }

    json_ok(['records' => $records]);
}

function handle_attendance_create(): void {
    $currentUserId = require_login();
    $currentUserRole = AuthService::getCurrentUserRole() ?? 'user';
    $data = read_json_body();
    $result = AttendanceService::createRecord($currentUserId, $currentUserRole, $data);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

function handle_attendance_update(int $recordId): void {
    require_login();
    $currentUserRole = AuthService::getCurrentUserRole() ?? 'user';
    $data = read_json_body();
    $data['id'] = $recordId;
    $result = AttendanceService::updateRecord($currentUserRole, $data);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

function handle_attendance_delete(int $recordId): void {
    require_login();
    $currentUserRole = AuthService::getCurrentUserRole() ?? 'user';
    $result = AttendanceService::deleteRecord($currentUserRole, ['id' => $recordId]);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

function handle_attendance_recalculate(): void {
    require_login();
    $currentUserRole = AuthService::getCurrentUserRole() ?? 'user';
    if ($currentUserRole !== 'admin') {
        json_error('forbidden', 'Only administrators can recalculate daily duration.', 403);
        return;
    }
    $data = read_json_body();
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $date = $data['date'] ?? '';
    if (!$userId || $date === '') {
        json_error('validation_error', 'Missing user_id or date.', 400);
        return;
    }
    $totalDuration = AttendanceService::recalcDailyDuration($userId, $date);
    json_ok(['message' => 'Duraci??n diaria recalculada y actualizada.', 'total_duration' => $totalDuration]);
}
