<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) { header('Location:/pages/login.php'); exit; }

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) { header('Location:/pages/orders.php'); exit; }

$formError = '';
$formSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['csrf_token']) && hash_equals($_SESSION['return_request_csrf'] ?? '', $_POST['csrf_token'])) {
        $reason = trim($_POST['reason'] ?? '');
        $returnTracking = trim($_POST['return_tracking'] ?? '');
        
        $hasFile = isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK;
        if (!$reason || !$returnTracking || !$hasFile) {
            $formError = 'กรุณากรอกข้อมูลให้ครบถ้วน และแนบรูปภาพหลักฐาน';
        } else {
            $checkStmt = $pdo->prepare('SELECT return_id FROM return_requests WHERE order_id = ? LIMIT 1');
            $checkStmt->execute([$orderId]);
            if ($checkStmt->fetch()) {
                $formError = 'คำสั่งซื้อนี้มีการขอคืนสินค้าแล้ว';
            } else {
                $orderStmt = $pdo->prepare("SELECT o.shipping_status, o.shipping_fee FROM orders o WHERE o.order_id = ? AND o.user_id = ? LIMIT 1");
                $orderStmt->execute([$orderId, $userId]);
                $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);

                if (!$orderData || $orderData['shipping_status'] !== 'delivered') {
                    $formError = 'สถานะคำสั่งซื้อไม่ถูกต้องสำหรับการขอคืนสินค้า';
                } else {
                    $itemsStmt = $pdo->prepare("SELECT oi.price_at_purchase, oi.quantity, b.seller_type, b.seller_id FROM order_items oi JOIN books b ON b.book_id = oi.book_id WHERE oi.order_id = ?");
                    $itemsStmt->execute([$orderId]);
                    $itemsData = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($itemsData) {
                        $seller = $itemsData[0];
                        $refundAmount = array_sum(array_map(fn($i) => (float)$i['price_at_purchase'] * (int)$i['quantity'], $itemsData)) + (float)$orderData['shipping_fee'];

                        // File validation
                        $file = $_FILES['evidence'];
                        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime  = $finfo->file($file['tmp_name']);
                        $allowedExt  = ['jpg','jpeg','png','webp'];
                        $allowedMime = ['image/jpeg','image/png','image/webp'];

                        if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
                            $formError = 'รองรับเฉพาะไฟล์ JPG, PNG หรือ WebP';
                        } elseif ($file['size'] > 5 * 1024 * 1024) {
                            $formError = 'ขนาดไฟล์ต้องไม่เกิน 5MB';
                        } else {
                                $uploadDir = __DIR__ . '/uploads/returns/';
                                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                                $filename    = uniqid('rt_', true) . '.' . $ext;
                                $destination = $uploadDir . $filename;
                                
                                if (move_uploaded_file($file['tmp_name'], $destination)) {
                                    $evidenceUrl = 'uploads/returns/' . $filename;
                                    $pdo->beginTransaction();
                                    try {
                                        // v2 flow: status = 'pending_super_admin'
                                        $insertStmt = $pdo->prepare("INSERT INTO return_requests (order_id, user_id, seller_type, seller_id, reason, evidence_url, return_tracking, refund_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_super_admin')");
                                        $insertStmt->execute([$orderId, $userId, $seller['seller_type'], $seller['seller_id'], $reason, $evidenceUrl, $returnTracking, $refundAmount]);
                                        $newReturnId = (int)$pdo->lastInsertId();

                                        // v2 flow: notify super_admin (ไม่ใช่ร้านค้า)
                                        $saStmt = $pdo->query("SELECT user_id FROM users WHERE role = 'super_admin' LIMIT 20");
                                        foreach ($saStmt->fetchAll(PDO::FETCH_COLUMN) as $saId) {
                                            wanawanNotifyUser($pdo, (int)$saId, '📦 คำขอคืนสินค้าใหม่ รอการพิจารณา', 'ลูกค้ายื่นขอคืนสินค้าสำหรับคำสั่งซื้อ #' . $orderId . ' — กรุณาตรวจสอบหลักฐาน', 'order', ['related_order_id' => $orderId, 'related_return_id' => $newReturnId]);
                                        }
                                        $pdo->commit();
                                        echo "<script>window.parent.postMessage('reload_orders', '*');</script>";
                                        exit;
                                    } catch (Throwable $e) {
                                        $pdo->rollBack();
                                        @unlink($destination);
                                        $formError = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่';
                                    }
                                } else {
                                    $formError = 'อัปโหลดไฟล์ไม่สำเร็จ';
                                }
                        }
                    }
                }
            }
        }
    }
}

// Check order eligibility
$orderStmt = $pdo->prepare("SELECT o.order_id, o.order_number, o.shipping_status, o.shipping_fee FROM orders o WHERE o.order_id = ? AND o.user_id = ? LIMIT 1");
$orderStmt->execute([$orderId, $userId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order || $order['shipping_status'] !== 'delivered') {
    header('Location:/pages/orders.php?error=not_eligible'); exit;
}

// Check if request already exists
$existStmt = $pdo->prepare('SELECT return_id, status FROM return_requests WHERE order_id = ? LIMIT 1');
$existStmt->execute([$orderId]);
$existing = $existStmt->fetch(PDO::FETCH_ASSOC);

// Get items and seller info
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

$subtotal = array_sum(array_map(fn($i) => (float)$i['price_at_purchase'] * (int)$i['quantity'], $items));
$totalRefund = $subtotal + (float)$order['shipping_fee'];

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
    <title>คืนสินค้า #<?= htmlspecialchars($order['order_number']) ?> - WANAWAN</title>
    <meta name="description" content="แบบฟอร์มขอคืนสินค้าสำหรับคำสั่งซื้อ #<?= htmlspecialchars($order['order_number']) ?>">
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex; align-items: flex-start; justify-content: center;
            z-index: 9999; padding: 40px 20px;
            backdrop-filter: blur(5px);
            overflow-y: auto;
        }
        .modal-content {
            background: var(--bg-color);
            border-radius: 20px;
            width: 100%; max-width: 900px;
            position: relative;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin: auto;
            animation: modalFadeIn 0.3s ease-out;
        }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-close {
            position: absolute; top: 20px; right: 20px;
            background: #fff; width: 36px; height: 36px; border-radius: 50%;
            display: grid; place-items: center; color: var(--text-dark);
            text-decoration: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: 0.2s; z-index: 10;
        }
        .modal-close:hover { transform: scale(1.05); color: var(--accent-red); }
        .modal-header-title {
            padding: 24px 32px 0;
            text-align: center;
        }
        .modal-header-title h2 { color: var(--secondary-color); font-size: 24px; font-weight: 700; margin-bottom: 4px; }
        .modal-header-title p { color: var(--text-muted); font-size: 14px; }
        
        .rr-wrap { padding: 24px 32px 40px; }
        body { background: transparent; }
        
        /* Apply colors from styles.css */
        .card { background: var(--card-bg); border-color: var(--border-color); }
        .card-title { color: var(--secondary-color); border-bottom-color: var(--border-color); }
        .card-title .icon { color: var(--primary-color); background: var(--accent-pink-light) !important; }
        .item-title { color: var(--text-dark); }
        .item-price { color: var(--secondary-color); }
        .addr-box { background: var(--card-peach); border-color: var(--border-color); }
        .addr-box .addr-warning { color: var(--accent-red); }
        .addr-box .addr-title { color: var(--text-dark); }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(201,123,139,.15); }
        .upload-zone { border-color: var(--primary-color); background: #fff; }
        .upload-zone:hover, .upload-zone.drag { border-color: var(--primary-hover); background: var(--accent-pink-light); }
        .upload-zone .upload-icon { color: var(--primary-color); }
        .btn-submit { background: var(--primary-color); color: #fff; }
        .btn-submit:hover:not(:disabled) { background: var(--primary-hover); box-shadow: 0 6px 15px rgba(201,123,139,.3); }
        .already-card { background: var(--card-peach); border-color: var(--border-color); }
        .already-icon { color: var(--primary-color); }
        .already-title { color: var(--secondary-color); }
        .btn-back { background: var(--card-bg); border-color: var(--border-color); color: var(--secondary-color); }
        .btn-back:hover { background: var(--bg-color); border-color: var(--primary-color); color: var(--primary-color); }
        
        .rr-wrap {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        @media (max-width: 900px) {
            .rr-wrap { grid-template-columns: 1fr; }
        }
        .card { padding: 24px; border: 1px solid var(--border-color); border-radius: 16px; margin-bottom: 24px; }
        .card-title {
            display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 18px; margin-bottom: 20px;
            padding-bottom: 12px; border-bottom: 1px solid var(--border-color);
        }
        .card-title .icon {
            width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; font-size: 16px;
        }
        
        .item-row { display: flex; gap: 16px; padding: 16px 0; border-bottom: 1px dashed var(--border-color); }
        .item-row:last-child { border-bottom: none; padding-bottom: 0; }
        .item-img { width: 70px; height: 95px; border-radius: 8px; object-fit: cover; border: 1px solid rgba(0,0,0,0.05); }
        .item-info { flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .item-title { font-weight: 500; font-size: 15px; margin-bottom: 4px; line-height: 1.4; }
        .item-author { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; }
        .item-price { font-weight: 600; font-size: 15px; }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: var(--text-dark); }
        .form-label .req { color: var(--accent-red); }
        .form-control {
            width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 10px;
            font-size: 15px; font-family: inherit; transition: 0.2s; outline: none; background: #fff;
        }
        select.form-control { cursor: pointer; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill="none" stroke="%236b7280" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>'); background-repeat: no-referrer; background-position: right 16px center; background-size: 16px; padding-right: 40px; }
        
        .upload-zone {
            border: 2px dashed var(--border-color); border-radius: 12px; padding: 32px 20px; text-align: center;
            cursor: pointer; transition: 0.2s; position: relative;
        }
        .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .upload-icon { font-size: 32px; margin-bottom: 12px; transition: transform 0.2s; }
        .upload-zone:hover .upload-icon { transform: translateY(-4px); }
        .upload-text { font-size: 15px; font-weight: 500; color: var(--text-dark); margin-bottom: 4px; }
        .upload-hint { font-size: 13px; color: var(--text-muted); }
        .upload-preview { display: none; margin-top: 16px; position: relative; border-radius: 10px; overflow: hidden; border: 1px solid var(--border-color); background: #f8fafc; padding: 8px; }
        .upload-preview img { width: 100%; max-height: 200px; object-fit: contain; border-radius: 6px; }
        
        .btn-submit {
            width: 100%; padding: 14px; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; font-family: inherit;
            cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 8px;
        }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .summary-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 12px; color: var(--text-muted); }
        .summary-total {
            display: flex; justify-content: space-between; align-items: center; padding-top: 16px; margin-top: 16px;
            border-top: 1px solid var(--border-color); font-weight: 700; font-size: 16px; color: var(--text-dark);
        }
        .summary-total .amount { font-size: 22px; color: var(--primary-color); }
        
        .addr-box {
            padding: 16px 20px; border-radius: 12px; border: 1px solid rgba(219,39,119,0.1); font-size: 14px; line-height: 1.5;
        }
        .addr-warning { display: flex; align-items: center; font-weight: 600; margin-bottom: 12px; }
        .addr-title { font-weight: 600; font-size: 15px; margin-bottom: 8px; }
        .addr-row { color: var(--text-muted); }
        .addr-row span { color: var(--text-dark); font-weight: 500; }
        .no-addr { text-align: center; padding: 24px; color: var(--text-muted); font-size: 14px; border-radius: 12px; background: #f9fafb; border: 1px dashed var(--border-color); }
        
        .already-card { text-align: center; padding: 60px 30px; border-radius: 20px; margin: 40px auto; max-width: 500px; }
        .already-icon { font-size: 64px; margin-bottom: 24px; animation: bounce 2s infinite; }
        @keyframes bounce { 0%, 20%, 50%, 80%, 100% {transform: translateY(0);} 40% {transform: translateY(-15px);} 60% {transform: translateY(-7px);} }
        .already-title { font-size: 24px; font-weight: 700; margin-bottom: 12px; }
        .already-sub { font-size: 15px; color: var(--text-muted); margin-bottom: 32px; line-height: 1.6; }
        .btn-back { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 24px; border-radius: 10px; font-weight: 500; text-decoration: none; border: 1px solid; transition: 0.2s; }
        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; margin-top: 16px; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #dcfce7; color: #16a34a; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
<div class="modal-overlay">
  <div class="modal-content">
      <a href="#" class="modal-close" onclick="event.preventDefault(); window.parent.postMessage('close_rr_modal', '*');">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      </a>
      <div class="modal-header-title">
          <h2>คืนสินค้า / ขอเงินคืน</h2>
      </div>
      
<?php if ($existing): ?>
<!-- สถานะการขอคืนสินค้าที่มีอยู่แล้ว -->
<div class="rr-wrap" style="grid-template-columns:1fr">
    <div class="card already-card">
        <?php
        $icons   = ['pending' => '⏳', 'approved' => '✅', 'rejected' => '❌'];
        $labels  = ['pending' => 'รอการอนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ปฏิเสธแล้ว'];
        $classes = ['pending' => 'status-pending', 'approved' => 'status-approved', 'rejected' => 'status-rejected'];
        $st = $existing['status'];
        ?>
        <div class="already-icon"><?= $icons[$st] ?? '⏳' ?></div>
        <div class="already-title">สถานะการคืนสินค้า #<?= htmlspecialchars($order['order_number']) ?></div>
        <div class="already-sub">คุณได้ส่งคำขอคืนสินค้าสำหรับคำสั่งซื้อนี้แล้ว<br>กรุณารอการพิจารณาจากร้านค้าหรือผู้ดูแลระบบ</div>
        <div class="status-badge <?= $classes[$st] ?? '' ?>"><?= $icons[$st] ?? '' ?> <?= $labels[$st] ?? $st ?></div>
        <br>
        <a href="#" class="btn-back" onclick="event.preventDefault(); window.parent.postMessage('close_rr_modal', '*');">กลับไปหน้าคำสั่งซื้อ</a>
    </div>
</div>
<?php else: ?>
<!-- ฟอร์มขอคืนสินค้า -->
<div class="rr-wrap">
    <div style="display:flex;flex-direction:column;gap:24px;">

        <!-- ข้อมูลคำสั่งซื้อ -->
        <div class="card">
            <div class="card-title">
                <div class="icon" style="background:#eef2ff;color:#4f46e5"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                ข้อมูลคำสั่งซื้อ #<?= htmlspecialchars($order['order_number']) ?>
            </div>
            <?php foreach ($items as $it): ?>
                <div class="item-row">
                    <img src="<?= htmlspecialchars(returnItemImg($it['image_cover'])) ?>" alt="" class="item-img">
                    <div class="item-info">
                        <div class="item-title"><?= htmlspecialchars($it['title']) ?></div>
                        <div class="item-author"><?= htmlspecialchars($it['author'] ?? '') ?></div>
                        <div class="item-price">฿<?= number_format((float)$it['price_at_purchase'] * (int)$it['quantity'], 2) ?> (x<?= (int)$it['quantity'] ?>)</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ข้อมูลที่อยู่ผู้รับ -->
        <div class="card">
            <div class="card-title">
                <div class="icon" style="background:#fce7f3;color:#db2777"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg></div>
                ที่อยู่รับสินค้าคืน
            </div>
            <div class="alert alert-info" style="margin-bottom:16px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                กรุณาส่งคืนสินค้าตามที่อยู่นี้ เพื่อให้ร้านค้าสามารถตรวจสอบและดำเนินการคืนเงินได้
            </div>
            <?php if ($returnAddress): ?>
            <div class="addr-box">
                <div class="addr-warning"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;vertical-align:-2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ข้อมูลที่อยู่ผู้รับ</div>
                <div class="addr-title"><?= htmlspecialchars($sellerName) ?></div>
                <div class="addr-row">
                    <?php if ($contactName): ?><div>ผู้รับ: <span><?= htmlspecialchars($contactName) ?></span></div><?php endif; ?>
                    <?php if ($contactPhone): ?><div>โทร: <span><?= htmlspecialchars($contactPhone) ?></span></div><?php endif; ?>
                    <div style="margin-top:8px;white-space:pre-line"><?= htmlspecialchars($returnAddress) ?></div>
                </div>
            </div>
            <?php else: ?>
            <div class="no-addr">
                ยังไม่ได้กรอกข้อมูลที่อยู่<br>
                <small>กรุณารอ <?= htmlspecialchars($sellerName) ?> อัปเดตข้อมูลที่อยู่รับคืน</small>
            </div>
            <?php endif; ?>
        </div>

        <!-- แบบฟอร์มขอคืนสินค้า -->
        <div class="card">
            <div class="card-title">
                <div class="icon" style="background:#fff7ed;color:#ea580c"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></div>
                แบบฟอร์มขอคืนสินค้า
            </div>
            
            <form id="rr-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['return_request_csrf']) ?>">
                
                <div id="rr-err" class="alert alert-error" style="display:<?= $formError ? 'flex' : 'none' ?>;margin-bottom:20px"><?= htmlspecialchars($formError) ?></div>
                
                <div class="form-group">
                    <label class="form-label">เหตุผลการคืนสินค้า <span class="req">*</span></label>
                    <select id="reason" name="reason" class="form-control" required>
                        <option value="">- เลือกเหตุผล -</option>
                        <option value="damaged">สินค้าชำรุด/เสียหาย</option>
                        <option value="not_as_described">สินค้าไม่ตรงตามรายละเอียด</option>
                        <option value="wrong_item">ได้รับสินค้าผิด</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">หลักฐานการคืนสินค้า <span class="req">*</span></label>
                    <div class="upload-zone" id="upload-zone">
                        <input type="file" id="evidence" name="evidence" accept="image/jpeg,image/png,image/webp" required>
                        <div class="upload-icon"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></div>
                        <div class="upload-text">คลิกเพื่ออัปโหลดรูปภาพหลักฐาน</div>
                        <div class="upload-hint">JPG, PNG, WebP - ขนาดไม่เกิน 5MB</div>
                    </div>
                    <div class="upload-preview" id="preview-wrap">
                        <img id="preview-img" src="" alt="รูปภาพหลักฐาน">
                        <div style="font-size:12px;color:#6b7280;margin-top:6px;text-align:center" id="preview-name"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">หมายเลขพัสดุส่งคืน <span class="req">*</span></label>
                    <input type="text" id="return_tracking" name="return_tracking" class="form-control"
                           placeholder="เช่น TH123456789 (กรุณากรอกหลังจากส่งพัสดุแล้ว)" required maxlength="100">
                </div>

                <button type="submit" class="btn-submit" id="submit-btn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg> ส่งคำขอคืนสินค้า
                </button>
            </form>
        </div>

    </div>
    
    <div style="display:flex;flex-direction:column;gap:24px;">
        <div class="card">
            <div class="card-title">
                <div class="icon" style="background:#f0fdf4;color:#16a34a"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
                สรุปยอดเงินคืน (โดยประมาณ)
            </div>
            <?php foreach ($items as $it): ?>
            <div class="summary-row">
                <span><?= htmlspecialchars(mb_strimwidth($it['title'] ?? '-', 0, 24, '...')) ?> x<?= (int)$it['quantity'] ?></span>
                <strong>฿<?= number_format((float)$it['price_at_purchase'] * (int)$it['quantity'], 2) ?></strong>
            </div>
            <?php endforeach; ?>
            <div class="summary-row">
                <span>ค่าจัดส่ง</span>
                <strong>฿<?= number_format((float)$order['shipping_fee'], 2) ?></strong>
            </div>
            <div class="summary-total">
                <span>ยอดเงินคืนทั้งหมด</span>
                <span class="amount">฿<?= number_format($totalRefund, 2) ?></span>
            </div>
            <p style="font-size:11px;color:#9ca3af;margin-top:12px;line-height:1.6;">* ยอดเงินคืนที่แท้จริงอาจมีการเปลี่ยนแปลง ขึ้นอยู่กับการตรวจสอบสินค้าของร้านค้า</p>
        </div>

        <div class="card">
            <div class="card-title" style="margin-bottom:12px;">
                <div class="icon" style="background:#fff7ed;color:#ea580c"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div>
                ขั้นตอนการขอคืนสินค้า
            </div>
            <?php $steps = [['1','ส่งคำขอ','กรอกแบบฟอร์มและแนบหลักฐาน'],['2','ตรวจสอบ','ร้านค้าจะตรวจสอบคำขอของคุณ'],['3','ส่งคืน','ส่งสินค้าคืนตามที่อยู่ที่ระบุ'],['4','รับเงินคืน','รับเงินคืนภายใน 3-7 วันทำการ']]; ?>
            <?php foreach ($steps as [$num, $title, $desc]): ?>
            <div style="display:flex;gap:12px;margin-bottom:14px;align-items:flex-start">
                <div style="width:24px;height:24px;border-radius:50%;background:var(--secondary-color);color:#fff;display:grid;place-items:center;font-size:12px;font-weight:700;flex-shrink:0"><?= $num ?></div>
                <div>
                    <div style="font-size:14px;font-weight:600;color:var(--text-dark);margin-bottom:2px"><?= $title ?></div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

  </div>
</div>

<div id="toast" class="toast"></div>

<script>
(function(){
    function showToast(msg, isErr=false) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.style.background = isErr ? 'var(--accent-red)' : 'var(--primary-color)';
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3000);
    }
    
    // File upload preview
    const fileInput = document.getElementById('evidence');
    const wrap = document.getElementById('preview-wrap');
    const img = document.getElementById('preview-img');
    const name = document.getElementById('preview-name');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const f = fileInput.files[0];
            if (!f) return;
            if (f.size > 5 * 1024 * 1024) { showToast('ขนาดไฟล์ต้องไม่เกิน 5MB', true); fileInput.value = ''; return; }
            const reader = new FileReader();
            reader.onload = e => {
                img.src = e.target.result;
                name.textContent = f.name;
                wrap.style.display = 'block';
            };
            reader.readAsDataURL(f);
        });
    }

    const form = document.getElementById('rr-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const reason   = document.getElementById('reason').value;
            const tracking = document.getElementById('return_tracking').value.trim();
            const file     = fileInput.files[0];
            const btn      = document.getElementById('submit-btn');
            const errDiv   = document.getElementById('rr-err');
            
            errDiv.style.display = 'none';
            
            const msgs = [];
            if (!reason)   msgs.push('กรุณาเลือกเหตุผลการคืนสินค้า');
            if (!file)     msgs.push('กรุณาแนบรูปภาพหลักฐาน');
            if (!tracking) msgs.push('กรุณากรอกหมายเลขพัสดุ');
            
            if (msgs.length > 0) {
                e.preventDefault();
                errDiv.innerHTML = msgs.join('<br>');
                errDiv.style.display = 'block';
                return;
            }
            
            setTimeout(() => { btn.disabled = true; btn.textContent = 'กำลังดำเนินการ...'; }, 10);
        });
    }
})();
</script>
</body>
</html>
