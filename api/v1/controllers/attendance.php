<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/AttendanceService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';
require_once __DIR__ . '/../../../core/services/AuthService.php';

function build_attendance_filters_from_query(): array {
    $currentUserId = require_login();
    $currentUserRole = AuthService::getCurrentUserRole() ?? 'user';
    $allUsersRequested = ($_GET['user_id'] ?? '') === 'all' || isset($_GET['all']);
    $queryUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' && $_GET['user_id'] !== 'all'
        ? (int)$_GET['user_id']
        : null;

    if ($currentUserRole === 'admin') {
        $userId = $queryUserId ?: ($allUsersRequested ? null : $currentUserId);
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : null;
    } elseif ($currentUserRole === 'special') {
        $userId = $queryUserId ?: $currentUserId;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : null;
        if ($userId !== $currentUserId) {
            $limit = $limit ? min($limit, 15) : 15;
        }
    } else {
        $userId = $currentUserId;
        $limit = null;
    }

    $fromDate = trim((string)($_GET['from'] ?? $_GET['date_from'] ?? ''));
    $toDate = trim((string)($_GET['to'] ?? $_GET['date_to'] ?? ''));
    foreach ([$fromDate, $toDate] as $date) {
        $parsedDate = $date !== '' ? DateTime::createFromFormat('!Y-m-d', $date) : null;
        if ($date !== '' && (!$parsedDate || $parsedDate->format('Y-m-d') !== $date)) {
            json_error('validation_error', 'Dates must use the YYYY-MM-DD format.', 400);
            exit;
        }
    }
    if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
        json_error('validation_error', 'From Date cannot be greater than To Date.', 400);
        exit;
    }

    return [
        'user_id' => $userId,
        'limit' => $limit,
        'from' => $fromDate !== '' ? $fromDate : null,
        'to' => $toDate !== '' ? $toDate : null,
        'search' => trim((string)($_GET['search'] ?? '')),
        'view_mode' => in_array($_GET['view_mode'] ?? '', ['day', 'week'], true) ? $_GET['view_mode'] : null,
        'focus_date' => trim((string)($_GET['focus_date'] ?? '')),
    ];
}

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

    $filters = build_attendance_filters_from_query();
    $records = AttendanceService::fetchRecords(
        $filters['user_id'],
        $filters['limit'],
        $filters['from'],
        $filters['to'],
        $filters['search']
    );

    json_ok(['records' => $records]);
}

function handle_attendance_export(string $format): void {
    $filters = build_attendance_filters_from_query();
    if ((AuthService::getCurrentUserRole() ?? 'user') === 'admin') {
        $filters['limit'] = null;
    }
    $report = AttendanceService::exportReport($format, $filters);

    $contentTypes = [
        'csv' => 'text/csv; charset=UTF-8',
        'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
    ];
    header('Content-Type: ' . $contentTypes[$format]);
    header('Content-Disposition: attachment; filename="' . $report['filename'] . '"');
    header('Content-Length: ' . strlen($report['content']));
    echo $report['content'];
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
