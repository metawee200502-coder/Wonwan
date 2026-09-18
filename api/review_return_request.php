<?php
/**
 * POST /api/review_return_request.php
 *
 * Shop Admin / Publisher Admin Approve หรือ Reject คำขอคืนสินค้า
 * เมื่อ Approve → โอนเงินคืนเข้า Wallet ผู้ซื้อ + ตัดออกจาก vendor_wallet
 * Body:
 *   return_id   int
 *   action      'approve' | 'reject'
 *   admin_note  string (optional)
 *   csrf_token  string
 */
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// --- Auth ---
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['shop_admin', 'publisher_admin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

// --- CSRF ---
if (empty($_SESSION['admin_return_csrf'])) {
    $_SESSION['admin_return_csrf'] = bin2hex(random_bytes(24));
}
if (!hash_equals($_SESSION['admin_return_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'คำขอหมดอายุ กรุณาโหลดหน้าใหม่']);
    exit;
}

$userId    = (int)$_SESSION['user_id'];
$returnId  = (int)($_POST['return_id'] ?? 0);
$action    = trim($_POST['action'] ?? '');
$adminNote = trim($_POST['admin_note'] ?? '');
$sellerType = $role === 'publisher_admin' ? 'publisher' : 'shop';

if (!in_array($action, ['approve', 'reject'], true) || $returnId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    // ดึง seller ID ของ admin
    if ($sellerType === 'publisher') {
        $sq = $pdo->prepare('SELECT publisher_id FROM publisher_staff WHERE user_id = ? LIMIT 1');
    } else {
        $sq = $pdo->prepare('SELECT shop_id FROM shop_staff WHERE user_id = ? LIMIT 1');
    }
    $sq->execute([$userId]);
    $sellerId = (int)$sq->fetchColumn();

    if (!$sellerId) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'ไม่พบข้อมูลร้านค้า']);
        exit;
    }

    // ดึง return_request — ตรวจว่าเป็นของร้านนี้จริง
    $rq = $pdo->prepare('SELECT * FROM return_requests WHERE return_id = ? AND seller_type = ? AND seller_id = ? AND status = ? LIMIT 1');
    $rq->execute([$returnId, $sellerType, $sellerId, 'pending']);
    $returnReq = $rq->fetch(PDO::FETCH_ASSOC);

    if (!$returnReq) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'ไม่พบคำขอหรือดำเนินการไปแล้ว']);
        exit;
    }

    $pdo->beginTransaction();

    // อัปเดต status ของ return_request
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $upd = $pdo->prepare("UPDATE return_requests SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE return_id = ?");
    $upd->execute([$newStatus, $adminNote, $userId, $returnId]);

    if ($action === 'approve') {
        $refundAmount = (float)$returnReq['refund_amount'];
        $buyerId      = (int)$returnReq['user_id'];
        $orderId      = (int)$returnReq['order_id'];

        $orderInfoStmt = $pdo->prepare("SELECT net_payout, payout_status FROM orders WHERE order_id = ?");
        $orderInfoStmt->execute([$orderId]);
        $orderInfo = $orderInfoStmt->fetch(PDO::FETCH_ASSOC);
        $netPayout = (float)($orderInfo['net_payout'] ?? 0);
        $payoutStatus = $orderInfo['payout_status'] ?? 'pending';

        // ---- 1. โอนเงินคืนเข้า buyer wallet ----
        // ดึง (หรือสร้าง) user wallet — ใช้ตาราง user_wallets ถ้ามี หรือ vendor_wallets ถ้า user-seller
        // ตรวจว่ามีตาราง user_wallets ก่อน (ถ้าไม่มีสร้างใน buyer_wallet)
        // สำหรับ wanawan ใช้โมเดล: buyer refund ไปที่ users.wallet_balance (ถ้ามี) 
        // หรือสร้าง credit record ใน user_wallet_transactions

        // ตรวจตาราง vendor_wallets สำหรับ buyer
        $wq = $pdo->prepare("SELECT wallet_id, available_balance FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ? FOR UPDATE");
        $wq->execute([$buyerId]);
        $userWallet = $wq->fetch(PDO::FETCH_ASSOC);

        if (!$userWallet) {
            $pdo->prepare("INSERT INTO vendor_wallets (seller_type, seller_id, available_balance) VALUES ('user', ?, ?)")->execute([$buyerId, $refundAmount]);
            $userWalletId = (int)$pdo->lastInsertId();
        } else {
            $userWalletId = (int)$userWallet['wallet_id'];
            $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance + ? WHERE wallet_id = ?")->execute([$refundAmount, $userWalletId]);
        }

        // บันทึก transaction ของผู้ซื้อ
        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, order_id, type, amount, status, note) VALUES (?, ?, 'REFUND', ?, 'SUCCESS', 'คืนเงินจากการคืนสินค้า')")->execute([$userWalletId, $orderId, $refundAmount]);

        // ---- 2. ตัดเงินออกจาก vendor wallet เฉพาะกรณีที่ร้านค้าได้รับเงินไปแล้ว ----
        if ($payoutStatus === 'paid_to_seller' || $payoutStatus === 'refunded') {
            $vq = $pdo->prepare("SELECT wallet_id, available_balance FROM vendor_wallets WHERE seller_type = ? AND seller_id = ? FOR UPDATE");
            $vq->execute([$sellerType, $sellerId]);
            $vendorWallet = $vq->fetch(PDO::FETCH_ASSOC);

            if ($vendorWallet) {
                $deductAmount = min($netPayout, (float)$vendorWallet['available_balance']);
                if ($deductAmount > 0) {
                    $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance - ? WHERE wallet_id = ?")->execute([$deductAmount, $vendorWallet['wallet_id']]);
                    $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, order_id, type, amount, status, note) VALUES (?, ?, 'REFUND', ?, 'SUCCESS', 'หักคืนจาก Return Request')")->execute([$vendorWallet['wallet_id'], $orderId, $deductAmount]);
                }
            }
        }

        // Notify ผู้ซื้อ: approved
        wanawanNotifyUser($pdo, $buyerId, '✅ คำขอคืนสินค้าได้รับการอนุมัติ', 'ระบบจะโอนเงินคืน ฿' . number_format($refundAmount, 2) . ' เข้ากระเป๋าของคุณแล้ว', 'order', ['related_order_id' => $orderId]);

    } else {
        // Notify ผู้ซื้อ: rejected
        $buyerId = (int)$returnReq['user_id'];
        $orderId = (int)$returnReq['order_id'];
        $note    = $adminNote !== '' ? (' เหตุผล: ' . $adminNote) : '';
        wanawanNotifyUser($pdo, $buyerId, '❌ คำขอคืนสินค้าถูกปฏิเสธ', 'ร้านค้าไม่อนุมัติคำขอคืนสินค้า' . $note, 'order', ['related_order_id' => $orderId]);
    }

    logSellerAction($pdo, $sellerType, $sellerId, $userId, 'REVIEW_RETURN_' . strtoupper($action), (string)$returnId, 'return_id=' . $returnId . ($adminNote ? ' note=' . $adminNote : ''));

    $pdo->commit();

    $msg = $action === 'approve' ? 'อนุมัติและโอนเงินคืนให้ผู้ซื้อแล้ว' : 'ปฏิเสธคำขอคืนสินค้าแล้ว';
    echo json_encode(['ok' => true, 'message' => $msg]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('review_return_request: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
