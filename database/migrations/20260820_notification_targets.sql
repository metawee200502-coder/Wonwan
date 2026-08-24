ALTER TABLE notifications
  ADD COLUMN target_url VARCHAR(500) NULL AFTER related_review_id;
