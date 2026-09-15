<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query('SELECT request_id, status, trade_option FROM exchange_requests ORDER BY request_id DESC LIMIT 1');
print_r($stmt->fetch(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query('SELECT form_id, request_id, owner_id, is_accepted_by_partner FROM trade_condition_forms ORDER BY request_id DESC LIMIT 2');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
