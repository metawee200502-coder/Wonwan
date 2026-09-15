<?php
require 'config/config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM exchange_requests');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
