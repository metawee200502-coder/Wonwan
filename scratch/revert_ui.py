import re

with open('c:/laragon/www/wanawan_project/admin/qc_exchanges.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Extract the PHP logic up to ?>
php_logic_match = re.search(r'<\?php\s.*?(\?>)', content, re.DOTALL)
php_logic = php_logic_match.group(0)

# We need to inject approve_slip into the PHP logic
approve_slip_logic = """    } elseif ($action === 'approve_slip') {
        $request_id = (int)$_POST['request_id'];
        $side = $_POST['side']; // 'requester' or 'offer'
        $slip_result = $_POST['slip_result']; // 'approved' or 'rejected'

        if ($side === 'requester') {
            $stmt = $pdo->prepare("UPDATE exchange_requests SET requester_slip_status = ? WHERE request_id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE exchange_requests SET offer_slip_status = ? WHERE request_id = ?");
        }
        $stmt->execute([$slip_result, $request_id]);
        
        $sideLabel = ($side === 'requester') ? 'ผู้ขอแลก' : 'ผู้ตั้งโพสต์';
        logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'VERIFY_SLIP', (string)$request_id, "บันทึกผลตรวจสอบสลิปของ {$sideLabel} ว่า {$slip_result} สำหรับคำขอที่ {$request_id}");

        $_SESSION['flash_msg'] = "บันทึกผลตรวจสอบสลิปเรียบร้อยแล้ว";
"""

if "approve_slip" not in php_logic:
    php_logic = php_logic.replace("    header(\"Location: qc_exchanges.php?tab=\" . urlencode($tab));", approve_slip_logic + "    }\n\n    header(\"Location: qc_exchanges.php?tab=\" . urlencode($tab));")


# 2. Extract the physical tab PHP logic (we will replace it completely)
new_physical_tab = """        <?php if ($tab === 'physical'): ?>
            <?php
            // Physical QC handles requests where status = accepted or completed
            $reqs = $pdo->query("
                SELECT r.*,
                       e_req.title as req_title, e_req.image_cover as req_img, e_req.owner_id as target_owner_id,
                       e_off.title as off_title, e_off.image_cover as off_img, r.requester_id,
                       u1.display_name as target_owner_name, u1.profile_image as target_owner_image, u1.email as target_owner_email,
                       u2.display_name as requester_name, u2.profile_image as requester_image, u2.email as requester_email,
                       c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects,
                       c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects
                FROM exchange_requests r
                LEFT JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
                LEFT JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
                LEFT JOIN users u1 ON e_req.owner_id = u1.user_id
                LEFT JOIN users u2 ON r.requester_id = u2.user_id
                LEFT JOIN trade_condition_forms c_off ON c_off.request_id = r.request_id AND c_off.owner_id = e_req.owner_id
                LEFT JOIN trade_condition_forms c_req ON c_req.request_id = r.request_id AND c_req.owner_id = r.requester_id
                WHERE r.status IN ('accepted', 'completed')
                ORDER BY r.updated_at ASC
            ")->fetchAll();
            
            function formatTradeOption($opt) {
                if ($opt === 'direct_escrow') return 'ส่งตรงให้กัน (P2P Escrow)';
                if ($opt === 'admin_qc') return 'ผ่านส่วนกลาง (Admin QC)';
                if ($opt === 'meetup') return 'นัดรับ (Meet-up)';
                return $opt ?: 'ยังไม่ระบุ';
            }
            function getSlipStatusBadge($status) {
                if ($status === 'verified') return '<span class="slip-badge slip-verified">✅ ตรวจสอบแล้ว</span>';
                if ($status === 'pending') return '<span class="slip-badge slip-pending">⏳ รอตรวจสอบ</span>';
                return '<span class="slip-badge slip-none">❌ ยังไม่แนบสลิป</span>';
            }
            function chatImage($v) {
                if(!$v) return '/assets/images/default-avatar.png';
                if(re.search(r'^https?://', $v, re.IGNORECASE)) return $v; // Using PHP preg_match equivalent in python string would be tricky if not escaped, but we write literal PHP code here.
                $v = ltrim(str_replace('\\\\','/',$v),'/');
                if(str_starts_with($v,'pages/')) return '/'.$v;
                return '/'.$v;
            }
            // Fix chatImage function regex for PHP
            ?>
"""
# Replace python-like re.search with correct PHP
new_physical_tab = new_physical_tab.replace("if(re.search(r'^https?://', $v, re.IGNORECASE))", "if(preg_match('#^https?://#i',$v))")

new_physical_tab += """            <?php if (empty($reqs)): ?>
                <div class="empty-state">ไม่มีพัสดุรอตรวจสภาพจริง</div>
            <?php else: ?>
                <div class="tablebox">
                    <table class="orders">
                        <thead>
                            <tr>
                                <th>รหัสคำขอ</th>
                                <th>รูปแบบการแลก</th>
                                <th>ข้อมูลผู้ตั้งโพสต์</th>
                                <th>ข้อมูลผู้ขอแลก</th>
                                <th style="width: 250px;">สถานะจัดส่ง & QC</th>
                                <th style="width: 150px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reqs as $r): ?>
                                <tr>
                                    <td style="font-weight: 500; color: #00796B;">#EX-<?= $r['request_id'] ?></td>
                                    <td>
                                        <span class="action-badge badge-blue"><?= formatTradeOption($r['trade_option']) ?></span>
                                    </td>
                                    <td>
                                        <div class="user-profile">
                                            <img src="<?= chatImage($r['target_owner_image']) ?>" class="user-avatar" alt="Avatar">
                                            <div class="user-info-text">
                                                <span class="user-name"><?= htmlspecialchars($r['target_owner_name'] ?? 'ไม่มีชื่อ') ?></span>
                                                <span class="email-tag"><?= htmlspecialchars($r['target_owner_email'] ?? '') ?></span>
                                            </div>
                                        </div>
                                        <div class="book-title">📖 <?= htmlspecialchars($r['req_title'] ?? '') ?></div>
                                        <?php if (!empty($r['off_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="margin-top:8px; background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd;" onclick="openCondModal('<?= htmlspecialchars($r['off_cond_front']) ?>', '<?= htmlspecialchars($r['off_cond_back']) ?>', '<?= htmlspecialchars($r['off_cond_spine']) ?>')">🔎 ดูสภาพที่ตกลง</button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="user-profile">
                                            <img src="<?= chatImage($r['requester_image']) ?>" class="user-avatar" alt="Avatar">
                                            <div class="user-info-text">
                                                <span class="user-name"><?= htmlspecialchars($r['requester_name'] ?? 'ไม่มีชื่อ') ?></span>
                                                <span class="email-tag"><?= htmlspecialchars($r['requester_email'] ?? '') ?></span>
                                            </div>
                                        </div>
                                        <div class="book-title">📖 <?= htmlspecialchars($r['off_title'] ?? '') ?></div>
                                        <?php if (!empty($r['req_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="margin-top:8px; background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd;" onclick="openCondModal('<?= htmlspecialchars($r['req_cond_front']) ?>', '<?= htmlspecialchars($r['req_cond_back']) ?>', '<?= htmlspecialchars($r['req_cond_spine']) ?>')">🔎 ดูสภาพที่ตกลง</button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size:12px; margin-bottom:8px;">
                                            <strong>ผู้ตั้งโพสต์:</strong><br>
                                            สลิป: <?= getSlipStatusBadge($r['offer_slip_status']) ?><br>
                                            เลขพัสดุ: <span class="tracking-number"><?= htmlspecialchars($r['offer_tracking_to_admin'] ?? '-') ?></span><br>
                                            QC: <span class="qc-status-badge bg-<?= htmlspecialchars($r['admin_qc_offer_status'] ?? '') ?>"><?= strtoupper($r['admin_qc_offer_status'] ?? '') ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #eee; padding-top:8px; font-size:12px;">
                                            <strong>ผู้ขอแลก:</strong><br>
                                            สลิป: <?= getSlipStatusBadge($r['requester_slip_status']) ?><br>
                                            เลขพัสดุ: <span class="tracking-number"><?= htmlspecialchars($r['requester_tracking_to_admin'] ?? '-') ?></span><br>
                                            QC: <span class="qc-status-badge bg-<?= htmlspecialchars($r['admin_qc_requester_status'] ?? '') ?>"><?= strtoupper($r['admin_qc_requester_status'] ?? '') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <!-- Slip Actions -->
                                        <?php if ($r['offer_slip_status'] === 'pending' && !empty($r['offer_slip_url'])): ?>
                                            <div style="margin-bottom:10px; background:#fff3cd; padding:8px; border-radius:8px; border:1px solid #ffeeba;">
                                                <div style="font-size:11px; color:#856404; margin-bottom:4px;">สลิปตั้งโพสต์ <a href="<?= htmlspecialchars($r['offer_slip_url']) ?>" target="_blank" style="color:#0284c7;">[ดูสลิป]</a></div>
                                                <form method="POST" style="display:flex; gap:4px;">
                                                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                    <input type="hidden" name="side" value="offer">
                                                    <input type="hidden" name="action" value="approve_slip">
                                                    <button type="submit" name="slip_result" value="approved" class="btn-approve">อนุมัติ</button>
                                                    <button type="submit" name="slip_result" value="rejected" class="btn-reject">ไม่อนุมัติ</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($r['requester_slip_status'] === 'pending' && !empty($r['requester_slip_url'])): ?>
                                            <div style="margin-bottom:10px; background:#fff3cd; padding:8px; border-radius:8px; border:1px solid #ffeeba;">
                                                <div style="font-size:11px; color:#856404; margin-bottom:4px;">สลิปขอแลก <a href="<?= htmlspecialchars($r['requester_slip_url']) ?>" target="_blank" style="color:#0284c7;">[ดูสลิป]</a></div>
                                                <form method="POST" style="display:flex; gap:4px;">
                                                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                    <input type="hidden" name="side" value="requester">
                                                    <input type="hidden" name="action" value="approve_slip">
                                                    <button type="submit" name="slip_result" value="approved" class="btn-approve">อนุมัติ</button>
                                                    <button type="submit" name="slip_result" value="rejected" class="btn-reject">ไม่อนุมัติ</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>

                                        <!-- QC Actions -->
                                        <?php if ($r['admin_qc_requester_status'] === 'pending' || $r['admin_qc_offer_status'] === 'pending'): ?>
                                            <div style="margin-top:10px; text-align:center;">
                                                <button type="button" class="btn-approve" style="padding:10px 12px; font-size:13px; width:100%; background:#0284c7;" onclick="openAdminQcForm(<?= $r['request_id'] ?>, <?= (int)$r['requester_id'] ?>, <?= (int)$r['target_owner_id'] ?>)"><i class="fas fa-camera"></i> ทำบันทึกผล QC ทั้งสองฝ่าย</button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($r['admin_qc_requester_status'] === 'passed' && $r['admin_qc_offer_status'] === 'passed'): ?>
                                            <?php if (empty($r['admin_tracking_to_requester'])): ?>
                                                <form method="POST" style="display:flex; flex-direction:column; gap:6px;">
                                                    <input type="hidden" name="action" value="admin_ship">
                                                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                    <input type="text" name="admin_tracking_to_offer" placeholder="เลขพัสดุ (ส่งให้ผู้ตั้งโพสต์)" required style="padding:4px; font-size:11px; border:1px solid #ccc; border-radius:4px;">
                                                    <input type="text" name="admin_tracking_to_requester" placeholder="เลขพัสดุ (ส่งให้ผู้ขอแลก)" required style="padding:4px; font-size:11px; border:1px solid #ccc; border-radius:4px;">
                                                    <button type="submit" class="btn-approve" style="padding:4px;">บันทึกจัดส่ง</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="action-badge badge-green">จัดส่งเรียบร้อย</span>
                                                <div style="font-size:11px; margin-top:4px;">
                                                    ผู้ตั้งโพสต์: <?= htmlspecialchars($r['admin_tracking_to_offer'] ?? '') ?><br>
                                                    ผู้ขอแลก: <?= htmlspecialchars($r['admin_tracking_to_requester'] ?? '') ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
"""

html_header = """<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>QC แลกเปลี่ยนหนังสือ - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        body { background: #fdfbfb; margin: 0; display: flex; min-height: 100vh; font-family: 'Prompt', sans-serif;}
        .admin-main-wrap { flex: 1; min-width: 0; padding: 40px 50px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-title { color: #00796B; font-size: 26px; font-weight: bold; display: flex; align-items: center; gap: 10px; margin: 0;}
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid #e0f2f1; padding-bottom: 0; }
        .tab-btn { padding: 12px 24px; color: #777; text-decoration: none; font-weight: 600; border-bottom: 3px solid transparent; transition: all 0.2s; margin-bottom: -1px; font-size: 15px; }
        .tab-btn:hover { color: #00796B; }
        .tab-btn.active { color: #00796B; border-bottom-color: #00796B; }

        .tablebox { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e0f2f1; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .orders { width: 100%; border-collapse: collapse; min-width: 800px; }
        .orders th, .orders td { padding: 18px 20px; border-bottom: 1px solid #f0f0f0; text-align: left; }
        .orders th { background: #f5fbfb; color: #555; font-weight: 600; font-size: 14px; }
        .orders tr:hover { background: #fafafa; }
        .orders td { color: #444; font-size: 14px; vertical-align: top; }
        
        .user-profile { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .user-info-text { display: flex; flex-direction: column; gap: 2px; }
        .user-name { font-weight: 600; color: #333; font-size: 14px; }
        .email-tag { font-size: 11px; color: #00796B; background: #e0f2f1; padding: 2px 6px; border-radius: 4px; display: inline-block; width: fit-content; }
        
        .book-title { font-size: 13px; color: #666; margin-top: 4px; }
        
        .action-badge { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: bold; display: inline-block; }
        .badge-green { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .badge-blue { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .badge-orange { background: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }
        
        .qc-status-badge { display: inline-block; padding: 4px 10px; font-size: 12px; border-radius: 6px; font-weight: 700; }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-passed { background: #d4edda; color: #155724; }
        .bg-failed { background: #f8d7da; color: #721c24; }

        .btn-approve { background: #10b981; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; transition: 0.2s;}
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #ef4444; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; transition: 0.2s;}
        .btn-reject:hover { background: #dc2626; }
        
        .tracking-number { font-family: monospace; font-size: 13px; font-weight: 700; color: #00796B; }

        .empty-state { text-align: center; padding: 50px 20px; color: #888; font-size: 15px; }
        
        /* Layout overrides for general wrapper */
        .admin-wrap { max-width: unset; margin: unset; padding: unset; min-height: unset; font-family: unset; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>

    <div class="admin-main-wrap">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-clipboard-check"></i> จัดการคำขอแลกเปลี่ยน (QC)</h1>
        </div>

        <?php if (isset($_SESSION['flash_msg'])): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:8px; border-left:5px solid #28a745;">
                <?= htmlspecialchars($_SESSION['flash_msg']) ?>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

        <div class="tabs">
            <a href="?tab=posts" class="tab-btn <?= $tab === 'posts' ? 'active' : '' ?>">ตรวจสอบโพสต์ (Admin QC)</a>
            <a href="?tab=physical" class="tab-btn <?= $tab === 'physical' ? 'active' : '' ?>">ตรวจสอบสภาพหนังสือจริง (Physical QC)</a>
        </div>
"""

# Extract the 'posts' tab from current content
posts_tab_match = re.search(r'<\?php if \(\$tab === \'posts\'\): \?>(.*?)<\?php if \(\$tab === \'physical\'\): \?>', content, re.DOTALL)
posts_tab = "        <?php if ($tab === 'posts'): ?>\n" + posts_tab_match.group(1).strip() + "\n        <?php endif; ?>\n"

# Extract the Modal from the end of the file
modal_match = re.search(r'<!-- Admin QC Form Modal -->.*?</script>', content, re.DOTALL)
modal_html = modal_match.group(0)

# Build the complete file
final_content = php_logic + "\n" + html_header + "\n" + posts_tab + "\n" + new_physical_tab + "\n        <?php endif; ?>\n    </div>\n"
final_content += """
    <!-- Modal -->
    <div id="imgModal" class="modal" onclick="this.style.display='none'">
        <img class="modal-content" id="imgModalContent">
    </div>

    <div id="condModal" class="modal" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal-content" style="background:#fff; padding:20px; border-radius:12px; width:90%; max-width:800px; max-height:90vh; overflow-y:auto; margin:5% auto;">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">รูปภาพสภาพหนังสือที่ตกลงกันไว้</h3>
            <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <p style="margin-bottom:5px; font-weight:600;">ปกหน้า</p>
                    <img id="condFront" style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="">
                </div>
                <div style="flex:1; min-width:200px;">
                    <p style="margin-bottom:5px; font-weight:600;">ปกหลัง</p>
                    <img id="condBack" style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="">
                </div>
                <div style="flex:1; min-width:200px;">
                    <p style="margin-bottom:5px; font-weight:600;">สันหนังสือ</p>
                    <img id="condSpine" style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="">
                </div>
            </div>
            <div style="text-align:right; margin-top:20px;">
                <button type="button" class="btn-reject" onclick="document.getElementById('condModal').style.display='none'">ปิด</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(src) {
            document.getElementById('imgModal').style.display = "block";
            document.getElementById('imgModalContent').src = src;
        }

        function openCondModal(front, back, spine) {
            document.getElementById('condModal').style.display = 'block';
            document.getElementById('condFront').src = front;
            document.getElementById('condBack').src = back;
            document.getElementById('condSpine').src = spine;
        }
    </script>
"""
final_content += "\n" + modal_html + "\n</body>\n</html>"

with open('c:/laragon/www/wanawan_project/admin/qc_exchanges.php', 'w', encoding='utf-8') as f:
    f.write(final_content)
print("Restored original UI!")
