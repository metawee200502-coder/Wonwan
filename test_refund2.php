<?php
session_start();
require_once __DIR__ . '/config/config.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'super_admin';
$_SESSION['email'] = 'test@example.com';
$returnId = 1; // Assuming there is a return_id = 1. Let's find one that is shop_received
$rq = $pdo->prepare('SELECT * FROM return_requests WHERE status = "shop_received" LIMIT 1');
$rq->execute();
$returnReq = $rq->fetch(PDO::FETCH_ASSOC);
if (!$returnReq) {
    die("No shop_received requests found.");
}
$returnId = $returnReq['return_id'];
$buyerId      = (int)$returnReq['user_id'];
$orderId      = (int)$returnReq['order_id'];
$refundAmount = (float)$returnReq['refund_amount'];
$sellerType   = $returnReq['seller_type'];
$sellerId     = (int)$returnReq['seller_id'];

try {
    $pdo->beginTransaction();
    $wq = $pdo->prepare('SELECT wallet_id, balance FROM user_wallets WHERE user_id = ? FOR UPDATE');
    $wq->execute([$buyerId]);
    $userWallet = $wq->fetch(PDO::FETCH_ASSOC);

    if (!$userWallet) {
        $pdo->prepare('INSERT INTO user_wallets (user_id, balance) VALUES (?, ?)')->execute([$buyerId, $refundAmount]);
        $userWalletId = (int)$pdo->lastInsertId();
    } else {
        $userWalletId = (int)$userWallet['wallet_id'];
        $pdo->prepare('UPDATE user_wallets SET balance = balance + ? WHERE wallet_id = ?')->execute([$refundAmount, $userWalletId]);
    }

    $pdo->prepare("INSERT INTO user_wallet_transactions (wallet_id, order_id, type, amount, note) VALUES (?, ?, 'REFUND', ?, ?)")->execute([$userWalletId, $orderId, $refundAmount, 'test note']);

    $vq = $pdo->prepare('SELECT wallet_id, available_balance FROM vendor_wallets WHERE seller_type = ? AND seller_id = ? FOR UPDATE');
    $vq->execute([$sellerType, $sellerId]);
    $vendorWallet = $vq->fetch(PDO::FETCH_ASSOC);

    if ($vendorWallet) {
        $deductAmount = min($refundAmount, (float)$vendorWallet['available_balance']);
        if ($deductAmount > 0) {
            $pdo->prepare('UPDATE vendor_wallets SET available_balance = available_balance - ? WHERE wallet_id = ?')->execute([$deductAmount, $vendorWallet['wallet_id']]);
            $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, order_id, type, amount, status, note) VALUES (?, ?, 'REFUND', ?, 'SUCCESS', ?)")->execute([$vendorWallet['wallet_id'], $orderId, $deductAmount, 'test note']);
        }
    }

    $pdo->prepare("UPDATE return_requests SET status = 'refunded_to_wallet', refund_note = ?, refunded_by = ?, refunded_at = NOW() WHERE return_id = ?")->execute(['test note', 1, $returnId]);

    wanawanNotifyUser($pdo, $buyerId, 'title', 'msg', 'order', ['related_order_id' => $orderId]);
    logAdminAction($pdo, 'test@example.com', 'RETURN_REFUND_PROCESSED', (string)$returnId, 'test log');

    $pdo->rollBack(); // Rollback so we don't actually change data
    echo "SUCCESS";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . " on line " . $e->getLine();
}
