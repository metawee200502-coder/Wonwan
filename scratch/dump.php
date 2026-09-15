<?php
require 'config/config.php';
$stmt = $pdo->query('DESCRIBE trade_condition_forms');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
