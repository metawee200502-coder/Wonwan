<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SELECT * FROM vendor_wallets");
echo "VENDOR WALLETS:\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query("SELECT * FROM wallet_transactions LIMIT 10");
echo "\nWALLET TRANSACTIONS:\n";
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
