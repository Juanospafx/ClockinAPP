<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/QrService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';

function handle_qrs_list(): void {
    require_role(['admin']);
    $qrs = QrService::listQrs();
    json_ok(['qrs' => $qrs]);
}

function handle_qrs_create(): void {
    require_role(['admin']);
    $data = read_json_body();
    $result = QrService::createQr($data);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

function handle_qrs_delete(int $qrId): void {
    require_role(['admin']);
    $result = QrService::deleteQr($qrId);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}
