<?php
require __DIR__ . '/../config/config.php';
$completed = $pdo->query("
    SELECT r.request_id, r.trade_option, e.status, t.owner_id, t.required_deposit_amount, t.deposit_status
    FROM exchanges e
    JOIN exchange_requests r ON e.request_id = r.request_id
    JOIN trade_condition_forms t ON t.request_id = r.request_id
    WHERE e.status = 'completed' AND r.trade_option = 'direct_escrow' AND t.required_deposit_amount > 0
")->fetchAll(PDO::FETCH_ASSOC);

print_r($completed);
