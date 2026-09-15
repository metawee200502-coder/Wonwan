<?php
session_start();
if(empty($_SESSION['user_id'])) { header('Location: /pages/login.php'); exit; }
require_once __DIR__ . '/../config/config.php';
$userId = (int)$_SESSION['user_id'];
$requestId = (int)($_GET['request_id'] ?? 0);
$isIframe = !empty($_GET['iframe']);

if(!$requestId) exit('ไม่พบคำขอ');

$q = $pdo->prepare("SELECT e.*, er.requester_id, er.status, er.trade_option, offer.title offer_title, offer.owner_id offer_owner_id FROM exchange_requests er JOIN exchanges e ON e.exchange_id = er.exchange_id LEFT JOIN exchanges offer ON offer.exchange_id = er.offered_exchange_id WHERE er.request_id = ?");
$q->execute([$requestId]);
$request = $q->fetch(PDO::FETCH_ASSOC);

if(!$request) exit('ไม่พบคำขอ');
if($userId !== (int)$request['owner_id'] && $userId !== (int)$request['requester_id']) exit('ไม่มีสิทธิ์เข้าถึง');

$isOwner = ((int)$request['owner_id'] === $userId);
$partnerId = $isOwner ? (int)$request['requester_id'] : (int)$request['owner_id'];

// Get my condition form (which holds my deposit status)
$condQ = $pdo->prepare("SELECT * FROM trade_condition_forms WHERE request_id = ? AND owner_id = ?");
$condQ->execute([$requestId, $userId]);
$myCondition = $condQ->fetch(PDO::FETCH_ASSOC);

if(!$myCondition) exit('ไม่พบฟอร์มสภาพสินค้า');

// Get partner's condition form (which dictates how much I must pay)
$partnerCondQ = $pdo->prepare("SELECT required_deposit_amount FROM trade_condition_forms WHERE request_id = ? AND owner_id = ?");
$partnerCondQ->execute([$requestId, $partnerId]);
$partnerCondition = $partnerCondQ->fetch(PDO::FETCH_ASSOC);
$requiredDeposit = (float)($partnerCondition['required_deposit_amount'] ?? 0);

if($requiredDeposit <= 0) {
    exit('ไม่มีการเรียกเก็บเงินมัดจำจากอีกฝ่าย');
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slip_image'])) {
    if(!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $file = $_FILES['slip_image'];
        if($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if(in_array($ext, ['jpg','jpeg','png','webp'])) {
                $dir = __DIR__ . '/uploads/payments/';
                if(!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = 'slip_' . uniqid() . '.' . $ext;
                if(move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                    $slipUrl = '/pages/uploads/payments/' . $filename;
                    $pdo->prepare("UPDATE trade_condition_forms SET deposit_slip_url = ?, deposit_status = 'pending_verification' WHERE form_id = ?")
                        ->execute([$slipUrl, $myCondition['form_id']]);
                    $success = 'อัปโหลดสลิปสำเร็จ รอผู้ดูแลระบบตรวจสอบ';
                    $myCondition['deposit_status'] = 'pending_verification';
                    $myCondition['deposit_slip_url'] = $slipUrl;
                } else {
                    $error = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
                }
            } else {
                $error = 'รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, WEBP)';
            }
        } else {
            $error = 'กรุณาเลือกไฟล์สลิป';
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
    <title>อัปโหลดสลิปโอนเงินมัดจำ</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body {
            <?php if ($isIframe): ?>
            scrollbar-width: none;
            -ms-overflow-style: none;
            <?php endif; ?>
        }
        <?php if ($isIframe): ?>
        html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar {
            display: none;
        }
        * { scrollbar-width: none; }
        <?php endif; ?>

        body { 
            font-family: 'Prompt', sans-serif; 
            background: <?= $isIframe ? 'transparent' : '#fdf5f7' ?>; 
            margin: 0; 
            padding: <?= $isIframe ? '15px' : '20px' ?>; 
            display: flex; justify-content: center; align-items: <?= $isIframe ? 'flex-start' : 'center' ?>; 
            min-height: 100vh; 
        }
        .card { 
            background: <?= $isIframe ? 'transparent' : '#fff' ?>; 
            padding: <?= $isIframe ? '0' : '30px' ?>; 
            border-radius: 12px; 
            box-shadow: <?= $isIframe ? 'none' : '0 4px 15px rgba(0,0,0,0.05)' ?>; 
            width: 100%; max-width: 400px; text-align: center; 
        }
        .card h2 { color: #175564; margin-top: 0; display: <?= $isIframe ? 'none' : 'block' ?>; }
        .amount { font-size: <?= $isIframe ? '28px' : '32px' ?>; font-weight: bold; color: #175564; margin: 10px 0; }
        .qr-placeholder { background: #f0fdfa; border: 2px dashed #175564; padding: <?= $isIframe ? '20px' : '40px' ?>; border-radius: 8px; margin-bottom: 20px; color: #175564; }
        .btn { display: inline-block; width: 100%; padding: <?= $isIframe ? '10px' : '12px' ?>; background: linear-gradient(135deg, #175564, #2F6E63); color: #fff; border: none; border-radius: 8px; font-size: <?= $isIframe ? '14px' : '16px' ?>; font-weight: 600; cursor: pointer; text-decoration: none; transition: 0.2s; box-sizing: border-box; font-family: inherit; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(23,85,100,0.3); }
        .btn-outline { background: #fff; color: #175564; border: 1px solid #175564; margin-top: 10px; }
        .btn-outline:hover { background: #f0fdfa; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
        .alert-success { background: #f0fff4; color: #2f855a; border: 1px solid #c6f6d5; }
        .upload-area { margin-bottom: 20px; text-align: left; }
        .upload-area input { display: block; width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 5px; }
        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 14px; font-weight: 500; margin-bottom: 20px; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #c6f6d5; color: #22543d; }
    </style>
</head>
<body>
    <div class="card">
        <h2>ชำระเงินมัดจำ</h2>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php if($isIframe): ?><script>setTimeout(() => window.parent.postMessage({action: 'close_condition_modal'}, '*'), 1500);</script><?php endif; ?>
        <?php endif; ?>
        
        <?php if($myCondition['deposit_status'] === 'approved'): ?>
            <div class="status-badge status-approved"><i class="fas fa-check-circle"></i> ตรวจสอบสลิปผ่านแล้ว</div>
            <p>เงินมัดจำของคุณได้รับการยืนยันเรียบร้อยแล้ว</p>
            <?php if(!$isIframe): ?><a href="/pages/chat.php?conversation=<?= urlencode($_GET['conversation_id'] ?? '') ?>" class="btn btn-outline">กลับไปที่แชท</a><?php endif; ?>
        <?php elseif($myCondition['deposit_status'] === 'pending_verification'): ?>
            <div class="status-badge status-pending"><i class="fas fa-hourglass-half"></i> รอแอดมินตรวจสอบ</div>
            <p>คุณได้แนบสลิปแล้ว กรุณารอแอดมินตรวจสอบความถูกต้อง</p>
            <?php if($myCondition['deposit_slip_url']): ?>
                <img src="<?= htmlspecialchars($myCondition['deposit_slip_url']) ?>" alt="Slip" style="max-width: 100%; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0;">
            <?php endif; ?>
            <?php if(!$isIframe): ?><a href="/pages/chat.php?conversation=<?= urlencode($_GET['conversation_id'] ?? '') ?>" class="btn btn-outline">กลับไปที่แชท</a><?php endif; ?>
        <?php else: ?>
            <p style="color: #718096; margin-bottom: 5px;">ยอดเงินที่ต้องชำระ (บาท)</p>
            <div class="amount">฿<?= number_format($requiredDeposit, 2) ?></div>
            
            <div class="qr-placeholder">
                <i class="fas fa-qrcode" style="font-size: 48px; margin-bottom: 10px;"></i><br>
                ระบบ PromtPay (สมมติ)<br>
                080-000-0000<br>
                บจก. แลกเปลี่ยนหนังสือ
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="upload-area">
                    <label style="font-weight: 500; color: #4a5568;"><i class="fas fa-upload"></i> อัปโหลดหลักฐานการโอนเงิน (สลิป)</label>
                    <input type="file" name="slip_image" accept="image/jpeg,image/png,image/webp" required>
                </div>
                <button type="submit" class="btn">ยืนยันการชำระเงิน</button>
                <?php if(!$isIframe): ?><a href="/pages/chat.php?conversation=<?= urlencode($_GET['conversation_id'] ?? '') ?>" class="btn btn-outline" style="display:inline-block; text-align:center;">ยกเลิก</a><?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
