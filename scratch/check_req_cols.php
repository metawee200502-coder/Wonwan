<?php
require __DIR__ . '/../config/config.php';
$cols = $pdo->query("SHOW COLUMNS FROM exchange_requests")->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
