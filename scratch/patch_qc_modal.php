<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

$modalHtml = <<<HTML

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

$insertPoint = '<!-- Condition Modal -->';
$content = str_replace($insertPoint, $modalHtml . "\n\n    " . $insertPoint, $content);

file_put_contents($file, $content);
echo "Modal appended.\n";
