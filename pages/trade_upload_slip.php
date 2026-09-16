<?php
session_start();
if(empty($_SESSION['user_id'])) { header('Location: /pages/login.php'); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/promptpay.php';
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

$promptpayPayload = generatePromptPayPayload(WANAWAN_PROMPTPAY_ID, $requiredDeposit);
$qrImage = 'https://quickchart.io/qr?size=300&margin=2&ecLevel=M&text=' . rawurlencode($promptpayPayload);

$walletQ = $pdo->prepare("SELECT * FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ?");
$walletQ->execute([$userId]);
$wallet = $walletQ->fetch(PDO::FETCH_ASSOC);
$availableBalance = (float)($wallet['available_balance'] ?? 0);

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $address = trim($_POST['shipping_address'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'slip';
        
        if($address === '') {
            $error = 'กรุณากรอกที่อยู่จัดส่ง';
        } else if ($paymentMethod === 'wallet') {
            if ($availableBalance < $requiredDeposit) {
                $error = 'ยอดเงินใน Wonwan Wallet ไม่เพียงพอ กรุณาเติมเงินหรือเลือกชำระผ่านช่องทางอื่น';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance - ?, escrow_balance = escrow_balance + ? WHERE seller_type = 'user' AND seller_id = ?")
                        ->execute([$requiredDeposit, $requiredDeposit, $userId]);
                    
                    $pdo->prepare("UPDATE trade_condition_forms SET deposit_status = 'approved', deposit_approved_at = NOW() WHERE form_id = ?")
                        ->execute([$myCondition['form_id']]);
                        
                    if ($isOwner) {
                        $pdo->prepare("UPDATE exchange_requests SET offer_address = ? WHERE request_id = ?")->execute([$address, $requestId]);
                    } else {
                        $pdo->prepare("UPDATE exchange_requests SET requester_address = ? WHERE request_id = ?")->execute([$address, $requestId]);
                    }
                    
                    $pdo->commit();
                    $success = 'ชำระเงินผ่าน Wonwan Wallet สำเร็จ';
                    $myCondition['deposit_status'] = 'approved';
                    $availableBalance -= $requiredDeposit; // update for UI
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'เกิดข้อผิดพลาดในการชำระเงิน: ' . $e->getMessage();
                }
            }
        } else if (isset($_FILES['slip_image'])) {
            $file = $_FILES['slip_image'];
            if($file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if(in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dir = __DIR__ . '/uploads/payments/';
                    if(!is_dir($dir)) mkdir($dir, 0777, true);
                    $filename = 'slip_' . uniqid() . '.' . $ext;
                    if(move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                        $slipUrl = '/pages/uploads/payments/' . $filename;
                        $pdo->prepare("UPDATE trade_condition_forms SET deposit_slip_url = ?, deposit_status = 'reviewing' WHERE form_id = ?")
                            ->execute([$slipUrl, $myCondition['form_id']]);
                        
                        if ($isOwner) {
                            $pdo->prepare("UPDATE exchange_requests SET offer_address = ? WHERE request_id = ?")->execute([$address, $requestId]);
                        } else {
                            $pdo->prepare("UPDATE exchange_requests SET requester_address = ? WHERE request_id = ?")->execute([$address, $requestId]);
                        }
    
                        $success = 'อัปโหลดสลิปและบันทึกที่อยู่สำเร็จ รอผู้ดูแลระบบตรวจสอบ';
                        $myCondition['deposit_status'] = 'reviewing';
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
            width: 100%; max-width: 800px; text-align: center; 
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            text-align: left;
            align-items: start;
        }
        @media (max-width: 450px) {
            .form-grid { grid-template-columns: 1fr; }
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
                <?php if($isIframe): ?>
        <script>
        setTimeout(() => {
            window.parent.postMessage({action: 'deposit_paid', amount: <?= $requiredDeposit ?>}, '*');
            window.parent.postMessage({action: 'close_condition_modal_no_reload'}, '*');
        }, 1500);
        </script>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php if($myCondition['deposit_status'] === 'approved'): ?>
            <div class="status-badge status-approved"><i class="fas fa-check-circle"></i> ตรวจสอบสลิปผ่านแล้ว</div>
            <p>เงินมัดจำของคุณได้รับการยืนยันเรียบร้อยแล้ว</p>
            <?php if(!$isIframe): ?><a href="/pages/chat.php?conversation=<?= urlencode($_GET['conversation_id'] ?? '') ?>" class="btn btn-outline">กลับไปที่แชท</a><?php endif; ?>
        <?php elseif($myCondition['deposit_status'] === 'reviewing'): ?>
            <div class="status-badge status-pending"><i class="fas fa-hourglass-half"></i> รอแอดมินตรวจสอบ</div>
            <p>คุณได้แนบสลิปแล้ว กรุณารอแอดมินตรวจสอบความถูกต้อง</p>
            <?php if($myCondition['deposit_slip_url']): ?>
                <img src="<?= htmlspecialchars($myCondition['deposit_slip_url']) ?>" alt="Slip" style="max-width: 100%; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0;">
            <?php endif; ?>
            <?php if(!$isIframe): ?><a href="/pages/chat.php?conversation=<?= urlencode($_GET['conversation_id'] ?? '') ?>" class="btn btn-outline">กลับไปที่แชท</a><?php endif; ?>
        <?php else: ?>
            <p style="color: #718096; margin-bottom: 5px;">ยอดเงินที่ต้องชำระ (บาท)</p>
            <div class="amount">฿<?= number_format($requiredDeposit, 2) ?></div>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="form-grid">
                    <!-- ซ้าย: วิธีการชำระเงิน และ QR -->
                    <div class="left-col">
                        <div class="payment-method-selector" style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px; text-align:left;">
                            <label style="font-weight:500; color:#4a5568;">เลือกช่องทางการชำระเงิน</label>
                            
                            <label style="display:flex; align-items:center; padding:12px; border:1px solid #cbd5e1; border-radius:8px; cursor:pointer; background:#fff;">
                                <input type="radio" name="payment_method" value="slip" checked onchange="document.getElementById('qr-section').style.display='block'; document.getElementById('slip-upload-section').style.display='block'; document.getElementById('slip_image').required=true;" style="margin-right:10px; width:18px; height:18px;">
                                <div>
                                    <div style="font-weight:500; color:#1e293b;">โอนเงินผ่านธนาคาร (แนบสลิป)</div>
                                </div>
                            </label>

                            <label style="display:flex; align-items:center; padding:12px; border:1px solid #cbd5e1; border-radius:8px; cursor:pointer; background:#fff; opacity:<?= $availableBalance >= $requiredDeposit ? '1' : '0.6' ?>;">
                                <input type="radio" name="payment_method" value="wallet" onchange="document.getElementById('qr-section').style.display='none'; document.getElementById('slip-upload-section').style.display='none'; document.getElementById('slip_image').required=false;" style="margin-right:10px; width:18px; height:18px;" <?= $availableBalance < $requiredDeposit ? 'disabled' : '' ?>>
                                <div style="flex:1;">
                                    <div style="font-weight:500; color:#1e293b; display:flex; justify-content:space-between;">
                                        <span>Wonwan Wallet</span>
                                        <span style="color:<?= $availableBalance >= $requiredDeposit ? '#059669' : '#dc2626' ?>;">฿<?= number_format($availableBalance, 2) ?></span>
                                    </div>
                                    <?php if($availableBalance < $requiredDeposit): ?>
                                        <div style="font-size:12px; color:#dc2626; margin-top:4px;">ยอดเงินไม่เพียงพอ กรุณาเติมเงิน</div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                        
                        <div id="qr-section">
                            <div style="text-align:center; background:#f0fdfa; border:1px solid #c6f6d5; border-radius:12px; padding:20px; margin-bottom:20px;">
                                <img src="<?= htmlspecialchars($qrImage) ?>" alt="QR Code PromptPay" style="width:200px; height:200px; max-width:100%; border-radius:8px; margin-bottom:10px; background:#fff; padding:10px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
                                <div style="font-weight:600; color:#175564;">PromptPay: <?= htmlspecialchars(WANAWAN_PROMPTPAY_ID) ?></div>
                                <div style="font-size:14px; color:#4a5568;">สแกนเพื่อชำระเงินมัดจำ ฿<?= number_format($requiredDeposit, 2) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ขวา: ที่อยู่ แนบสลิป ปุ่มยืนยัน -->
                    <div class="right-col">
                        <div style="background:#fff3cd; color:#856404; padding:12px; border-radius:8px; font-size:13px; text-align:left; margin-bottom:20px; border:1px solid #ffeeba; line-height:1.5;">
                            <strong><i class="fas fa-info-circle"></i> หมายเหตุ:</strong> นี่คือการวางเงินมัดจำเข้าสู่ระบบส่วนกลางของ Wonwan (ไม่ได้โอนให้อีกฝ่ายโดยตรง) หากคุณได้รับสินค้าไม่ตรงตามสภาพที่ตกลงกันไว้ คุณสามารถเปิดข้อพิพาทเพื่อขอรับเงินมัดจำคืนได้
                        </div>

                        <div class="upload-area">
                            <label style="font-weight: 500; color: #4a5568; margin-bottom:5px; display:block;"><i class="fas fa-map-marker-alt"></i> ที่อยู่สำหรับจัดส่ง</label>
                            <textarea name="shipping_address" rows="5" style="width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: inherit; font-size:14px; resize:vertical; margin-bottom: 20px;" required placeholder="ระบุ ชื่อ-นามสกุล, ที่อยู่, เบอร์โทรศัพท์"></textarea>
                        </div>

                        <div id="slip-upload-section" class="upload-area">
                            <label style="font-weight: 500; color: #4a5568; margin-bottom:5px; display:block;"><i class="fas fa-upload"></i> อัปโหลดหลักฐานการโอนเงิน (สลิป)</label>
                            <div class="slip-preview-box" onclick="document.getElementById('slip_image').click()" style="width: 100%; height: 200px; border: 2px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f8fafc; overflow: hidden; margin-bottom: 20px; position: relative;">
                                <div id="slip-placeholder-text" style="color: #64748b; text-align: center;">
                                    <i class="fas fa-image" style="font-size: 32px; margin-bottom: 10px; display: block; color:#94a3b8;"></i>
                                    คลิกเพื่อแนบรูปภาพสลิป
                                </div>
                                <img id="slip-preview-img" style="display: none; width: 100%; height: 100%; object-fit: contain; background: #000;">
                            </div>
                            <input type="file" id="slip_image" name="slip_image" accept="image/jpeg,image/png,image/webp" required style="display: none;" onchange="previewSlip(this)">
                        </div>

                        <button type="submit" class="btn" style="margin-bottom: 10px;">ยืนยันการชำระเงิน</button>
                        <?php if(!$isIframe): ?><a href="/pages/chat.php?conversation=<?= urlencode($_GET['conversation_id'] ?? '') ?>" class="btn btn-outline" style="display:block; text-align:center;">ยกเลิก</a><?php endif; ?>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <script>
    function previewSlip(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('slip-preview-img').src = e.target.result;
                document.getElementById('slip-preview-img').style.display = 'block';
                document.getElementById('slip-placeholder-text').style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>
