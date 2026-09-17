-- =======================================================================
-- Return & Refund System — Migration v2 FIXED (MySQL 8.0)
-- วนวรรณ Platform  |  2026-09-17
-- Fixed: ต้อง backup ค่า status เดิมก่อนเปลี่ยน ENUM
-- =======================================================================

-- -----------------------------------------------------------------------
-- Helper procedures
-- -----------------------------------------------------------------------
DROP PROCEDURE IF EXISTS ww_add_col;
DELIMITER $$
CREATE PROCEDURE ww_add_col(
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

DROP PROCEDURE IF EXISTS ww_add_index;
DELIMITER $$
CREATE PROCEDURE ww_add_index(
    IN tbl_name VARCHAR(64),
    IN idx_name VARCHAR(64),
    IN col_list TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = tbl_name
          AND INDEX_NAME   = idx_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl_name, '` ADD INDEX `', idx_name, '` (', col_list, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

-- -----------------------------------------------------------------------
-- Step 1: เพิ่มคอลัมน์ status_v2 ชั่วคราว เพื่อเก็บค่าใหม่
-- -----------------------------------------------------------------------
CALL ww_add_col('return_requests', 'status_v2',
    "VARCHAR(50) NULL COMMENT 'temp column for migration'");

-- -----------------------------------------------------------------------
-- Step 2: map ค่าเดิม → ค่าใหม่ ใส่ใน status_v2
-- -----------------------------------------------------------------------
UPDATE return_requests SET status_v2 = 'pending_super_admin' WHERE status = 'pending';
UPDATE return_requests SET status_v2 = 'refunded_to_wallet'  WHERE status = 'approved';
UPDATE return_requests SET status_v2 = 'rejected'            WHERE status = 'rejected';
-- ถ้ามีค่าอื่น ๆ ที่ไม่คาดคิด ให้ set เป็น pending_super_admin
UPDATE return_requests SET status_v2 = 'pending_super_admin' WHERE status_v2 IS NULL;

-- -----------------------------------------------------------------------
-- Step 3: drop default ของคอลัมน์ status เดิมก่อน
-- -----------------------------------------------------------------------
ALTER TABLE return_requests ALTER COLUMN status DROP DEFAULT;

-- -----------------------------------------------------------------------
-- Step 4: เปลี่ยน ENUM โดยใช้ค่า NULL ชั่วคราว
-- -----------------------------------------------------------------------
ALTER TABLE return_requests
    MODIFY COLUMN status
        ENUM(
            'pending_super_admin',
            'waiting_for_shop',
            'shop_received',
            'refunded_to_wallet',
            'rejected'
        )
        NULL;

-- -----------------------------------------------------------------------
-- Step 5: copy ค่าจาก status_v2 → status
-- -----------------------------------------------------------------------
UPDATE return_requests SET status = status_v2;

-- -----------------------------------------------------------------------
-- Step 6: ตั้ง NOT NULL default กลับ
-- -----------------------------------------------------------------------
ALTER TABLE return_requests
    MODIFY COLUMN status
        ENUM(
            'pending_super_admin',
            'waiting_for_shop',
            'shop_received',
            'refunded_to_wallet',
            'rejected'
        )
        NOT NULL
        DEFAULT 'pending_super_admin';

-- -----------------------------------------------------------------------
-- Step 7: drop คอลัมน์ temp
-- -----------------------------------------------------------------------
ALTER TABLE return_requests DROP COLUMN status_v2;

-- -----------------------------------------------------------------------
-- Step 8: เพิ่มคอลัมน์ tracking ใหม่
-- -----------------------------------------------------------------------
CALL ww_add_col('return_requests', 'super_admin_reviewed_by',
    "INT UNSIGNED NULL COMMENT 'user_id ของ super_admin ที่ approve/reject'");

CALL ww_add_col('return_requests', 'super_admin_reviewed_at',
    "DATETIME NULL COMMENT 'เวลาที่ super_admin ตรวจสอบ'");

CALL ww_add_col('return_requests', 'shop_received_at',
    "DATETIME NULL COMMENT 'เวลาที่ร้านค้ากดยืนยันรับสินค้าคืน'");

CALL ww_add_col('return_requests', 'shop_received_by',
    "INT UNSIGNED NULL COMMENT 'user_id ของ shop_admin ที่กดยืนยัน'");

CALL ww_add_col('return_requests', 'refunded_at',
    "DATETIME NULL COMMENT 'เวลาที่ super_admin โอนเงินคืนเข้า wallet'");

CALL ww_add_col('return_requests', 'refunded_by',
    "INT UNSIGNED NULL COMMENT 'user_id ของ super_admin ที่โอนเงิน'");

CALL ww_add_col('return_requests', 'refund_note',
    "TEXT NULL COMMENT 'หมายเหตุจาก super_admin ตอนโอนเงิน'");

-- -----------------------------------------------------------------------
-- Step 9: ตาราง user_wallets (safety net)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_wallets (
    wallet_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    balance     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Step 10: ตาราง user_wallet_transactions (safety net)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_wallet_transactions (
    txn_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id   INT UNSIGNED NOT NULL,
    return_id   INT UNSIGNED NULL COMMENT 'อ้างอิง return_requests.return_id',
    order_id    INT UNSIGNED NULL,
    type        ENUM('REFUND','TOPUP','WITHDRAWAL','PURCHASE') NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    note        TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet (wallet_id),
    INDEX idx_order  (order_id),
    INDEX idx_return (return_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL ww_add_col('user_wallet_transactions', 'return_id',
    "INT UNSIGNED NULL COMMENT 'อ้างอิง return_requests.return_id'");

-- -----------------------------------------------------------------------
-- Step 11: เพิ่ม note ใน wallet_transactions ถ้ายังไม่มี
-- -----------------------------------------------------------------------
CALL ww_add_col('wallet_transactions', 'note',
    "TEXT NULL COMMENT 'หมายเหตุธุรกรรม'");

-- -----------------------------------------------------------------------
-- Step 12: Index เพิ่มประสิทธิภาพ
-- -----------------------------------------------------------------------
CALL ww_add_index('return_requests', 'idx_status_seller', 'status, seller_type, seller_id');

-- -----------------------------------------------------------------------
-- Cleanup
-- -----------------------------------------------------------------------
DROP PROCEDURE IF EXISTS ww_add_col;
DROP PROCEDURE IF EXISTS ww_add_index;

SELECT 'Migration return_refund_v2.sql completed successfully' AS result;
