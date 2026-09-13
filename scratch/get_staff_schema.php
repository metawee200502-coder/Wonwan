<?php
require_once __DIR__ . '/../config/config.php';

$describe = function($table) use ($pdo) {
    echo "\nDESCRIBE $table:\n";
    $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "{$c['Field']} ({$c['Type']})\n";
    }
};

$describe('shop_staff');
$describe('publisher_staff');
