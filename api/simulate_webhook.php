<?php
// สำหรับจำลองระบบ Webhook ของ Payment Gateway (เฉพาะทดสอบ)
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$orderIds = $input['order_ids'] ?? [];

if (empty($orderIds)) {
    echo json_encode(['success' => false, 'error' => 'No orders provided']);
    exit;
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare("UPDATE orders SET payment_status='paid', paid_at=NOW(), shipping_status='packing' WHERE order_id=? AND payment_status='pending'");
    $notify = $pdo->prepare("INSERT INTO notifications(user_id,title,message,notification_type,is_read,sent_via) VALUES(?,?,?,'order',0,'web')");
    
    foreach ($orderIds as $orderId) {
        $orderId = (int)$orderId;
        $stmt = $pdo->prepare('SELECT user_id, order_number FROM orders WHERE order_id=? AND payment_status="pending"');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $update->execute([$orderId]);
            $userId = $order['user_id'];
            
            // Delete from cart
            $itemQ = $pdo->prepare('SELECT oi.book_id, b.seller_type, b.seller_id FROM order_items oi JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=?');
            $itemQ->execute([$orderId]);
            $sellerUsers = [];
            
            foreach ($itemQ->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $pdo->prepare('DELETE FROM cart_items WHERE user_id=? AND book_id=?')->execute([$userId, $item['book_id']]);
                
                $staffTable = $item['seller_type'] === 'publisher' ? 'publisher_staff' : 'shop_staff';
                $sellerColumn = $item['seller_type'] === 'publisher' ? 'publisher_id' : 'shop_id';
                $staff = $pdo->prepare("SELECT user_id FROM {$staffTable} WHERE {$sellerColumn}=?");
                $staff->execute([$item['seller_id']]);
                $sellerUsers = array_merge($sellerUsers, $staff->fetchAll(PDO::FETCH_COLUMN));
            }
            
            // Notify buyer
            $notify->execute([$userId, 'ชำระเงินสำเร็จ', 'คำสั่งซื้อ #'.$order['order_number'].' ชำระเงินเรียบร้อยแล้ว']);
            
            // Notify sellers
            foreach (array_unique(array_map('intval', $sellerUsers)) as $sellerUser) {
                $notify->execute([$sellerUser, 'คำสั่งซื้อใหม่จ่ายเงินแล้ว', 'คำสั่งซื้อ #'.$order['order_number'].' ได้รับยอดเงินเรียบร้อยแล้ว']);
            }
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
