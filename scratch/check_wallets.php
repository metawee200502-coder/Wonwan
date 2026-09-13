<?php
require_once __DIR__.'/../config/config.php';
$orders = $pdo->query("SELECT order_id, shipping_status, gp_amount, net_payout FROM orders ORDER BY order_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($orders);

$wallets = $pdo->query("SELECT * FROM vendor_wallets")->fetchAll(PDO::FETCH_ASSOC);
print_r($wallets);

$txs = $pdo->query("SELECT * FROM wallet_transactions")->fetchAll(PDO::FETCH_ASSOC);
print_r($txs);
