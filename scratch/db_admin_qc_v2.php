<?php
require __DIR__ . '/../config/config.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // Create admin_qc_reports table
    $sql1 = "
    CREATE TABLE IF NOT EXISTS `admin_qc_reports` (
        `report_id` INT AUTO_INCREMENT PRIMARY KEY,
        `request_id` INT NOT NULL,
        `item_owner_id` INT NOT NULL,
        `is_match` TINYINT(1) NOT NULL,
        `admin_notes` TEXT,
        `photo_urls` JSON,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`request_id`) REFERENCES `exchange_requests`(`request_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql1);

    // Alter exchange_requests
    // Check if columns exist first to avoid errors
    $checkSql = "SHOW COLUMNS FROM `exchange_requests` LIKE 'requester_qc_acceptance_status'";
    $stmt = $pdo->query($checkSql);
    if ($stmt->rowCount() == 0) {
        $sql2 = "ALTER TABLE `exchange_requests` ADD COLUMN `requester_qc_acceptance_status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending'";
        $pdo->exec($sql2);
    }

    $checkSql = "SHOW COLUMNS FROM `exchange_requests` LIKE 'offer_qc_acceptance_status'";
    $stmt = $pdo->query($checkSql);
    if ($stmt->rowCount() == 0) {
        $sql3 = "ALTER TABLE `exchange_requests` ADD COLUMN `offer_qc_acceptance_status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending'";
        $pdo->exec($sql3);
    }

    $pdo->commit();
    echo "Database changes applied successfully.";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
