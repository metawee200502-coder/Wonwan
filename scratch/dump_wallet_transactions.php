<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("DESCRIBE wallet_transactions");
file_put_contents(__DIR__ . '/wallet_transactions_desc.txt', print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true));
echo "Done";
