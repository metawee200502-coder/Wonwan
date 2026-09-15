import re

# Read current file
with open('c:/laragon/www/wanawan_project/admin/qc_exchanges.php', 'r', encoding='utf-8') as f:
    content = f.read()

# We will completely replace the HTML from <body> down, and the <style> tag.
# But keep the PHP at the top intact.

php_logic_match = re.search(r'(<\?php.*?<!DOCTYPE html>)', content, re.DOTALL)
if php_logic_match:
    php_logic = php_logic_match.group(1)
else:
    print("Could not find PHP logic block")
    exit()

# Wait, we need to extract the SQL query and the PHP functions, which are currently scattered.
# Actually, the SQL query is currently placed inside the HTML: `<?php $reqs = $pdo->query(...)->fetchAll(); ... ?>`
# I will extract it and put it at the very top, before the HTML.

new_file_content = """<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve_slip') {
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
            $msg = "📋 ประกาศผล QC จากส่วนกลาง\\n";
            $msg .= "ผู้ขอแลก (ฝั่งส่งมา) ได้ผล: " . ($req_is_match ? "✅ ผ่าน" : "❌ ไม่ผ่าน") . "\\n";
            $msg .= "หมายเหตุ: " . $req_notes . "\\n\\n";
            $msg .= "ผู้ตั้งโพสต์ (ฝั่งรับแลก) ได้ผล: " . ($off_is_match ? "✅ ผ่าน" : "❌ ไม่ผ่าน") . "\\n";
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

    header("Location: qc_exchanges.php");
    exit;
}

// Data Fetching
$reqs = $pdo->query("
    SELECT r.*,
           e_req.title as req_title, e_req.image_cover as req_img, e_req.owner_id as target_owner_id,
           e_off.title as off_title, e_off.image_cover as off_img, r.requester_id,
           u1.display_name as target_owner_name, u1.profile_image as target_owner_image, u1.email as target_owner_email,
           u2.display_name as requester_name, u2.profile_image as requester_image, u2.email as requester_email,
           c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects, c_off.defects_description as off_cond_defects_desc, c_off.notes as off_cond_notes,
           c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects, c_req.defects_description as req_cond_defects_desc, c_req.notes as req_cond_notes
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
    if ($opt === 'admin_qc') return 'ผ่านจุดตรวจส่วนกลาง';
    if ($opt === 'meetup') return 'นัดรับ (Meet-up)';
    return 'ยังไม่ระบุ';
}

function getSlipBadgeHtml($status, $url) {
    $btn = '';
    if (!empty($url)) {
        $btn = ' <button type="button" class="qc-slip-view-btn" onclick="openModal(\\''.htmlspecialchars(str_replace('\\\\', '/', $url)).'\\')">ดูสลิป</button>';
    }
    
    if ($status === 'verified' || $status === 'approved') return '<span class="qc-slip-badge success">แนบแล้ว (ตรวจสอบแล้ว)</span>'.$btn;
    if ($status === 'pending') return '<span class="qc-slip-badge warning">แนบแล้ว (รอตรวจสอบ)</span>'.$btn;
    if ($status === 'rejected') return '<span class="qc-slip-badge danger">สลิปไม่ถูกต้อง</span>'.$btn;
    return '<span class="qc-slip-badge warning-light">ยังไม่แนบสลิป</span>';
}

function getConditionPhotosCount($front, $back, $spine, $defects_json) {
    $count = 0;
    if (!empty($front)) $count++;
    if (!empty($back)) $count++;
    if (!empty($spine)) $count++;
    
    $defects = json_decode($defects_json ?? '[]', true) ?: [];
    $count += count($defects);
    return $count;
}

function chatImage($v) {
    if(!$v) return '/assets/images/default-avatar.png';
    if(preg_match('#^https?://#i',$v)) return $v;
    $v = ltrim(str_replace('\\\\','/',$v),'/');
    if(str_starts_with($v,'pages/')) return '/'.$v;
    return '/'.$v;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>QC แลกเปลี่ยนหนังสือ - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #F8FAFC;
            --white: #FFFFFF;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border-light: #E2E8F0;
            --primary: #0F766E;
            --primary-hover: #0F766E; /* Slightly darker in practice */
            
            --blue-bg: #E0F2FE;
            --blue-text: #0284C7;
            
            --green-bg: #ECFDF5;
            --green-text: #059669;
            
            --pink-bg: #FEF2F2;
            --pink-text: #E11D48;
            
            --grey-bg: #F1F5F9;
            --grey-text: #475569;
            
            --orange-bg: #FFFBEB;
            --orange-text: #D97706;
        }

        body { 
            background: var(--bg-color); 
            margin: 0; 
            display: flex; 
            min-height: 100vh; 
            font-family: 'Prompt', sans-serif;
            color: var(--text-main);
        }
        
        .admin-wrap { max-width: unset; margin: unset; padding: unset; min-height: unset; font-family: unset; }
        .admin-main-wrap { flex: 1; padding: 0 40px 40px 40px; width: 100%; box-sizing: border-box; overflow-x: hidden; }

        /* Top Header Portal */
        .qc-portal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 25px;
        }
        .qc-portal-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .qc-portal-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .qc-portal-brand .icon {
            background: var(--primary);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .qc-portal-brand .text {
            font-size: 11px;
            line-height: 1.2;
            color: var(--primary);
        }
        .qc-portal-brand .text strong {
            font-size: 14px;
            color: var(--text-main);
        }
        .qc-breadcrumbs {
            font-size: 13px;
            color: var(--text-muted);
            border-left: 1px solid var(--border-light);
            padding-left: 20px;
        }
        .qc-breadcrumbs strong { color: var(--text-main); }
        
        .qc-portal-right { display: flex; align-items: center; gap: 15px; }
        .qc-bell { color: var(--text-muted); font-size: 18px; position: relative; }
        .qc-bell .dot { position: absolute; top:0; right:0; width:6px; height:6px; background:#10B981; border-radius:50%; }
        .qc-profile-chip {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .qc-profile-chip .avatar {
            width: 36px; height: 36px; border-radius: 50%; background: #1E293B; color:white;
            display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:14px;
        }
        .qc-profile-chip .info { font-size: 12px; line-height: 1.2; }
        .qc-profile-chip .info strong { color: var(--text-main); font-size:13px; display:block; }
        .qc-profile-chip .info span { color: var(--text-muted); }

        /* Title Area */
        .qc-title-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .qc-title-area h1 {
            font-size: 24px;
            margin: 0 0 5px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .qc-status-count-badge {
            font-size: 12px;
            background: var(--green-bg);
            color: var(--green-text);
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: normal;
        }
        .qc-title-area p { margin: 0; color: var(--text-muted); font-size: 14px; }
        
        .qc-action-buttons { display: flex; gap: 10px; }
        .btn-outline {
            background: white; border: 1px solid var(--border-light);
            padding: 10px 16px; border-radius: 8px; color: var(--text-main);
            font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-family: 'Prompt';
        }
        .btn-dark {
            background: var(--text-main); color: white; border: none;
            padding: 10px 16px; border-radius: 8px;
            font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-family: 'Prompt';
        }

        /* Filter Bar */
        .qc-filter-bar {
            background: white;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .qc-search { display: flex; align-items: center; gap: 10px; flex: 1; max-width: 400px; color: var(--text-muted); }
        .qc-search input {
            border: none; outline: none; font-family: 'Prompt'; font-size: 14px; width: 100%;
        }
        .qc-filters { display: flex; align-items: center; gap: 15px; font-size: 13px; color: var(--text-muted); }
        .qc-filters select {
            border: 1px solid var(--border-light); border-radius: 6px; padding: 6px 12px;
            font-family: 'Prompt'; font-size: 13px; color: var(--text-main); outline:none;
        }
        
        /* Main Layout */
        .qc-list-container {
            background: white;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            overflow: hidden;
        }
        .qc-list-header {
            display: flex;
            background: #F8FAFC;
            border-bottom: 1px solid var(--border-light);
            padding: 15px 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            gap: 20px;
        }
        .qc-step-circle {
            display: inline-flex; align-items: center; justify-content: center;
            width: 18px; height: 18px; border-radius: 50%;
            background: #E2E8F0; color: var(--text-muted);
            font-size: 11px; font-weight: bold; margin-right: 6px;
        }
        .qc-step-circle.green { background: #D1FAE5; color: #059669; }

        .qc-card {
            display: flex;
            padding: 25px 20px;
            border-bottom: 1px solid var(--border-light);
            gap: 20px;
            background: white;
            transition: background 0.2s;
        }
        .qc-card:hover { background: #FAFAFA; }
        .qc-card:last-child { border-bottom: none; }

        /* Col 1 */
        .qc-col-1 { flex: 0 0 130px; }
        .qc-req-id { font-size: 15px; font-weight: bold; color: var(--primary); display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .qc-type-badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
            background: var(--blue-bg); color: var(--blue-text); margin-bottom: 15px;
        }
        .qc-type-badge.green { background: var(--green-bg); color: var(--green-text); }
        .qc-timestamp { font-size: 11px; color: var(--text-muted); display:flex; align-items:center; gap:5px; }

        /* Col 2 & 3 */
        .qc-col-2, .qc-col-3 { flex: 1; display: flex; flex-direction: column; gap: 15px; }
        
        .qc-user-card {
            border: 1px solid var(--border-light);
            border-radius: 8px; padding: 12px;
            position: relative;
        }
        .qc-user-card.pink { border-color: #FECACA; background: #FFF5F5; }
        
        .qc-user-header { display: flex; align-items: center; justify-content: space-between; font-size: 13px; margin-bottom: 10px; }
        .qc-user-header .role { display: flex; align-items: center; gap: 8px; font-weight: 600; }
        .qc-dot { width: 8px; height: 8px; border-radius: 50%; }
        .qc-dot.green { background: #10B981; }
        .qc-dot.red { background: #EF4444; }
        .qc-dot.grey { background: #CBD5E1; }
        .qc-username { font-weight: bold; color: var(--text-main); }
        
        .qc-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 11px; margin-bottom: 12px; }
        .qc-label { color: var(--text-muted); margin-bottom: 2px; }
        .qc-val { color: var(--text-main); font-weight: 500; }
        .qc-val.tracking { color: var(--primary); font-weight: 600; }
        
        .qc-slip-row { display: flex; align-items: center; justify-content: space-between; font-size: 11px; }
        
        .qc-slip-badge {
            padding: 2px 8px; border-radius: 12px; font-weight: 600; border: 1px solid transparent;
        }
        .qc-slip-badge.warning { background: var(--orange-bg); color: var(--orange-text); border-color: #FDE68A; }
        .qc-slip-badge.warning-light { color: #D97706; }
        .qc-slip-badge.success { background: var(--green-bg); color: var(--green-text); border-color: #A7F3D0; }
        .qc-slip-badge.danger { background: var(--pink-bg); color: var(--pink-text); border-color: #FECACA; }
        
        .qc-slip-view-btn {
            background: none; border: none; color: var(--blue-text); text-decoration: underline; font-size: 11px; cursor: pointer; padding: 0; font-family: 'Prompt';
        }

        /* Col 3 specific */
        .qc-book-card {
            border: 1px solid var(--border-light);
            border-radius: 8px; padding: 12px;
        }
        .qc-book-header { font-size: 12px; font-weight: 600; color: var(--text-main); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .qc-book-header i { color: var(--primary); }
        .qc-book-title { font-size: 12px; font-weight: bold; margin-bottom: 12px; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .qc-photo-btn {
            border: 1px solid var(--border-light); background: var(--bg-color); color: var(--text-muted);
            padding: 4px 10px; border-radius: 20px; font-size: 11px; cursor: pointer; font-family: 'Prompt';
            display: inline-flex; align-items: center; gap: 6px;
        }
        .qc-photo-btn.has-photo {
            border-color: #A7F3D0; background: var(--green-bg); color: var(--green-text); font-weight: 600;
        }

        /* Col 4 */
        .qc-col-4 { flex: 1.2; display: flex; flex-direction: column; justify-content: center; gap: 20px; padding-left: 20px; }
        
        .qc-primary-btn {
            background: var(--primary); color: white; border: none; border-radius: 8px;
            padding: 12px; font-size: 14px; font-weight: 600; font-family: 'Prompt'; width: 100%;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 4px 10px rgba(15, 118, 110, 0.2); transition: 0.2s;
        }
        .qc-primary-btn:hover { background: #115E59; }
        .qc-primary-note { font-size: 11px; color: var(--text-muted); text-align: left; line-height: 1.4; margin-top: 8px; }
        
        .qc-step-box {
            background: #F8FAFC; border: 1px dashed var(--border-light); border-radius: 8px; padding: 12px;
        }
        .qc-step-box .title { font-size: 12px; font-weight: bold; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .qc-step-box .desc { font-size: 11px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }

        /* Success States in Col 4 */
        .qc-success-row { font-size: 13px; color: var(--green-text); font-weight: 600; display: flex; justify-content: space-between; margin-bottom: 6px; }
        .qc-success-desc { font-size: 12px; color: var(--text-main); display: flex; align-items: center; gap: 6px; }
        .qc-success-desc i { color: var(--green-text); }
        
        .qc-print-box { border: 1px solid var(--border-light); border-radius: 8px; padding: 12px; margin-top: auto; }
        .qc-print-box .header { display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-bottom: 12px; }
        .qc-print-box .tag { font-size: 10px; color: var(--text-muted); }

        /* Pagination & Footer */
        .qc-footer { display: flex; justify-content: space-between; align-items: center; padding: 20px; font-size: 13px; color: var(--text-muted); }
        .qc-pagination { display: flex; gap: 5px; }
        .qc-page-btn {
            border: 1px solid var(--border-light); background: white; padding: 4px 12px; border-radius: 20px; cursor: pointer; color: var(--text-main); font-family: 'Prompt';
        }
        .qc-page-btn.active { background: var(--bg-color); font-weight: bold; border-color: var(--primary); color: var(--primary); }

        .qc-sop-box {
            background: var(--green-bg); border: 1px solid #A7F3D0; border-radius: 12px; padding: 20px; margin-top: 20px;
            display: flex; gap: 15px; font-size: 13px; color: var(--text-main); line-height: 1.5;
        }
        .qc-sop-box .icon { font-size: 20px; color: var(--primary); }

        /* Modal specific overrides */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            animation: zoomIn 0.3s ease;
        }
        @keyframes zoomIn {
            from {transform: scale(0.9); opacity: 0;}
            to {transform: scale(1); opacity: 1;}
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>

    <div class="admin-main-wrap">
        <!-- Portal Header -->
        <div class="qc-portal-header">
            <div class="qc-portal-left">
                <div class="qc-portal-brand">
                    <div class="icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="text">HUB OPERATIONS<br><strong>BookExchange Portal</strong></div>
                </div>
                <div class="qc-breadcrumbs">
                    หน้าหลัก / ระบบตรวจสอบคุณภาพ / <strong>รายการรอ QC</strong>
                </div>
            </div>
            <div class="qc-portal-right">
                <div class="qc-bell"><i class="far fa-bell"></i><div class="dot"></div></div>
                <div class="qc-profile-chip">
                    <div class="avatar">QC</div>
                    <div class="info">
                        <strong>Admin Inspector #04</strong>
                        <span>ส่วนกลาง (Central Hub)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Title Area -->
        <div class="qc-title-area">
            <div>
                <h1>จัดการคำขอแลกเปลี่ยน (QC) <span class="qc-status-count-badge">รอดำเนินการ <?= count($reqs) ?> รายการ</span></h1>
                <p>ตรวจสอบสภาพหนังสือจริงจากทั้งสองฝั่ง เปรียบเทียบกับภาพที่แจ้งในระบบ ก่อนส่งมอบให้ลูกค้าปลายทาง</p>
            </div>
            <div class="qc-action-buttons">
                <button class="btn-outline"><i class="fas fa-file-export"></i> ส่งออกข้อมูล</button>
                <button class="btn-dark" onclick="location.reload()"><i class="fas fa-sync-alt"></i> รีเฟรชสถานะ</button>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_msg'])): ?>
            <div style="background:#D1FAE5; color:#065F46; padding:15px; margin-bottom:20px; border-radius:8px; border:1px solid #34D399;">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['flash_msg']) ?>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="qc-filter-bar">
            <div class="qc-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="ค้นหารหัสคำขอ (#EX-...), ชื่อผู้ใช้ หรือเลขพัสดุ...">
            </div>
            <div class="qc-filters">
                <span>คัดกรอง:</span>
                <select><option>สถานะ: ทั้งหมด (<?= count($reqs) ?>)</option></select>
                <select><option>ประเภท: ทั้งหมด</option></select>
                <button class="btn-outline" style="padding:6px 12px;"><i class="fas fa-filter"></i></button>
            </div>
        </div>

        <?php if (empty($reqs)): ?>
            <div style="text-align:center; padding: 50px; color: var(--text-muted);">
                <i class="fas fa-box-open" style="font-size:40px; margin-bottom:15px; color:#CBD5E1;"></i>
                <p>ไม่มีรายการพัสดุที่รอการตรวจสภาพในขณะนี้</p>
            </div>
        <?php else: ?>
            <div class="qc-list-container">
                <div class="qc-list-header">
                    <div style="flex: 0 0 130px;">รหัสคำขอ</div>
                    <div style="flex: 1;"><span class="qc-step-circle">1</span> ข้อมูล & พัสดุขาเข้า (จากลูกค้า)</div>
                    <div style="flex: 1;"><span class="qc-step-circle">2</span> สภาพสินค้าดั้งเดิม</div>
                    <div style="flex: 1.2;"><span class="qc-step-circle green">3-4</span> ผลตรวจสอบ & ขาออก</div>
                </div>

                <?php foreach ($reqs as $r): ?>
                    <div class="qc-card">
                        <!-- Column 1 -->
                        <div class="qc-col-1">
                            <div class="qc-req-id">#EX-<?= $r['request_id'] ?> <i class="far fa-copy" style="color:#CBD5E1; cursor:pointer; font-size:12px;"></i></div>
                            <?php if ($r['trade_option'] === 'admin_qc'): ?>
                                <div class="qc-type-badge green"><?= formatTradeOption($r['trade_option']) ?></div>
                            <?php else: ?>
                                <div class="qc-type-badge"><?= formatTradeOption($r['trade_option']) ?></div>
                            <?php endif; ?>
                            <div class="qc-timestamp"><i class="far fa-clock"></i> สร้างเมื่อ: <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?> น.</div>
                        </div>

                        <!-- Column 2 -->
                        <div class="qc-col-2">
                            <!-- Requester -->
                            <div class="qc-user-card">
                                <div class="qc-user-header">
                                    <div class="role"><span class="qc-dot green"></span> ผู้ขอแลก</div>
                                    <div class="qc-username"><?= htmlspecialchars($r['requester_name'] ?? 'ไม่มีชื่อ') ?></div>
                                </div>
                                <div class="qc-details-grid">
                                    <div>
                                        <div class="qc-label">ที่อยู่ปลายทาง</div>
                                        <div class="qc-val"><?= mb_strimwidth(htmlspecialchars($r['requester_address'] ?? 'ยังไม่ระบุ'), 0, 30, '...') ?></div>
                                    </div>
                                    <div>
                                        <div class="qc-label">เลขพัสดุขาเข้า</div>
                                        <div class="qc-val tracking"><?= htmlspecialchars($r['requester_tracking_to_admin'] ?? '-') ?></div>
                                    </div>
                                </div>
                                <div class="qc-slip-row">
                                    <div class="qc-label">สลิปมัดจำ/ขนส่ง:</div>
                                    <div><?= getSlipBadgeHtml($r['requester_slip_status'], $r['requester_slip_url']) ?></div>
                                </div>
                                <?php if ($r['requester_slip_status'] === 'pending' && !empty($r['requester_slip_url'])): ?>
                                    <form method="POST" style="margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light); display:flex; gap:5px; justify-content:flex-end;">
                                        <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                        <input type="hidden" name="side" value="requester">
                                        <input type="hidden" name="action" value="approve_slip">
                                        <button type="submit" name="slip_result" value="approved" style="background:#10B981; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer;">อนุมัติสลิป</button>
                                        <button type="submit" name="slip_result" value="rejected" style="background:#EF4444; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer;">ไม่อนุมัติ</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Offerer -->
                            <div class="qc-user-card pink">
                                <div class="qc-user-header">
                                    <div class="role"><span class="qc-dot red"></span> ผู้รับแลก</div>
                                    <div class="qc-username"><?= htmlspecialchars($r['target_owner_name'] ?? 'ไม่มีชื่อ') ?></div>
                                </div>
                                <div class="qc-details-grid">
                                    <div>
                                        <div class="qc-label">ที่อยู่ปลายทาง</div>
                                        <div class="qc-val"><?= mb_strimwidth(htmlspecialchars($r['offer_address'] ?? 'ยังไม่ระบุ'), 0, 30, '...') ?></div>
                                    </div>
                                    <div>
                                        <div class="qc-label">เลขพัสดุขาเข้า</div>
                                        <div class="qc-val tracking"><?= htmlspecialchars($r['offer_tracking_to_admin'] ?? '-') ?></div>
                                    </div>
                                </div>
                                <div class="qc-slip-row">
                                    <div class="qc-label">สลิปมัดจำ/ขนส่ง:</div>
                                    <div><?= getSlipBadgeHtml($r['offer_slip_status'], $r['offer_slip_url']) ?></div>
                                </div>
                                <?php if ($r['offer_slip_status'] === 'pending' && !empty($r['offer_slip_url'])): ?>
                                    <form method="POST" style="margin-top:10px; padding-top:10px; border-top:1px dashed #FECACA; display:flex; gap:5px; justify-content:flex-end;">
                                        <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                        <input type="hidden" name="side" value="offer">
                                        <input type="hidden" name="action" value="approve_slip">
                                        <button type="submit" name="slip_result" value="approved" style="background:#10B981; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer;">อนุมัติสลิป</button>
                                        <button type="submit" name="slip_result" value="rejected" style="background:#EF4444; color:white; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer;">ไม่อนุมัติ</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Column 3 -->
                        <div class="qc-col-3">
                            <!-- Req Book -->
                            <div class="qc-book-card">
                                <div class="qc-book-header"><i class="fas fa-book-open"></i> หนังสือของผู้ขอแลก</div>
                                <div class="qc-book-title"><?= htmlspecialchars($r['off_title'] ?? '[รอข้อมูลลงทะเบียนหนังสือ]') ?></div>
                                <?php 
                                $req_count = getConditionPhotosCount($r['req_cond_front'], $r['req_cond_back'], $r['req_cond_spine'], $r['req_cond_defects']);
                                if ($req_count > 0): ?>
                                    <button class="qc-photo-btn has-photo" onclick="openDynamicCondModal('req_cond_<?= $r['request_id'] ?>')">
                                        <i class="fas fa-check"></i> มีภาพถ่ายสภาพ (<?= $req_count ?> รูป)
                                    </button>
                                <?php else: ?>
                                    <button class="qc-photo-btn"><i class="far fa-image"></i> ไม่มีรูปภาพ</button>
                                <?php endif; ?>
                                
                                <!-- Hidden form data for modal -->
                                <div id="req_cond_<?= $r['request_id'] ?>" style="display:none;">
                                    <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                                        <?php if(!empty($r['req_cond_front'])): ?>
                                        <div style="flex:1; min-width:200px;">
                                            <p style="margin-bottom:5px; font-weight:600; font-size:14px;">ปกหน้า</p>
                                            <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openImgModal(this.src)" src="<?= htmlspecialchars(str_replace('\\\\', '/', $r['req_cond_front'])) ?>">
                                        </div>
                                        <?php endif; ?>
                                        <?php if(!empty($r['req_cond_back'])): ?>
                                        <div style="flex:1; min-width:200px;">
                                            <p style="margin-bottom:5px; font-weight:600; font-size:14px;">ปกหลัง</p>
                                            <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openImgModal(this.src)" src="<?= htmlspecialchars(str_replace('\\\\', '/', $r['req_cond_back'])) ?>">
                                        </div>
                                        <?php endif; ?>
                                        <?php if(!empty($r['req_cond_spine'])): ?>
                                        <div style="flex:1; min-width:200px;">
                                            <p style="margin-bottom:5px; font-weight:600; font-size:14px;">สันหนังสือ</p>
                                            <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openImgModal(this.src)" src="<?= htmlspecialchars(str_replace('\\\\', '/', $r['req_cond_spine'])) ?>">
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                    $req_defects = json_decode($r['req_cond_defects'] ?? '[]', true) ?: [];
                                    if (!empty($req_defects)): ?>
                                        <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                                            <h4 style="margin:0 0 10px 0; font-size:15px; color:#b91c1c;">📸 รูปภาพตำหนิ (<?= count($req_defects) ?> รูป)</h4>
                                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                                <?php foreach ($req_defects as $d): ?>
                                                    <img style="width:120px; height:120px; object-fit:cover; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openImgModal(this.src)" src="<?= htmlspecialchars(str_replace('\\\\', '/', $d)) ?>">
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($r['req_cond_defects_desc'])): ?>
                                        <div style="margin-top:15px; background:#fffbeb; border-left:4px solid #f59e0b; padding:12px; border-radius:4px; font-size:14px; color:#92400e;">
                                            <strong>📝 อธิบายตำหนิ:</strong><br><?= nl2br(htmlspecialchars($r['req_cond_defects_desc'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($r['req_cond_notes'])): ?>
                                        <div style="margin-top:10px; background:#f8fafc; border-left:4px solid #94a3b8; padding:12px; border-radius:4px; font-size:14px; color:#334155;">
                                            <strong>ℹ️ หมายเหตุเพิ่มเติม:</strong><br><?= nl2br(htmlspecialchars($r['req_cond_notes'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Off Book -->
                            <div class="qc-book-card">
                                <div class="qc-book-header"><i class="fas fa-book-open"></i> หนังสือของผู้รับแลก</div>
                                <div class="qc-book-title"><?= htmlspecialchars($r['req_title'] ?? '[รอข้อมูลลงทะเบียนหนังสือ]') ?></div>
                                <?php 
                                $off_count = getConditionPhotosCount($r['off_cond_front'], $r['off_cond_back'], $r['off_cond_spine'], $r['off_cond_defects']);
                                if ($off_count > 0): ?>
                                    <button class="qc-photo-btn has-photo" onclick="openDynamicCondModal('off_cond_<?= $r['request_id'] ?>')">
                                        <i class="fas fa-check"></i> มีภาพถ่ายสภาพ (<?= $off_count ?> รูป)
                                    </button>
                                <?php else: ?>
                                    <button class="qc-photo-btn"><i class="far fa-image"></i> ไม่มีรูปภาพ</button>
                                <?php endif; ?>
                                
                                <!-- Hidden form data for modal -->
                                <div id="off_cond_<?= $r['request_id'] ?>" style="display:none;">
                                    <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                                        <?php if(!empty($r['off_cond_front'])): ?>
                                        <div style="flex:1; min-width:200px;">
                                            <p style="margin-bottom:5px; font-weight:600; font-size:14px;">ปกหน้า</p>
                                            <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openImgModal(this.src)" src="<?= htmlspecialchars(str_replace('\\\\', '/', $r['off_cond_front'])) ?>">
                                        </div>
                                        <?php endif; ?>
                                        <?php if(!empty($r['off_cond_back'])): ?>
                                        <div style="flex:1; min-width:200px;">
                                            <p style="margin-bottom:5px; font-weight:600; font-size:14px;">ปกหลัง</p>
                                            <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openImgModal(this.src)" src="<?= htmlspecialchars(str_replace('\\\\', '/', $r['off_cond_back'])) ?>">
                                        </div>
                                        <?php endif; ?>
                                        <?php if(!empty($r['off_cond_spine'])): ?>
                                        <div style="flex:1; min-width:200px;">
                                            <p style="margin-bottom:5px; font-weight:600; font-size:14px;">สันหนังสือ</p>
                                            <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openImgModal(this.src)" src="<?= htmlspecialchars(str_replace('\\\\', '/', $r['off_cond_spine'])) ?>">
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                    $off_defects = json_decode($r['off_cond_defects'] ?? '[]', true) ?: [];
                                    if (!empty($off_defects)): ?>
                                        <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                                            <h4 style="margin:0 0 10px 0; font-size:15px; color:#b91c1c;">📸 รูปภาพตำหนิ (<?= count($off_defects) ?> รูป)</h4>
                                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                                <?php foreach ($off_defects as $d): ?>
                                                    <img style="width:120px; height:120px; object-fit:cover; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openImgModal(this.src)" src="<?= htmlspecialchars(str_replace('\\\\', '/', $d)) ?>">
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($r['off_cond_defects_desc'])): ?>
                                        <div style="margin-top:15px; background:#fffbeb; border-left:4px solid #f59e0b; padding:12px; border-radius:4px; font-size:14px; color:#92400e;">
                                            <strong>📝 อธิบายตำหนิ:</strong><br><?= nl2br(htmlspecialchars($r['off_cond_defects_desc'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($r['off_cond_notes'])): ?>
                                        <div style="margin-top:10px; background:#f8fafc; border-left:4px solid #94a3b8; padding:12px; border-radius:4px; font-size:14px; color:#334155;">
                                            <strong>ℹ️ หมายเหตุเพิ่มเติม:</strong><br><?= nl2br(htmlspecialchars($r['off_cond_notes'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Column 4 -->
                        <div class="qc-col-4">
                            <?php if ($r['admin_qc_requester_status'] === 'pending' || $r['admin_qc_offer_status'] === 'pending'): ?>
                                <!-- Not yet QC'd -->
                                <div>
                                    <button type="button" class="qc-primary-btn" onclick="openAdminQcForm(<?= $r['request_id'] ?>, <?= (int)$r['requester_id'] ?>, <?= (int)$r['target_owner_id'] ?>)">
                                        <i class="fas fa-clipboard-list"></i> ส่งฟอร์มรายงาน QC
                                    </button>
                                    <div class="qc-primary-note">* กรอกฟอร์มเพื่อส่งรายงานภาพสภาพจริงปัจจุบันให้ลูกค้าตรวจสอบและตัดสินใจ</div>
                                </div>
                                <div class="qc-step-box">
                                    <div class="title"><span class="qc-dot grey" style="display:inline-block;"></span> 4. จัดส่งขาออก (ถึงลูกค้า)</div>
                                    <div class="desc"><i class="far fa-clock"></i> รอดำเนินผล QC และลูกค้ายอมรับสภาพ</div>
                                </div>
                            <?php elseif ($r['requester_qc_acceptance_status'] === 'accepted' && $r['offer_qc_acceptance_status'] === 'accepted'): ?>
                                <!-- QC Done & Accepted -->
                                <div>
                                    <div class="qc-success-row">
                                        <span><i class="fas fa-check-circle"></i> ตรวจ QC ผ่านแล้วทั้งคู่</span>
                                        <a href="#" style="color:var(--primary); font-size:11px;">ดูรายงาน</a>
                                    </div>
                                    <div class="qc-success-desc"><i class="fas fa-check"></i> ทั้งสองฝ่ายกดยอมรับสภาพหนังสือเรียบร้อยแล้ว</div>
                                </div>
                                
                                <div class="qc-print-box">
                                    <div class="header">
                                        <span style="display:flex; align-items:center; gap:6px;"><span class="qc-dot green" style="display:inline-block;"></span> <strong>พร้อมออกใบปะหน้าพัสดุ</strong></span>
                                        <span class="tag">Hub Outbound</span>
                                    </div>
                                    <?php if (empty($r['admin_tracking_to_requester'])): ?>
                                        <form method="POST" style="display:flex; flex-direction:column; gap:8px;">
                                            <input type="hidden" name="action" value="admin_ship">
                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                            <input type="text" name="admin_tracking_to_requester" placeholder="เลขพัสดุ (ผู้ขอแลก)" required style="padding:6px; font-size:11px; border:1px solid #cbd5e1; border-radius:4px; font-family:'Prompt';">
                                            <input type="text" name="admin_tracking_to_offer" placeholder="เลขพัสดุ (ผู้รับแลก)" required style="padding:6px; font-size:11px; border:1px solid #cbd5e1; border-radius:4px; font-family:'Prompt';">
                                            <button type="submit" class="btn-dark" style="width:100%; justify-content:center;">พิมพ์ใบปะหน้า & บันทึกเลขพัสดุ</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn-dark" style="width:100%; justify-content:center; background:#475569;" disabled>จัดส่งเรียบร้อยแล้ว</button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Waiting for customer decision -->
                                <div>
                                    <div class="qc-success-row" style="color:#D97706;">
                                        <span><i class="fas fa-paper-plane"></i> ส่งผลตรวจสอบแล้ว</span>
                                        <a href="#" style="color:var(--primary); font-size:11px;">ดูรายงาน</a>
                                    </div>
                                    <div class="qc-success-desc" style="color:#D97706;"><i class="fas fa-hourglass-half"></i> รอลูกค้าทั้งสองฝ่ายพิจารณาตัดสินใจ</div>
                                </div>
                                <div class="qc-step-box">
                                    <div class="title"><span class="qc-dot grey" style="display:inline-block;"></span> 4. จัดส่งขาออก (ถึงลูกค้า)</div>
                                    <div class="desc"><i class="far fa-clock"></i> รอลูกค้ายืนยันยอมรับสภาพ</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Footer Pagination -->
                <div class="qc-footer">
                    <div>แสดง <strong>1 - <?= count($reqs) ?></strong> จากทั้งหมด <strong><?= count($reqs) ?></strong> คำขอแลกเปลี่ยน</div>
                    <div class="qc-pagination">
                        <button class="qc-page-btn" disabled style="opacity:0.5;">ก่อนหน้า</button>
                        <button class="qc-page-btn active">1</button>
                        <button class="qc-page-btn" disabled style="opacity:0.5;">ถัดไป</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SOP Box -->
        <div class="qc-sop-box">
            <div class="icon"><i class="fas fa-info-circle"></i></div>
            <div>
                <strong>เกณฑ์การปฏิบัติงานตรวจสภาพหนังสือ (Central QC SOP):</strong><br>
                เมื่อหนังสือมาถึงคลังส่วนกลาง เจ้าหน้าที่ต้องเปิดกล่อง บันทึกภาพมุมสัน สันกาว รอยพับ หรือคราบน้ำลงในแบบฟอร์ม เพื่อให้ผู้ใช้ทั้งสองฝั่งกดยืนยัน หากฝั่งใดฝั่งหนึ่งปฏิเสธ ระบบจะส่งหนังสือคืนเจ้าของเดิมอัตโนมัติ
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="imgModal" class="modal" onclick="this.style.display='none'">
        <img class="modal-content" id="imgModalContent">
    </div>

    <div id="condModal" class="modal" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal-content" style="background:#fff; padding:30px; border-radius:12px; width:90%; max-width:900px; max-height:90vh; overflow-y:auto; margin:5% auto; position:relative;">
            <span onclick="document.getElementById('condModal').style.display='none'" style="position:absolute; top:20px; right:20px; cursor:pointer; font-size:24px; color:#999;">&times;</span>
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px; font-size:20px; color:#1f2937;"><i class="fas fa-file-invoice"></i> ข้อมูลฟอร์มสภาพหนังสือ</h3>
            <div id="condModalBody"></div>
            <div style="text-align:center; margin-top:30px; border-top:1px solid #eee; padding-top:20px;">
                <button type="button" class="btn-outline" style="margin:auto;" onclick="document.getElementById('condModal').style.display='none'">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- Admin QC Form Modal -->
    <div id="adminQcFormModal" class="modal" style="background:rgba(0,0,0,0.6); align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
        <div style="background:#fff; width:90%; max-width:800px; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.2); overflow:hidden; margin:auto; position:relative;">
            <div style="background:#F8FAFC; padding:20px; border-bottom:1px solid var(--border-light); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; color:var(--text-main); font-size:18px;"><i class="fas fa-clipboard-check" style="color:var(--primary);"></i> ส่งฟอร์มรายงาน QC จากส่วนกลาง</h3>
                <span onclick="document.getElementById('adminQcFormModal').style.display='none'" style="cursor:pointer; font-size:26px; color:#94A3B8; line-height:1;">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data" style="padding:20px;">
                <input type="hidden" name="action" value="submit_qc_report_both">
                <input type="hidden" name="request_id" id="qc_request_id">
                <input type="hidden" name="requester_id" id="qc_requester_id">
                <input type="hidden" name="offer_id" id="qc_offer_id">
                
                <div style="display:flex; gap:20px; flex-wrap:wrap;">
                    <!-- Section 1 -->
                    <div style="flex:1; min-width:300px; background:#F8FAFC; border:1px solid #CBD5E1; border-radius:12px; padding:15px;">
                        <h4 style="margin:0 0 10px 0; color:#0F172A; font-size:15px; border-bottom:2px solid #E2E8F0; padding-bottom:8px;">การประเมินฝั่ง <span style="color:#059669;">[ผู้ขอแลก]</span></h4>
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:8px;">1. สภาพตรงตามที่แจ้งไว้หรือไม่?</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="1" required checked> ตรง (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="0" required> ไม่ตรง (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:5px;">2. หมายเหตุถึงลูกค้า</label>
                            <textarea name="req_notes" rows="3" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;" placeholder="เช่น: มีรอยพับที่มุมล่างขวา" required></textarea>
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพสภาพจริง</label>
                            <input type="file" name="req_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                    
                    <!-- Section 2 -->
                    <div style="flex:1; min-width:300px; background:#FEF2F2; border:1px solid #FECACA; border-radius:12px; padding:15px;">
                        <h4 style="margin:0 0 10px 0; color:#7F1D1D; font-size:15px; border-bottom:2px solid #FCA5A5; padding-bottom:8px;">การประเมินฝั่ง <span style="color:#DC2626;">[ผู้รับแลก]</span></h4>
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7F1D1D; display:block; margin-bottom:8px;">1. สภาพตรงตามที่แจ้งไว้หรือไม่?</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="1" required checked> ตรง (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="0" required> ไม่ตรง (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7F1D1D; display:block; margin-bottom:5px;">2. หมายเหตุถึงลูกค้า</label>
                            <textarea name="off_notes" rows="3" style="width:100%; padding:8px; border:1px solid #fca5a5; border-radius:6px; font-size:13px; box-sizing:border-box;" placeholder="เช่น: สภาพสมบูรณ์ดีมาก" required></textarea>
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#7F1D1D; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพสภาพจริง</label>
                            <input type="file" name="off_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                    <button type="submit" class="qc-primary-btn" style="width:100%;">ส่งรายงาน QC ให้ลูกค้าพิจารณา</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(src) {
            document.getElementById('imgModalContent').src = src;
            document.getElementById('imgModal').style.display = 'flex';
        }
        function openImgModal(src) {
            document.getElementById('imgModalContent').src = src;
            document.getElementById('imgModal').style.display = 'flex';
        }
        function openDynamicCondModal(dataDivId) {
            const dataHtml = document.getElementById(dataDivId).innerHTML;
            document.getElementById('condModalBody').innerHTML = dataHtml;
            document.getElementById('condModal').style.display = 'flex';
        }
        function openAdminQcForm(reqId, reqUserId, offUserId) {
            document.getElementById('qc_request_id').value = reqId;
            document.getElementById('qc_requester_id').value = reqUserId;
            document.getElementById('qc_offer_id').value = offUserId;
            document.getElementById('adminQcFormModal').style.display = 'flex';
        }
    </script>
</body>
</html>"""

with open('c:/laragon/www/wanawan_project/scratch/rewrite_qc.php', 'w', encoding='utf-8') as f:
    f.write(new_file_content)

print("Generated rewrite_qc.php")
