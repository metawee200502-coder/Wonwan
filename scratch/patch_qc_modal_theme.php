<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

$searchModal = <<<HTML
    <!-- Admin QC Form Modal -->
    <div id="adminQcFormModal" class="modal">
        <div class="modal-content" style="background:#fff; border-radius:12px; width:90%; max-width:500px; padding:0; overflow:hidden;">
            <div style="padding:15px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; background:#f8fafc;">
                <h3 style="margin:0; color:#334155; font-size:16px;">ฟอร์มตรวจสอบสภาพสินค้า (Admin QC)</h3>
                <span onclick="document.getElementById('adminQcFormModal').style.display='none'" style="cursor:pointer; font-size:24px; color:#94a3b8; line-height:1;">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data" style="padding:20px;">
                <input type="hidden" name="action" value="submit_qc_report">
                <input type="hidden" name="request_id" id="qc_request_id">
                <input type="hidden" name="side" id="qc_side">
                <input type="hidden" name="item_owner_id" id="qc_item_owner_id">
                
                <div style="margin-bottom:15px;">
                    <label style="font-weight:bold; font-size:13px; color:#475569; display:block; margin-bottom:8px;">1. สินค้าตรงปก/ถูกต้องหรือไม่?</label>
                    <div style="display:flex; gap:15px;">
                        <label style="display:flex; align-items:center; gap:5px; font-size:14px; cursor:pointer;">
                            <input type="radio" name="is_match" value="1" required checked> ตรงปก (ผ่าน)
                        </label>
                        <label style="display:flex; align-items:center; gap:5px; font-size:14px; cursor:pointer;">
                            <input type="radio" name="is_match" value="0" required> ไม่ตรงปก/มีปัญหา (ไม่ผ่าน)
                        </label>
                    </div>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="font-weight:bold; font-size:13px; color:#475569; display:block; margin-bottom:5px;">2. หมายเหตุจากส่วนกลาง (ตำหนิเพิ่มเติม, สภาพแพ็กเกจ ฯลฯ)</label>
                    <textarea name="admin_notes" rows="3" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;" placeholder="ตัวอย่าง: มุมหนังสือมีรอยยับเล็กน้อย, สินค้าอยู่ในสภาพดีมาก" required></textarea>
                </div>
                
                <div style="margin-bottom:20px;">
                    <label style="font-weight:bold; font-size:13px; color:#475569; display:block; margin-bottom:5px;">3. รูปถ่ายสินค้าจากส่วนกลาง (ถ่ายเป็นหลักฐาน)</label>
                    <input type="file" name="qc_photos[]" multiple accept="image/*" style="width:100%; border:1px solid #cbd5e1; padding:8px; border-radius:6px; font-size:13px;" required>
                    <small style="color:#64748b; font-size:11px; margin-top:4px; display:block;">เลือกได้หลายรูป</small>
                </div>
                
                <button type="submit" class="action-btn" style="width:100%; background:#2563eb; font-size:14px; padding:10px;">ส่งรายงาน QC เข้าห้องแชท</button>
            </form>
        </div>
    </div>
    
    <script>
    function openAdminQcForm(reqId, side, itemOwnerId) {
        document.getElementById('qc_request_id').value = reqId;
        document.getElementById('qc_side').value = side;
        document.getElementById('qc_item_owner_id').value = itemOwnerId;
        document.getElementById('adminQcFormModal').style.display = 'flex';
    }
    </script>
HTML;

$replaceModal = <<<HTML
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

$content = str_replace($searchModal, $replaceModal, $content);
file_put_contents($file, $content);
echo "QC Modal theme patched.\n";
