<?php
require __DIR__ . '/../config/config.php';
$w = $pdo->query("SELECT * FROM vendor_wallets WHERE seller_id = 56 AND seller_type='user'")->fetch(PDO::FETCH_ASSOC);
print_r($w);
