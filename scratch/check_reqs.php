<?php
require 'config/config.php';
$stmt = $pdo->query('SELECT request_id, status FROM exchange_requests');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
