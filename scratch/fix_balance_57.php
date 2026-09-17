<?php
require __DIR__ . '/../config/config.php';
$pdo->query("UPDATE vendor_wallets SET available_balance = 145.00 WHERE seller_type='user' AND seller_id=57");
echo "Corrected user 57 balance to 145.00\n";
