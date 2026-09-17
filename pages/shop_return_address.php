<?php
/**
 * pages/shop_return_address.php
 * ตั้งค่าที่อยู่รับคืนสินค้า — Shop Admin / Publisher Admin
 */
session_start();
require_once __DIR__ . '/../config/config.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = $_SESSION['role'] ?? '';
if (!$userId || !in_array($role, ['shop_admin', 'publisher_admin'], true)) {
    header('Location:/pages/login.php'); exit;
}

$sellerType = $role === 'publisher_admin' ? 'publisher' : 'shop';

// ดึง seller ID
if ($sellerType === 'publisher') {
    $sq = $pdo->prepare('SELECT p.publisher_id id, p.company_name name, p.return_contact_name, p.return_contact_phone, p.return_address FROM publisher_staff ps JOIN publishers p ON p.publisher_id = ps.publisher_id WHERE ps.user_id = ? LIMIT 1');
} else {
    $sq = $pdo->prepare('SELECT s.shop_id id, s.shop_name name, s.return_contact_name, s.return_contact_phone, s.return_address FROM shop_staff ss JOIN shops s ON s.shop_id = ss.shop_id WHERE ss.user_id = ? LIMIT 1');
}
$sq->execute([$userId]);
$sellerRow = $sq->fetch(PDO::FETCH_ASSOC);
if (!$sellerRow) { header('Location:/pages/login.php'); exit; }

$sellerId   = (int)$sellerRow['id'];
$sellerName = (string)$sellerRow['name'];

$active_menu = 'return_address';

$formError = '';
$formSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactName  = trim($_POST['return_contact_name'] ?? '');
    $contactPhone = trim($_POST['return_contact_phone'] ?? '');
    $address      = trim($_POST['return_address'] ?? '');

    if ($contactName === '') $formError = 'กรุณากรอกชื่อผู้ติดต่อ / ผู้รับ';
    elseif ($contactPhone === '') $formError = 'กรุณากรอกเบอร์โทรศัพท์';
    elseif ($address === '') $formError = 'กรุณากรอกที่อยู่เต็ม';
    elseif (!preg_match('/^0\d{8,9}$/', $contactPhone)) $formError = 'เบอร์โทรศัพท์ไม่ถูกต้อง (เช่น 0812345678)';
    else {
        try {
            if ($sellerType === 'publisher') {
                $upd = $pdo->prepare('UPDATE publishers SET return_contact_name = ?, return_contact_phone = ?, return_address = ? WHERE publisher_id = ?');
            } else {
                $upd = $pdo->prepare('UPDATE shops SET return_contact_name = ?, return_contact_phone = ?, return_address = ? WHERE shop_id = ?');
            }
            $upd->execute([$contactName, $contactPhone, $address, $sellerId]);
            logSellerAction($pdo, $sellerType, $sellerId, $userId, 'UPDATE_RETURN_ADDRESS', (string)$sellerId, 'อัปเดตที่อยู่รับสินค้าคืน');
            $formSuccess = 'บันทึกข้อมูลเรียบร้อยแล้ว';
            
            $sellerRow['return_contact_name'] = $contactName;
            $sellerRow['return_contact_phone'] = $contactPhone;
            $sellerRow['return_address'] = $address;
        } catch (Throwable $e) {
            $formError = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
        }
    }
}
$seller_name = $sellerName;

// Generate CSRF
if (empty($_SESSION['admin_return_addr_csrf'])) {
    $_SESSION['admin_return_addr_csrf'] = bin2hex(random_bytes(24));
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าที่อยู่รับคืนสินค้า — วนวรรณ</title>
    <meta name="description" content="ตั้งค่าที่อยู่สำหรับรับสินค้าคืนจากลูกค้า">
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-family); background: #fdf8f9; color: #1a1a2e; display: flex; min-height: 100vh; }
        .main { margin-left: 220px; flex: 1; padding: 44px 48px; min-width: 0; }
        @media (max-width: 900px) { .main { margin-left: 0; padding: 24px 16px; } }

        .page-head { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .page-head h1 { font-size: 26px; color: var(--secondary-color); font-weight: 800; }
        .page-head p { font-size: 14px; color: #6b7280; margin-top: 5px; }

        .grid-wrap { display: grid; grid-template-columns: 1fr 360px; gap: 28px; max-width: 900px; }
        @media (max-width: 800px) { .grid-wrap { grid-template-columns: 1fr; } }

        .card { background: #fff; border-radius: 20px; padding: 28px; border: 1px solid #f0e4ea; box-shadow: 0 2px 12px rgba(0,0,0,.04); }
        .card-title { font-size: 16px; font-weight: 800; color: var(--secondary-color); margin-bottom: 22px; padding-bottom: 14px; border-bottom: 1px solid #f5eef1; display: flex; align-items: center; gap: 10px; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 700; color: #4b5563; margin-bottom: 8px; }
        .form-label .req { color: #ef4444; }
        .form-control {
            width: 100%; padding: 12px 16px; border: 1.5px solid #e9d8de; border-radius: 12px;
            font: inherit; font-size: 14px; color: #1a1a2e; outline: none;
            transition: border-color .2s, box-shadow .2s; background: #fdf8f9;
        }
        .form-control:focus { border-color: var(--secondary-color); box-shadow: 0 0 0 3px rgba(194,70,110,.12); background: #fff; }
        textarea.form-control { resize: vertical; min-height: 110px; }

        .btn-save {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 28px; border: none; border-radius: 12px; cursor: pointer;
            font: inherit; font-size: 14px; font-weight: 700;
            background: linear-gradient(135deg, var(--secondary-color), #a0446e);
            color: #fff; box-shadow: 0 4px 16px rgba(194,70,110,.3);
            transition: opacity .2s, transform .15s;
        }
        .btn-save:hover { opacity: .92; transform: translateY(-1px); }
        .btn-save:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        .notice { padding: 14px 18px; border-radius: 12px; font-size: 14px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; }
        .notice-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .notice-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .notice-info    { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

        /* Preview box */
        .preview-box {
            background: linear-gradient(135deg, #fdf2f8, #fce7f3);
            border: 1px solid #f9a8d4; border-radius: 16px; padding: 20px;
        }
        .preview-box .preview-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #9d174d; margin-bottom: 10px; }
        .preview-box .preview-name  { font-size: 17px; font-weight: 800; color: #1a1a2e; margin-bottom: 12px; }
        .preview-box .preview-row   { font-size: 13px; color: #374151; display: flex; gap: 8px; margin-bottom: 6px; }
        .preview-box .preview-row svg { flex-shrink: 0; margin-top: 1px; color: #be185d; }
        .preview-box .no-addr       { font-size: 13px; color: #9ca3af; font-style: italic; margin-top: 8px; }

        /* Tip list */
        .tip-list { list-style: none; padding: 0; }
        .tip-list li { display: flex; gap: 10px; font-size: 13px; color: #4b5563; margin-bottom: 12px; line-height: 1.5; }
        .tip-list li .tip-icon { font-size: 16px; flex-shrink: 0; }

        .toast-msg { display: none; position: fixed; bottom: 28px; right: 28px; background: #1a1a2e; color: #fff; border-radius: 12px; padding: 14px 24px; font-size: 14px; font-weight: 600; z-index: 999; box-shadow: 0 8px 30px rgba(0,0,0,.2); }
        .toast-msg.show { display: block; animation: fadeup .3s; }
        @keyframes fadeup { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: translateY(0); } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../components/sidebar.php'; ?>

<main class="main">
    <div class="page-head">
        <div>
            <h1>📍 ตั้งค่าที่อยู่รับคืนสินค้า</h1>
            <p>ลูกค้าจะเห็นข้อมูลนี้เมื่อต้องการยื่นคำขอคืนสินค้า กรุณากรอกข้อมูลให้ถูกต้องและเป็นปัจจุบัน</p>
        </div>
    </div>

    <div class="grid-wrap">
        <!-- ── ฝั่งซ้าย: ฟอร์ม ── -->
        <div>
            <div class="card">
                <div class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    ข้อมูลที่อยู่รับสินค้าคืน
                </div>

                <div class="notice notice-info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    สามารถอัปเดตได้ตลอดเวลา เช่น เมื่อย้ายออฟฟิศหรือเปลี่ยนโกดัง ที่อยู่ใหม่จะมีผลทันที
                </div>

                <div id="form-message"></div>

                <form id="addr-form" method="POST" action="" novalidate>
                    <input type="hidden" name="csrf_token" id="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['admin_return_addr_csrf']) ?>">

                    <div class="form-group">
                        <label class="form-label">ชื่อผู้ติดต่อ / ผู้รับ <span class="req">*</span></label>
                        <input type="text" id="return_contact_name" name="return_contact_name"
                               class="form-control"
                               value="<?= htmlspecialchars($sellerRow['return_contact_name'] ?? '') ?>"
                               placeholder="เช่น ฝ่ายรับคืนสินค้า วนวรรณ" maxlength="100" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์ <span class="req">*</span></label>
                        <input type="tel" id="return_contact_phone" name="return_contact_phone"
                               class="form-control"
                               value="<?= htmlspecialchars($sellerRow['return_contact_phone'] ?? '') ?>"
                               placeholder="เช่น 0812345678" maxlength="20" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ที่อยู่เต็ม (สำหรับจัดส่งพัสดุคืน) <span class="req">*</span></label>
                        <textarea id="return_address" name="return_address" class="form-control" required
                                  placeholder="บ้านเลขที่ / ซอย / ถนน&#10;ตำบล / อำเภอ / จังหวัด / รหัสไปรษณีย์"><?= htmlspecialchars($sellerRow['return_address'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-save" id="save-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        บันทึกที่อยู่
                    </button>
                </form>
            </div>
        </div>

        <!-- ── ฝั่งขวา: Preview + Tips ── -->
        <div style="display:flex;flex-direction:column;gap:20px;">
            <div class="card">
                <div class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    ตัวอย่างที่ลูกค้าจะเห็น
                </div>
                <div class="preview-box">
                    <div class="preview-label">📮 ที่อยู่รับสินค้าคืน</div>
                    <div class="preview-name" id="pv-name"><?= htmlspecialchars($sellerName) ?></div>
                    <div class="preview-row" id="pv-contact-wrap" style="<?= ($sellerRow['return_contact_name'] ?? '') ? '' : 'display:none' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        ผู้รับ: <strong id="pv-contact"><?= htmlspecialchars($sellerRow['return_contact_name'] ?? '') ?></strong>
                    </div>
                    <div class="preview-row" id="pv-phone-wrap" style="<?= ($sellerRow['return_contact_phone'] ?? '') ? '' : 'display:none' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.67 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.58 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.73a16 16 0 0 0 6.29 6.29l.92-.92a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        โทร: <strong id="pv-phone"><?= htmlspecialchars($sellerRow['return_contact_phone'] ?? '') ?></strong>
                    </div>
                    <div id="pv-addr" style="margin-top:10px;font-size:13px;color:#374151;white-space:pre-line"><?= htmlspecialchars($sellerRow['return_address'] ?? '') ?></div>
                    <div class="no-addr" id="pv-empty" style="<?= ($sellerRow['return_address'] ?? '') ? 'display:none' : '' ?>">
                        ยังไม่ได้กรอกข้อมูลที่อยู่
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    คำแนะนำ
                </div>
                <ul class="tip-list">
                    <li><span class="tip-icon">✅</span> กรอกที่อยู่ที่ถูกต้องและชัดเจน ลูกค้าจะส่งสินค้ากลับมาตามนี้</li>
                    <li><span class="tip-icon">📞</span> ใส่เบอร์โทรติดต่อที่รับสายได้จริง เผื่อบริษัทขนส่งโทรมา</li>
                    <li><span class="tip-icon">🔄</span> อัปเดตทันทีเมื่อย้ายที่อยู่ เพื่อป้องกันการสูญหายของพัสดุ</li>
                    <li><span class="tip-icon">📮</span> รวมรหัสไปรษณีย์ทุกครั้งเพื่อความถูกต้อง</li>
                </ul>
            </div>
        </div>
    </div>
</main>

<div class="toast-msg" id="toast"></div>

<script>
(function() {
    const form     = document.getElementById('addr-form');
    const msgDiv   = document.getElementById('form-message');
    const saveBtn  = document.getElementById('save-btn');

    // Live preview
    function updatePreview() {
        const contact = document.getElementById('return_contact_name').value.trim();
        const phone   = document.getElementById('return_contact_phone').value.trim();
        const addr    = document.getElementById('return_address').value.trim();

        document.getElementById('pv-contact').textContent = contact;
        document.getElementById('pv-phone').textContent   = phone;
        document.getElementById('pv-addr').textContent    = addr;
        document.getElementById('pv-contact-wrap').style.display = contact ? '' : 'none';
        document.getElementById('pv-phone-wrap').style.display   = phone   ? '' : 'none';
        document.getElementById('pv-empty').style.display        = addr    ? 'none' : '';
    }

      ['return_contact_name','return_contact_phone','return_address'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.addEventListener('input', updatePreview);
      });
      
      // Initialize preview on page load
      updatePreview();

      form.addEventListener('submit', function(e) {
        const contactName  = document.getElementById('return_contact_name').value.trim();
        const contactPhone = document.getElementById('return_contact_phone').value.trim();
        const address      = document.getElementById('return_address').value.trim();
        
        let err = '';
        if (!contactName) err = 'กรุณากรอกชื่อผู้ติดต่อ / ผู้รับ';
        else if (!contactPhone) err = 'กรุณากรอกเบอร์โทรศัพท์';
        else if (!address) err = 'กรุณากรอกที่อยู่เต็ม';
        else if (!/^0\d{8,9}$/.test(contactPhone)) err = 'เบอร์โทรศัพท์ไม่ถูกต้อง';

        if (err) {
            e.preventDefault();
            showNotice('error', err);
            return;
        }
        
        saveBtn.disabled = true;
        saveBtn.textContent = '⏳ กำลังบันทึกข้อมูล…';
    });

    function showNotice(type, msg) {
        msgDiv.innerHTML = `<div class="notice notice-${type}" style="margin-bottom:20px">${msg}</div>`;
    }
    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3000);
    }
})();
</script>
</body>
</html>
