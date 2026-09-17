-- Add new tracking columns to return_requests
ALTER TABLE return_requests
    ADD COLUMN IF NOT EXISTS super_admin_reviewed_by INT UNSIGNED NULL COMMENT 'user_id ของ super_admin ที่ approve/reject',
    ADD COLUMN IF NOT EXISTS super_admin_reviewed_at DATETIME NULL COMMENT 'เวลาที่ super_admin ตรวจสอบ',
    ADD COLUMN IF NOT EXISTS shop_received_at DATETIME NULL COMMENT 'เวลาที่ร้านค้ากดยืนยันรับสินค้าคืน',
    ADD COLUMN IF NOT EXISTS shop_received_by INT UNSIGNED NULL COMMENT 'user_id ของ shop_admin ที่กดยืนยัน',
    ADD COLUMN IF NOT EXISTS refunded_at DATETIME NULL COMMENT 'เวลาที่ super_admin โอนเงินคืน',
    ADD COLUMN IF NOT EXISTS refunded_by INT UNSIGNED NULL COMMENT 'user_id ของ super_admin ที่โอนเงิน',
    ADD COLUMN IF NOT EXISTS refund_note TEXT NULL COMMENT 'หมายเหตุจาก super_admin ตอนโอนเงิน';

-- user_wallets (safety net)
CREATE TABLE IF NOT EXISTS user_wallets (
    wallet_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    balance     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- user_wallet_transactions (safety net)
CREATE TABLE IF NOT EXISTS user_wallet_transactions (
    txn_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id   INT UNSIGNED NOT NULL,
    return_id   INT UNSIGNED NULL,
    order_id    INT UNSIGNED NULL,
    type        ENUM('REFUND','TOPUP','WITHDRAWAL','PURCHASE') NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    note        TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wallet (wallet_id),
    INDEX idx_order  (order_id),
    INDEX idx_return (return_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- note column in wallet_transactions
ALTER TABLE wallet_transactions ADD COLUMN IF NOT EXISTS note TEXT NULL COMMENT 'หมายเหตุธุรกรรม';

-- Composite index for status filtering
ALTER TABLE return_requests ADD INDEX IF NOT EXISTS idx_status_seller (status, seller_type, seller_id);

SELECT 'Columns migration completed' AS result;
DESCRIBE return_requests;
