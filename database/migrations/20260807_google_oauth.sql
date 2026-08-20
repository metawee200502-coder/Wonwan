ALTER TABLE users
    ADD UNIQUE KEY uq_users_google_id (google_id);

CREATE TABLE IF NOT EXISTS oauth_login_states (
    state_hash CHAR(64) PRIMARY KEY,
    nonce VARCHAR(128) NOT NULL,
    return_origin VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_oauth_state_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS oauth_login_handoffs (
    token_hash CHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_oauth_handoff_expiry (expires_at),
    CONSTRAINT fk_oauth_handoff_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

