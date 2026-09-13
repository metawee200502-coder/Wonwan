<?php
session_start();
require_once __DIR__.'/../config/config.php';

if(empty($_SESSION['user_id'])){header('Location:login.php');exit;}
$userId=(int)$_SESSION['user_id'];
$role=$_SESSION['role']??'';
if(!in_array($role,['publisher_admin','shop_admin'],true)){header('Location:home.php');exit;}

$sellerType=$role==='publisher_admin'?'publisher':'shop';

if (isset($_GET['ajax_order_details']) && isset($_GET['order_id'])) {
    header('Content-Type: application/json');
    $orderId = (int)$_GET['order_id'];
    
    if($sellerType==='publisher') {
        $q=$pdo->prepare('SELECT p.publisher_id FROM publisher_staff ps JOIN publishers p ON p.publisher_id=ps.publisher_id WHERE ps.user_id=? LIMIT 1');
    } else {
        $q=$pdo->prepare('SELECT s.shop_id FROM shop_staff ss JOIN shops s ON s.shop_id=ss.shop_id WHERE ss.user_id=? LIMIT 1');
    }
    $q->execute([$userId]);
    $sellerId = $q->fetchColumn();

    // Fetch Order Info
    $stmtOrder = $pdo->prepare("
        SELECT o.order_number, o.shipping_name, o.shipping_phone, o.shipping_address, o.shipping_method, o.tracking_number, o.subtotal, o.gp_amount, o.shipping_fee, o.net_payout, o.payment_status, o.shipping_status,
        COALESCE(u.display_name, 'ไม่ระบุ') as buyer_name
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id = ?
    ");
    $stmtOrder->execute([$orderId]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    // Fetch Items
    $stmtItems = $pdo->prepare("SELECT oi.quantity, oi.price_at_purchase, b.title, b.image_cover FROM order_items oi JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=? AND b.seller_type=? AND b.seller_id=?");
    $stmtItems->execute([$orderId, $sellerType, $sellerId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'order' => $order,
        'items' => $items
    ]);
    exit;
}

if($sellerType==='publisher') {
    $q=$pdo->prepare('SELECT p.publisher_id id, p.company_name name, p.bank_name, p.bank_account_number, p.bank_account_name FROM publisher_staff ps JOIN publishers p ON p.publisher_id=ps.publisher_id WHERE ps.user_id=? LIMIT 1');
} else {
    $q=$pdo->prepare('SELECT s.shop_id id, s.shop_name name, s.bank_name, s.bank_account_number, s.bank_account_name FROM shop_staff ss JOIN shops s ON s.shop_id=ss.shop_id WHERE ss.user_id=? LIMIT 1');
}
$q->execute([$userId]);
$seller=$q->fetch(PDO::FETCH_ASSOC);

if(!$seller){header('Location:dashboard.php');exit;}
$sellerId=(int)$seller['id'];
$seller_name=$seller['name'];
$active_menu='wallet';

// CSRF Token
if(empty($_SESSION['seller_wallet_csrf']))$_SESSION['seller_wallet_csrf']=bin2hex(random_bytes(24));
$success='';$error='';

// Ensure wallet exists
$pdo->prepare("INSERT IGNORE INTO vendor_wallets (seller_type, seller_id) VALUES (?, ?)")->execute([$sellerType, $sellerId]);

// Fetch Wallet Info
$walletStmt = $pdo->prepare("SELECT * FROM vendor_wallets WHERE seller_type=? AND seller_id=?");
$walletStmt->execute([$sellerType, $sellerId]);
$wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

// Fetch Bank Accounts
$banksStmt = $pdo->prepare("SELECT * FROM vendor_bank_accounts WHERE seller_type=? AND seller_id=? ORDER BY is_default DESC, id ASC");
$banksStmt->execute([$sellerType, $sellerId]);
$bankAccounts = $banksStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Withdrawal Request
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['withdraw'])) {
    if(!hash_equals($_SESSION['seller_wallet_csrf'],(string)($_POST['csrf_token']??''))) {
        $error = 'คำขอหมดอายุ กรุณาลองใหม่';
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $bankId = (int)($_POST['bank_account_id'] ?? 0);
        $selectedBank = null;
        foreach($bankAccounts as $b) {
            if($b['id'] == $bankId) {
                $selectedBank = $b; break;
            }
        }
        if(!$selectedBank && count($bankAccounts) > 0) {
            $selectedBank = $bankAccounts[0]; // fallback
        }

        if (!$selectedBank) {
            $error = 'กรุณาเพิ่มบัญชีรับเงินก่อนทำการถอน';
        } elseif ($amount < 100) {
            $error = 'ยอดถอนขั้นต่ำคือ 100 บาท';
        } elseif ($amount > (float)$wallet['available_balance']) {
            $error = 'ยอดเงินที่ถอนได้ไม่เพียงพอ';
        } else {
            // Process withdrawal
            $pdo->beginTransaction();
            try {
                // Lock wallet row
                $lockStmt = $pdo->prepare("SELECT available_balance FROM vendor_wallets WHERE wallet_id=? FOR UPDATE");
                $lockStmt->execute([$wallet['wallet_id']]);
                $currentBalance = (float)$lockStmt->fetchColumn();
                
                if ($amount <= $currentBalance) {
                    // Deduct from available, add to pending_withdrawal
                    $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance - ?, pending_withdrawal_balance = pending_withdrawal_balance + ? WHERE wallet_id=?")->execute([$amount, $amount, $wallet['wallet_id']]);
                    
                    // Log transaction
                    $bankInfo = "ธนาคาร: " . $selectedBank['bank_name'] . "\nชื่อบัญชี: " . $selectedBank['account_name'] . "\nเลขบัญชี: " . $selectedBank['account_number'];
                    $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, status, bank_account_info, withdrawal_bank_info) VALUES (?, 'WITHDRAWAL', ?, 'PENDING', ?, ?)")->execute([$wallet['wallet_id'], $amount, $bankInfo, json_encode($selectedBank, JSON_UNESCAPED_UNICODE)]);
                    
                    $pdo->commit();
                    $success = 'สร้างรายการถอนเงินสำเร็จ กรุณารอแอดมินตรวจสอบและโอนเงินเข้าบัญชี (PENDING)';
                    
                    // Refresh wallet
                    $walletStmt->execute([$sellerType, $sellerId]);
                    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $pdo->rollBack();
                    $error = 'ยอดเงินที่ถอนได้ไม่เพียงพอ';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
}

// Handle Add Bank
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_bank'])) {
    if(!hash_equals($_SESSION['seller_wallet_csrf'],(string)($_POST['csrf_token']??''))) {
        $error = 'คำขอหมดอายุ กรุณาลองใหม่';
    } else {
        if(count($bankAccounts) >= 3) {
            $error = 'สามารถเพิ่มบัญชีได้สูงสุด 3 บัญชีเท่านั้น';
        } else {
            $bankName = trim($_POST['bank_name'] ?? '');
            $bankAccName = trim($_POST['bank_account_name'] ?? '');
            $bankAccNum = trim($_POST['bank_account_number'] ?? '');
            
            if($bankName==='' || $bankAccName==='' || $bankAccNum==='') {
                $error = 'กรุณากรอกข้อมูลบัญชีให้ครบถ้วน';
            } else {
                $isDefault = count($bankAccounts) === 0 ? 1 : 0;
                $pdo->prepare("INSERT INTO vendor_bank_accounts (seller_type, seller_id, bank_name, account_name, account_number, is_default) VALUES (?, ?, ?, ?, ?, ?)")->execute([$sellerType, $sellerId, $bankName, $bankAccName, $bankAccNum, $isDefault]);
                $success = 'เพิ่มบัญชีรับเงินเรียบร้อยแล้ว';
                // Reload banks
                $banksStmt->execute([$sellerType, $sellerId]);
                $bankAccounts = $banksStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
}

// Handle Delete Bank
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_bank'])) {
    if(!hash_equals($_SESSION['seller_wallet_csrf'],(string)($_POST['csrf_token']??''))) {
        $error = 'คำขอหมดอายุ กรุณาลองใหม่';
    } else {
        $bankId = (int)$_POST['bank_id'];
        $pdo->prepare("DELETE FROM vendor_bank_accounts WHERE id=? AND seller_type=? AND seller_id=?")->execute([$bankId, $sellerType, $sellerId]);
        $success = 'ลบบัญชีรับเงินเรียบร้อยแล้ว';
        // Reload and fix default if needed
        $banksStmt->execute([$sellerType, $sellerId]);
        $bankAccounts = $banksStmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($bankAccounts) > 0) {
            $hasDefault = false;
            foreach($bankAccounts as $b) { if($b['is_default']) $hasDefault = true; }
            if(!$hasDefault) {
                $pdo->prepare("UPDATE vendor_bank_accounts SET is_default=1 WHERE id=?")->execute([$bankAccounts[0]['id']]);
                $bankAccounts[0]['is_default'] = 1;
            }
        }
    }
}

// Handle Set Default Bank
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['set_default_bank'])) {
    if(!hash_equals($_SESSION['seller_wallet_csrf'],(string)($_POST['csrf_token']??''))) {
        $error = 'คำขอหมดอายุ กรุณาลองใหม่';
    } else {
        $bankId = (int)$_POST['bank_id'];
        $pdo->prepare("UPDATE vendor_bank_accounts SET is_default=0 WHERE seller_type=? AND seller_id=?")->execute([$sellerType, $sellerId]);
        $pdo->prepare("UPDATE vendor_bank_accounts SET is_default=1 WHERE id=? AND seller_type=? AND seller_id=?")->execute([$bankId, $sellerType, $sellerId]);
        $success = 'ตั้งเป็นบัญชีหลักเรียบร้อยแล้ว';
        $banksStmt->execute([$sellerType, $sellerId]);
        $bankAccounts = $banksStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$statYear = isset($_GET['stat_year']) ? (int)$_GET['stat_year'] : (int)date('Y');
$statMonth = isset($_GET['stat_month']) ? (int)$_GET['stat_month'] : 0;

if ($statMonth > 0) {
    // Fetch total stats (For selected month)
    $statsStmt = $pdo->prepare("SELECT COUNT(DISTINCT order_id) as total_orders, SUM(amount) as total_earnings FROM wallet_transactions WHERE wallet_id=? AND type='EARNING' AND status='SUCCESS' AND YEAR(created_at) = ? AND MONTH(created_at) = ?");
    $statsStmt->execute([$wallet['wallet_id'], $statYear, $statMonth]);
    
    // Fetch Growth (Selected Month vs Previous Month)
    $prevMonthDate = date('Y-m-d', strtotime("$statYear-$statMonth-01 -1 month"));
    $prevYear = (int)date('Y', strtotime($prevMonthDate));
    $prevMonth = (int)date('m', strtotime($prevMonthDate));
    
    $growthStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN amount ELSE 0 END) as current_period,
            SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN amount ELSE 0 END) as previous_period
        FROM wallet_transactions 
        WHERE wallet_id=? AND type='EARNING' AND status='SUCCESS'
    ");
    $growthStmt->execute([$statMonth, $statYear, $prevMonth, $prevYear, $wallet['wallet_id']]);
} else {
    // Fetch total stats (For selected year)
    $statsStmt = $pdo->prepare("SELECT COUNT(DISTINCT order_id) as total_orders, SUM(amount) as total_earnings FROM wallet_transactions WHERE wallet_id=? AND type='EARNING' AND status='SUCCESS' AND YEAR(created_at) = ?");
    $statsStmt->execute([$wallet['wallet_id'], $statYear]);
    
    // Fetch Growth (Selected Year vs Previous Year)
    $prevYear = $statYear - 1;
    $growthStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN YEAR(created_at) = ? THEN amount ELSE 0 END) as current_period,
            SUM(CASE WHEN YEAR(created_at) = ? THEN amount ELSE 0 END) as previous_period
        FROM wallet_transactions 
        WHERE wallet_id=? AND type='EARNING' AND status='SUCCESS'
    ");
    $growthStmt->execute([$statYear, $prevYear, $wallet['wallet_id']]);
}
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Fetch Total Withdrawn
$withdrawnStmt = $pdo->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE wallet_id=? AND type='WITHDRAWAL' AND status='SUCCESS'");
$withdrawnStmt->execute([$wallet['wallet_id']]);
$totalWithdrawn = (float)$withdrawnStmt->fetchColumn();

$growthData = $growthStmt->fetch(PDO::FETCH_ASSOC);
$currentPeriod = (float)($growthData['current_period'] ?? 0);
$previousPeriod = (float)($growthData['previous_period'] ?? 0);
$growthPct = 0;
if($previousPeriod > 0) {
    $growthPct = (($currentPeriod - $previousPeriod) / $previousPeriod) * 100;
} elseif ($currentPeriod > 0) {
    $growthPct = 100;
}

// Fetch Transactions
$txStmt = $pdo->prepare("SELECT t.*, CONCAT('WD-', DATE_FORMAT(t.created_at, '%Y%m%d'), '-', LPAD(t.transaction_id, 4, '0')) as txn_code, o.order_number, o.subtotal, o.shipping_fee, o.gp_amount FROM wallet_transactions t LEFT JOIN orders o ON t.order_id = o.order_id WHERE t.wallet_id=? ORDER BY t.created_at DESC LIMIT 100");
$txStmt->execute([$wallet['wallet_id']]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>รายได้ทั้งหมด - วนวรรณ</title>
<link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-family);background:#fff;color:var(--text-dark);display:flex;min-height:100vh}
.sidebar{width:260px;min-height:100vh;background:#f9e1eb;display:flex;flex-direction:column;padding:25px 16px;position:fixed}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:0 12px 30px}
.sidebar-logo img{width:74px}
.sidebar-logo-text{font-size:24px;color:var(--secondary-color);font-weight:700}
.sidebar-logo-sub,.profile-role{font-size:11px;color:var(--primary-color)}
.sidebar-nav{display:flex;flex-direction:column;gap:9px;flex:1}
.nav-item{display:flex;align-items:center;gap:12px;padding:13px 15px;border-radius:8px;text-decoration:none;color:#91576d}
.nav-item svg{width:20px;height:20px}
.nav-item.active,.nav-item:hover{background:#d18aa7;color:#fff}
.sidebar-profile{display:flex;gap:10px;align-items:center;padding:15px}
.profile-avatar{width:40px;height:40px;border-radius:50%;background:#fff;display:grid;place-items:center;overflow:hidden}
.profile-avatar img{width:100%;height:100%;object-fit:cover}
.profile-seller,.profile-name{font-size:12px;font-weight:700}
.main{margin-left:260px;padding:55px 38px;width:calc(100% - 260px)}
.head{display:flex;justify-content:space-between;align-items:center;margin-bottom: 28px;}
.head h1{color:var(--secondary-color);font-size:30px}

.wallet-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:24px;margin-bottom:28px;}
.wallet-card {background: linear-gradient(135deg, #713c51 0%, #91576d 40%, #c9829f 100%);color: #fff;border-radius: 20px;padding: 35px;position: relative;overflow: hidden;box-shadow: 0 15px 35px rgba(145, 87, 109, 0.3);transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;}
.wallet-card:hover {transform: translateY(-8px);box-shadow: 0 22px 45px rgba(145, 87, 109, 0.4);}
.wallet-card::before {content: "";position: absolute;top: -50%;left: -50%;width: 200%;height: 200%;background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 60%);transform: rotate(30deg);pointer-events: none;}
.wallet-card::after {content: "฿";position: absolute;right: -20px;bottom: -60px;font-size: 320px;opacity: 0.06;font-weight: 900;line-height: 1;transform: rotate(-15deg);pointer-events: none;}
.wallet-card h3 {font-size: 16px;font-weight: 500;opacity: 0.9;margin-bottom: 15px;letter-spacing: 0.5px;}
.wallet-card .balance {font-size: 48px;font-weight: 800;letter-spacing: -1px;text-shadow: 0 4px 15px rgba(0,0,0,0.15);display: flex;align-items: center;gap: 8px;z-index:1;position:relative;}
.wallet-card .balance span {font-size: 24px;font-weight: 500;opacity: 0.8;}
.wallet-card .balance-pending {font-size: 14px;opacity: 0.9;margin-top: 12px;display: inline-block;background: rgba(255,255,255,0.15);padding: 6px 12px;border-radius: 20px;backdrop-filter: blur(5px);border:1px solid rgba(255,255,255,0.2);z-index:1;position:relative;}
.withdraw-btn {display: inline-flex;align-items: center;justify-content: center;background: #fff;color: #91576d;border: none;border-radius: 12px;padding: 12px 28px;font-size: 16px;font-weight: 700;margin-top: 25px;cursor: pointer;transition: all 0.3s ease;font-family: inherit;box-shadow: 0 4px 15px rgba(0,0,0,0.1);gap: 8px;z-index:1;position:relative;}
.withdraw-btn:hover {transform: translateY(-3px) scale(1.02);box-shadow: 0 8px 25px rgba(0,0,0,0.15);color: #713c51;}
.withdraw-btn:active {transform: translateY(1px);}

.stats-card{border:1px solid #dfbdc8;border-radius:16px;padding:30px;background:#fff;}
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.stat-item{padding: 20px;background: #fdf5f8;border-radius: 12px;text-align: center;}
.stat-item h4{font-size: 14px;color: #91576d;font-weight: normal;margin-bottom: 8px;}
.stat-item strong{font-size: 24px;color: #d18aa7;}

.notice{padding:12px 16px;border-radius:8px;margin:15px 0}
.ok{background:#e9f5e5;color:#39703a}
.bad{background:#fde5e1;color:#a33}

.tablebox{overflow:auto;border:1px solid #e3c3ce;border-radius:14px;}
.tablebox table{width:100%;border-collapse:collapse;min-width:700px;}
.tablebox th,.tablebox td{padding:15px;border-bottom:1px solid #ead6dd;text-align:left;}
.tablebox th{background: #fdf5f8;color: #91576d;}
.badge{padding:5px 10px;border-radius:18px;font-size:11px;font-weight:700;}
.badge-earn{background:#e9f5e5;color:#39703a;}
.badge-withdraw{background:#fde5e1;color:#a33;}
.badge-success{background:#e9f5e5;color:#39703a;}
.badge-pending{background:#fff3cd;color:#856404;}
.badge-failed{background:#fde5e1;color:#a33;}

.modal{position:fixed;inset:0;background:#0006;display:none;align-items:center;justify-content:center;padding:20px;z-index:500}
.modal.open{display:flex}
.modalbox{background:#fff;border-radius:16px;padding:28px;width:min(450px,100%);}
.modalbox h2{color: var(--secondary-color);margin-bottom: 20px;}
.field{margin-bottom: 15px;}
.field label{display: block;font-size: 14px;color: #555;margin-bottom: 5px;}
.field input, .field select {width:100%;padding:11px;border:1px solid #ccc;border-radius:7px;font-size:16px;font-family: inherit;}
.field p.hint{font-size: 12px;color: #888;margin-top: 5px;}
.bank-info{background: #f9f9f9;padding: 15px;border-radius: 8px;margin-bottom: 20px;font-size: 14px;line-height: 1.6;}
.actions{display:flex;gap:10px;margin-top: 20px;}
.btn{border:0;border-radius:8px;padding:11px 20px;font-weight:600;cursor:pointer;flex:1;font-family: inherit;}
.btn-primary{background:var(--secondary-color);color:#fff;}
.btn-secondary{background:#f0f0f0;color:#555;}
.bank-list-item { background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #eee; }
.bank-list-item.is-default { border-color: #dfbdc8; background: #fdf5f8; }
.bank-details { flex: 1; }
.bank-actions { display: flex; gap: 8px; flex-direction:column;}
</style>
</head>
<body>
<?php include __DIR__.'/../components/sidebar.php';?>
<main class="main">
    <div class="head">
        <h1>รายได้ทั้งหมด</h1>
    </div>

    <div class="wallet-grid">
        <div class="wallet-card">
            <h3>ยอดเงินที่สามารถถอนได้</h3>
            <div class="balance">
                <span>฿</span><?=number_format($wallet['available_balance'], 2)?>
            </div>
            <?php if($wallet['pending_withdrawal_balance'] > 0): ?>
            <div class="balance-pending">กำลังดำเนินการถอน: ฿<?=number_format($wallet['pending_withdrawal_balance'], 2)?></div>
            <?php endif; ?>
            
            <div style="display:flex; gap:10px; margin-top:25px; z-index:1; position:relative; flex-wrap:wrap;">
                <button class="withdraw-btn" id="openWithdrawModal" style="margin-top:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M9 19h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2z"></path><circle cx="16" cy="14" r="2"></circle></svg>
                    ถอนเงิน
                </button>
                <button class="withdraw-btn" id="openManageBankModal" style="margin-top:0; background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3); backdrop-filter:blur(5px);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    จัดการบัญชี
                </button>
            </div>
        </div>
        
        <div class="stats-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                <h3 style="color:#91576d;margin:0;">สถิติภาพรวม</h3>
                <form method="get" style="display:flex; gap:10px;">
                    <select name="stat_month" onchange="this.form.submit()" style="padding:6px; border:1px solid #ccc; border-radius:6px; font-family:inherit; color:#555; background:#fff;">
                        <option value="0">ตลอดทั้งปี</option>
                        <?php 
                        $months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                        foreach($months as $idx => $mName) {
                            $mVal = $idx + 1;
                            $selected = ($statMonth == $mVal) ? 'selected' : '';
                            echo "<option value=\"$mVal\" $selected>$mName</option>";
                        }
                        ?>
                    </select>
                    <select name="stat_year" onchange="this.form.submit()" style="padding:6px; border:1px solid #ccc; border-radius:6px; font-family:inherit; color:#555; background:#fff;">
                        <?php for($y=date('Y'); $y>=2023; $y--): ?>
                        <option value="<?=$y?>" <?=($statYear==$y)?'selected':''?>><?=$y?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
            <div class="stats-grid" style="grid-template-columns:1fr 1fr;gap:15px;">
                <div class="stat-item" style="padding:15px;">
                    <h4>รายได้สุทธิ (<?=$statMonth>0 ? 'เดือนที่เลือก' : 'ปีที่เลือก'?>)</h4>
                    <strong>฿<?=number_format($stats['total_earnings']??0, 2)?></strong>
                </div>
                <div class="stat-item" style="padding:15px;">
                    <h4>ถอนเงินไปแล้วทั้งหมด</h4>
                    <strong style="color:#d18aa7;">฿<?=number_format($totalWithdrawn, 2)?></strong>
                </div>
                <div class="stat-item" style="padding:15px;">
                    <h4>คำสั่งซื้อที่สำเร็จแล้ว</h4>
                    <strong><?=number_format($stats['total_orders']??0)?></strong>
                </div>
                <div class="stat-item" style="padding:15px;">
                    <h4>อัตราการเติบโต (<?=$statMonth>0 ? 'เทียบเดือนก่อน' : 'เทียบปีก่อน'?>)</h4>
                    <strong style="color: <?=$growthPct>=0?'#39703a':'#a33'?>">
                        <?=$growthPct>0?'+':''?><?=number_format($growthPct, 1)?>%
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <h3 style="color:#91576d;margin-bottom:15px;">ประวัติธุรกรรม (Transaction History)</h3>
    <div class="tx-tabs" style="display:flex;gap:10px;margin-bottom:15px;">
        <button class="tx-tab active" data-filter="all" style="padding:8px 16px;border:none;background:#91576d;color:#fff;border-radius:20px;cursor:pointer;font-weight:600;font-family:inherit;">ทั้งหมด</button>
        <button class="tx-tab" data-filter="earn" style="padding:8px 16px;border:none;background:#e9ecef;color:#495057;border-radius:20px;cursor:pointer;font-weight:600;font-family:inherit;">รายได้</button>
        <button class="tx-tab" data-filter="withdraw" style="padding:8px 16px;border:none;background:#e9ecef;color:#495057;border-radius:20px;cursor:pointer;font-weight:600;font-family:inherit;">การถอนเงิน</button>
    </div>
    <div class="tablebox">
        <table>
            <thead>
                <tr>
                    <th>วันที่-เวลา</th>
                    <th>ประเภท</th>
                    <th>จำนวนเงิน (บาท)</th>
                    <th>สถานะ</th>
                    <th>รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!$transactions): ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:40px;color:#888;">ยังไม่มีประวัติธุรกรรม</td>
                </tr>
                <?php else: foreach($transactions as $tx): ?>
                <tr class="tx-row" data-type="<?=in_array($tx['type'],['EARNING','REFUND'])?'earn':'withdraw'?>">
                    <td><?=date('d/m/Y H:i', strtotime($tx['created_at']))?></td>
                    <td>
                        <?php if($tx['type']==='EARNING'): ?>
                            <span class="badge badge-earn">รายได้จากยอดขาย</span>
                        <?php else: ?>
                            <span class="badge badge-withdraw">ถอนเงิน</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="color: <?=$tx['type']==='EARNING'?'#39703a':'#a33'?>">
                            <?=$tx['type']==='EARNING'?'+':'-'?>฿<?=number_format($tx['amount'],2)?>
                        </strong>
                    </td>
                    <td>
                        <?php
                        $sClass = 'pending'; $sText = 'รอดำเนินการ';
                        if($tx['status']==='SUCCESS') { $sClass = 'success'; $sText = 'สำเร็จ'; }
                        if($tx['status']==='FAILED' || $tx['status']==='REJECTED') { $sClass = 'failed'; $sText = 'ล้มเหลว'; }
                        ?>
                        <span class="badge badge-<?=$sClass?>"><?=$sText?></span>
                    </td>
                    <td>
                        <?php if($tx['type']==='EARNING'): ?>
                            <div style="font-size:13px; color:#444; line-height:1.6;">
                                <strong style="color:#91576d;">ออเดอร์ #<?=htmlspecialchars($tx['order_number'] ?? $tx['order_id'])?></strong><br>
                                <span style="color:#666;">ยอดขาย: ฿<?=number_format($tx['subtotal'] ?? 0, 2)?></span><br>
                                <span style="color:#d32f2f;">หักค่าธรรมเนียม: -฿<?=number_format($tx['gp_amount'] ?? 0, 2)?></span><br>
                                <span style="color:#388e3c;">ค่าจัดส่ง: +฿<?=number_format($tx['shipping_fee'] ?? 0, 2)?></span><br>
                                <button type="button" class="btn-sm" style="font-family:inherit; margin-top:8px; padding:4px 10px; font-size:12px; background:#f0f0f0; color:#555; border:1px solid #ddd; border-radius:10px; cursor:pointer;" onclick="openOrderModal(<?=$tx['order_id']?>, '<?=htmlspecialchars($tx['order_number'] ?? $tx['order_id'])?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:3px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>ดูรายละเอียด
                                </button>
                            </div>
                        <?php elseif($tx['type']==='REFUND'): ?>
                            <small style="color:#777">คืนเงินออเดอร์ #<?=htmlspecialchars($tx['order_number'] ?? $tx['order_id'])?></small>
                                <?php else: ?>
                                    <small style="color:#777" title="<?=htmlspecialchars($tx['bank_account_info'])?>">รหัสอ้างอิง: <strong><?=htmlspecialchars($tx['txn_code'])?></strong><br>เข้าบัญชีธนาคาร<br><?=nl2br(htmlspecialchars($tx['bank_account_info']))?></small>
                                <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</main>

<div class="modal" id="withdrawModal">
    <div class="modalbox">
        <h2>ถอนเงินเข้าบัญชี</h2>
        
        <form method="post" id="withdrawForm">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['seller_wallet_csrf'])?>">
            <input type="hidden" name="withdraw" value="1">
            
            <div class="field">
                <label>เลือกบัญชีรับเงิน</label>
                <?php if(empty($bankAccounts)): ?>
                    <div style="color:#a33; margin-bottom:10px; font-size:14px;">ยังไม่มีบัญชีรับเงิน กรุณาเพิ่มบัญชีก่อนทำรายการ</div>
                <?php else: ?>
                    <select name="bank_account_id" required style="width:100%;padding:11px;border:1px solid #ccc;border-radius:7px;font-size:14px;font-family:inherit;background:#f9f9f9;">
                        <?php foreach($bankAccounts as $b): ?>
                        <option value="<?=$b['id']?>"><?=htmlspecialchars($b['bank_name'].' - '.$b['account_name'].' ('.$b['account_number'].')')?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

        <form method="post" id="withdrawForm">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['seller_wallet_csrf'])?>">
            <input type="hidden" name="withdraw" value="1">
            
            <div class="field">
                <label>จำนวนเงินที่ต้องการถอน (บาท)</label>
                <input type="number" name="amount" step="0.01" min="0" data-max="<?=$wallet['available_balance']?>" required placeholder="0.00">
                <p class="hint">ยอดเงินที่ถอนได้: ฿<?=number_format($wallet['available_balance'], 2)?> (ขั้นต่ำ 100 บาท)</p>
            </div>
            
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="closeWithdrawModal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">ยืนยันการถอนเงิน</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="manageBankModal" style="z-index:600">
    <div class="modalbox" style="width:min(500px,100%);">
        <h2>จัดการบัญชีรับเงิน</h2>
        
        <div style="margin-bottom: 20px;">
            <?php foreach($bankAccounts as $b): ?>
            <div class="bank-list-item <?=$b['is_default']?'is-default':''?>">
                <div class="bank-details">
                    <strong style="color:var(--secondary-color);"><?=htmlspecialchars($b['bank_name'])?></strong>
                    <span style="font-size:12px; <?=$b['is_default']?'color:#91576d;font-weight:bold;':''?>"><?=$b['is_default']?'(บัญชีหลัก)':''?></span><br>
                    <span style="font-size:13px; color:#555;">ชื่อ: <?=htmlspecialchars($b['account_name'])?></span><br>
                    <span style="font-size:13px; color:#555;">เลขที่: <?=htmlspecialchars($b['account_number'])?></span>
                </div>
                <div class="bank-actions">
                    <?php if(!$b['is_default']): ?>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['seller_wallet_csrf'])?>">
                        <input type="hidden" name="set_default_bank" value="1">
                        <input type="hidden" name="bank_id" value="<?=$b['id']?>">
                        <button type="submit" class="btn" style="padding:6px 10px; font-size:11px; background:#e9f5e5; color:#39703a; width:100%;">ตั้งหลัก</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="margin:0;" class="delete-bank-form">
                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['seller_wallet_csrf'])?>">
                        <input type="hidden" name="delete_bank" value="1">
                        <input type="hidden" name="bank_id" value="<?=$b['id']?>">
                        <button type="submit" class="btn" style="padding:6px 10px; font-size:11px; background:#fde5e1; color:#a33; width:100%;">ลบ</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if(count($bankAccounts) < 3): ?>
        <h4 style="margin-bottom:10px; color:#91576d;">+ เพิ่มบัญชีใหม่ (เพิ่มได้สูงสุด 3 บัญชี)</h4>
        <form method="post" style="background:#f9f9f9; padding:15px; border-radius:8px; border:1px solid #eee;">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['seller_wallet_csrf'])?>">
            <input type="hidden" name="add_bank" value="1">
            <div class="field">
                <select name="bank_name" required style="width:100%;padding:9px;border:1px solid #ccc;border-radius:7px;font-size:14px;background:#fff;font-family:inherit;">
                    <option value="">-- เลือกธนาคาร/ช่องทางรับเงิน --</option>
                    <?php
                    $banks = ['ธนาคารกสิกรไทย (KBANK)','ธนาคารไทยพาณิชย์ (SCB)','ธนาคารกรุงเทพ (BBL)','ธนาคารกรุงไทย (KTB)','ธนาคารกรุงศรีอยุธยา (BAY)','ธนาคารทหารไทยธนชาต (ttb)','ธนาคารออมสิน (GSB)','ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร (BAAC)','พร้อมเพย์ (PromptPay)'];
                    foreach($banks as $bName) echo "<option value=\"".htmlspecialchars($bName)."\">".htmlspecialchars($bName)."</option>";
                    ?>
                </select>
            </div>
            <div class="field" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                <input type="text" name="bank_account_name" required placeholder="ชื่อบัญชี" style="padding:9px;border:1px solid #ccc;border-radius:7px;font-size:14px;font-family:inherit;">
                <input type="text" name="bank_account_number" required placeholder="เลขบัญชี / พร้อมเพย์" style="padding:9px;border:1px solid #ccc;border-radius:7px;font-size:14px;font-family:inherit;">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; padding:9px;">เพิ่มบัญชี</button>
        </form>
        <?php else: ?>
        <div style="text-align:center; color:#888; font-size:13px; margin-bottom:15px; padding:10px; background:#f9f9f9; border-radius:8px;">เพิ่มบัญชีรับเงินครบ 3 บัญชีแล้ว</div>
        <?php endif; ?>
        
        <div class="actions">
            <button type="button" class="btn btn-secondary" id="closeManageBankModal">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal" id="orderModal">
    <div class="modal-box" style="width: 500px; max-width: 90vw; padding: 0; overflow: hidden; background: #f4f7f6; border-radius: 12px;">
        <style>
        #orderItemsContainer::-webkit-scrollbar { display: none; }
        </style>
        <!-- Header -->
        <div style="background: #00796B; color: #fff; padding: 25px 20px 20px 20px; position: relative;">
            <h2 id="modalBuyerName" style="margin: 0 0 5px 0; font-size: 20px;">กำลังโหลด...</h2>
            <div id="modalOrderSubInfo" style="font-size: 13px; opacity: 0.9;"></div>
        </div>
        
        <!-- Content -->
        <div id="orderItemsContainer" style="max-height: 70vh; overflow-y: auto; padding: 0; scrollbar-width: none; -ms-overflow-style: none;">
            <!-- Loaded via AJAX -->
        </div>
    </div>
</div>

<script>
async function openOrderModal(orderId, orderNumber) {
    document.getElementById('modalBuyerName').innerText = 'กำลังโหลด...';
    document.getElementById('modalOrderSubInfo').innerText = '';
    const container = document.getElementById('orderItemsContainer');
    container.innerHTML = '<div style="text-align:center; padding:40px; color:#888;">กำลังโหลดข้อมูล...</div>';
    document.getElementById('orderModal').classList.add('open');

    try {
        const res = await fetch('?ajax_order_details=1&order_id=' + orderId);
        const data = await res.json();
        const order = data.order;
        const items = data.items;
        
        if(!order || items.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#888;">ไม่พบข้อมูล</div>';
            return;
        }

        // Translate Status
        let statusText = 'กำลังดำเนินการ';
        if(order.shipping_status === 'delivered') statusText = 'สำเร็จแล้ว';
        else if(order.shipping_status === 'shipped') statusText = 'กำลังจัดส่ง';
        else if(order.shipping_status === 'cancelled') statusText = 'ยกเลิกแล้ว';
        else if(order.payment_status === 'paid') statusText = 'ชำระเงินแล้ว';

        document.getElementById('modalBuyerName').innerText = order.buyer_name;
        document.getElementById('modalOrderSubInfo').innerText = statusText + ' · #' + order.order_number;

        let html = '';

        // 1. Items
        html += `
        <div style="background: #fff; margin-bottom: 10px; padding: 20px;">
            <div style="color: #00796B; font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>
                รายการสินค้า
            </div>
            <div style="display:flex; flex-direction:column; gap:15px;">
        `;
        items.forEach(item => {
            const cover = item.image_cover ? (item.image_cover.startsWith('http') || item.image_cover.startsWith('/') ? item.image_cover : '/uploads/books/' + item.image_cover) : '/assets/images/default_book.png';
            html += `
                <div style="display:flex; justify-content: space-between; align-items: center;">
                    <div style="display:flex; gap:15px; align-items: center;">
                        <img src="${cover}" style="width:50px; height:50px; object-fit:cover; border-radius:8px; border:1px solid #eee;">
                        <div>
                            <div style="color: #333; font-weight: 500; font-size: 14px; margin-bottom: 4px;">${item.title}</div>
                            <div style="color: #888; font-size: 12px;">· x${item.quantity}</div>
                        </div>
                    </div>
                    <div style="color: #00796B; font-weight: bold; font-size: 14px;">
                        ฿${parseFloat(item.price_at_purchase).toFixed(2)}
                    </div>
                </div>
            `;
        });
        html += `</div></div>`;

        // 2. Shipping Info
        html += `
        <div style="background: #fff; margin-bottom: 10px; padding: 20px;">
            <div style="color: #00796B; font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                ข้อมูลการจัดส่ง
            </div>
            
            <div style="display:flex; justify-content:space-between; margin-bottom: 10px; font-size:13px;">
                <div style="color:#666;">ผู้รับ</div>
                <div style="color:#333; font-weight:500;">${order.shipping_name}</div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom: 10px; font-size:13px;">
                <div style="color:#666;">เบอร์โทร</div>
                <div style="color:#333; font-weight:500;">${order.shipping_phone}</div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom: 15px; font-size:13px;">
                <div style="color:#666;">ที่อยู่</div>
                <div style="color:#333; font-weight:500; text-align:right; max-width:60%;">${order.shipping_address}</div>
            </div>
        `;
        if (order.shipping_method) {
            html += `
            <div style="background: #f0f7f4; border: 1px solid #c8e6c9; padding: 12px 15px; border-radius: 8px;">
                <div style="color: #2e7d32; font-size: 13px; margin-bottom: 5px;"><strong>บริษัทขนส่ง:</strong> ${order.shipping_method}</div>
                <div style="color: #2e7d32; font-size: 13px;"><strong>หมายเลขพัสดุ:</strong> ${order.tracking_number || '-'}</div>
            </div>
            `;
        }
        html += `</div>`;

        // 3. Summary (GP)
        html += `
        <div style="background: #fff; padding: 20px;">
            <div style="color: #00796B; font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                สรุปยอดรายได้ระบบ (GP)
            </div>
            
            <div style="display:flex; justify-content:space-between; margin-bottom: 10px; font-size:13px;">
                <div style="color:#666;">ยอดรวมสินค้า</div>
                <div style="color:#333; font-weight:500;">฿${parseFloat(order.subtotal).toFixed(2)}</div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom: 10px; font-size:13px;">
                <div style="color:#d32f2f;">หักค่าธรรมเนียมการขาย (10%)</div>
                <div style="color:#333; font-weight:500;">- ฿${parseFloat(order.gp_amount).toFixed(2)}</div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom: 15px; font-size:13px;">
                <div style="color:#666;">ค่าจัดส่งที่ลูกค้าจ่าย</div>
                <div style="color:#333; font-weight:500;">+ ฿${parseFloat(order.shipping_fee).toFixed(2)}</div>
            </div>
            
            <div style="border-top: 1px dashed #ddd; margin: 15px 0;"></div>
            
            <div style="display:flex; justify-content:space-between; font-size:14px;">
                <div style="color:#666;">ยอดสุทธิโอนให้ร้านค้า</div>
                <div style="color:#333; font-weight:bold;">฿${parseFloat(order.net_payout).toFixed(2)}</div>
            </div>
        </div>
        
        <div style="padding: 10px 20px 20px 20px;">
            <button type="button" onclick="document.getElementById('orderModal').classList.remove('open')" style="font-family: inherit; width: 100%; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 8px; color: #555; font-size: 15px; font-weight: bold; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='#fff'">ปิดหน้าต่าง</button>
        </div>
        `;

        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:red;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
    }
}

document.getElementById('openManageBankModal').onclick = function() {
    document.getElementById('manageBankModal').classList.add('open');
};
document.getElementById('closeManageBankModal').onclick = function() {
    document.getElementById('manageBankModal').classList.remove('open');
};
document.getElementById('manageBankModal').onclick = function(e) {
    if(e.target === this) this.classList.remove('open');
};

document.getElementById('openWithdrawModal').onclick = function() {
    document.getElementById('withdrawModal').classList.add('open');
};
document.getElementById('closeWithdrawModal').onclick = function() {
    document.getElementById('withdrawModal').classList.remove('open');
};
document.getElementById('withdrawModal').onclick = function(e) {
    if(e.target === this) this.classList.remove('open');
};

document.getElementById('withdrawForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const amountInput = this.querySelector('input[name="amount"]');
    const amount = parseFloat(amountInput.value);
    const maxAmount = parseFloat(amountInput.getAttribute('data-max'));
    
    if (isNaN(amount) || amount < 100) {
        Swal.fire({
            icon: 'warning',
            title: 'ไม่ถึงขั้นต่ำ',
            text: 'ยอดถอนขั้นต่ำคือ 100 บาท',
            confirmButtonColor: '#91576d'
        });
        return;
    }
    if (amount > maxAmount) {
        Swal.fire({
            icon: 'warning',
            title: 'ยอดเงินไม่พอ',
            text: 'ยอดเงินที่คุณต้องการถอนมีมากกว่ายอดเงินที่สามารถถอนได้ (฿' + maxAmount.toFixed(2) + ')',
            confirmButtonColor: '#91576d'
        });
        return;
    }
    
    Swal.fire({
        title: 'ยืนยันการถอนเงิน?',
        text: "คุณต้องการถอนเงินจำนวน ฿" + amount.toFixed(2) + " เข้าบัญชีใช่หรือไม่?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#91576d',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});

document.querySelectorAll('.delete-bank-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'ยืนยันการลบบัญชี?',
            text: "คุณต้องการลบบัญชีรับเงินนี้ใช่หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#a33',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'ลบ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });
});

<?php if(isset($_POST['withdraw'])): ?>
    <?php if($success): ?>
    Swal.fire({
        icon: 'success',
        title: 'ส่งคำขอสำเร็จ',
        text: <?=json_encode($success)?>,
        confirmButtonColor: '#91576d'
    });
    <?php else: ?>
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: <?=json_encode($error)?>,
        confirmButtonColor: '#91576d'
    }).then(() => {
        document.getElementById('withdrawModal').classList.add('open');
    });
    <?php endif; ?>
<?php endif; ?>

<?php if(isset($_POST['add_bank']) || isset($_POST['delete_bank']) || isset($_POST['set_default_bank'])): ?>
    <?php if($success): ?>
    Swal.fire({
        icon: 'success',
        title: 'สำเร็จ',
        text: <?=json_encode($success)?>,
        confirmButtonColor: '#91576d'
    }).then(() => {
        document.getElementById('manageBankModal').classList.add('open');
    });
    <?php else: ?>
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: <?=json_encode($error)?>,
        confirmButtonColor: '#91576d'
    }).then(() => {
        document.getElementById('manageBankModal').classList.add('open');
    });
    <?php endif; ?>
<?php endif; ?>

document.querySelectorAll('.tx-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tx-tab').forEach(b => {
            b.style.background = '#e9ecef';
            b.style.color = '#495057';
            b.classList.remove('active');
        });
        this.style.background = '#91576d';
        this.style.color = '#fff';
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        document.querySelectorAll('.tx-row').forEach(row => {
            if(filter === 'all' || row.dataset.type === filter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>
</body>
</html>
