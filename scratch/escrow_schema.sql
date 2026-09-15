CREATE TABLE `trades` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `proposer_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `trade_option` ENUM('meetup', 'direct_escrow', 'admin_qc') NOT NULL,
  `status` ENUM('pending_agreement', 'condition_review', 'payment_held', 'shipping', 'disputed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_agreement',
  `system_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `qc_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  -- FOREIGN KEY (`proposer_id`) REFERENCES `users`(`id`),
  -- FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `trade_items` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `trade_id` CHAR(36) NOT NULL,
  `user_id` INT NOT NULL,
  `book_id` INT NOT NULL,
  `condition_front_img` VARCHAR(255) DEFAULT NULL,
  `condition_back_img` VARCHAR(255) DEFAULT NULL,
  `condition_spine_img` VARCHAR(255) DEFAULT NULL,
  `defects_img` JSON DEFAULT NULL,
  `defects_desc` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `is_form_accepted` BOOLEAN NOT NULL DEFAULT FALSE,
  `deposit_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `tracking_number` VARCHAR(100) DEFAULT NULL,
  `shipping_courier` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`trade_id`) REFERENCES `trades`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  -- FOREIGN KEY (`book_id`) REFERENCES `books`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `trade_disputes` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `trade_id` CHAR(36) NOT NULL,
  `reporter_id` INT NOT NULL,
  `accused_id` INT NOT NULL,
  `unboxing_video_url` VARCHAR(255) NOT NULL,
  `received_item_imgs` JSON NOT NULL,
  `dispute_reason` TEXT NOT NULL,
  `status` ENUM('pending_review', 'resolved_return', 'resolved_reject') NOT NULL DEFAULT 'pending_review',
  `admin_id` INT DEFAULT NULL,
  `admin_resolution_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`trade_id`) REFERENCES `trades`(`id`) ON DELETE CASCADE
  -- FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`),
  -- FOREIGN KEY (`accused_id`) REFERENCES `users`(`id`),
  -- FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wallet_transactions` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `user_id` INT NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `type` ENUM('topup', 'trade_fee', 'escrow_hold', 'escrow_release', 'escrow_refund', 'dispute_penalty') NOT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` CHAR(36) DEFAULT NULL,
  `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'completed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  -- FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
