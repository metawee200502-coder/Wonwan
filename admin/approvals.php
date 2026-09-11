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
        .admin-wrap { max-width: 1000px; margin: 40px 20px; padding: 0 20px; min-height: 60vh; font-family: 'Prompt', sans-serif; }
        .admin-title { font-size: 24px; color: var(--secondary-color); margin-bottom: 24px; font-weight: 600; font-family: 'Prompt', sans-serif; }
        
        .segmented-control {
            display: inline-flex;
            background-color: #fdfaf8;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 24px;
            gap: 4px;
        }
        .segment-btn {
            padding: 8px 32px;
            border-radius: 6px;
            font-size: 15px;
            color: var(--text-dark);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
            border: none;
            background: transparent;
            font-family: inherit;
        }
        .segment-btn.active {
            background-color: #0b5963;
            color: #fff;
        }
        .segment-btn:hover:not(.active) {
            background-color: #f5f0ee;
        }
        
        .section-container { display: none; }
        .section-container.active { display: block; }
        
        .section-title { font-size: 20px; color: var(--text-dark); margin: 30px 0 16px; padding-bottom: 8px; border-bottom: 2px solid var(--border-color); }
        .approval-list { display: flex; flex-direction: column; gap: 16px; }
        .approval-card { 
            background: #fff; border: 1px solid var(--border-color); border-radius: 12px; 
            padding: 20px; display: flex; gap: 20px; align-items: flex-start;
            cursor: pointer; transition: box-shadow 0.2s, border-color 0.2s;
        }
        .approval-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-color: #ddd; }
        
        .approval-card img { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; background: #f0f0f0; }
        .approval-info { flex: 1; }
        .approval-name { font-size: 18px; font-weight: 600; color: var(--text-dark); margin-bottom: 4px; }
        .approval-meta { font-size: 14px; color: var(--text-muted); margin-bottom: 12px; line-height: 1.6; }
        .approval-doc { 
            display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; 
            background: #fdf2ee; color: var(--primary-color); border-radius: 6px; 
            font-size: 13px; font-weight: 500; text-decoration: none; margin-bottom: 16px; 
            transition: background 0.2s;
        }
        .approval-doc:hover { background: #fce4da; }
        .approval-actions { display: flex; gap: 10px; }
        .btn-approve { font-family: inherit; background: #5cb85c; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-approve:hover { background: #4cae4c; }
        .btn-reject { font-family: inherit; background: #e8546a; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-reject:hover { background: #c84155; }
        .empty-state { padding: 40px; text-align: center; color: var(--text-muted); background: #f9f9f9; border-radius: 12px; border: 1px dashed var(--border-color); }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000;
            display: none; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s;
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content {
            background: #fff; border-radius: 12px; width: 90%; max-width: 600px;
            max-height: 90vh; overflow-y: auto; padding: 24px; position: relative;
            transform: scale(0.95); transition: transform 0.2s;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .modal-overlay.active .modal-content { transform: scale(1); }
        .modal-close {
            position: absolute; top: 16px; right: 16px; cursor: pointer;
            background: #f0f0f0; border-radius: 50%; width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-dark); transition: background 0.2s;
        }
        .modal-close:hover { background: #e0e0e0; }
        .modal-title { font-size: 20px; font-weight: 600; color: var(--text-dark); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); }
        .modal-body { font-size: 15px; color: var(--text-dark); line-height: 1.6; }
        .modal-body p { margin-bottom: 10px; }
        .modal-body strong { display: inline-block; width: 140px; color: var(--secondary-color); }
        .swal-prompt-font { font-family: 'Prompt', sans-serif !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    <div class="admin-main-wrap">
    <main class="admin-wrap">
        <h1 class="admin-title">คำขอเปิดร้านรออนุมัติ</h1>

        <div class="segmented-control">
            <button class="segment-btn active" onclick="switchTab('publisher')">สำนักพิมพ์</button>
            <button class="segment-btn" onclick="switchTab('shop')">ร้านค้ามือสอง</button>
        </div>

        <?php if (isset($_SESSION['flash_msg'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: <?= json_encode($_SESSION['flash_msg']) ?>,
                        confirmButtonColor: '#0b5963',
                        customClass: {
                            popup: 'swal-prompt-font',
                            title: 'swal-prompt-font',
                            htmlContainer: 'swal-prompt-font',
                            confirmButton: 'swal-prompt-font'
                        }
                    });
                });
            </script>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <div id="section-publisher" class="section-container active">
            <?php if (empty($pendingPubs)): ?>
                <div class="empty-state">ไม่มีคำขอเปิดสำนักพิมพ์ใหม่</div>
            <?php else: ?>
                <div class="approval-list">
                    <?php foreach ($pendingPubs as $pub): ?>
                        <div class="approval-card" onclick="showDetails('publisher', <?= htmlspecialchars(json_encode([
                            'name' => $pub['company_name'],
                            'contact_name' => $pub['contact_name'],
                            'contact_position' => $pub['contact_position'],
                            'contact_email' => $pub['contact_email'],
                            'contact_phone' => $pub['contact_phone']
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)">
                            <img src="<?= htmlspecialchars($pub['profile_image'] ?: '/assets/image/placeholder.png') ?>" alt="Logo">
                            <div class="approval-info">
                                <div class="approval-name"><?= htmlspecialchars($pub['company_name']) ?></div>
                                <div class="approval-meta">
                                    ติดต่อ: <?= htmlspecialchars($pub['contact_name']) ?> (<?= htmlspecialchars($pub['contact_position']) ?>)<br>
                                    อีเมล: <?= htmlspecialchars($pub['contact_email']) ?> | โทร: <?= htmlspecialchars($pub['contact_phone']) ?>
                                </div>
                                <?php if (!empty($pub['company_document_url'])): ?>
                                    <a href="javascript:void(0)" onclick="event.stopPropagation(); showDocument('/process/<?= htmlspecialchars($pub['company_document_url']) ?>')" class="approval-doc">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                        ดูเอกสารหนังสือรับรอง
                                    </a>
                                <?php endif; ?>
                                <form method="POST" class="approval-actions" onsubmit="event.preventDefault();">
                                    <input type="hidden" name="type" value="publisher">
                                    <input type="hidden" name="id" value="<?= $pub['publisher_id'] ?>">
                                    <button type="button" class="btn-approve" onclick="confirmApproval(event, this.form, 'approve')">อนุมัติสำนักพิมพ์</button>
                                    <button type="button" class="btn-reject" onclick="confirmApproval(event, this.form, 'reject')">ไม่อนุมัติ</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="section-shop" class="section-container">
            <?php if (empty($pendingShops)): ?>
                <div class="empty-state">ไม่มีคำขอเปิดร้านค้าใหม่</div>
            <?php else: ?>
                <div class="approval-list">
                    <?php foreach ($pendingShops as $shop): ?>
                        <div class="approval-card" onclick="showDetails('shop', <?= htmlspecialchars(json_encode([
                            'name' => $shop['shop_name'],
                            'id_card_number' => $shop['id_card_number'],
                            'bank_name' => $shop['bank_name'],
                            'bank_account_number' => $shop['bank_account_number'],
                            'bank_account_name' => $shop['bank_account_name']
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)">
                            <img src="<?= htmlspecialchars($shop['profile_image'] ?: '/assets/image/placeholder.png') ?>" alt="Logo">
                            <div class="approval-info">
                                <div class="approval-name"><?= htmlspecialchars($shop['shop_name']) ?></div>
                                <div class="approval-meta">
                                    บัตรประชาชน: <?= htmlspecialchars($shop['id_card_number']) ?><br>
                                    บัญชีธนาคาร: <?= htmlspecialchars($shop['bank_name']) ?> - <?= htmlspecialchars($shop['bank_account_number']) ?> (<?= htmlspecialchars($shop['bank_account_name']) ?>)
                                </div>
                                <?php if (!empty($shop['id_card_document_url'])): ?>
                                    <a href="javascript:void(0)" onclick="event.stopPropagation(); showDocument('/pages/<?= htmlspecialchars($shop['id_card_document_url']) ?>')" class="approval-doc">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                        ดูภาพถ่ายบัตรประชาชน
                                    </a>
                                <?php endif; ?>
                                <form method="POST" class="approval-actions" onsubmit="event.preventDefault();">
                                    <input type="hidden" name="type" value="shop">
                                    <input type="hidden" name="id" value="<?= $shop['shop_id'] ?>">
                                    <button type="button" class="btn-approve" onclick="confirmApproval(event, this.form, 'approve')">อนุมัติร้านค้า</button>
                                    <button type="button" class="btn-reject" onclick="confirmApproval(event, this.form, 'reject')">ไม่อนุมัติ</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>
    </div>

    <!-- Modal Template -->
    <div class="modal-overlay" id="customModal" onclick="if(event.target===this) closeModal()">
        <div class="modal-content">
            <div class="modal-close" onclick="closeModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </div>
            <div class="modal-title" id="modalTitle">รายละเอียด</div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../components/footer.php'; ?>

    <script>
        function confirmApproval(event, form, actionValue) {
            event.stopPropagation();
            let isReject = actionValue === 'reject';
            let actionText = isReject ? 'ไม่อนุมัติ' : 'อนุมัติ';
            let confirmBtnColor = isReject ? '#e8546a' : '#0b5963';
            
            let swalConfig = {
                title: 'ยืนยันการดำเนินการ?',
                text: 'คุณต้องการ' + actionText + 'รายการนี้ใช่หรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmBtnColor,
                cancelButtonColor: '#999',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                customClass: {
                    popup: 'swal-prompt-font',
                    title: 'swal-prompt-font',
                    htmlContainer: 'swal-prompt-font',
                    confirmButton: 'swal-prompt-font',
                    cancelButton: 'swal-prompt-font'
                }
            };

            if (isReject) {
                swalConfig.input = 'textarea';
                swalConfig.inputLabel = 'ระบุหมายเหตุ/เหตุผลที่ไม่อนุมัติ';
                swalConfig.inputPlaceholder = 'เช่น เอกสารไม่ชัดเจน, ข้อมูลไม่ครบถ้วน...';
                swalConfig.inputAttributes = {
                    'aria-label': 'ระบุหมายเหตุที่ไม่อนุมัติ'
                };
                swalConfig.preConfirm = (reason) => {
                    if (!reason) {
                        Swal.showValidationMessage('กรุณาระบุหมายเหตุเพื่อแจ้งให้ผู้ใช้งานทราบ');
                        return false;
                    }
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
            document.querySelectorAll('.segment-btn').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.section-container').forEach(el => el.classList.remove('active'));
            
            if(tab === 'publisher') {
                document.querySelectorAll('.segment-btn')[0].classList.add('active');
                document.getElementById('section-publisher').classList.add('active');
            } else {
                document.querySelectorAll('.segment-btn')[1].classList.add('active');
                document.getElementById('section-shop').classList.add('active');
            }
        }

        function showDetails(type, data) {
            document.getElementById('modalTitle').innerText = data.name;
            let html = '';
            
            if (type === 'publisher') {
                html += `
                    <p><strong>ประเภท:</strong> สำนักพิมพ์</p>
                    <p><strong>ชื่อผู้ติดต่อ:</strong> ${data.contact_name}</p>
                    <p><strong>ตำแหน่ง:</strong> ${data.contact_position}</p>
                    <p><strong>อีเมล:</strong> ${data.contact_email}</p>
                    <p><strong>เบอร์โทร:</strong> ${data.contact_phone}</p>
                `;
            } else {
                html += `
                    <p><strong>ประเภท:</strong> ร้านค้ามือสอง</p>
                    <p><strong>เลขบัตรประชาชน:</strong> ${data.id_card_number}</p>
                    <p><strong>ธนาคาร:</strong> ${data.bank_name}</p>
                    <p><strong>เลขที่บัญชี:</strong> ${data.bank_account_number}</p>
                    <p><strong>ชื่อบัญชี:</strong> ${data.bank_account_name}</p>
                `;
            }
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('customModal').classList.add('active');
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
