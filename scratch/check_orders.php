<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SELECT * FROM orders WHERE order_number LIKE 'EXC-FEE-%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
