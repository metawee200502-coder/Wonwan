<?php
$file = 'c:/laragon/www/wanawan_project/admin/escrow.php';
$content = '<?php
session_start();
require_once __DIR__ . \'/../config/config.php\';
if (empty($_SESSION[\'user_id\']) || $_SESSION[\'role\'] !== \'super_admin\') {
    header(\'Location: /pages/login.php\');
    exit;
}

if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\' && isset($_POST[\'action\'], $_POST[\'form_id\']) && isset($_GET[\'tab\']) && $_GET[\'tab\'] === \'p2p\') {
    if (empty($_SESSION[\'admin_escrow_csrf\']) || !hash_equals($_SESSION[\'admin_escrow_csrf\'], (string)($_POST[\'csrf_token\'] ?? \'\'))) {
        $_SESSION[\'flash_error\'] = "คำขอหมดอายุ กรุณาลองใหม่";
    } else {
        $action = $_POST[\'action\'];
        $form_id = (int)$_POST[\'form_id\'];
        if ($action === \'approve_slip\') {
            $stmt = $pdo->prepare("UPDATE trade_condition_forms SET deposit_status = \'approved\', deposit_approved_at = NOW() WHERE form_id = ?");
            $stmt->execute([$form_id]);
            $_SESSION[\'flash_msg\'] = "อนุมัติสลิปมัดจำ P2P เรียบร้อยแล้ว";
        } elseif ($action === \'reject_slip\') {
            $stmt = $pdo->prepare("UPDATE trade_condition_forms SET deposit_status = \'pending\', deposit_slip_url = NULL WHERE form_id = ?");
            $stmt->execute([$form_id]);
            $_SESSION[\'flash_msg\'] = "ปฏิเสธสลิปแล้ว ระบบจะแจ้งให้ผู้ใช้แนบใหม่";
        }
    }
    header("Location: escrow.php?tab=p2p&status=" . urlencode($_GET[\'status\'] ?? \'reviewing\'));
    exit;
}

if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\' && isset($_POST[\'action\'], $_POST[\'order_id\'])) {
    if (empty($_SESSION[\'admin_escrow_csrf\']) || !hash_equals($_SESSION[\'admin_escrow_csrf\'], (string)($_POST[\'csrf_token\'] ?? \'\'))) {
        $_SESSION[\'flash_error\'] = "คำขอหมดอายุ กรุณาลองใหม่";
    } else {
        $action = $_POST[\'action\'];
        $order_id = (int)$_POST[\'order_id\'];

        if ($action === \'approve_slip\') {
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = \'paid\', paid_at = NOW() WHERE order_id = ? AND payment_status = \'reviewing\'");
            $stmt->execute([$order_id]);
            $order_number = $pdo->query("SELECT order_number FROM orders WHERE order_id = " . (int)$order_id)->fetchColumn() ?: "#" . $order_id;
            logAdminAction($pdo, $_SESSION[\'email\'] ?? \'unknown\', \'APPROVE_SLIP\', (string)$order_number, "อนุมัติสลิปโอนเงินสำหรับออเดอร์ {$order_number}");
            $_SESSION[\'flash_msg\'] = "อนุมัติสลิปเรียบร้อยแล้ว";
        } elseif ($action === \'reject_slip\') {
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = \'pending\', payment_slip_url = NULL WHERE order_id = ? AND payment_status = \'reviewing\'");
            $stmt->execute([$order_id]);
            $order_number = $pdo->query("SELECT order_number FROM orders WHERE order_id = " . (int)$order_id)->fetchColumn() ?: "#" . $order_id;
            logAdminAction($pdo, $_SESSION[\'email\'] ?? \'unknown\', \'REJECT_SLIP\', (string)$order_number, "ปฏิเสธสลิปโอนเงินสำหรับออเดอร์ {$order_number}");
            $_SESSION[\'flash_msg\'] = "ปฏิเสธสลิปแล้ว ระบบจะแจ้งให้ลูกค้าอัปโหลดใหม่";
        }
    }
    header("Location: escrow.php?status=" . urlencode($_GET[\'status\'] ?? \'reviewing\'));
    exit;
}

if (empty($_SESSION[\'admin_escrow_csrf\'])) {
    $_SESSION[\'admin_escrow_csrf\'] = bin2hex(random_bytes(24));
}

$tab = $_GET[\'tab\'] ?? \'orders\';
$status = $_GET[\'status\'] ?? \'reviewing\';
$search = ltrim(trim($_GET[\'search\'] ?? \'\'), \'#\');

if ($tab === \'p2p\') {
    $statsQuery = $pdo->query("
        SELECT 
            COUNT(CASE WHEN deposit_status = \'pending_verification\' THEN 1 END) as reviewing_count,
            COUNT(CASE WHEN DATE(deposit_approved_at) = CURDATE() THEN 1 END) as approved_today
        FROM trade_condition_forms WHERE required_deposit_amount > 0
    ");
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

    $where = "t.required_deposit_amount > 0";
    $params = [];

    if ($status === \'pending\') {
        $where .= " AND t.deposit_status = \'pending\'";
    } elseif ($status === \'reviewing\') {
        $where .= " AND t.deposit_status = \'pending_verification\'";
    } elseif ($status === \'completed\') {
        $where .= " AND t.deposit_status = \'approved\'";
    }
    
    if ($search !== \'\') {
        $where .= " AND (u.display_name LIKE :search OR u.email LIKE :search OR t.request_id = :search)";
        $params[\'search\'] = "%$search%";
    }

    $q = $pdo->prepare("SELECT t.*, u.display_name, u.email, u.profile_image FROM trade_condition_forms t JOIN users u ON u.user_id = t.owner_id WHERE $where ORDER BY t.form_id DESC");
    $q->execute($params);
    $p2p_forms = $q->fetchAll(PDO::FETCH_ASSOC);
} else {

// Stats
$statsQuery = $pdo->query("
    SELECT 
        COUNT(CASE WHEN payment_status = \'reviewing\' THEN 1 END) as reviewing_count,
        COUNT(CASE WHEN DATE(paid_at) = CURDATE() THEN 1 END) as approved_today
    FROM orders
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Fetch orders
$where = "1=1";
$params = [];

if ($status === \'pending\') {
    $where .= " AND (o.payment_status = \'pending\' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY))";
} elseif ($status === \'reviewing\') {
    $where .= " AND o.payment_status = \'reviewing\'";
} elseif ($status === \'packing\') {
    $where .= " AND o.payment_status = \'paid\' AND o.shipping_status IN (\'pending\', \'packing\')";
} elseif ($status === \'shipped\') {
    $where .= " AND o.shipping_status = \'shipped\'";
} elseif ($status === \'completed\') {
    $where .= " AND o.shipping_status = \'delivered\'";
} elseif ($status === \'cancelled\') {
    $where .= " AND (o.payment_status = \'cancelled\' OR o.shipping_status IN (\'cancelled\', \'returned\') OR (o.payment_status = \'pending\' AND o.created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)))";
}

if ($search !== \'\') {
    $where .= " AND (o.order_number LIKE :search OR o.shipping_name LIKE :search)";
    $params[\'search\'] = "%{$search}%";
}

$q = $pdo->prepare("SELECT o.* FROM orders o WHERE $where ORDER BY o.created_at DESC");
$q->execute($params);
$orders = $q->fetchAll(PDO::FETCH_ASSOC);

// Fetch items for each order
$iq = $pdo->prepare(\'SELECT oi.*, b.title, b.author, b.image_cover FROM order_items oi JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=?\');
foreach ($orders as &$o) {
    $iq->execute([$o[\'order_id\']]);
    $o[\'items\'] = $iq->fetchAll(PDO::FETCH_ASSOC);
}
unset($o);

function adminBookImg(?string $v):string{
    if(!$v)return \'/assets/image/minilogo.png\';
    $v=str_replace(\'\\\\\',\'/\',trim($v));
    if(preg_match(\'#^https?://#i\',$v))return $v;
    $v=ltrim($v,\'/\');
    if(str_starts_with($v,\'pages/uploads/\'))return \'/\'.$v;
    if(str_starts_with($v,\'uploads/\')){
        if(is_file(__DIR__.\'/../\'.$v))return \'/\'.$v;
        if(is_file(__DIR__.\'/\'.$v))return \'/pages/\'.$v;
        return \'/\'.$v;
    }
    if(str_starts_with($v,\'assets/\'))return \'/\'.$v;
    return \'/assets/image/books/\'.$v;
}
function adminSlip(?string $v):string{return $v?\'/pages/\'.ltrim(str_replace(\'\\\\\',\'/\',$v),\'/\'):\'\';}

function orderStateLabel(array $o):array{
    if ($o[\'payment_status\'] === \'cancelled\') {
        return [\'ยกเลิก (ไม่ได้ชำระเงิน)\', \'cancelled\'];
    }
    if ($o[\'shipping_status\'] === \'returned\') {
        return [\'คืนสินค้า / คืนเงิน\', \'cancelled\'];
    }
    if ($o[\'shipping_status\'] === \'cancelled\') {
        return [\'ยกเลิก (ชำระเงินแล้ว)\', \'cancelled\'];
    }
    
    // Auto cancel logic (24 hrs)
    if ($o[\'payment_status\'] === \'pending\' && strtotime($o[\'created_at\']) < time() - 86400) {
        return [\'ยกเลิก (หมดเวลา)\', \'cancelled\'];
    }

    if ($o[\'payment_status\'] === \'reviewing\') return [\'รอตรวจสอบสลิป\', \'reviewing\'];
    if ($o[\'payment_status\'] === \'paid\') {
        if ($o[\'shipping_status\'] === \'delivered\') return [\'ลูกค้ายืนยัน / สำเร็จ\', \'completed\'];
        if ($o[\'shipping_status\'] === \'shipped\') return [\'ร้านส่งแล้ว\', \'shipped\'];
        return [\'รอร้านส่งของ\', \'paid\'];
    }
    return [\'รอชำระเงิน\', \'pending\'];
}

} // End else
$active_menu = \'escrow\';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการคำสั่งซื้อ - วนวรรณ (Admin)</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        body { background: #fff; margin: 0; display: flex; min-height: 100vh; }
        .admin-main-wrap { flex: 1; min-width: 0; padding: 40px 50px; }
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
        .head h1 { color: var(--secondary-color); font-size: 30px; margin: 0; }
        
        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat { border: 1px solid #dfbdc8; border-radius: 14px; padding: 25px; }
        .stat strong { display: block; color: #d88fac; font-size: 28px; margin-top: 5px; }
        
        .notice { padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; }
        .ok { background: #e9f5e5; color: #39703a; }
        .bad { background: #fde5e1; color: #a33; }
        
        .toolbar { display: flex; flex-direction: column; gap: 15px; padding: 18px; border: 1px solid #e3c3ce; border-bottom: 0; border-radius: 14px 14px 0 0; }
        .toolbar-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .toolbar h3 { margin: 0; color: #514348; }
        .filters { display: flex; flex-wrap: wrap; gap: 5px; }
        .filters a { display: inline-block; padding: 8px 13px; text-decoration: none; color: #765d66; border-radius: 18px; font-size: 14px; }
        .filters a:hover { background: #f9f5f6; }
        .filters a.active { background: #d18aa7; color: #fff; }
        
        .tablebox { overflow: auto; border: 1px solid #e3c3ce; border-radius: 0 0 14px 14px; }
        .orders { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .orders th, .orders td { padding: 15px; border-bottom: 1px solid #ead6dd; text-align: left; vertical-align: top; }
        .orders th { background: #fafafa; font-weight: 600; color: #555; }
        
        .book { display: flex; align-items: flex-start; gap: 10px; }
        .book img { width: 45px; height: 62px; object-fit: cover; border-radius: 5px; background: #eee; }
        
        .badge { padding: 6px 12px; border-radius: 18px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; text-align: center; }
        .badge-reviewing { background: #fff0cf; color: #9b6b13; }
        .badge-shipped { background: #f3e5f5; color: #8e24aa; }
        .badge-paid { background: #e3f2fd; color: #1976d2; }
        .badge-completed { background: #e9f5e5; color: #39703a; }
        .badge-cancelled { background: #fde5e1; color: #c54848; }
        .badge-pending { background: #f3f4f6; color: #4b5563; }
        
        .btn { border: 0; border-radius: 8px; padding: 9px 13px; background: var(--secondary-color); color: #fff; cursor: pointer; text-decoration: none; font-family: inherit; }
        
        /* Modal Styles */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 500; backdrop-filter: blur(3px); }
        .modal.open { display: flex; }
        .modalbox { background: #fffaf8; border-radius: 22px; width: min(850px, 100%); max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 80px rgba(69,42,47,0.28); display: flex; flex-direction: column; scrollbar-width: none; -ms-overflow-style: none; }
        .modalbox::-webkit-scrollbar { display: none; }
        .modalbox h2 { margin: 0; padding: 24px 30px; background: linear-gradient(135deg, #f8dce6, #fceee8); color: var(--secondary-color); font-size: 25px; border-bottom: 1px solid #e8cbd5; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { display: grid; grid-template-columns: 1fr 1fr; }
        .modal-body section { padding: 28px 30px; }
        .modal-body section + section { background: #fff; border-left: 1px solid #ead6dd; }
        
        .slip-view { width: 100%; height: 100%; min-height: 300px; max-height: 500px; object-fit: contain; border: 1px dashed #d7b8c3; border-radius: 12px; background: #f9f5f3; cursor: pointer; transition: transform 0.2s; }
        .slip-view:hover { transform: scale(1.02); }
        .no-slip { display: flex; align-items: center; justify-content: center; height: 300px; color: #9a8b90; background: #f8f4f2; border: 1px dashed #d7b8c3; border-radius: 12px; text-align: center; padding: 20px; line-height: 1.5; }
        
        #mItems { scrollbar-width: none; -ms-overflow-style: none; }
        #mItems::-webkit-scrollbar { display: none; }
        #mItems p { display: flex; justify-content: space-between; gap: 10px; margin: 0 0 10px; padding: 10px 12px; background: #fff; border: 1px solid #eadce0; border-radius: 8px; color: #514348; font-size: 14px; }
        
        .actions { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 10px; }
        .btn-approve { background: #2e7d32; flex: 1; padding: 12px; font-weight: bold; font-size: 15px; }
        .btn-reject { background: #fff; color: #d32f2f; border: 1px solid #d32f2f; flex: 1; padding: 12px; font-weight: bold; font-size: 15px; }
        .btn-reject:hover { background: #ffebee; }
        
        @media(max-width: 768px) {
            .modal-body { grid-template-columns: 1fr; }
            .modal-body section + section { border-left: 0; border-top: 1px solid #ead6dd; }
            .stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . \'/../components/admin_sidebar.php\'; ?>
    <div class="admin-main-wrap">
        <div class="head" style="margin-bottom:15px;">
            <h1>จัดการการชำระเงิน / มัดจำ</h1>
        </div>
        <div style="margin-bottom:28px; display:flex; gap:10px;">
            <a href="escrow.php" class="btn" style="<?= $tab !== \'p2p\' ? \'background:#91576d;color:#fff;\' : \'background:#eee;color:#555;\' ?>">คำสั่งซื้อปกติ</a>
            <a href="escrow.php?tab=p2p" class="btn" style="<?= $tab === \'p2p\' ? \'background:#91576d;color:#fff;\' : \'background:#eee;color:#555;\' ?>">เงินมัดจำแลกเปลี่ยน (P2P)</a>
        </div>

        <div class="stats">
            <div class="stat">รอตรวจสอบสลิป (รายการ)<strong><?= number_format($stats[\'reviewing_count\'] ?? 0) ?></strong></div>
            <div class="stat">อนุมัติแล้ววันนี้ (รายการ)<strong><?= number_format($stats[\'approved_today\'] ?? 0) ?></strong></div>
        </div>

        <?php if(isset($_SESSION[\'flash_msg\'])): ?>
            <div class="notice ok"><?= htmlspecialchars($_SESSION[\'flash_msg\']) ?></div>
            <?php unset($_SESSION[\'flash_msg\']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION[\'flash_error\'])): ?>
            <div class="notice bad"><?= htmlspecialchars($_SESSION[\'flash_error\']) ?></div>
            <?php unset($_SESSION[\'flash_error\']); ?>
        <?php endif; ?>

        <div class="toolbar">
            <div class="toolbar-top">
                <h3>รายการคำสั่งซื้อทั้งหมด</h3>
                <form method="get" style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาเลขคำสั่งซื้อ, ชื่อลูกค้า..." style="padding:8px 12px;border:1px solid #d8c9c4;border-radius:8px;outline:none;font-size:13px;width:240px">
                    <button type="submit" class="btn" style="padding:8px 15px;min-height:0">ค้นหา</button>
                    <a href="?status=<?= htmlspecialchars($status) ?>" class="btn" style="padding:8px 15px;min-height:0;background:#f3f4f6;color:#555;border:1px solid #ddd;">ล้าง</a>
                </form>
            </div>
            <nav class="filters">
                <a class="<?= $status === \'all\' ? \'active\' : \'\' ?>" href="?status=all<?= $search ? \'&search=\'.urlencode($search) : \'\' ?>">ทั้งหมด</a>
                <a class="<?= $status === \'pending\' ? \'active\' : \'\' ?>" href="?status=pending<?= $search ? \'&search=\'.urlencode($search) : \'\' ?>">รอชำระเงิน</a>
                <a class="<?= $status === \'reviewing\' ? \'active\' : \'\' ?>" href="?status=reviewing<?= $search ? \'&search=\'.urlencode($search) : \'\' ?>">รอตรวจสอบสลิป</a>
                <a class="<?= $status === \'packing\' ? \'active\' : \'\' ?>" href="?status=packing<?= $search ? \'&search=\'.urlencode($search) : \'\' ?>">รอร้านส่งของ</a>
                <a class="<?= $status === \'shipped\' ? \'active\' : \'\' ?>" href="?status=shipped<?= $search ? \'&search=\'.urlencode($search) : \'\' ?>">ร้านส่งแล้ว</a>
                <a class="<?= $status === \'completed\' ? \'active\' : \'\' ?>" href="?status=completed<?= $search ? \'&search=\'.urlencode($search) : \'\' ?>">ส่งสำเร็จ / ลูกค้ายืนยัน</a>
                <a class="<?= $status === \'cancelled\' ? \'active\' : \'\' ?>" href="?status=cancelled<?= $search ? \'&search=\'.urlencode($search) : \'\' ?>">ยกเลิก / คืนเงิน</a>
            </nav>
        </div>
        
        <div class="tablebox">
            <table class="orders">
                <?php if ($tab === \'p2p\'): ?>
                <thead>
                    <tr>
                        <th>รหัสคำขอแลกเปลี่ยน</th>
                        <th>ผู้ใช้งาน</th>
                        <th>ยอดมัดจำที่ต้องชำระ</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($p2p_forms)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:70px;color:#888;">ไม่พบรายการในหมวดนี้</td></tr>
                    <?php else: foreach ($p2p_forms as $p2p): 
                        $statusText = \'รอตรวจสอบสลิป\';
                        $statusClass = \'reviewing\';
                        if ($p2p[\'deposit_status\'] === \'approved\') { $statusText = \'ตรวจสอบผ่านแล้ว\'; $statusClass = \'completed\'; }
                        elseif ($p2p[\'deposit_status\'] === \'pending\') { $statusText = \'รอผู้ใช้ชำระเงิน\'; $statusClass = \'pending\'; }
                    ?>
                        <tr>
                            <td>
                                <strong>REQ-#<?= htmlspecialchars($p2p[\'request_id\']) ?></strong><br>
                                <span style="font-size:12px; color:#888;">Form ID: <?= $p2p[\'form_id\'] ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($p2p[\'display_name\'] ?: $p2p[\'email\']) ?></strong>
                            </td>
                            <td>
                                <strong style="color:#c53030;">฿<?= number_format($p2p[\'required_deposit_amount\'], 2) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-<?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                            <td>
                                <button class="btn detail-p2p" data-form=\'<?= htmlspecialchars(json_encode($p2p, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>\' <?= $p2p[\'deposit_status\'] === \'pending_verification\' ? \'style="background:#f0a500; color:#fff;"\' : \'style="background:#fff; color:#91576d; border:1px solid #91576d;"\' ?>>
                                    <?= $p2p[\'deposit_status\'] === \'pending_verification\' ? \'ตรวจสอบสลิป\' : \'ดูรายละเอียด\' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php else: ?>
                <thead>
                    <tr>
                        <th>รหัสคำสั่งซื้อ</th>
                        <th>รายละเอียด</th>
                        <th>ลูกค้า</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="5" style="text-align:center;padding:70px;color:#888;">ไม่พบคำสั่งซื้อในหมวดนี้</td></tr>
                    <?php else: foreach ($orders as $o): 
                        $first = $o[\'items\'][0] ?? [];
                        list($statusText, $statusClass) = orderStateLabel($o);
                        $orderData = [
                            \'id\' => $o[\'order_id\'],
                            \'number\' => $o[\'order_number\'],
                            \'date\' => $o[\'created_at\'],
                            \'paid_at\' => $o[\'paid_at\'],
                            \'status\' => $o[\'payment_status\'],
                            \'shipping_status\' => $o[\'shipping_status\'],
                            \'buyer\' => $o[\'shipping_name\'],
                            \'phone\' => $o[\'shipping_phone\'],
                            \'address\' => $o[\'shipping_address\'],
                            \'courier\' => $o[\'shipping_courier\'],
                            \'tracking\' => $o[\'tracking_number\'],
                            \'items\' => array_map(function($i) {
                                return [
                                    \'title\' => $i[\'title\'] ?: \'หนังสือ\',
                                    \'qty\' => $i[\'quantity\'],
                                    \'price\' => (float)$i[\'price_at_purchase\']
                                ];
                            }, $o[\'items\']),
                            \'subtotal\' => (float)$o[\'subtotal\'],
                            \'discount\' => (float)$o[\'discount_amount\'],
                            \'shipping\' => (float)$o[\'shipping_fee\'],
                            \'total\' => (float)$o[\'total_amount\'],
                            \'slip\' => adminSlip($o[\'payment_slip_url\'] ?? null)
                        ];
                    ?>
                        <tr>
                            <td>
                                <strong>#<?= htmlspecialchars($o[\'order_number\']) ?></strong><br>
                                <span style="font-size:12px; color:#888;"><?= date(\'d/m/Y H:i\', strtotime($o[\'created_at\'])) ?></span>
                            </td>
                            <td>
                                <div class="book">
                                    <img src="<?= htmlspecialchars(adminBookImg($first[\'image_cover\'] ?? null)) ?>">
                                    <div>
                                        <strong><?= htmlspecialchars($first[\'title\'] ?? \'ไม่ระบุ\') ?></strong><br>
                                        <small><?= count($o[\'items\']) ?> รายการ · ฿<?= number_format($o[\'total_amount\'], 2) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($o[\'shipping_name\']) ?></strong><br>
                                <small><?= htmlspecialchars($o[\'shipping_phone\']) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                            <td>
                                <button class="btn detail" data-order=\'<?= htmlspecialchars(json_encode($orderData, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>\' <?= $o[\'payment_status\'] === \'reviewing\' ? \'style="background:#f0a500; color:#fff;"\' : \'style="background:#fff; color:#91576d; border:1px solid #91576d;"\' ?>>
                                    <?= $o[\'payment_status\'] === \'reviewing\' ? \'ตรวจสอบสลิป\' : \'ดูรายละเอียด\' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal" id="modal">
        <div class="modalbox">
            <h2>
                <span id="mNumber"></span>
                <span id="mStatusBadge" class="badge"></span>
            </h2>
            <div class="modal-body">
                <!-- Left: Slip Image -->
                <section style="background: #fdfbfb;">
                    <h3 style="color:var(--secondary-color); margin:0 0 15px; font-size:16px;">หลักฐานการชำระเงิน</h3>
                    <div id="mSlipContainer"></div>
                </section>
                
                <!-- Right: Details -->
                <section>
                    <div style="font-size: 13px; color: #777; margin-bottom: 15px;">
                        <strong>สั่งซื้อเมื่อ:</strong> <span id="mDate"></span><br>
                        <strong>อนุมัติเมื่อ:</strong> <span id="mPaidDate"></span>
                    </div>
                    
                    <h3 style="color:var(--secondary-color); margin:0 0 10px; font-size:15px;">รายการสินค้า</h3>
                    <div id="mItems" style="max-height:180px; overflow-y:auto; margin-bottom:15px; padding-right:5px;"></div>
                    
                    <div style="background:#f9f9f9; padding: 15px; border-radius: 8px; font-size: 14px; margin-bottom: 15px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px">
                            <span>รวมค่าสินค้า</span><strong id="mSubtotal"></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px">
                            <span>ส่วนลด</span><strong id="mDiscount" style="color: #2e7d32;"></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:12px; padding-bottom: 8px; border-bottom: 1px dashed #ccc;">
                            <span>ค่าจัดส่ง</span><strong id="mShippingFee"></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size: 16px;">
                            <strong>ยอดรวมสุทธิ</strong><strong id="mTotal" style="color:var(--secondary-color)"></strong>
                        </div>
                    </div>
                    
                    <div id="mShippingInfo" style="display:none; background:#e8f4fd; padding:15px; border-radius:8px; font-size:14px; border:1px solid #b6defc;">
                        <h4 style="margin: 0 0 8px; color: #1976d2;">ข้อมูลการจัดส่ง</h4>
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px">
                            <span>บริษัทขนส่ง:</span><strong id="mCourier"></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span>เลขพัสดุ:</span><strong id="mTracking" style="color:#d32f2f"></strong>
                        </div>
                    </div>
                    
                    <div id="mActions" class="actions"></div>
                </section>
            </div>
            <div style="padding: 0 30px 25px;">
                <button class="btn" id="close" style="width:100%; background:#fff; color:#91576d; border:1px solid #e3c3ce; padding: 12px;">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
        const token = <?= json_encode($_SESSION[\'admin_escrow_csrf\']) ?>;
        const modal = document.getElementById(\'modal\');
        const esc = s => String(s ?? \'\').replace(/[&<>"\']/g, c => ({\'&\':\'&amp;\',\'<\':\'&lt;\',\'>\':\'&gt;\',\'"\':\'&quot;\',"\'":\'&#39;\'}[c]));
        
        function getActionForm(id) {
            return `
                <form method="post" style="display:flex; width:100%; gap:10px;" onsubmit="return confirm(\'ยืนยันการดำเนินการนี้?\');">
                    <input type="hidden" name="csrf_token" value="${token}">
                    <input type="hidden" name="order_id" value="${id}">
                    <button type="submit" class="btn btn-reject" name="action" value="reject_slip">ไม่อนุมัติ (สลิปไม่ถูกต้อง)</button>
                    <button type="submit" class="btn btn-approve" name="action" value="approve_slip">✓ อนุมัติสลิปโอนเงิน</button>
                </form>
            `;
        }

        document.querySelectorAll(\'.detail\').forEach(b => {
            b.onclick = () => {
                const o = JSON.parse(b.dataset.order);
                document.getElementById(\'mNumber\').textContent = \'คำสั่งซื้อ #\' + o.number;
                document.getElementById(\'mDate\').textContent = o.date || \'-\';
                document.getElementById(\'mPaidDate\').textContent = o.paid_at || (o.status === \'paid\' || o.status === \'completed\' ? \'(ชำระแล้ว ไม่ระบุเวลา)\' : \'-\');
                
                // Slip Image
                const slipContainer = document.getElementById(\'mSlipContainer\');
                if (o.slip) {
                    slipContainer.innerHTML = `<a href="${esc(o.slip)}" target="_blank" title="คลิกเพื่อดูรูปเต็ม"><img src="${esc(o.slip)}" class="slip-view" alt="Slip"></a>`;
                } else {
                    slipContainer.innerHTML = `<div class="no-slip">ไม่มีรูปสลิปแนบมา<br><small style="margin-top:10px;">(หรือออเดอร์ถูกปฏิเสธสลิป / ยกเลิกไปแล้ว)</small></div>`;
                }
                
                // Items
                document.getElementById(\'mItems\').innerHTML = o.items.map(i => `
                    <p>
                        <span style="flex:1;"><strong>${esc(i.title)}</strong> <br><small style="color:#888;">จำนวน: ${i.qty}</small></span>
                        <span>฿${Number(i.price * i.qty).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                    </p>
                `).join(\'\');
                
                // Summary
                document.getElementById(\'mSubtotal\').textContent = \'฿\' + Number(o.subtotal).toLocaleString(undefined, {minimumFractionDigits: 2});
                document.getElementById(\'mDiscount\').textContent = \'-฿\' + Number(o.discount).toLocaleString(undefined, {minimumFractionDigits: 2});
                document.getElementById(\'mShippingFee\').textContent = \'฿\' + Number(o.shipping).toLocaleString(undefined, {minimumFractionDigits: 2});
                document.getElementById(\'mTotal\').textContent = \'฿\' + Number(o.total).toLocaleString(undefined, {minimumFractionDigits: 2});
                
                // Shipping Info
                const shipInfo = document.getElementById(\'mShippingInfo\');
                if (o.tracking) {
                    shipInfo.style.display = \'block\';
                    document.getElementById(\'mCourier\').textContent = o.courier || \'ไม่ระบุ\';
                    document.getElementById(\'mTracking\').textContent = o.tracking;
                } else {
                    shipInfo.style.display = \'none\';
                }
                
                // Status Badge in Modal Header
                const badge = document.getElementById(\'mStatusBadge\');
                let badgeClass = \'pending\';
                let badgeText = \'รอชำระเงิน\';
                
                if (o.status === \'cancelled\') { badgeClass = \'cancelled\'; badgeText = \'ยกเลิก (ไม่ได้ชำระเงิน)\'; }
                else if (o.shipping_status === \'returned\') { badgeClass = \'cancelled\'; badgeText = \'คืนสินค้า / คืนเงิน\'; }
                else if (o.shipping_status === \'cancelled\') { badgeClass = \'cancelled\'; badgeText = \'ยกเลิก (ชำระเงินแล้ว)\'; }
                else if (o.status === \'pending\' && (new Date(o.date).getTime() < Date.now() - 86400000)) { badgeClass = \'cancelled\'; badgeText = \'ยกเลิก (หมดเวลา)\'; }
                else if (o.status === \'reviewing\') { badgeClass = \'reviewing\'; badgeText = \'รอตรวจสอบสลิป\'; }
                else if (o.status === \'paid\') {
                    if (o.shipping_status === \'delivered\') { badgeClass = \'completed\'; badgeText = \'ลูกค้ายืนยัน / สำเร็จ\'; }
                    else if (o.shipping_status === \'shipped\') { badgeClass = \'shipped\'; badgeText = \'ร้านส่งแล้ว\'; }
                    else { badgeClass = \'paid\'; badgeText = \'รอร้านส่งของ\'; }
                }
                
                badge.className = \'badge badge-\' + badgeClass;
                badge.textContent = badgeText;
                
                // Actions
                const actionContainer = document.getElementById(\'mActions\');
                if (o.status === \'reviewing\') {
                    actionContainer.innerHTML = getActionForm(o.id);
                    actionContainer.style.display = \'flex\';
                } else {
                    actionContainer.innerHTML = \'\';
                    actionContainer.style.display = \'none\';
                }
                
                modal.classList.add(\'open\');
            };
        });
        
        document.getElementById(\'close\').onclick = () => modal.classList.remove(\'open\');
        modal.onclick = e => { if (e.target === modal) modal.classList.remove(\'open\'); };
    </script>
</body>
</html>
';
file_put_contents($file, $content);
echo 'Patched escrow.php tables successfully!';