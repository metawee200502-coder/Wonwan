<?php
require __DIR__ . '/../config/config.php';

$res = $pdo->query("SELECT * FROM vendor_wallets WHERE seller_type='user' AND seller_id IN (56, 57)")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

$tx = $pdo->query("SELECT * FROM wallet_transactions WHERE wallet_id IN (SELECT wallet_id FROM vendor_wallets WHERE seller_type='user' AND seller_id IN (56, 57))")->fetchAll(PDO::FETCH_ASSOC);
print_r($tx);
