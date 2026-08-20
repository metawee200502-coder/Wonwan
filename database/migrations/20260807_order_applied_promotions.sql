CREATE TABLE IF NOT EXISTS order_applied_promotions (
    order_promotion_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    promotion_id INT NULL,
    promotion_name VARCHAR(150) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_applied_promotions_order (order_id),
    CONSTRAINT fk_order_applied_promotions_order FOREIGN KEY (order_id)
        REFERENCES orders(order_id) ON DELETE CASCADE
);

ALTER TABLE orders MODIFY promotion_name VARCHAR(500) NULL;
