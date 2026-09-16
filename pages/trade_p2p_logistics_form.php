<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}
require_once __DIR__ . '/../config/config.php';

$userId = (int)$_SESSION['user_id'];
$requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
$conversationId = (int)($_GET['conversation_id'] ?? 0);

if (!$requestId) {
    exit('Invalid request');
}

$q = $pdo->prepare('SELECT er.*, e.owner_id, e.title as target_title FROM exchange_requests er JOIN exchanges e ON er.exchange_id = e.exchange_id WHERE er.request_id = ?');
$q->execute([$requestId]);
$req = $q->fetch(PDO::FETCH_ASSOC);

if (!$req || !in_array($userId, [(int)$req['requester_id'], (int)$req['owner_id']], true)) {
    exit('Unauthorized');
}

// Get the user's condition form
$condQ = $pdo->prepare("SELECT * FROM trade_condition_forms WHERE request_id = ? AND owner_id = ?");
$condQ->execute([$requestId, $userId]);
$myCondition = $condQ->fetch(PDO::FETCH_ASSOC);

if (!$myCondition) exit('ไม่พบข้อมูลสภาพสินค้า');

// Process submission
$isRequester = ($userId === (int)$req['requester_id']);
$partnerAddress = $isRequester ? $req['offer_address'] : $req['requester_address'];
$partnerAddressHTML = $partnerAddress ? nl2br(htmlspecialchars($partnerAddress)) : '<span style="color:#a0aec0;font-style:italic;">ยังไม่ได้ระบุที่อยู่จัดส่ง</span>';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tracking_number'], $_POST['logistics_company'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $tracking = trim($_POST['tracking_number']);
        $company = trim($_POST['logistics_company']);
        if ($tracking && $company) {
            $stmt = $pdo->prepare("UPDATE trade_condition_forms SET tracking_number = ?, logistics_company = ?, shipped_at = NOW() WHERE form_id = ?");
            $stmt->execute([$tracking, $company, $myCondition['form_id']]);
            
            // Insert system message
            if ($conversationId > 0) {
                $msgQ = $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text) VALUES (?, ?, ?)");
                $msgQ->execute([$conversationId, $userId, "[P2P_TRACKING_SUBMITTED:{$company}:{$tracking}]"]);
            }
            
            if (!empty($_REQUEST['iframe'])) {
                echo "<script>window.parent.location.reload();</script>";
                exit;
            }
            // Redirect back to chat
            header("Location: /pages/chat.php?conversation=" . $conversationId);
            exit;
        } else {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        }
    }
}
if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งเลขพัสดุจัดส่ง (P2P)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #fdf5f7; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        <?php if (!empty($_REQUEST['iframe'])): ?>
        body { min-height: auto; background: transparent; padding: 20px; }
        .card { box-shadow: none; max-width: 100%; padding: 0; background: transparent; }
        <?php endif; ?>
        .card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(209,138,167,0.15); width: 100%; max-width: 400px; text-align: center; }
        .card h2 { color: #8b5266; margin-top: 0; }
        .form-group { text-align: left; margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #4a5568; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; box-sizing: border-box; }
        .btn { display: inline-block; width: 100%; padding: 12px; background: linear-gradient(135deg, #d18aa7, #b66f68); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; text-decoration: none; transition: 0.2s; box-sizing: border-box; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(209,138,167,0.3); }
        .btn-outline { background: #fff; color: #8b5266; border: 1px solid #d18aa7; margin-top: 10px; }
        .btn-outline:hover { background: #fdf5f7; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: left; }
        .alert-error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
    </style>
</head>
<body>
    <div class="card">
        <?php if (empty($_REQUEST['iframe'])): ?>
        <h2>📦 แจ้งเลขพัสดุ</h2>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <p style="color: #718096; margin-bottom: 20px; font-size: 14px;">กรุณากรอกข้อมูลการจัดส่งให้ครบถ้วน เพื่อให้ระบบและอีกฝ่ายสามารถติดตามสถานะพัสดุได้</p>
        
        <div style="margin:0 0 20px 0;padding:15px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;text-align:left;">
            <strong style="color:#4a5568;font-size:13px;display:block;margin-bottom:8px;"><i class="fas fa-map-marker-alt"></i> ที่อยู่จัดส่งของอีกฝ่าย:</strong>
            <div style="color:#2d3748;font-size:14px;line-height:1.5;"><?= $partnerAddressHTML ?></div>
        </div>
        
        <form method="post">
            <?php if (!empty($_REQUEST['iframe'])): ?>
            <input type="hidden" name="iframe" value="1">
            <?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="form-group">
                <label>บริษัทขนส่ง</label>
                <select name="logistics_company" class="form-control" required>
                    <option value="">-- เลือกบริษัทขนส่ง --</option>
                    <option value="ไปรษณีย์ไทย (EMS)">ไปรษณีย์ไทย (EMS)</option>
                    <option value="Kerry Express">Kerry Express</option>
                    <option value="Flash Express">Flash Express</option>
                    <option value="J&T Express">J&T Express</option>
                    <option value="Shopee Express">Shopee Express</option>
                    <option value="อื่นๆ">อื่นๆ</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>เลขพัสดุ</label>
                <input type="text" name="tracking_number" class="form-control" required placeholder="เช่น TH123456789">
            </div>
            
            <button type="submit" class="btn">ยืนยันการจัดส่ง</button>
            <?php if (empty($_REQUEST['iframe'])): ?>
            <a href="/pages/chat.php?conversation=<?= urlencode($conversationId) ?>" class="btn btn-outline" style="display:inline-block; text-align:center;">ยกเลิก</a>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
