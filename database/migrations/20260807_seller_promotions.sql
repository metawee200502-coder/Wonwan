CREATE TABLE IF NOT EXISTS seller_promotions (
    promotion_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_type ENUM('publisher','shop') NOT NULL,
    seller_id INT NOT NULL,
    promotion_name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(10,2) NOT NULL,
    minimum_quantity INT NOT NULL DEFAULT 1,
    minimum_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    maximum_discount DECIMAL(10,2) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_seller_promotion_active (seller_type, seller_id, is_active, starts_at, ends_at)
);

ALTER TABLE orders
    ADD COLUMN promotion_id INT NULL AFTER shipping_fee,
    ADD COLUMN promotion_name VARCHAR(150) NULL AFTER promotion_id,
    ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER promotion_name;
