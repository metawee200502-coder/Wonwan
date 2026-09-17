<?php
/**
 * pages/return_request.php?order_id=X
 * หน้าฟอร์มคืนสินค้า — ผู้ซื้อเท่านั้น
 */
session_start();
if (empty($_SESSION['user_id'])) { header('Location:/pages/login.php'); exit; }
// ห้าม shop_admin / publisher_admin เข้า
if (in_array($_SESSION['role'] ?? '', ['shop_admin','publisher_admin','super_admin'], true)) {
    header('Location:/pages/dashboard.php'); exit;
}
require_once __DIR__ . '/../config/config.php';

$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) { header('Location:/pages/orders.php'); exit; }

// ตรวจว่า order นี้เป็นของ user คนนี้ และสถานะ delivered
$orderStmt = $pdo->prepare("SELECT o.order_id, o.order_number, o.shipping_status, o.shipping_fee FROM orders o WHERE o.order_id = ? AND o.user_id = ? LIMIT 1");
$orderStmt->execute([$orderId, $userId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order || $order['shipping_status'] !== 'delivered') {
    header('Location:/pages/orders.php?error=not_eligible'); exit;
}

// ตรวจว่ายังไม่เคยยื่น
$existStmt = $pdo->prepare('SELECT return_id, status FROM return_requests WHERE order_id = ? LIMIT 1');
$existStmt->execute([$orderId]);
$existing = $existStmt->fetch(PDO::FETCH_ASSOC);

// ดึง items + seller info
$itemsStmt = $pdo->prepare("SELECT oi.*, b.title, b.author, b.image_cover, b.seller_type, b.seller_id, COALESCE(p.company_name, s.shop_name, NULLIF(u.display_name,''), u.email) seller_name, COALESCE(p.return_address, s.return_address, '') seller_return_address, COALESCE(p.return_contact_name, s.return_contact_name, '') seller_contact_name, COALESCE(p.return_contact_phone, s.return_contact_phone, '') seller_contact_phone FROM order_items oi LEFT JOIN books b ON b.book_id = oi.book_id LEFT JOIN publishers p ON b.seller_type = 'publisher' AND p.publisher_id = b.seller_id LEFT JOIN shops s ON b.seller_type = 'shop' AND s.shop_id = b.seller_id LEFT JOIN users u ON b.seller_type = 'user' AND u.user_id = b.seller_id WHERE oi.order_id = ?");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) { header('Location:/pages/orders.php'); exit; }

$firstItem = $items[0];
$sellerType    = $firstItem['seller_type'];
$sellerId      = $firstItem['seller_id'];
$sellerName    = wanawanSellerName($firstItem['seller_name'] ?? '', $sellerType);
$returnAddress = $firstItem['seller_return_address'];
$contactName   = $firstItem['seller_contact_name'];
$contactPhone  = $firstItem['seller_contact_phone'];

// ยอดรวม
$subtotal = array_sum(array_map(fn($i) => (float)$i['price_at_purchase'] * (int)$i['quantity'], $items));
$totalRefund = $subtotal + (float)$order['shipping_fee'];

// สร้าง CSRF token
if (empty($_SESSION['return_request_csrf'])) {
    $_SESSION['return_request_csrf'] = bin2hex(random_bytes(24));
}

function returnItemImg(?string $v): string {
    if (!$v) return '/assets/image/minilogo.png';
    $v = str_replace('\\', '/', trim($v));
    if (preg_match('#^https?://#i', $v)) return $v;
    $v = ltrim($v, '/');
    if (str_starts_with($v, 'pages/uploads/') || str_starts_with($v, 'uploads/')) return '/pages/' . ltrim($v, 'pages/');
    return '/assets/image/books/' . $v;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ขอคืนสินค้า #<?= htmlspecialchars($order['order_number']) ?> — วนวรรณ</title>
    <meta name="description" content="ยื่นคำขอคืนสินค้าและขอคืนเงินสำหรับคำสั่งซื้อ #<?= htmlspecialchars($order['order_number']) ?>">
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-family); background: #f8f9fa; color: #1a1a2e; min-height: 100vh; }

        /* ── HEADER ── */
        .rr-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 20px 0;
        }
        .rr-header-inner {
            max-width: 860px; margin: 0 auto; padding: 0 24px;
            display: flex; align-items: center; gap: 16px;
        }
        .rr-header a.back {
            display: inline-flex; align-items: center; gap: 8px;
            color: rgba(255,255,255,.7); text-decoration: none; font-size: 14px;
            transition: color .2s;
        }
        .rr-header a.back:hover { color: #fff; }
        .rr-header h1 {
            font-size: 20px; font-weight: 700;
            background: linear-gradient(90deg, #e2e8f0, #a5b4fc);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* ── MAIN LAYOUT ── */
        .rr-wrap { max-width: 860px; margin: 0 auto; padding: 32px 24px 80px; display: grid; grid-template-columns: 1fr 340px; gap: 28px; }
        @media (max-width: 720px) { .rr-wrap { grid-template-columns: 1fr; } }

        /* ── CARD ── */
        .card {
            background: #fff; border-radius: 20px; padding: 28px;
            border: 1px solid #e8eaf0; box-shadow: 0 2px 12px rgba(0,0,0,.04);
        }
        .card-title {
            font-size: 16px; font-weight: 700; color: #1a1a2e;
            display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
            padding-bottom: 16px; border-bottom: 1px solid #f0f0f5;
        }
        .card-title .icon { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; }

        /* ── ORDER ITEMS ── */
        .item-row { display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f5f5f8; }
        .item-row:last-child { border-bottom: none; }
        .item-img { width: 54px; height: 72px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
        .item-info { flex: 1; min-width: 0; }
        .item-title { font-size: 14px; font-weight: 600; color: #1a1a2e; margin-bottom: 4px; }
        .item-author { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
        .item-price { font-size: 13px; font-weight: 700; color: #0f3460; }

        /* ── ADDRESS BOX ── */
        .addr-box {
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            border: 1px solid #c7d2fe; border-radius: 14px; padding: 18px; margin-top: 0;
        }
        .addr-box .addr-warning { font-size: 11px; color: #6366f1; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
        .addr-box .addr-title { font-size: 15px; font-weight: 700; color: #1e1b4b; margin-bottom: 8px; }
        .addr-box .addr-row { font-size: 13px; color: #374151; line-height: 1.8; }
        .addr-box .addr-row span { font-weight: 600; }
        .no-addr {
            background: #fff7ed; border: 1px dashed #fdba74; border-radius: 14px; padding: 18px;
            font-size: 13px; color: #92400e; text-align: center;
        }

        /* ── FORM ── */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 8px; }
        .form-label .req { color: #ef4444; margin-left: 2px; }
        .form-control {
            width: 100%; padding: 12px 16px; border: 1.5px solid #e5e7eb; border-radius: 12px;
            font: inherit; font-size: 14px; color: #1a1a2e; outline: none; transition: border-color .2s, box-shadow .2s;
            background: #fafafa;
        }
        .form-control:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); background: #fff; }
        select.form-control { cursor: pointer; appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%236b7280' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px;
        }

        /* ── UPLOAD ZONE ── */
        .upload-zone {
            border: 2px dashed #c7d2fe; border-radius: 14px; padding: 28px 20px; text-align: center;
            cursor: pointer; transition: border-color .2s, background .2s;
            background: #fafbff; position: relative;
        }
        .upload-zone:hover, .upload-zone.drag { border-color: #6366f1; background: #eef2ff; }
        .upload-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-zone .upload-icon { font-size: 36px; margin-bottom: 10px; }
        .upload-zone .upload-text { font-size: 14px; color: #6b7280; }
        .upload-zone .upload-hint { font-size: 11px; color: #9ca3af; margin-top: 4px; }
        .upload-preview { margin-top: 14px; display: none; }
        .upload-preview img { max-width: 100%; max-height: 200px; border-radius: 10px; object-fit: contain; border: 1px solid #e5e7eb; }

        /* ── SUMMARY CARD (right column) ── */
        .summary-card { position: sticky; top: 28px; }
        .summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; color: #4b5563; border-bottom: 1px solid #f0f0f5; }
        .summary-row:last-child { border: none; }
        .summary-row strong { color: #1a1a2e; }
        .summary-total { display: flex; justify-content: space-between; padding: 14px 0 0; margin-top: 4px; }
        .summary-total span { font-size: 15px; font-weight: 700; color: #1a1a2e; }
        .summary-total .amount { font-size: 22px; font-weight: 800; color: #6366f1; }

        /* ── SUBMIT BTN ── */
        .btn-submit {
            width: 100%; padding: 16px; border: none; border-radius: 14px; cursor: pointer;
            font: inherit; font-size: 15px; font-weight: 700;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff; letter-spacing: .03em; margin-top: 20px;
            transition: opacity .2s, transform .15s; box-shadow: 0 4px 18px rgba(99,102,241,.35);
        }
        .btn-submit:hover { opacity: .92; transform: translateY(-1px); }
        .btn-submit:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        /* ── ALREADY SUBMITTED ── */
        .already-card { text-align: center; padding: 48px 28px; }
        .already-icon { font-size: 64px; margin-bottom: 20px; }
        .already-title { font-size: 22px; font-weight: 800; color: #1a1a2e; margin-bottom: 10px; }
        .already-sub { font-size: 14px; color: #6b7280; line-height: 1.7; }
        .status-badge { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 30px; font-weight: 700; font-size: 14px; margin: 18px auto; }
        .status-pending  { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
        .status-approved { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
        .status-rejected { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-back { display: inline-block; padding: 12px 28px; background: #1a1a2e; color: #fff; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14px; margin-top: 16px; }

        /* ── ALERT ── */
        .alert { padding: 14px 18px; border-radius: 12px; font-size: 14px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; }
        .alert-info { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .toast-msg { display: none; position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #1a1a2e; color: #fff; border-radius: 12px; padding: 14px 28px; font-size: 14px; font-weight: 600; z-index: 9999; box-shadow: 0 8px 30px rgba(0,0,0,.2); }
        .toast-msg.show { display: block; animation: fadeup .3s ease; }
        @keyframes fadeup { from { opacity:0; transform: translate(-50%, 12px); } to { opacity:1; transform: translate(-50%,0); } }
    </style>
</head>
<body>

<header class="rr-header">
    <div class="rr-header-inner">
        <a href="/pages/orders.php" class="back">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            กลับไปรายการคำสั่งซื้อ
        </a>
        <h1>ขอคืนสินค้า / คืนเงิน</h1>
    </div>
</header>

<?php if ($existing): ?>
<!-- ── แสดง status ถ้ายื่นไปแล้ว ── -->
<div class="rr-wrap" style="grid-template-columns:1fr">
    <div class="card already-card">
        <?php
        $icons   = ['pending' => '⏳', 'approved' => '✅', 'rejected' => '❌'];
        $labels  = ['pending' => 'รอการพิจารณา', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ปฏิเสธแล้ว'];
        $classes = ['pending' => 'status-pending', 'approved' => 'status-approved', 'rejected' => 'status-rejected'];
        $st = $existing['status'];
        ?>
        <div class="already-icon"><?= $icons[$st] ?? '📋' ?></div>
        <div class="already-title">คำขอคืนสินค้า #<?= htmlspecialchars($order['order_number']) ?></div>
        <div class="already-sub">คุณได้ยื่นคำขอสำหรับคำสั่งซื้อนี้แล้ว<br>กรุณารอการพิจารณาจากร้านค้า</div>
        <div class="status-badge <?= $classes[$st] ?? '' ?>"><?= $icons[$st] ?? '' ?> <?= $labels[$st] ?? $st ?></div>
        <br>
        <a href="/pages/orders.php" class="btn-back">กลับไปรายการคำสั่งซื้อ</a>
    </div>
</div>

<?php else: ?>

<div class="rr-wrap">
    <!-- ── LEFT COLUMN: FORM ── -->
    <div style="display:flex;flex-direction:column;gap:24px;">

        <!-- รายการสินค้า -->
        <div class="card">
            <div class="card-title">
                <div class="icon" style="background:#eef2ff;">📦</div>
                สินค้าในคำสั่งซื้อ #<?= htmlspecialchars($order['order_number']) ?>
            </div>
            <?php foreach ($items as $it): ?>
            <div class="item-row">
                <img class="item-img" src="<?= htmlspecialchars(returnItemImg($it['image_cover'])) ?>" alt="">
                <div class="item-info">
                    <div class="item-title"><?= htmlspecialchars($it['title'] ?? '-') ?></div>
                    <div class="item-author"><?= htmlspecialchars($it['author'] ?? '') ?></div>
                    <div class="item-price">฿<?= number_format((float)$it['price_at_purchase'] * (int)$it['quantity'], 2) ?> (×<?= (int)$it['quantity'] ?>)</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ที่อยู่รับคืนของร้านค้า -->
        <div class="card">
            <div class="card-title">
                <div class="icon" style="background:#fce7f3;">📍</div>
                ที่อยู่สำหรับจัดส่งคืน
            </div>
            <div class="alert alert-info" style="margin-bottom:16px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                กรุณาส่งสินค้าไปยังที่อยู่ด้านล่างนี้ก่อน แล้วจึงกรอกเลขพัสดุในฟอร์ม
            </div>
            <?php if ($returnAddress): ?>
            <div class="addr-box">
                <div class="addr-warning">📮 ที่อยู่รับสินค้าคืน</div>
                <div class="addr-title"><?= htmlspecialchars($sellerName) ?></div>
                <div class="addr-row">
                    <?php if ($contactName): ?><div>ผู้รับ: <span><?= htmlspecialchars($contactName) ?></span></div><?php endif; ?>
                    <?php if ($contactPhone): ?><div>โทร: <span><?= htmlspecialchars($contactPhone) ?></span></div><?php endif; ?>
                    <div style="margin-top:8px;white-space:pre-line"><?= htmlspecialchars($returnAddress) ?></div>
                </div>
            </div>
            <?php else: ?>
            <div class="no-addr">
                ⚠️ ร้านค้ายังไม่ได้ตั้งค่าที่อยู่รับสินค้าคืน<br>
                <small>กรุณาติดต่อร้านค้า <?= htmlspecialchars($sellerName) ?> โดยตรงก่อนส่งคืน</small>
            </div>
            <?php endif; ?>
        </div>

        <!-- ฟอร์ม -->
        <div class="card">
            <div class="card-title">
                <div class="icon" style="background:#fff7ed;">📝</div>
                ข้อมูลการขอคืน
            </div>

            <div id="form-error" class="alert alert-error" style="display:none;"></div>

            <form id="return-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['return_request_csrf']) ?>">
                <input type="hidden" name="order_id" value="<?= $orderId ?>">

                <div class="form-group">
                    <label class="form-label">สาเหตุการคืนสินค้า <span class="req">*</span></label>
                    <select id="reason" name="reason" class="form-control" required>
                        <option value="">— เลือกสาเหตุ —</option>
                        <option value="damaged">🔧 สินค้าชำรุด/เสียหาย</option>
                        <option value="not_as_described">📷 สินค้าไม่ตรงตามที่โฆษณา</option>
                        <option value="wrong_item">📦 ได้รับสินค้าผิดรายการ</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">รูปภาพหลักฐาน <span class="req">*</span></label>
                    <div class="upload-zone" id="upload-zone">
                        <input type="file" id="evidence" name="evidence" accept="image/jpeg,image/png,image/webp" required>
                        <div class="upload-icon">🖼️</div>
                        <div class="upload-text">คลิกหรือลากไฟล์มาวางที่นี่</div>
                        <div class="upload-hint">JPG, PNG, WebP — ขนาดสูงสุด 5MB</div>
                    </div>
                    <div class="upload-preview" id="preview-wrap">
                        <img id="preview-img" src="" alt="ตัวอย่างรูปหลักฐาน">
                        <div style="font-size:12px;color:#6b7280;margin-top:6px;text-align:center" id="preview-name"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">เลขพัสดุที่ส่งคืน <span class="req">*</span></label>
                    <input type="text" id="return_tracking" name="return_tracking" class="form-control"
                           placeholder="เช่น TH123456789 (กรุณาส่งสินค้าก่อนกรอกเลขนี้)" required maxlength="100">
                </div>

                <button type="submit" class="btn-submit" id="submit-btn">
                    ✅ ยืนยันการขอคืนสินค้า
                </button>
            </form>
        </div>
    </div>

    <!-- ── RIGHT COLUMN: SUMMARY ── -->
    <div class="summary-card">
        <div class="card">
            <div class="card-title">
                <div class="icon" style="background:#f0fdf4;">💰</div>
                สรุปการคืนเงิน (ประมาณการ)
            </div>
            <?php foreach ($items as $it): ?>
            <div class="summary-row">
                <span><?= htmlspecialchars(mb_strimwidth($it['title'] ?? '-', 0, 24, '…')) ?> ×<?= (int)$it['quantity'] ?></span>
                <strong>฿<?= number_format((float)$it['price_at_purchase'] * (int)$it['quantity'], 2) ?></strong>
            </div>
            <?php endforeach; ?>
            <div class="summary-row">
                <span>ค่าจัดส่ง</span>
                <strong>฿<?= number_format((float)$order['shipping_fee'], 2) ?></strong>
            </div>
            <div class="summary-total">
                <span>ยอดที่จะได้รับคืน</span>
                <span class="amount">฿<?= number_format($totalRefund, 2) ?></span>
            </div>
            <p style="font-size:11px;color:#9ca3af;margin-top:12px;line-height:1.6;">* ยอดเงินจะเข้ากระเป๋าของคุณหลังจากร้านค้าตรวจสอบและอนุมัติคำขอ</p>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-title" style="margin-bottom:12px;">
                <div class="icon" style="background:#fff7ed;">📋</div>
                ขั้นตอนการคืนสินค้า
            </div>
            <?php $steps = [['1','ยื่นคำขอ','กรอกฟอร์มนี้ให้ครบถ้วน'],['2','ส่งสินค้าคืน','แพ็คสินค้าและส่งไปที่อยู่ด้านบน'],['3','รอตรวจสอบ','ร้านค้าจะตรวจสอบภายใน 3-7 วัน'],['4','รับเงินคืน','เงินจะเข้ากระเป๋าหลังอนุมัติ']]; ?>
            <?php foreach ($steps as [$num, $title, $desc]): ?>
            <div style="display:flex;gap:12px;margin-bottom:14px;align-items:flex-start">
                <div style="width:28px;height:28px;border-radius:50%;background:#1a1a2e;color:#fff;display:grid;place-items:center;font-size:12px;font-weight:800;flex-shrink:0"><?= $num ?></div>
                <div><div style="font-size:13px;font-weight:700;color:#1a1a2e"><?= $title ?></div><div style="font-size:12px;color:#6b7280"><?= $desc ?></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="toast-msg" id="toast"></div>

<script>
(function() {
    // Upload preview
    const fileInput  = document.getElementById('evidence');
    const zone       = document.getElementById('upload-zone');
    const previewWrap= document.getElementById('preview-wrap');
    const previewImg = document.getElementById('preview-img');
    const previewName= document.getElementById('preview-name');

    if (fileInput) {
        fileInput.addEventListener('change', handleFile);
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
        zone.addEventListener('drop', e => {
            e.preventDefault(); zone.classList.remove('drag');
            if (e.dataTransfer.files[0]) {
                const dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]);
                fileInput.files = dt.files; handleFile();
            }
        });
    }

    function handleFile() {
        const f = fileInput.files[0];
        if (!f) return;
        if (f.size > 5 * 1024 * 1024) { showToast('ไฟล์ต้องมีขนาดไม่เกิน 5MB', true); fileInput.value = ''; return; }
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            previewName.textContent = f.name + ' (' + (f.size / 1024).toFixed(0) + ' KB)';
            previewWrap.style.display = 'block';
        };
        reader.readAsDataURL(f);
    }

    // Form submit
    const form  = document.getElementById('return-form');
    const errDiv= document.getElementById('form-error');
    const btn   = document.getElementById('submit-btn');

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            errDiv.style.display = 'none';

            const reason   = document.getElementById('reason').value;
            const tracking = document.getElementById('return_tracking').value.trim();
            const file     = fileInput ? fileInput.files[0] : null;
            const msgs = [];
            if (!reason)   msgs.push('กรุณาเลือกสาเหตุการคืนสินค้า');
            if (!file)     msgs.push('กรุณาแนบรูปหลักฐาน');
            if (!tracking) msgs.push('กรุณากรอกเลขพัสดุที่ส่งคืน');

            if (msgs.length) {
                errDiv.textContent = msgs.join(' · ');
                errDiv.style.display = 'flex';
                errDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            btn.disabled = true;
            btn.textContent = '⏳ กำลังส่งคำขอ…';

            const fd = new FormData(form);
            try {
                const resp = await fetch('/api/submit_return_request.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.ok) {
                    showToast('✅ ' + data.message);
                    setTimeout(() => { window.location.href = '/pages/orders.php'; }, 2000);
                } else {
                    errDiv.textContent = data.message || 'เกิดข้อผิดพลาด';
                    errDiv.style.display = 'flex';
                    btn.disabled = false;
                    btn.textContent = '✅ ยืนยันการขอคืนสินค้า';
                }
            } catch (err) {
                errDiv.textContent = 'เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ กรุณาลองใหม่';
                errDiv.style.display = 'flex';
                btn.disabled = false;
                btn.textContent = '✅ ยืนยันการขอคืนสินค้า';
            }
        });
    }

    function showToast(msg, isError) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.style.background = isError ? '#dc2626' : '#1a1a2e';
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3000);
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
