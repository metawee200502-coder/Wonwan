CREATE TABLE `book_reviews` (
  `review_id` int NOT NULL AUTO_INCREMENT,
  `book_id` int NOT NULL COMMENT 'รหัสหนังสือที่ถูกรีวิว อ้างอิงจากตาราง books',
  `user_id` int NOT NULL COMMENT 'รหัสผู้เขียนรีวิว อ้างอิงจากตาราง users',
  `order_item_id` int DEFAULT NULL,
  `rating` int NOT NULL COMMENT 'คะแนนรีวิว เก็บเป็นตัวเลข 1 - 5 ดาว',
  `review_text` text COMMENT 'ข้อความแสดงความคิดเห็น/คำวิจารณ์',
  `seller_reply` text,
  `seller_reply_at` datetime DEFAULT NULL,
  `seller_reply_user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `uq_book_reviews_order_item` (`order_item_id`),
  KEY `book_id` (`book_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_book_reviews_reply_user` (`seller_reply_user_id`),
  CONSTRAINT `book_reviews_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE,
  CONSTRAINT `book_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_book_reviews_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_book_reviews_reply_user` FOREIGN KEY (`seller_reply_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `books` (
  `book_id` int NOT NULL AUTO_INCREMENT,
  `seller_id` int NOT NULL COMMENT 'รหัสผู้ขาย (สำนักพิมพ์, ร้านค้า, ผู้ใช้ทั่วไป)',
  `seller_type` enum('publisher','shop','user') NOT NULL DEFAULT 'shop',
  `isbn` varchar(20) DEFAULT NULL COMMENT 'รหัสบาร์โค้ด รองรับสแกนอัตโนมัติ',
  `isbn_13` varchar(20) DEFAULT NULL COMMENT 'รหัส ISBN-13 (จากหน้ามือ 1)',
  `title` varchar(255) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `publisher_name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL COMMENT 'หมวดหมู่ (เช่น นวนิยาย, คลาสสิก)',
  `format` varchar(50) DEFAULT NULL COMMENT 'รูปแบบ (เช่น ปกอ่อน Paperback)',
  `pages` int DEFAULT NULL COMMENT 'จำนวนหน้า',
  `dimensions` varchar(50) DEFAULT NULL COMMENT 'ขนาด (เช่น 14.5 x 21 x 2.2 ซม.)',
  `weight_grams` int DEFAULT NULL COMMENT 'น้ำหนัก (กรัม)',
  `language` varchar(50) DEFAULT NULL COMMENT 'ภาษา',
  `publish_date` date DEFAULT NULL COMMENT 'วันที่พิมพ์',
  `synopsis` text COMMENT 'เรื่องย่อ / คำอธิบาย',
  `condition_level` enum('new','good','fair','old') NOT NULL COMMENT 'เหมือนใหม่, สภาพดี, มีตำหนิ, สภาพเก่า',
  `defects_detail` text COMMENT 'รายละเอียดเพิ่มเติม/ตำหนิ',
  `price` decimal(10,2) NOT NULL COMMENT 'ราคาขาย',
  `stock_quantity` int NOT NULL DEFAULT '1' COMMENT 'จำนวนสินค้าที่มีในสต็อก',
  `original_price` decimal(10,2) DEFAULT NULL COMMENT 'ราคาหน้าปก (สำหรับมือ 2)',
  `image_cover` text,
  `image_back` text,
  `image_inside` text,
  `image_edge` text,
  `shipping_methods` text COMMENT 'วิธีการจัดส่ง (Standard, ปณท., นัดรับ)',
  `seller_lat` decimal(10,7) DEFAULT NULL,
  `seller_lng` decimal(10,7) DEFAULT NULL,
  `seller_province` varchar(100) DEFAULT NULL,
  `status` enum('available','sold','hidden','draft') NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `book_type` enum('new','used') DEFAULT 'used',
  PRIMARY KEY (`book_id`),
  KEY `idx_seller` (`seller_id`,`seller_type`)
) ENGINE=InnoDB AUTO_INCREMENT=229 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `cart_items` (
  `cart_item_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'รหัสผู้ซื้อ อ้างอิงจากตาราง users',
  `book_id` int NOT NULL COMMENT 'รหัสหนังสือที่เลือก อ้างอิงจากตาราง books',
  `quantity` int DEFAULT '1' COMMENT 'จำนวนเล่มที่ต้องการซื้อ',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_item_id`),
  UNIQUE KEY `user_id` (`user_id`,`book_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `chat_conversations` (
  `conversation_id` int NOT NULL AUTO_INCREMENT,
  `customer_user_id` int NOT NULL,
  `seller_type` enum('publisher','shop','user') COLLATE utf8mb4_unicode_ci NOT NULL,
  `seller_id` int NOT NULL,
  `referenced_book_id` int DEFAULT NULL,
  `referenced_exchange_id` int DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversation_id`),
  UNIQUE KEY `uq_chat_participants` (`customer_user_id`,`seller_type`,`seller_id`),
  KEY `idx_chat_seller` (`seller_type`,`seller_id`,`last_message_at`),
  KEY `fk_chat_book` (`referenced_book_id`),
  KEY `idx_chat_exchange` (`referenced_exchange_id`),
  CONSTRAINT `fk_chat_book` FOREIGN KEY (`referenced_book_id`) REFERENCES `books` (`book_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chat_customer` FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_exchange` FOREIGN KEY (`referenced_exchange_id`) REFERENCES `exchanges` (`exchange_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_messages` (
  `message_id` bigint NOT NULL AUTO_INCREMENT,
  `conversation_id` int NOT NULL,
  `sender_user_id` int NOT NULL,
  `message_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_type` enum('image','video') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referenced_book_id` int DEFAULT NULL,
  `referenced_exchange_id` int DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `idx_chat_messages` (`conversation_id`,`created_at`),
  KEY `fk_message_sender` (`sender_user_id`),
  KEY `fk_message_book` (`referenced_book_id`),
  KEY `idx_message_exchange` (`referenced_exchange_id`),
  CONSTRAINT `fk_message_book` FOREIGN KEY (`referenced_book_id`) REFERENCES `books` (`book_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`conversation_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_exchange` FOREIGN KEY (`referenced_exchange_id`) REFERENCES `exchanges` (`exchange_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_presence` (
  `user_id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `last_seen_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`conversation_id`),
  KEY `idx_chat_presence_active` (`conversation_id`,`last_seen_at`),
  CONSTRAINT `fk_chat_presence_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`conversation_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_presence_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `digital_twin_conversations` (
  `conversation_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `item_type` enum('book','exchange') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'book',
  `item_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversation_id`),
  UNIQUE KEY `uq_digital_twin_user_item` (`user_id`,`item_type`,`item_id`),
  KEY `idx_digital_twin_updated` (`user_id`,`updated_at`),
  CONSTRAINT `fk_digital_twin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `digital_twin_messages` (
  `message_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `role` enum('user','assistant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `idx_digital_twin_messages` (`conversation_id`,`message_id`),
  CONSTRAINT `fk_digital_twin_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `digital_twin_conversations` (`conversation_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exchange_requests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `exchange_id` int NOT NULL,
  `requester_id` int NOT NULL,
  `offered_exchange_id` int DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','accepted','completed','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uq_exchange_requester` (`exchange_id`,`requester_id`),
  KEY `idx_exchange_requests_requester` (`requester_id`,`status`,`created_at`),
  KEY `fk_exchange_requests_offer` (`offered_exchange_id`),
  CONSTRAINT `fk_exchange_requests_offer` FOREIGN KEY (`offered_exchange_id`) REFERENCES `exchanges` (`exchange_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exchange_requests_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exchange_requests_target` FOREIGN KEY (`exchange_id`) REFERENCES `exchanges` (`exchange_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exchange_wishlists` (
  `exchange_wishlist_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `exchange_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`exchange_wishlist_id`),
  UNIQUE KEY `uq_exchange_wishlist` (`user_id`,`exchange_id`),
  KEY `fk_exchange_wishlist_item` (`exchange_id`),
  CONSTRAINT `fk_exchange_wishlist_item` FOREIGN KEY (`exchange_id`) REFERENCES `exchanges` (`exchange_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exchange_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `exchanges` (
  `exchange_id` int NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `condition_percent` enum('99','90','80','70') NOT NULL COMMENT '99%, 90%, 80%, <70%',
  `defect_types` text COMMENT 'ประเภทตำหนิ (จุดเหลือง, รอยพับ, ฯลฯ)',
  `image_cover` varchar(255) DEFAULT NULL,
  `image_back` varchar(255) DEFAULT NULL,
  `image_inside` varchar(255) DEFAULT NULL,
  `image_edge` varchar(255) DEFAULT NULL,
  `wanted_type` enum('specific','category','any') NOT NULL COMMENT 'เฉพาะเล่ม, ตามหมวดหมู่, เปิดรับทุกข้อเสนอ',
  `wanted_detail` text COMMENT 'บันทึกถึงผู้เสนอ (เช่น ขอสภาพดีไม่มีรอยขีดเขียน)',
  `shipping_methods` text COMMENT 'การจัดส่ง (Thai Post, Flash, J&T, จุดนัดพบ)',
  `story_reason` text COMMENT 'เรื่องราว/ทำไมถึงแลก',
  `owner_lat` decimal(10,7) DEFAULT NULL,
  `owner_lng` decimal(10,7) DEFAULT NULL,
  `owner_province` varchar(100) DEFAULT NULL,
  `status` enum('open','in_progress','completed') DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`exchange_id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `exchanges_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `line_link_states` (
  `state_hash` char(64) NOT NULL,
  `user_id` int NOT NULL,
  `return_origin` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`state_hash`),
  KEY `idx_line_link_expiry` (`expires_at`),
  KEY `fk_line_link_user` (`user_id`),
  CONSTRAINT `fk_line_link_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'รหัสผู้ใช้งานที่รับการแจ้งเตือน',
  `title` varchar(255) NOT NULL COMMENT 'หัวข้อแจ้งเตือน (เช่น อัปเดตสถานะพัสดุ)',
  `message` text NOT NULL COMMENT 'เนื้อหาข้อความ หรือแบบฟอร์มที่ส่งไป',
  `notification_type` enum('order','exchange','system','chat','follow','review') DEFAULT 'system',
  `chat_conversation_id` int DEFAULT NULL,
  `related_user_id` int DEFAULT NULL,
  `related_review_id` int DEFAULT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0' COMMENT 'สถานะการอ่าน (0 = ยังไม่อ่าน, 1 = อ่านแล้ว)',
  `sent_via` varchar(50) DEFAULT NULL COMMENT 'ส่งผ่านช่องทางไหน (เช่น Email, LINE)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_notification_chat` (`chat_conversation_id`),
  KEY `idx_notification_related_user` (`related_user_id`),
  KEY `idx_notifications_review` (`related_review_id`),
  CONSTRAINT `fk_notification_chat` FOREIGN KEY (`chat_conversation_id`) REFERENCES `chat_conversations` (`conversation_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_review` FOREIGN KEY (`related_review_id`) REFERENCES `book_reviews` (`review_id`) ON DELETE SET NULL,
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=335 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `oauth_login_handoffs` (
  `token_hash` char(64) NOT NULL,
  `user_id` int NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token_hash`),
  KEY `idx_oauth_handoff_expiry` (`expires_at`),
  KEY `fk_oauth_handoff_user` (`user_id`),
  CONSTRAINT `fk_oauth_handoff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `oauth_login_states` (
  `state_hash` char(64) NOT NULL,
  `nonce` varchar(128) NOT NULL,
  `return_origin` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`state_hash`),
  KEY `idx_oauth_state_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `order_applied_promotions` (
  `order_promotion_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `promotion_id` int DEFAULT NULL,
  `promotion_name` varchar(150) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_promotion_id`),
  KEY `idx_order_applied_promotions_order` (`order_id`),
  CONSTRAINT `fk_order_applied_promotions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `order_items` (
  `order_item_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL COMMENT 'รหัสออเดอร์หลัก อ้างอิงจากตาราง orders',
  `book_id` int DEFAULT NULL COMMENT 'รหัสหนังสือที่ซื้อ',
  `quantity` int NOT NULL DEFAULT '1' COMMENT 'จำนวนเล่มที่ซื้อในออเดอร์นี้',
  `price_at_purchase` decimal(10,2) NOT NULL COMMENT 'ราคาหนังสือ ณ ตอนที่กดซื้อ (ล็อกราคาไว้เผื่อคนขายเปลี่ยนราคาทีหลัง)',
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `order_shipments` (
  `shipment_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `seller_type` enum('publisher','shop','user') NOT NULL,
  `seller_id` int NOT NULL,
  `courier` varchar(100) NOT NULL,
  `tracking_number` varchar(100) NOT NULL,
  `status` enum('packing','shipped','delivered','returned') NOT NULL DEFAULT 'shipped',
  `shipped_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`shipment_id`),
  UNIQUE KEY `uq_order_seller` (`order_id`,`seller_type`,`seller_id`),
  CONSTRAINT `fk_order_shipments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `orders` (
  `order_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'รหัสผู้ซื้อ',
  `order_number` varchar(50) NOT NULL COMMENT 'เลขออเดอร์ที่ระบบเจนให้อัตโนมัติ',
  `subtotal` decimal(10,2) NOT NULL COMMENT 'ราคารวมสินค้าทั้งหมดก่อนบวกค่าส่ง',
  `shipping_fee` decimal(10,2) DEFAULT '0.00' COMMENT 'ค่าจัดส่งสินค้า',
  `promotion_id` int DEFAULT NULL,
  `promotion_name` varchar(500) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reservation_expires_at` datetime DEFAULT NULL,
  `stock_reserved` tinyint(1) NOT NULL DEFAULT '0',
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL COMMENT 'ยอดเงินสุทธิที่ต้องชำระจริง',
  `shipping_name` varchar(255) NOT NULL COMMENT 'ชื่อ-นามสกุล ผู้รับพัสดุ',
  `shipping_address` text NOT NULL COMMENT 'ที่อยู่จัดส่งโดยละเอียด',
  `shipping_phone` varchar(20) NOT NULL COMMENT 'เบอร์โทรศัพท์ผู้รับ',
  `shipping_method` varchar(100) NOT NULL COMMENT 'วิธีจัดส่ง เช่น Standard Delivery, ไปรษณีย์ไทย',
  `shipping_courier` varchar(100) DEFAULT NULL COMMENT 'บริษัทขนส่ง',
  `tracking_number` varchar(100) DEFAULT NULL COMMENT 'เลขพัสดุ (แอดมินหรือร้านค้ามาเพิ่มให้ทีหลัง)',
  `shipped_at` datetime DEFAULT NULL COMMENT 'วันและเวลาที่ส่งพัสดุจริง',
  `payment_status` enum('pending','reviewing','paid','failed','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'pending=รอชำระ, reviewing=รอตรวจสอบสลิป, paid=ชำระแล้ว',
  `payment_slip_url` varchar(255) DEFAULT NULL COMMENT 'ที่อยู่ไฟล์รูปภาพหลักฐานการโอนเงิน (สลิป)',
  `shipping_status` enum('pending','packing','shipped','delivered','returned','cancelled') DEFAULT 'pending' COMMENT 'สถานะ: รอจัดส่ง, เตรียมพัสดุ, ส่งแล้ว, ได้รับแล้ว, ตีกลับ, ยกเลิก',
  `paid_at` datetime DEFAULT NULL COMMENT 'วัน-เวลาที่ชำระเงินสำเร็จจริง',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  KEY `idx_order_reservation_expiry` (`payment_status`,`stock_reserved`,`reservation_expires_at`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `password_reset_tokens` (
  `reset_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` char(64) NOT NULL,
  `requested_ip` varchar(45) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`,`created_at`),
  KEY `idx_password_reset_expiry` (`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `profile_follows` (
  `follower_user_id` int NOT NULL,
  `target_type` enum('publisher','shop','user') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follower_user_id`,`target_type`,`target_id`),
  KEY `idx_profile_follows_target` (`target_type`,`target_id`,`created_at`),
  CONSTRAINT `fk_profile_follows_user` FOREIGN KEY (`follower_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publisher_posts` (
  `post_id` int NOT NULL AUTO_INCREMENT,
  `promotion_id` int DEFAULT NULL,
  `publisher_id` int NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#FFFFFF',
  `is_bold` tinyint(1) NOT NULL DEFAULT '0',
  `is_italic` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`),
  KEY `idx_publisher_posts_publisher_created` (`publisher_id`,`created_at`),
  KEY `idx_publisher_posts_promotion` (`promotion_id`),
  CONSTRAINT `fk_publisher_posts_publisher` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`publisher_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publisher_staff` (
  `user_id` int NOT NULL,
  `publisher_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`publisher_id`),
  KEY `publisher_id` (`publisher_id`),
  CONSTRAINT `publisher_staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `publisher_staff_ibfk_2` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`publisher_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `publishers` (
  `publisher_id` int NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL COMMENT 'ชื่อนิติบุคคล/บริษัท',
  `description` text COMMENT 'คำอธิบาย เช่น บริษัท รักพิมพ์ พับลิชชิ่ง...',
  `profile_image` varchar(255) DEFAULT NULL,
  `company_document_url` varchar(255) NOT NULL COMMENT 'ที่อยู่ไฟล์หนังสือรับรองบริษัท',
  `contact_name` varchar(255) NOT NULL,
  `contact_email` varchar(255) NOT NULL,
  `contact_position` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `followers_count` int DEFAULT '0' COMMENT 'จำนวนผู้ติดตาม',
  `status` enum('pending','approved','suspended') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`publisher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `readers` (
  `reader_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `full_name` varchar(255) NOT NULL COMMENT 'ชื่อ-สกุล จากหน้าสมัครสมาชิก',
  `display_name` varchar(100) DEFAULT NULL COMMENT 'ชื่อนามแฝง (ถ้ามี)',
  `profile_image` varchar(255) DEFAULT NULL,
  `description` text COMMENT 'คำอธิบายโปรไฟล์/ไบโอ',
  `shipping_address` text,
  PRIMARY KEY (`reader_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `readers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `seller_promotion_targets` (
  `target_id` int NOT NULL AUTO_INCREMENT,
  `promotion_id` int NOT NULL,
  `target_type` enum('category','book') NOT NULL,
  `target_value` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`target_id`),
  UNIQUE KEY `uniq_promotion_target` (`promotion_id`,`target_type`,`target_value`),
  CONSTRAINT `fk_promotion_target_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `seller_promotions` (`promotion_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `seller_promotions` (
  `promotion_id` int NOT NULL AUTO_INCREMENT,
  `seller_type` enum('publisher','shop') NOT NULL,
  `seller_id` int NOT NULL,
  `promotion_name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `scope_type` enum('all','category','books') NOT NULL DEFAULT 'all',
  `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `minimum_quantity` int NOT NULL DEFAULT '1',
  `minimum_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `maximum_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int DEFAULT NULL,
  `usage_count` int NOT NULL DEFAULT '0',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`promotion_id`),
  KEY `idx_seller_promotion_active` (`seller_type`,`seller_id`,`is_active`,`starts_at`,`ends_at`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `shop_posts` (
  `post_id` int NOT NULL AUTO_INCREMENT,
  `promotion_id` int DEFAULT NULL,
  `shop_id` int NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `background_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#FFFFFF',
  `is_bold` tinyint(1) NOT NULL DEFAULT '0',
  `is_italic` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`),
  KEY `idx_shop_posts_shop_created` (`shop_id`,`created_at`),
  KEY `idx_shop_posts_promotion` (`promotion_id`),
  CONSTRAINT `fk_shop_posts_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`shop_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shop_staff` (
  `user_id` int NOT NULL,
  `shop_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`shop_id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `shop_staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `shop_staff_ibfk_2` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`shop_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `shops` (
  `shop_id` int NOT NULL AUTO_INCREMENT,
  `shop_name` varchar(255) NOT NULL,
  `description` text COMMENT 'คำอธิบายร้าน เช่น รวบรวมและคัดสรรผลงาน...',
  `profile_image` varchar(255) DEFAULT NULL,
  `id_card_number` varchar(13) NOT NULL,
  `id_card_document_url` varchar(255) NOT NULL COMMENT 'ที่อยู่ไฟล์บัตรประชาชน',
  `bank_account_name` varchar(255) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `bank_account_number` varchar(50) NOT NULL,
  `followers_count` int DEFAULT '0' COMMENT 'จำนวนผู้ติดตาม',
  `status` enum('pending','approved','suspended') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`shop_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `social_logins` (
  `social_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'อ้างอิงไปยังตาราง users หลัก',
  `provider` enum('google','facebook','line') NOT NULL COMMENT 'ช่องทางที่ใช้ล็อกอิน',
  `provider_uid` varchar(255) NOT NULL COMMENT 'รหัสบัญชีที่ได้จากผู้ให้บริการ (UID)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`social_id`),
  UNIQUE KEY `provider` (`provider`,`provider_uid`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `social_logins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `user_follows` (
  `follower_user_id` int NOT NULL,
  `followed_user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follower_user_id`,`followed_user_id`),
  KEY `idx_user_follows_followed` (`followed_user_id`,`created_at`),
  CONSTRAINT `fk_user_follows_followed` FOREIGN KEY (`followed_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_follows_follower` FOREIGN KEY (`follower_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `bio` text,
  `followers_count` int NOT NULL DEFAULT '0',
  `line_user_id` varchar(255) DEFAULT NULL COMMENT 'รหัส Line User ID สำหรับรับการแจ้งเตือน',
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('reader','shop_admin','publisher_admin','super_admin') DEFAULT 'reader',
  `last_active` datetime DEFAULT NULL COMMENT 'ใช้เช็กว่าแอดมินคนไหนกำลังออนไลน์',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notify_preference` enum('email','line','both','none') DEFAULT 'email' COMMENT 'ช่องทางที่ลูกค้าอยากรับการแจ้งเตือน',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_users_google_id` (`google_id`),
  UNIQUE KEY `uq_users_line_user_id` (`line_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `wishlists` (
  `wishlist_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `book_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`wishlist_id`),
  UNIQUE KEY `uq_wishlist_user_book` (`user_id`,`book_id`),
  KEY `idx_wishlist_book` (`book_id`),
  CONSTRAINT `fk_wishlist_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci