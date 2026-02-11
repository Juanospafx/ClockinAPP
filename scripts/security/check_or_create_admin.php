<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$pdo = get_pdo();

try {
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "Admin user found. ID: " . $user['id'] . ", Username: " . $user['username'] . ", Role: " . $user['role'] . "\n";
        echo "Password (first 10 chars): " . substr($user['password'], 0, 10) . "...\n";
    } else {
        echo "Admin user not found. Creating new admin user...\n";
        $plainPassword = 'admin';
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute(['admin', $hashedPassword, 'admin']);
        echo "New admin user created with password 'admin' (hashed).\n";
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
