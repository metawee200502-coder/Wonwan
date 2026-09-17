<?php
require __DIR__ . '/../config/config.php';
$w = $pdo->query("SELECT * FROM vendor_wallets WHERE seller_id = 57 AND seller_type='user'")->fetch(PDO::FETCH_ASSOC);
print_r($w);
$txs = $pdo->query("SELECT * FROM wallet_transactions WHERE wallet_id = {$w['wallet_id']}")->fetchAll(PDO::FETCH_ASSOC);
print_r($txs);
