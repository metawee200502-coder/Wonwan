<?php
session_start();
require_once __DIR__ . '/../config/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

$tab = $_GET['tab'] ?? 'posts';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve_post' || $action === 'reject_post') {
        $exchange_id = (int)$_POST['exchange_id'];
        $new_status = ($action === 'approve_post') ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE exchanges SET qc_status = ? WHERE exchange_id = ?");
        $stmt->execute([$new_status, $exchange_id]);
        $_SESSION['flash_msg'] = "ตรวจสอบโพสต์เรียบร้อยแล้ว";
    } elseif ($action === 'physical_qc') {
        $request_id = (int)$_POST['request_id'];
        $side = $_POST['side']; // 'requester' or 'offer'
        $qc_result = $_POST['qc_result']; // 'passed' or 'failed'
        
        if ($side === 'requester') {
            $stmt = $pdo->prepare("UPDATE exchange_requests SET admin_qc_requester_status = ? WHERE request_id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE exchange_requests SET admin_qc_offer_status = ? WHERE request_id = ?");
        }
        $stmt->execute([$qc_result, $request_id]);
        $_SESSION['flash_msg'] = "บันทึกผลการตรวจสภาพหนังสือเรียบร้อยแล้ว";
    } elseif ($action === 'admin_ship') {
        $request_id = (int)$_POST['request_id'];
        $tracking_req = $_POST['admin_tracking_to_requester'];
        $tracking_off = $_POST['admin_tracking_to_offer'];
        
        $stmt = $pdo->prepare("UPDATE exchange_requests SET admin_tracking_to_requester = ?, admin_tracking_to_offer = ?, status = 'completed' WHERE request_id = ?");
        $stmt->execute([$tracking_req, $tracking_off, $request_id]);
        
        // Update both exchanges to completed
        $req = $pdo->query("SELECT exchange_id, offered_exchange_id FROM exchange_requests WHERE request_id = $request_id")->fetch();
        if ($req) {
            $pdo->query("UPDATE exchanges SET status = 'completed' WHERE exchange_id IN ({$req['exchange_id']}, {$req['offered_exchange_id']})");
        }
        $_SESSION['flash_msg'] = "บันทึกเลขพัสดุและจบการแลกเปลี่ยนเรียบร้อยแล้ว";
    }

    header("Location: qc_exchanges.php?tab=" . urlencode($tab));
    exit;
}

$active_menu = 'qc_exchanges';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>QC แลกเปลี่ยนหนังสือ - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        .admin-wrap { max-width: 1200px; margin: 40px auto; padding: 0 20px; min-height: 60vh; font-family: var(--font-family); }
        .admin-title { font-size: 24px; color: var(--secondary-color); margin-bottom: 24px; font-weight: 600; display:flex; align-items:center; gap:10px; }
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 0; }
        .tab-btn { padding: 12px 24px; color: var(--text-muted); text-decoration: none; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s; margin-bottom: -1px; }
        .tab-btn:hover { color: var(--primary-color); }
        .tab-btn.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        
        .list-container { display: flex; flex-direction: column; gap: 20px; }
        .qc-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; }
        .qc-card-inner { display: flex; gap: 20px; align-items: stretch; }
        .qc-info { flex: 1; }
        .qc-title { font-size: 18px; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; }
        .qc-meta { font-size: 14px; color: var(--text-muted); line-height: 1.6; margin-bottom: 12px; }
        .qc-images { display: flex; gap: 10px; margin-bottom: 12px; }
        .qc-images img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color); cursor: pointer; }
        
        .qc-actions { display: flex; flex-direction: column; gap: 10px; justify-content: center; min-width: 160px; border-left: 1px solid var(--border-color); padding-left: 20px; }
        .btn-approve { background: #5cb85c; color: #fff; border: none; padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-align: center; }
        .btn-approve:hover { background: #4cae4c; }
        .btn-reject { background: #e8546a; color: #fff; border: none; padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-align: center; }
        .btn-reject:hover { background: #c84155; }
        
        .empty-state { padding: 40px; text-align: center; color: var(--text-muted); background: #f9f9f9; border-radius: 12px; border: 1px dashed var(--border-color); margin-top: 20px; }

        .physical-box { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .side-box { background: #fdfdfd; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; }
        .side-title { font-weight: 600; font-size: 15px; color: var(--secondary-color); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color); }
        .qc-status-badge { display: inline-block; padding: 4px 10px; font-size: 12px; border-radius: 12px; font-weight: 600; }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-passed { background: #d4edda; color: #155724; }
        .bg-failed { background: #f8d7da; color: #721c24; }
        
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); }
        .modal-content { margin: auto; display: block; max-width: 90%; max-height: 90vh; margin-top: 5vh; object-fit: contain; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    <?php
    <div class="admin-main-wrap">
    <main class="admin-wrap">
        <h1 class="admin-title">ตรวจสอบสินค้าแลกเปลี่ยน (QC)</h1>

        <div class="tabs">
            <a href="?tab=posts" class="tab-btn <?= $tab === 'posts' ? 'active' : '' ?>">ตรวจสอบโพสต์ก่อนลงเว็บ</a>
            <a href="?tab=physical" class="tab-btn <?= $tab === 'physical' ? 'active' : '' ?>">ตรวจสอบสภาพหนังสือจริง (Physical QC)</a>
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
 if ($tab === 'posts'): ?>
            <?php

            $posts = $pdo->query("SELECT * FROM exchanges WHERE qc_status = 'pending' ORDER BY created_at ASC")->fetchAll();
            ?>
            <?php
 if (empty($posts)): ?>
                <div class="empty-state">ไม่มีโพสต์รอตรวจสอบ</div>
            <?php
 else: ?>
                <div class="list-container">
                    <?php
 foreach ($posts as $post): ?>
                        <div class="qc-card">
                            <div class="qc-card-inner">
                                <div class="qc-info">
                                    <div class="qc-title"><?= htmlspecialchars($post['title']) ?> (<?= htmlspecialchars($post['author'] ?? 'ไม่ระบุผู้แต่ง') ?>)</div>
                                    <div class="qc-meta">
                                        สภาพ: <?= htmlspecialchars($post['condition_percent']) ?>% | ตำหนิ: <?= htmlspecialchars($post['defect_types'] ?: 'ไม่มี') ?><br>
                                        เงื่อนไขการรับแลก: <?= htmlspecialchars($post['wanted_type']) ?> (<?= htmlspecialchars($post['wanted_detail'] ?: '-') ?>)
                                    </div>
                                    <div class="qc-images">
                                        <?php
 foreach (['image_cover', 'image_back', 'image_inside', 'image_edge'] as $imgF): ?>
                                            <?php
 if (!empty($post[$imgF])): ?>
                                                <img src="<?= htmlspecialchars($post[$imgF]) ?>" onclick="openModal(this.src)" alt="Book Image">
                                            <?php
 endif; ?>
                                        <?php
 endforeach; ?>
                                    </div>
                                </div>
                                <div class="qc-actions">
                                    <form method="POST">
                                        <input type="hidden" name="exchange_id" value="<?= $post['exchange_id'] ?>">
                                        <button type="submit" name="action" value="approve_post" class="btn-approve" style="width:100%; margin-bottom:10px;">อนุมัติโพสต์</button>
                                        <button type="submit" name="action" value="reject_post" class="btn-reject" style="width:100%;">ไม่อนุมัติ (ลบโพสต์)</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php
 endforeach; ?>
                </div>
            <?php
 endif; ?>
        <?php
 endif; ?>

        <?php
 if ($tab === 'physical'): ?>
            <?php

            // Physical QC handles requests where status = accepted
            $reqs = $pdo->query("
                SELECT r.*, 
                       e_req.title as req_title, e_req.image_cover as req_img, 
                       e_off.title as off_title, e_off.image_cover as off_img
                FROM exchange_requests r
                JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
                JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
                WHERE r.status = 'accepted'
                ORDER BY r.updated_at ASC
            ")->fetchAll();
            ?>
            <?php
 if (empty($reqs)): ?>
                <div class="empty-state">ไม่มีพัสดุรอตรวจสภาพจริง</div>
            <?php
 else: ?>
                <div class="list-container">
                    <?php
 foreach ($reqs as $r): ?>
                        <div class="qc-card">
                            <div style="font-weight:600; margin-bottom:16px; font-size:16px; border-bottom:1px solid #eee; padding-bottom:10px;">
                                รหัสการแลกเปลี่ยน: #EX-<?= $r['request_id'] ?>
                            </div>
                            <div class="physical-box">
                                <!-- ฝั่งหนังสือตั้งต้น (Requester) -->
                                <div class="side-box">
                                    <div class="side-title">หนังสือฝั่งผู้ตั้งโพสต์: <?= htmlspecialchars($r['req_title']) ?></div>
                                    <div style="font-size:14px; margin-bottom:8px;">เลขพัสดุที่ส่งมาส่วนกลาง: <strong><?= htmlspecialchars($r['requester_tracking_to_admin'] ?: 'ยังไม่กรอก') ?></strong></div>
                                    <div style="font-size:14px; margin-bottom:12px;">
                                        สถานะ QC: 
                                        <span class="qc-status-badge bg-<?= htmlspecialchars($r['admin_qc_requester_status']) ?>">
                                            <?= strtoupper($r['admin_qc_requester_status']) ?>
                                        </span>
                                    </div>
                                    <?php
 if ($r['admin_qc_requester_status'] === 'pending' && !empty($r['requester_tracking_to_admin'])): ?>
                                        <form method="POST" style="display:flex; gap:10px;">
                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                            <input type="hidden" name="side" value="requester">
                                            <input type="hidden" name="action" value="physical_qc">
                                            <button type="submit" name="qc_result" value="passed" class="btn-approve" style="padding:6px 12px; font-size:13px;">ผ่าน QC</button>
                                            <button type="submit" name="qc_result" value="failed" class="btn-reject" style="padding:6px 12px; font-size:13px;">ไม่ผ่าน QC</button>
                                        </form>
                                    <?php
 endif; ?>
                                </div>
                                
                                <!-- ฝั่งหนังสือเสนอแลก (Offer) -->
                                <div class="side-box">
                                    <div class="side-title">หนังสือฝั่งผู้ขอแลก: <?= htmlspecialchars($r['off_title']) ?></div>
                                    <div style="font-size:14px; margin-bottom:8px;">เลขพัสดุที่ส่งมาส่วนกลาง: <strong><?= htmlspecialchars($r['offer_tracking_to_admin'] ?: 'ยังไม่กรอก') ?></strong></div>
                                    <div style="font-size:14px; margin-bottom:12px;">
                                        สถานะ QC: 
                                        <span class="qc-status-badge bg-<?= htmlspecialchars($r['admin_qc_offer_status']) ?>">
                                            <?= strtoupper($r['admin_qc_offer_status']) ?>
                                        </span>
                                    </div>
                                    <?php
 if ($r['admin_qc_offer_status'] === 'pending' && !empty($r['offer_tracking_to_admin'])): ?>
                                        <form method="POST" style="display:flex; gap:10px;">
                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                            <input type="hidden" name="side" value="offer">
                                            <input type="hidden" name="action" value="physical_qc">
                                            <button type="submit" name="qc_result" value="passed" class="btn-approve" style="padding:6px 12px; font-size:13px;">ผ่าน QC</button>
                                            <button type="submit" name="qc_result" value="failed" class="btn-reject" style="padding:6px 12px; font-size:13px;">ไม่ผ่าน QC</button>
                                        </form>
                                    <?php
 endif; ?>
                                </div>
                            </div>
                            
                            <!-- ถ้าผ่านทั้งคู่ ให้ส่งกลับ -->
                            <?php
 if ($r['admin_qc_requester_status'] === 'passed' && $r['admin_qc_offer_status'] === 'passed' && empty($r['admin_tracking_to_requester'])): ?>
                                <div style="background:#f4f9f5; border:1px solid #d4edda; padding:20px; border-radius:8px;">
                                    <div style="font-weight:600; color:#155724; margin-bottom:16px;">✅ สินค้าผ่าน QC ทั้งสองฝ่ายพร้อมจัดส่งสลับให้ผู้ใช้</div>
                                    <form method="POST" style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-end;">
                                        <input type="hidden" name="action" value="admin_ship">
                                        <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                        <div style="flex:1; min-width:200px;">
                                            <label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">เลขพัสดุ (ส่งให้ผู้ตั้งโพสต์)</label>
                                            <input type="text" name="admin_tracking_to_requester" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-family:inherit;">
                                        </div>
                                        <div style="flex:1; min-width:200px;">
                                            <label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">เลขพัสดุ (ส่งให้ผู้ขอแลก)</label>
                                            <input type="text" name="admin_tracking_to_offer" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-family:inherit;">
                                        </div>
                                        <button type="submit" class="btn-approve" style="padding:10px 24px;">บันทึกการจัดส่ง & จบงาน</button>
                                    </form>
                                </div>
                            <?php
 endif; ?>
                        </div>
                    <?php
 endforeach; ?>
                </div>
            <?php
 endif; ?>
        <?php
 endif; ?>
    </main>
    </div>

    <!-- Modal -->
    <div id="imgModal" class="modal" onclick="this.style.display='none'">
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
