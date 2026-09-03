<?php
require_once __DIR__ . '/../config/config.php';

try {
    $email = 'admin1@wonwan';
    $password = '123456';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE users SET password_hash = ?, role = 'super_admin' WHERE email = ?")->execute([$hash, $email]);
        echo "Admin user updated. Password: $password\n";
    } else {
        $pdo->prepare("INSERT INTO users(email, password_hash, display_name, role) VALUES(?, ?, 'Admin Wonwan', 'super_admin')")->execute([$email, $hash]);
        echo "Admin user created. Password: $password\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
