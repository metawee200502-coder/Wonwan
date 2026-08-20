ALTER TABLE book_reviews
  ADD COLUMN seller_reply TEXT NULL AFTER review_text,
  ADD COLUMN seller_reply_at DATETIME NULL AFTER seller_reply,
  ADD COLUMN seller_reply_user_id INT NULL AFTER seller_reply_at,
  ADD INDEX idx_book_reviews_reply_user (seller_reply_user_id),
  ADD CONSTRAINT fk_book_reviews_reply_user FOREIGN KEY (seller_reply_user_id) REFERENCES users(user_id) ON DELETE SET NULL;

ALTER TABLE notifications
  MODIFY notification_type ENUM('order','exchange','system','chat','follow','review') NULL DEFAULT 'system',
  ADD COLUMN related_review_id INT NULL AFTER related_user_id,
  ADD INDEX idx_notifications_review (related_review_id),
  ADD CONSTRAINT fk_notifications_review FOREIGN KEY (related_review_id) REFERENCES book_reviews(review_id) ON DELETE SET NULL;
