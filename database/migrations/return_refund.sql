-- =======================================================================
-- Return & Refund System Migration (MySQL 8.0 compatible)
-- วนวรรณ E-Commerce Platform  |  2026-09-17
-- =======================================================================

-- -----------------------------------------------------------------------
-- Helper: เพิ่มคอลัมน์เฉพาะกรณีที่ยังไม่มี (ใช้ stored procedure)
-- -----------------------------------------------------------------------
DROP PROCEDURE IF EXISTS ww_add_column_if_not_exists;
DELIMITER $$
CREATE PROCEDURE ww_add_column_if_not_exists(
    IN tbl_name VARCHAR(64),
    IN col_name VARCHAR(64),
    IN col_def  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = tbl_name
          AND COLUMN_NAME  = col_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl_name, '` ADD COLUMN `', col_name, '` ', col_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

-- -----------------------------------------------------------------------
-- 1. shops table
-- -----------------------------------------------------------------------
CALL ww_add_column_if_not_exists('shops', 'return_contact_name',  "VARCHAR(100) NULL COMMENT 'ชื่อผู้ติดต่อสำหรับรับคืน'");
CALL ww_add_column_if_not_exists('shops', 'return_contact_phone', "VARCHAR(20) NULL COMMENT 'เบอร์โทรผู้รับสินค้าคืน'");
CALL ww_add_column_if_not_exists('shops', 'return_address',       "TEXT NULL COMMENT 'ที่อยู่สำหรับรับสินค้าคืน'");

-- -----------------------------------------------------------------------
-- 2. publishers table
-- -----------------------------------------------------------------------
CALL ww_add_column_if_not_exists('publishers', 'return_contact_name',  "VARCHAR(100) NULL COMMENT 'ชื่อผู้ติดต่อสำหรับรับคืน'");
CALL ww_add_column_if_not_exists('publishers', 'return_contact_phone', "VARCHAR(20) NULL COMMENT 'เบอร์โทรผู้รับสินค้าคืน'");
CALL ww_add_column_if_not_exists('publishers', 'return_address',       "TEXT NULL COMMENT 'ที่อยู่สำหรับรับสินค้าคืน'");

-- -----------------------------------------------------------------------
-- 3. ตาราง return_requests
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS return_requests (
    return_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id            INT UNSIGNED         NOT NULL,
    user_id             INT UNSIGNED         NOT NULL,
    seller_type         ENUM('shop','publisher','user') NOT NULL,
    seller_id           INT UNSIGNED         NOT NULL,
    reason              ENUM('damaged','not_as_described','wrong_item') NOT NULL,
    evidence_url        VARCHAR(512)         NOT NULL,
    return_tracking     VARCHAR(120)         NOT NULL,
    refund_amount       DECIMAL(10,2)        NOT NULL DEFAULT 0.00,
    status              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note          TEXT                 NULL,
    reviewed_by         INT UNSIGNED         NULL,
    created_at          DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at         DATETIME             NULL,
    UNIQUE  KEY uq_order  (order_id),
    INDEX   idx_user      (user_id),
    INDEX   idx_seller    (seller_type, seller_id),
    INDEX   idx_status    (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- 4. ตาราง user_wallets (ถ้ายังไม่มี)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_wallets (
    wallet_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    balance     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_wallet_transactions (
    txn_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id   INT UNSIGNED NOT NULL,
    order_id    INT UNSIGNED NULL,
    type        ENUM('REFUND','TOPUP','WITHDRAWAL') NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    note        TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet (wallet_id),
    INDEX idx_order  (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
-- 5. เพิ่ม note ใน wallet_transactions ถ้ายังไม่มี
-- -----------------------------------------------------------------------
CALL ww_add_column_if_not_exists('wallet_transactions', 'note', "TEXT NULL COMMENT 'หมายเหตุธุรกรรม'");

-- Cleanup
DROP PROCEDURE IF EXISTS ww_add_column_if_not_exists;

SELECT 'Migration return_refund.sql completed successfully' AS result;
