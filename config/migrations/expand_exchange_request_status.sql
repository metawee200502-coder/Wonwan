ALTER TABLE exchange_requests
    MODIFY status ENUM('pending','accepted','completed','rejected','cancelled') NOT NULL DEFAULT 'pending';
