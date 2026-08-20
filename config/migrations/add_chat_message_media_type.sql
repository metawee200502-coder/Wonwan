ALTER TABLE chat_messages
    ADD COLUMN media_type ENUM('image','video') NULL AFTER image_path;
