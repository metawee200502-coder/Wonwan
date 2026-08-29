-- Migration: เพิ่มคอลัมน์ตำแหน่ง GPS สำหรับ books (มือสอง) และ exchanges (แลกเปลี่ยน)
-- Date: 2026-08-29

ALTER TABLE `books`
  ADD COLUMN IF NOT EXISTS `seller_lat`      DECIMAL(10,7) NULL DEFAULT NULL COMMENT 'Latitude ของผู้โพส' AFTER `shipping_methods`,
  ADD COLUMN IF NOT EXISTS `seller_lng`      DECIMAL(10,7) NULL DEFAULT NULL COMMENT 'Longitude ของผู้โพส' AFTER `seller_lat`,
  ADD COLUMN IF NOT EXISTS `seller_province` VARCHAR(100)  NULL DEFAULT NULL COMMENT 'ชื่อจังหวัดของผู้โพส (reverse geocode)' AFTER `seller_lng`;

ALTER TABLE `exchanges`
  ADD COLUMN IF NOT EXISTS `owner_lat`      DECIMAL(10,7) NULL DEFAULT NULL COMMENT 'Latitude ของผู้โพส' AFTER `story_reason`,
  ADD COLUMN IF NOT EXISTS `owner_lng`      DECIMAL(10,7) NULL DEFAULT NULL COMMENT 'Longitude ของผู้โพส' AFTER `owner_lat`,
  ADD COLUMN IF NOT EXISTS `owner_province` VARCHAR(100)  NULL DEFAULT NULL COMMENT 'ชื่อจังหวัดของผู้โพส (reverse geocode)' AFTER `owner_lng`;
