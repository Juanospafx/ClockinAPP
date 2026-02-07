<?php
require_once __DIR__ . '/config.php';

function getPDO() {
    static $pdo;
    if ($pdo === null) {
        $host = DB_HOST;
        $port = defined('DB_PORT') ? DB_PORT : 3306; // Usa 3306 por defecto si no está definido
        $db   = DB_NAME;
        $user = DB_USER;
        $pass = DB_PASS;
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}
?>
