<?php
$modalHTML = <<<HTML
    <!-- Admin QC Form Modal -->
    <div id="adminQcFormModal" class="modal">
        <div class="modal-content" style="background:#fff; border-radius:16px; width:90%; max-width:800px; padding:0; overflow:auto; max-height:90vh; border:1px solid #e0d6d1; box-shadow:0 10px 30px rgba(139,82,102,0.1);">
            <div style="padding:18px 20px; border-bottom:1px solid #f6e7e0; display:flex; justify-content:space-between; align-items:center; background:#fffaf7; position:sticky; top:0; z-index:10;">
                <h3 style="margin:0; color:#8b5266; font-size:18px;">แบบฟอร์มตรวจสอบสภาพหนังสือจริง (QC จากส่วนกลาง)</h3>
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
                        <h4 style="margin:0 0 10px 0; color:#0f172a; font-size:15px; border-bottom:2px solid #e2e8f0; padding-bottom:8px;">1. หนังสือของฝั่งผู้ขอแลก <span style="color:#2563eb;">[หนังสือต้นทาง]</span><br><small style="color:#64748b; font-weight:normal;">(กรอกข้อมูลหลังจากได้รับพัสดุจากผู้ขอแลกแล้ว)</small></h4>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:8px;">1. สภาพตรงปก/ตรงตามที่ตกลงกันไว้หรือไม่</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="1" required checked> ตรงปก (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="req_is_match" value="0" required> ไม่ตรงปก (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:5px;">2. หมายเหตุ/คำอธิบายเพิ่มเติมจากแอดมิน</label>
                            <textarea name="req_notes" rows="3" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;" placeholder="เช่น: สภาพดีมาก ไม่มีรอยยับ" required></textarea>
                        </div>
                        
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#334155; display:block; margin-bottom:5px;">3. อัปโหลดรูปถ่ายสภาพจริง (เพื่อส่งให้ผู้โพสต์ดู)</label>
                            <input type="file" name="req_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                    
                    <!-- Section 2: Offerer -->
                    <div style="flex:1; min-width:300px; background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:15px;">
                        <h4 style="margin:0 0 10px 0; color:#7f1d1d; font-size:15px; border-bottom:2px solid #fca5a5; padding-bottom:8px;">2. หนังสือของฝั่งผู้โพสต์ <span style="color:#dc2626;">[หนังสือปลายทาง]</span><br><small style="color:#991b1b; font-weight:normal;">(กรอกข้อมูลหลังจากได้รับพัสดุจากผู้โพสต์แล้ว)</small></h4>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:8px;">1. สภาพตรงปก/ตรงตามที่ตกลงกันไว้หรือไม่</label>
                            <div style="display:flex; gap:15px;">
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="1" required checked> ตรงปก (ผ่าน)</label>
                                <label style="display:flex; align-items:center; gap:5px; font-size:13px; cursor:pointer;"><input type="radio" name="off_is_match" value="0" required> ไม่ตรงปก (ไม่ผ่าน)</label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:5px;">2. หมายเหตุ/คำอธิบายเพิ่มเติมจากแอดมิน</label>
                            <textarea name="off_notes" rows="3" style="width:100%; padding:8px; border:1px solid #fca5a5; border-radius:6px; font-size:13px;" placeholder="เช่น: สภาพดีมาก ไม่มีรอยยับ" required></textarea>
                        </div>
                        
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#7f1d1d; display:block; margin-bottom:5px;">3. อัปโหลดรูปถ่ายสภาพจริง (เพื่อส่งให้ผู้ขอแลกดู)</label>
                            <input type="file" name="off_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                    <button type="submit" style="width:100%; background:#d18aa7; color:#fff; border:none; border-radius:8px; font-size:16px; font-weight:bold; padding:14px; cursor:pointer; transition:background 0.3s;" onmouseover="this.style.background='#a95575'" onmouseout="this.style.background='#d18aa7'">บันทึกผลการ QC และส่งรายงานให้ทั้งสองฝ่าย</button>
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

$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

// Inject right before Condition Modal
$content = str_replace('<!-- Condition Modal -->', $modalHTML . "\n\n    <!-- Condition Modal -->", $content);

file_put_contents($file, $content);
echo "Injected modal HTML successfully!\n";
