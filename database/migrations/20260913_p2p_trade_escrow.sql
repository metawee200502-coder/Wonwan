-- Migration: P2P Trade & Escrow System
-- Created At: 2026-09-13

-- 1. Create trades table
CREATE TABLE IF NOT EXISTS trades (
    trade_id INT AUTO_INCREMENT PRIMARY KEY,
    user_a_id INT NOT NULL,
    user_b_id INT NOT NULL,
    trade_option ENUM('meetup', 'direct_escrow', 'admin_qc') NOT NULL,
    status ENUM('pending', 'condition_review', 'payment_pending', 'shipping', 'delivered', 'disputed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_a_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (user_b_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create trade_items table (Links trade to the exchanges table)
CREATE TABLE IF NOT EXISTS trade_items (
    trade_item_id INT AUTO_INCREMENT PRIMARY KEY,
    trade_id INT NOT NULL,
    owner_id INT NOT NULL,
    exchange_id INT NOT NULL,
    estimated_value DECIMAL(10, 2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trade_id) REFERENCES trades(trade_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (exchange_id) REFERENCES exchanges(exchange_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create trade_condition_forms table (Proof of Condition)
CREATE TABLE IF NOT EXISTS trade_condition_forms (
    form_id INT AUTO_INCREMENT PRIMARY KEY,
    trade_item_id INT NOT NULL,
    owner_id INT NOT NULL,
    front_cover_url VARCHAR(255) NOT NULL,
    back_cover_url VARCHAR(255) NOT NULL,
    spine_url VARCHAR(255) NOT NULL,
    defect_urls JSON,
    defects_description TEXT,
    notes TEXT,
    is_accepted_by_partner BOOLEAN DEFAULT FALSE,
    accepted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trade_item_id) REFERENCES trade_items(trade_item_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create trade_disputes table
CREATE TABLE IF NOT EXISTS trade_disputes (
    dispute_id INT AUTO_INCREMENT PRIMARY KEY,
    trade_id INT NOT NULL,
    raised_by INT NOT NULL,
    against_user INT NOT NULL,
    unboxing_video_url VARCHAR(255) NOT NULL,
    actual_item_photos JSON NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('open', 'under_review', 'resolved_return', 'resolved_reject') NOT NULL DEFAULT 'open',
    admin_resolution_notes TEXT,
    resolved_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trade_id) REFERENCES trades(trade_id) ON DELETE CASCADE,
    FOREIGN KEY (raised_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (against_user) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Update vendor_wallets for escrow balance (MariaDB 10.2+ supports IF NOT EXISTS)
-- Actually, IF NOT EXISTS for ADD COLUMN is supported in MariaDB 10.6+. 
-- Just in case, let's keep it simple. If it fails, I'll fix it.
ALTER TABLE vendor_wallets ADD COLUMN escrow_balance DECIMAL(10,2) DEFAULT 0.00 AFTER pending_withdrawal_balance;
