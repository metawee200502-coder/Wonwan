ALTER TABLE seller_promotions
    ADD COLUMN scope_type ENUM('all','category','books') NOT NULL DEFAULT 'all' AFTER description;

CREATE TABLE IF NOT EXISTS seller_promotion_targets (
    target_id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_id INT NOT NULL,
    target_type ENUM('category','book') NOT NULL,
    target_value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_promotion_target (promotion_id,target_type,target_value),
    CONSTRAINT fk_promotion_target_promotion FOREIGN KEY (promotion_id)
        REFERENCES seller_promotions(promotion_id) ON DELETE CASCADE
);
