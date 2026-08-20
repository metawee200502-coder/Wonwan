ALTER TABLE orders
    ADD COLUMN reservation_expires_at DATETIME NULL AFTER discount_amount,
    ADD COLUMN stock_reserved TINYINT(1) NOT NULL DEFAULT 0 AFTER reservation_expires_at,
    ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER stock_reserved,
    ADD INDEX idx_order_reservation_expiry (payment_status,stock_reserved,reservation_expires_at);
