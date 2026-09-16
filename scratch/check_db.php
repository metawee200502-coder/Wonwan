<?php
require_once __DIR__ . '/../config/config.php';
$q = $pdo->query("SHOW COLUMNS FROM trade_condition_forms LIKE 'deposit_status'");
$col = $q->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($col);
echo "</pre>";
