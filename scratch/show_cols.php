<?php
require_once __DIR__.'/../config/config.php';
$cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
