<?php
require 'C:\laragon\www\wanawan_project\config\config.php';
$stmt = $pdo->query("SELECT shop_id, return_contact_name, return_contact_phone, return_address FROM shops LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query("SELECT publisher_id, company_name, return_contact_name, return_contact_phone, return_address FROM publishers LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
