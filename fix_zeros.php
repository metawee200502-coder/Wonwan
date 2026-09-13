<?php
require 'c:\laragon\www\wanawan_project\config\config.php';

try {
    $pdo->beginTransaction();
    
    // Update orders where net_payout is 0, computing from subtotal and shipping_fee
    $stmt = $pdo->prepare("UPDATE orders o JOIN order_items oi ON o.order_id=oi.order_id JOIN books b ON b.book_id=oi.book_id SET o.gp_amount = o.subtotal * CASE WHEN b.seller_type = 'user' THEN 0.15 ELSE 0.10 END, o.net_payout = o.subtotal - (o.subtotal * CASE WHEN b.seller_type = 'user' THEN 0.15 ELSE 0.10 END) + o.shipping_fee WHERE o.net_payout = 0 AND o.subtotal > 0");
    $stmt->execute();
    
    $stmt2 = $pdo->prepare("UPDATE wallet_transactions wt JOIN orders o ON wt.order_id = o.order_id SET wt.amount = o.net_payout WHERE wt.type='EARNING' AND wt.amount = 0 AND o.net_payout > 0");
    $stmt2->execute();
    
    // Also re-sync wallet balances
    $wallets = $pdo->query("SELECT * FROM vendor_wallets")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($wallets as $wallet) {
        $walletId = $wallet['wallet_id'];
        $sellerType = $wallet['seller_type'];
        $sellerId = $wallet['seller_id'];
        
        $earningsStmt = $pdo->prepare("SELECT SUM(o.net_payout) FROM orders o JOIN order_items oi ON o.order_id=oi.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type=? AND b.seller_id=? AND o.shipping_status='delivered'");
        $earningsStmt->execute([$sellerType, $sellerId]);
        $totalEarnings = (float)$earningsStmt->fetchColumn();
        
        $withdrawStmt = $pdo->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE wallet_id=? AND type='WITHDRAWAL' AND status IN ('PENDING', 'SUCCESS')");
        $withdrawStmt->execute([$walletId]);
        $totalWithdrawn = (float)$withdrawStmt->fetchColumn();
        
        $newBalance = max(0, $totalEarnings - $totalWithdrawn);
        $pdo->prepare("UPDATE vendor_wallets SET available_balance=? WHERE wallet_id=?")->execute([$newBalance, $walletId]);
    }

    $pdo->commit();
    echo "Fixed everything.";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
