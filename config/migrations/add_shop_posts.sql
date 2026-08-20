CREATE TABLE IF NOT EXISTS shop_posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(500) NULL,
    background_color VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
    is_bold TINYINT(1) NOT NULL DEFAULT 0,
    is_italic TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shop_posts_shop_created (shop_id, created_at),
    CONSTRAINT fk_shop_posts_shop FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
