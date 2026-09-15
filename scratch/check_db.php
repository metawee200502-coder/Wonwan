<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query('SELECT request_id, status, trade_option, shipping_deadline_days FROM exchange_requests ORDER BY request_id DESC LIMIT 5');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query('SELECT * FROM trade_condition_forms ORDER BY form_id DESC LIMIT 5');
echo "\nForms: " . json_encode($stmt2->fetchAll(PDO::FETCH_ASSOC));
