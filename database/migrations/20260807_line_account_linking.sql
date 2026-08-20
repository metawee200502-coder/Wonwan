CREATE TABLE IF NOT EXISTS line_link_states (
    state_hash CHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    return_origin VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_line_link_expiry (expires_at),
    CONSTRAINT fk_line_link_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
    ADD UNIQUE KEY uq_users_line_user_id (line_user_id);

