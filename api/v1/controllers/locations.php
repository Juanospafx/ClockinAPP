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

    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $startDate = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : null;
    $endDate = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : null;

    $result = LocationService::getHistory($userId, $startDate ?: null, $endDate ?: null);
    json_ok($result['data']);
}

function handle_locations_latest(): void {
    require_role(['admin', 'special']);
    $result = LocationService::getLatestForUsers();
    json_ok($result['data']);
}
