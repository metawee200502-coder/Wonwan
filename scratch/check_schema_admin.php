<?php
require 'config/config.php';
$stmt = $pdo->query('DESCRIBE exchange_requests');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query('DESCRIBE trade_condition_forms');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
