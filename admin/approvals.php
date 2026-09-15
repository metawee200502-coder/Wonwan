<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['type'], $_POST['id'])) {
    $action = $_POST['action']; // 'approve' or 'reject'
    $type = $_POST['type'];     // 'shop' or 'publisher'
    $id = (int)$_POST['id'];
    $reason = $_POST['rejection_reason'] ?? '';
    $newStatus = ($action === 'approve') ? 'approved' : 'suspended'; 

    $targetEmail = '';
    $targetName = '';

    if ($type === 'shop') {
        $stmt = $pdo->prepare("UPDATE shops SET status = ? WHERE shop_id = ?");
        $stmt->execute([$newStatus, $id]);

        $q = $pdo->prepare("SELECT u.email, u.display_name FROM shops s JOIN shop_staff ss ON s.shop_id = ss.shop_id JOIN users u ON ss.user_id = u.user_id WHERE s.shop_id = ? LIMIT 1");
        $q->execute([$id]);
        if($row = $q->fetch()) {
            $targetEmail = $row['email'];
            $targetName = $row['display_name'];
        }
    } else {
        $stmt = $pdo->prepare("UPDATE publishers SET status = ? WHERE publisher_id = ?");
        $stmt->execute([$newStatus, $id]);

        $q = $pdo->prepare("SELECT u.email, u.display_name FROM publishers p JOIN publisher_staff ps ON p.publisher_id = ps.publisher_id JOIN users u ON ps.user_id = u.user_id WHERE p.publisher_id = ? LIMIT 1");
        $q->execute([$id]);
        if($row = $q->fetch()) {
            $targetEmail = $row['email'];
            $targetName = $row['display_name'];
        }
    }
    
    if ($targetEmail) {
        $typeLabel = ($type === 'shop') ? 'ร้านค้า' : 'สำนักพิมพ์';
        if ($action === 'approve') {
            $subject = "ยินดีด้วย! คำขอเปิด{$typeLabel}ของคุณได้รับการอนุมัติแล้ว";
            $body = "<h3>เรียนคุณ {$targetName},</h3>
                     <p>เรายินดีที่จะแจ้งให้ทราบว่า คำขอเปิด{$typeLabel}ของคุณได้รับการอนุมัติเรียบร้อยแล้ว</p>
                     <p>คุณสามารถเข้าสู่ระบบและเริ่มจัดการ{$typeLabel}ของคุณได้ทันที!</p>
                     <p>ขอบคุณที่ร่วมเป็นส่วนหนึ่งกับเรา<br>ทีมงาน Wanawan</p>";
        } else {
            $subject = "แจ้งผลการตรวจสอบคำขอเปิด{$typeLabel}";
            $body = "<h3>เรียนคุณ {$targetName},</h3>
                     <p>ทางเราได้ทำการตรวจสอบข้อมูลคำขอเปิด{$typeLabel}ของคุณแล้ว</p>
                     <p><strong>ผลการตรวจสอบ: ไม่อนุมัติ</strong></p>
                     <p><strong>หมายเหตุจากผู้ดูแลระบบ:</strong><br>" . nl2br(htmlspecialchars($reason)) . "</p>
                     <p>หากคุณมีข้อสงสัย หรือต้องการแก้ไขข้อมูล กรุณาติดต่อทีมงานของเรา</p>
                     <p>ขอแสดงความนับถือ<br>ทีมงาน Wanawan</p>";
        }
        sendSystemEmail($targetEmail, $subject, $body);
    }

    // --- Audit Log ---
    $actionType = ($action === 'approve') ? "APPROVE_" . strtoupper($type) : "REJECT_" . strtoupper($type);
    $details = ($action === 'approve') ? "อนุมัติ{$typeLabel} ID: {$id}" : "ปฏิเสธ{$typeLabel} ID: {$id} เหตุผล: " . $reason;
    logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', $actionType, (string)$id, $details);

    $_SESSION['flash_msg'] = "ดำเนินการเรียบร้อยแล้ว";
    header('Location: approvals.php');
    exit;
}

$pendingShops = $pdo->query("SELECT * FROM shops WHERE status = 'pending'")->fetchAll();
$pendingPubs = $pdo->query("SELECT * FROM publishers WHERE status = 'pending'")->fetchAll();

$active_menu = 'approvals';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>อนุมัติร้านค้า - ระบบหลังบ้าน</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
        <style>
        body { background: #f8fafc; margin: 0; display: flex; min-height: 100vh; font-family: 'Prompt', sans-serif; }
        .admin-main-wrap { flex: 1; min-width: 0; padding: 30px 40px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; }
        .page-title { margin: 0; color: #0f172a; font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        .page-subtitle { color: #64748b; font-size: 13px; margin-top: 8px; }
        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #ecfdf5; color: #10b981; border-radius: 20px; font-size: 11px; font-weight: 600; border: 1px solid #a7f3d0; }
        .status-badge::before { content: ""; width: 6px; height: 6px; background: #10b981; border-radius: 50%; }
        
        .header-actions { display: flex; gap: 12px; }
        .btn-outline { background: white; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 8px; color: #475569; font-size: 13px; font-family: inherit; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-outline:hover { background: #f1f5f9; border-color: #cbd5e1; color: #0f172a; }
        
        /* History Tabs Structure */
        .history-wrap { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 25px 30px; }
        .history-tabs-container { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px; }
        .history-tabs { display: flex; gap: 30px; }
        .history-tab { color: #64748b; text-decoration: none; font-size: 15px; font-weight: 600; position: relative; padding-bottom: 12px; transition: color 0.2s; cursor: pointer; background: transparent; border: none; font-family: inherit; }
        .history-tab.active { color: #0f172a; }
        .history-tab.active::after { content: ''; position: absolute; bottom: -13px; left: 0; right: 0; height: 2px; background: #0f172a; border-radius: 2px 2px 0 0; }
        .tab-count { background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 6px; font-weight: 600; }
        .history-tab.active .tab-count { background: #044b3f; color: white; }
        
        /* Filters */
        .history-filters { display: flex; gap: 12px; align-items: center; margin-bottom: 25px; }
        .history-search { flex: 1; position: relative; }
        .history-search i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px; }
        .history-search input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 14px; outline: none; box-sizing: border-box; }
        .filter-select { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #475569; outline: none; background: white; cursor: pointer; }
        
        /* List */
        .section-container { display: none; }
        .section-container.active { display: block; }
        .approval-list { display: flex; flex-direction: column; gap: 20px; }
        
        /* Card Redesign */
        .approval-card { 
            background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; 
            padding: 25px; display: flex; gap: 25px; align-items: flex-start;
            position: relative; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01);
        }
        .approval-card:hover { border-color: #cbd5e1; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03); }
        
        .avatar-initial { 
            width: 65px; height: 65px; border-radius: 12px; display: flex; align-items: center; justify-content: center; 
            font-size: 28px; font-weight: 800; color: #044b3f; background: #e0f2fe; flex-shrink: 0;
        }
        .avatar-initial.pub { background: #dcfce7; color: #166534; }
        .avatar-initial.shop { background: #e0f2fe; color: #0369a1; }
        
        .approval-info { flex: 1; }
        
        .doc-btn { 
            display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; 
            background: #fef2f2; color: #dc2626; border-radius: 6px; 
            font-size: 12px; font-weight: 600; text-decoration: none; border: 1px solid #fecaca;
            transition: background 0.2s; margin-bottom: 15px;
        }
        .doc-btn:hover { background: #fee2e2; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; margin-bottom: 20px; font-size: 13px; color: #475569; line-height: 1.6; }
        .info-grid span { color: #94a3b8; display: inline-block; width: 60px; }
        .info-grid strong { color: #0f172a; font-weight: 500; }
        
        .docs-section { font-size: 12px; color: #64748b; margin-top: 20px; }
        .docs-section .title { margin-bottom: 10px; }
        
        .approval-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 15px; min-width: 140px; }
        .btn-approve { font-family: inherit; width: 100%; background: #10b981; color: #fff; border: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; transition: background 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { font-family: inherit; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer; transition: background 0.2s; }
        .btn-reject:hover { background: #fee2e2; }
        .btn-req-doc { font-family: inherit; background: white; color: #64748b; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer; transition: all 0.2s; }
        .btn-req-doc:hover { background: #f1f5f9; color: #0f172a; }
        .secondary-actions { display: flex; gap: 8px; width: 100%; justify-content: space-between; }
        
        .empty-state { padding: 60px; text-align: center; color: #94a3b8; background: #f8fafc; border-radius: 12px; font-size: 14px; }
        
        .pagination-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 25px; font-size: 12px; color: #64748b; }
        .pagination-controls { display: flex; gap: 5px; }
        .page-btn { border: 1px solid #e2e8f0; background: white; padding: 6px 12px; border-radius: 6px; color: #64748b; cursor: pointer; font-family: inherit; font-size: 12px; transition: all 0.2s; }
        .page-btn.active { background: #044b3f; color: white; border-color: #044b3f; }
        .page-btn:hover:not(.active) { background: #f1f5f9; }
        
        .info-alert { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 15px 20px; margin-top: 25px; display: flex; align-items: flex-start; gap: 12px; font-size: 12px; color: #047857; line-height: 1.6; }
        .info-alert i { font-size: 16px; margin-top: 2px; }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); z-index: 1000;
            display: none; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s; backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content {
            background: #fff; border-radius: 20px; width: 90%; max-width: 600px;
            max-height: 90vh; overflow-y: auto; padding: 30px; position: relative;
            transform: scale(0.95); transition: transform 0.2s;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        .modal-overlay.active .modal-content { transform: scale(1); }
        .modal-close {
            position: absolute; top: 20px; right: 20px; cursor: pointer;
            background: #f1f5f9; border-radius: 50%; width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            color: #475569; transition: background 0.2s; border: none; font-size: 18px;
        }
        .modal-close:hover { background: #e2e8f0; color: #0f172a; }
        .modal-title { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 20px; }
        .modal-body { font-size: 14px; color: #334155; line-height: 1.6; }
        .swal-prompt-font { font-family: 'Prompt', sans-serif !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    <div class="admin-main-wrap">
        <div class="page-header">
            <div>
                <h1 class="page-title">คำขอเปิดร้านรออนุมัติ <span class="status-badge">รอตรวจสอบ <?= count($pendingPubs) + count($pendingShops) ?> รายการ</span></h1>
                <div class="page-subtitle">ตรวจสอบคุณสมบัติ เอกสารรับรองทางกฎหมาย และหนังสือรับรองบริษัทก่อนอนุมัติการเปิดร้านบนแพลตฟอร์ม</div>
            </div>
            <div class="header-actions">
                <button class="btn-outline"><i class="fas fa-history"></i> ประวัติการอนุมัติ (History)</button>
                <button class="btn-outline" style="background:#475569; color:white; border:none;" onclick="location.reload()"><i class="fas fa-sync-alt"></i> รีเฟรชสถานะ</button>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_msg'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: <?= json_encode($_SESSION['flash_msg']) ?>,
                        confirmButtonColor: '#0f172a',
                        customClass: { popup: 'swal-prompt-font', title: 'swal-prompt-font', htmlContainer: 'swal-prompt-font', confirmButton: 'swal-prompt-font' }
                    });
                });
            </script>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <div class="history-wrap">
            <div class="history-tabs-container">
                <div class="history-tabs">
                    <button class="history-tab active" onclick="switchTab('all')">ทั้งหมด (All Requests) <span class="tab-count"><?= count($pendingPubs) + count($pendingShops) ?></span></button>
                    <button class="history-tab" onclick="switchTab('publisher')">สำนักพิมพ์ (Publishers) <span class="tab-count"><?= count($pendingPubs) ?></span></button>
                    <button class="history-tab" onclick="switchTab('shop')">ร้านค้ามือสอง (Used Bookstores) <span class="tab-count"><?= count($pendingShops) ?></span></button>
                </div>
            </div>

            <div class="history-filters">
                <div class="history-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="ค้นหาชื่อร้านค้า, ชื่อเจ้าของ, อีเมล หรือเบอร์โทร...">
                </div>
                <select class="filter-select">
                    <option>สถานะ: รอตรวจสอบทั้งหมด</option>
                </select>
                <select class="filter-select">
                    <option>ช่วงวันที่: 30 วันล่าสุด</option>
                </select>
                <button class="btn-outline" style="padding:10px 15px;"><i class="fas fa-filter"></i></button>
            </div>

            <?php
            // Prepare unified list for "All" tab
            $allRequests = [];
            foreach($pendingPubs as $p) {
                $p['req_type'] = 'publisher';
                $allRequests[] = $p;
            }
            foreach($pendingShops as $s) {
                $s['req_type'] = 'shop';
                $allRequests[] = $s;
            }
            // In a real app we'd sort by created_at, but we'll just merge for now.
            ?>

            <!-- ALL TAB -->
            <div id="section-all" class="section-container active">
                <?php if (empty($allRequests)): ?>
                    <div class="empty-state">ไม่มีคำขอเปิดร้านค้าใหม่</div>
                <?php else: ?>
                    <div class="approval-list">
                        <?php foreach ($allRequests as $req): 
                            if ($req['req_type'] === 'publisher') {
                                renderPublisherCard($req);
                            } else {
                                renderShopCard($req);
                            }
                        endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PUBLISHER TAB -->
            <div id="section-publisher" class="section-container">
                <?php if (empty($pendingPubs)): ?>
                    <div class="empty-state">ไม่มีคำขอเปิดสำนักพิมพ์ใหม่</div>
                <?php else: ?>
                    <div class="approval-list">
                        <?php foreach ($pendingPubs as $pub): renderPublisherCard($pub); endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SHOP TAB -->
            <div id="section-shop" class="section-container">
                <?php if (empty($pendingShops)): ?>
                    <div class="empty-state">ไม่มีคำขอเปิดร้านค้าใหม่</div>
                <?php else: ?>
                    <div class="approval-list">
                        <?php foreach ($pendingShops as $shop): renderShopCard($shop); endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="pagination-footer">
                <div>แสดง <strong>1 - <?= count($allRequests) ?></strong> จากทั้งหมด <strong><?= count($allRequests) ?></strong> รายการคำขอ</div>
                <div class="pagination-controls">
                    <button class="page-btn">ก่อนหน้า</button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn">ถัดไป</button>
                </div>
            </div>
        </div>

        <div class="info-alert">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>เกณฑ์การตรวจสอบและอนุมัติร้านค้า (Merchant Verification SOP):</strong> เจ้าหน้าที่ส่วนกลางต้องตรวจสอบความถูกต้องของเลขประจำตัวผู้เสียภาษีและชื่อนิติบุคคลในหนังสือรับรองกระทรวงพาณิชย์ให้ตรงกับชื่อบัญชีรับเงิน เมื่ออนุมัติแล้วระบบจะส่งอีเมลแจ้งรหัสผ่านและเปิดสิทธิ์การวางขายหนังสือทันที
            </div>
        </div>
    </div>

    <!-- Modal Template -->
    <div class="modal-overlay" id="customModal" onclick="if(event.target===this) closeModal()">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div class="modal-title" id="modalTitle">รายละเอียด</div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/footer.php'; ?>

<?php
function renderPublisherCard($pub) {
    $initial = mb_substr(strtoupper(trim($pub['company_name'])), 0, 1) ?: 'P';
    // For English names like "Asia Book", we might want the first two letters, but 1 is fine.
    if(preg_match('/^[A-Za-z]+ [A-Za-z]+/', $pub['company_name'])) {
        $words = explode(' ', $pub['company_name']);
        $initial = strtoupper(substr($words[0],0,1) . substr($words[1],0,1));
    }
?>
    <div class="approval-card">
        <div class="avatar-initial pub"><?= htmlspecialchars($initial) ?></div>
        <div class="approval-info">
            <?php if (!empty($pub['company_document_url'])): ?>
                <a href="javascript:void(0)" onclick="showDocument('/process/<?= htmlspecialchars($pub['company_document_url']) ?>')" class="doc-btn">
                    <i class="fas fa-file-pdf"></i> หนังสือรับรองบริษัท / ใบอนุญาต (DBD.pdf)
                </a>
            <?php endif; ?>
            
            <div class="info-grid">
                <div><span>ติดต่อ:</span> <strong>คุณ <?= htmlspecialchars($pub['contact_name']) ?> (<?= htmlspecialchars($pub['contact_position']) ?>)</strong></div>
                <div><span>อีเมล:</span> <strong><?= htmlspecialchars($pub['contact_email']) ?></strong></div>
                <div><span>โทรศัพท์:</span> <strong><?= htmlspecialchars($pub['contact_phone']) ?></strong></div>
                <div><span>พื้นที่จดทะเบียน:</span> <strong>กรุงเทพมหานคร</strong></div>
            </div>
            
            <div class="docs-section">
                <div class="title">เอกสารประกอบการพิจารณา</div>
                <?php if (!empty($pub['company_document_url'])): ?>
                    <a href="javascript:void(0)" onclick="showDocument('/process/<?= htmlspecialchars($pub['company_document_url']) ?>')" class="doc-btn" style="background:#fff; border-color:#e2e8f0; color:#dc2626;">
                        <i class="fas fa-file-pdf"></i> หนังสือรับรองบริษัท / ใบอนุญาต (DBD & ภ.พ.20).pdf
                    </a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </div>
        </div>
        <form method="POST" class="approval-actions" onsubmit="event.preventDefault();">
            <input type="hidden" name="type" value="publisher">
            <input type="hidden" name="id" value="<?= $pub['publisher_id'] ?>">
            <button type="button" class="btn-approve" onclick="confirmApproval(event, this.form, 'approve')">
                <i class="fas fa-check"></i> อนุมัติสำนักพิมพ์
            </button>
            <div class="secondary-actions">
                <button type="button" class="btn-req-doc" onclick="confirmApproval(event, this.form, 'reject')">ขอเอกสารเพิ่ม</button>
                <button type="button" class="btn-reject" onclick="confirmApproval(event, this.form, 'reject')">ไม่อนุมัติ</button>
            </div>
        </form>
    </div>
<?php
}

function renderShopCard($shop) {
    $initial = mb_substr(strtoupper(trim($shop['shop_name'])), 0, 1) ?: 'S';
?>
    <div class="approval-card">
        <div class="avatar-initial shop"><?= htmlspecialchars($initial) ?></div>
        <div class="approval-info">
            <?php if (!empty($shop['id_card_document_url'])): ?>
                <a href="javascript:void(0)" onclick="showDocument('/pages/<?= htmlspecialchars($shop['id_card_document_url']) ?>')" class="doc-btn">
                    <i class="fas fa-file-pdf"></i> สำเนาบัตรประชาชน.pdf
                </a>
            <?php endif; ?>
            
            <div class="info-grid">
                <div><span>ชื่อร้าน:</span> <strong><?= htmlspecialchars($shop['shop_name']) ?></strong></div>
                <div><span>บัตรประชาชน:</span> <strong><?= htmlspecialchars($shop['id_card_number']) ?></strong></div>
                <div><span>ธนาคาร:</span> <strong><?= htmlspecialchars($shop['bank_name']) ?></strong></div>
                <div><span>เลขบัญชี:</span> <strong><?= htmlspecialchars($shop['bank_account_number']) ?> (<?= htmlspecialchars($shop['bank_account_name']) ?>)</strong></div>
            </div>
            
            <div class="docs-section">
                <div class="title">เอกสารประกอบการพิจารณา</div>
                <?php if (!empty($shop['id_card_document_url'])): ?>
                    <a href="javascript:void(0)" onclick="showDocument('/pages/<?= htmlspecialchars($shop['id_card_document_url']) ?>')" class="doc-btn" style="background:#fff; border-color:#e2e8f0; color:#dc2626;">
                        <i class="fas fa-file-pdf"></i> สำเนาบัตรประชาชน / บุ๊คแบงก์.pdf
                    </a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </div>
        </div>
        <form method="POST" class="approval-actions" onsubmit="event.preventDefault();">
            <input type="hidden" name="type" value="shop">
            <input type="hidden" name="id" value="<?= $shop['shop_id'] ?>">
            <button type="button" class="btn-approve" onclick="confirmApproval(event, this.form, 'approve')">
                <i class="fas fa-check"></i> อนุมัติร้านค้า
            </button>
            <div class="secondary-actions">
                <button type="button" class="btn-req-doc" onclick="confirmApproval(event, this.form, 'reject')">ขอเอกสารเพิ่ม</button>
                <button type="button" class="btn-reject" onclick="confirmApproval(event, this.form, 'reject')">ไม่อนุมัติ</button>
            </div>
        </form>
    </div>
<?php
}
?>

    <script>
        function confirmApproval(event, form, actionValue) {
            let isReject = actionValue === 'reject';
            let actionText = isReject ? 'ไม่อนุมัติ / ขอเอกสารเพิ่ม' : 'อนุมัติ';
            let confirmBtnColor = isReject ? '#dc2626' : '#10b981';
            
            let swalConfig = {
                title: 'ยืนยันการดำเนินการ?',
                text: 'คุณต้องการ' + actionText + 'รายการนี้ใช่หรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmBtnColor,
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                customClass: { popup: 'swal-prompt-font', title: 'swal-prompt-font', htmlContainer: 'swal-prompt-font', confirmButton: 'swal-prompt-font', cancelButton: 'swal-prompt-font' }
            };

            if (isReject) {
                swalConfig.input = 'textarea';
                swalConfig.inputLabel = 'ระบุหมายเหตุ/เหตุผลที่ไม่อนุมัติ หรือ ขอเอกสารเพิ่ม';
                swalConfig.inputPlaceholder = 'เช่น เอกสารไม่ชัดเจน, ข้อมูลไม่ครบถ้วน...';
                swalConfig.preConfirm = (reason) => {
                    if (!reason) { Swal.showValidationMessage('กรุณาระบุหมายเหตุเพื่อแจ้งให้ผู้ใช้งานทราบ'); return false; }
                    return reason;
                }
            }

            Swal.fire(swalConfig).then((result) => {
                if (result.isConfirmed) {
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'action';
                    input.value = actionValue;
                    form.appendChild(input);
                    if (isReject && result.value) {
                        let reasonInput = document.createElement('input');
                        reasonInput.type = 'hidden';
                        reasonInput.name = 'rejection_reason';
                        reasonInput.value = result.value;
                        form.appendChild(reasonInput);
                    }
                    form.submit();
                }
            });
        }

        function switchTab(tab) {
            document.querySelectorAll('.history-tab').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.section-container').forEach(el => el.classList.remove('active'));
            
            if(tab === 'all') {
                document.querySelectorAll('.history-tab')[0].classList.add('active');
                document.getElementById('section-all').classList.add('active');
            } else if(tab === 'publisher') {
                document.querySelectorAll('.history-tab')[1].classList.add('active');
                document.getElementById('section-publisher').classList.add('active');
            } else {
                document.querySelectorAll('.history-tab')[2].classList.add('active');
                document.getElementById('section-shop').classList.add('active');
            }
            
            // Update total label based on active tab
            const countStr = document.querySelector('.history-tab.active .tab-count').innerText;
            const badge = document.querySelector('.status-badge');
            if(badge) badge.innerHTML = `รอตรวจสอบ ${countStr} รายการ`;
        }

        function showDocument(url) {
            document.getElementById('modalTitle').innerText = 'เอกสารแนบ';
            let isPdf = url.toLowerCase().endsWith('.pdf');
            let html = '';
            if (isPdf) {
                html = `<iframe src="${url}" style="width:100%; height:70vh; border:none; border-radius:8px;"></iframe>`;
            } else {
                html = `<img src="${url}" style="width:100%; max-height:70vh; object-fit:contain; border-radius:8px; display:block; margin:0 auto;">`;
            }
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('customModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('customModal').classList.remove('active');
        }
    </script>
</body>
</html>
