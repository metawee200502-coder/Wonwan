<?php
require_once __DIR__ . '/../config/config.php';

$queries = [
    // 1. เพิ่มคอลัมน์ที่จำเป็นสำหรับ Escrow ในตาราง orders
    "ALTER TABLE `orders` ADD COLUMN `status` ENUM('PENDING_PAYMENT', 'PAID', 'SHIPPED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PENDING_PAYMENT'",
    "ALTER TABLE `orders` ADD COLUMN `slip_image_url` VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `orders` ADD COLUMN `tracking_number` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `orders` ADD COLUMN `gp_amount` DECIMAL(10,2) DEFAULT '0.00'",
    "ALTER TABLE `orders` ADD COLUMN `net_payout` DECIMAL(10,2) DEFAULT '0.00'",
    "ALTER TABLE `orders` ADD COLUMN `shipping_fee` DECIMAL(10,2) DEFAULT '0.00'",
    "ALTER TABLE `orders` ADD COLUMN `product_subtotal` DECIMAL(10,2) DEFAULT '0.00'",

    // 2. สร้างตาราง shop_wallets
    "CREATE TABLE IF NOT EXISTS `shop_wallets` (
      `wallet_id` INT NOT NULL AUTO_INCREMENT,
      `shop_id` INT NOT NULL,
      `available_balance` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
      `pending_balance` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
      `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`wallet_id`),
      UNIQUE KEY `uq_shop_id` (`shop_id`),
      CONSTRAINT `fk_wallet_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`shop_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 3. สร้างตาราง wallet_transactions
    "CREATE TABLE IF NOT EXISTS `wallet_transactions` (
      `transaction_id` INT NOT NULL AUTO_INCREMENT,
      `wallet_id` INT NOT NULL,
      `order_id` INT DEFAULT NULL,
      `type` ENUM('EARNING', 'WITHDRAW') NOT NULL,
      `amount` DECIMAL(12,2) NOT NULL,
      `status` ENUM('SUCCESS', 'PENDING', 'FAILED') NOT NULL DEFAULT 'PENDING',
      `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`transaction_id`),
      CONSTRAINT `fk_txn_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `shop_wallets` (`wallet_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

echo "<h3>Escrow & Wallet Migration</h3><ul>";
foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "<li><strong style='color:green'>Success:</strong> " . htmlspecialchars(substr($sql, 0, 80)) . "...</li>";
    } catch (PDOException $e) {
        // Ignore duplicate column errors (1060)
        if (strpos($e->getMessage(), '1060') !== false) {
            echo "<li><strong style='color:orange'>Skipped (Column already exists):</strong> " . htmlspecialchars(substr($sql, 0, 80)) . "...</li>";
        } else {
            echo "<li><strong style='color:red'>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br><em>" . htmlspecialchars(substr($sql, 0, 80)) . "...</em></li>";
        }
    }
}
echo "</ul><p>Migration completed.</p>";
