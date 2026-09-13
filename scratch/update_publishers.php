<?php
require_once __DIR__ . '/../config/config.php';
try {
    $pdo->exec("ALTER TABLE publishers ADD COLUMN bank_name VARCHAR(100) DEFAULT NULL AFTER status");
    $pdo->exec("ALTER TABLE publishers ADD COLUMN bank_account_name VARCHAR(255) DEFAULT NULL AFTER bank_name");
    $pdo->exec("ALTER TABLE publishers ADD COLUMN bank_account_number VARCHAR(50) DEFAULT NULL AFTER bank_account_name");
    echo "Columns added to publishers.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
