<?php
require_once __DIR__ . '/../config/config.php';
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN delivered_at DATETIME DEFAULT NULL AFTER shipped_at");
    echo "Column delivered_at added.";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
