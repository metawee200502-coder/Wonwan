<?php
require __DIR__ . '/../config/config.php';

$res = $pdo->query("
    SELECT o.order_number, r.trade_option, o.gp_amount
    FROM orders o
    JOIN exchange_requests r ON o.order_number = CONCAT('EXC-FEE-', r.request_id, '-', o.user_id)
    WHERE r.trade_option = 'admin_qc'
")->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
