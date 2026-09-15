<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}
require_once __DIR__ . '/../config/config.php';

$userId = (int)$_SESSION['user_id'];
$requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
$isIframe = !empty($_GET['iframe']) || !empty($_POST['iframe']);

if (!$requestId) {
    exit('Invalid request');
}





$q = $pdo->prepare('SELECT er.*, e.owner_id, e.title as target_title FROM exchange_requests er JOIN exchanges e ON er.exchange_id = e.exchange_id WHERE er.request_id = ?');
$q->execute([$requestId]);
$req = $q->fetch(PDO::FETCH_ASSOC);

if (!$req || !in_array($userId, [(int)$req['requester_id'], (int)$req['owner_id']], true)) {
    exit('Unauthorized');
}

$isRequester = ($userId === (int)$req['requester_id']);
$mySlip = $isRequester ? $req['requester_slip_url'] : $req['offer_slip_url'];
$mySlipStatus = $isRequester ? $req['requester_slip_status'] : $req['offer_slip_status'];
$myAddress = $isRequester ? $req['requester_address'] : $req['offer_address'];
$myTracking = $isRequester ? $req['requester_tracking_to_admin'] : $req['offer_tracking_to_admin'];
$myQcStatus = $isRequester ? $req['admin_qc_requester_status'] : $req['admin_qc_offer_status'];
$myAdminTracking = $isRequester ? $req['admin_tracking_to_requester'] : $req['admin_tracking_to_offer'];

// Placeholder data
$feeAmount = 100;
$bankName = "ธนาคารกสิกรไทย (KBank)";
$bankAccountName = "บจก. วนวรรณ";
$bankAccountNumber = "123-4-56789-0";
$adminAddress = "บริษัท วนวรรณ จำกัด\n123 อาคารทดสอบ ชั้น 2 ถ.สุขุมวิท\nแขวงคลองเตย เขตคลองเตย\nกรุงเทพมหานคร 10110\nเบอร์โทร: 02-123-4567";

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wonwan Escrow - ขั้นตอนตรวจสอบ QC</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #175564;
            --primary-dark: #2F6E63;
            --bg-color: #F8FAFC;
            --text-main: #1E293B;
            --text-muted: #64748B;
        }
        * { box-sizing: border-box; }
        html, body {
            <?php if ($isIframe): ?>
            scrollbar-width: none;
            -ms-overflow-style: none;
            <?php endif; ?>
        }
        <?php if ($isIframe): ?>
        html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar { display: none; }
        * { scrollbar-width: none; }
        <?php endif; ?>

        body {
            font-family: 'Prompt', 'Inter', sans-serif;
            background: <?= $isIframe ? 'transparent' : 'var(--bg-color)' ?>;
            margin: 0; padding: <?= $isIframe ? '15px' : '20px' ?>;
            color: var(--text-main);
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: <?= $isIframe ? 'transparent' : '#fff' ?>;
            padding: <?= $isIframe ? '0' : '30px' ?>;
            border-radius: 16px;
            box-shadow: <?= $isIframe ? 'none' : '0 10px 30px rgba(0,0,0,0.05)' ?>;
        }
        h2 { margin-top: 0; color: #175564; text-align: center; margin-bottom: 24px; display: <?= $isIframe ? 'none' : 'block' ?>; }
        .step {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fafaf9;
        }
        .step-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #175564;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .step-title.completed { color: #2F6E63; }
        .info-box {
            background: #f0fdfa;
            border: 1px solid #ccfbf1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            line-height: 1.6;
        }
        .info-box.info-blue { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
        .info-box.info-green { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .info-box.info-red { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .btn {
            background: linear-gradient(135deg, #175564, #2F6E63); font-family: 'Prompt', sans-serif;
            color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; font-size: 15px; transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .file-upload-wrapper { position: relative; overflow: hidden; display: inline-block; width: 100%; }
        .file-upload-wrapper input[type=file] { font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; }
        .btn-upload { background: #fff; border: 1px dashed #cbd5e1; color: #64748B; padding: 20px; text-align: center; border-radius: 8px; font-size: 14px; }
        .upload-preview { display: none; margin-top: 10px; max-width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>ขั้นตอนการส่งตรวจสภาพหนังสือ</h2>
        
        <?php if ($myQcStatus !== 'pending'): ?>
            <div class="info-box <?= $myQcStatus === 'passed' ? 'info-green' : 'info-red' ?>">
                <strong>ผลการตรวจสอบสภาพ:</strong> <?= $myQcStatus === 'passed' ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> ผ่าน' : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> ไม่ผ่าน' ?>
            </div>
            
            <?php if ($myQcStatus === 'passed' && $myAdminTracking): ?>
                <div class="info-box info-blue">
                    <strong><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M5.8 11.3 2 22l10.7-3.79"></path><path d="M4 3h.01"></path><path d="M22 8h.01"></path><path d="M15 2h.01"></path><path d="M22 20h.01"></path><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"></path><path d="m22 13-.82-.33c-.86-.39-1.67.14-1.92 1.03v0c-.2.74-.93 1.18-1.7 1.11h-.14c-.86-.07-1.63.5-1.78 1.35L15 18"></path><path d="m11 15 8-8"></path></svg> พัสดุถูกจัดส่งถึงคุณแล้ว!</strong><br>
                    หมายเลขพัสดุจากส่วนกลาง: <strong><?= htmlspecialchars($myAdminTracking) ?></strong>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- STEP 1: โอนเงินค่าบริการ (ถ้ายังไม่จ่าย หรือ สลิปถูกปฏิเสธ) -->
        <?php if ($mySlipStatus === null || $mySlipStatus === 'rejected'): ?>
            <div class="step">
                <div class="step-title">1. ชำระค่าบริการและอัปโหลดสลิป</div>
                
                <?php if ($mySlipStatus === 'rejected'): ?>
                    <div class="info-box info-red">
                        <strong><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> สลิปของคุณไม่ถูกต้อง</strong> กรุณาตรวจสอบความถูกต้องของยอดเงินหรือรูปภาพและอัปโหลดสลิปใหม่อีกครั้ง
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <strong>รายละเอียดค่าใช้จ่าย (รวม 100 บาท)</strong><br>
                    - ค่าบริการส่วนกลาง (QC): 50 บาท<br>
                    - ค่าส่งสินค้ากลับ: 50 บาท<br><br>
                    <strong>บัญชีธนาคาร:</strong> <?= $bankName ?><br>
                    <strong>ชื่อบัญชี:</strong> <?= $bankAccountName ?><br>
                    <strong>เลขที่บัญชี:</strong> <strong style="font-size:16px; color:#d97706;"><?= $bankAccountNumber ?></strong>
                </div>
                
                <form action="/pages/handlers/trade_submit_logistics.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="request_id" value="<?= $requestId ?>">
                    <input type="hidden" name="action_type" value="upload_slip">
<?php if($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                    
                    <div class="form-group">
                        <label>หลักฐานการโอนเงิน (สลิป)</label>
                        <div class="file-upload-wrapper">
                            <div class="btn-upload" id="uploadBtnText">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg> คลิกเพื่อเลือกไฟล์สลิป (รูปภาพ)
                            </div>
                            <input type="file" name="slip_image" accept="image/*" required onchange="previewImage(this)">
                        </div>
                        <img id="imgPreview" class="upload-preview" src="">
                    </div>
                    
                    <button type="submit" class="btn">ยืนยันการชำระเงิน</button>
                </form>
            </div>
        <?php else: ?>
            <div class="step" style="opacity: 0.7;">
                <div class="step-title completed"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> 1. ชำระค่าบริการและอัปโหลดสลิป</div>
                <div style="font-size:14px;">อัปโหลดสลิปเรียบร้อยแล้ว</div>
            </div>
        <?php endif; ?>

        <!-- STEP 2: ส่งของและแจ้งเลขพัสดุ -->
        <?php if ($mySlipStatus === 'pending'): ?>
            <div class="step">
                <div class="step-title">2. รอแอดมินตรวจสอบสลิป</div>
                <div class="info-box info-blue" style="text-align:center;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M5 22h14"></path><path d="M5 2h14"></path><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path></svg> กรุณารอสักครู่... แอดมินกำลังตรวจสอบสลิปโอนเงินของคุณ<br>เมื่อได้รับการอนุมัติแล้ว คุณจะสามารถเห็นที่อยู่ในการจัดส่งและกรอกเลขพัสดุได้
                </div>
            </div>
        <?php elseif ($mySlipStatus === 'approved' && empty($myTracking)): ?>
            <div class="step">
                <div class="step-title">2. จัดส่งและแจ้งเลขพัสดุ</div>
                <div class="info-box">
                    <strong>ที่อยู่สำหรับจัดส่งสินค้าไปตรวจ (ส่วนกลาง):</strong><br>
                    <?= nl2br(htmlspecialchars($adminAddress)) ?>
                </div>
                
                <form action="/pages/handlers/trade_submit_logistics.php" method="POST">
                    <input type="hidden" name="request_id" value="<?= $requestId ?>">
                    <input type="hidden" name="action_type" value="submit_tracking">
<?php if($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                    
                    <div class="form-group">
                        <label>ที่อยู่ของคุณ (สำหรับรับสินค้ากลับ)</label>
                        <textarea name="user_address" class="form-control" required placeholder="ชื่อ, ที่อยู่, เบอร์โทรศัพท์..."><?= htmlspecialchars($myAddress ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>บริษัทขนส่ง</label>
                        <select name="carrier" class="form-control" required>
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
                    
                    <button type="submit" class="btn">บันทึกข้อมูลการจัดส่ง</button>
                </form>
            </div>
        <?php elseif ($mySlipStatus === 'approved' && !empty($myTracking)): ?>
            <div class="step" style="opacity: 0.7;">
                <div class="step-title completed"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> 2. จัดส่งและแจ้งเลขพัสดุ</div>
                <div style="font-size:14px;">แจ้งเลขพัสดุเรียบร้อยแล้ว: <strong><?= htmlspecialchars($myTracking) ?></strong></div>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('imgPreview');
                    img.src = e.target.result;
                    img.style.display = 'block';
                    document.getElementById('uploadBtnText').style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
