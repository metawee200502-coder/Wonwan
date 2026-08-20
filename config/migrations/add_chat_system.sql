CREATE TABLE IF NOT EXISTS chat_conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_user_id INT NOT NULL,
    seller_type ENUM('publisher','shop','user') NOT NULL,
    seller_id INT NOT NULL,
    referenced_book_id INT NULL,
    last_message_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chat_participants (customer_user_id, seller_type, seller_id),
    KEY idx_chat_seller (seller_type, seller_id, last_message_at),
    CONSTRAINT fk_chat_customer FOREIGN KEY (customer_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_book FOREIGN KEY (referenced_book_id) REFERENCES books(book_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    message_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_user_id INT NOT NULL,
    message_text TEXT NOT NULL,
    referenced_book_id INT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chat_messages (conversation_id, created_at),
    CONSTRAINT fk_message_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE CASCADE,
    CONSTRAINT fk_message_sender FOREIGN KEY (sender_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_message_book FOREIGN KEY (referenced_book_id) REFERENCES books(book_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
