CREATE TABLE IF NOT EXISTS digital_twin_conversations (
    conversation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_type ENUM('book','exchange') NOT NULL DEFAULT 'book',
    item_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (conversation_id),
    UNIQUE KEY uq_digital_twin_user_item (user_id, item_type, item_id),
    KEY idx_digital_twin_updated (user_id, updated_at),
    CONSTRAINT fk_digital_twin_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_twin_messages (
    message_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id),
    KEY idx_digital_twin_messages (conversation_id, message_id),
    CONSTRAINT fk_digital_twin_conversation FOREIGN KEY (conversation_id)
        REFERENCES digital_twin_conversations(conversation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
