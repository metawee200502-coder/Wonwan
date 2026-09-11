<?php
require_once __DIR__ . '/config/config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) DEFAULT 0;");
    $pdo->exec("ALTER TABLE users ADD COLUMN ban_until DATETIME DEFAULT NULL;");
    $pdo->exec("ALTER TABLE users ADD COLUMN ban_reason TEXT DEFAULT NULL;");
    echo "Columns added successfully.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
