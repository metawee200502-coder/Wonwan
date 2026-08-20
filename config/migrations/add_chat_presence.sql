CREATE TABLE IF NOT EXISTS chat_presence (
    user_id INT NOT NULL,
    conversation_id INT NOT NULL,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, conversation_id),
    INDEX idx_chat_presence_active (conversation_id, last_seen_at),
    CONSTRAINT fk_chat_presence_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_presence_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
