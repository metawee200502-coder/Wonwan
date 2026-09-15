<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

// 1. Replace the buttons in the table
$searchButtons = <<<HTML
                                            <?php if (\$r['admin_qc_requester_status'] === 'pending' && !empty(\$r['requester_tracking_to_admin'])): ?>
                                                <button type="button" onclick="openAdminQcForm(<?= \$r['request_id'] ?>, 'requester', <?= \$r['requester_id'] ?>)" class="action-btn" style="background:#0f172a; color:#fff; font-size:11px; padding:2px 6px; margin-top:4px; border:none; border-radius:4px;">ทำแบบฟอร์มตรวจรับ (QC)</button>
                                            <?php elseif (\$r['admin_qc_requester_status'] !== 'pending'): ?>
                                                <span class="badge badge-<?= \$r['requester_qc_acceptance_status'] === 'accepted' ? 'success' : (\$r['requester_qc_acceptance_status'] === 'rejected' ? 'danger' : 'warning') ?>" style="margin-left:4px;">
                                                    ผู้รับ: <?= \$r['requester_qc_acceptance_status'] === 'accepted' ? 'ยอมรับแล้ว' : (\$r['requester_qc_acceptance_status'] === 'rejected' ? 'ปฏิเสธ' : 'รอการยืนยัน') ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (\$r['admin_qc_offer_status'] === 'pending' && !empty(\$r['offer_tracking_to_admin'])): ?>
                                                <button type="button" onclick="openAdminQcForm(<?= \$r['request_id'] ?>, 'offer', <?= \$r['owner_id'] ?>)" class="action-btn" style="background:#0f172a; color:#fff; font-size:11px; padding:2px 6px; margin-top:4px; border:none; border-radius:4px;">ทำแบบฟอร์มตรวจรับ (QC)</button>
                                            <?php elseif (\$r['admin_qc_offer_status'] !== 'pending'): ?>
                                                <span class="badge badge-<?= \$r['offer_qc_acceptance_status'] === 'accepted' ? 'success' : (\$r['offer_qc_acceptance_status'] === 'rejected' ? 'danger' : 'warning') ?>" style="margin-left:4px;">
                                                    ผู้รับ: <?= \$r['offer_qc_acceptance_status'] === 'accepted' ? 'ยอมรับแล้ว' : (\$r['offer_qc_acceptance_status'] === 'rejected' ? 'ปฏิเสธ' : 'รอการยืนยัน') ?>
                                                </span>
                                            <?php endif; ?>
HTML;

$replaceButtons = <<<HTML
                                            <?php if (\$r['admin_qc_requester_status'] !== 'pending'): ?>
                                                <div style="font-size:11px; margin-top:2px;">ผู้ขอแลก (การตัดสินใจของผู้โพสต์): <span class="badge badge-<?= \$r['requester_qc_acceptance_status'] === 'accepted' ? 'success' : (\$r['requester_qc_acceptance_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= \$r['requester_qc_acceptance_status'] === 'accepted' ? 'ยอมรับแล้ว' : (\$r['requester_qc_acceptance_status'] === 'rejected' ? 'ปฏิเสธ' : 'รอการยืนยัน') ?></span></div>
                                            <?php endif; ?>
                                            
                                            <?php if (\$r['admin_qc_offer_status'] !== 'pending'): ?>
                                                <div style="font-size:11px; margin-top:2px;">ผู้โพสต์ (การตัดสินใจของผู้ขอแลก): <span class="badge badge-<?= \$r['offer_qc_acceptance_status'] === 'accepted' ? 'success' : (\$r['offer_qc_acceptance_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= \$r['offer_qc_acceptance_status'] === 'accepted' ? 'ยอมรับแล้ว' : (\$r['offer_qc_acceptance_status'] === 'rejected' ? 'ปฏิเสธ' : 'รอการยืนยัน') ?></span></div>
                                            <?php endif; ?>

                                            <?php if ((\$r['admin_qc_requester_status'] === 'pending' && !empty(\$r['requester_tracking_to_admin'])) || (\$r['admin_qc_offer_status'] === 'pending' && !empty(\$r['offer_tracking_to_admin']))): ?>
                                                <button type="button" onclick="openAdminQcForm(<?= \$r['request_id'] ?>, <?= \$r['requester_id'] ?>, <?= \$r['owner_id'] ?>)" class="action-btn" style="background:#b66f68; color:#fff; font-size:12px; padding:6px 10px; margin-top:8px; border:none; border-radius:6px; width:100%; cursor:pointer;">📝 ทำแบบฟอร์มตรวจรับ QC</button>
                                            <?php endif; ?>
HTML;

$content = str_replace($searchButtons, $replaceButtons, $content);

// 2. Replace the modal HTML
$searchModal = <<<HTML
    <!-- Admin QC Form Modal -->
    <div id="adminQcFormModal" class="modal">
        <div class="modal-content" style="background:#fff; border-radius:16px; width:90%; max-width:500px; padding:0; overflow:hidden; border:1px solid #e0d6d1; box-shadow:0 10px 30px rgba(139,82,102,0.1);">
            <div style="padding:18px 20px; border-bottom:1px solid #f6e7e0; display:flex; justify-content:space-between; background:#fffaf7;">
                <h3 style="margin:0; color:#8b5266; font-size:18px;">ฟอร์มตรวจสอบสภาพสินค้า <span id="qc_form_title_role" style="font-size:14px; color:#b66f68; font-weight:normal;"></span></h3>
                <span onclick="document.getElementById('adminQcFormModal').style.display='none'" style="cursor:pointer; font-size:26px; color:#b66f68; line-height:1; opacity:0.7;">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data" style="padding:20px;">
                <input type="hidden" name="action" value="submit_qc_report">
                <input type="hidden" name="request_id" id="qc_request_id">
                <input type="hidden" name="side" id="qc_side">
                <input type="hidden" name="item_owner_id" id="qc_item_owner_id">
                
                <div style="background:#fdfaf8; border:1px solid #f6e7e0; border-radius:10px; padding:15px; margin-bottom:15px;">
                    <label style="font-weight:600; font-size:14px; color:#a95575; display:block; margin-bottom:12px;">1. สินค้าตรงปก/ถูกต้องหรือไม่?</label>
                    <div style="display:flex; gap:20px;">
                        <label style="display:flex; align-items:center; gap:8px; font-size:14px; cursor:pointer; color:#4a4a4a;">
                            <input type="radio" name="is_match" value="1" required checked style="accent-color:#8b5266; width:18px; height:18px;"> ตรงปก (ผ่าน)
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:14px; cursor:pointer; color:#4a4a4a;">
                            <input type="radio" name="is_match" value="0" required style="accent-color:#8b5266; width:18px; height:18px;"> ไม่ตรงปก/มีปัญหา (ไม่ผ่าน)
                        </label>
                    </div>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="font-weight:600; font-size:14px; color:#a95575; display:block; margin-bottom:8px;">2. หมายเหตุจากส่วนกลาง (ตำหนิเพิ่มเติม, สภาพแพ็กเกจ ฯลฯ)</label>
                    <textarea name="admin_notes" rows="3" style="width:100%; padding:12px; border:1px solid #e0d6d1; border-radius:8px; font-size:14px; outline:none; transition:border 0.3s;" onfocus="this.style.borderColor='#b66f68'" onblur="this.style.borderColor='#e0d6d1'" placeholder="ตัวอย่าง: มุมหนังสือมีรอยยับเล็กน้อย, สินค้าอยู่ในสภาพดีมาก" required></textarea>
                </div>
                
                <div style="margin-bottom:24px;">
                    <label style="font-weight:600; font-size:14px; color:#a95575; display:block; margin-bottom:8px;">3. รูปถ่ายสินค้าจากส่วนกลาง (ถ่ายเป็นหลักฐาน)</label>
                    <div style="border:1px dashed #d3948b; background:#fffcfb; padding:15px; border-radius:8px; text-align:center;">
                        <input type="file" name="qc_photos[]" multiple accept="image/*" style="width:100%; font-size:14px; color:#4a4a4a;" required>
                        <small style="color:#b66f68; font-size:12px; margin-top:8px; display:block;">เลือกได้หลายรูปเพื่อความชัดเจน</small>
                    </div>
                </div>
                
                <button type="submit" style="width:100%; background:#d18aa7; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; padding:12px; cursor:pointer; transition:background 0.3s;" onmouseover="this.style.background='#a95575'" onmouseout="this.style.background='#d18aa7'">ส่งรายงาน QC เข้าห้องแชท</button>
            </form>
        </div>
    </div>
    
    <script>
    function openAdminQcForm(reqId, side, itemOwnerId) {
        document.getElementById('qc_request_id').value = reqId;
        document.getElementById('qc_side').value = side;
        document.getElementById('qc_item_owner_id').value = itemOwnerId;
        
        let roleName = side === 'requester' ? '(ของฝั่ง: ผู้ขอแลก)' : '(ของฝั่ง: ผู้ลงประกาศ)';
        document.getElementById('qc_form_title_role').innerText = roleName;
        
        document.getElementById('adminQcFormModal').style.display = 'flex';
    }
    </script>
HTML;

$replaceModal = <<<HTML
    <!-- Admin QC Form Modal -->
    <div id="adminQcFormModal" class="modal">
        <div class="modal-content" style="background:#fff; border-radius:16px; width:90%; max-width:800px; padding:0; overflow:auto; max-height:90vh; border:1px solid #e0d6d1; box-shadow:0 10px 30px rgba(139,82,102,0.1);">
            <div style="padding:18px 20px; border-bottom:1px solid #f6e7e0; display:flex; justify-content:space-between; align-items:center; background:#fffaf7; position:sticky; top:0; z-index:10;">
                <h3 style="margin:0; color:#8b5266; font-size:18px;">ฟอร์มตรวจสอบสภาพสินค้า (QC ทั้งสองฝ่าย)</h3>
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
                        <h4 style="margin:0 0 10px 0; color:#0f172a; font-size:15px; border-bottom:2px solid #e2e8f0; padding-bottom:8px;">📦 ตรวจสอบหนังสือของ <span style="color:#2563eb;">[ผู้ขอแลก]</span><br><small style="color:#64748b; font-weight:normal;">(รายงานนี้จะถูกส่งไปให้ผู้ลงประกาศเป็นคนตัดสินใจ)</small></h4>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:8px;">1. สินค้าตรงปก/ถูกต้องหรือไม่?</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="1" required checked> ตรงปก (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="0" required> ไม่ตรงปก (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:5px;">2. หมายเหตุจากส่วนกลาง</label>
                            <textarea name="req_notes" rows="3" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;" placeholder="ตัวอย่าง: มีรอยยับเล็กน้อย" required></textarea>
                        </div>
                        
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:5px;">3. รูปถ่ายสินค้าจากส่วนกลาง (ถ่ายเป็นหลักฐาน)</label>
                            <input type="file" name="req_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                    
                    <!-- Section 2: Offerer -->
                    <div style="flex:1; min-width:300px; background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:15px;">
                        <h4 style="margin:0 0 10px 0; color:#7f1d1d; font-size:15px; border-bottom:2px solid #fca5a5; padding-bottom:8px;">📦 ตรวจสอบหนังสือของ <span style="color:#dc2626;">[ผู้ลงประกาศ]</span><br><small style="color:#991b1b; font-weight:normal;">(รายงานนี้จะถูกส่งไปให้ผู้ขอแลกเป็นคนตัดสินใจ)</small></h4>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:8px;">1. สินค้าตรงปก/ถูกต้องหรือไม่?</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="1" required checked> ตรงปก (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="0" required> ไม่ตรงปก (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:5px;">2. หมายเหตุจากส่วนกลาง</label>
                            <textarea name="off_notes" rows="3" style="width:100%; padding:8px; border:1px solid #fca5a5; border-radius:6px; font-size:13px;" placeholder="ตัวอย่าง: หนังสือสภาพดีมาก" required></textarea>
                        </div>
                        
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:5px;">3. รูปถ่ายสินค้าจากส่วนกลาง (ถ่ายเป็นหลักฐาน)</label>
                            <input type="file" name="off_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                    <button type="submit" style="width:100%; background:#d18aa7; color:#fff; border:none; border-radius:8px; font-size:16px; font-weight:bold; padding:14px; cursor:pointer; transition:background 0.3s;" onmouseover="this.style.background='#a95575'" onmouseout="this.style.background='#d18aa7'">ส่งรายงาน QC เข้าห้องแชทของทั้งสองฝ่าย</button>
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

$content = str_replace($searchModal, $replaceModal, $content);
file_put_contents($file, $content);
echo "QC Modal unified patched.\n";
