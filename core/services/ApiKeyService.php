<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class ApiKeyService {
    public static function getGoogleMapsKey(): ?string {
        return $_ENV['GOOGLE_MAPS_API_KEY'] ?? $_SERVER['GOOGLE_MAPS_API_KEY'] ?? null;
    }
}
