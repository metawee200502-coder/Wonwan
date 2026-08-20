ALTER TABLE chat_conversations
    ADD COLUMN referenced_exchange_id INT NULL AFTER referenced_book_id,
    ADD KEY idx_chat_exchange (referenced_exchange_id),
    ADD CONSTRAINT fk_chat_exchange
        FOREIGN KEY (referenced_exchange_id) REFERENCES exchanges(exchange_id) ON DELETE SET NULL;

ALTER TABLE chat_messages
    ADD COLUMN referenced_exchange_id INT NULL AFTER referenced_book_id,
    ADD KEY idx_message_exchange (referenced_exchange_id),
    ADD CONSTRAINT fk_message_exchange
        FOREIGN KEY (referenced_exchange_id) REFERENCES exchanges(exchange_id) ON DELETE SET NULL;
