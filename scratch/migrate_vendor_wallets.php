<?php
require_once __DIR__ . '/../config/config.php';

$queries = [
    // 1. Drop old tables if they exist
    "DROP TABLE IF EXISTS `wallet_transactions`",
    "DROP TABLE IF EXISTS `shop_wallets`",

    // 2. Create vendor_wallets table
    "CREATE TABLE IF NOT EXISTS `vendor_wallets` (
      `wallet_id` INT NOT NULL AUTO_INCREMENT,
      `seller_type` ENUM('shop', 'publisher') NOT NULL,
      `seller_id` INT NOT NULL,
      `available_balance` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
      `pending_withdrawal_balance` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
      `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`wallet_id`),
      UNIQUE KEY `uq_seller` (`seller_type`, `seller_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 3. Create wallet_transactions table
    "CREATE TABLE IF NOT EXISTS `wallet_transactions` (
      `transaction_id` INT NOT NULL AUTO_INCREMENT,
      `wallet_id` INT NOT NULL,
      `order_id` INT DEFAULT NULL,
      `type` ENUM('EARNING', 'WITHDRAWAL') NOT NULL,
      `amount` DECIMAL(12,2) NOT NULL,
      `status` ENUM('SUCCESS', 'PENDING', 'FAILED', 'REJECTED') NOT NULL DEFAULT 'SUCCESS',
      `bank_account_info` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`transaction_id`),
      CONSTRAINT `fk_txn_vendor_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `vendor_wallets` (`wallet_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

echo "<h3>Vendor Wallets Migration</h3><ul>";
foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "<li><strong style='color:green'>Success:</strong> " . htmlspecialchars(substr($sql, 0, 80)) . "...</li>";
    } catch (PDOException $e) {
        echo "<li><strong style='color:red'>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br><em>" . htmlspecialchars(substr($sql, 0, 80)) . "...</em></li>";
    }
}
echo "</ul><p>Migration completed.</p>";
