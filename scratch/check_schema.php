<?php
require 'config/config.php';
global $pdo;

echo "Orders columns:\n";
$stmt = $pdo->query('SHOW COLUMNS FROM orders');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

echo "\nOrder Batches columns:\n";
$stmt = $pdo->query('SHOW COLUMNS FROM order_batches');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
