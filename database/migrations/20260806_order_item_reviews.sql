ALTER TABLE book_reviews
    ADD COLUMN order_item_id INT NULL AFTER user_id,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at,
    ADD UNIQUE KEY uq_book_reviews_order_item (order_item_id),
    ADD CONSTRAINT fk_book_reviews_order_item
        FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id)
        ON DELETE SET NULL;
