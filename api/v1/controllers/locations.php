<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/LocationService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';

function handle_locations_log(): void {
    require_role(['admin', 'special', 'user']);
    $data = read_json_body();
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $result = LocationService::logLocation($userId, $latitude, $longitude);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

function handle_locations_history(): void {
    require_role(['admin', 'special']);
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    if (!$userId || $startDate === '' || $endDate === '') {
        json_error('validation_error', 'Missing user_id, start_date, or end_date.', 400);
        return;
    }
    $result = LocationService::getHistory($userId, $startDate, $endDate);
    json_ok($result['data']);
}

function handle_locations_latest(): void {
    require_role(['admin', 'special']);
    $result = LocationService::getLatestForUsers();
    json_ok($result['data']);
}
