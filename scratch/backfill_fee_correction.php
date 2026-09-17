<?php
require __DIR__ . '/../config/config.php';

$orders = $pdo->query("SELECT o.order_id, o.order_number, o.user_id, o.subtotal, o.gp_amount, r.trade_option FROM orders o JOIN exchange_requests r ON o.order_number = CONCAT('EXC-FEE-', r.request_id, '-', o.user_id) WHERE o.order_number LIKE 'EXC-FEE-%' AND r.trade_option = 'direct_escrow'")->fetchAll(PDO::FETCH_ASSOC);

foreach ($orders as $o) {
    if ($o['gp_amount'] != 5) {
        $pdo->prepare("UPDATE orders SET gp_amount = 5 WHERE order_id = ?")->execute([$o['order_id']]);
        echo "Updated order {$o['order_number']} gp_amount to 5\n";
    }
    
    // Check wallet balance
    $wallet = $pdo->prepare("SELECT wallet_id, escrow_balance FROM vendor_wallets WHERE seller_type='user' AND seller_id=?");
    $wallet->execute([$o['user_id']]);
    $w = $wallet->fetch();
    
    if ($w && $w['escrow_balance'] >= $o['subtotal']) {
        $refund = $o['subtotal'] - 5;
        // Deduct escrow, add available
        $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, available_balance = available_balance + ? WHERE wallet_id = ?")->execute([$o['subtotal'], $refund, $w['wallet_id']]);
        
        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, status, created_at) VALUES (?, 'EARNING', ?, 'SUCCESS', NOW())")->execute([$w['wallet_id'], $refund]);
        
        echo "Refunded $refund to user {$o['user_id']} and deducted {$o['subtotal']} from escrow.\n";
    }
}
echo "Correction completed.\n";
