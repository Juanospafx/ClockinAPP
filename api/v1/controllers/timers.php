<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/TimerService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';
require_once __DIR__ . '/../../../core/services/AuthService.php';

function handle_timers_active(): void {
    $role = require_role(['admin', 'special', 'user']);
    $userId = AuthService::getCurrentUserId() ?? 0;
    $data = TimerService::getActiveTimers($role, $userId);
    json_ok($data);
}

function handle_timers_me(): void {
    require_role(['user', 'special']);
    $userId = AuthService::getCurrentUserId();
    if (!$userId) {
        json_error('unauthorized', 'Unauthorized', 401);
        return;
    }
    $data = TimerService::getMyTimer($userId);
    json_ok($data);
}

function handle_timers_update(int $timerId): void {
    $currentUserId = require_login();
    $currentRole = require_role(['admin', 'special']);
    $data = read_json_body();
    $requestedStatus = (string)($data['status'] ?? '');
    $location = trim((string)($data['location'] ?? 'Manual Action'));
    $clientTimeIso = (string)($data['client_time_iso'] ?? '');

    if (!$timerId || $requestedStatus === '' || $clientTimeIso === '') {
        json_error('validation_error', 'Missing timer ID, status, or client_time_iso.', 400);
        return;
    }

    // Special users can only control timers of 'user' role employees
    if ($currentRole === 'special') {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            "SELECT r.name AS role FROM attendance_records ar JOIN users u ON u.id = ar.user_id JOIN roles r ON r.id = u.role_id WHERE ar.id = ? LIMIT 1"
        );
        $stmt->execute([$timerId]);
        $timerOwnerRole = $stmt->fetchColumn();
        if ($timerOwnerRole !== 'user') {
            json_error('forbidden', 'You can only manage timers of regular users.', 403);
            return;
        }
    }

    $result = TimerService::updateTimerStatus($timerId, $requestedStatus, $location, $clientTimeIso);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}
