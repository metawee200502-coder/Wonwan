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

// Fetch Total Withdrawn
$withdrawnStmt = $pdo->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE wallet_id=? AND type='WITHDRAWAL' AND status='SUCCESS'");
$withdrawnStmt->execute([$wallet['wallet_id']]);
$totalWithdrawn = (float)$withdrawnStmt->fetchColumn();

// Fetch Transactions
$txStmt = $pdo->prepare("SELECT t.*, CONCAT('WD-', DATE_FORMAT(t.created_at, '%Y%m%d'), '-', LPAD(t.transaction_id, 4, '0')) as txn_code, o.order_number, o.subtotal, o.shipping_fee, o.gp_amount FROM wallet_transactions t LEFT JOIN orders o ON t.order_id = o.order_id WHERE t.wallet_id=? ORDER BY t.created_at DESC LIMIT 100");
$txStmt->execute([$wallet['wallet_id']]);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);
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
        .account{display:flex;width:100%;min-height:900px}
        .wallet-content{flex:1;max-width:1040px;margin:0 auto;padding:38px 50px 90px;min-width:0}
        .title{font-size:34px;color:var(--secondary-color);margin:0}
        .subtitle{margin:4px 0 28px;color:var(--text-muted)}
        
        .wallet-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:24px;margin-bottom:28px;}
        @media(max-width:900px){.wallet-grid{grid-template-columns:1fr;}}
        .wallet-card {background: linear-gradient(135deg, #134e5e 0%, #71b280 100%);color: #fff;border-radius: 20px;padding: 35px;position: relative;overflow: hidden;box-shadow: 0 15px 35px rgba(27, 88, 101, 0.3);transition: transform 0.4s ease;}
        .wallet-card:hover {transform: translateY(-5px);box-shadow: 0 22px 45px rgba(27, 88, 101, 0.4);}
        .wallet-card::before {content: "";position: absolute;top: -50%;left: -50%;width: 200%;height: 200%;background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 60%);transform: rotate(30deg);pointer-events: none;}
        .wallet-card h3 {font-size: 16px;font-weight: 500;opacity: 0.9;margin-bottom: 15px;}
        .wallet-card .balance {font-size: 48px;font-weight: 800;letter-spacing: -1px;text-shadow: 0 4px 15px rgba(0,0,0,0.15);display: flex;align-items: center;gap: 8px;position:relative;z-index:1;}
        .wallet-card .balance span {font-size: 24px;font-weight: 500;opacity: 0.8;}
        .wallet-card .balance-pending {font-size: 14px;opacity: 0.9;margin-top: 12px;display: inline-block;background: rgba(255,255,255,0.15);padding: 6px 12px;border-radius: 20px;backdrop-filter: blur(5px);border:1px solid rgba(255,255,255,0.2);position:relative;z-index:1;}
        
        .withdraw-btn {display: inline-flex;align-items: center;justify-content: center;background: #fff;color: #134e5e;border: none;border-radius: 12px;padding: 12px 28px;font-size: 16px;font-weight: 700;margin-top: 25px;cursor: pointer;transition: all 0.3s ease;font-family: inherit;box-shadow: 0 4px 15px rgba(0,0,0,0.1);gap: 8px;position:relative;z-index:1;}
        .withdraw-btn:hover {transform: translateY(-3px) scale(1.02);box-shadow: 0 8px 25px rgba(0,0,0,0.15);}
        .withdraw-btn:active {transform: translateY(1px);}
        
        .stats-card{border:1px solid #d5e5e0;border-radius:16px;padding:30px;background:#fff;}
        .stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        .stat-item{padding: 20px;background: #f4fbf8;border-radius: 12px;text-align: center;}
        .stat-item h4{font-size: 14px;color: #2b6a5d;font-weight: normal;margin-bottom: 8px;}
        .stat-item strong{font-size: 24px;color: #1a4d42;}
        
        .tablebox{overflow:auto;border:1px solid #d5e5e0;border-radius:14px;background:#fff;}
        .tablebox table{width:100%;border-collapse:collapse;min-width:700px;}
        .tablebox th,.tablebox td{padding:15px;border-bottom:1px solid #eef5f3;text-align:left;}
        .tablebox th{background: #f4fbf8;color: #2b6a5d;}
        .badge{padding:5px 10px;border-radius:18px;font-size:11px;font-weight:700;}
        .badge-earn{background:#e9f5e5;color:#39703a;}
        .badge-withdraw{background:#fde5e1;color:#a33;}
        .badge-success{background:#e9f5e5;color:#39703a;}
        .badge-pending{background:#fff3cd;color:#856404;}
        .badge-failed{background:#fde5e1;color:#a33;}

        .modal{position:fixed;inset:0;background:#0006;display:none;align-items:center;justify-content:center;padding:20px;z-index:1000}
        .modal.open{display:flex}
        .modalbox{background:#fff;border-radius:16px;padding:28px;width:min(450px,100%);max-height:90vh;overflow-y:auto;}
        .modalbox h2{color: var(--secondary-color);margin-bottom: 20px;}
        .field{margin-bottom: 15px;}
        .field label{display: block;font-size: 14px;color: #555;margin-bottom: 5px;}
        .field input, .field select {width:100%;padding:11px;border:1px solid #ccc;border-radius:7px;font-size:16px;font-family: inherit;}
        .field p.hint{font-size: 12px;color: #888;margin-top: 5px;}
        .actions{display:flex;gap:10px;margin-top: 20px;}
        .btn{border:0;border-radius:8px;padding:11px 20px;font-weight:600;cursor:pointer;flex:1;font-family: inherit;}
        .btn-primary{background:var(--secondary-color);color:#fff;}
        .btn-secondary{background:#f0f0f0;color:#555;}
        .bank-list-item { background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #eee; }
        .bank-list-item.is-default { border-color: #71b280; background: #f4fbf8; }
        .bank-details { flex: 1; }
        .bank-actions { display: flex; gap: 8px; flex-direction:column;}
    </style>
</head>
<body>
    <?php require __DIR__.'/../components/header.php';?>
    <div class="account">
        <?php require __DIR__.'/../components/profile_sidebar.php';?>
        <main class="wallet-content">
            <h1 class="title">Wonwan Wallet</h1>
            <p class="subtitle">กระเป๋าเงินสำหรับรายได้จากการขายสินค้ามือสองและการคืนเงิน</p>
            
            <div class="wallet-grid">
                <div class="wallet-card">
                    <h3>ยอดเงินที่สามารถถอนได้</h3>
                    <div class="balance">
                        <span>฿</span><?=number_format($wallet['available_balance'] ?? 0, 2)?>
                    </div>
                    <?php if(($wallet['pending_withdrawal_balance'] ?? 0) > 0): ?>
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
                    <h3 style="color:#2b6a5d;margin-bottom:20px;">สถิติภาพรวม</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <h4>รายได้สุทธิทั้งหมด</h4>
                            <strong>฿<?=number_format($stats['total_earnings']??0, 2)?></strong>
                        </div>
                        <div class="stat-item">
                            <h4>ถอนเงินไปแล้วทั้งหมด</h4>
                            <strong style="color:#2d5f6d;">฿<?=number_format($totalWithdrawn, 2)?></strong>
                        </div>
                        <div class="stat-item" style="grid-column: span 2;">
                            <h4>คำสั่งซื้อที่สำเร็จแล้ว</h4>
                            <strong><?=number_format($stats['total_orders']??0)?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <h3 style="color:#2b6a5d;margin-bottom:15px;margin-top:20px;">ประวัติธุรกรรม (Transaction History)</h3>
            <div class="tx-tabs" style="display:flex;gap:10px;margin-bottom:15px;">
                <button class="tx-tab active" data-filter="all" style="padding:8px 16px;border:none;background:#2b6a5d;color:#fff;border-radius:20px;cursor:pointer;font-weight:600;font-family:inherit;">ทั้งหมด</button>
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
                                <?php elseif($tx['type']==='REFUND'): ?>
                                    <span class="badge badge-earn" style="background:#e3f2fd;color:#1565c0;">ได้รับเงินคืน</span>
                                <?php else: ?>
                                    <span class="badge badge-withdraw">ถอนเงิน</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: <?=in_array($tx['type'],['EARNING','REFUND'])?'#39703a':'#a33'?>">
                                    <?=in_array($tx['type'],['EARNING','REFUND'])?'+':'-'?>฿<?=number_format($tx['amount'],2)?>
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
                                        <strong style="color:#2b6a5d;">ออเดอร์ #<?=htmlspecialchars($tx['order_number'] ?? $tx['order_id'])?></strong><br>
                                        <span style="color:#666;">ยอดขาย: ฿<?=number_format($tx['subtotal'] ?? 0, 2)?></span><br>
                                        <span style="color:#d32f2f;">หักค่าธรรมเนียม: -฿<?=number_format($tx['gp_amount'] ?? 0, 2)?></span><br>
                                        <span style="color:#388e3c;">ค่าจัดส่ง: +฿<?=number_format($tx['shipping_fee'] ?? 0, 2)?></span>
                                    </div>
                                <?php elseif($tx['type']==='REFUND'): ?>
                                    <small style="color:#777">คืนเงินออเดอร์ #<?=htmlspecialchars($tx['order_number'] ?? $tx['order_id'])?></small>
                                <?php else: ?>
                                    <small style="color:#777" title="<?=htmlspecialchars($tx['bank_account_info'])?>">รหัสอ้างอิง: <strong style="color:#2b6a5d;"><?=htmlspecialchars($tx['txn_code'])?></strong><br>เข้าบัญชีธนาคาร<br><?=nl2br(htmlspecialchars($tx['bank_account_info']))?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
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
                        <div style="color:#a33; margin-bottom:10px; font-size:14px;">ยังไม่มีบัญชีรับเงิน กรุณาเพิ่มบัญชีก่อนทำรายการ</div>
                    <?php else: ?>
                        <select name="bank_account_id" required style="width:100%;padding:11px;border:1px solid #ccc;border-radius:7px;font-size:14px;background:#f9f9f9;">
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
                    <button type="submit" class="btn btn-primary" style="background:#134e5e;color:#fff;">ยืนยันการถอนเงิน</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="manageBankModal" style="z-index:1100">
        <div class="modalbox" style="width:min(500px,100%);">
            <h2>จัดการบัญชีรับเงิน</h2>
            
            <div style="margin-bottom: 20px;">
                <?php foreach($bankAccounts as $b): ?>
                <div class="bank-list-item <?=$b['is_default']?'is-default':''?>">
                    <div class="bank-details">
                        <strong style="color:<?=$b['is_default']?'#2b6a5d':'#444'?>;"><?=htmlspecialchars($b['bank_name'])?></strong>
                        <span style="font-size:12px; <?=$b['is_default']?'color:#71b280;font-weight:bold;':''?>"><?=$b['is_default']?'(บัญชีหลัก)':''?></span><br>
                        <span style="font-size:13px; color:#555;">ชื่อ: <?=htmlspecialchars($b['account_name'])?></span><br>
                        <span style="font-size:13px; color:#555;">เลขที่: <?=htmlspecialchars($b['account_number'])?></span>
                    </div>
                    <div class="bank-actions">
                        <?php if(!$b['is_default']): ?>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['user_wallet_csrf'])?>">
                            <input type="hidden" name="set_default_bank" value="1">
                            <input type="hidden" name="bank_id" value="<?=$b['id']?>">
                            <button type="submit" class="btn" style="padding:6px 10px; font-size:11px; background:#e9f5e5; color:#39703a; width:100%;">ตั้งหลัก</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" style="margin:0;" class="delete-bank-form">
                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['user_wallet_csrf'])?>">
                            <input type="hidden" name="delete_bank" value="1">
                            <input type="hidden" name="bank_id" value="<?=$b['id']?>">
                            <button type="submit" class="btn" style="padding:6px 10px; font-size:11px; background:#fde5e1; color:#a33; width:100%;">ลบ</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if(count($bankAccounts) < 3): ?>
            <h4 style="margin-bottom:10px; color:#2b6a5d;">+ เพิ่มบัญชีใหม่ (เพิ่มได้สูงสุด 3 บัญชี)</h4>
            <form method="post" style="background:#f9f9f9; padding:15px; border-radius:8px; border:1px solid #eee;">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['user_wallet_csrf'])?>">
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
                <button type="submit" class="btn btn-primary" style="width:100%; padding:9px;background:#134e5e;">เพิ่มบัญชี</button>
            </form>
            <?php else: ?>
            <div style="text-align:center; color:#888; font-size:13px; margin-bottom:15px; padding:10px; background:#f9f9f9; border-radius:8px;">เพิ่มบัญชีรับเงินครบ 3 บัญชีแล้ว</div>
            <?php endif; ?>
            
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="closeManageBankModal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
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
            Swal.fire({ icon: 'warning', title: 'ไม่ถึงขั้นต่ำ', text: 'ยอดถอนขั้นต่ำคือ 100 บาท', confirmButtonColor: '#134e5e' });
            return;
        }
        if (amount > maxAmount) {
            Swal.fire({ icon: 'warning', title: 'ยอดเงินไม่พอ', text: 'ยอดเงินที่คุณต้องการถอนมีมากกว่ายอดเงินที่สามารถถอนได้ (฿' + maxAmount.toFixed(2) + ')', confirmButtonColor: '#134e5e' });
            return;
        }
        
        Swal.fire({
            title: 'ยืนยันการถอนเงิน?',
            text: "คุณต้องการถอนเงินจำนวน ฿" + amount.toFixed(2) + " เข้าบัญชีใช่หรือไม่?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#134e5e',
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
        Swal.fire({ icon: 'success', title: 'ส่งคำขอสำเร็จ', text: <?=json_encode($success)?>, confirmButtonColor: '#134e5e' });
        <?php else: ?>
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: <?=json_encode($error)?>, confirmButtonColor: '#134e5e' })
        .then(() => { document.getElementById('withdrawModal').classList.add('open'); });
        <?php endif; ?>
    <?php endif; ?>

    <?php if(isset($_POST['add_bank']) || isset($_POST['delete_bank']) || isset($_POST['set_default_bank'])): ?>
        <?php if($success): ?>
        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: <?=json_encode($success)?>, confirmButtonColor: '#134e5e' })
        .then(() => { document.getElementById('manageBankModal').classList.add('open'); });
        <?php else: ?>
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: <?=json_encode($error)?>, confirmButtonColor: '#134e5e' });
        <?php endif; ?>
    <?php endif; ?>
    
    document.querySelectorAll('.tx-tab').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tx-tab').forEach(b => {
                b.style.background = '#e9ecef';
                b.style.color = '#495057';
                b.classList.remove('active');
            });
            this.style.background = '#2b6a5d';
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
