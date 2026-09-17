<?php
require __DIR__ . '/../config/config.php';

$res = $pdo->query("
    SELECT r.request_id, r.status, t.deposit_status
    FROM exchange_requests r
    JOIN trade_condition_forms t ON t.request_id = r.request_id
    WHERE r.trade_option = 'admin_qc'
")->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
