ALTER TABLE notifications
    MODIFY notification_type ENUM('order','exchange','system','chat','follow') NULL DEFAULT 'system',
    ADD COLUMN related_user_id INT NULL AFTER chat_conversation_id,
    ADD KEY idx_notification_related_user (related_user_id);
