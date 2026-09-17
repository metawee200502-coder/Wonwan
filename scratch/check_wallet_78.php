<?php
require __DIR__ . '/../config/config.php';
$txs = $pdo->query("SELECT * FROM wallet_transactions WHERE wallet_id = 78")->fetchAll(PDO::FETCH_ASSOC);
print_r($txs);

$orders = $pdo->query("SELECT * FROM orders WHERE user_id = 57 AND shipping_status='delivered'")->fetchAll(PDO::FETCH_ASSOC);
print_r($orders);
