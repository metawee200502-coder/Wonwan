<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query('SELECT request_id, status, trade_option, shipping_deadline_days FROM exchange_requests ORDER BY request_id DESC LIMIT 5');
$res1 = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt2 = $pdo->query('SELECT * FROM trade_condition_forms ORDER BY form_id DESC LIMIT 10');
$res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
file_put_contents(__DIR__ . '/check_db_out.txt', json_encode(['req' => $res1, 'forms' => $res2], JSON_PRETTY_PRINT));
echo "Done";
