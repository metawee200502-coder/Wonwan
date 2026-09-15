<?php
require_once __DIR__ . '/../config/config.php';

$sqlFile = __DIR__ . '/../database/migrations/20260913_p2p_trade_escrow.sql';
if (!file_exists($sqlFile)) {
    die("SQL file not found.");
}

$sql = file_get_contents($sqlFile);

try {
    $pdo->exec($sql);
    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
