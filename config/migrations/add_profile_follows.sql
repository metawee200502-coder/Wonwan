CREATE TABLE IF NOT EXISTS profile_follows (
    follower_user_id INT NOT NULL,
    target_type ENUM('publisher','shop','user') NOT NULL,
    target_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_user_id, target_type, target_id),
    KEY idx_profile_follows_target (target_type, target_id, created_at),
    CONSTRAINT fk_profile_follows_user
        FOREIGN KEY (follower_user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO profile_follows (follower_user_id, target_type, target_id, created_at)
SELECT follower_user_id, 'user', followed_user_id, created_at
FROM user_follows;
