<?php
require __DIR__ . '/../config/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `system_bank_accounts` (
            `account_id` int NOT NULL AUTO_INCREMENT,
            `bank_name` varchar(100) NOT NULL,
            `account_name` varchar(100) NOT NULL,
            `account_number` varchar(50) NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`account_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `system_withdrawals` (
            `withdrawal_id` int NOT NULL AUTO_INCREMENT,
            `amount` decimal(12,2) NOT NULL,
            `bank_account_info` text NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`withdrawal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Migration successful.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
