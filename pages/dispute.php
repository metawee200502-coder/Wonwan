<?php
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    die("Invalid Order ID");
}

// Check if order exists and belongs to user
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found or unauthorized");
}

// Check if already disputed
$stmtCheck = $pdo->prepare("SELECT * FROM disputes WHERE order_id = ? AND user_id = ?");
$stmtCheck->execute([$order_id, $user_id]);
if ($stmtCheck->fetch()) {
    $error = "คุณได้เปิดข้อพิพาทสำหรับออเดอร์นี้ไปแล้ว";
} elseif ($order['payout_status'] !== 'pending') {
    $error = "ออเดอร์นี้ไม่สามารถเปิดข้อพิพาทได้แล้ว (สถานะปัจจุบัน: " . $order['payout_status'] . ")";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $reason = trim($_POST['reason'] ?? '');
    
    // Handle image upload
    $proof_image = null;
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/disputes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename = uniqid('dispute_') . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $destination)) {
                $proof_image = '/uploads/disputes/' . $filename;
            }
        }
    }

    if (empty($reason)) {
        $submit_error = "กรุณากรอกสาเหตุการเปิดข้อพิพาท";
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO disputes (order_id, user_id, reason, proof_image) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$order_id, $user_id, $reason, $proof_image]);
        
        $stmtUpdate = $pdo->prepare("UPDATE orders SET payout_status = 'disputed' WHERE order_id = ?");
        $stmtUpdate->execute([$order_id]);
        
        header("Location: order_details.php?id=" . $order_id);
        exit;
    }
}

$breadcrumbs = [
    ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'],
    ['label' => 'การซื้อของฉัน', 'url' => '/pages/orders.php'],
    ['label' => 'เปิดข้อพิพาท', 'url' => '']
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เปิดข้อพิพาท / ขอคืนเงิน - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        .dispute-container { max-width: 600px; margin: 40px auto; padding: 0 20px; font-family: var(--font-family); min-height: 60vh; }
        .dispute-header { font-size: 24px; color: var(--secondary-color); font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        
        .order-summary { background: #fdfdfd; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .order-summary h3 { margin: 0 0 10px; font-size: 16px; color: var(--text-dark); }
        .order-summary p { margin: 0; font-size: 14px; color: var(--text-muted); line-height: 1.6; }

        .dispute-form { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 14px; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: var(--primary-color); }
        textarea.form-control { resize: vertical; min-height: 120px; }
        
        .btn-submit { display: block; width: 100%; background: #e8546a; color: #fff; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; text-align: center; }
        .btn-submit:hover { background: #c84155; }
        
        .alert-error { background: #f8d7da; color: #721c24; padding: 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .btn-back { display: inline-block; margin-top: 10px; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 500; }
        .btn-back:hover { color: var(--text-dark); text-decoration: underline; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/header.php'; ?>
    <main class="dispute-container">
        <h1 class="dispute-header">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            เปิดข้อพิพาท / ขอคืนเงิน
        </h1>

        <div class="order-summary">
            <h3>ออเดอร์: <?= htmlspecialchars($order['order_number']) ?></h3>
            <p>
                ยอดรวมที่ชำระ: ฿<?= number_format($order['total_amount'], 2) ?><br>
                สถานะการจัดส่ง: <?= htmlspecialchars($order['shipping_status']) ?>
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <a href="order_details.php?id=<?= $order_id ?>" class="btn-back">กลับไปหน้าออเดอร์</a>
        <?php else: ?>
            <?php if (!empty($submit_error)): ?>
                <div class="alert-error"><?= htmlspecialchars($submit_error) ?></div>
            <?php endif; ?>

            <form class="dispute-form" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">สาเหตุที่ขอคืนเงิน / ข้อพิพาท <span style="color:#e8546a">*</span></label>
                    <textarea name="reason" class="form-control" placeholder="อธิบายปัญหาที่เกิดขึ้น เช่น สินค้าไม่ตรงกับที่โพสต์ไว้, มีตำหนิที่ไม่แจ้ง, ไม่ได้รับสินค้า..." required><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">รูปภาพหลักฐานประกอบ (ถ้ามี)</label>
                    <input type="file" name="proof_image" class="form-control" accept="image/jpeg, image/png, image/webp" style="padding: 9px;">
                    <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">รองรับไฟล์รูปภาพ JPG, PNG, WEBP (อัปโหลดได้ 1 รูป)</div>
                </div>

                <button type="submit" class="btn-submit">ยืนยันเปิดข้อพิพาท</button>
            </form>
        <?php endif; ?>
    </main>
    <?php require_once __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
