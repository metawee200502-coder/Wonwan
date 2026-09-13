<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// ตรวจสอบสิทธิ์ Super Admin
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_slip'])) {
    $orderId = (int)$_POST['order_id'];
    
    // ยิง API (จำลองแบบเรียก Function โดยตรงเพื่อความง่าย)
    try {
        $stmt = $pdo->prepare("SELECT payment_status FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order && ($order['payment_status'] === 'reviewing' || $order['payment_status'] === 'pending')) {
            $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE order_id = ?")->execute([$orderId]);
            
            // แจ้งเตือนร้านค้า
            require_once __DIR__.'/../config/order_notifications.php';
            $itemQ = $pdo->prepare('SELECT oi.book_id, b.seller_type, b.seller_id, o.order_number FROM order_items oi JOIN books b ON b.book_id=oi.book_id JOIN orders o ON o.order_id=oi.order_id WHERE oi.order_id=?');
            $itemQ->execute([$orderId]);
            $items = $itemQ->fetchAll(PDO::FETCH_ASSOC);
            if ($items) {
                 foreach (batchSellerUsers($pdo, $items[0]['seller_type'], (int)$items[0]['seller_id']) as $sellerUser) {
                      wanawanNotifyUser($pdo, $sellerUser, 'สลิปได้รับการอนุมัติแล้ว', 'คำสั่งซื้อ #'.$items[0]['order_number'].' ชำระเงินสำเร็จ สามารถจัดส่งได้เลย', 'order');
                 }
            }
            $success = "อนุมัติสลิปออเดอร์เรียบร้อยแล้ว";
        } else {
            $error = "ไม่สามารถอนุมัติได้ หรือสถานะออเดอร์เปลี่ยนไปแล้ว";
        }
    } catch (Exception $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ดึงออเดอร์ที่รอตรวจสลิป
$stmt = $pdo->prepare("
    SELECT o.order_id, o.order_number, o.total_amount, o.payment_slip_url, o.created_at, u.display_name as buyer_name 
    FROM orders o 
    JOIN users u ON u.user_id = o.user_id 
    WHERE o.payment_status = 'reviewing' 
    ORDER BY o.created_at ASC
");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบสลิปโอนเงิน (Super Admin)</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body { font-family: var(--font-family); background: #f5f7f9; }
        .container { max-width: 1000px; margin: 40px auto; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        h1 { color: var(--secondary-color); margin-bottom: 20px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background: #e9f5e5; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
        .order-card { display: flex; flex-wrap: wrap; gap: 20px; border: 1px solid #eee; padding: 20px; border-radius: 10px; margin-bottom: 20px; background: #fafafa; }
        .order-info { flex: 1; min-width: 250px; }
        .order-slip { width: 300px; max-width: 100%; border: 1px dashed #ccc; border-radius: 8px; overflow: hidden; background: #fff; text-align: center; }
        .order-slip img { width: 100%; height: auto; max-height: 400px; object-fit: contain; cursor: pointer; }
        .btn { padding: 10px 20px; background: #39703a; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; text-decoration: none; }
        .btn:hover { background: #2e5a2f; }
        .empty { text-align: center; padding: 50px; color: #888; font-size: 18px; }
        
        /* Modal สำหรับดูสลิปภาพใหญ่ */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); align-items: center; justify-content: center; }
        .modal img { max-width: 90%; max-height: 90%; border-radius: 10px; }
        .modal-close { position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>ตรวจสอบสลิปโอนเงินส่วนกลาง (Escrow)</h1>
        <p style="color:#666; margin-bottom: 30px;">
            เมื่อลูกค้าอัปโหลดสลิป ออเดอร์จะมาอยู่ในหน้านี้ แอดมินต้องตรวจสอบยอดเงินเข้าบัญชีส่วนกลางก่อนกดยืนยัน ร้านค้าจึงจะเห็นที่อยู่จัดส่ง
        </p>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if (!$orders): ?>
            <div class="empty">ไม่มีสลิปที่รอการตรวจสอบ 🎉</div>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <div class="order-card">
                    <div class="order-info">
                        <h2>ออเดอร์ #<?= htmlspecialchars($o['order_number']) ?></h2>
                        <p><strong>ผู้ซื้อ:</strong> <?= htmlspecialchars($o['buyer_name']) ?></p>
                        <p><strong>ยอดที่ต้องโอน:</strong> <span style="font-size: 20px; color: #bc443d; font-weight: bold;">฿<?= number_format($o['total_amount'], 2) ?></span></p>
                        <p><strong>เวลาสั่งซื้อ:</strong> <?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></p>
                        
                        <form method="post" style="margin-top: 25px;" onsubmit="return confirm('ยืนยันว่าตรวจสอบสลิปแล้วเงินเข้าบัญชีจริงใช่หรือไม่?');">
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <button type="submit" name="approve_slip" class="btn">✅ ยืนยันสลิปถูกต้อง (อนุมัติ)</button>
                        </form>
                    </div>
                    <div class="order-slip">
                        <?php if ($o['payment_slip_url']): ?>
                            <?php $imgSrc = str_starts_with(ltrim(str_replace('\\','/',$o['payment_slip_url']),'/'), 'uploads/') ? '/pages/'.ltrim(str_replace('\\','/',$o['payment_slip_url']),'/') : ''; ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="สลิปโอนเงิน" onclick="openModal(this.src)">
                            <p style="font-size:12px; color:#777; margin-top:5px;">คลิกเพื่อดูรูปเต็ม</p>
                        <?php else: ?>
                            <p style="padding: 50px 10px; color: #aaa;">ไม่มีรูปสลิป</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal Image -->
    <div id="imageModal" class="modal" onclick="this.style.display='none'">
        <span class="modal-close">&times;</span>
        <img id="modalImg" src="">
    </div>

    <script>
        function openModal(src) {
            if(!src) return;
            document.getElementById('modalImg').src = src;
            document.getElementById('imageModal').style.display = 'flex';
        }
    </script>
</body>
</html>
