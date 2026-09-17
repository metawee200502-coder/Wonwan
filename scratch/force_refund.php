<?php
require __DIR__ . '/../config/config.php';

$orders = $pdo->query("SELECT o.order_id, o.order_number, o.user_id, o.subtotal, o.gp_amount, r.trade_option FROM orders o JOIN exchange_requests r ON o.order_number = CONCAT('EXC-FEE-', r.request_id, '-', o.user_id) WHERE o.order_number LIKE 'EXC-FEE-%' AND r.trade_option = 'direct_escrow' AND o.shipping_status = 'delivered'")->fetchAll(PDO::FETCH_ASSOC);

foreach ($orders as $o) {
    // Check if we already refunded this order
    $txCheck = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id IN (SELECT wallet_id FROM vendor_wallets WHERE seller_type='user' AND seller_id=?) AND (type='EARNING' OR type='REFUND') AND amount = ?");
    $refund = $o['subtotal'] - 5;
    $txCheck->execute([$o['user_id'], $refund]);
    
    if ($txCheck->rowCount() == 0) {
        $wallet = $pdo->prepare("SELECT wallet_id FROM vendor_wallets WHERE seller_type='user' AND seller_id=?");
        $wallet->execute([$o['user_id']]);
        $w = $wallet->fetch();
        
        if ($w) {
            $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance + ? WHERE wallet_id = ?")->execute([$refund, $w['wallet_id']]);
            $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, status, created_at) VALUES (?, 'REFUND', ?, 'SUCCESS', NOW())")->execute([$w['wallet_id'], $refund]);
            echo "Refunded $refund to user {$o['user_id']}\n";
        }
    } else {
        echo "Already refunded user {$o['user_id']}\n";
    }
}
echo "Correction completed.\n";
