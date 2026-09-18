<?php
/**
 * POST /api/submit_return_request.php
 *
 * ผู้ซื้อยื่นคำขอคืนสินค้า
 * Body (multipart/form-data):
 *   order_id       int
 *   reason         'damaged' | 'not_as_described' | 'wrong_item'
 *   return_tracking string
 *   evidence       file (jpg/jpeg/png/webp ≤5MB)
 *   csrf_token     string
 *
 * Flow (v2): initial status = 'pending_super_admin'
 *            Notify → Super Admin (ไม่ใช่ร้านค้า)
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

// --- CSRF ---
if (empty($_SESSION['return_request_csrf'])) {
    $_SESSION['return_request_csrf'] = bin2hex(random_bytes(24));
}
if (!hash_equals($_SESSION['return_request_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'คำขอหมดอายุ กรุณาลองใหม่']);
    exit;
}

$userId         = (int)$_SESSION['user_id'];
$orderId        = (int)($_POST['order_id'] ?? 0);
$reason         = trim($_POST['reason'] ?? '');
$returnTracking = trim($_POST['return_tracking'] ?? '');

$allowedReasons = ['damaged', 'not_as_described', 'wrong_item'];
$errors = [];

// --- Validate inputs ---
if ($orderId <= 0) $errors[] = 'ไม่พบข้อมูลคำสั่งซื้อ';
if (!in_array($reason, $allowedReasons, true)) $errors[] = 'กรุณาเลือกสาเหตุการคืน';
if ($returnTracking === '') $errors[] = 'กรุณากรอกเลขพัสดุที่ส่งคืน';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    // ตรวจว่า order นี้ของผู้ใช้คนนี้จริง และสถานะ delivered
    $orderStmt = $pdo->prepare("SELECT o.order_id, o.shipping_status FROM orders o WHERE o.order_id = ? AND o.user_id = ? LIMIT 1");
    $orderStmt->execute([$orderId, $userId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'ไม่พบคำสั่งซื้อ']);
        exit;
    }
    if ($order['shipping_status'] !== 'delivered') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'สามารถยื่นคำขอคืนสินค้าได้เฉพาะคำสั่งซื้อที่ได้รับสินค้าแล้วเท่านั้น']);
        exit;
    }

    // ตรวจว่ายังไม่เคยยื่นคำขอสำหรับ order นี้
    $existStmt = $pdo->prepare('SELECT return_id FROM return_requests WHERE order_id = ? LIMIT 1');
    $existStmt->execute([$orderId]);
    if ($existStmt->fetchColumn()) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'คำสั่งซื้อนี้มีคำขอคืนสินค้าอยู่แล้ว']);
        exit;
    }

    // ดึงข้อมูลผู้ขายจาก order_items
    $sellerStmt = $pdo->prepare("SELECT b.seller_type, b.seller_id FROM order_items oi JOIN books b ON b.book_id = oi.book_id WHERE oi.order_id = ? LIMIT 1");
    $sellerStmt->execute([$orderId]);
    $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$seller) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'ไม่พบข้อมูลผู้ขาย']);
        exit;
    }

    // --- คำนวณ refund_amount (ดึงจาก total_amount ซึ่งหักส่วนลดแล้ว) ---
    $amountStmt = $pdo->prepare("SELECT total_amount FROM orders WHERE order_id = ?");
    $amountStmt->execute([$orderId]);
    $refundAmount = (float)$amountStmt->fetchColumn();

    // --- File upload ---
    if (empty($_FILES['evidence']) || $_FILES['evidence']['error'] === UPLOAD_ERR_NO_FILE) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'กรุณาแนบรูปหลักฐาน']);
        exit;
    }
    if ($_FILES['evidence']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาดระหว่างอัปโหลดไฟล์']);
        exit;
    }

    $file       = $_FILES['evidence'];
    $maxSize    = 5 * 1024 * 1024; // 5MB
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $finfo      = new finfo(FILEINFO_MIME_TYPE);
    $mime       = $finfo->file($file['tmp_name']);
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['size'] > $maxSize) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'ไฟล์ต้องมีขนาดไม่เกิน 5MB']);
        exit;
    }
    if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'รองรับเฉพาะไฟล์ JPG, PNG หรือ WebP']);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/returns/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename    = uniqid('ret_', true) . '.' . $ext;
    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'บันทึกไฟล์ไม่สำเร็จ']);
        exit;
    }
    $evidenceUrl = 'uploads/returns/' . $filename;

    // --- INSERT (v2 flow: status = pending_super_admin) ---
    $pdo->beginTransaction();
    try {
        $insertStmt = $pdo->prepare(
            "INSERT INTO return_requests
                (order_id, user_id, seller_type, seller_id, reason,
                 evidence_url, return_tracking, refund_amount, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_super_admin')"
        );
        $insertStmt->execute([
            $orderId,
            $userId,
            $seller['seller_type'],
            $seller['seller_id'],
            $reason,
            $evidenceUrl,
            $returnTracking,
            $refundAmount,
        ]);
        $newReturnId = (int)$pdo->lastInsertId();

        // Notify → Super Admin (ไม่ใช่ร้านค้า)
        $saStmt = $pdo->query("SELECT user_id FROM users WHERE role = 'super_admin' LIMIT 20");
        foreach ($saStmt->fetchAll(PDO::FETCH_COLUMN) as $saId) {
            wanawanNotifyUser(
                $pdo, (int)$saId,
                '📦 คำขอคืนสินค้าใหม่ รอการพิจารณา',
                'ลูกค้ายื่นขอคืนสินค้าสำหรับคำสั่งซื้อ #' . $orderId . ' — กรุณาตรวจสอบหลักฐาน',
                'order',
                ['related_order_id' => $orderId, 'related_return_id' => $newReturnId]
            );
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        // ลบไฟล์ที่อัปโหลดแล้วออก (Rollback file upload)
        @unlink($destination);
        throw $e;
    }

    echo json_encode(['ok' => true, 'message' => 'ยื่นคำขอคืนสินค้าเรียบร้อยแล้ว ระบบจะแจ้งผลภายหลัง']);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('submit_return_request: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง']);
}
