<?php
/**
 * POST /api/superadmin_review_return.php
 *
 * Super Admin ตรวจสอบและ Approve / Reject คำขอคืนสินค้า (Step 1)
 *
 * เมื่อ Approve → status เปลี่ยนเป็น 'waiting_for_shop'
 *                  + Notify ร้านค้าให้รอรับสินค้าคืน
 * เมื่อ Reject  → status เปลี่ยนเป็น 'rejected'
 *                  + Notify ผู้ซื้อพร้อมเหตุผล
 *
 * Body (multipart/form-data):
 *   return_id   int
 *   action      'approve' | 'reject'
 *   admin_note  string (optional — แสดงให้ผู้ซื้อเห็น)
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

$adminId  = (int)$_SESSION['user_id'];
$returnId = (int)($_POST['return_id'] ?? 0);
$action   = trim($_POST['action'] ?? '');
$note     = trim($_POST['admin_note'] ?? '');

if (!in_array($action, ['approve', 'reject'], true) || $returnId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    // ดึงคำขอ — ต้องอยู่ในสถานะ pending_super_admin เท่านั้น
    $rq = $pdo->prepare(
        'SELECT * FROM return_requests WHERE return_id = ? AND status = ? LIMIT 1'
    );
    $rq->execute([$returnId, 'pending_super_admin']);
    $returnReq = $rq->fetch(PDO::FETCH_ASSOC);

    if (!$returnReq) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'ไม่พบคำขอ หรือดำเนินการไปแล้ว']);
        exit;
    }

    $buyerId = (int)$returnReq['user_id'];
    $orderId = (int)$returnReq['order_id'];

    $pdo->beginTransaction();

    if ($action === 'approve') {
        // ─── APPROVE: เปลี่ยน status → waiting_for_shop ───
        $pdo->prepare(
            "UPDATE return_requests
             SET status = 'waiting_for_shop',
                 admin_note = ?,
                 super_admin_reviewed_by = ?,
                 super_admin_reviewed_at = NOW()
             WHERE return_id = ?"
        )->execute([$note, $adminId, $returnId]);

        // Notify ร้านค้าให้รอรับสินค้าคืน
        $shopUserIds = wanawanSellerUserIds(
            $pdo,
            $returnReq['seller_type'],
            (int)$returnReq['seller_id']
        );
        foreach ($shopUserIds as $shopUserId) {
            wanawanNotifyUser(
                $pdo, $shopUserId,
                '📦 มีสินค้าคืนที่ Super Admin อนุมัติแล้ว',
                'กรุณาเตรียมรับสินค้าคืนจากลูกค้าสำหรับคำสั่งซื้อ #' . $orderId . ' และกดยืนยันเมื่อได้รับสินค้าเรียบร้อย',
                'order',
                ['related_order_id' => $orderId, 'related_return_id' => $returnId]
            );
        }

        // Notify ผู้ซื้อว่าคำขอผ่านการพิจารณาแล้ว (รอส่งของคืน)
        wanawanNotifyUser(
            $pdo, $buyerId,
            '✅ คำขอคืนสินค้าได้รับการอนุมัติ',
            'กรุณาส่งพัสดุคืนไปยังร้านค้าตามที่อยู่ที่ระบุ — ระบบจะโอนเงินคืนหลังร้านค้ายืนยันรับสินค้า' .
                ($note ? ' หมายเหตุ: ' . $note : ''),
            'order',
            ['related_order_id' => $orderId, 'related_return_id' => $returnId]
        );

        $message = 'อนุมัติคำขอแล้ว รอร้านค้ายืนยันรับสินค้าคืน';

    } else {
        // ─── REJECT: เปลี่ยน status → rejected ───
        $pdo->prepare(
            "UPDATE return_requests
             SET status = 'rejected',
                 admin_note = ?,
                 super_admin_reviewed_by = ?,
                 super_admin_reviewed_at = NOW()
             WHERE return_id = ?"
        )->execute([$note, $adminId, $returnId]);

        // Notify ผู้ซื้อว่าถูกปฏิเสธ พร้อมเหตุผล
        wanawanNotifyUser(
            $pdo, $buyerId,
            '❌ คำขอคืนสินค้าถูกปฏิเสธ',
            'ระบบตรวจสอบหลักฐานแล้ว ไม่สามารถอนุมัติคำขอได้' .
                ($note ? ' เหตุผล: ' . $note : ''),
            'order',
            ['related_order_id' => $orderId, 'related_return_id' => $returnId]
        );

        $message = 'ปฏิเสธคำขอคืนสินค้าแล้ว';
    }

    // Log ใน admin_action_logs
    logAdminAction(
        $pdo,
        $_SESSION['email'] ?? ('uid:' . $adminId),
        'RETURN_' . strtoupper($action),
        (string)$returnId,
        'return_id=' . $returnId . ($note ? ' note=' . $note : '')
    );

    $pdo->commit();

    echo json_encode(['ok' => true, 'message' => $message]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('superadmin_review_return: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่']);
}
