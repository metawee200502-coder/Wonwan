<?php
require_once __DIR__ . '/../config/config.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS `disputes` (
      `dispute_id` int NOT NULL AUTO_INCREMENT,
      `order_id` int NOT NULL,
      `user_id` int NOT NULL,
      `reason` varchar(500) NOT NULL,
      `proof_image` varchar(500) DEFAULT NULL,
      `status` enum('open', 'resolved', 'closed') NOT NULL DEFAULT 'open',
      `resolution` enum('refunded', 'rejected') DEFAULT NULL,
      `admin_note` text,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`dispute_id`),
      KEY `fk_dispute_order` (`order_id`),
      KEY `fk_dispute_user` (`user_id`),
      CONSTRAINT `fk_dispute_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_dispute_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "ALTER TABLE `orders` ADD COLUMN `payout_status` ENUM('pending', 'paid_to_seller', 'disputed', 'refunded') NOT NULL DEFAULT 'pending' AFTER `payment_status`",

    "ALTER TABLE `exchanges` ADD COLUMN `qc_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER `status`",

    "ALTER TABLE `exchange_requests` ADD COLUMN `requester_tracking_to_admin` varchar(100) DEFAULT NULL AFTER `status`",
    "ALTER TABLE `exchange_requests` ADD COLUMN `offer_tracking_to_admin` varchar(100) DEFAULT NULL AFTER `requester_tracking_to_admin`",
    "ALTER TABLE `exchange_requests` ADD COLUMN `admin_qc_requester_status` ENUM('pending', 'passed', 'failed') NOT NULL DEFAULT 'pending' AFTER `offer_tracking_to_admin`",
    "ALTER TABLE `exchange_requests` ADD COLUMN `admin_qc_offer_status` ENUM('pending', 'passed', 'failed') NOT NULL DEFAULT 'pending' AFTER `admin_qc_requester_status`",
    "ALTER TABLE `exchange_requests` ADD COLUMN `admin_tracking_to_requester` varchar(100) DEFAULT NULL AFTER `admin_qc_offer_status`",
    "ALTER TABLE `exchange_requests` ADD COLUMN `admin_tracking_to_offer` varchar(100) DEFAULT NULL AFTER `admin_tracking_to_requester`",

    "UPDATE `exchanges` SET `qc_status` = 'approved'"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "Success: " . substr($sql, 0, 50) . "...\n";
    } catch (PDOException $e) {
        // Ignore duplicate column errors (1060)
        if (strpos($e->getMessage(), '1060') !== false) {
            echo "Skipped (already exists): " . substr($sql, 0, 50) . "...\n";
        } else {
            echo "Error on: " . substr($sql, 0, 50) . "...\n";
            echo "Message: " . $e->getMessage() . "\n";
        }
    }
}
