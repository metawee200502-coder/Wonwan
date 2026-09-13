<?php
require_once __DIR__ . '/../config/config.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n";
print_r($tables);

$describe = function($table) use ($pdo) {
    echo "\nDESCRIBE $table:\n";
    $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "{$c['Field']} ({$c['Type']})\n";
    }
};

$describe('users');
$describe('shops');
$describe('publishers');
