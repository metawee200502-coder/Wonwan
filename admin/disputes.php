<?php
session_start();
require_once __DIR__ . '/../config/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['dispute_id'], $_POST['order_id'])) {
    $action = $_POST['action'];
    $dispute_id = (int)$_POST['dispute_id'];
    $order_id = (int)$_POST['order_id'];
    $admin_note = $_POST['admin_note'] ?? '';

    if ($action === 'refund') {
        $stmt = $pdo->prepare("UPDATE disputes SET status = 'resolved', resolution = 'refunded', admin_note = ? WHERE dispute_id = ?");
        $stmt->execute([$admin_note, $dispute_id]);
        
        $stmt2 = $pdo->prepare("UPDATE orders SET payout_status = 'refunded', shipping_status = 'returned' WHERE order_id = ?");
        $stmt2->execute([$order_id]);
        
        $order_number = $pdo->query("SELECT order_number FROM orders WHERE order_id = " . (int)$order_id)->fetchColumn() ?: "#" . $order_id;
        logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'RESOLVE_DISPUTE_REFUND', (string)$dispute_id, "ตัดสินข้อพิพาทให้คืนเงินลูกค้า สำหรับออเดอร์ {$order_number}. หมายเหตุ: {$admin_note}");
        
        $_SESSION['flash_msg'] = "บันทึกการคืนเงินให้ลูกค้าเรียบร้อยแล้ว";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE disputes SET status = 'resolved', resolution = 'rejected', admin_note = ? WHERE dispute_id = ?");
        $stmt->execute([$admin_note, $dispute_id]);
        
        // Payout status goes back to pending so we can transfer money to seller
        $stmt2 = $pdo->prepare("UPDATE orders SET payout_status = 'pending' WHERE order_id = ?");
        $stmt2->execute([$order_id]);
        
        $order_number = $pdo->query("SELECT order_number FROM orders WHERE order_id = " . (int)$order_id)->fetchColumn() ?: "#" . $order_id;
        logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'RESOLVE_DISPUTE_REJECT', (string)$dispute_id, "ปฏิเสธข้อพิพาทและโอนเงินให้ร้านค้า สำหรับออเดอร์ {$order_number}. หมายเหตุ: {$admin_note}");
        
        $_SESSION['flash_msg'] = "ปฏิเสธข้อพิพาทเรียบร้อย ยอดเงินถูกส่งกลับไปยังสถานะรอโอนให้ร้านค้า";
    }

    header("Location: disputes.php");
    exit;
}

$stmt = $pdo->query("
    SELECT d.*, o.order_number, o.total_amount, o.shipping_name, o.shipping_phone, u.email as user_email
    FROM disputes d
    JOIN orders o ON d.order_id = o.order_id
    JOIN users u ON d.user_id = u.user_id
    WHERE d.status = 'open'
    ORDER BY d.created_at ASC
");
$disputes = $stmt->fetchAll();

$active_menu = 'disputes';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการข้อพิพาท (Disputes) - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        .admin-wrap { max-width: 1200px; margin: 40px auto; padding: 0 20px; min-height: 60vh; font-family: var(--font-family); }
        .admin-title { font-size: 24px; color: var(--secondary-color); margin-bottom: 24px; font-weight: 600; display:flex; align-items:center; gap:10px; }
        .dispute-list { display: flex; flex-direction: column; gap: 20px; }
        .dispute-card { background: #fff; border: 1px solid var(--border-color); border-left: 4px solid #e8546a; border-radius: 12px; padding: 20px; display: flex; gap: 24px; align-items: stretch; }
        .dispute-info { flex: 2; display: flex; flex-direction: column; gap: 10px; }
        .dispute-reason-box { background: #fdf2f4; padding: 16px; border-radius: 8px; border: 1px solid #f8d7da; }
        .dispute-reason-title { font-size: 14px; font-weight: 600; color: #721c24; margin-bottom: 6px; }
        .dispute-reason-text { font-size: 15px; color: var(--text-dark); line-height: 1.6; }
        
        .order-meta { font-size: 14px; color: var(--text-muted); line-height: 1.6; }
        .proof-img { width: 120px; height: 120px; border-radius: 8px; object-fit: cover; background: #f5f5f5; border: 1px solid var(--border-color); cursor: pointer; }
        
        .dispute-actions { flex: 1; border-left: 1px solid var(--border-color); padding-left: 24px; display: flex; flex-direction: column; gap: 12px; }
        .dispute-note { width: 100%; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; font-family: inherit; font-size: 14px; resize: vertical; min-height: 80px; }
        .dispute-note:focus { outline: none; border-color: var(--primary-color); }
        
        .btn-refund { background: #e8546a; color: #fff; border: none; padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-align: center; }
        .btn-refund:hover { background: #c84155; }
        .btn-reject-dispute { background: #5cb85c; color: #fff; border: none; padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-align: center; }
        .btn-reject-dispute:hover { background: #4cae4c; }
        
        .empty-state { padding: 40px; text-align: center; color: var(--text-muted); background: #f9f9f9; border-radius: 12px; border: 1px dashed var(--border-color); margin-top: 20px; }

        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); }
        .modal-content { margin: auto; display: block; max-width: 90%; max-height: 90vh; margin-top: 5vh; object-fit: contain; }
        .modal-close { position: absolute; top: 20px; right: 35px; color: #fff; font-size: 40px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    <div class="admin-main-wrap">
    <main class="admin-wrap">
        <h1 class="admin-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e8546a" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            จัดการข้อพิพาท (Dispute Tickets)
        </h1>

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
 if (empty($disputes)): ?>
            <div class="empty-state">ไม่มีข้อพิพาทที่ต้องจัดการในขณะนี้</div>
        <?php
 else: ?>
            <div class="dispute-list">
                <?php
 foreach ($disputes as $d): ?>
                    <div class="dispute-card">
                        <div class="dispute-info">
                            <div style="font-size: 16px; font-weight: 600; color: var(--text-dark);">ออเดอร์: <?= htmlspecialchars($d['order_number']) ?></div>
                            <div class="order-meta">
                                ผู้เปิดข้อพิพาท: <?= htmlspecialchars($d['shipping_name']) ?> (<?= htmlspecialchars($d['user_email']) ?>)<br>
                                วันที่เปิดข้อพิพาท: <?= htmlspecialchars($d['created_at']) ?> | ยอดเงิน: ฿<?= number_format($d['total_amount'], 2) ?>
                            </div>
                            <div class="dispute-reason-box">
                                <div class="dispute-reason-title">สาเหตุข้อพิพาท:</div>
                                <div class="dispute-reason-text"><?= nl2br(htmlspecialchars($d['reason'])) ?></div>
                            </div>
                            <?php
 if (!empty($d['proof_image'])): ?>
                                <div style="margin-top: 10px;">
                                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">หลักฐาน (คลิกเพื่อขยาย):</div>
                                    <img src="<?= htmlspecialchars($d['proof_image']) ?>" alt="Proof" class="proof-img" onclick="openModal(this.src)">
                                </div>
                            <?php
 endif; ?>
                        </div>
                        <div class="dispute-actions">
                            <form method="POST" onsubmit="return confirm('ตรวจสอบให้แน่ใจก่อนกดตัดสิน ยืนยันใช่หรือไม่?');" style="display:flex; flex-direction:column; gap:10px; height:100%;">
                                <input type="hidden" name="dispute_id" value="<?= $d['dispute_id'] ?>">
                                <input type="hidden" name="order_id" value="<?= $d['order_id'] ?>">
                                
                                <div style="font-size: 14px; font-weight: 600; color: var(--text-dark); margin-bottom: 4px;">การตัดสินของแอดมิน:</div>
                                <textarea name="admin_note" class="dispute-note" placeholder="บันทึกข้อความจากแอดมิน (เช่น เหตุผลในการตัดสิน)..." required></textarea>
                                
                                <div style="margin-top: auto; display:flex; flex-direction:column; gap:10px;">
                                    <button type="submit" name="action" value="refund" class="btn-refund">ยกเลิกออเดอร์และคืนเงินให้ผู้ซื้อ</button>
                                    <button type="submit" name="action" value="reject" class="btn-reject-dispute">ปฏิเสธข้อพิพาท (โอนให้ผู้ขาย)</button>
                                </div>
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
