CREATE TABLE IF NOT EXISTS order_shipments (
    shipment_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    seller_type ENUM('publisher','shop','user') NOT NULL,
    seller_id INT NOT NULL,
    courier VARCHAR(100) NOT NULL,
    tracking_number VARCHAR(100) NOT NULL,
    status ENUM('packing','shipped','delivered','returned') NOT NULL DEFAULT 'shipped',
    shipped_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_seller (order_id,seller_type,seller_id),
    CONSTRAINT fk_order_shipments_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);
