<?php
session_start();
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}
require_once __DIR__ . '/../config/config.php';

$action = $_GET['action'] ?? '';
$disputeId = (int)($_GET['id'] ?? 0);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resolve_dispute'])) {
        $resolution = $_POST['resolution'] ?? '';
        $penalty = (float)($_POST['penalty_amount'] ?? 0);
        $notes = trim($_POST['admin_notes'] ?? '');
        
        try {
            $pdo->beginTransaction();
            
            // Simplified escrow handling logic for scaffolding
            $stmt = $pdo->prepare("SELECT d.*, r.requester_id, e.owner_id as target_owner_id 
                                   FROM trade_disputes d 
                                   JOIN exchange_requests r ON d.request_id = r.request_id
                                   JOIN exchanges e ON r.exchange_id = e.exchange_id
                                   WHERE d.dispute_id = ? FOR UPDATE");
            $stmt->execute([$disputeId]);
            $dispute = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$dispute) throw new Exception("Dispute not found.");
            
            $senderId = $dispute['against_user'];
            $receiverId = $dispute['raised_by'];
            
            // Escrow amount is dynamically fetched from trade_condition_forms
            $formStmt = $pdo->prepare("SELECT required_deposit_amount FROM trade_condition_forms WHERE request_id = ? AND owner_id = ?");
            $formStmt->execute([$dispute['request_id'], $senderId]);
            $escrowAmount = (float)$formStmt->fetchColumn();
            if ($escrowAmount <= 0) {
                // Fallback or no deposit
                $escrowAmount = 250.00;
            }
            
            $senderWalletStmt = $pdo->prepare("SELECT wallet_id FROM vendor_wallets WHERE seller_type='user' AND seller_id=? FOR UPDATE");
            $senderWalletStmt->execute([$senderId]);
            $senderWallet = $senderWalletStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resolution === 'approve_claim') {
                if ($penalty > $escrowAmount) throw new Exception("Penalty exceeds escrow amount.");
                
                $refundAmount = $escrowAmount - $penalty;
                if ($senderWallet) {
                    $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, available_balance = available_balance + ? WHERE wallet_id=?")
                        ->execute([$escrowAmount, $refundAmount, $senderWallet['wallet_id']]);
                }
                
                $receiverWalletStmt = $pdo->prepare("SELECT wallet_id FROM vendor_wallets WHERE seller_type='user' AND seller_id=? FOR UPDATE");
                $receiverWalletStmt->execute([$receiverId]);
                $receiverWallet = $receiverWalletStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($receiverWallet) {
                    $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance + ? WHERE wallet_id=?")->execute([$penalty, $receiverWallet['wallet_id']]);
                    $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, status) VALUES (?, 'compensation', ?, 'SUCCESS')")->execute([$receiverWallet['wallet_id'], $penalty]);
                }
                
                $pdo->prepare("UPDATE trade_disputes SET status = 'resolved_return', admin_resolution_notes = ?, resolved_by = ? WHERE dispute_id = ?")
                    ->execute([$notes, $_SESSION['user_id'], $disputeId]);
                    
                $pdo->prepare("UPDATE exchange_requests SET status = 'cancelled' WHERE request_id = ?")->execute([$dispute['request_id']]);
                
                $success = "อนุมัติการเคลมเรียบร้อยแล้ว หักเงินมัดจำ {$penalty} บาท ชดเชยให้ผู้รับ";
                
                // 5-Baht deduction rule for refunds
                $refundFee = 5.00;
                $netRefund = max(0, $escrowAmount - $refundFee);
                
                if ($senderWallet) {
                    $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, available_balance = available_balance + ? WHERE wallet_id=?")
                        ->execute([$escrowAmount, $netRefund, $senderWallet['wallet_id']]);
                        
                    // Log the deduction if there's a wallet transaction table
                    $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, status, notes) VALUES (?, 'deduction', ?, 'SUCCESS', 'ค่าธรรมเนียมการคืนเงิน (P2P)')")
                        ->execute([$senderWallet['wallet_id'], $refundFee]);
                }
                    
                $pdo->prepare("UPDATE trade_disputes SET status = 'resolved_reject', admin_resolution_notes = ?, resolved_by = ? WHERE dispute_id = ?")
                    ->execute([$notes, $_SESSION['user_id'], $disputeId]);
                    
                $pdo->prepare("UPDATE exchange_requests SET status = 'cancelled' WHERE request_id = ?")->execute([$dispute['request_id']]);
                
                $success = "ปฏิเสธการเคลมเรียบร้อยแล้ว คืนเงินมัดจำ {$netRefund} บาท (หักค่าธรรมเนียม {$refundFee} บาท) ให้ผู้ส่ง";
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Fetch all disputes
$stmt = $pdo->query("
    SELECT d.*, r.trade_option, 
           u_raised.display_name as raiser_name, u_against.display_name as against_name
    FROM trade_disputes d
    JOIN exchange_requests r ON d.request_id = r.request_id
    JOIN users u_raised ON d.raised_by = u_raised.user_id
    JOIN users u_against ON d.against_user = u_against.user_id
    ORDER BY d.created_at DESC
");
$disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$active_menu = 'trade_disputes';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Trade Disputes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        :root {
            --admin-primary: #1E293B;
            --admin-secondary: #0F172A;
            --admin-accent: #3B82F6;
            --bg-color: #F1F5F9;
            --surface: #FFFFFF;
            --text-main: #334155;
            --text-muted: #64748B;
            --border: #E2E8F0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            color: var(--text-main);
        }

        .admin-main-wrap { display: flex; min-height: 100vh; }
        
        .admin-wrap {
            flex: 1;
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .admin-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 30px;
        }

        .admin-title {
            font-size: 28px;
            color: var(--admin-secondary);
            margin: 0 0 5px 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .admin-subtitle { color: var(--text-muted); font-size: 15px; margin: 0; }

        .dashboard-card {
            background: var(--surface);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .table-responsive { overflow-x: auto; }
        
        table {
            width: 100%; border-collapse: collapse; text-align: left;
        }
        
        th {
            background: #F8FAFC;
            color: #475569;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            color: #1E293B;
            font-size: 14px;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #F8FAFC; }

        .status-badge {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-weight: 600; font-size: 12px; text-transform: capitalize;
        }
        .status-open { background: #FEF3C7; color: #D97706; }
        .status-under_review { background: #E0F2FE; color: #0284C7; }
        .status-resolved_return { background: #FEE2E2; color: #DC2626; }
        .status-resolved_reject { background: #DCFCE7; color: #16A34A; }

        .btn-view {
            background: var(--admin-accent); color: #fff;
            padding: 6px 16px; border: none; border-radius: 6px;
            font-weight: 500; font-size: 13px; cursor: pointer;
            transition: all 0.2s;
        }
        .btn-view:hover { background: #2563EB; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3); }

        /* Modern Split Screen Modal */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px); z-index: 1000;
            justify-content: center; align-items: center;
        }
        
        .modal-content {
            background: #fff; width: 95%; max-width: 1400px; height: 90vh;
            border-radius: 20px; display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalScale 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes modalScale { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header {
            padding: 20px 30px; background: #fff; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        
        .modal-header h3 { margin: 0; color: var(--admin-secondary); font-size: 20px; display: flex; align-items: center; gap: 10px;}
        .close-btn {
            background: #F1F5F9; border: none; width: 36px; height: 36px;
            border-radius: 50%; color: #64748B; font-size: 20px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: 0.2s;
        }
        .close-btn:hover { background: #E2E8F0; color: #0F172A; }

        .modal-body { display: flex; flex: 1; overflow: hidden; background: #F8FAFC; }
        
        .split-pane { flex: 1; padding: 30px; overflow-y: auto; }
        .split-pane.left { border-right: 1px solid var(--border); background: #fff; }
        .split-pane.right { background: #FAFAFA; }

        .pane-title {
            font-size: 18px; font-weight: 600; margin: 0 0 20px 0;
            padding-bottom: 10px; border-bottom: 2px solid var(--border);
            display: flex; align-items: center; gap: 10px;
        }
        .left .pane-title { color: #3B82F6; border-color: #DBEAFE; }
        .right .pane-title { color: #EF4444; border-color: #FEE2E2; }

        .info-card { background: #F8FAFC; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--border); }
        
        .evidence-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .evidence-box {
            border: 1px solid var(--border); border-radius: 12px; overflow: hidden;
            background: #fff;
        }
        .evidence-box img { width: 100%; height: 200px; object-fit: cover; display: block; cursor: zoom-in; }
        .evidence-box .label { padding: 10px; text-align: center; font-size: 13px; font-weight: 600; color: #475569; border-top: 1px solid var(--border); background: #F8FAFC;}

        .modal-footer {
            padding: 20px 30px; background: #fff; border-top: 1px solid var(--border);
        }

        .resolution-form { display: flex; gap: 20px; align-items: center; }
        .form-input {
            flex: 1; padding: 12px 16px; border: 1px solid var(--border);
            border-radius: 8px; font-family: inherit; font-size: 14px;
            transition: 0.2s;
        }
        .form-input:focus { outline: none; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        .penalty-box { display: flex; align-items: center; gap: 10px; background: #F8FAFC; padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border); }
        .penalty-input { width: 100px; padding: 8px; border: 1px solid #CBD5E1; border-radius: 6px; font-family: inherit; }

        .btn-action {
            padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: #fff; display: flex; align-items: center; gap: 8px; transition: 0.2s;
        }
        .btn-approve { background: #EF4444; }
        .btn-approve:hover { background: #DC2626; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .btn-reject { background: #10B981; }
        .btn-reject:hover { background: #059669; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }

        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #DCFCE7; color: #166534; }
        .alert-error { background: #FEE2E2; color: #991B1B; }

    </style>
</head>
<body>
    <div style="display:none;"><?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?></div>
    
    <div class="admin-main-wrap">
        <main class="admin-wrap">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title">ศูนย์จัดการข้อพิพาท (Dispute Resolution)</h1>
                    <p class="admin-subtitle">ตรวจสอบหลักฐานและตัดสินข้อร้องเรียนในระบบ Escrow</p>
                </div>
            </div>
            
            <?php if ($success): ?><div class="alert alert-success">✓ <?=htmlspecialchars($success)?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error">⚠ <?=htmlspecialchars($error)?></div><?php endif; ?>
            
            <div class="dashboard-card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัสข้อพิพาท</th>
                                <th>Request ID</th>
                                <th>รูปแบบ (Option)</th>
                                <th>ผู้เปิดเคลม (ผู้รับ)</th>
                                <th>ผู้ถูกเคลม (ผู้ส่ง)</th>
                                <th>สถานะ</th>
                                <th>วันที่</th>
                                <th style="text-align:center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disputes as $d): ?>
                            <tr>
                                <td><strong style="color:#3B82F6;">#<?= $d['dispute_id'] ?></strong></td>
                                <td>#<?= $d['request_id'] ?></td>
                                <td><span style="background:#F1F5F9; padding:4px 8px; border-radius:4px; font-size:12px; color:#475569;"><?= $d['trade_option'] ?: 'N/A' ?></span></td>
                                <td><?= htmlspecialchars($d['raiser_name']) ?></td>
                                <td><?= htmlspecialchars($d['against_name']) ?></td>
                                <td>
                                    <?php 
                                        $statusClass = 'status-open';
                                        if($d['status'] === 'under_review') $statusClass = 'status-under_review';
                                        if($d['status'] === 'resolved_return') $statusClass = 'status-resolved_return';
                                        if($d['status'] === 'resolved_reject') $statusClass = 'status-resolved_reject';
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= str_replace('_', ' ', $d['status']) ?></span>
                                </td>
                                <td style="color:#64748B; font-size:13px;"><?= date('d M Y H:i', strtotime($d['created_at'])) ?></td>
                                <td style="text-align:center;">
                                    <button class="btn-view" onclick="openDisputeModal(<?= $d['dispute_id'] ?>, '<?= htmlspecialchars($d['against_name']) ?>', '<?= htmlspecialchars($d['raiser_name']) ?>')">
                                        พิจารณาคดี
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($disputes)): ?>
                                <tr><td colspan="8" style="text-align:center; padding: 40px; color:#94A3B8;">ไม่มีข้อพิพาทในระบบ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modern Split Screen Modal -->
    <div class="modal" id="disputeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><span style="font-size:24px;">⚖️</span> พิจารณาข้อพิพาท <span style="color:#3B82F6;">#<span id="modalDisputeId"></span></span></h3>
                <button class="close-btn" onclick="document.getElementById('disputeModal').style.display='none'">&times;</button>
            </div>
            
            <div class="modal-body">
                <!-- Left: Original Condition Form -->
                <div class="split-pane left">
                    <h4 class="pane-title">📦 หลักฐานก่อนส่ง (Condition Form)</h4>
                    <div class="info-card">
                        ผู้ส่งสินค้า: <strong id="senderName" style="color:#334155;"></strong>
                    </div>
                    
                    <div class="evidence-grid">
                        <div class="evidence-box">
                            <img src="dummy_front.jpg" id="origFront">
                            <div class="label">ปกหน้า</div>
                        </div>
                        <div class="evidence-box">
                            <img src="dummy_back.jpg" id="origBack">
                            <div class="label">ปกหลัง</div>
                        </div>
                    </div>
                    <div class="evidence-box" style="margin-bottom:20px; width: 50%;">
                        <img src="dummy_spine.jpg" id="origSpine" style="height:150px;">
                        <div class="label">สันหนังสือ</div>
                    </div>
                    
                    <div style="background:#F1F5F9; padding:20px; border-radius:12px; border:1px solid #E2E8F0;">
                        <div style="font-weight:600; color:#334155; margin-bottom:8px;">📝 ตำหนิที่แจ้งไว้ตอนส่ง:</div>
                        <div id="origDefects" style="color:#475569; line-height:1.6;">(ไม่มีข้อมูล)</div>
                    </div>
                </div>
                
                <!-- Right: Claim Evidence -->
                <div class="split-pane right">
                    <h4 class="pane-title">🚨 หลักฐานการเคลม (Claim Report)</h4>
                    <div class="info-card" style="background:#FFF1F2; border-color:#FECDD3;">
                        ผู้เปิดเคลม (ผู้รับ): <strong id="claimerName" style="color:#BE123C;"></strong>
                    </div>
                    
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:600; color:#334155; margin-bottom:10px;">🎥 วิดีโอแกะกล่อง (Unboxing Video)</div>
                        <div style="background:#000; border-radius:12px; height:240px; display:flex; align-items:center; justify-content:center; color:#fff;">
                            [ Video Player Placeholder ]
                        </div>
                    </div>
                    
                    <div style="font-weight:600; color:#334155; margin-bottom:10px;">📸 รูปสินค้าที่ได้รับจริง</div>
                    <div class="evidence-grid" id="claimPhotos">
                        <div class="evidence-box"><img src="dummy_claim1.jpg"><div class="label">หลักฐาน 1</div></div>
                        <div class="evidence-box"><img src="dummy_claim2.jpg"><div class="label">หลักฐาน 2</div></div>
                    </div>
                    
                    <div style="background:#FEF2F2; padding:20px; border-radius:12px; border:1px solid #FECACA;">
                        <div style="font-weight:600; color:#991B1B; margin-bottom:8px;">💬 เหตุผลการเคลม:</div>
                        <div id="claimReason" style="color:#7F1D1D; line-height:1.6;">(ไม่มีข้อมูล)</div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <form method="post" class="resolution-form">
                    <input type="hidden" name="resolve_dispute" value="1">
                    <input type="hidden" name="id" id="formDisputeId" value="">
                    
                    <input type="text" name="admin_notes" class="form-input" placeholder="พิมพ์บันทึกคำตัดสินของแอดมินที่นี่..." required>
                    
                    <div class="penalty-box">
                        <label style="font-weight:600; color:#475569; font-size:14px;">หักค่าปรับตีของกลับ:</label>
                        <input type="number" name="penalty_amount" value="50" class="penalty-input">
                        <span style="color:#64748B; font-size:14px;">฿</span>
                    </div>
                    
                    <button type="submit" name="resolution" value="approve_claim" class="btn-action btn-approve" onclick="return confirm('ยืนยันให้ผู้รับชนะเคลม (ตีของกลับ) และหักเงินมัดจำผู้ส่ง?')">
                        🛑 อนุมัติเคลม (ผู้ส่งผิด)
                    </button>
                    <button type="submit" name="resolution" value="reject_claim" class="btn-action btn-reject" onclick="return confirm('ยืนยันปฏิเสธการเคลม (ผู้รับแพ้) และคืนเงินให้ผู้ส่งเต็มจำนวน?')">
                        ✅ ปฏิเสธเคลม (ผู้รับผิด)
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function openDisputeModal(id, senderName, claimerName) {
        document.getElementById('formDisputeId').value = id;
        document.getElementById('modalDisputeId').innerText = id;
        
        document.getElementById('senderName').innerText = senderName || "Sender";
        document.getElementById('claimerName').innerText = claimerName || "Receiver";
        
        // Dummy data insertion for UI testing
        document.getElementById('origDefects').innerText = "ไม่มีรอยยับ สภาพสมบูรณ์ 100%";
        document.getElementById('claimReason').innerText = "ได้รับของแล้วหน้าปกมีรอยพับขนาดใหญ่ และสันหนังสือมีรอยขาด ไม่ตรงตามรูปก่อนส่งเลยครับ";
        
        document.getElementById('disputeModal').style.display = 'flex';
    }
    </script>
</body>
</html>
