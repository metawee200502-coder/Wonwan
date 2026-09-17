<?php
require __DIR__ . '/../config/config.php';

function getTable($pdo, $table) {
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['Create Table'];
}

$output = getTable($pdo, 'wallet_transactions') . "\n\n" . getTable($pdo, 'vendor_wallets');
file_put_contents(__DIR__.'/table_schema.txt', $output);
echo "Schema saved";
