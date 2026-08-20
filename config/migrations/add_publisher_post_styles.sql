ALTER TABLE publisher_posts
    ADD COLUMN background_color VARCHAR(20) NOT NULL DEFAULT '#FFFFFF' AFTER image_url,
    ADD COLUMN is_bold TINYINT(1) NOT NULL DEFAULT 0 AFTER background_color,
    ADD COLUMN is_italic TINYINT(1) NOT NULL DEFAULT 0 AFTER is_bold;
