<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SELECT order_number, subtotal, payment_status, shipping_status FROM orders WHERE order_number LIKE 'EX%' LIMIT 10");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents(__DIR__.'/ex_orders.txt', print_r($res, true));
echo "Done";
