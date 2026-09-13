<?php
require_once __DIR__.'/../config/config.php';
// Backfill for order 42
$orderId = 42;
$shopIdQuery = $pdo->prepare("SELECT b.seller_type, b.seller_id FROM order_items oi JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=? LIMIT 1");
$shopIdQuery->execute([$orderId]);
$seller = $shopIdQuery->fetch(PDO::FETCH_ASSOC);

if ($seller) {
    $subtotal = array_sum(array_map(function($i){ return (float)($i['price_at_purchase']*$i['quantity']); }, $pdo->query("SELECT price_at_purchase, quantity FROM order_items WHERE order_id=$orderId")->fetchAll(PDO::FETCH_ASSOC)));
    $target = $pdo->query("SELECT shipping_fee FROM orders WHERE order_id=$orderId")->fetch();
    $shippingFee = (float)($target['shipping_fee'] ?? 0);
    $gpAmount = $subtotal * 0.10;
    $netPayout = ($subtotal - $gpAmount) + $shippingFee;

    $pdo->prepare("UPDATE orders SET gp_amount=?, net_payout=? WHERE order_id=?")->execute([$gpAmount, $netPayout, $orderId]);
    echo "Order 42 updated: Subtotal=$subtotal, Net=$netPayout\n";

    // Update wallet
    $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance + ? WHERE seller_type=? AND seller_id=?")->execute([$netPayout, $seller['seller_type'], $seller['seller_id']]);
    
    // Add tx
    $wallet = $pdo->query("SELECT wallet_id FROM vendor_wallets WHERE seller_type='{$seller['seller_type']}' AND seller_id={$seller['seller_id']}")->fetch();
    if($wallet) {
        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, order_id, type, amount, status) VALUES (?, ?, 'EARNING', ?, 'SUCCESS')")->execute([$wallet['wallet_id'], $orderId, $netPayout]);
        echo "Tx added\n";
    }
}
