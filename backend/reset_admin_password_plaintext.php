<?php
require_once __DIR__ . '/includes/db.php';

$pdo = getPDO();

$username = 'admin';
$plainPassword = 'admin'; // Set to plain text 'admin'

try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->execute([$plainPassword, $username]);

    if ($stmt->rowCount() > 0) {
        echo "Password for user '{$username}' reset to plain text 'admin' successfully.";
    } else {
        echo "User '{$username}' not found or password already set.";
    }
} catch (PDOException $e) {
    die("Could not update password: " . $e->getMessage());
}
?>