ALTER TABLE orders
MODIFY payment_status ENUM('pending','reviewing','paid','failed','cancelled')
NOT NULL DEFAULT 'pending'
COMMENT 'pending=รอชำระ, reviewing=รอตรวจสอบสลิป, paid=ชำระแล้ว';
