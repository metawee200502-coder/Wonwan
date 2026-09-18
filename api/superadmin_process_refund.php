<?php
/**
 * POST /api/superadmin_process_refund.php
 *
 * Super Admin โอนเงินคืนเข้า Wonwan Wallet ของลูกค้า (Step 3 — Final)
 *
 * Guard: status ต้องเป็น 'shop_received' เท่านั้น
 *
 * Transaction Flow:
 *   1. BEGIN
 *   2. SELECT user_wallets FOR UPDATE (lock row)
 *   3. INSERT / UPDATE user_wallets (balance + refund_amount)
 *   4. INSERT user_wallet_transactions (type = REFUND)
 *   5. ตัดจาก vendor_wallets (ถ้ามี available_balance)
 *   6. UPDATE return_requests status = 'refunded_to_wallet'
 *   7. COMMIT (หรือ ROLLBACK ทั้งหมดถ้า step ใด fail)
 *
 * Body (multipart/form-data):
 *   return_id   int
 *   refund_note string (optional)
 *   csrf_token  string
 */
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// --- Auth: Super Admin Only ---
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}
if (($_SESSION['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง — เฉพาะ Super Admin เท่านั้น']);
    exit;
}

// --- CSRF ---
if (empty($_SESSION['superadmin_return_csrf'])) {
    $_SESSION['superadmin_return_csrf'] = bin2hex(random_bytes(24));
}
if (!hash_equals($_SESSION['superadmin_return_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'คำขอหมดอายุ กรุณาโหลดหน้าใหม่']);
    exit;
}

$adminId    = (int)$_SESSION['user_id'];
$returnId   = (int)($_POST['return_id'] ?? 0);
$refundNote = trim($_POST['refund_note'] ?? '');

if ($returnId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ไม่พบ return_id ที่ถูกต้อง']);
    exit;
}

try {
    // ดึงคำขอ — ต้องอยู่ในสถานะ shop_received เท่านั้น
    $rq = $pdo->prepare(
        'SELECT * FROM return_requests WHERE return_id = ? AND status = ? LIMIT 1'
    );
    $rq->execute([$returnId, 'shop_received']);
    $returnReq = $rq->fetch(PDO::FETCH_ASSOC);

    if (!$returnReq) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'ไม่พบคำขอ หรือยังไม่ได้รับการยืนยันจากร้านค้า']);
        exit;
    }

    $buyerId      = (int)$returnReq['user_id'];
    $orderId      = (int)$returnReq['order_id'];
    $refundAmount = (float)$returnReq['refund_amount'];
    $sellerType   = $returnReq['seller_type'];
    $sellerId     = (int)$returnReq['seller_id'];

    // Fetch order details for the note and deduction logic
    $orderInfoStmt = $pdo->prepare("SELECT order_number, net_payout, payout_status FROM orders WHERE order_id = ?");
    $orderInfoStmt->execute([$orderId]);
    $orderInfo = $orderInfoStmt->fetch(PDO::FETCH_ASSOC);
    $orderNumber = $orderInfo['order_number'] ?: $orderId;
    $netPayout = (float)($orderInfo['net_payout'] ?? 0);
    $payoutStatus = $orderInfo['payout_status'] ?? 'pending';

    // ─── BEGIN TRANSACTION ───
    $pdo->beginTransaction();

    // ── Step 1: Lock & upsert vendor_wallets สำหรับ buyer ──
    $wq = $pdo->prepare("SELECT wallet_id, available_balance FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ? FOR UPDATE");
    $wq->execute([$buyerId]);
    $userWallet = $wq->fetch(PDO::FETCH_ASSOC);

    if (!$userWallet) {
        // สร้าง wallet ใหม่
        $pdo->prepare(
            "INSERT INTO vendor_wallets (seller_type, seller_id, available_balance) VALUES ('user', ?, ?)"
        )->execute([$buyerId, $refundAmount]);
        $userWalletId = (int)$pdo->lastInsertId();
    } else {
        $userWalletId = (int)$userWallet['wallet_id'];
        // เพิ่ม balance (ใช้ SQL arithmetic เพื่อ atomic update)
        $pdo->prepare(
            'UPDATE vendor_wallets SET available_balance = available_balance + ? WHERE wallet_id = ?'
        )->execute([$refundAmount, $userWalletId]);
    }

    // ── Step 2: บันทึก wallet_transactions สำหรับ buyer ──
    $pdo->prepare(
        "INSERT INTO wallet_transactions
            (wallet_id, order_id, type, amount, status, note)
         VALUES (?, ?, 'REFUND', ?, 'SUCCESS', ?)"
    )->execute([
        $userWalletId,
        $orderId,
        $refundAmount,
        'คืนเงินจากการคืนสินค้า คำสั่งซื้อ #' . $orderNumber .
            ($refundNote ? ' — ' . $refundNote : ''),
    ]);

    // ── Step 3: ตัดเงินออกจาก vendor_wallet เฉพาะกรณีที่ร้านค้าได้รับเงินไปแล้ว ──
    if ($payoutStatus === 'paid_to_seller' || $payoutStatus === 'refunded') {
        $vq = $pdo->prepare(
            'SELECT wallet_id, available_balance FROM vendor_wallets
             WHERE seller_type = ? AND seller_id = ? FOR UPDATE'
        );
        $vq->execute([$sellerType, $sellerId]);
        $vendorWallet = $vq->fetch(PDO::FETCH_ASSOC);

        if ($vendorWallet) {
            $deductAmount = min($netPayout, (float)$vendorWallet['available_balance']);
            if ($deductAmount > 0) {
                $pdo->prepare(
                    'UPDATE vendor_wallets SET available_balance = available_balance - ? WHERE wallet_id = ?'
                )->execute([$deductAmount, $vendorWallet['wallet_id']]);

                $pdo->prepare(
                    "INSERT INTO wallet_transactions
                        (wallet_id, order_id, type, amount, status, note)
                     VALUES (?, ?, 'REFUND', ?, 'SUCCESS', ?)"
                )->execute([
                    $vendorWallet['wallet_id'],
                    $orderId,
                    $deductAmount,
                    'หักคืนจาก Return Request #' . $returnId . ' (คำสั่งซื้อ #' . $orderNumber . ')',
                ]);
            }
        }
    }

    // ── Step 4: อัปเดต return_requests → refunded_to_wallet ──
    $pdo->prepare(
        "UPDATE return_requests
         SET status      = 'refunded_to_wallet',
             refund_note = ?,
             refunded_by = ?,
             refunded_at = NOW()
         WHERE return_id = ?"
    )->execute([$refundNote, $adminId, $returnId]);

    // ── Step 5: อัปเดต orders.shipping_status → returned ──
    $pdo->prepare("UPDATE orders SET payout_status = 'refunded', shipping_status = 'returned' WHERE order_id = ?")->execute([$orderId]);

    // ── Notify ผู้ซื้อ ──
    wanawanNotifyUser(
        $pdo, $buyerId,
        '💚 ได้รับเงินคืนเข้า Wonwan Wallet แล้ว',
        'ระบบโอนเงิน ฿' . number_format($refundAmount, 2) .
            ' เข้า Wonwan Wallet ของคุณเรียบร้อยแล้ว สามารถใช้ชำระค่าสินค้าครั้งต่อไปได้ทันที',
        'order',
        ['related_order_id' => $orderId, 'related_return_id' => $returnId]
    );

    // Log
    logAdminAction(
        $pdo,
        $_SESSION['email'] ?? ('uid:' . $adminId),
        'RETURN_REFUND_PROCESSED',
        (string)$returnId,
        'return_id=' . $returnId . ' amount=' . $refundAmount . ' buyer_id=' . $buyerId
    );

    $pdo->commit();

    echo json_encode([
        'ok'      => true,
        'message' => 'โอนเงิน ฿' . number_format($refundAmount, 2) . ' เข้า Wonwan Wallet ของลูกค้าเรียบร้อยแล้ว',
        'amount'  => $refundAmount,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('superadmin_process_refund: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาด — ระบบยกเลิกธุรกรรมทั้งหมดแล้ว กรุณาลองใหม่']);
}
