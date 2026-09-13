<?php
require_once __DIR__ . '/../config/config.php';

// สมมติว่ามีการ Auth เช็ค shop_id ของร้านค้าที่ล็อกอินอยู่
// $shopId = $_SESSION['shop_id']; // ตัวอย่าง

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? null;
    $trackingNumber = $_POST['tracking_number'] ?? null;
    // $shopId = $_POST['shop_id'] ?? null; // กรณีรับผ่านฟอร์มเพื่อการทดสอบ
    
    if (!$orderId || !$trackingNumber) {
        echo json_encode(['success' => false, 'message' => 'Missing order_id or tracking_number']);
        exit;
    }

    try {
        // ตรวจสอบสถานะออเดอร์ (ออเดอร์นี้ต้องเป็น PAID เท่านั้นถึงจะใส่ tracking ได้)
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ?");
        // ในระบบจริงควรเช็ค shop_id ด้วย: WHERE order_id = ? AND shop_id = ?
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        if ($order['status'] !== 'PAID') {
            echo json_encode(['success' => false, 'message' => 'Cannot update tracking. Order must be PAID.']);
            exit;
        }

        // อัปเดตสถานะเป็น SHIPPED และใส่ tracking_number
        $updateStmt = $pdo->prepare("UPDATE orders SET status = 'SHIPPED', tracking_number = ? WHERE order_id = ?");
        $updateStmt->execute([$trackingNumber, $orderId]);

        echo json_encode(['success' => true, 'message' => 'Tracking updated. Order is now SHIPPED.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
