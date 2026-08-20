ALTER TABLE notifications
    ADD COLUMN chat_conversation_id INT NULL AFTER notification_type,
    ADD INDEX idx_notification_chat (chat_conversation_id),
    ADD CONSTRAINT fk_notification_chat
        FOREIGN KEY (chat_conversation_id)
        REFERENCES chat_conversations(conversation_id)
        ON DELETE SET NULL;
