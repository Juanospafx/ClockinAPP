<?php
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();

// Get all users
$stmt = $pdo->query("SELECT id, password FROM users");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    // Hash the password
    $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);

    // Update the user's password
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->execute([$hashedPassword, $user['id']]);
}

echo "Passwords hashed successfully.";
?>