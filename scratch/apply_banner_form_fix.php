<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$newJs = <<<'JS'
  if(!data.tradeOption){
   const propForm = document.createElement('form');
   propForm.method = 'post';
   propForm.style.cssText = 'padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,.05)';
   propForm.innerHTML = `<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['chat_csrf']??''); ?>">
   <input type="hidden" name="request_id" value="${data.requestId}">
   <label style="font-size:12px; font-weight:600; color:#1e40af; margin-bottom:6px; display:block;">💬 กำลังเจรจา: กรุณาเสนอรูปแบบการแลกเปลี่ยน</label>
   <select name="trade_option" id="tradeOptionSelect" style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:6px;margin-bottom:10px;font-size:13px;outline:none;" required>
     <option value="" disabled selected>-- เลือกรูปแบบการแลกเปลี่ยน --</option>
     <option value="direct_escrow">ส่งตรงให้กัน (P2P Escrow)</option>
     <option value="admin_qc">ส่งผ่านส่วนกลาง (Admin QC)</option>
     <option value="meetup">นัดรับ (Meet-up)</option>
   </select>
   <div id="deadlineGroup" style="display:none;background:#f8fafc;padding:10px;border-radius:6px;margin-bottom:10px;border:1px solid #e2e8f0;">
     <label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">ระยะเวลาจัดส่ง (วัน)</label>
     <select name="deadline_days" style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;outline:none;">
       <option value="3">ภายใน 3 วัน</option>
       <option value="5">ภายใน 5 วัน</option>
       <option value="7">ภายใน 7 วัน</option>
     </select>
   </div>
   <div id="meetupGroup" style="display:none;background:#f8fafc;padding:10px;border-radius:6px;margin-bottom:10px;border:1px solid #e2e8f0;">
     <div style="display:flex;gap:8px;margin-bottom:8px;">
       <div style="flex:1;"><label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">วันที่</label><input type="date" name="meetup_date" min="<?php echo date('Y-m-d'); ?>" style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;outline:none;"></div>
       <div style="flex:1;"><label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">เวลา</label><input type="time" name="meetup_time" style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;outline:none;"></div>
     </div>
     <label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">สถานที่นัดรับ</label>
     <input type="text" name="meetup_location" placeholder="ระบุสถานที่นัดพบ..." style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;outline:none;">
   </div>
   <button name="propose_full_trade" class="btn primary" style="width:100%;padding:10px;border-radius:6px;background:#3b82f6;color:#fff;font-weight:600;border:0;cursor:pointer;">เสนอเงื่อนไขนี้</button>`;
   const w=document.createElement('div');w.append(propForm);insertBanner(w);
   
   propForm.querySelector('#tradeOptionSelect').addEventListener('change', function() {
     const val = this.value;
     propForm.querySelector('#deadlineGroup').style.display = (val === 'direct_escrow' || val === 'admin_qc') ? 'block' : 'none';
     propForm.querySelector('#meetupGroup').style.display = (val === 'meetup') ? 'block' : 'none';
   });
  }
JS;

$pattern = '/if\(!data\.tradeOption\)\{[\s\S]*?w\.append\(b\);insertBanner\(w\);\s*\}/u';

$content = preg_replace($pattern, $newJs, $content, 1, $count);
if ($count > 0) {
    file_put_contents($file, $content);
    echo "Replaced banner JS successfully.\n";
} else {
    echo "Pattern not found!\n";
}
