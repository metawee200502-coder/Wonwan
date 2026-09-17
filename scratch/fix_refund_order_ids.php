<?php
require __DIR__ . '/../config/config.php';

// Fetch the 2 users that we refunded
$txs = $pdo->query("SELECT transaction_id, wallet_id, amount FROM wallet_transactions WHERE type='REFUND' AND order_id IS NULL")->fetchAll(PDO::FETCH_ASSOC);

foreach ($txs as $tx) {
    // Find the corresponding order for this user and refund amount
    $w = $pdo->prepare("SELECT seller_id FROM vendor_wallets WHERE wallet_id = ?");
    $w->execute([$tx['wallet_id']]);
    $userId = $w->fetchColumn();
    
    $expectedSubtotal = $tx['amount'] + 5; // since refund = subtotal - 5
    
    $o = $pdo->prepare("SELECT order_id FROM orders WHERE user_id = ? AND subtotal = ? AND order_number LIKE 'EXC-FEE-%'");
    $o->execute([$userId, $expectedSubtotal]);
    $orderId = $o->fetchColumn();
    
    if ($orderId) {
        $pdo->prepare("UPDATE wallet_transactions SET order_id = ? WHERE transaction_id = ?")->execute([$orderId, $tx['transaction_id']]);
        echo "Updated tx {$tx['transaction_id']} with order_id $orderId\n";
    }
}
