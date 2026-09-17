<?php
/**
 * POST /api/shop_confirm_received.php
 *
 * ร้านค้า / สำนักพิมพ์ ยืนยันว่าได้รับสินค้าคืนเรียบร้อยแล้ว (Step 2)
 *
 * เมื่อกดยืนยัน → status เปลี่ยนเป็น 'shop_received'
 *                  + Notify Super Admin ให้ดำเนินการโอนเงิน
 *
 * Body (multipart/form-data):
 *   return_id   int
 *   csrf_token  string
 */
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// --- Auth: Shop Admin / Publisher Admin ---
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

$userId     = (int)$_SESSION['user_id'];
$returnId   = (int)($_POST['return_id'] ?? 0);
$sellerType = $role === 'publisher_admin' ? 'publisher' : 'shop';

if ($returnId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    // ดึง seller ID ของ admin นี้
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

    // ดึงคำขอ — ต้องเป็นของร้านนี้ และสถานะ waiting_for_shop เท่านั้น
    $rq = $pdo->prepare(
        'SELECT * FROM return_requests
         WHERE return_id = ?
           AND seller_type = ?
           AND seller_id   = ?
           AND status      = ?
         LIMIT 1'
    );
    $rq->execute([$returnId, $sellerType, $sellerId, 'waiting_for_shop']);
    $returnReq = $rq->fetch(PDO::FETCH_ASSOC);

    if (!$returnReq) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'ไม่พบคำขอ หรือยังไม่ได้รับการอนุมัติจาก Super Admin']);
        exit;
    }

    $buyerId = (int)$returnReq['user_id'];
    $orderId = (int)$returnReq['order_id'];

    $pdo->beginTransaction();

    // เปลี่ยน status → shop_received
    $pdo->prepare(
        "UPDATE return_requests
         SET status           = 'shop_received',
             shop_received_at = NOW(),
             shop_received_by = ?
         WHERE return_id = ?"
    )->execute([$userId, $returnId]);

    // Notify Super Admin ให้ดำเนินการโอนเงิน
    $saStmt = $pdo->query("SELECT user_id FROM users WHERE role = 'super_admin' LIMIT 20");
    foreach ($saStmt->fetchAll(PDO::FETCH_COLUMN) as $saId) {
        wanawanNotifyUser(
            $pdo, (int)$saId,
            '💰 ร้านค้ายืนยันรับสินค้าคืนแล้ว — รอโอนเงิน',
            'ร้านค้ายืนยันรับสินค้าคืนจากคำสั่งซื้อ #' . $orderId . ' เรียบร้อยแล้ว กรุณาดำเนินการโอนเงิน ฿' .
                number_format((float)$returnReq['refund_amount'], 2) . ' เข้า Wonwan Wallet ของลูกค้า',
            'order',
            ['related_order_id' => $orderId, 'related_return_id' => $returnId]
        );
    }

    // Notify ผู้ซื้อว่าร้านค้ารับสินค้าแล้ว รอรับเงินคืน
    wanawanNotifyUser(
        $pdo, $buyerId,
        '📬 ร้านค้าได้รับสินค้าคืนแล้ว',
        'ร้านค้ายืนยันการรับสินค้าคืนแล้ว ระบบกำลังดำเนินการโอนเงิน ฿' .
            number_format((float)$returnReq['refund_amount'], 2) . ' เข้า Wonwan Wallet ของคุณ',
        'order',
        ['related_order_id' => $orderId, 'related_return_id' => $returnId]
    );

    logSellerAction(
        $pdo, $sellerType, $sellerId, $userId,
        'CONFIRM_RETURN_RECEIVED', (string)$returnId,
        'return_id=' . $returnId . ' order_id=' . $orderId
    );

    $pdo->commit();

    echo json_encode([
        'ok'      => true,
        'message' => 'ยืนยันรับสินค้าเคลมเรียบร้อยแล้ว ระบบจะดำเนินการคืนเงินให้ลูกค้าต่อไป',
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('shop_confirm_received: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่']);
}
