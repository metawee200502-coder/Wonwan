<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
require_once __DIR__ . '/../config/config.php';

$userId = (int)$_SESSION['user_id'];
$requestId = (int)($_GET['request_id'] ?? ($_GET['trade_id'] ?? ($_POST['request_id'] ?? 0)));
$conversationId = (int)($_GET['conversation_id'] ?? ($_POST['conversation_id'] ?? 0));
$isIframe = isset($_GET['iframe']) || isset($_POST['iframe']);

if (!$requestId) {
    die("Request ID is missing.");
}

// Fetch request info
// Fetch request info
$stmt = $pdo->prepare("
    SELECT r.*, 
           e_req.owner_id as target_owner_id,
           e_req.title as target_title, e_req.image_cover as target_cover,
           e_off.title as offer_title, e_off.image_cover as offer_cover
    FROM exchange_requests r
    JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
    LEFT JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
    WHERE r.request_id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

$isAdmin = ($_SESSION['role'] ?? '') === 'super_admin';
$isReadonly = isset($_GET['readonly']) && $_GET['readonly'] == 1;

if (!$request || (!$isAdmin && $request['requester_id'] != $userId && $request['target_owner_id'] != $userId)) {
    die("Exchange Request not found or unauthorized.");
}

if (!$request) {
    die("Exchange Request not found or unauthorized.");
}

// Determine roles
$isRequester = ($request['requester_id'] == $userId);
$partnerId = $isRequester ? $request['target_owner_id'] : $request['requester_id'];

$myBookTitle = $isRequester ? ($request['offer_title'] ?? 'สินค้าที่เสนอ') : $request['target_title'];
$partnerBookTitle = $isRequester ? $request['target_title'] : ($request['offer_title'] ?? 'สินค้าที่เสนอ');

// Fetch my form
$myFormStmt = $pdo->prepare("SELECT * FROM trade_condition_forms WHERE request_id = ? AND owner_id = ?");
$myFormStmt->execute([$requestId, $userId]);
$myForm = $myFormStmt->fetch(PDO::FETCH_ASSOC);

// Fetch partner form
$partnerFormStmt = $pdo->prepare("SELECT * FROM trade_condition_forms WHERE request_id = ? AND owner_id = ?");
$partnerFormStmt->execute([$requestId, $partnerId]);
$partnerForm = $partnerFormStmt->fetch(PDO::FETCH_ASSOC);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_form'])) {
        $defectsDesc = trim($_POST['defects_description'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $requiredDeposit = isset($_POST['required_deposit_amount']) && $_POST['required_deposit_amount'] !== '' ? (float)$_POST['required_deposit_amount'] : null;
        
        // Handle file uploads (simplified scaffolding logic)
        $uploadDir = __DIR__ . '/uploads/conditions';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        
        $paths = [];
        $imageFields = ['front_cover', 'back_cover', 'spine'];
        foreach ($imageFields as $field) {
            if (!empty($_FILES[$field]['name'])) {
                $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
                $filename = "cond_{$requestId}_{$userId}_{$field}_" . time() . ".$ext";
                move_uploaded_file($_FILES[$field]['tmp_name'], "$uploadDir/$filename");
                $paths[$field] = "/pages/uploads/conditions/$filename";
            }
        }
        
        $defectUrls = [];
        if (!empty($_FILES['additional_images']['name'][0])) {
            $count = min(3, count($_FILES['additional_images']['name']));
            for ($i = 0; $i < $count; $i++) {
                if (!empty($_FILES['additional_images']['name'][$i])) {
                    $ext = pathinfo($_FILES['additional_images']['name'][$i], PATHINFO_EXTENSION);
                    $filename = "cond_{$requestId}_{$userId}_additional_{$i}_" . time() . ".$ext";
                    move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], "$uploadDir/$filename");
                    $defectUrls[] = "/pages/uploads/conditions/$filename";
                }
            }
        }
        
        $defectUrls = [];
        if (!empty($_FILES['additional_images']['name'][0])) {
            $count = min(3, count($_FILES['additional_images']['name']));
            for ($i = 0; $i < $count; $i++) {
                if (!empty($_FILES['additional_images']['name'][$i])) {
                    $ext = pathinfo($_FILES['additional_images']['name'][$i], PATHINFO_EXTENSION);
                    $filename = "cond_{$requestId}_{$userId}_additional_{$i}_" . time() . ".$ext";
                    move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], "$uploadDir/$filename");
                    $defectUrls[] = "/pages/uploads/conditions/$filename";
                }
            }
        }
        
        if (empty($paths['front_cover']) || empty($paths['back_cover']) || empty($paths['spine'])) {
            $errors[] = 'กรุณาอัปโหลดรูปภาพให้ครบทั้ง 3 มุม (ปกหน้า, ปกหลัง, สัน)';
        }

        if (empty($errors)) {
            $insertStmt = $pdo->prepare("INSERT INTO trade_condition_forms (request_id, owner_id, front_cover_url, back_cover_url, spine_url, defect_urls, defects_description, notes, required_deposit_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $requestId, 
                $userId, 
                $paths['front_cover'], 
                $paths['back_cover'], 
                $paths['spine'],
                json_encode($defectUrls),
                $defectsDesc,
                $notes,
                $requiredDeposit
            ]);
            $success = 'บันทึกฟอร์มสภาพหนังสือสำเร็จ!';
            $myFormStmt->execute([$requestId, $userId]);
            $myForm = $myFormStmt->fetch(PDO::FETCH_ASSOC);
            
            // Insert chat message
            if ($conversationId) {
                $msg = '[CONDITION_FORM_SUBMITTED:'.$userId.']';
                $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId, $userId, $msg]);
                $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
            }
            
            // Auto close modal if in iframe
            if ($isIframe) {
                echo "<script>window.parent.postMessage({action: 'close_condition_modal'}, '*');</script>";
            }
        }
    } elseif (isset($_POST['accept_partner'])) {
        // Accept Partner Form
        if ($partnerForm) {
            $pdo->prepare("UPDATE trade_condition_forms SET is_accepted_by_partner = 1, accepted_at = NOW() WHERE form_id = ?")->execute([$partnerForm['form_id']]);
            $success = 'คุณได้ยอมรับสภาพหนังสือของอีกฝ่ายแล้ว หากอีกฝ่ายยอมรับเช่นกัน ระบบจะทำการหักเงินมัดจำทันที';
            // Refresh
            $partnerFormStmt->execute([$requestId, $partnerId]);
            $partnerForm = $partnerFormStmt->fetch(PDO::FETCH_ASSOC);
            
            // Insert chat message
            if ($conversationId) {
                $msg = '[CONDITION_FORM_ACCEPTED:'.$userId.']';
                $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId, $userId, $msg]);
                $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
            }
            
            // Trigger API equivalent internally
            if ($myForm && $myForm['is_accepted_by_partner'] && $partnerForm['is_accepted_by_partner']) {
                $pdo->prepare("UPDATE exchange_requests SET status = 'accepted' WHERE request_id = ?")->execute([$requestId]);
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wonwan Escrow - ยืนยันสภาพสินค้า</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #175564;
            --primary-dark: #2F6E63;
            --bg-color: #F8FAFC;
            --surface: rgba(255, 255, 255, 0.85);
            --text-main: #1E293B;
            --text-muted: #64748B;
            --glass-border: rgba(255, 255, 255, 0.6);
            --shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        * { box-sizing: border-box; }

        html, body {
            <?php if ($isIframe): ?>
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            <?php endif; ?>
        }
        <?php if ($isIframe): ?>
        html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar {
            display: none; /* Chrome, Safari and Opera */
        }
        * {
            scrollbar-width: none;
        }
        <?php endif; ?>

        body {
            font-family: 'Prompt', sans-serif;
            background: <?= $isIframe ? 'transparent' : 'linear-gradient(135deg, #F0F4F8 0%, #DFE9F3 100%)' ?>;
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
        }

        .navbar {
            background: #fff;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            display: <?= $isIframe ? 'none' : 'flex' ?>;
            align-items: center;
        }

        .navbar img { height: 35px; }

        .container {
            max-width: 900px;
            margin: <?= $isIframe ? '0 auto' : '40px auto' ?>;
            padding: <?= $isIframe ? '15px' : '40px' ?>;
            background: var(--surface);
            border-radius: 24px;
            box-shadow: <?= $isIframe ? 'none' : 'var(--shadow)' ?>;
            backdrop-filter: <?= $isIframe ? 'none' : 'blur(20px)' ?>;
            border: <?= $isIframe ? 'none' : '1px solid var(--glass-border)' ?>;
        }

        .header-title { text-align: center; margin-bottom: <?= $isIframe ? '10px' : '40px' ?>; }
        .header-title h1 {
            font-size: <?= $isIframe ? '20px' : '32px' ?>;
            font-weight: 700;
            background: linear-gradient(135deg, #175564, #2F6E63);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 <?= $isIframe ? '4px' : '10px' ?> 0;
        }
        .header-title p { color: var(--text-muted); font-size: <?= $isIframe ? '13px' : '16px' ?>; margin: 0; }

        .status-badge {
            display: inline-block;
            background: #e0f2f1;
            color: #175564;
            padding: <?= $isIframe ? '4px 12px' : '6px 16px' ?>;
            border-radius: 20px;
            font-weight: 600;
            font-size: <?= $isIframe ? '12px' : '14px' ?>;
            margin-top: <?= $isIframe ? '8px' : '15px' ?>;
        }

        .card-glass {
            background: rgba(255, 255, 255, 0.6);
            border-radius: 16px;
            padding: <?= $isIframe ? '15px' : '25px' ?>;
            border: 1px solid rgba(255, 255, 255, 0.8);
            margin-bottom: <?= $isIframe ? '15px' : '30px' ?>;
            transition: transform 0.2s ease;
        }
        
        .card-glass:hover { transform: translateY(-2px); }

        .section-title {
            font-size: <?= $isIframe ? '16px' : '20px' ?>;
            font-weight: 600;
            margin: 0 0 <?= $isIframe ? '10px' : '20px' ?> 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title span {
            display: flex; align-items: center; justify-content: center;
            width: <?= $isIframe ? '24px' : '30px' ?>; height: <?= $isIframe ? '24px' : '30px' ?>; background: #175564; color: #fff; border-radius: 50%;
            font-size: <?= $isIframe ? '13px' : '16px' ?>; font-weight: bold;
        }

        .upload-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: <?= $isIframe ? '10px' : '20px' ?>;
        }

        .upload-box {
            position: relative;
            height: <?= $isIframe ? '100px' : '220px' ?>;
            border: 2px dashed #CBD5E1;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            background: #F8FAFC;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .upload-box:hover {
            border-color: #175564;
            background: #f0fdfa;
            color: #175564;
        }

        .upload-box input { 
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            z-index: -1;
        }
        .upload-box img {
            width: 100%; height: 100%; object-fit: cover;
            position: absolute; top: 0; left: 0; display: none;
            z-index: 10;
        }
        
        .upload-icon { font-size: 32px; margin-bottom: 10px; }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            font-family: inherit;
            font-size: 15px;
            background: #fff;
            transition: border-color 0.2s;
            margin-bottom: 20px;
            resize: vertical;
        }
        .form-control:focus { outline: none; border-color: #175564; box-shadow: 0 0 0 3px rgba(255, 123, 0, 0.1); }
        .form-label { display: block; font-weight: 500; margin-bottom: 8px; color: #475569; }

        .btn {
            padding: <?= $isIframe ? '12px 20px' : '16px 32px' ?>;
            border: none;
            border-radius: 12px;
            font-size: <?= $isIframe ? '14px' : '16px' ?>;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #175564, #2F6E63);
            color: #fff;
            box-shadow: 0 4px 15px rgba(23, 85, 100, 0.3);
        }
        .btn-primary:hover { box-shadow: 0 6px 20px rgba(23, 85, 100, 0.4); transform: translateY(-1px); }

        .btn-success { background: linear-gradient(135deg, #10B981, #059669); color: #fff; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        .btn-success:hover { box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4); transform: translateY(-1px); }

        .alert {
            padding: <?= $isIframe ? '10px 15px' : '16px 20px' ?>;
            border-radius: 12px;
            margin-bottom: <?= $isIframe ? '15px' : '25px' ?>;
            font-size: <?= $isIframe ? '13px' : '16px' ?>;
            display: flex; align-items: center; gap: 15px;
            animation: slideIn 0.3s ease-out;
        }
        .alert-info { background: #E0F2FE; color: #0369A1; border-left: 4px solid #0EA5E9; }
        .alert-warning { background: #FEF3C7; color: #92400E; border-left: 4px solid #F59E0B; }
        .alert-success { background: #DCFCE7; color: #166534; border-left: 4px solid #22C55E; }
        .alert-error { background: #FEE2E2; color: #991B1B; border-left: 4px solid #EF4444; }

        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .partner-view { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        @media (max-width: 768px) { .partner-view { grid-template-columns: 1fr; } }
        
        .image-preview-card {
            border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #E2E8F0;
        }
        .image-preview-card img { width: 100%; height: 250px; object-fit: cover; display: block; }
        .image-preview-card .label { padding: 12px; background: #F8FAFC; text-align: center; font-weight: 600; color: #475569; border-top: 1px solid #E2E8F0; }

        .info-box { background: #F8FAFC; padding: 20px; border-radius: 12px; border: 1px solid #E2E8F0; margin-top: 20px; }
        .info-box h4 { margin: 0 0 10px 0; color: #1E293B; font-size: 16px; }
        .info-box p { margin: 0; color: #64748B; line-height: 1.6; }
    </style>
</head>
<body>
    
    <div class="navbar">
        <h2 style="margin:0; color:#FF7B00; font-weight:800; letter-spacing:-0.5px;">Wonwan</h2>
    </div>

    <div class="container">
        <div class="header-title">
            <h1>ยืนยันสภาพสินค้า (Escrow)</h1>
            <p>ระบบคนกลางเพื่อความปลอดภัยในการแลกเปลี่ยนของคุณ</p>
            <div class="status-badge">รายการเทรด <?=formatExchangeOrderId($requestId)?> • <?= htmlspecialchars($request['trade_option'] ?: 'ยังไม่ระบุ') ?></div>
        </div>

        <?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if (!empty($errors)): ?><div class="alert alert-error">⚠ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>

        <?php if ($isAdmin && $isReadonly): ?>
            <!-- Admin View: Show Both Forms -->
            <h4 style="text-align:center; color:#8b5266; margin-bottom:20px;">ข้อมูลสภาพหนังสือ (สำหรับส่วนกลางตรวจสอบ)</h4>
            
            <?php 
                // Fetch both forms directly for admin
                $formsStmt = $pdo->prepare("SELECT * FROM trade_condition_forms WHERE request_id = ? ORDER BY form_id ASC");
                $formsStmt->execute([$requestId]);
                $allForms = $formsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($allForms)) {
                    echo "<div class='alert alert-warning' style='text-align:center;'>ยังไม่มีฝ่ายใดกรอกฟอร์มสภาพหนังสือ</div>";
                }
                
                foreach ($allForms as $f):
                    $ownerUser = $pdo->query("SELECT display_name, email FROM users WHERE user_id = {$f['owner_id']}")->fetch();
                    $ownerName = !empty($ownerUser['display_name']) ? $ownerUser['display_name'] : ($ownerUser['email'] ?? 'ไม่ระบุชื่อ');
            ?>
                <div class="form-container" style="margin-bottom:30px; border:2px solid #e2e8f0; border-radius: 12px; overflow:hidden; background:#fff;">
                    <div style="background:#f8fafc; padding:15px; border-bottom:1px solid #e2e8f0; font-weight:bold; color:#0f172a; font-size:16px;">
                        ฟอร์มสภาพหนังสือของ: <span style="color:#FF7B00;"><?= htmlspecialchars($ownerName) ?></span>
                    </div>
                    <div style="padding:20px;">
                        <div class="upload-grid" style="margin-bottom:20px;">
                            <?php foreach (['front_cover_url', 'back_cover_url', 'spine_url'] as $idx => $imgField): ?>
                                <?php if (!empty($f[$imgField])): ?>
                                    <div class="image-preview-card">
                                        <img src="<?= htmlspecialchars($f[$imgField]) ?>" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                                        <div class="label"><?= ['ปกหน้า', 'ปกหลัง', 'สันหนังสือ'][$idx] ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php 
                            $partnerExtras = json_decode($f['defect_urls'] ?? '[]', true) ?: [];
                            foreach($partnerExtras as $idx => $url):
                            ?>
                            <div class="image-preview-card">
                                <img src="<?= htmlspecialchars($url) ?>" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                                <div class="label">รูปเพิ่มเติม <?= $idx + 1 ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="info-box" style="margin-top:20px;">
                            <h4>📍 ตำหนิที่พบ</h4>
                            <p><?= nl2br(htmlspecialchars($f['defects_description'] ?: 'ไม่มีระบุ')) ?></p>
                        </div>
                        
                        <div class="info-box">
                            <h4>📌 หมายเหตุเพิ่มเติม</h4>
                            <p><?= nl2br(htmlspecialchars($f['notes'] ?: '-')) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            </div>
            
            <!-- Lightbox Code -->
            <div id="imageLightbox" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(5px); flex-direction:column;">
                <span style="position:absolute; top:20px; right:30px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer; z-index:100000;" onclick="closeLightbox()">&times;</span>
                <div style="display:flex; align-items:center; justify-content:center; width:100%; height:100%; position:relative;">
                    <img id="lightboxImg" src="" style="max-width:85%; max-height:85%; object-fit:contain; border-radius:8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                </div>
            </div>
            
            <script>
            function openLightbox(src) {
                document.getElementById('lightboxImg').src = src;
                document.getElementById('imageLightbox').style.display = 'flex';
            }
            function closeLightbox() {
                document.getElementById('imageLightbox').style.display = 'none';
                document.getElementById('lightboxImg').src = '';
            }
            </script>
        </body>
        </html>
        <?php exit; ?>
        <?php endif; ?>


        <?php if ($isAdmin && $isReadonly): ?>
            <!-- Admin View: Show Both Forms -->
            <h4 style="text-align:center; color:#8b5266; margin-bottom:20px;">ข้อมูลสภาพหนังสือ (สำหรับส่วนกลางตรวจสอบ)</h4>
            
            <?php 
                // Fetch both forms directly for admin
                $formsStmt = $pdo->prepare("SELECT * FROM trade_condition_forms WHERE request_id = ? ORDER BY form_id ASC");
                $formsStmt->execute([$requestId]);
                $allForms = $formsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($allForms)) {
                    echo "<div class='alert alert-warning' style='text-align:center;'>ยังไม่มีฝ่ายใดกรอกฟอร์มสภาพหนังสือ</div>";
                }
                
                foreach ($allForms as $f):
                    $ownerUser = $pdo->query("SELECT first_name, last_name, display_name FROM users WHERE user_id = {$f['owner_id']}")->fetch();
                    $ownerName = $ownerUser['display_name'] ?: trim($ownerUser['first_name'] . ' ' . $ownerUser['last_name']);
            ?>
                <div class="form-container" style="margin-bottom:30px; border:2px solid #e2e8f0; border-radius: 12px; overflow:hidden; background:#fff;">
                    <div style="background:#f8fafc; padding:15px; border-bottom:1px solid #e2e8f0; font-weight:bold; color:#0f172a; font-size:16px;">
                        ฟอร์มสภาพหนังสือของ: <span style="color:#FF7B00;"><?= htmlspecialchars($ownerName) ?></span>
                    </div>
                    <div style="padding:20px;">
                        <div class="upload-grid" style="margin-bottom:20px;">
                            <?php foreach (['front_cover_url', 'back_cover_url', 'spine_url'] as $idx => $imgField): ?>
                                <?php if (!empty($f[$imgField])): ?>
                                    <div class="image-preview-card">
                                        <img src="<?= htmlspecialchars($f[$imgField]) ?>" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                                        <div class="label"><?= ['ปกหน้า', 'ปกหลัง', 'สันหนังสือ'][$idx] ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php 
                            $partnerExtras = json_decode($f['defect_urls'] ?? '[]', true) ?: [];
                            foreach($partnerExtras as $idx => $url):
                            ?>
                            <div class="image-preview-card">
                                <img src="<?= htmlspecialchars($url) ?>" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                                <div class="label">รูปเพิ่มเติม <?= $idx + 1 ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="info-box" style="margin-top:20px;">
                            <h4>📍 ตำหนิที่พบ</h4>
                            <p><?= nl2br(htmlspecialchars($f['defects_description'] ?: 'ไม่มีระบุ')) ?></p>
                        </div>
                        
                        <div class="info-box">
                            <h4>📌 หมายเหตุเพิ่มเติม</h4>
                            <p><?= nl2br(htmlspecialchars($f['notes'] ?: '-')) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            </div>
            
            <!-- Lightbox Code -->
            <div id="imageLightbox" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(5px); flex-direction:column;">
                <span style="position:absolute; top:20px; right:30px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer; z-index:100000;" onclick="closeLightbox()">&times;</span>
                <div style="display:flex; align-items:center; justify-content:center; width:100%; height:100%; position:relative;">
                    <img id="lightboxImg" src="" style="max-width:85%; max-height:85%; object-fit:contain; border-radius:8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                </div>
            </div>
            
            <script>
            function openLightbox(src) {
                document.getElementById('lightboxImg').src = src;
                document.getElementById('imageLightbox').style.display = 'flex';
            }
            function closeLightbox() {
                document.getElementById('imageLightbox').style.display = 'none';
                document.getElementById('lightboxImg').src = '';
            }
            </script>
        </body>
        </html>
        <?php exit; ?>
        <?php endif; ?>


        <?php if (!$myForm): ?>
            <!-- 1. กรอกฟอร์มของตัวเอง -->
            <div class="alert alert-info">
                <strong>ขั้นตอนที่ 1:</strong> ถ่ายรูปหนังสือ "<?= htmlspecialchars($myBookTitle) ?>" ของคุณเพื่อเป็นหลักฐาน
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="request_id" value="<?= $requestId ?>">
                <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
                <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                
                <div class="card-glass">
                    <h2 class="section-title" style="<?= $isIframe ? 'margin-bottom:10px; font-size:16px;' : '' ?>"><span>1</span> อัปโหลดรูปภาพหลักฐาน</h2>
                    <div class="upload-grid">
                        <label class="upload-box">
                            <input type="file" name="front_cover" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:5px; color:var(--primary);"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg></div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">ปกหน้า</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="back_cover" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:5px; color:var(--primary);"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg></div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">ปกหลัง</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="spine" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:5px; color:var(--primary);"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg></div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">สันหนังสือ</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="additional_images[]" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:5px; color:var(--primary);"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg></div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">รูปเพิ่มเติม 1</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="additional_images[]" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:5px; color:var(--primary);"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg></div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">รูปเพิ่มเติม 2</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="additional_images[]" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:5px; color:var(--primary);"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg></div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">รูปเพิ่มเติม 3</span>
                            <img src="">
                        </label>
                    </div>
                </div>

                <div class="card-glass">
                    <h2 class="section-title" style="<?= $isIframe ? 'margin-bottom:10px; font-size:16px;' : '' ?>"><span>2</span> รายละเอียดเพิ่มเติม</h2>
                    <div style="display:flex; gap:<?= $isIframe ? '10px' : '20px' ?>;">
                        <div style="flex:1;">
                            <label class="form-label" style="<?= $isIframe ? 'font-size:12px;margin-bottom:4px;' : '' ?>">ตำหนิของหนังสือ (ถ้ามี)</label>
                            <textarea name="defects_description" class="form-control" rows="<?= $isIframe ? '2' : '3' ?>" placeholder="รอยยับหน้า 45..."></textarea>
                        </div>
                        <div style="flex:1;">
                            <label class="form-label" style="<?= $isIframe ? 'font-size:12px;margin-bottom:4px;' : '' ?>">หมายเหตุการจัดส่ง</label>
                            <textarea name="notes" class="form-control" rows="<?= $isIframe ? '2' : '3' ?>" placeholder="ห่อบับเบิ้ล..."></textarea>
                        </div>
                    </div>
                    <?php if ($request['trade_option'] === 'direct_escrow'): ?>
                    <label class="form-label" style="color:#d97706; margin-top: <?= $isIframe ? '5px' : '10px' ?>; <?= $isIframe ? 'font-size:12px; margin-bottom:4px;' : '' ?>">ยอดเงินมัดจำที่คุณต้องการให้อีกฝ่ายโอน (บาท)</label>
                    <input type="number" name="required_deposit_amount" class="form-control" placeholder="ระบุจำนวนเงิน เช่น 300" min="0" step="1" required style="border-color:#fcd34d; background:#fffbeb; <?= $isIframe ? 'padding:8px;' : '' ?>">
                    <p style="font-size:11px; color:#92400e; margin-top:2px; margin-bottom:<?= $isIframe ? '0' : '20px' ?>;">* ประเมินจากมูลค่าหนังสือของคุณ เพื่อใช้เป็นหลักประกันความปลอดภัย</p>
                    <?php endif; ?>
                </div>

                <?php if (!$isReadonly): ?>
                <button type="submit" name="submit_form" class="btn btn-primary">บันทึกและส่งให้อีกฝ่ายตรวจสอบ</button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="alert alert-success" style="justify-content:center;">
                ✅ <strong>บันทึกสำเร็จ!</strong> คุณได้ส่งหลักฐานของคุณเรียบร้อยแล้ว
            </div>
        <?php endif; ?>

        <?php if ($myForm && $partnerForm): ?>
            <!-- 2. ตรวจสอบฟอร์มของอีกฝ่าย -->
            <div class="card-glass" style="margin-top: 40px; border: 2px solid rgba(255, 123, 0, 0.2);">
                <h2 class="section-title" style="color: #175564; justify-content:center; margin-bottom:30px;">
                    ตรวจสอบหนังสือคู่แลกเปลี่ยน
                </h2>
                
                <h3 style="text-align:center; margin-bottom:25px; color:#334155;">
                    "<?= htmlspecialchars($partnerBookTitle) ?>"
                </h3>

                <div class="upload-grid">
                    <div class="image-preview-card">
                        <img src="<?= htmlspecialchars($partnerForm['front_cover_url']) ?>" class="gallery-image" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                        <div class="label">ปกหน้า</div>
                    </div>
                    <div class="image-preview-card">
                        <img src="<?= htmlspecialchars($partnerForm['back_cover_url']) ?>" class="gallery-image" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                        <div class="label">ปกหลัง</div>
                    </div>
                    <div class="image-preview-card">
                        <img src="<?= htmlspecialchars($partnerForm['spine_url']) ?>" class="gallery-image" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                        <div class="label">สันหนังสือ</div>
                    </div>
                    <?php 
                    $partnerExtras = json_decode($partnerForm['defect_urls'] ?? '[]', true) ?: [];
                    foreach($partnerExtras as $idx => $url):
                    ?>
                    <div class="image-preview-card">
                        <img src="<?= htmlspecialchars($url) ?>" class="gallery-image" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                        <div class="label">รูปเพิ่มเติม <?= $idx + 1 ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="info-box">
                    <h4>📝 ตำหนิที่ผู้ส่งแจ้ง</h4>
                    <p><?= nl2br(htmlspecialchars($partnerForm['defects_description'] ?: 'ผู้ส่งระบุว่าไม่มีตำหนิ')) ?></p>
                </div>
                
                <div class="info-box">
                    <h4>📌 หมายเหตุเพิ่มเติม</h4>
                    <p><?= nl2br(htmlspecialchars($partnerForm['notes'] ?: '-')) ?></p>
                </div>
                
                <?php if ($request['trade_option'] === 'direct_escrow' && !empty($partnerForm['required_deposit_amount'])): ?>
                <div class="info-box" style="background:#fffbeb; border-color:#fde68a;">
                    <h4 style="color:#d97706;">💰 ยอดเงินมัดจำที่คุณต้องชำระ</h4>
                    <p style="font-size:18px; font-weight:bold; color:#b45309; margin-top:5px;"><?= number_format($partnerForm['required_deposit_amount'], 2) ?> บาท</p>
                    <p style="font-size:13px; color:#92400e; margin-top:5px;">(ชำระเงินในขั้นตอนถัดไปในแชท หลังจากกดยอมรับเงื่อนไขนี้)</p>
                </div>
                <?php endif; ?>

                <?php if (!$partnerForm['is_accepted_by_partner']): ?>
                    <div class="alert alert-warning" style="margin-top:30px;">
                        <strong>คำเตือน:</strong> ตรวจสอบรูปภาพอย่างละเอียด หากของจริงที่ได้รับไม่ตรงตามรูปภาพนี้ คุณสามารถเปิดข้อพิพาท (Dispute) เพื่อขอรับเงินมัดจำคืนได้
                    </div>
                    <form method="post">
                        <input type="hidden" name="request_id" value="<?= $requestId ?>">
                        <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
                        <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                        
                        <?php if (!$isReadonly): ?>
                        <button type="submit" name="accept_partner" class="btn btn-success">
                            ✓ ยอมรับเงื่อนไขและยืนยันการแลกเปลี่ยน
                        </button>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success" style="justify-content:center; margin-top:30px;">
                        ✅ คุณกดยอมรับสภาพหนังสือเล่มนี้แล้ว! ระบบจะทำการล็อคเงินมัดจำเมื่อครบ 2 ฝ่าย
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($myForm && !$partnerForm): ?>
            <div class="alert alert-warning" style="justify-content:center; margin-top:30px;">
                ⏳ รอคู่แลกเปลี่ยนกรอกฟอร์มสภาพสินค้า (Escrow) ของตนเอง...
            </div>
        <?php endif; ?>

    </div>
    
    <script>
    // Preview images smoothly
    document.querySelectorAll('.upload-box input').forEach(input => {
        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                const img = this.parentElement.querySelector('img');
                const icon = this.parentElement.querySelector('.upload-icon');
                const text = this.parentElement.querySelector('span');
                
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                    if(icon) icon.style.display = 'none';
                    if(text) {
                        text.style.position = 'absolute';
                        text.style.bottom = '10px';
                        text.style.background = 'rgba(0,0,0,0.6)';
                        text.style.color = 'white';
                        text.style.padding = '4px 12px';
                        text.style.borderRadius = '20px';
                        text.style.fontSize = '12px';
                        text.style.zIndex = '20';
                    }
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    </script>



<div id="imageLightbox" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(5px); flex-direction:column;">
    <span style="position:absolute; top:20px; right:30px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer; z-index:100000;" onclick="closeLightbox()">&times;</span>
    
    <div style="display:flex; align-items:center; justify-content:center; width:100%; height:100%; position:relative;">
        <!-- Left Arrow -->
        <div onclick="prevImage(event)" style="position:absolute; left:20px; width:50px; height:50px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; justify-content:center; align-items:center; color:#fff; font-size:24px; font-weight:bold; cursor:pointer; user-select:none; z-index:100000; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
            &#10094;
        </div>
        
        <img id="lightboxImg" src="" style="max-width:85%; max-height:85%; object-fit:contain; border-radius:8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        
        <!-- Right Arrow -->
        <div onclick="nextImage(event)" style="position:absolute; right:20px; width:50px; height:50px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; justify-content:center; align-items:center; color:#fff; font-size:24px; font-weight:bold; cursor:pointer; user-select:none; z-index:100000; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
            &#10095;
        </div>
    </div>
    
    <div id="lightboxCounter" style="position:absolute; bottom:20px; color:#fff; font-size:16px; font-weight:500;"></div>
</div>

<script>
let galleryImages = [];
let currentImageIndex = 0;

function initGallery() {
    const imgs = document.querySelectorAll('.gallery-image');
    galleryImages = Array.from(imgs).map(img => img.src);
}

function openLightbox(src) {
    if (galleryImages.length === 0) initGallery();
    currentImageIndex = galleryImages.indexOf(src);
    if(currentImageIndex === -1) currentImageIndex = 0;
    
    updateLightboxImage();
    document.getElementById('imageLightbox').style.display = 'flex';
}

function updateLightboxImage() {
    if(galleryImages.length === 0) return;
    document.getElementById('lightboxImg').src = galleryImages[currentImageIndex];
    document.getElementById('lightboxCounter').innerText = (currentImageIndex + 1) + " / " + galleryImages.length;
}

function prevImage(e) {
    e.stopPropagation();
    if(galleryImages.length === 0) return;
    currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
    updateLightboxImage();
}

function nextImage(e) {
    e.stopPropagation();
    if(galleryImages.length === 0) return;
    currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
    updateLightboxImage();
}

function closeLightbox() {
    document.getElementById('imageLightbox').style.display = 'none';
    document.getElementById('lightboxImg').src = '';
}

// Close on background click
document.getElementById('imageLightbox').addEventListener('click', function(e) {
    if (e.target === this || e.target.querySelector('#lightboxImg')) {
        closeLightbox();
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (document.getElementById('imageLightbox').style.display === 'flex') {
        if (e.key === 'ArrowLeft') prevImage(e);
        if (e.key === 'ArrowRight') nextImage(e);
        if (e.key === 'Escape') closeLightbox();
    }
});
</script>
</body>
</html>
