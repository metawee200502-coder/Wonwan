CREATE TABLE IF NOT EXISTS exchange_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    exchange_id INT NOT NULL,
    requester_id INT NOT NULL,
    offered_exchange_id INT NULL,
    message TEXT NULL,
    status ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exchange_requester (exchange_id, requester_id),
    KEY idx_exchange_requests_requester (requester_id, status, created_at),
    CONSTRAINT fk_exchange_requests_target FOREIGN KEY (exchange_id) REFERENCES exchanges(exchange_id) ON DELETE CASCADE,
    CONSTRAINT fk_exchange_requests_requester FOREIGN KEY (requester_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_exchange_requests_offer FOREIGN KEY (offered_exchange_id) REFERENCES exchanges(exchange_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
