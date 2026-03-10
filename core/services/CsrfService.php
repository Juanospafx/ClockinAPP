<?php
declare(strict_types=1);

class CsrfService {
    private const TOKEN_KEY = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-Token';

    public static function generateToken(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    public static function validateRequest(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? '';
        if ($sessionToken === '') {
            return false;
        }

        // Check header first, then POST body
        $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        return hash_equals($sessionToken, $requestToken);
    }

    public static function regenerateToken(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::TOKEN_KEY];
    }
}
