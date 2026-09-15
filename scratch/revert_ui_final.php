<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';

$content = <<< 'PHP_EOF'
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
        
        $stmt = $pdo->prepare("UPDATE exchanges SET qc_status = ?, status = ? WHERE exchange_id = ?");
        $stmt->execute([$new_status, ($new_status === 'approved' ? 'active' : 'inactive'), $exchange_id]);

        logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'QC_POST', (string)$exchange_id, "Admin {$new_status} post {$exchange_id}");

        $_SESSION['flash_msg'] = "บันทึกผลตรวจสอบโพสต์เรียบร้อยแล้ว";
    } elseif ($action === 'approve_slip') {
        $request_id = (int)$_POST['request_id'];
        $side = $_POST['side']; // 'requester' or 'offer'
        $slip_result = $_POST['slip_result']; // 'approved' or 'rejected'

        if ($side === 'requester') {
            $stmt = $pdo->prepare("UPDATE exchange_requests SET requester_slip_status = ? WHERE request_id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE exchange_requests SET offer_slip_status = ? WHERE request_id = ?");
        }
        $stmt->execute([$slip_result, $request_id]);
        
        $sideLabel = ($side === 'requester') ? 'ผู้ขอแลก' : 'ผู้ตั้งโพสต์';
        logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'VERIFY_SLIP', (string)$request_id, "บันทึกผลตรวจสอบสลิปของ {$sideLabel} ว่า {$slip_result} สำหรับคำขอที่ {$request_id}");

        $_SESSION['flash_msg'] = "บันทึกผลตรวจสอบสลิปเรียบร้อยแล้ว";
    } elseif ($action === 'submit_qc_report_both') {
        $request_id = (int)$_POST['request_id'];
        $req_user_id = (int)$_POST['requester_id'];
        $off_user_id = (int)$_POST['offer_id'];
        
        $req_is_match = (int)$_POST['req_is_match'];
        $req_notes = trim($_POST['req_notes'] ?? '');
        $off_is_match = (int)$_POST['off_is_match'];
        $off_notes = trim($_POST['off_notes'] ?? '');
        
        // Handle file uploads
        $uploadDir = __DIR__ . '/../pages/uploads/qc/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $req_photos = [];
        if (!empty($_FILES['req_photos']['name'][0])) {
            foreach ($_FILES['req_photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['req_photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['req_photos']['name'][$key], PATHINFO_EXTENSION);
                    $newName = 'qc_req_' . $request_id . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($tmp_name, $uploadDir . $newName)) {
                        $req_photos[] = '/pages/uploads/qc/' . $newName;
                    }
                }
            }
        }
        
        $off_photos = [];
        if (!empty($_FILES['off_photos']['name'][0])) {
            foreach ($_FILES['off_photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['off_photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['off_photos']['name'][$key], PATHINFO_EXTENSION);
                    $newName = 'qc_off_' . $request_id . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($tmp_name, $uploadDir . $newName)) {
                        $off_photos[] = '/pages/uploads/qc/' . $newName;
                    }
                }
            }
        }
        
        // Save to admin_qc_reports
        $stmt = $pdo->prepare("INSERT INTO admin_qc_reports (request_id, item_owner_id, is_match, admin_notes, photo_urls, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$request_id, $off_user_id, $req_is_match, $req_notes, json_encode($req_photos)]);
        $stmt->execute([$request_id, $req_user_id, $off_is_match, $off_notes, json_encode($off_photos)]);
        
        // Update statuses
        $req_status = $req_is_match ? 'passed' : 'failed';
        $off_status = $off_is_match ? 'passed' : 'failed';
        $pdo->prepare("UPDATE exchange_requests SET admin_qc_requester_status = ?, admin_qc_offer_status = ? WHERE request_id = ?")->execute([$req_status, $off_status, $request_id]);
        
        // Send to chat
        $q = $pdo->prepare("SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id = (SELECT exchange_id FROM exchange_requests WHERE request_id = ?) LIMIT 1");
        $q->execute([$request_id]);
        $conv = $q->fetch(PDO::FETCH_ASSOC);
        if ($conv) {
            $msg = "📋 ประกาศผล QC จากส่วนกลาง\n";
            $msg .= "ผู้ขอแลก (ฝั่งส่งมา) ได้ผล: " . ($req_is_match ? "✅ ผ่าน" : "❌ ไม่ผ่าน") . "\n";
            $msg .= "หมายเหตุ: " . $req_notes . "\n\n";
            $msg .= "ผู้ตั้งโพสต์ (ฝั่งรับแลก) ได้ผล: " . ($off_is_match ? "✅ ผ่าน" : "❌ ไม่ผ่าน") . "\n";
            $msg .= "หมายเหตุ: " . $off_notes;
            
            $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, metadata) VALUES (?, ?, ?, 'admin_qc_report', ?)")
                ->execute([$conv['conversation_id'], $_SESSION['user_id'], $msg, json_encode([
                    'req_is_match' => $req_is_match,
                    'req_notes' => $req_notes,
                    'req_photos' => $req_photos,
                    'off_is_match' => $off_is_match,
                    'off_notes' => $off_notes,
                    'off_photos' => $off_photos
                ])]);
            $pdo->prepare("UPDATE chat_conversations SET last_message_at = NOW() WHERE conversation_id = ?")->execute([$conv['conversation_id']]);
        }
        
        $_SESSION['flash_msg'] = "บันทึกผล QC ทั้งสองฝ่ายเรียบร้อยแล้ว";
    } elseif ($action === 'admin_ship') {
        $request_id = (int)$_POST['request_id'];
        $tracking_req = trim($_POST['admin_tracking_to_requester'] ?? '');
        $tracking_off = trim($_POST['admin_tracking_to_offer'] ?? '');

        $stmt = $pdo->prepare("UPDATE exchange_requests SET admin_tracking_to_requester = ?, admin_tracking_to_offer = ?, status = 'completed' WHERE request_id = ?");
        $stmt->execute([$tracking_req, $tracking_off, $request_id]);

        // Update both exchanges to completed
        $req = $pdo->query("SELECT exchange_id, offered_exchange_id FROM exchange_requests WHERE request_id = $request_id")->fetch();
        if ($req) {
            $pdo->query("UPDATE exchanges SET status = 'completed' WHERE exchange_id IN ({$req['exchange_id']}, {$req['offered_exchange_id']})");
        }

        logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'COMPLETE_EXCHANGE', (string)$request_id, "บันทึกเลขพัสดุและส่งของกลับสำเร็จสำหรับคำขอ {$request_id}");

        $_SESSION['flash_msg'] = "บันทึกเลขพัสดุและส่งของกลับสำเร็จ";
    }

    header("Location: qc_exchanges.php?tab=" . urlencode($tab));
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>QC แลกเปลี่ยนหนังสือ - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        body { background: #fdfbfb; margin: 0; display: flex; min-height: 100vh; font-family: 'Prompt', sans-serif;}
        .admin-main-wrap { flex: 1; min-width: 0; padding: 40px 50px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-title { color: #00796B; font-size: 26px; font-weight: bold; display: flex; align-items: center; gap: 10px; margin: 0;}
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid #e0f2f1; padding-bottom: 0; }
        .tab-btn { padding: 12px 24px; color: #777; text-decoration: none; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s; margin-bottom: -1px; font-size: 15px; }
        .tab-btn:hover { color: #00796B; }
        .tab-btn.active { color: #00796B; border-bottom-color: #00796B; }

        .tablebox { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e0f2f1; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .orders { width: 100%; border-collapse: collapse; min-width: 800px; }
        .orders th, .orders td { padding: 18px 20px; border-bottom: 1px solid #f0f0f0; text-align: left; }
        .orders th { background: #f5fbfb; color: #555; font-weight: 600; font-size: 14px; }
        .orders tr:hover { background: #fafafa; }
        .orders td { color: #444; font-size: 14px; vertical-align: top; }
        
        .user-profile { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .user-info-text { display: flex; flex-direction: column; gap: 2px; }
        .user-name { font-weight: 600; color: #333; font-size: 14px; }
        .email-tag { font-size: 11px; color: #00796B; background: #e0f2f1; padding: 2px 6px; border-radius: 4px; display: inline-block; width: fit-content; }
        
        .book-title { font-size: 13px; color: #666; margin-top: 4px; }
        
        .action-badge { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: bold; display: inline-block; }
        .badge-green { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .badge-blue { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .badge-orange { background: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }
        
        .qc-status-badge { display: inline-block; padding: 4px 10px; font-size: 12px; border-radius: 6px; font-weight: 700; }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-passed { background: #d4edda; color: #155724; }
        .bg-failed { background: #f8d7da; color: #721c24; }

        .btn-approve { background: #10b981; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; transition: 0.2s;}
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #ef4444; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; transition: 0.2s;}
        .btn-reject:hover { background: #dc2626; }
        
        .tracking-number { font-family: monospace; font-size: 13px; font-weight: 700; color: #00796B; }

        .empty-state { text-align: center; padding: 50px 20px; color: #888; font-size: 15px; }
        
        /* Layout overrides for general wrapper */
        .admin-wrap { max-width: unset; margin: unset; padding: unset; min-height: unset; font-family: unset; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>

    <div class="admin-main-wrap">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-clipboard-check"></i> จัดการคำขอแลกเปลี่ยน (QC)</h1>
        </div>

        <?php if (isset($_SESSION['flash_msg'])): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:8px; border-left:5px solid #28a745;">
                <?= htmlspecialchars($_SESSION['flash_msg']) ?>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <div class="tabs">
            <a href="?tab=posts" class="tab-btn <?= $tab === 'posts' ? 'active' : '' ?>">ตรวจสอบโพสต์ (Admin QC)</a>
            <a href="?tab=physical" class="tab-btn <?= $tab === 'physical' ? 'active' : '' ?>">ตรวจสอบสภาพหนังสือจริง (Physical QC)</a>
        </div>

        <?php if ($tab === 'posts'): ?>
            <?php
            $posts = $pdo->query("SELECT * FROM exchanges WHERE qc_status = 'pending' ORDER BY created_at ASC")->fetchAll();
            ?>
            <?php if (empty($posts)): ?>
                <div class="empty-state">ไม่มีโพสต์รอตรวจสอบ</div>
            <?php else: ?>
                <div class="tablebox">
                    <table class="orders">
                        <thead>
                            <tr>
                                <th>รหัสโพสต์</th>
                                <th>รูปหน้าปก</th>
                                <th>ชื่อหนังสือ</th>
                                <th>ผู้ใช้งาน</th>
                                <th>เวลาที่โพสต์</th>
                                <th style="width: 150px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td style="font-weight: 500; color: #00796B;">#POST-<?= $post['exchange_id'] ?></td>
                                    <td>
                                        <img src="<?= htmlspecialchars('/' . ltrim(str_replace('\\','/',$post['image_cover']),'/')) ?>" alt="cover" style="width:50px; height:50px; object-fit:cover; border-radius:4px; border:1px solid #eee;">
                                    </td>
                                    <td><?= htmlspecialchars($post['title']) ?></td>
                                    <td>User #<?= $post['owner_id'] ?></td>
                                    <td style="font-size:12px; color:#666;"><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" style="display:flex; gap:4px;">
                                            <input type="hidden" name="exchange_id" value="<?= $post['exchange_id'] ?>">
                                            <button type="submit" name="action" value="approve_post" class="btn-approve">อนุมัติ</button>
                                            <button type="submit" name="action" value="reject_post" class="btn-reject">ไม่อนุมัติ</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($tab === 'physical'): ?>
            <?php
            // Physical QC handles requests where status = accepted or completed
            $reqs = $pdo->query("
                SELECT r.*,
                       e_req.title as req_title, e_req.image_cover as req_img, e_req.owner_id as target_owner_id,
                       e_off.title as off_title, e_off.image_cover as off_img, r.requester_id,
                       u1.display_name as target_owner_name, u1.profile_image as target_owner_image, u1.email as target_owner_email,
                       u2.display_name as requester_name, u2.profile_image as requester_image, u2.email as requester_email,
                       c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects,
                       c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects
                FROM exchange_requests r
                LEFT JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
                LEFT JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
                LEFT JOIN users u1 ON e_req.owner_id = u1.user_id
                LEFT JOIN users u2 ON r.requester_id = u2.user_id
                LEFT JOIN trade_condition_forms c_off ON c_off.request_id = r.request_id AND c_off.owner_id = e_req.owner_id
                LEFT JOIN trade_condition_forms c_req ON c_req.request_id = r.request_id AND c_req.owner_id = r.requester_id
                WHERE r.status IN ('accepted', 'completed')
                ORDER BY r.updated_at ASC
            ")->fetchAll();
            
            function formatTradeOption($opt) {
                if ($opt === 'direct_escrow') return 'ส่งตรงให้กัน (P2P Escrow)';
                if ($opt === 'admin_qc') return 'ผ่านส่วนกลาง (Admin QC)';
                if ($opt === 'meetup') return 'นัดรับ (Meet-up)';
                return $opt ?: 'ยังไม่ระบุ';
            }
            function getSlipStatusBadge($status) {
                if ($status === 'verified') return '<span class="slip-badge slip-verified">✅ ตรวจสอบแล้ว</span>';
                if ($status === 'pending') return '<span class="slip-badge slip-pending">⏳ รอตรวจสอบ</span>';
                return '<span class="slip-badge slip-none">❌ ยังไม่แนบสลิป</span>';
            }
            function chatImage($v) {
                if(!$v) return '/assets/images/default-avatar.png';
                if(preg_match('#^https?://#i',$v)) return $v;
                $v = ltrim(str_replace('\\','/',$v),'/');
                if(str_starts_with($v,'pages/')) return '/'.$v;
                return '/'.$v;
            }
            ?>
            <?php if (empty($reqs)): ?>
                <div class="empty-state">ไม่มีพัสดุรอตรวจสภาพจริง</div>
            <?php else: ?>
                <div class="tablebox">
                    <table class="orders">
                        <thead>
                            <tr>
                                <th>รหัสคำขอ</th>
                                <th>รูปแบบการแลก</th>
                                <th>ข้อมูลผู้ตั้งโพสต์</th>
                                <th>ข้อมูลผู้ขอแลก</th>
                                <th style="width: 250px;">สถานะจัดส่ง & QC</th>
                                <th style="width: 150px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reqs as $r): ?>
                                <tr>
                                    <td style="font-weight: 500; color: #00796B;">#EX-<?= $r['request_id'] ?></td>
                                    <td>
                                        <span class="action-badge badge-blue"><?= formatTradeOption($r['trade_option']) ?></span>
                                    </td>
                                    <td>
                                        <div class="user-profile">
                                            <img src="<?= chatImage($r['target_owner_image']) ?>" class="user-avatar" alt="Avatar">
                                            <div class="user-info-text">
                                                <span class="user-name"><?= htmlspecialchars($r['target_owner_name'] ?? 'ไม่มีชื่อ') ?></span>
                                                <span class="email-tag"><?= htmlspecialchars($r['target_owner_email'] ?? '') ?></span>
                                            </div>
                                        </div>
                                        <div class="book-title">📖 <?= htmlspecialchars($r['req_title'] ?? '') ?></div>
                                        <?php if (!empty($r['off_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="margin-top:8px; background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd;" onclick="openCondModal('<?= htmlspecialchars($r['off_cond_front']) ?>', '<?= htmlspecialchars($r['off_cond_back']) ?>', '<?= htmlspecialchars($r['off_cond_spine']) ?>')">🔎 ดูสภาพที่ตกลง</button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="user-profile">
                                            <img src="<?= chatImage($r['requester_image']) ?>" class="user-avatar" alt="Avatar">
                                            <div class="user-info-text">
                                                <span class="user-name"><?= htmlspecialchars($r['requester_name'] ?? 'ไม่มีชื่อ') ?></span>
                                                <span class="email-tag"><?= htmlspecialchars($r['requester_email'] ?? '') ?></span>
                                            </div>
                                        </div>
                                        <div class="book-title">📖 <?= htmlspecialchars($r['off_title'] ?? '') ?></div>
                                        <?php if (!empty($r['req_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="margin-top:8px; background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd;" onclick="openCondModal('<?= htmlspecialchars($r['req_cond_front']) ?>', '<?= htmlspecialchars($r['req_cond_back']) ?>', '<?= htmlspecialchars($r['req_cond_spine']) ?>')">🔎 ดูสภาพที่ตกลง</button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size:12px; margin-bottom:8px;">
                                            <strong>ผู้ตั้งโพสต์:</strong><br>
                                            สลิป: <?= getSlipStatusBadge($r['offer_slip_status']) ?><br>
                                            เลขพัสดุ: <span class="tracking-number"><?= htmlspecialchars($r['offer_tracking_to_admin'] ?? '-') ?></span><br>
                                            QC: <span class="qc-status-badge bg-<?= htmlspecialchars($r['admin_qc_offer_status'] ?? '') ?>"><?= strtoupper($r['admin_qc_offer_status'] ?? '') ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #eee; padding-top:8px; font-size:12px;">
                                            <strong>ผู้ขอแลก:</strong><br>
                                            สลิป: <?= getSlipStatusBadge($r['requester_slip_status']) ?><br>
                                            เลขพัสดุ: <span class="tracking-number"><?= htmlspecialchars($r['requester_tracking_to_admin'] ?? '-') ?></span><br>
                                            QC: <span class="qc-status-badge bg-<?= htmlspecialchars($r['admin_qc_requester_status'] ?? '') ?>"><?= strtoupper($r['admin_qc_requester_status'] ?? '') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <!-- Slip Actions -->
                                        <?php if ($r['offer_slip_status'] === 'pending' && !empty($r['offer_slip_url'])): ?>
                                            <div style="margin-bottom:10px; background:#fff3cd; padding:8px; border-radius:8px; border:1px solid #ffeeba;">
                                                <div style="font-size:11px; color:#856404; margin-bottom:4px;">สลิปตั้งโพสต์ <a href="<?= htmlspecialchars($r['offer_slip_url']) ?>" target="_blank" style="color:#0284c7;">[ดูสลิป]</a></div>
                                                <form method="POST" style="display:flex; gap:4px;">
                                                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                    <input type="hidden" name="side" value="offer">
                                                    <input type="hidden" name="action" value="approve_slip">
                                                    <button type="submit" name="slip_result" value="approved" class="btn-approve">อนุมัติ</button>
                                                    <button type="submit" name="slip_result" value="rejected" class="btn-reject">ไม่อนุมัติ</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($r['requester_slip_status'] === 'pending' && !empty($r['requester_slip_url'])): ?>
                                            <div style="margin-bottom:10px; background:#fff3cd; padding:8px; border-radius:8px; border:1px solid #ffeeba;">
                                                <div style="font-size:11px; color:#856404; margin-bottom:4px;">สลิปขอแลก <a href="<?= htmlspecialchars($r['requester_slip_url']) ?>" target="_blank" style="color:#0284c7;">[ดูสลิป]</a></div>
                                                <form method="POST" style="display:flex; gap:4px;">
                                                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                    <input type="hidden" name="side" value="requester">
                                                    <input type="hidden" name="action" value="approve_slip">
                                                    <button type="submit" name="slip_result" value="approved" class="btn-approve">อนุมัติ</button>
                                                    <button type="submit" name="slip_result" value="rejected" class="btn-reject">ไม่อนุมัติ</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>

                                        <!-- QC Actions -->
                                        <?php if ($r['admin_qc_requester_status'] === 'pending' || $r['admin_qc_offer_status'] === 'pending'): ?>
                                            <div style="margin-top:10px; text-align:center;">
                                                <button type="button" class="btn-approve" style="padding:10px 12px; font-size:13px; width:100%; background:#0284c7;" onclick="openAdminQcForm(<?= $r['request_id'] ?>, <?= (int)$r['requester_id'] ?>, <?= (int)$r['target_owner_id'] ?>)"><i class="fas fa-camera"></i> ทำบันทึกผล QC ทั้งสองฝ่าย</button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($r['admin_qc_requester_status'] === 'passed' && $r['admin_qc_offer_status'] === 'passed'): ?>
                                            <?php if (empty($r['admin_tracking_to_requester'])): ?>
                                                <form method="POST" style="display:flex; flex-direction:column; gap:6px;">
                                                    <input type="hidden" name="action" value="admin_ship">
                                                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                    <input type="text" name="admin_tracking_to_offer" placeholder="เลขพัสดุ (ส่งให้ผู้ตั้งโพสต์)" required style="padding:4px; font-size:11px; border:1px solid #ccc; border-radius:4px;">
                                                    <input type="text" name="admin_tracking_to_requester" placeholder="เลขพัสดุ (ส่งให้ผู้ขอแลก)" required style="padding:4px; font-size:11px; border:1px solid #ccc; border-radius:4px;">
                                                    <button type="submit" class="btn-approve" style="padding:4px;">บันทึกจัดส่ง</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="action-badge badge-green">จัดส่งเรียบร้อย</span>
                                                <div style="font-size:11px; margin-top:4px;">
                                                    ผู้ตั้งโพสต์: <?= htmlspecialchars($r['admin_tracking_to_offer'] ?? '') ?><br>
                                                    ผู้ขอแลก: <?= htmlspecialchars($r['admin_tracking_to_requester'] ?? '') ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="imgModal" class="modal" onclick="this.style.display='none'">
        <img class="modal-content" id="imgModalContent">
    </div>

    <div id="condModal" class="modal" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal-content" style="background:#fff; padding:20px; border-radius:12px; width:90%; max-width:800px; max-height:90vh; overflow-y:auto; margin:5% auto;">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">รูปภาพสภาพหนังสือที่ตกลงกันไว้</h3>
            <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <p style="margin-bottom:5px; font-weight:600;">ปกหน้า</p>
                    <img id="condFront" style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="">
                </div>
                <div style="flex:1; min-width:200px;">
                    <p style="margin-bottom:5px; font-weight:600;">ปกหลัง</p>
                    <img id="condBack" style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="">
                </div>
                <div style="flex:1; min-width:200px;">
                    <p style="margin-bottom:5px; font-weight:600;">สันหนังสือ</p>
                    <img id="condSpine" style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="">
                </div>
            </div>
            <div style="text-align:right; margin-top:20px;">
                <button type="button" class="btn-reject" onclick="document.getElementById('condModal').style.display='none'">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Admin QC Form Modal -->
    <div id="adminQcFormModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
        <div style="background:#fff; width:90%; max-width:800px; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.2); overflow:hidden; margin:auto;">
            <div style="background:#f1f5f9; padding:20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; color:#334155; font-size:18px;"><i class="fas fa-clipboard-check"></i> บันทึกผลการตรวจสอบ QC สินค้า</h3>
                <span onclick="document.getElementById('adminQcFormModal').style.display='none'" style="cursor:pointer; font-size:26px; color:#b66f68; line-height:1; opacity:0.7;">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data" style="padding:20px;">
                <input type="hidden" name="action" value="submit_qc_report_both">
                <input type="hidden" name="request_id" id="qc_request_id">
                <input type="hidden" name="requester_id" id="qc_requester_id">
                <input type="hidden" name="offer_id" id="qc_offer_id">
                
                <div style="display:flex; gap:20px; flex-wrap:wrap;">
                    <!-- Section 1: Requester -->
                    <div style="flex:1; min-width:300px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:12px; padding:15px;">
                        <h4 style="margin:0 0 10px 0; color:#0f172a; font-size:15px; border-bottom:2px solid #e2e8f0; padding-bottom:8px;">ผู้ขอแลก <span style="color:#2563eb;">[ผู้ส่งมา]</span></h4>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:8px;">1. สินค้าตรงตามที่ตกลงหรือไม่?</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="1" required checked> ตรงตาม (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="0" required> ไม่ตรงตาม (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:5px;">2. หมายเหตุจากส่วนกลาง</label>
                            <textarea name="req_notes" rows="3" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;" placeholder="ตัวอย่าง: สภาพดีมาก ไม่มีรอยตำหนิ" required></textarea>
                        </div>
                        
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพจากส่วนกลาง (ถ่ายให้ชัดเจน)</label>
                            <input type="file" name="req_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                    
                    <!-- Section 2: Offerer -->
                    <div style="flex:1; min-width:300px; background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:15px;">
                        <h4 style="margin:0 0 10px 0; color:#7f1d1d; font-size:15px; border-bottom:2px solid #fca5a5; padding-bottom:8px;">ผู้ตั้งโพสต์ <span style="color:#dc2626;">[ผู้รับแลก]</span></h4>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:8px;">1. สินค้าตรงตามที่ตกลงหรือไม่?</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="1" required checked> ตรงตาม (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="0" required> ไม่ตรงตาม (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:5px;">2. หมายเหตุจากส่วนกลาง</label>
                            <textarea name="off_notes" rows="3" style="width:100%; padding:8px; border:1px solid #fca5a5; border-radius:6px; font-size:13px;" placeholder="ตัวอย่าง: มีรอยยับมุมหนังสือ" required></textarea>
                        </div>
                        
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพจากส่วนกลาง (ถ่ายให้ชัดเจน)</label>
                            <input type="file" name="off_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                    <button type="submit" style="width:100%; background:#d18aa7; color:#fff; border:none; border-radius:8px; font-size:16px; font-weight:bold; padding:14px; cursor:pointer; transition:background 0.3s;" onmouseover="this.style.background='#a95575'" onmouseout="this.style.background='#d18aa7'">ส่งผลการ QC ให้ผู้ใช้งานทั้งสองฝ่าย</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAdminQcForm(reqId, reqUserId, offUserId) {
            document.getElementById('qc_request_id').value = reqId;
            document.getElementById('qc_requester_id').value = reqUserId;
            document.getElementById('qc_offer_id').value = offUserId;
            document.getElementById('adminQcFormModal').style.display = 'flex';
        }
    </script>
</body>
</html>
PHP_EOF;

file_put_contents($file, $content);
echo "Restored original UI!";
