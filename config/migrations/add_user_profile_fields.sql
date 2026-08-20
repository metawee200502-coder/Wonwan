ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER display_name;
ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER profile_image;
ALTER TABLE users ADD COLUMN followers_count INT NOT NULL DEFAULT 0 AFTER bio;
