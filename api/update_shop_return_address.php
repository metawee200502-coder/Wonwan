<?php
/**
 * POST /api/update_shop_return_address.php
 *
 * Shop Admin / Publisher Admin อัปเดตที่อยู่รับคืนสินค้า
 * Body (form-data หรือ JSON):
 *   return_contact_name   string
 *   return_contact_phone  string
 *   return_address        string  (ที่อยู่เต็ม)
 */
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// --- Auth: เฉพาะ shop_admin / publisher_admin ---
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

$userId = (int)$_SESSION['user_id'];
$sellerType = $role === 'publisher_admin' ? 'publisher' : 'shop';

// --- ดึง seller ID จาก staff table ---
try {
    if ($sellerType === 'publisher') {
        $q = $pdo->prepare('SELECT publisher_id FROM publisher_staff WHERE user_id = ? LIMIT 1');
    } else {
        $q = $pdo->prepare('SELECT shop_id FROM shop_staff WHERE user_id = ? LIMIT 1');
    }
    $q->execute([$userId]);
    $sellerId = (int)$q->fetchColumn();

    if (!$sellerId) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'ไม่พบข้อมูลร้านค้า']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาด']);
    exit;
}

// รับ body (รองรับทั้ง form-data และ JSON)
$input = $_POST;
if (empty($input)) {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

$contactName  = trim($input['return_contact_name']  ?? '');
$contactPhone = trim($input['return_contact_phone'] ?? '');
$address      = trim($input['return_address']       ?? '');

$errors = [];
if ($contactName === '')  $errors[] = 'กรุณากรอกชื่อผู้ติดต่อ';
if ($contactPhone === '') $errors[] = 'กรุณากรอกเบอร์โทร';
if ($address === '')      $errors[] = 'กรุณากรอกที่อยู่รับสินค้าคืน';
if (!preg_match('/^0\d{8,9}$/', $contactPhone)) $errors[] = 'เบอร์โทรไม่ถูกต้อง (ตัวอย่าง: 0812345678)';

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    if ($sellerType === 'publisher') {
        $upd = $pdo->prepare('UPDATE publishers SET return_contact_name = ?, return_contact_phone = ?, return_address = ? WHERE publisher_id = ?');
    } else {
        $upd = $pdo->prepare('UPDATE shops SET return_contact_name = ?, return_contact_phone = ?, return_address = ? WHERE shop_id = ?');
    }
    $upd->execute([$contactName, $contactPhone, $address, $sellerId]);

    // Log action
    logSellerAction($pdo, $sellerType, $sellerId, $userId, 'UPDATE_RETURN_ADDRESS', (string)$sellerId, 'อัปเดตที่อยู่รับคืนสินค้า');

    echo json_encode(['ok' => true, 'message' => 'บันทึกที่อยู่รับคืนสินค้าเรียบร้อยแล้ว']);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('update_shop_return_address: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่']);
}
