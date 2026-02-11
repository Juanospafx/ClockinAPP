<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/ApiKeyService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';

function handle_maps_key(): void {
    require_role(['admin', 'special', 'user']);
    $key = ApiKeyService::getGoogleMapsKey();
    if (!$key) {
        json_error('not_found', 'API key not found or empty.', 404);
        return;
    }
    json_ok(['apiKey' => $key]);
}
