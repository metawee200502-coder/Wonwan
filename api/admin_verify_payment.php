<?php
require_once __DIR__ . '/../config/config.php';
session_start();

// ตรวจสอบว่าเป็น super_admin หรือผู้มีสิทธิ์อนุมัติสลิปหรือไม่
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? null;
    
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'Missing order_id']);
        exit;
    }

    try {
        // 1. ตรวจสอบสถานะออเดอร์ (อิงจากระบบเดิมที่รอแอดมินตรวจ = 'reviewing')
        $stmt = $pdo->prepare("SELECT payment_status FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        if ($order['payment_status'] !== 'reviewing' && $order['payment_status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'Order is not in reviewing state']);
            exit;
        }

        // 2. อัปเดตสถานะเป็น paid (เงินเข้าส่วนกลางสำเร็จ)
        $updateStmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE order_id = ?");
        $updateStmt->execute([$orderId]);

        // แจ้งเตือนร้านค้า (Optional)
        require_once __DIR__.'/../config/order_notifications.php';
        $itemQ = $pdo->prepare('SELECT oi.book_id, b.seller_type, b.seller_id, o.order_number FROM order_items oi JOIN books b ON b.book_id=oi.book_id JOIN orders o ON o.order_id=oi.order_id WHERE oi.order_id=?');
        $itemQ->execute([$orderId]);
        $items = $itemQ->fetchAll(PDO::FETCH_ASSOC);
        if ($items) {
             foreach (batchSellerUsers($pdo, $items[0]['seller_type'], (int)$items[0]['seller_id']) as $sellerUser) {
                  wanawanNotifyUser($pdo, $sellerUser, 'สลิปได้รับการอนุมัติแล้ว', 'คำสั่งซื้อ #'.$items[0]['order_number'].' ชำระเงินสำเร็จ สามารถจัดส่งได้เลย', 'order');
             }
        }

        echo json_encode(['success' => true, 'message' => 'Payment verified. Order is now PAID.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
