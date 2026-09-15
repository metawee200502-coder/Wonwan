<?php
require_once __DIR__ . '/../config/config.php';

function runSql($pdo, $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql<br>\n";
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . " -> $sql<br>\n";
    }
}

// 1. Add trade_option to exchange_requests
runSql($pdo, "ALTER TABLE exchange_requests ADD COLUMN trade_option ENUM('meetup', 'direct_escrow', 'admin_qc') NULL AFTER status");

// 2. Drop Foreign Keys for trade_condition_forms and trade_disputes
runSql($pdo, "DROP TABLE IF EXISTS trade_disputes");
runSql($pdo, "DROP TABLE IF EXISTS trade_condition_forms");
runSql($pdo, "DROP TABLE IF EXISTS trade_items");
runSql($pdo, "DROP TABLE IF EXISTS trades");

// 3. Recreate trade_condition_forms linking to exchange_requests
runSql($pdo, "CREATE TABLE IF NOT EXISTS trade_condition_forms (
    form_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
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
    FOREIGN KEY (request_id) REFERENCES exchange_requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 4. Recreate trade_disputes linking to exchange_requests
runSql($pdo, "CREATE TABLE IF NOT EXISTS trade_disputes (
    dispute_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
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
    FOREIGN KEY (request_id) REFERENCES exchange_requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (raised_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (against_user) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Done!";
