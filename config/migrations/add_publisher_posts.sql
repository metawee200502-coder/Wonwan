CREATE TABLE IF NOT EXISTS publisher_posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    publisher_id INT NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_publisher_posts_publisher_created (publisher_id, created_at),
    CONSTRAINT fk_publisher_posts_publisher
        FOREIGN KEY (publisher_id) REFERENCES publishers(publisher_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
