<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SELECT user_id, profile_image FROM users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("SELECT shop_id, profile_image FROM shops");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
