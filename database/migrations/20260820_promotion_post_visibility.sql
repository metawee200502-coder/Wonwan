ALTER TABLE publisher_posts
  ADD COLUMN promotion_id INT NULL AFTER post_id,
  ADD INDEX idx_publisher_posts_promotion (promotion_id);

ALTER TABLE shop_posts
  ADD COLUMN promotion_id INT NULL AFTER post_id,
  ADD INDEX idx_shop_posts_promotion (promotion_id);
