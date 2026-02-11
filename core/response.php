<?php
declare(strict_types=1);

function json_ok(array $data = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode([
        'ok' => true,
        'data' => $data,
    ]);
}

function json_error(string $code, string $message, int $status = 400, $details = null): void {
    http_response_code($status);
    $error = [
        'code' => $code,
        'message' => $message,
    ];
    if ($details !== null) {
        $error['details'] = $details;
    }
    echo json_encode([
        'ok' => false,
        'error' => $error,
    ]);
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
