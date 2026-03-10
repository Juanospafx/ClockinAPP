<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$pdo = get_pdo();
$pdo->exec("ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
$pdo->exec("CREATE INDEX idx_users_deleted_at ON users (deleted_at)");

echo "Column deleted_at added to users table.\n";
