<?php
session_start();
require_once __DIR__.'/../config/config.php';

if(empty($_SESSION['user_id'])){header('Location:login.php');exit;}
$userId=(int)$_SESSION['user_id'];
$sellerType = 'user';
$sellerId = $userId;

$profile_active = 'wallet';

// CSRF Token
if(empty($_SESSION['user_wallet_csrf']))$_SESSION['user_wallet_csrf']=bin2hex(random_bytes(24));
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
    if(!hash_equals($_SESSION['user_wallet_csrf'],(string)($_POST['csrf_token']??''))) {
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
            $selectedBank = $bankAccounts[0];
        }

        if (!$selectedBank) {
            $error = 'กรุณาเพิ่มบัญชีรับเงินก่อนทำการถอน';
        } elseif ($amount < 100) {
            $error = 'ยอดถอนขั้นต่ำคือ 100 บาท';
        } elseif ($amount > (float)$wallet['available_balance']) {
            $error = 'ยอดเงินที่ถอนได้ไม่เพียงพอ';
        } else {
            $pdo->beginTransaction();
            try {
                $lockStmt = $pdo->prepare("SELECT available_balance FROM vendor_wallets WHERE wallet_id=? FOR UPDATE");
                $lockStmt->execute([$wallet['wallet_id']]);
                $currentBalance = (float)$lockStmt->fetchColumn();
                
                if ($amount <= $currentBalance) {
                    $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance - ?, pending_withdrawal_balance = pending_withdrawal_balance + ? WHERE wallet_id=?")->execute([$amount, $amount, $wallet['wallet_id']]);
                    
                    $bankInfo = "ธนาคาร: " . $selectedBank['bank_name'] . "\nชื่อบัญชี: " . $selectedBank['account_name'] . "\nเลขบัญชี: " . $selectedBank['account_number'];
                    $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, status, bank_account_info, withdrawal_bank_info) VALUES (?, 'WITHDRAWAL', ?, 'PENDING', ?, ?)")->execute([$wallet['wallet_id'], $amount, $bankInfo, json_encode($selectedBank, JSON_UNESCAPED_UNICODE)]);
                    
                    $pdo->commit();
                    $success = 'สร้างรายการถอนเงินสำเร็จ กรุณารอระบบดำเนินการโอนเงิน';
                    
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
    if(!hash_equals($_SESSION['user_wallet_csrf'],(string)($_POST['csrf_token']??''))) {
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
                $banksStmt->execute([$sellerType, $sellerId]);
                $bankAccounts = $banksStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
}

// Handle Delete Bank
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_bank'])) {
    if(!hash_equals($_SESSION['user_wallet_csrf'],(string)($_POST['csrf_token']??''))) {
        $error = 'คำขอหมดอายุ กรุณาลองใหม่';
    } else {
        $bankId = (int)$_POST['bank_id'];
        $pdo->prepare("DELETE FROM vendor_bank_accounts WHERE id=? AND seller_type=? AND seller_id=?")->execute([$bankId, $sellerType, $sellerId]);
        $success = 'ลบบัญชีรับเงินเรียบร้อยแล้ว';
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
    if(!hash_equals($_SESSION['user_wallet_csrf'],(string)($_POST['csrf_token']??''))) {
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

// Stats
$statsStmt = $pdo->prepare("SELECT COUNT(DISTINCT o.order_id) as total_orders, SUM(o.net_payout) as total_earnings FROM orders o JOIN order_items oi ON o.order_id=oi.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type=? AND b.seller_id=? AND o.shipping_status='delivered'");
$statsStmt->execute([$sellerType, $sellerId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Escrow Stats
$escrowStmt = $pdo->prepare("SELECT SUM(o.net_payout) as escrow_amount FROM orders o JOIN order_items oi ON o.order_id=oi.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type=? AND b.seller_id=? AND o.payment_status='paid' AND o.shipping_status != 'delivered' AND o.shipping_status != 'cancelled' AND o.shipping_status != 'returned'");
$escrowStmt->execute([$sellerType, $sellerId]);
$escrowAmount = (float)$escrowStmt->fetchColumn();

// Fetch Total Withdrawn
$withdrawnStmt = $pdo->prepare("SELECT SUM(amount), COUNT(transaction_id) FROM wallet_transactions WHERE wallet_id=? AND type='WITHDRAWAL' AND status='SUCCESS'");
$withdrawnStmt->execute([$wallet['wallet_id']]);
$withdrawnRow = $withdrawnStmt->fetch(PDO::FETCH_NUM);
$totalWithdrawn = (float)($withdrawnRow[0] ?? 0);
$totalWithdrawCount = (int)($withdrawnRow[1] ?? 0);

// Fetch Transactions
$txStmt = $pdo->prepare("SELECT t.*, CONCAT('WD-', DATE_FORMAT(t.created_at, '%Y%m%d'), '-', LPAD(t.transaction_id, 4, '0')) as txn_code, o.order_number, o.subtotal, o.shipping_fee, o.gp_amount, (SELECT b.title FROM order_items oi JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id = t.order_id LIMIT 1) as book_title FROM wallet_transactions t LEFT JOIN orders o ON t.order_id = o.order_id WHERE t.wallet_id=? ORDER BY t.created_at DESC LIMIT 100");
$txStmt->execute([$wallet['wallet_id']]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$txAll = count($transactions);
$txEarn = 0; $txWithdraw = 0; $txRefund = 0;
foreach($transactions as $tx) {
    if($tx['type']==='EARNING') $txEarn++;
    elseif($tx['type']==='WITHDRAWAL') $txWithdraw++;
    elseif($tx['type']==='REFUND') $txRefund++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Wonwan Wallet - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *{box-sizing:border-box}
        body {background: #fcfcfc;}
        .account{display:flex;width:100%;min-height:900px;background:#fcfcfc;}
        .wallet-content{flex:1;max-width:1160px;margin:0 auto;padding:36px 42px 90px;min-width:0}
        
        .header-area { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
        .breadcrumb { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
        .breadcrumb a { color: #6b7280; text-decoration: none; }
        .title{margin:0 0 8px;color:#111827;font-size:32px;font-weight:700;}
        .subtitle { color: #6b7280; font-size: 14px; margin: 0; line-height: 1.5; }
        .header-meta { font-size: 13px; color: #9ca3af; }
        
        .wallet-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;}
        @media(max-width:950px){.wallet-grid{grid-template-columns:1fr;}}
        
        .wallet-card {background: #115e59; color: #fff;border-radius: 20px;padding: 32px;position: relative;overflow: hidden;box-shadow: 0 4px 6px -1px rgba(17, 94, 89, 0.1);display: flex; flex-direction: column; justify-content: space-between; min-height: 260px;}
        .wallet-card::after {content: "฿";position: absolute;bottom: -40px;right: 10px;font-size: 240px;font-weight: 700;color: rgba(255,255,255,0.06);line-height: 1;pointer-events: none;}
        
        .wallet-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; z-index: 1; position: relative; }
        .wallet-card-header h3 {font-size: 16px;font-weight: 600;margin:0; display: flex; align-items: center; gap: 8px;}
        .wallet-card-header h3 svg { opacity: 0.8; }
        .badge-ready { background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; backdrop-filter: blur(4px); }
        
        .wallet-card-body { z-index: 1; position: relative; margin-bottom: 24px; }
        .balance {font-size: 56px;font-weight: 700;letter-spacing: -1px;display: flex;align-items: center;gap: 8px; line-height: 1; margin-bottom: 8px;}
        .balance span {font-size: 28px;font-weight: 600;opacity: 0.8;}
        .wallet-card-body p { margin: 0; font-size: 13px; opacity: 0.9; display: flex; align-items: center; gap: 6px; }
        
        .wallet-card-footer { display: flex; justify-content: space-between; align-items: center; z-index: 1; position: relative; }
        .btn-group { display: flex; gap: 12px; }
        .btn-withdraw { background: #fff; color: #115e59; border: none; border-radius: 12px; padding: 10px 24px; font-size: 14px; font-weight: 700; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 8px; font-family: inherit;}
        .btn-withdraw:hover { background: #f3f4f6; }
        .btn-manage { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,0.4); border-radius: 12px; padding: 10px 24px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 8px; font-family: inherit;}
        .btn-manage:hover { background: rgba(255,255,255,0.1); }
        .auto-withdraw-link { color: #fff; font-size: 13px; text-decoration: none; opacity: 0.9; }
        .auto-withdraw-link:hover { text-decoration: underline; }
        
        .stats-card { background: #fff; border: 1px solid #f3f4f6; border-radius: 20px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; }
        .stats-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .stats-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px; }
        .badge-alltime { background: #f3f4f6; color: #4b5563; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; flex: 1; }
        .stat-box { border: 1px solid #f3f4f6; border-radius: 16px; padding: 20px; display: flex; flex-direction: column; justify-content: center; }
        .stat-box.highlight { background: #f0fdf4; border-color: #dcfce7; }
        .stat-box-title { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
        .stat-box-value { font-size: 24px; font-weight: 700; color: #111827; margin: 0 0 6px 0; }
        .stat-box-sub { font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        .text-green { color: #059669; }
        .text-gray { color: #9ca3af; }
        
        .history-section { background: #fff; border: 1px solid #f3f4f6; border-radius: 20px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 32px; }
        .history-header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        .history-header h2 { margin: 0; font-size: 20px; font-weight: 700; color: #111827; }
        .badge-count { background: #f3f4f6; color: #4b5563; padding: 4px 12px; border-radius: 16px; font-size: 13px; font-weight: 600; }
        
        .tools-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .tabs button { padding: 8px 16px; border-radius: 20px; background: #fff; border: 1px solid #e5e7eb; color: #4b5563; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s; font-family: inherit;}
        .tabs button.active { background: #115e59; color: #fff; border-color: #115e59; }
        .tabs button:not(.active):hover { background: #f9fafb; }
        
        .filters { display: flex; gap: 12px; }
        .search-box { position: relative; }
        .search-box input { height: 38px; padding: 0 16px 0 36px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; font: inherit; font-size: 13px; width: 240px; outline: none;}
        .search-box svg { position: absolute; left: 12px; top: 11px; color: #9ca3af; width: 16px; height: 16px; }
        .filter-btn { height: 38px; padding: 0 16px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; color: #374151; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-family: inherit; }
        .filter-btn:hover { background: #f9fafb; }
        
        .tx-table { width: 100%; border-collapse: collapse; text-align: left; }
        .tx-table th { padding: 16px; font-size: 13px; font-weight: 600; color: #6b7280; border-bottom: 1px solid #f3f4f6; }
        .tx-table td { padding: 16px; border-bottom: 1px solid #f9fafb; vertical-align: middle; }
        
        .tx-date { font-size: 14px; color: #111827; }
        .tx-time { font-size: 12px; color: #9ca3af; }
        
        .tx-type { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 16px; font-size: 12px; font-weight: 600; }
        .tx-type.withdraw { color: #dc2626; border: 1px solid #fecaca; }
        .tx-type.earn { color: #059669; border: 1px solid #a7f3d0; }
        
        .tx-amount { font-size: 16px; font-weight: 700; }
        .tx-amount.red { color: #dc2626; }
        .tx-amount.green { color: #059669; }
        .tx-sub { font-size: 11px; color: #9ca3af; display: block; margin-top: 2px; }
        
        .tx-status { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 16px; font-size: 12px; font-weight: 600; }
        .tx-status.success { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
        .tx-status.pending { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
        .tx-status.failed { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }
        
        .tx-detail { font-size: 13px; color: #4b5563; line-height: 1.6; }
        .tx-detail strong { color: #111827; }
        .tx-detail-muted { color: #6b7280; }
        .tx-detail-red { color: #dc2626; }
        .tx-detail-green { color: #059669; }
        
        .tx-action-btn { background: #fff; border: 1px solid #e5e7eb; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; color: #374151; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;}
        .tx-action-btn:hover { background: #f9fafb; }
        
        .pagination { display: flex; justify-content: space-between; align-items: center; padding-top: 24px; font-size: 13px; color: #6b7280; }
        .page-controls { display: flex; gap: 8px; }
        .page-btn { padding: 6px 12px; border: 1px solid #e5e7eb; background: #fff; border-radius: 6px; color: #374151; font-weight: 500; cursor: pointer; }
        .page-btn.active { background: #115e59; color: #fff; border-color: #115e59; }
        
        .bottom-banner { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px 32px; display: flex; gap: 20px; align-items: flex-start; }
        .shield-icon { width: 48px; height: 48px; background: #ecfdf5; color: #059669; border-radius: 12px; display: grid; place-items: center; flex-shrink: 0; }
        .banner-text h3 { margin: 0 0 8px 0; font-size: 16px; font-weight: 700; color: #111827; }
        .banner-text p { margin: 0 0 12px 0; font-size: 13px; color: #4b5563; line-height: 1.6; }
        .banner-links { display: flex; gap: 24px; font-size: 13px; font-weight: 600; }
        .banner-links a { color: #115e59; text-decoration: none; }
        .banner-links a:hover { text-decoration: underline; }
        .banner-links span { color: #4b5563; cursor: pointer; }

        /* Modals */
        .modal{position:fixed;inset:0;background:rgba(17,24,39,0.5);display:none;align-items:center;justify-content:center;padding:20px;z-index:1000; backdrop-filter: blur(4px);}
        .modal.open{display:flex}
        .modalbox{background:#fff;border-radius:16px;padding:32px;width:min(500px,100%);max-height:90vh;overflow-y:auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); font-family: inherit;}
        .modalbox h2{color: #111827;margin: 0 0 24px 0; font-size: 20px;}
        .field{margin-bottom: 20px;}
        .field label{display: block;font-size: 14px;font-weight:600;color: #374151;margin-bottom: 8px;}
        .field input, .field select {width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;font-family: inherit; outline:none;}
        .field input:focus, .field select:focus { border-color: #115e59; }
        .field p.hint{font-size: 13px;color: #6b7280;margin-top: 6px;}
        .actions{display:flex;gap:12px;margin-top: 24px; justify-content: flex-end;}
        .btn{border:1px solid transparent;border-radius:8px;padding:10px 20px;font-weight:600;cursor:pointer;font-family: inherit; font-size: 14px;}
        .btn-primary{background:#115e59;color:#fff;}
        .btn-primary:hover{background:#0f5132;}
        .btn-secondary{background:#fff;border-color:#d1d5db;color:#374151;}
        .btn-secondary:hover{background:#f9fafb;}
        
        .bank-list-item { background: #fff; padding: 16px; border-radius: 12px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e5e7eb; }
        .bank-list-item.is-default { border-color: #34d399; background: #f0fdf4; }
        .bank-details { flex: 1; }
        .bank-actions { display: flex; gap: 8px; flex-direction:column;}
        .btn-small { padding: 6px 12px; font-size: 12px; border-radius: 6px; font-weight: 600; cursor: pointer; border: 1px solid transparent;}

    </style>
</head>
<body>
    <?php require __DIR__.'/../components/header.php';?>
    <div class="account">
        <?php require __DIR__.'/../components/profile_sidebar.php';?>
        <main class="wallet-content">
            
            <div class="header-area">
                <div>
                    <div class="breadcrumb">
                        <a href="/">หน้าหลัก</a> &gt; <a href="/pages/profile.php">จัดการบัญชี</a> &gt; <strong style="color:#111827;">Wonwan Wallet</strong>
                    </div>
                    <h1 class="title">Wonwan Wallet</h1>
                    <p class="subtitle">กระเป๋าเงินสำหรับรายได้จากการขายสินค้ามือสองและการคืนเงิน ปลอดภัยด้วยมาตรฐานระบบ Escrow</p>
                </div>
                <div class="header-meta">
                    อัปเดตล่าสุด: วันนี้ <?=date('H:i')?> น.
                </div>
            </div>
            
            <div class="wallet-grid">
                <div class="wallet-card">
                    <div class="wallet-card-header">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><path d="M12 12h.01"></path><path d="M17 12h.01"></path><path d="M7 12h.01"></path></svg> 
                            ยอดเงินที่สามารถถอนได้ (Available Balance)
                        </h3>
                        <span class="badge-ready">• พร้อมโอนออก</span>
                    </div>
                    <div class="wallet-card-body">
                        <div class="balance">
                            <span>฿</span> <?=number_format($wallet['available_balance'] ?? 0, 2)?>
                        </div>
                        <p>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            พร้อมโอนเข้าบัญชีธนาคารได้ทันที • ถอนขั้นต่ำ ฿100.00
                        </p>
                    </div>
                    <div class="wallet-card-footer">
                        <div class="btn-group">
                            <button class="btn-withdraw" id="openWithdrawModal">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
                                ถอนเงิน
                            </button>
                            <button class="btn-manage" id="openManageBankModal">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                จัดการบัญชีธนาคาร
                            </button>
                        </div>
                        <a href="#" class="auto-withdraw-link">ตั้งค่าถอนอัตโนมัติ</a>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-header">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"></circle></svg>
                            สถิติภาพรวม
                        </h3>
                        <span class="badge-alltime">ตลอดเวลา</span>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-box highlight">
                            <div class="stat-box-title">รายได้สุทธิทั้งหมด</div>
                            <div class="stat-box-value">฿<?=number_format($stats['total_earnings']??0, 2)?></div>
                            <div class="stat-box-sub text-green">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                +12.5% เดือนนี้
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-box-title">ถอนเงินไปแล้วทั้งหมด</div>
                            <div class="stat-box-value">฿<?=number_format($totalWithdrawn, 2)?></div>
                            <div class="stat-box-sub text-gray">
                                • โอนสำเร็จ <?=number_format($totalWithdrawCount)?> ครั้ง
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-box-title">คำสั่งซื้อที่สำเร็จ</div>
                            <div class="stat-box-value"><?=number_format($stats['total_orders']??0)?> รายการ</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-box-title">ยอดเงินรอตรวจสอบ</div>
                            <div class="stat-box-value">฿<?=number_format($escrowAmount, 2)?></div>
                            <div class="stat-box-sub text-gray" style="justify-content: flex-end;">Escrow</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="history-section">
                <div class="history-header">
                    <h2>ประวัติธุรกรรม (Transaction History)</h2>
                    <span class="badge-count"><?=$txAll?> รายการ</span>
                </div>
                
                <div class="tools-bar">
                    <div class="tabs">
                        <button class="tx-tab active" data-filter="all">ทั้งหมด (<?=$txAll?>)</button>
                        <button class="tx-tab" data-filter="earn">รายได้จากยอดขาย (<?=$txEarn?>)</button>
                        <button class="tx-tab" data-filter="withdraw">การถอนเงิน (<?=$txWithdraw?>)</button>
                        <button class="tx-tab" data-filter="refund">เงินคืน (<?=$txRefund?>)</button>
                    </div>
                    <div class="filters">
                        <div class="search-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" placeholder="ค้นหารหัส, ออเดอร์, ธนาคาร...">
                        </div>
                        <button class="filter-btn">30 วันล่าสุด</button>
                        <button class="filter-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                            ส่งออก Statement
                        </button>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table class="tx-table">
                        <thead>
                            <tr>
                                <th>วันที่-เวลา</th>
                                <th>ประเภท</th>
                                <th>จำนวนเงิน (บาท)</th>
                                <th>สถานะ</th>
                                <th>รายละเอียดธุรกรรม</th>
                                <th>การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!$transactions): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:40px;color:#9ca3af;">ยังไม่มีประวัติธุรกรรม</td>
                            </tr>
                            <?php else: foreach($transactions as $tx): ?>
                            <tr class="tx-row" data-type="<?=strtolower($tx['type'])==='earning'?'earn':(strtolower($tx['type'])==='refund'?'refund':'withdraw')?>">
                                <td>
                                    <div class="tx-date"><?=date('d/m/Y', strtotime($tx['created_at']))?></div>
                                    <div class="tx-time"><?=date('H:i', strtotime($tx['created_at']))?> น.</div>
                                </td>
                                <td>
                                    <?php if($tx['type']==='EARNING'): ?>
                                        <span class="tx-type earn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19V5a2 2 0 0 1 2-2h13.4a2 2 0 0 1 2 2v14"></path><path d="M2 15h10"></path><path d="M2 19h10"></path></svg> รายได้จากยอดขาย</span>
                                    <?php elseif($tx['type']==='REFUND'): ?>
                                        <span class="tx-type earn" style="color:#2563eb; border-color:#bfdbfe;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg> เงินคืน</span>
                                    <?php else: ?>
                                        <span class="tx-type withdraw"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg> ถอนเงิน</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(in_array($tx['type'],['EARNING','REFUND'])): ?>
                                        <div class="tx-amount green">+฿<?=number_format($tx['amount'],2)?></div>
                                        <span class="tx-sub">เข้ากระเป๋าเงินทันที</span>
                                    <?php else: ?>
                                        <div class="tx-amount red">-฿<?=number_format($tx['amount'],2)?></div>
                                        <span class="tx-sub">ฟรีค่าธรรมเนียม</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $sClass = 'pending'; $sText = 'รอดำเนินการ'; $sIcon = '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>';
                                    if($tx['status']==='SUCCESS') { $sClass = 'success'; $sText = 'สำเร็จ'; $sIcon = '<polyline points="20 6 9 17 4 12"></polyline>'; }
                                    if($tx['status']==='FAILED' || $tx['status']==='REJECTED') { $sClass = 'failed'; $sText = 'ล้มเหลว'; $sIcon = '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>'; }
                                    ?>
                                    <span class="tx-status <?=$sClass?>">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><?=$sIcon?></svg>
                                        <?=$sText?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($tx['type']==='EARNING'): ?>
                                        <div class="tx-detail">
                                            ออเดอร์: <strong>#<?=htmlspecialchars($tx['order_number'] ?? $tx['order_id'])?></strong><br>
                                            <span class="tx-detail-muted">📖 <?=htmlspecialchars($tx['book_title'] ?? 'หนังสือ')?></span><br>
                                            <span class="tx-detail-muted">ยอดขาย: ฿<?=number_format($tx['subtotal'] ?? 0, 2)?></span> 
                                            <span class="tx-detail-red">หักค่าธรรมเนียม: -฿<?=number_format($tx['gp_amount'] ?? 0, 2)?></span> 
                                            <span class="tx-detail-green">ค่าจัดส่งได้รับ: +฿<?=number_format($tx['shipping_fee'] ?? 0, 2)?></span>
                                        </div>
                                    <?php elseif($tx['type']==='REFUND'): ?>
                                        <div class="tx-detail">คืนเงินออเดอร์ #<?=htmlspecialchars($tx['order_number'] ?? $tx['order_id'])?></div>
                                    <?php else: ?>
                                        <?php 
                                            $bankName = ''; $accNo = ''; $accName = '';
                                            if (preg_match('/ธนาคาร:\s*(.*?)\nชื่อบัญชี:\s*(.*?)\nเลขบัญชี:\s*(.*)/', $tx['bank_account_info'], $matches)) {
                                                $bankName = trim($matches[1]);
                                                $accName = trim($matches[2]);
                                                $accNo = trim($matches[3]);
                                            }
                                        ?>
                                        <div class="tx-detail">
                                            รหัสอ้างอิง: <strong><?=htmlspecialchars($tx['txn_code'])?></strong><br>
                                            <span class="tx-detail-muted">🏦 <?=htmlspecialchars($bankName)?> · <?=htmlspecialchars($accNo)?></span><br>
                                            <span class="tx-detail-muted">ชื่อบัญชี: <?=htmlspecialchars($accName)?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(in_array($tx['type'],['EARNING','REFUND'])): ?>
                                        <a href="/pages/seller_market.php?search=<?=htmlspecialchars($tx['order_number'] ?? '')?>" class="tx-action-btn">ดูออเดอร์ <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
                                    <?php else: ?>
                                        <span style="color:#9ca3af; font-size:13px;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination">
                    <span>แสดง <?=$txAll?> จากทั้งหมด <?=$txAll?> รายการ</span>
                    <div class="page-controls">
                        <button class="page-btn">ก่อนหน้า</button>
                        <button class="page-btn active">1</button>
                        <button class="page-btn">ถัดไป</button>
                    </div>
                </div>
            </div>

            <div class="bottom-banner">
                <div class="shield-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <div>
                    <div class="banner-text">
                        <h3>ความปลอดภัยและการจัดการกระเป๋าเงิน Wanwan Wallet</h3>
                        <p>ยอดเงินจากการขายหนังสือมือสองจะเข้าสู่กระเป๋าเงินทันทีหลังจากผู้ซื้อกดยืนยันรับหนังสือ หรือพ้นกำหนดระยะเวลาตรวจสอบอัตโนมัติ 48 ชั่วโมง โดยระบบ Escrow ปลอดภัย 100%<br>สามารถสั่งคำขอถอนเงินเข้าบัญชีธนาคารในประเทศไทยได้ตลอด 24 ชม. ตรวจสอบสถานะการโอนได้แบบเรียลไทม์</p>
                    </div>
                    <div class="banner-links">
                        <a href="#">อ่านข้อกำหนดการเงินและเงื่อนไขกระเป๋าเงิน →</a>
                        <span>· ติดต่อฝ่ายสนับสนุนการเงิน</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <?php require __DIR__.'/../components/footer.php';?>

    <!-- Modals -->
    <div class="modal" id="withdrawModal">
        <div class="modalbox">
            <h2>ถอนเงินเข้าบัญชี</h2>
            <form method="post" id="withdrawForm">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['user_wallet_csrf'])?>">
                <input type="hidden" name="withdraw" value="1">
                
                <div class="field">
                    <label>เลือกบัญชีรับเงิน</label>
                    <?php if(empty($bankAccounts)): ?>
                        <div style="color:#dc2626; margin-bottom:10px; font-size:14px; font-weight:500;">ยังไม่มีบัญชีรับเงิน กรุณาเพิ่มบัญชีก่อนทำรายการ</div>
                    <?php else: ?>
                        <select name="bank_account_id" required>
                            <?php foreach($bankAccounts as $b): ?>
                            <option value="<?=$b['id']?>"><?=htmlspecialchars($b['bank_name'].' - '.$b['account_name'].' ('.$b['account_number'].')')?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label>จำนวนเงินที่ต้องการถอน (บาท)</label>
                    <input type="number" name="amount" step="0.01" min="0" data-max="<?=$wallet['available_balance'] ?? 0?>" required placeholder="0.00">
                    <p class="hint">ยอดเงินที่ถอนได้: ฿<?=number_format($wallet['available_balance'] ?? 0, 2)?> (ขั้นต่ำ 100 บาท)</p>
                </div>
                
                <div class="actions">
                    <button type="button" class="btn btn-secondary" id="closeWithdrawModal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">ยืนยันการถอนเงิน</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="manageBankModal" style="z-index:1100">
        <div class="modalbox">
            <h2>จัดการบัญชีรับเงิน</h2>
            
            <div style="margin-bottom: 24px;">
                <?php foreach($bankAccounts as $b): ?>
                <div class="bank-list-item <?=$b['is_default']?'is-default':''?>">
                    <div class="bank-details">
                        <strong style="color:<?=$b['is_default']?'#059669':'#111827'?>; font-size:14px;"><?=htmlspecialchars($b['bank_name'])?></strong>
                        <?php if($b['is_default']): ?><span style="font-size:12px; color:#059669; font-weight:700; margin-left:4px;">(บัญชีหลัก)</span><?php endif; ?><br>
                        <span style="font-size:13px; color:#4b5563;">ชื่อ: <?=htmlspecialchars($b['account_name'])?></span><br>
                        <span style="font-size:13px; color:#4b5563;">เลขที่: <?=htmlspecialchars($b['account_number'])?></span>
                    </div>
                    <div class="bank-actions">
                        <?php if(!$b['is_default']): ?>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['user_wallet_csrf'])?>">
                            <input type="hidden" name="set_default_bank" value="1">
                            <input type="hidden" name="bank_id" value="<?=$b['id']?>">
                            <button type="submit" class="btn-small" style="background:#ecfdf5; color:#059669;">ตั้งเป็นบัญชีหลัก</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" style="margin:0;" class="delete-bank-form">
                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['user_wallet_csrf'])?>">
                            <input type="hidden" name="delete_bank" value="1">
                            <input type="hidden" name="bank_id" value="<?=$b['id']?>">
                            <button type="submit" class="btn-small" style="background:#fef2f2; color:#dc2626;">ลบ</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if(count($bankAccounts) < 3): ?>
            <h4 style="margin:0 0 12px 0; color:#111827; font-size:14px;">+ เพิ่มบัญชีใหม่ (สูงสุด 3 บัญชี)</h4>
            <form method="post" style="background:#f9fafb; padding:16px; border-radius:12px; border:1px solid #f3f4f6;">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['user_wallet_csrf'])?>">
                <input type="hidden" name="add_bank" value="1">
                <div class="field" style="margin-bottom:12px;">
                    <select name="bank_name" required>
                        <option value="">-- เลือกธนาคาร/ช่องทางรับเงิน --</option>
                        <?php
                        $banks = ['ธนาคารกสิกรไทย (KBANK)','ธนาคารไทยพาณิชย์ (SCB)','ธนาคารกรุงเทพ (BBL)','ธนาคารกรุงไทย (KTB)','ธนาคารกรุงศรีอยุธยา (BAY)','ธนาคารทหารไทยธนชาต (ttb)','ธนาคารออมสิน (GSB)','ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร (BAAC)','พร้อมเพย์ (PromptPay)'];
                        foreach($banks as $bName) echo "<option value=\"".htmlspecialchars($bName)."\">".htmlspecialchars($bName)."</option>";
                        ?>
                    </select>
                </div>
                <div class="field" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <input type="text" name="bank_account_name" required placeholder="ชื่อบัญชี">
                    <input type="text" name="bank_account_number" required placeholder="เลขบัญชี / พร้อมเพย์">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">เพิ่มบัญชี</button>
            </form>
            <?php else: ?>
            <div style="text-align:center; color:#6b7280; font-size:13px; padding:12px; background:#f9fafb; border-radius:8px; border:1px dashed #d1d5db;">เพิ่มบัญชีรับเงินครบ 3 บัญชีแล้ว</div>
            <?php endif; ?>
            
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="closeManageBankModal" style="width:100%;">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('openManageBankModal').onclick = function(e) {
        e.preventDefault();
        document.getElementById('manageBankModal').classList.add('open');
    };
    document.getElementById('closeManageBankModal').onclick = function() {
        document.getElementById('manageBankModal').classList.remove('open');
    };
    document.getElementById('manageBankModal').onclick = function(e) {
        if(e.target === this) this.classList.remove('open');
    };

    document.getElementById('openWithdrawModal').onclick = function(e) {
        e.preventDefault();
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
            Swal.fire({ icon: 'warning', title: 'ไม่ถึงขั้นต่ำ', text: 'ยอดถอนขั้นต่ำคือ 100 บาท', confirmButtonColor: '#115e59' });
            return;
        }
        if (amount > maxAmount) {
            Swal.fire({ icon: 'warning', title: 'ยอดเงินไม่พอ', text: 'ยอดเงินที่คุณต้องการถอนมีมากกว่ายอดเงินที่สามารถถอนได้ (฿' + maxAmount.toFixed(2) + ')', confirmButtonColor: '#115e59' });
            return;
        }
        
        Swal.fire({
            title: 'ยืนยันการถอนเงิน?',
            text: "คุณต้องการถอนเงินจำนวน ฿" + amount.toFixed(2) + " เข้าบัญชีใช่หรือไม่?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#115e59',
            cancelButtonColor: '#9ca3af',
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
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#9ca3af',
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
        Swal.fire({ icon: 'success', title: 'ส่งคำขอสำเร็จ', text: <?=json_encode($success)?>, confirmButtonColor: '#115e59' });
        <?php else: ?>
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: <?=json_encode($error)?>, confirmButtonColor: '#115e59' })
        .then(() => { document.getElementById('withdrawModal').classList.add('open'); });
        <?php endif; ?>
    <?php endif; ?>

    <?php if(isset($_POST['add_bank']) || isset($_POST['delete_bank']) || isset($_POST['set_default_bank'])): ?>
        <?php if($success): ?>
        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: <?=json_encode($success)?>, confirmButtonColor: '#115e59' })
        .then(() => { document.getElementById('manageBankModal').classList.add('open'); });
        <?php else: ?>
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: <?=json_encode($error)?>, confirmButtonColor: '#115e59' });
        <?php endif; ?>
    <?php endif; ?>
    
    document.querySelectorAll('.tx-tab').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tx-tab').forEach(b => {
                b.classList.remove('active');
            });
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
