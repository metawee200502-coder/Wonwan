<?php
require __DIR__ . '/../config/config.php';

$completed = $pdo->query("
    SELECT r.request_id, t.owner_id, t.required_deposit_amount, t.deposit_status
    FROM exchange_requests r
    JOIN trade_condition_forms t ON t.request_id = r.request_id
    WHERE r.status = 'completed' AND r.trade_option = 'direct_escrow' AND t.required_deposit_amount > 0
")->fetchAll(PDO::FETCH_ASSOC);

$count = 0;
foreach ($completed as $c) {
    $orderNum = 'EXC-FEE-' . $c['request_id'] . '-' . $c['owner_id'];
    
    // Check if it already exists
    $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNum]);
    if ($stmt->rowCount() == 0) {
        // Insert as delivered because the exchange is already completed
        $insert = $pdo->prepare("
            INSERT INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method, created_at, delivered_at) 
            VALUES (?, ?, ?, ?, ?, 'paid', 'delivered', 'ระบบส่วนกลาง', 'N/A', 'N/A', 'N/A', NOW(), NOW())
        ");
        $insert->execute([
            $c['owner_id'], 
            $orderNum, 
            $c['required_deposit_amount'], 
            $c['required_deposit_amount'], 
            $c['required_deposit_amount']
        ]);
        $count++;
    }
}

echo "Backfilled $count fee records for previously completed exchanges.";
