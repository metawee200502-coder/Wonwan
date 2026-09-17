<?php
require "C:\laragon\www\wanawan_project\config\config.php";
$stmt = $pdo->prepare('SELECT p.publisher_id id, p.company_name name, p.return_contact_name, p.return_contact_phone, p.return_address FROM publisher_staff ps JOIN publishers p ON p.publisher_id = ps.publisher_id WHERE ps.user_id = ? LIMIT 1');
$stmt->execute([22]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
