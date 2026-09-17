<?php
/**
 * GET /api/get_shop_return_address.php?seller_type=shop&seller_id=5
 *
 * ดึงข้อมูลที่อยู่รับคืนสินค้าของผู้ขาย (shop / publisher / user)
 * ต้องล็อกอินแล้วเท่านั้น
 */
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

$sellerType = trim($_GET['seller_type'] ?? '');
$sellerId   = (int)($_GET['seller_id'] ?? 0);

if (!in_array($sellerType, ['shop', 'publisher', 'user'], true) || $sellerId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'พารามิเตอร์ไม่ถูกต้อง']);
    exit;
}

try {
    if ($sellerType === 'shop') {
        $stmt = $pdo->prepare('SELECT shop_name AS seller_name, return_contact_name, return_contact_phone, return_address FROM shops WHERE shop_id = ? LIMIT 1');
    } elseif ($sellerType === 'publisher') {
        $stmt = $pdo->prepare('SELECT company_name AS seller_name, return_contact_name, return_contact_phone, return_address FROM publishers WHERE publisher_id = ? LIMIT 1');
    } else {
        // user-seller: ใช้ display_name แทน, ไม่มี return_address (แสดงว่า "ยังไม่ตั้งค่า")
        $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(display_name,''), email) AS seller_name, NULL AS return_contact_name, NULL AS return_contact_phone, NULL AS return_address FROM users WHERE user_id = ? LIMIT 1");
    }

    $stmt->execute([$sellerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'ไม่พบข้อมูลผู้ขาย']);
        exit;
    }

    echo json_encode([
        'ok'                  => true,
        'seller_name'         => (string)($row['seller_name'] ?? ''),
        'return_contact_name' => (string)($row['return_contact_name'] ?? ''),
        'return_contact_phone'=> (string)($row['return_contact_phone'] ?? ''),
        'return_address'      => (string)($row['return_address'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่']);
}
