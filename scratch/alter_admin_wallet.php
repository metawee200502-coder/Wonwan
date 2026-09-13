<?php
require __DIR__ . '/../config/config.php';
try {
    $pdo->exec("ALTER TABLE system_bank_accounts ADD COLUMN is_default tinyint(1) NOT NULL DEFAULT 0");
    echo "Added is_default";
} catch(Exception $e) {
    echo $e->getMessage();
}
