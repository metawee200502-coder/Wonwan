<?php
require __DIR__ . '/../config/config.php';

$res = $pdo->query("SELECT order_number, shipping_status, subtotal, user_id FROM orders WHERE order_number LIKE 'EXC-FEE-%' ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
