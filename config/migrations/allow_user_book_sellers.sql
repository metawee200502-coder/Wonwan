ALTER TABLE books
    MODIFY seller_type ENUM('publisher','shop','user') NOT NULL DEFAULT 'shop';
