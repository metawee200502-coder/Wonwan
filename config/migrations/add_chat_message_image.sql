ALTER TABLE chat_messages
    ADD COLUMN image_path VARCHAR(500) NULL AFTER message_text;
