<?php
session_start();
require_once __DIR__ . '/../config/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['type'], $_POST['id'])) {
    $action = $_POST['action']; // 'approve' or 'reject'
    $type = $_POST['type'];     // 'shop' or 'publisher'
    $id = (int)$_POST['id'];
    $newStatus = ($action === 'approve') ? 'approved' : 'suspended'; // Or just delete if rejected? 'suspended' is fine.

    if ($type === 'shop') {
        $stmt = $pdo->prepare("UPDATE shops SET status = ? WHERE shop_id = ?");
        $stmt->execute([$newStatus, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE publishers SET status = ? WHERE publisher_id = ?");
        $stmt->execute([$newStatus, $id]);
    }
    
    $_SESSION['flash_msg'] = "ดำเนินการเรียบร้อยแล้ว";
    header('Location: approvals.php');
    exit;
}

$pendingShops = $pdo->query("SELECT * FROM shops WHERE status = 'pending'")->fetchAll();
$pendingPubs = $pdo->query("SELECT * FROM publishers WHERE status = 'pending'")->fetchAll();

$active_menu = 'approvals';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>อนุมัติร้านค้า - ระบบหลังบ้าน</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        .admin-wrap { max-width: 1200px; margin: 40px auto; padding: 0 20px; min-height: 60vh; font-family: var(--font-family); }
        .admin-title { font-size: 24px; color: var(--secondary-color); margin-bottom: 24px; font-weight: 600; }
        .section-title { font-size: 20px; color: var(--text-dark); margin: 30px 0 16px; padding-bottom: 8px; border-bottom: 2px solid var(--border-color); }
        .approval-list { display: flex; flex-direction: column; gap: 16px; }
        .approval-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; gap: 20px; align-items: flex-start; }
        .approval-card img { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; background: #f0f0f0; }
        .approval-info { flex: 1; }
        .approval-name { font-size: 18px; font-weight: 600; color: var(--text-dark); margin-bottom: 4px; }
        .approval-meta { font-size: 14px; color: var(--text-muted); margin-bottom: 12px; line-height: 1.6; }
        .approval-doc { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #fdf2ee; color: var(--primary-color); border-radius: 6px; font-size: 13px; font-weight: 500; text-decoration: none; margin-bottom: 16px; }
        .approval-doc:hover { background: #fce4da; }
        .approval-actions { display: flex; gap: 10px; }
        .btn-approve { background: #5cb85c; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-approve:hover { background: #4cae4c; }
        .btn-reject { background: #e8546a; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-reject:hover { background: #c84155; }
        .empty-state { padding: 40px; text-align: center; color: var(--text-muted); background: #f9f9f9; border-radius: 12px; border: 1px dashed var(--border-color); }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    <?php
    <div class="admin-main-wrap">
    <main class="admin-wrap">
        <h1 class="admin-title">คำขอเปิดร้านรออนุมัติ</h1>

        <?php
 if (isset($_SESSION['flash_msg'])): ?>
            <div style="background: #e6f4ea; color: #1e8e3e; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($_SESSION['flash_msg']) ?>
            </div>
            <?php
 unset($_SESSION['flash_msg']); ?>
        <?php
 endif; ?>

        <h2 class="section-title">สำนักพิมพ์ (Publishers)</h2>
        <?php
 if (empty($pendingPubs)): ?>
            <div class="empty-state">ไม่มีคำขอเปิดสำนักพิมพ์ใหม่</div>
        <?php
 else: ?>
            <div class="approval-list">
                <?php
 foreach ($pendingPubs as $pub): ?>
                    <div class="approval-card">
                        <img src="<?= htmlspecialchars($pub['profile_image'] ?: '/assets/image/placeholder.png') ?>" alt="Logo">
                        <div class="approval-info">
                            <div class="approval-name"><?= htmlspecialchars($pub['company_name']) ?></div>
                            <div class="approval-meta">
                                ติดต่อ: <?= htmlspecialchars($pub['contact_name']) ?> (<?= htmlspecialchars($pub['contact_position']) ?>)<br>
                                อีเมล: <?= htmlspecialchars($pub['contact_email']) ?> | โทร: <?= htmlspecialchars($pub['contact_phone']) ?>
                            </div>
                            <?php
 if (!empty($pub['company_document_url'])): ?>
                                <a href="<?= htmlspecialchars($pub['company_document_url']) ?>" target="_blank" class="approval-doc">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    ดูเอกสารหนังสือรับรอง
                                </a>
                            <?php
 endif; ?>
                            <form method="POST" class="approval-actions" onsubmit="return confirm('ยืนยันการดำเนินการ?');">
                                <input type="hidden" name="type" value="publisher">
                                <input type="hidden" name="id" value="<?= $pub['publisher_id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn-approve">อนุมัติสำนักพิมพ์</button>
                                <button type="submit" name="action" value="reject" class="btn-reject">ไม่อนุมัติ</button>
                            </form>
                        </div>
                    </div>
                <?php
 endforeach; ?>
            </div>
        <?php
 endif; ?>

        <h2 class="section-title" style="margin-top: 48px;">ร้านค้ามือสอง (Used Book Shops)</h2>
        <?php
 if (empty($pendingShops)): ?>
            <div class="empty-state">ไม่มีคำขอเปิดร้านค้าใหม่</div>
        <?php
 else: ?>
            <div class="approval-list">
                <?php
 foreach ($pendingShops as $shop): ?>
                    <div class="approval-card">
                        <img src="<?= htmlspecialchars($shop['profile_image'] ?: '/assets/image/placeholder.png') ?>" alt="Logo">
                        <div class="approval-info">
                            <div class="approval-name"><?= htmlspecialchars($shop['shop_name']) ?></div>
                            <div class="approval-meta">
                                บัตรประชาชน: <?= htmlspecialchars($shop['id_card_number']) ?><br>
                                บัญชีธนาคาร: <?= htmlspecialchars($shop['bank_name']) ?> - <?= htmlspecialchars($shop['bank_account_number']) ?> (<?= htmlspecialchars($shop['bank_account_name']) ?>)
                            </div>
                            <?php
 if (!empty($shop['id_card_document_url'])): ?>
                                <a href="<?= htmlspecialchars($shop['id_card_document_url']) ?>" target="_blank" class="approval-doc">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    ดูภาพถ่ายบัตรประชาชน
                                </a>
                            <?php
 endif; ?>
                            <form method="POST" class="approval-actions" onsubmit="return confirm('ยืนยันการดำเนินการ?');">
                                <input type="hidden" name="type" value="shop">
                                <input type="hidden" name="id" value="<?= $shop['shop_id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn-approve">อนุมัติร้านค้า</button>
                                <button type="submit" name="action" value="reject" class="btn-reject">ไม่อนุมัติ</button>
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
    <?php
 require_once __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
