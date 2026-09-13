<?php
session_start();
require_once __DIR__ . '/../config/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

if (isset($_GET['ajax_history']) && isset($_GET['wallet_id'])) {
    header('Content-Type: application/json');
    $walletId = (int)$_GET['wallet_id'];
    $stmt = $pdo->prepare("SELECT amount, status, created_at, 'ถอนเงิน' as note FROM wallet_transactions WHERE wallet_id = ? AND type = 'WITHDRAWAL' ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$walletId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

$active_menu = 'withdraw_approvals';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $txnId = (int)$_POST['txn_id'];
    if ($_POST['action'] === 'approve') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE transaction_id = ? AND status = 'PENDING' FOR UPDATE");
            $stmt->execute([$txnId]);
            $txn = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($txn) {
                $pdo->prepare("UPDATE wallet_transactions SET status = 'SUCCESS' WHERE transaction_id = ?")->execute([$txnId]);
                $pdo->prepare("UPDATE vendor_wallets SET pending_withdrawal_balance = pending_withdrawal_balance - ? WHERE wallet_id = ?")->execute([$txn['amount'], $txn['wallet_id']]);
                
                logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'APPROVE_WITHDRAWAL', (string)$txnId, "อนุมัติการถอนเงินจำนวน {$txn['amount']} บาท");
                $pdo->commit();
                $success = "อนุมัติการถอนเงินเรียบร้อยแล้ว";
            } else {
                $pdo->rollBack();
                $error = "ไม่พบข้อมูล หรือรายการถูกจัดการไปแล้ว";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'reject') {
        $reason = $_POST['reason'] ?? '';
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE transaction_id = ? AND status = 'PENDING' FOR UPDATE");
            $stmt->execute([$txnId]);
            $txn = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($txn) {
                $pdo->prepare("UPDATE wallet_transactions SET status = 'REJECTED' WHERE transaction_id = ?")->execute([$txnId]);
                $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance + ?, pending_withdrawal_balance = pending_withdrawal_balance - ? WHERE wallet_id = ?")->execute([$txn['amount'], $txn['amount'], $txn['wallet_id']]);
                
                logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'REJECT_WITHDRAWAL', (string)$txnId, "ปฏิเสธการถอนเงินจำนวน {$txn['amount']} บาท. เหตุผล: {$reason}");
                $pdo->commit();
                $success = "ปฏิเสธการถอนเงินเรียบร้อยแล้ว";
            } else {
                $pdo->rollBack();
                $error = "ไม่พบข้อมูล หรือรายการถูกจัดการไปแล้ว";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Fetch Real Data
$filterStatus = $_GET['status'] ?? 'pending';
$statusCondition = "";
$orderBy = "DESC";
if ($filterStatus === 'pending') {
    $statusCondition = "AND wt.status = 'PENDING'";
    $orderBy = "ASC";
} elseif ($filterStatus === 'approved') {
    $statusCondition = "AND wt.status = 'SUCCESS'";
} elseif ($filterStatus === 'rejected') {
    $statusCondition = "AND wt.status IN ('REJECTED', 'FAILED')";
}

$stmt = $pdo->query("
    SELECT 
        wt.transaction_id as txn_id,
        CONCAT('WD-', DATE_FORMAT(wt.created_at, '%Y%m%d'), '-', LPAD(wt.transaction_id, 4, '0')) as txn_code,
        wt.amount as requested_amount,
        wt.withdrawal_bank_info,
        wt.created_at as date_time,
        wt.status,
        wt.wallet_id,
        vw.available_balance,
        COALESCE(u.display_name, s.shop_name, p.company_name) as user_name,
        COALESCE(u.user_id, s.shop_id, p.publisher_id) as user_id
    FROM wallet_transactions wt
    JOIN vendor_wallets vw ON wt.wallet_id = vw.wallet_id
    LEFT JOIN users u ON vw.seller_type = 'user' AND vw.seller_id = u.user_id
    LEFT JOIN shops s ON vw.seller_type = 'shop' AND vw.seller_id = s.shop_id
    LEFT JOIN publishers p ON vw.seller_type = 'publisher' AND vw.seller_id = p.publisher_id
    WHERE wt.type = 'WITHDRAWAL' $statusCondition
    ORDER BY wt.created_at $orderBy
");
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบอนุมัติการถอนเงิน - วนวรรณ (Admin)</title>
    
    <link rel="stylesheet" href="/assets/css/styles.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background: #fdfbfb; margin: 0; display: flex; min-height: 100vh; overflow-y: scroll; }
        .admin-main-wrap { flex: 1; min-width: 0; padding: 40px 50px; }
        
        .page-title { color: #00796B; font-size: 26px; margin-bottom: 25px; font-weight: bold; }
        
        .tablebox { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e0f2f1; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .orders { width: 100%; border-collapse: collapse; min-width: 800px; }
        .orders th, .orders td { padding: 18px 20px; border-bottom: 1px solid #f0f0f0; text-align: left; }
        .orders th { background: #f5fbfb; color: #555; font-weight: 600; font-size: 14px; }
        .orders tr:hover { background: #fafafa; }
        .orders td { color: #444; font-size: 15px; }
        
        .badge { padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; }
        .badge-pending { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        .badge-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .badge-danger { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        
        .action-btns { display: flex; gap: 8px; justify-content: center; }
        .btn-sm { padding: 6px 12px; border-radius: 8px; font-size: 13px; font-family: inherit; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
        .btn-approve { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .btn-approve:hover { background: #c8e6c9; }
        .btn-reject { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .btn-reject:hover { background: #ffcdd2; }
        .btn-history { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .btn-history:hover { background: #bbdefb; }
        
        .warning-text { color: #d32f2f; font-weight: bold; display: flex; align-items: center; gap: 5px; }
        .success-text { color: #2e7d32; font-weight: bold; }
        
        /* Modals (No Bootstrap JS required, custom modals matching admin theme) */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
        .modal.open { display: flex; }
        .modal-box { background: #fff; width: 450px; border-radius: 24px; padding: 30px; position: relative; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .modal-close { position: absolute; right: 25px; top: 25px; font-size: 24px; color: #999; cursor: pointer; background: none; border: none; padding: 0; }
        .modal-title { margin: 0 0 20px 0; color: #333; font-size: 20px; font-weight: bold; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; font-size: 14px; }
        .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 12px; font-family: inherit; font-size: 15px; box-sizing: border-box; transition: border-color 0.2s; resize: vertical; min-height: 80px; }
        .form-group textarea:focus { border-color: #00796B; outline: none; }
        
        .btn-submit { font-family: inherit; flex: 1; background: #00796B; color: #fff; border: none; padding: 12px; border-radius: 12px; font-weight: bold; font-size: 15px; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #00695C; }
        .btn-submit.danger { background: #d32f2f; }
        .btn-submit.danger:hover { background: #c62828; }
        .btn-cancel { font-family: inherit; flex: 1; background: #f0f0f0; color: #444; border: none; padding: 12px; border-radius: 12px; font-weight: bold; font-size: 15px; cursor: pointer; transition: background 0.2s; }
        .btn-cancel:hover { background: #e0e0e0; }
        
        .modal-actions { display: flex; gap: 10px; margin-top: 25px; }
        
        /* History Table in Modal */
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 12px 10px; border-bottom: 1px solid #eee; text-align: left; font-size: 14px; }
        .history-table th { color: #666; font-weight: 600; background: #fdfdfd; position: sticky; top: 0; }
        .history-table td { color: #444; }
    </style>
</head>
<body>

    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    
    <div class="admin-main-wrap">
        <?php if($success): ?>
            <script>Swal.fire({icon: 'success', title: 'สำเร็จ', text: '<?= htmlspecialchars($success) ?>', timer: 2000, showConfirmButton: false});</script>
        <?php endif; ?>
        <?php if($error): ?>
            <script>Swal.fire({icon: 'error', title: 'ผิดพลาด', text: '<?= htmlspecialchars($error) ?>'});</script>
        <?php endif; ?>
        
        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 25px;">
            <h1 class="page-title" style="margin-bottom: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: bottom; margin-right: 5px;"><path d="M2 12h4l2-9 5 18 3-10h6"></path></svg>
                ระบบอนุมัติการถอนเงิน
            </h1>
            
            <div style="position: relative; width: 300px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 12px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="searchInput" placeholder="ค้นหา รหัสอ้างอิง, ชื่อ, ID..." style="width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #ddd; border-radius: 10px; font-family: inherit; font-size: 15px; box-sizing: border-box; outline: none; transition: border-color 0.2s;">
                <style>#searchInput:focus { border-color: #00796B; }</style>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-bottom:20px;">
            <a href="?status=pending" style="padding:8px 16px; border-radius:20px; text-decoration:none; font-weight:600; font-size:14px; background:<?= $filterStatus==='pending' ? '#00796B' : '#e9ecef' ?>; color:<?= $filterStatus==='pending' ? '#fff' : '#495057' ?>;">รอตรวจสอบ</a>
            <a href="?status=approved" style="padding:8px 16px; border-radius:20px; text-decoration:none; font-weight:600; font-size:14px; background:<?= $filterStatus==='approved' ? '#00796B' : '#e9ecef' ?>; color:<?= $filterStatus==='approved' ? '#fff' : '#495057' ?>;">อนุมัติแล้ว</a>
            <a href="?status=rejected" style="padding:8px 16px; border-radius:20px; text-decoration:none; font-weight:600; font-size:14px; background:<?= $filterStatus==='rejected' ? '#00796B' : '#e9ecef' ?>; color:<?= $filterStatus==='rejected' ? '#fff' : '#495057' ?>;">ปฏิเสธ/ยกเลิก</a>
            <a href="?status=all" style="padding:8px 16px; border-radius:20px; text-decoration:none; font-weight:600; font-size:14px; background:<?= $filterStatus==='all' ? '#00796B' : '#e9ecef' ?>; color:<?= $filterStatus==='all' ? '#fff' : '#495057' ?>;">ทั้งหมด</a>
        </div>

        <div class="tablebox">
            <table class="orders">
                <thead>
                    <tr>
                        <th>รหัสอ้างอิง (Txn ID)</th>
                        <th>ข้อมูลผู้ใช้</th>
                        <th>ยอดที่ขอถอน</th>
                        <th>ยอดเงินที่ใช้ได้จริง</th>
                        <th>บัญชีปลายทาง</th>
                        <th>วัน-เวลาที่ขอถอน</th>
                        <th>สถานะ</th>
                        <th style="text-align:center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($withdrawals as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['txn_code']) ?></strong></td>
                        <td>
                            <strong style="color:#00796B;"><?= htmlspecialchars($item['user_name']) ?></strong><br>
                            <small style="color:#888;">ID: <?= str_pad(htmlspecialchars($item['user_id']), 4, '0', STR_PAD_LEFT) ?></small>
                        </td>
                        <td style="color:#1976d2; font-weight:bold; font-size:16px;">
                            ฿<?= number_format($item['requested_amount'], 2) ?>
                        </td>
                        <td>
                            <?php if($item['available_balance'] < $item['requested_amount']): ?>
                                <span class="warning-text" title="ยอดเงินไม่พอสำหรับการถอน!">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                    ฿<?= number_format($item['available_balance'], 2) ?>
                                </span>
                            <?php else: ?>
                                <span class="success-text">
                                    ฿<?= number_format($item['available_balance'], 2) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php $bank = json_decode($item['withdrawal_bank_info'] ?? '[]', true) ?: []; ?>
                        <td>
                            <?= htmlspecialchars($bank['bank_name'] ?? 'ไม่ทราบ') ?><br>
                            <small style="color:#666;"><?= htmlspecialchars($bank['account_number'] ?? '') ?> (<?= htmlspecialchars($bank['account_name'] ?? '') ?>)</small>
                        </td>
                        <td><span style="font-size:13px; color:#555;"><?= htmlspecialchars($item['date_time']) ?></span></td>
                        <td>
                            <?php if($item['status'] === 'SUCCESS'): ?>
                                <span class="badge" style="background:#e8f5e9;color:#2e7d32;padding:5px 10px;border-radius:18px;font-weight:700;font-size:11px;">สำเร็จ</span>
                            <?php elseif($item['status'] === 'PENDING'): ?>
                                <span class="badge badge-pending">รอตรวจสอบ</span>
                            <?php else: ?>
                                <span class="badge" style="background:#ffebee;color:#c62828;padding:5px 10px;border-radius:18px;font-weight:700;font-size:11px;">ถูกปฏิเสธ</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <div class="action-btns">
                                <?php if($item['status'] === 'PENDING'): ?>
                                <button class="btn-sm btn-approve" onclick="openApproveModal('<?= $item['txn_id'] ?>', '<?= htmlspecialchars($item['txn_code']) ?>', <?= $item['requested_amount'] ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    อนุมัติ
                                </button>
                                <button class="btn-sm btn-reject" onclick="openRejectModal('<?= $item['txn_id'] ?>', '<?= htmlspecialchars($item['txn_code']) ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    ปฏิเสธ
                                </button>
                                <?php endif; ?>
                                <button class="btn-sm btn-history" onclick="openHistoryModal(<?= $item['wallet_id'] ?>, '<?= htmlspecialchars($item['user_name']) ?>', '<?= str_pad(htmlspecialchars($item['user_id']), 4, '0', STR_PAD_LEFT) ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                    ประวัติ
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 1. Approve Modal -->
    <div class="modal" id="approveModal">
      <div class="modal-box">
          <button class="modal-close" onclick="closeModal('approveModal')">&times;</button>
          <h2 class="modal-title" style="color:#2e7d32;">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
              ยืนยันการอนุมัติถอนเงิน
          </h2>
          <form method="POST" action="">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="txn_id" id="approve_txn_id">
              <div style="font-size:15px; color:#444; margin-bottom:15px;">
                  คุณต้องการอนุมัติการถอนเงินรหัส: <strong id="approve_display_txn" style="color:#00796B;"></strong> ใช่หรือไม่?
              </div>
              <div style="font-size:18px; font-weight:bold; color:#1976d2; margin-bottom:20px; background: #f0f7ff; padding: 15px; border-radius: 12px; text-align: center; border: 1px dashed #90caf9;">
                  ยอดเงิน: <span id="approve_display_amount"></span> บาท
              </div>
              <div class="modal-actions">
                  <button type="button" class="btn-cancel" onclick="closeModal('approveModal')">ยกเลิก</button>
                  <button type="submit" class="btn-submit" style="background:#2e7d32;">ยืนยันอนุมัติ</button>
              </div>
          </form>
      </div>
    </div>

    <!-- 2. Reject Modal -->
    <div class="modal" id="rejectModal">
      <div class="modal-box">
          <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
          <h2 class="modal-title" style="color:#d32f2f;">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
              ปฏิเสธการถอนเงิน
          </h2>
          <form method="POST" action="">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="txn_id" id="reject_txn_id">
              <p style="font-size:14px; color:#555; margin-bottom:15px; background: #ffebee; padding: 12px; border-radius: 8px; border: 1px solid #ffcdd2;">
                  กรุณาระบุเหตุผลที่ปฏิเสธคำขอรหัส: <strong id="reject_display_txn"></strong>
              </p>
              <div class="form-group">
                  <label>เหตุผลที่ปฏิเสธ:</label>
                  <textarea name="reason" id="rejectReason" required placeholder="เช่น ยอดเงินไม่เพียงพอ, พบความผิดปกติในการใช้งาน..."></textarea>
              </div>
              <div class="modal-actions">
                  <button type="button" class="btn-cancel" onclick="closeModal('rejectModal')">ยกเลิก</button>
                  <button type="submit" class="btn-submit danger">ยืนยันปฏิเสธ</button>
              </div>
          </form>
      </div>
    </div>

    <!-- 3. History Modal -->
    <div class="modal" id="historyModal">
      <div class="modal-box" style="width: 600px; max-width: 90vw;">
          <button class="modal-close" onclick="closeModal('historyModal')">&times;</button>
          <h2 class="modal-title" style="color:#1565c0;">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              ประวัติการถอนเงินล่าสุด
          </h2>
          <p style="margin-top:-15px; margin-bottom:20px; color:#777; font-size:14px;">
              ผู้ใช้งาน: <strong id="history_user_name" style="color:#333;"></strong>
          </p>
          
          <div style="background:#fdfdfd; border-radius:12px; max-height: 350px; overflow-y:auto; border: 1px solid #eee;">
              <table class="history-table">
                  <thead>
                      <tr>
                          <th>วันที่</th>
                          <th style="text-align:right">จำนวนเงิน</th>
                          <th style="text-align:center">สถานะ</th>
                          <th>หมายเหตุ</th>
                      </tr>
                  </thead>
                  <tbody id="history_table_body">
                      <!-- Data injected by JS -->
                  </tbody>
              </table>
          </div>
          
          <div class="modal-actions">
              <button type="button" class="btn-cancel" onclick="closeModal('historyModal')" style="flex:none; width:100%;">ปิดหน้าต่าง</button>
          </div>
      </div>
    </div>

    <script>
        function closeModal(id) { document.getElementById(id).classList.remove('open'); }
        
        function openApproveModal(txnId, txnCode, amount) {
            document.getElementById('approve_txn_id').value = txnId;
            document.getElementById('approve_display_txn').innerText = txnCode;
            document.getElementById('approve_display_amount').innerText = parseFloat(amount).toLocaleString('th-TH', {minimumFractionDigits: 2});
            document.getElementById('approveModal').classList.add('open');
        }

        function openRejectModal(txnId, txnCode) {
            document.getElementById('reject_txn_id').value = txnId;
            document.getElementById('reject_display_txn').innerText = txnCode;
            document.getElementById('rejectReason').value = '';
            document.getElementById('rejectModal').classList.add('open');
        }

        async function openHistoryModal(walletId, userName, userId) {
            document.getElementById('history_user_name').innerText = userName + ' (' + userId + ')';
            document.getElementById('history_table_body').innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#888;">กำลังโหลดข้อมูล...</td></tr>';
            document.getElementById('historyModal').classList.add('open');
            
            try {
                let response = await fetch('?ajax_history=1&wallet_id=' + walletId);
                let data = await response.json();
                let tbodyHtml = '';
                if (data.length === 0) {
                    tbodyHtml = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#888;">ไม่พบประวัติการถอนเงิน</td></tr>';
                } else {
                    data.forEach(item => {
                        let statusBadge = item.status === 'SUCCESS' ? '<span class="badge" style="background:#e8f5e9;color:#2e7d32;padding:4px 10px;border-radius:6px;font-weight:600;">สำเร็จ</span>' : (item.status === 'PENDING' ? '<span class="badge" style="background:#fff3e0;color:#ef6c00;padding:4px 10px;border-radius:6px;font-weight:600;">รอดำเนินการ</span>' : '<span class="badge" style="background:#ffebee;color:#c62828;padding:4px 10px;border-radius:6px;font-weight:600;">ถูกปฏิเสธ</span>');
                        
                        let dateObj = new Date(item.created_at);
                        let dateStr = dateObj.getFullYear() + '-' + String(dateObj.getMonth()+1).padStart(2,'0') + '-' + String(dateObj.getDate()).padStart(2,'0') + ' ' + String(dateObj.getHours()).padStart(2,'0') + ':' + String(dateObj.getMinutes()).padStart(2,'0');
                        tbodyHtml += `
                            <tr>
                                <td>${dateStr}</td>
                                <td style="text-align:right; font-weight:bold; color:#1976d2;">฿${parseFloat(item.amount).toFixed(2)}</td>
                                <td style="text-align:center;">${statusBadge}</td>
                                <td style="color:#777; font-size:13px;">${item.note || '-'}</td>
                            </tr>
                        `;
                    });
                }
                document.getElementById('history_table_body').innerHTML = tbodyHtml;
            } catch (e) {
                document.getElementById('history_table_body').innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:red;">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
            }
        }
        
        // Search Filter
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.orders tbody tr');
            
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                if(text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', e => {
                if(e.target === modal) modal.classList.remove('open');
            });
        });
    </script>
</body>
</html>
