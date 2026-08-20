CREATE TABLE IF NOT EXISTS password_reset_tokens (
    reset_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    requested_ip VARCHAR(45) NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_password_reset_user (user_id, created_at),
    KEY idx_password_reset_expiry (expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

