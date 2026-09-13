ALTER TABLE shop_staff ADD COLUMN staff_role ENUM('owner', 'sub_admin') DEFAULT 'sub_admin';
UPDATE shop_staff SET staff_role = 'owner';

ALTER TABLE publisher_staff ADD COLUMN staff_role ENUM('owner', 'sub_admin') DEFAULT 'sub_admin';
UPDATE publisher_staff SET staff_role = 'owner';

CREATE TABLE IF NOT EXISTS seller_action_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_type ENUM('shop', 'publisher') NOT NULL,
    seller_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_id VARCHAR(100) DEFAULT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (seller_type, seller_id),
    INDEX (user_id)
);
