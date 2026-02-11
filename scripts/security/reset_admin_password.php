<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$pdo = get_pdo();

$username = 'admin';
$plainPassword = 'password'; // Set a known plain-text password
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->execute([$hashedPassword, $username]);

    if ($stmt->rowCount() > 0) {
        echo "Password for user '{$username}' reset and hashed successfully.";
    } else {
        echo "User '{$username}' not found or password already set.";
    }
} catch (PDOException $e) {
    die("Could not update password: " . $e->getMessage());
}
?>
