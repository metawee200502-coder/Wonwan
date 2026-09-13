<?php
session_start();
require_once __DIR__ . '/../config/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

if (empty($_SESSION['admin_wallet_csrf'])) {
    $_SESSION['admin_wallet_csrf'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

// Calculate Total Revenue
$totalRevenueQuery = $pdo->query("SELECT SUM(gp_amount) FROM orders WHERE shipping_status='delivered' AND gp_amount > 0");
$totalRevenue = (float)$totalRevenueQuery->fetchColumn();

// Calculate Total Withdrawn
$totalWithdrawnQuery = $pdo->query("SELECT SUM(amount) FROM system_withdrawals");
$totalWithdrawn = (float)$totalWithdrawnQuery->fetchColumn();

$availableBalance = $totalRevenue - $totalWithdrawn;
if ($availableBalance < 0) $availableBalance = 0;

// Handle Bank Account Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_wallet_csrf'], $_POST['csrf_token'] ?? '')) {
        $error = 'คำขอหมดอายุ กรุณาลองใหม่';
    } else {
        if (isset($_POST['add_bank'])) {
            $bankName = trim($_POST['bank_name'] ?? '');
            $accName = trim($_POST['account_name'] ?? '');
            $accNum = trim($_POST['account_number'] ?? '');
            if ($bankName === '' || $accName === '' || $accNum === '') {
                $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
            } else {
                $check = $pdo->query("SELECT COUNT(*) FROM system_bank_accounts")->fetchColumn();
                if ($check >= 3) {
                    $error = 'เพิ่มบัญชีได้สูงสุด 3 บัญชีเท่านั้น';
                } else {
                    $isDefault = $check == 0 ? 1 : 0;
                    $pdo->prepare("INSERT INTO system_bank_accounts (bank_name, account_name, account_number, is_default) VALUES (?, ?, ?, ?)")->execute([$bankName, $accName, $accNum, $isDefault]);
                    $success = 'เพิ่มบัญชีสำเร็จ';
                }
            }
        } elseif (isset($_POST['delete_bank'])) {
            $id = (int)($_POST['bank_id'] ?? 0);
            $pdo->prepare("DELETE FROM system_bank_accounts WHERE account_id=?")->execute([$id]);
            $success = 'ลบบัญชีสำเร็จ';
            
            // Check if we need a new default
            $banks = $pdo->query("SELECT * FROM system_bank_accounts")->fetchAll(PDO::FETCH_ASSOC);
            if (count($banks) > 0) {
                $hasDefault = false;
                foreach($banks as $b) { if($b['is_default']) $hasDefault = true; }
                if(!$hasDefault) {
                    $pdo->prepare("UPDATE system_bank_accounts SET is_default=1 WHERE account_id=?")->execute([$banks[0]['account_id']]);
                }
            }
        } elseif (isset($_POST['set_default_bank'])) {
            $id = (int)($_POST['bank_id'] ?? 0);
            $pdo->query("UPDATE system_bank_accounts SET is_default=0");
            $pdo->prepare("UPDATE system_bank_accounts SET is_default=1 WHERE account_id=?")->execute([$id]);
            $success = 'ตั้งเป็นบัญชีหลักสำเร็จ';
        } elseif (isset($_POST['withdraw'])) {
            $amount = (float)str_replace(',', '', $_POST['amount'] ?? '0');
            $bankId = (int)($_POST['bank_id'] ?? 0);
            
            if ($amount < 100) {
                $error = 'ขั้นต่ำการถอนคือ 100 บาท';
            } elseif ($amount > $availableBalance) {
                $error = 'ยอดเงินที่ถอนได้ไม่เพียงพอ';
            } else {
                $bstmt = $pdo->prepare("SELECT * FROM system_bank_accounts WHERE account_id=?");
                $bstmt->execute([$bankId]);
                $bank = $bstmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$bank) {
                    $error = 'ไม่พบบัญชีธนาคาร';
                } else {
                    $bankInfoJson = json_encode([
                        'bank_name' => $bank['bank_name'],
                        'account_name' => $bank['account_name'],
                        'account_number' => $bank['account_number']
                    ], JSON_UNESCAPED_UNICODE);
                    
                    $pdo->prepare("INSERT INTO system_withdrawals (amount, bank_account_info) VALUES (?, ?)")->execute([$amount, $bankInfoJson]);
                    $success = 'ทำรายการถอนเงินสำเร็จ ยอดเงินจะถูกปรับปรุงทันที';
                    
                    // Recalculate balances
                    $totalWithdrawn += $amount;
                    $availableBalance -= $amount;
                }
            }
        }
    }
}

// Fetch Banks
$banks = $pdo->query("SELECT * FROM system_bank_accounts")->fetchAll(PDO::FETCH_ASSOC);

// Filters for Statistics
$statYear = isset($_GET['stat_year']) ? (int)$_GET['stat_year'] : (int)date('Y');
$statMonth = isset($_GET['stat_month']) ? (int)$_GET['stat_month'] : 0;

if ($statMonth > 0) {
    // Specific month
    $stmt = $pdo->prepare("SELECT COUNT(order_id) as total_orders, SUM(gp_amount) as total_earnings FROM orders WHERE shipping_status='delivered' AND YEAR(delivered_at)=? AND MONTH(delivered_at)=?");
    $stmt->execute([$statYear, $statMonth]);
    $st = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Growth
    $prevMonthDate = date('Y-m-d', strtotime("$statYear-$statMonth-01 -1 month"));
    $prevY = (int)date('Y', strtotime($prevMonthDate));
    $prevM = (int)date('m', strtotime($prevMonthDate));
    
    $stmt2 = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN MONTH(delivered_at)=? AND YEAR(delivered_at)=? THEN gp_amount ELSE 0 END) as cur,
            SUM(CASE WHEN MONTH(delivered_at)=? AND YEAR(delivered_at)=? THEN gp_amount ELSE 0 END) as prev
        FROM orders WHERE shipping_status='delivered'
    ");
    $stmt2->execute([$statMonth, $statYear, $prevM, $prevY]);
    $g = $stmt2->fetch(PDO::FETCH_ASSOC);
} else {
    // Entire year
    $stmt = $pdo->prepare("SELECT COUNT(order_id) as total_orders, SUM(gp_amount) as total_earnings FROM orders WHERE shipping_status='delivered' AND YEAR(delivered_at)=?");
    $stmt->execute([$statYear]);
    $st = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Growth
    $prevY = $statYear - 1;
    $stmt2 = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN YEAR(delivered_at)=? THEN gp_amount ELSE 0 END) as cur,
            SUM(CASE WHEN YEAR(delivered_at)=? THEN gp_amount ELSE 0 END) as prev
        FROM orders WHERE shipping_status='delivered'
    ");
    $stmt2->execute([$statYear, $prevY]);
    $g = $stmt2->fetch(PDO::FETCH_ASSOC);
}

$statEarnings = (float)($st['total_earnings'] ?? 0);
$statOrders = (int)($st['total_orders'] ?? 0);
$cur = (float)($g['cur'] ?? 0);
$prev = (float)($g['prev'] ?? 0);

if ($prev > 0) {
    $growth = (($cur - $prev) / $prev) * 100;
} else {
    $growth = $cur > 0 ? 100 : 0;
}
$growthClass = $growth >= 0 ? 'growth-pos' : 'growth-neg';

// Tabs
$tab = $_GET['tab'] ?? 'revenue';

$history = [];
if ($tab === 'withdraw') {
    $history = $pdo->query("SELECT * FROM system_withdrawals ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $history = $pdo->query("
        SELECT o.*,
               (SELECT GROUP_CONCAT(DISTINCT COALESCE(s.shop_name, p.company_name, u.display_name) SEPARATOR ', ')
                FROM order_items oi
                JOIN books b ON b.book_id = oi.book_id
                LEFT JOIN shops s ON b.seller_type = 'shop' AND b.seller_id = s.shop_id
                LEFT JOIN publishers p ON b.seller_type = 'publisher' AND b.seller_id = p.publisher_id
                LEFT JOIN users u ON b.seller_type = 'user' AND b.seller_id = u.user_id
                WHERE oi.order_id = o.order_id) as seller_names
        FROM orders o
        WHERE o.shipping_status = 'delivered' AND o.gp_amount > 0
        ORDER BY o.delivered_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $itemStmt = $pdo->prepare("SELECT oi.*,b.title,b.author,b.image_cover FROM order_items oi LEFT JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=:order_id");
    foreach($history as &$h) {
        $itemStmt->execute([':order_id'=>$h['order_id']]);
        $h['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function orderImage(?string $v):?string{if(!$v)return null;if(preg_match('#^https?://#i',$v))return $v;$v=ltrim(str_replace('\\','/',$v),'/');return str_starts_with($v,'uploads/')?'/pages/'.$v:'/assets/image/books/'.$v;}

$active_menu = 'revenue';

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายได้ของระบบ - วนวรรณ (Admin)</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #fdfbfb; margin: 0; display: flex; min-height: 100vh; }
        .admin-main-wrap { flex: 1; min-width: 0; padding: 40px 50px; }
        
        .page-title { color: #00796B; font-size: 26px; margin-bottom: 25px; font-weight: bold; }
        
        /* Green Wallet Theme */
        .dashboard-grid { display: grid; grid-template-columns: 500px 1fr; gap: 25px; margin-bottom: 30px; align-items: stretch; }
        
        .wallet-card {
            background: linear-gradient(135deg, #00897B, #004D40);
            border-radius: 20px; padding: 40px 35px; color: #fff; position: relative; overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,77,64,0.25); display: flex; flex-direction: column; justify-content: center; min-height: 240px; box-sizing: border-box;
        }
        .wallet-card::after {
            content: "฿";
            position: absolute; right: 20px; top: -30px; font-size: 240px; font-weight: 800;
            color: rgba(255,255,255,0.06); transform: rotate(15deg); line-height: 1; pointer-events: none;
        }
        .wallet-card-title { font-size: 16px; margin-bottom: 20px; opacity: 0.9; }
        .wallet-card-amount { font-size: 55px; font-weight: bold; margin-bottom: 35px; display: flex; align-items: baseline; gap: 8px; }
        .wallet-card-amount span { font-size: 32px; opacity: 0.8; }
        .wallet-actions { display: flex; gap: 15px; position: relative; z-index: 2; }
        .btn-withdraw { font-family: inherit; flex: 1; background: #fff; color: #00695C; border: none; padding: 14px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 15px; }
        .btn-withdraw:hover { background: #f0f0f0; transform: translateY(-2px); }
        .btn-manage-bank { font-family: inherit; flex: 1; background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3); padding: 14px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; backdrop-filter: blur(5px); font-size: 15px; }
        .btn-manage-bank:hover { background: rgba(255,255,255,0.3); }

        /* Stats Box */
        .stats-box { background: #fff; border: 1px solid #e0f2f1; border-radius: 20px; padding: 25px 35px; box-shadow: 0 5px 20px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: center; gap: 15px; box-sizing: border-box; }
        .stats-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .stats-header h3 { margin: 0; color: #444; font-size: 18px; }
        .stats-filters { display: flex; gap: 10px; }
        .stats-filters select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: 14px; color: #555; outline: none; }
        
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .stat-item { background: #fdfdfd; border: 1px solid #e0f2f1; padding: 20px 15px; border-radius: 14px; text-align: center; }
        .stat-item span { display: block; color: #666; font-size: 14px; margin-bottom: 8px; }
        .stat-item strong { display: block; color: #00796B; font-size: 26px; }
        .growth-pos { color: #2e7d32 !important; }
        .growth-neg { color: #d32f2f !important; }

        /* History Tabs */
        .history-tabs { display: flex; gap: 30px; margin-bottom: 20px; border-bottom: 2px solid #eee; }
        .history-tab { padding: 10px 5px; color: #777; text-decoration: none; font-size: 18px; font-weight: 600; position: relative; transition: color 0.2s; }
        .history-tab.active { color: #00796B; }
        .history-tab.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 3px; background: #00796B; border-radius: 3px 3px 0 0; }
        
        .tablebox { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e0f2f1; }
        .orders { width: 100%; border-collapse: collapse; min-width: 800px; }
        .orders th, .orders td { padding: 18px 20px; border-bottom: 1px solid #f0f0f0; text-align: left; }
        .orders th { background: #f5fbfb; color: #555; font-weight: 600; }
        .orders tr:hover { background: #fafafa; }
        .orders td { color: #444; }
        
        /* Modals */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
        .modal.open { display: flex; }
        .modal-box { background: #fff; width: 450px; border-radius: 24px; padding: 30px; position: relative; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .modal-close { position: absolute; right: 25px; top: 25px; font-size: 24px; color: #999; cursor: pointer; background: none; border: none; padding: 0; }
        .modal-title { margin: 0 0 25px 0; color: #333; font-size: 20px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 12px; font-family: inherit; font-size: 15px; box-sizing: border-box; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: #00796B; outline: none; }
        
        .btn-submit { font-family: inherit; width: 100%; background: #00796B; color: #fff; border: none; padding: 14px; border-radius: 12px; font-weight: bold; font-size: 16px; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #00695C; }
        
        /* Bank List in Modal */
        .bank-item { border: 1px solid #e0e0e0; border-radius: 12px; padding: 15px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; transition: border-color 0.2s; cursor: pointer; }
        .bank-item:hover { border-color: #00796B; background: #fdfdfd; }
        .bank-item.selected { border-color: #00796B; background: #f5fbfb; }
        .bank-info strong { display: block; color: #333; font-size: 15px; margin-bottom: 4px; }
        .bank-info span { color: #666; font-size: 13px; }
        .bank-delete { background: none; border: none; color: #d32f2f; cursor: pointer; font-size: 13px; padding: 5px; }
        .bank-delete:hover { text-decoration: underline; }
        
        @media(max-width: 1024px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
<style>
    /* Add styling for clickable rows */
    .orders tbody tr.clickable-row:hover { background-color: #f5fbfb; cursor: pointer; }
    
    /* odModal Styling */
    .od-modal{position:fixed;inset:0;background:#0005;display:none;align-items:center;justify-content:center;padding:20px;z-index:9999;backdrop-filter:blur(2px)}
    .od-modal.open{display:flex}
    .od-box{width:min(680px,100%);max-height:90vh;overflow-y:auto;overflow-x:hidden;scrollbar-width:none;-ms-overflow-style:none;background:#f6f3f1;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.22)}
    .od-box::-webkit-scrollbar{display:none}
    .od-head{padding:22px 24px;border-radius:20px 20px 0 0;color:#fff}
    .od-head h2{margin:0;font-size:18px}
    .od-head p{margin:4px 0 0;font-size:13px;opacity:.88}
    .od-section{background:#fff;margin-bottom:6px;padding:16px 22px}
    .od-section h3{margin:0 0 12px;font-size:15px;color:#00796B;border-bottom:1px solid #eee;padding-bottom:8px}
    .od-row{display:flex;justify-content:space-between;padding:5px 0;font-size:14px;color:#666}
    .od-row strong{color:#333}
    .od-item{display:flex;gap:14px;align-items:center;padding:10px 0;border-bottom:1px solid #f0eae8}
    .od-item:last-child{border:0}
    .od-item img{width:52px;height:68px;object-fit:cover;border-radius:6px;background:#f7ebe5}
    .od-item-info{flex:1}
    .od-item-title{font-weight:600;color:#333;font-size:15px;margin:0 0 3px}
    .od-item-sub{font-size:13px;color:#888}
    .od-item-price{font-weight:700;color:#00796B;white-space:nowrap}
    .od-tracking{padding:12px 16px;background:#eef6ee;border:1px solid #b8d8b8;border-radius:10px;color:#3a6b3a;font-size:14px;margin-bottom:4px}
    .od-footer-btns{display:flex;gap:10px;flex-wrap:wrap;padding:16px 22px;background:#fff;border-radius:0 0 20px 20px}
    .od-btn{height:42px;padding:0 18px;border-radius:9px;border:1px solid #ccc;background:#fff;font:inherit;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;text-decoration:none;white-space:nowrap;transition:background .15s,border-color .15s}
    .od-btn.close-btn{margin-left:auto;color:#999;border-color:#ddd}
    .od-btn.close-btn:hover{background:#f5f5f5}
    .grand-total{display:flex;justify-content:space-between;padding:10px 0 4px;font-size:16px;font-weight:700;color:#00796B;border-top:1px solid #eee;margin-top:6px}
</style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    <div class="admin-main-wrap">
        <h1 class="page-title">รายได้ของระบบ</h1>

        <?php if($success): ?>
            <script>Swal.fire({icon: 'success', title: 'สำเร็จ', text: '<?= htmlspecialchars($success) ?>', timer: 2000, showConfirmButton: false});</script>
        <?php endif; ?>
        <?php if($error): ?>
            <script>Swal.fire({icon: 'error', title: 'ผิดพลาด', text: '<?= htmlspecialchars($error) ?>'});</script>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Left: Wallet Card -->
            <div class="wallet-card">
                <div class="wallet-card-title">ยอดเงินที่สามารถถอนได้</div>
                <div class="wallet-card-amount">
                    <span>฿</span>
                    <?= number_format($availableBalance, 2) ?>
                </div>
                <div class="wallet-actions">
                    <button class="btn-withdraw" onclick="openWithdrawModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:text-bottom; margin-right:5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        ถอนเงิน
                    </button>
                    <button class="btn-manage-bank" onclick="openBankModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:text-bottom; margin-right:5px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        จัดการบัญชี
                    </button>
                </div>
            </div>

            <!-- Right: Stats Box -->
            <div class="stats-box">
                <div class="stats-header">
                    <h3>สถิติภาพรวม</h3>
                    <form method="get" class="stats-filters">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                        <select name="stat_year" onchange="this.form.submit()">
                            <?php for($y=date('Y'); $y>=date('Y')-3; $y--): ?>
                                <option value="<?= $y ?>" <?= $statYear==$y?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="stat_month" onchange="this.form.submit()">
                            <option value="0">ตลอดทั้งปี</option>
                            <?php 
                            $thaiMonths = ['1'=>'มกราคม','2'=>'กุมภาพันธ์','3'=>'มีนาคม','4'=>'เมษายน','5'=>'พฤษภาคม','6'=>'มิถุนายน','7'=>'กรกฎาคม','8'=>'สิงหาคม','9'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];
                            for($m=1; $m<=12; $m++): ?>
                                <option value="<?= $m ?>" <?= $statMonth==$m?'selected':'' ?>><?= $thaiMonths[$m] ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <span>รายได้สุทธิ (ช่วงที่เลือก)</span>
                        <strong>฿<?= number_format($statEarnings, 2) ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>ถอนเงินไปแล้วทั้งหมด</span>
                        <strong>฿<?= number_format($totalWithdrawn, 2) ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>คำสั่งซื้อที่สำเร็จแล้ว</span>
                        <strong style="color: #444;"><?= number_format($statOrders) ?></strong>
                    </div>
                    <div class="stat-item">
                        <span>อัตราการเติบโต (เทียบช่วงก่อนหน้า)</span>
                        <strong class="<?= $growthClass ?>"><?= $growth > 0 ? '+' : '' ?><?= number_format($growth, 1) ?>%</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Tabs -->
        <div class="history-tabs">
            <a href="?tab=revenue&stat_year=<?= $statYear ?>&stat_month=<?= $statMonth ?>" class="history-tab <?= $tab==='revenue' ? 'active' : '' ?>">ประวัติรายได้จาก GP</a>
            <a href="?tab=withdraw&stat_year=<?= $statYear ?>&stat_month=<?= $statMonth ?>" class="history-tab <?= $tab==='withdraw' ? 'active' : '' ?>">ประวัติการถอน (Transaction History)</a>
        </div>

        <div class="tablebox">
            <table class="orders">
                <thead>
                    <?php if($tab === 'revenue'): ?>
                        <tr>
                            <th>วันที่-เวลา</th>
                            <th>หมายเลขออเดอร์</th>
                            <th>ร้านค้าที่ขาย</th>
                            <th style="text-align:right">รายได้ระบบ (GP)</th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th>วันที่-เวลา</th>
                            <th>บัญชีปลายทาง</th>
                            <th style="text-align:right">จำนวนเงิน (บาท)</th>
                            <th>สถานะ</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="4" style="text-align:center;padding:70px;color:#888;">ยังไม่มีประวัติธุรกรรม</td></tr>
                    <?php else: foreach ($history as $h): ?>
                        <?php if($tab === 'revenue'): 
                            $orderJson = htmlspecialchars(json_encode([
                                'number' => $h['order_number'],
                                'seller' => $h['seller_names'] ?: 'ไม่ระบุ',
                                'buyer' => $h['shipping_name'],
                                'phone' => $h['shipping_phone'],
                                'address' => $h['shipping_address'],
                                'courier' => $h['shipping_courier'] ?? '',
                                'tracking' => $h['tracking_number'] ?? '',
                                'subtotal' => (float)($h['subtotal'] ?? 0),
                                'shipping_fee' => (float)($h['shipping_fee'] ?? 0),
                                'total' => (float)($h['total_amount'] ?? 0),
                                'gp_amount' => (float)($h['gp_amount'] ?? 0),
                                'items' => array_map(fn($i) => [
                                    'title' => $i['title'] ?: 'หนังสือ',
                                    'author' => $i['author'] ?? '',
                                    'qty' => $i['quantity'],
                                    'price' => (float)$i['price_at_purchase'],
                                    'img' => orderImage($i['image_cover']) ?? ''
                                ], $h['items'])
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                        ?>
                            <tr class="clickable-row" onclick="openOrderDetail(this)" data-od='<?= $orderJson ?>'>
                                <td><?= $h['delivered_at'] ? date('d/m/Y H:i', strtotime($h['delivered_at'])) : '-' ?></td>
                                <td><strong>#<?= htmlspecialchars($h['order_number']) ?></strong><br><small style="color:#00796B; font-weight:600; font-size:12px;">ดูรายละเอียด &rsaquo;</small></td>
                                <td><?= htmlspecialchars($h['seller_names'] ?: 'ไม่ระบุ') ?></td>
                                <td style="text-align:right; color:#2e7d32; font-weight:bold;">+ ฿<?= number_format($h['gp_amount'], 2) ?></td>
                            </tr>
                        <?php else: ?>
                            <?php $bInfo = json_decode($h['bank_account_info'], true); ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($bInfo['bank_name'] ?? 'ไม่ระบุ') ?></strong><br>
                                    <span style="font-size:13px;color:#777;"><?= htmlspecialchars($bInfo['account_number'] ?? '') ?> (<?= htmlspecialchars($bInfo['account_name'] ?? '') ?>)</span>
                                </td>
                                <td style="text-align:right; font-weight:bold;">฿<?= number_format($h['amount'], 2) ?></td>
                                <td><span style="color:#00796B; font-weight:600;">สำเร็จ</span></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modals -->
    <!-- Bank Account Modal -->
    <div class="modal" id="bankModal">
        <div class="modal-box" style="width: 550px; padding: 40px; border-radius: 20px;">
            <h2 class="modal-title" style="color: #00695C; font-weight: bold; margin-bottom: 25px; font-size: 24px;">จัดการบัญชีรับเงิน</h2>
            
            <div id="bankList" style="margin-bottom: 25px;">
                <?php foreach($banks as $b): ?>
                    <div class="bank-item" style="border: 1px solid <?= $b['is_default'] ? '#00796B' : '#e0e0e0' ?>; background: <?= $b['is_default'] ? '#f5fbfb' : '#fff' ?>; border-radius: 12px; padding: 15px 20px; margin-bottom: 12px; position: relative;">
                        <div class="bank-info" style="margin-bottom: 5px;">
                            <strong style="color: #00695C; font-size: 16px; display: block; margin-bottom: 5px;">
                                <?= htmlspecialchars($b['bank_name']) ?>
                                <?php if($b['is_default']): ?>
                                    <span style="color: #d32f2f; font-size: 13px; font-weight: normal;">(บัญชีหลัก)</span>
                                <?php endif; ?>
                            </strong>
                            <span style="color: #555; display: block; margin-bottom: 3px;">ชื่อ: <?= htmlspecialchars($b['account_name']) ?></span>
                            <span style="color: #555; display: block;">เลขที่: <?= htmlspecialchars($b['account_number']) ?></span>
                        </div>
                        <div style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 8px;">
                            <?php if(!$b['is_default']): ?>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_wallet_csrf'] ?>">
                                <input type="hidden" name="bank_id" value="<?= $b['account_id'] ?>">
                                <button type="submit" name="set_default_bank" style="background: #e8f5e9; color: #2e7d32; border: none; padding: 6px 15px; border-radius: 6px; font-size: 13px; font-family: inherit; font-weight: bold; cursor: pointer; width: 100%;">ตั้งหลัก</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" style="margin:0;" onsubmit="return confirm('ยืนยันการลบบัญชีนี้?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_wallet_csrf'] ?>">
                                <input type="hidden" name="bank_id" value="<?= $b['account_id'] ?>">
                                <button type="submit" name="delete_bank" style="background: #ffebee; color: #d32f2f; border: none; padding: 6px 15px; border-radius: 6px; font-size: 13px; font-family: inherit; font-weight: bold; cursor: pointer; width: 100%;">ลบ</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if(!$banks): ?>
                    <div style="text-align:center; padding: 20px; color:#888; border:1px dashed #ccc; border-radius:12px;">ยังไม่มีบัญชีธนาคาร</div>
                <?php endif; ?>
            </div>

            <h3 style="font-size: 16px; margin: 0 0 15px 0; color: #883344;">+ เพิ่มบัญชีใหม่ (เพิ่มได้สูงสุด 3 บัญชี)</h3>
            <?php if(count($banks) < 3): ?>
                <form method="post" style="border: 1px solid #eee; border-radius: 12px; padding: 20px;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_wallet_csrf'] ?>">
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <select name="bank_name" required style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: 15px; appearance: none; background: #fff url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"%23333\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"6 9 12 15 18 9\"></polyline></svg>') no-repeat right 15px center/16px; padding-right: 40px; cursor: pointer;">
                            <option value="">-- เลือกธนาคาร/ช่องทางรับเงิน --</option>
                            <option value="ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร (BAAC)">ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร (BAAC)</option>
                            <option value="ธนาคารออมสิน (GSB)">ธนาคารออมสิน (GSB)</option>
                            <option value="กสิกรไทย (KBank)">กสิกรไทย (KBank)</option>
                            <option value="ไทยพาณิชย์ (SCB)">ไทยพาณิชย์ (SCB)</option>
                            <option value="กรุงเทพ (BBL)">กรุงเทพ (BBL)</option>
                            <option value="กรุงไทย (KTB)">กรุงไทย (KTB)</option>
                            <option value="กรุงศรีอยุธยา (BAY)">กรุงศรีอยุธยา (BAY)</option>
                            <option value="ทีเอ็มบีธนชาต (ttb)">ทีเอ็มบีธนชาต (ttb)</option>
                            <option value="อาคารสงเคราะห์ (GHB)">อาคารสงเคราะห์ (GHB)</option>
                            <option value="พร้อมเพย์ (PromptPay)">พร้อมเพย์ (PromptPay)</option>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <input type="text" name="account_name" placeholder="ชื่อบัญชี" required style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: 15px; box-sizing: border-box;">
                        <input type="text" name="account_number" placeholder="เลขบัญชี / พร้อมเพย์" required style="width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: 15px; box-sizing: border-box;">
                    </div>
                    
                    <button type="submit" name="add_bank" class="btn-submit" style="border-radius: 8px;">เพิ่มบัญชี</button>
                </form>
            <?php else: ?>
                <div style="margin-top:20px; color:#d32f2f; font-size:14px; text-align:center;">สามารถเพิ่มบัญชีได้สูงสุด 3 บัญชีเท่านั้น</div>
            <?php endif; ?>
            
            <button type="button" class="btn-cancel" onclick="closeBankModal()" style="font-family: inherit; width: 100%; background: #f0f0f0; color: #444; border: none; padding: 14px; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: background 0.2s; margin-top: 20px;">ปิดหน้าต่าง</button>
        </div>
    </div>

    <!-- Withdraw Modal -->
    <div class="modal" id="withdrawModal">
        <div class="modal-box" style="width: 480px; padding: 35px 40px; border-radius: 20px;">
            <button class="modal-close" onclick="closeWithdrawModal()">&times;</button>
            <h2 class="modal-title" style="color: #00695C; font-weight: bold; margin-bottom: 25px; font-size: 24px;">ถอนเงินเข้าบัญชี</h2>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['admin_wallet_csrf'] ?>">
                
                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="color: #666; font-size: 15px;">เลือกบัญชีรับเงิน</label>
                    <?php if($banks): ?>
                        <select name="bank_id" required style="width: 100%; padding: 14px 15px; border: 1px solid #222; border-radius: 8px; font-family: inherit; font-size: 16px; font-weight: 500; appearance: none; background: #fff url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"%23333\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"6 9 12 15 18 9\"></polyline></svg>') no-repeat right 15px center/16px; padding-right: 40px; cursor: pointer;">
                            <?php foreach($banks as $b): ?>
                                <option value="<?= $b['account_id'] ?>"><?= htmlspecialchars($b['bank_name']) ?> - <?= htmlspecialchars($b['account_name']) ?> (<?= htmlspecialchars($b['account_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <div style="padding: 15px; background: #fff4e5; color: #663c00; border-radius: 8px; font-size: 14px;">
                            กรุณาเพิ่มบัญชีรับเงินก่อนทำรายการถอน <br>
                            <a href="javascript:void(0)" onclick="closeWithdrawModal(); openBankModal();" style="color:#d32f2f; font-weight:bold; margin-top:5px; display:inline-block;">เพิ่มบัญชีที่นี่</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group" style="margin-bottom: 30px;">
                    <label style="color: #666; font-size: 15px;">จำนวนเงินที่ต้องการถอน (บาท)</label>
                    <input type="text" name="amount" id="withdrawAmount" placeholder="0.00" required autocomplete="off" oninput="this.value = this.value.replace(/[^0-9.]/g, '')" style="border: 1px solid #ddd; border-radius: 8px; padding: 14px 15px; font-size: 18px; color: #555;">
                    <div style="display:flex; justify-content:space-between; margin-top:10px;">
                        <span style="font-size:14px; color:#888;">ยอดเงินที่ถอนได้: ฿<?= number_format($availableBalance, 2) ?> <span style="color:#aaa;">(ขั้นต่ำ 100 บาท)</span></span>
                        <a href="javascript:void(0)" onclick="document.getElementById('withdrawAmount').value = '<?= $availableBalance ?>'" style="font-size:14px; color:#00796B; text-decoration:none; font-weight: 600;">ถอนทั้งหมด</a>
                    </div>
                </div>

                <div style="display: flex; gap: 15px;">
                    <button type="button" class="btn-cancel" onclick="closeWithdrawModal()" style="font-family: inherit; flex: 1; background: #f0f0f0; color: #444; border: none; padding: 14px; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: background 0.2s;">ยกเลิก</button>
                    <button type="submit" name="withdraw" class="btn-submit" <?= !$banks ? 'disabled style="opacity:0.5;cursor:not-allowed; border-radius:8px; flex:1;"' : 'style="border-radius:8px; flex:1;"' ?>>ยืนยันการถอนเงิน</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openBankModal() { document.getElementById('bankModal').classList.add('open'); }
        function closeBankModal() { document.getElementById('bankModal').classList.remove('open'); }
    </script>    <!-- Order Details Modal -->
    <div class="od-modal" id="odModal">
        <div class="od-box">
            <div class="od-head" id="odHead" style="background: #00796B;">
                <h2 id="odTitle"></h2>
                <p id="odSub"></p>
            </div>
            <div class="od-section" id="odItems"></div>
            <div class="od-section">
                <h3 style="display:flex;align-items:center;gap:7px">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>ข้อมูลการจัดส่ง
                </h3>
                <div class="od-row"><span>ผู้รับ</span><strong id="odBuyer"></strong></div>
                <div class="od-row"><span>เบอร์โทร</span><strong id="odPhone"></strong></div>
                <div class="od-row"><span>ที่อยู่</span><strong id="odAddress" style="max-width:320px;text-align:right"></strong></div>
                <div id="odTrackingBox" style="display:none" class="od-tracking">
                    <strong id="odCourier"></strong>
                    <div>หมายเลขพัสดุ: <span id="odTracking" style="font-weight:700;letter-spacing:.5px"></span></div>
                </div>
            </div>
            <div class="od-section">
                <h3 style="display:flex;align-items:center;gap:7px">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>สรุปยอดรายได้ระบบ (GP)
                </h3>
                <div class="od-row"><span>ยอดรวมสินค้า</span><strong id="odSubtotal"></strong></div>
                <div class="od-row" style="color:#d32f2f;">
                    <span>หักค่าธรรมเนียมการขาย (10%)</span>
                    <strong id="odGPDeduction"></strong>
                </div>
                <div class="od-row"><span>ค่าจัดส่งที่ลูกค้าจ่าย</span><strong id="odShippingFee"></strong></div>
                
                <div class="od-row" style="border-top: 1px dashed #eee; margin-top: 5px; padding-top: 10px; color: #666;">
                    <span>ยอดสุทธิโอนให้ร้านค้า</span>
                    <strong id="odNetPayout"></strong>
                </div>
                
                <div class="grand-total" style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-top: 15px; border: 1px solid #c8e6c9; align-items: center;">
                    <span style="color:#2e7d32;">รายได้ค่าธรรมเนียมของระบบ</span>
                    <strong id="odTotal" style="color:#2e7d32; font-size: 18px;"></strong>
                </div>
            </div>
            <div class="od-footer-btns" id="odFooterBtns">
                <button class="od-btn close-btn" onclick="odModal.classList.remove('open')">ปิด</button>
            </div>
        </div>
    </div>
    
    <script>
    const odModal=document.getElementById('odModal');
    function openOrderDetail(el){
      if(event.target.closest('button,form'))return;
      const d=JSON.parse(el.dataset.od);
      
      document.getElementById('odTitle').textContent=d.seller;
      document.getElementById('odSub').textContent='สำเร็จแล้ว · #'+d.number;
      document.getElementById('odBuyer').textContent=d.buyer;
      document.getElementById('odPhone').textContent=d.phone;
      document.getElementById('odAddress').textContent=d.address;
      
      const trackBox=document.getElementById('odTrackingBox');
      if(d.tracking){
          trackBox.style.display='block';
          document.getElementById('odCourier').textContent='บริษัทขนส่ง: '+(d.courier||'ไม่ระบุ');
          document.getElementById('odTracking').textContent=d.tracking;
      } else {
          trackBox.style.display='none';
      }
      
      document.getElementById('odSubtotal').textContent='฿'+d.subtotal.toLocaleString(undefined,{minimumFractionDigits:2});
      document.getElementById('odGPDeduction').textContent='- ฿'+d.gp_amount.toLocaleString(undefined,{minimumFractionDigits:2});
      document.getElementById('odShippingFee').textContent='+ ฿'+d.shipping_fee.toLocaleString(undefined,{minimumFractionDigits:2});
      
      const netPayout = (d.subtotal - d.gp_amount) + d.shipping_fee;
      document.getElementById('odNetPayout').textContent='฿'+netPayout.toLocaleString(undefined,{minimumFractionDigits:2});
      document.getElementById('odTotal').textContent='+ ฿'+d.gp_amount.toLocaleString(undefined,{minimumFractionDigits:2});
      
      document.getElementById('odItems').innerHTML='<h3 style="display:flex;align-items:center;gap:7px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>รายการสินค้า</h3>'+d.items.map(i=>`<div class="od-item"><img src="${i.img||'/assets/image/minilogo.png'}" onerror="this.src='/assets/image/minilogo.png'"><div class="od-item-info"><div class="od-item-title">${i.title}</div><div class="od-item-sub">${i.author} · x${i.qty}</div></div><div class="od-item-price">฿${(i.price*i.qty).toLocaleString(undefined,{minimumFractionDigits:2})}</div></div>`).join('');
      
      odModal.classList.add('open');
    }
    odModal.addEventListener('click',e=>{if(e.target===odModal)odModal.classList.remove('open')});
    document.addEventListener('keydown',e=>{if(e.key==='Escape')odModal.classList.remove('open')});
    </script>

    <script>
        function openWithdrawModal() { document.getElementById('withdrawModal').style.display = 'flex'; }
        function closeWithdrawModal() { document.getElementById('withdrawModal').classList.remove('open'); }
        
        // Close modal when clicking outside
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('open');
            }
        }
    </script>
</body>
</html>
