<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// 1. Add PHP backend logic for submit_qc_report_both
$phpLogic = <<<PHP
    } elseif (\$action === 'submit_qc_report_both') {
        \$request_id = (int)\$_POST['request_id'];
        \$req_user_id = (int)\$_POST['requester_id'];
        \$off_user_id = (int)\$_POST['offer_id'];
        
        \$req_is_match = (int)\$_POST['req_is_match'];
        \$req_notes = trim(\$_POST['req_notes'] ?? '');
        \$off_is_match = (int)\$_POST['off_is_match'];
        \$off_notes = trim(\$_POST['off_notes'] ?? '');
        
        // Handle file uploads
        \$uploadDir = __DIR__ . '/../pages/uploads/qc/';
        if (!is_dir(\$uploadDir)) mkdir(\$uploadDir, 0777, true);
        
        \$req_photos = [];
        if (!empty(\$_FILES['req_photos']['name'][0])) {
            foreach (\$_FILES['req_photos']['tmp_name'] as \$key => \$tmp_name) {
                if (\$_FILES['req_photos']['error'][\$key] === UPLOAD_ERR_OK) {
                    \$ext = pathinfo(\$_FILES['req_photos']['name'][\$key], PATHINFO_EXTENSION);
                    \$newName = 'qc_req_' . \$request_id . '_' . uniqid() . '.' . \$ext;
                    if (move_uploaded_file(\$tmp_name, \$uploadDir . \$newName)) {
                        \$req_photos[] = '/pages/uploads/qc/' . \$newName;
                    }
                }
            }
        }
        
        \$off_photos = [];
        if (!empty(\$_FILES['off_photos']['name'][0])) {
            foreach (\$_FILES['off_photos']['tmp_name'] as \$key => \$tmp_name) {
                if (\$_FILES['off_photos']['error'][\$key] === UPLOAD_ERR_OK) {
                    \$ext = pathinfo(\$_FILES['off_photos']['name'][\$key], PATHINFO_EXTENSION);
                    \$newName = 'qc_off_' . \$request_id . '_' . uniqid() . '.' . \$ext;
                    if (move_uploaded_file(\$tmp_name, \$uploadDir . \$newName)) {
                        \$off_photos[] = '/pages/uploads/qc/' . \$newName;
                    }
                }
            }
        }
        
        // Save to admin_qc_reports
        \$stmt = \$pdo->prepare("INSERT INTO admin_qc_reports (request_id, item_owner_id, is_match, admin_notes, photo_urls, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        \$stmt->execute([\$request_id, \$off_user_id, \$req_is_match, \$req_notes, json_encode(\$req_photos)]);
        \$stmt->execute([\$request_id, \$req_user_id, \$off_is_match, \$off_notes, json_encode(\$off_photos)]);
        
        // Update statuses
        \$req_status = \$req_is_match ? 'passed' : 'failed';
        \$off_status = \$off_is_match ? 'passed' : 'failed';
        \$pdo->prepare("UPDATE exchange_requests SET admin_qc_requester_status = ?, admin_qc_offer_status = ? WHERE request_id = ?")->execute([\$req_status, \$off_status, \$request_id]);
        
        // Send to chat
        \$q = \$pdo->prepare("SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id = (SELECT exchange_id FROM exchange_requests WHERE request_id = ?) LIMIT 1");
        \$q->execute([\$request_id]);
        \$conv = \$q->fetch(PDO::FETCH_ASSOC);
        if (\$conv) {
            \$msg = ",,,,؅,,T, ,, ,^,,-,s,,,,z,,',T,,1%,,^,,?,1^, ,T,?,,,\\n";
            \$msg .= ",o,11%, ,1%,؅1,,z,, 1O: " . (\$req_is_match ? ", ,,؅,>,?" : "1,,1^, ,,؅,>,?") . "\\n";
            \$msg .= ",,,,1?,, ,,: " . \$req_notes . "\\n\\n";
            \$msg .= ",o,11%,,,-1?,,?: " . (\$off_is_match ? ", ,,؅,>,?" : "1,,1^, ,,؅,>,?") . "\\n";
            \$msg .= ",,,,1?,, ,,: " . \$off_notes;
            
            \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, metadata) VALUES (?, ?, ?, 'admin_qc_report', ?)")
                ->execute([\$conv['conversation_id'], \$_SESSION['user_id'], \$msg, json_encode([
                    'req_is_match' => \$req_is_match,
                    'req_notes' => \$req_notes,
                    'req_photos' => \$req_photos,
                    'off_is_match' => \$off_is_match,
                    'off_notes' => \$off_notes,
                    'off_photos' => \$off_photos
                ])]);
            \$pdo->prepare("UPDATE chat_conversations SET last_message_at = NOW() WHERE conversation_id = ?")->execute([\$conv['conversation_id']]);
        }
        
        \$_SESSION['flash_msg'] = ",s,,T,-, ,?,,,,s,1%,,,,,,؅,,T, ,, ,^,,-,s QC 1?,,,,s,1%,-,,1?,1%, ";
PHP;

if (strpos($content, "elseif (\$action === 'submit_qc_report_both')") === false) {
    $content = str_replace("    } elseif (\$action === 'admin_ship') {", $phpLogic . "\n    } elseif (\$action === 'admin_ship') {", $content);
}

// 2. Add the Modal HTML
$modalHtml = <<<HTML
    <!-- Admin QC Form Modal -->
    <div id="adminQcFormModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
        <div style="background:#fff; width:90%; max-width:800px; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.2); overflow:hidden; margin:auto;">
            <div style="background:#f1f5f9; padding:20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; color:#334155; font-size:18px;"><i class="fas fa-clipboard-check"></i> บันทึกผลการตรวจสอบ QC สินค้า</h3>
                <span onclick="document.getElementById('adminQcFormModal').style.display='none'" style="cursor:pointer; font-size:26px; color:#b66f68; line-height:1; opacity:0.7;">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data" style="padding:20px;">
                <input type="hidden" name="action" value="submit_qc_report_both">
                <input type="hidden" name="request_id" id="qc_request_id">
                <input type="hidden" name="requester_id" id="qc_requester_id">
                <input type="hidden" name="offer_id" id="qc_offer_id">
                
                <div style="display:flex; gap:20px; flex-wrap:wrap;">
                    <!-- Section 1: Requester -->
                    <div style="flex:1; min-width:300px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:12px; padding:15px;">
                        <h4 style="margin:0 0 10px 0; color:#0f172a; font-size:15px; border-bottom:2px solid #e2e8f0; padding-bottom:8px;">ผู้ขอแลก <span style="color:#2563eb;">[ผู้ส่งมา]</span></h4>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:8px;">1. สินค้าตรงตามที่ตกลงหรือไม่?</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="1" required checked> ตรงตาม (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="0" required> ไม่ตรงตาม (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:5px;">2. หมายเหตุจากส่วนกลาง</label>
                            <textarea name="req_notes" rows="3" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;" placeholder="ตัวอย่าง: สภาพดีมาก ไม่มีรอยตำหนิ" required></textarea>
                        </div>
                        
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพจากส่วนกลาง (ถ่ายให้ชัดเจน)</label>
                            <input type="file" name="req_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                    
                    <!-- Section 2: Offerer -->
                    <div style="flex:1; min-width:300px; background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:15px;">
                        <h4 style="margin:0 0 10px 0; color:#7f1d1d; font-size:15px; border-bottom:2px solid #fca5a5; padding-bottom:8px;">ผู้ตั้งโพสต์ <span style="color:#dc2626;">[ผู้รับแลก]</span></h4>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:8px;">1. สินค้าตรงตามที่ตกลงหรือไม่?</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="1" required checked> ตรงตาม (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="0" required> ไม่ตรงตาม (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:5px;">2. หมายเหตุจากส่วนกลาง</label>
                            <textarea name="off_notes" rows="3" style="width:100%; padding:8px; border:1px solid #fca5a5; border-radius:6px; font-size:13px;" placeholder="ตัวอย่าง: มีรอยยับมุมหนังสือ" required></textarea>
                        </div>
                        
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพจากส่วนกลาง (ถ่ายให้ชัดเจน)</label>
                            <input type="file" name="off_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                    <button type="submit" style="width:100%; background:#d18aa7; color:#fff; border:none; border-radius:8px; font-size:16px; font-weight:bold; padding:14px; cursor:pointer; transition:background 0.3s;" onmouseover="this.style.background='#a95575'" onmouseout="this.style.background='#d18aa7'">ส่งผลการ QC ให้ผู้ใช้งานทั้งสองฝ่าย</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function openAdminQcForm(reqId, reqUserId, offUserId) {
        document.getElementById('qc_request_id').value = reqId;
        document.getElementById('qc_requester_id').value = reqUserId;
        document.getElementById('qc_offer_id').value = offUserId;
        document.getElementById('adminQcFormModal').style.display = 'flex';
    }
    </script>
HTML;

if (strpos($content, 'id="adminQcFormModal"') === false) {
    $content = str_replace('</body>', $modalHtml . "\n</body>", $content);
}

// 3. Remove the old individual passed/failed buttons and add the open modal button
$reqPattern = '/<\?php\s*if \(\$r\[\'admin_qc_requester_status\'\] === \'pending\' && !empty\(\$r\[\'requester_tracking_to_admin\'\]\)\):\s*\?>\s*<form method="POST"[^>]*>.*?<\/form>\s*<\?php\s*endif;\s*\?>/s';
$content = preg_replace($reqPattern, '', $content);

$offPattern = '/<\?php\s*if \(\$r\[\'admin_qc_offer_status\'\] === \'pending\' && !empty\(\$r\[\'offer_tracking_to_admin\'\]\)\):\s*\?>\s*<form method="POST"[^>]*>.*?<\/form>\s*<\?php\s*endif;\s*\?>/s';
$content = preg_replace($offPattern, '', $content);

// 4. Inject the unified button right under the physical-box
$unifiedButton = <<<HTML
                            <?php if (\$r['admin_qc_requester_status'] === 'pending' || \$r['admin_qc_offer_status'] === 'pending'): ?>
                                <div style="margin-top:20px; text-align:center;">
                                    <button type="button" class="btn-approve" style="padding:12px 24px; font-size:15px; width:100%; max-width:400px; background:#0284c7;" onclick="openAdminQcForm(<?= \$r['request_id'] ?>, <?= (int)\$r['requester_id'] ?>, <?= (int)\$r['target_owner_id'] ?>)"><i class="fas fa-camera"></i> ทำบันทึกผล QC ทั้งสองฝ่าย (แนบรูป)</button>
                                </div>
                            <?php endif; ?>
HTML;

$content = str_replace('<!-- ถ้าผ่านทั้งคู่ ให้ส่งกลับ -->', $unifiedButton . "\n                            <!-- ถ้าผ่านทั้งคู่ ให้ส่งกลับ -->", $content);

// 5. Update SQL to include target_owner_id
$content = str_replace(
    "e_req.title as req_title, e_req.image_cover as req_img,",
    "e_req.title as req_title, e_req.image_cover as req_img, e_req.owner_id as target_owner_id,",
    $content
);

file_put_contents($file, $content);
echo "Re-applied Modal and SQL logic!";
