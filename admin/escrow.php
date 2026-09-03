<?php
session_start();
require_once __DIR__ . '/../config/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

$tab = $_GET['tab'] ?? 'slips';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $action = $_POST['action'];
    $order_id = (int)$_POST['order_id'];

    if ($action === 'approve_slip') {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_at = NOW() WHERE order_id = ? AND payment_status = 'reviewing'");
        $stmt->execute([$order_id]);
        $_SESSION['flash_msg'] = "ยืนยันการโอนเงินเรียบร้อยแล้ว";
    } elseif ($action === 'reject_slip') {
        // Can optionally set to 'pending' or 'failed'
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'pending', payment_slip_url = NULL WHERE order_id = ? AND payment_status = 'reviewing'");
        $stmt->execute([$order_id]);
        $_SESSION['flash_msg'] = "ปฏิเสธสลิปแล้ว ลูกค้าต้องอัปโหลดใหม่";
    } elseif ($action === 'transfer_payout') {
        $stmt = $pdo->prepare("UPDATE orders SET payout_status = 'paid_to_seller' WHERE order_id = ? AND payout_status = 'pending'");
        $stmt->execute([$order_id]);
        $_SESSION['flash_msg'] = "บันทึกการโอนเงินให้ร้านค้าเรียบร้อยแล้ว";
    }

    header("Location: escrow.php?tab=" . urlencode($tab));
    exit;
}

if ($tab === 'slips') {
    $orders = $pdo->query("SELECT * FROM orders WHERE payment_status = 'reviewing' ORDER BY created_at ASC")->fetchAll();
} elseif ($tab === 'payouts') {
    // tab = payouts
    $orders = $pdo->query("SELECT * FROM orders WHERE shipping_status = 'delivered' AND payout_status = 'pending' AND payment_status = 'paid' ORDER BY created_at ASC")->fetchAll();
} else {
    // tab = cancelled
    $orders = $pdo->query("SELECT * FROM orders WHERE payment_status IN ('failed', 'cancelled') OR shipping_status = 'cancelled' ORDER BY created_at DESC")->fetchAll();
}

$active_menu = 'escrow';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบเงินกลาง (Escrow) - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        body {
            font-family: 'Inter', 'Sarabun', sans-serif;
            background-color: #f4f9f7;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
        }
        .admin-main-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .admin-wrap { margin: 40px; padding: 0 20px; min-height: 60vh; }
        .admin-title { font-size: 28px; color: #1a4f44; margin-bottom: 32px; font-weight: 700; letter-spacing: -0.5px; }
        
        .tabs { display: flex; gap: 32px; margin-bottom: 32px; border-bottom: 2px solid #eef2f0; padding-bottom: 0; }
        .tab-btn { padding: 12px 0; color: #64748b; text-decoration: none; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s; margin-bottom: -2px; font-size: 15px; }
        .tab-btn:hover { color: #2F6E63; }
        .tab-btn.active { color: #2F6E63; border-bottom-color: #2F6E63; }
        
        .order-list { display: flex; flex-direction: column; gap: 20px; }
        .order-card { background: #fff; border-radius: 16px; padding: 24px; display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); transition: transform 0.2s; border: 1px solid #f4f9f7; }
        .order-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.05); }
        
        .order-info { flex: 1; }
        .order-number { font-size: 16px; font-weight: 700; color: #2F6E63; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .order-number svg { width: 18px; height: 18px; color: #2F6E63; }
        
        .order-meta { font-size: 14px; color: #64748b; margin-bottom: 16px; line-height: 1.6; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #f1f5f9; display: inline-block; min-width: 300px; }
        .order-price { font-size: 24px; font-weight: 800; color: #1e293b; margin-bottom: 16px; display: flex; align-items: baseline; gap: 4px; }
        .order-price span { font-size: 14px; color: #64748b; font-weight: 500; }
        
        .slip-container { margin-top: 16px; display: inline-block; }
        .slip-label { font-size: 13px; color: #64748b; margin-bottom: 8px; font-weight: 500; display: flex; align-items: center; gap: 6px; }
        .slip-img { width: 140px; height: 180px; border-radius: 10px; object-fit: cover; background: #f1f5f9; border: 2px solid #e2e8f0; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .slip-img:hover { border-color: #3b82f6; transform: scale(1.02); }
        
        .order-actions { display: flex; flex-direction: column; gap: 12px; justify-content: center; min-width: 180px; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px dashed #cbd5e1; }
        .btn-action { display: flex; align-items: center; justify-content: center; gap: 8px; border: none; padding: 12px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 14px; text-align: center; }
        .btn-approve { background: #10b981; color: #fff; box-shadow: 0 2px 10px rgba(16, 185, 129, 0.2); }
        .btn-approve:hover { background: #059669; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        .btn-reject { background: #fff; color: #ef4444; border: 1px solid #ef4444; }
        .btn-reject:hover { background: #fef2f2; }
        
        .empty-state { padding: 60px 40px; text-align: center; color: #94a3b8; background: #fff; border-radius: 16px; border: 2px dashed #e2e8f0; margin-top: 20px; font-weight: 500; font-size: 16px; display: flex; flex-direction: column; align-items: center; gap: 12px; }
        
        /* Modal for viewing slip */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(15, 23, 42, 0.9); backdrop-filter: blur(4px); }
        .modal-content { margin: auto; display: block; max-width: 90%; max-height: 90vh; margin-top: 5vh; object-fit: contain; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-close { position: absolute; top: 20px; right: 35px; color: #fff; font-size: 40px; font-weight: 300; cursor: pointer; transition: color 0.2s; }
        .modal-close:hover { color: #ef4444; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    <div class="admin-main-wrap">
    <main class="admin-wrap">
        <h1 class="admin-title">ระบบเงินกลาง (Escrow)</h1>

        <div class="tabs">
            <a href="?tab=slips" class="tab-btn <?= $tab === 'slips' ? 'active' : '' ?>">ตรวจสอบสลิปโอนเงิน</a>
            <a href="?tab=payouts" class="tab-btn <?= $tab === 'payouts' ? 'active' : '' ?>">รอโอนเงินให้ร้านค้า</a>
            <a href="?tab=cancelled" class="tab-btn <?= $tab === 'cancelled' ? 'active' : '' ?>">ถูกยกเลิก / หมดเวลา</a>
        </div>

        <?php
 if (isset($_SESSION['flash_msg'])): ?>
            <div style="background: #e6f4ea; color: #1e8e3e; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($_SESSION['flash_msg']) ?>
            </div>
            <?php
 unset($_SESSION['flash_msg']); ?>
        <?php
 endif; ?>

        <?php
 if (empty($orders)): ?>
            <div class="empty-state">ไม่มีรายการตกค้างในหมวดหมู่นี้</div>
        <?php
 else: ?>
            <div class="order-list">
                <?php foreach ($orders as $order): 
                    $stmtItems = $pdo->prepare("SELECT oi.*, b.title, b.image_cover FROM order_items oi LEFT JOIN books b ON oi.book_id = b.book_id WHERE oi.order_id = ?");
                    $stmtItems->execute([$order['order_id']]);
                    $items = $stmtItems->fetchAll();
                ?>
                    <div class="order-card">
                        <div class="order-info" style="flex: 0 0 320px;">
                            <div class="order-number">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                ออเดอร์: <?= htmlspecialchars($order['order_number']) ?>
                            </div>
                            <div class="order-meta" style="min-width: unset; width: 100%; box-sizing: border-box;">
                                <strong>ลูกค้า:</strong> <?= htmlspecialchars($order['shipping_name']) ?> (<?= htmlspecialchars($order['shipping_phone']) ?>)<br>
                                <strong>วันที่สั่งซื้อ:</strong> <?= htmlspecialchars($order['created_at']) ?>
                            </div>
                            <div class="order-price">
                                <span>ยอดรวม:</span> ฿<?= number_format($order['total_amount'], 2) ?>
                            </div>
                            
                            <?php if ($tab === 'slips' && !empty($order['payment_slip_url'])): 
                                $slipUrl = strpos($order['payment_slip_url'], 'http') === 0 ? $order['payment_slip_url'] : '/' . ltrim($order['payment_slip_url'], '/');
                            ?>
                                <div class="slip-container">
                                    <div class="slip-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        หลักฐานการโอนเงิน (คลิกเพื่อขยาย)
                                    </div>
                                    <img src="<?= htmlspecialchars($slipUrl) ?>" alt="Slip" class="slip-img" onclick="openModal(this.src)">
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- เพิ่มส่วนแสดงรายละเอียดสินค้าตรงกลาง -->
                        <div class="order-details" style="flex: 1; border-left: 1px dashed #cbd5e1; padding-left: 24px;">
                            <div style="font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                รายการสินค้า (<?= count($items) ?> รายการ)
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 12px; max-height: 200px; overflow-y: auto; padding-right: 12px;">
                                <?php foreach ($items as $item): 
                                    $itemImg = !empty($item['image_cover']) ? (strpos($item['image_cover'], 'http') === 0 ? $item['image_cover'] : '/' . ltrim($item['image_cover'], '/')) : '/assets/images/default_book.png';
                                ?>
                                    <div style="display: flex; gap: 12px; align-items: flex-start; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
                                        <img src="<?= htmlspecialchars($itemImg) ?>" style="width: 44px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; font-size: 14px; color: #334155; line-height: 1.4; margin-bottom: 4px;"><?= htmlspecialchars($item['title'] ?? 'หนังสือไม่ทราบชื่อ') ?></div>
                                            <div style="font-size: 13px; color: #64748b;">
                                                <span style="color: #2F6E63; font-weight: 600;">฿<?= number_format($item['price_at_purchase'], 2) ?></span> &times; <?= $item['quantity'] ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (!empty($order['promotion_name']) || $order['discount_amount'] > 0): ?>
                                <div style="margin-top: 16px; padding: 12px 16px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; font-size: 13px; color: #b45309; display: flex; align-items: flex-start; gap: 8px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                    <div>
                                        <strong>โค้ดส่วนลดที่ใช้:</strong> <?= htmlspecialchars($order['promotion_name'] ?: 'ส่วนลดพิเศษ') ?><br>
                                        <span style="font-weight: 600; color: #d97706;">หักลดไป ฿<?= number_format($order['discount_amount'], 2) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="order-actions">
                            <form method="POST" onsubmit="return confirm('ยืนยันการดำเนินการ?');" style="display:flex; flex-direction:column; gap:10px; width:100%;">
                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                <?php if ($tab === 'slips'): ?>
                                    <button type="submit" name="action" value="approve_slip" class="btn-action btn-approve">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                        อนุมัติสลิป
                                    </button>
                                    <button type="submit" name="action" value="reject_slip" class="btn-action btn-reject">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        ปฏิเสธสลิป
                                    </button>
                                <?php elseif ($tab === 'payouts'): ?>
                                    <button type="submit" name="action" value="transfer_payout" class="btn-action btn-approve" style="background:#f0a500; box-shadow: 0 2px 10px rgba(240, 165, 0, 0.2);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                        โอนเงินให้ร้านค้าแล้ว
                                    </button>
                                <?php elseif ($tab === 'cancelled'): ?>
                                    <div style="color: #ef4444; font-weight: 600; text-align: center; padding: 12px; background: #fef2f2; border-radius: 8px; font-size: 14px; border: 1px solid #fee2e2;">
                                        ถูกยกเลิก / หมดเวลา
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php
 endforeach; ?>
            </div>
        <?php
 endif; ?>

    </main>
    </div>

    <!-- Modal -->
    <div id="imgModal" class="modal" onclick="this.style.display='none'">
        <span class="modal-close">&times;</span>
        <img class="modal-content" id="imgModalContent">
    </div>

    <script>
        function openModal(src) {
            document.getElementById('imgModal').style.display = "block";
            document.getElementById('imgModalContent').src = src;
        }
    </script>
    <?php
 require_once __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
