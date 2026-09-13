<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Output to a txt file so I can read it
file_put_contents(__DIR__ . '/tables.txt', implode("\n", $tables));

$stmt = $pdo->query("DESCRIBE vendor_wallets");
file_put_contents(__DIR__ . '/vendor_wallets_desc.txt', print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true));
echo "Done";
