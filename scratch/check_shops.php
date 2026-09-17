<?php
require 'C:\laragon\www\wanawan_project\config\config.php';
$stmt = $pdo->query("SELECT shop_id, shop_name, return_contact_name, return_contact_phone, return_address FROM shops WHERE return_address LIKE '%ทดสอบระบบคืนของคืนเงิน%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
