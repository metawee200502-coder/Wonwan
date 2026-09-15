<?php
$file = 'c:\laragon\www\wanawan_project\pages\chat.php';
$content = file_get_contents($file);

// Replace lines 192-247
// We are targeting the block from `if (data.rawStatus === 'accepted') {` to the end of the `if (!data.tradeOption) { ... }` block
$search = <<< 'JS'
 if (data.rawStatus === 'accepted') {
    if (!data.tradeOption) {
      const propForm = document.createElement('form');
      propForm.method = 'post';
      propForm.style.marginTop = '15px';
      propForm.style.padding = '0 16px 16px 16px';
      
      let html = '<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['chat_csrf'])?>">';
      html += '<input type="hidden" name="request_id" value="'+data.requestId+'">';
      html += '<label style="font-size:12px; font-weight:600; color:#8b5266; margin-bottom:4px; display:block;">เลือกรูปแบบการแลกเปลี่ยน</label>';
      html += '<select name="trade_option" id="tradeOptionSelect" style="box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;margin-bottom:10px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;box-shadow:0 2px 4px rgba(169,85,117,0.03);transition:border 0.2s;" onfocus="this.style.borderColor=\'#d18aa7\'" onblur="this.style.borderColor=\'#f2dbe4\'" required>';
      html += '<option value="" disabled selected>-- กรุณาเลือก --</option>';
      html += '<option value="direct_escrow">ส่งตรงให้กัน (P2P Escrow)</option>';
      html += '<option value="admin_qc">ส่งผ่านส่วนกลาง (Admin QC)</option>';
      html += '<option value="meetup">นัดรับ (Meet-up)</option>';
      html += '</select>';
      
      html += '<div id="deadlineGroup" style="display:none; box-sizing:border-box; background:#fdf5f7; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #f2dbe4;">';
      html += '<label style="font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;">ระยะเวลาจัดส่ง (วัน)</label>';
      html += '<select name="deadline_days" style="box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;box-shadow:0 2px 4px rgba(169,85,117,0.03);transition:border 0.2s;" onfocus="this.style.borderColor=\'#d18aa7\'" onblur="this.style.borderColor=\'#f2dbe4\'">';
      html += '<option value="3">ภายใน 3 วัน</option>';
      html += '<option value="5">ภายใน 5 วัน</option>';
      html += '<option value="7">ภายใน 7 วัน</option>';
      html += '</select>';
      html += '</div>';

      html += '<div id="meetupGroup" style="display:none; box-sizing:border-box; background:#fdf5f7; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #f2dbe4;">';
      html += '<div style="display:flex;gap:8px;margin-bottom:8px;">';
      html += '<div style="flex:1;"><label style="font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;">วันที่</label><input type="date" name="meetup_date" style="box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;" min="<?=date("Y-m-d")?>"></div>';
      html += '<div style="flex:1;"><label style="font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;">เวลา</label><input type="time" name="meetup_time" style="box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;"></div>';
      html += '</div>';
      html += '<label style="font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;">สถานที่นัดรับ</label>';
      html += '<input type="text" name="meetup_location" placeholder="ระบุสถานที่นัดพบ..." style="box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;">';
      html += '</div>';
      
      html += '<button name="propose_full_trade" class="btn primary" style="box-sizing:border-box;width:100%;font-size:13px;background:linear-gradient(135deg, #d18aa7, #b66f68);border:0;color:#fff;font-weight:600;padding:10px;border-radius:6px;cursor:pointer;box-shadow:0 4px 10px rgba(209,138,167,0.3);transition:transform 0.2s;font-family:inherit;" onmouseover="this.style.transform=\'scale(1.02)\'" onmouseout="this.style.transform=\'scale(1)\'">ยื่นข้อเสนอ</button>';
      
      propForm.innerHTML = html;
      summary.append(propForm);
      
      const optSelect = propForm.querySelector('#tradeOptionSelect');
      const dlGroup = propForm.querySelector('#deadlineGroup');
      const muGroup = propForm.querySelector('#meetupGroup');
      optSelect.addEventListener('change', function() {
          if(this.value === 'direct_escrow' || this.value === 'admin_qc') {
              dlGroup.style.display = 'block';
              muGroup.style.display = 'none';
          } else if(this.value === 'meetup') {
              dlGroup.style.display = 'none';
              muGroup.style.display = 'block';
          } else {
              dlGroup.style.display = 'none';
              muGroup.style.display = 'none';
          }
      });
      
    } else {
JS;

// Use precise regex to prevent missing matches due to whitespace.
// A simpler way: we can just find the exact block and replace it using strpos.
$start_pos = strpos($content, "if (data.rawStatus === 'accepted') {");
$end_pos = strpos($content, "} else {", $start_pos);

if ($start_pos !== false && $end_pos !== false) {
    $js_new = <<< 'JS'
if (data.rawStatus === 'accepted') {
    if (!data.tradeOption) {
      // Create top banner
      const bannerHtml = `
          <div style="background-color: #fff9f9; border-bottom: 1px solid #fde2e2; padding: 15px 20px; text-align: center; border-radius: 8px 8px 0 0;">
              <div style="color: #c53030; font-weight: 600; font-size: 16px; margin-bottom: 5px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                  <i class="fas fa-exclamation-triangle" style="color: #ed8936;"></i> สถานะกำลังเจรจา
              </div>
              <div style="color: #718096; font-size: 14px; margin-bottom: 15px;">
                  กรุณายื่นข้อเสนอหรือรอให้อีกฝ่ายยื่นข้อเสนอวิธีการแลกเปลี่ยน
              </div>
              <button id="btnProposeTrade" style="background: linear-gradient(135deg, #d18aa7, #b66f68); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 14px; transition: transform 0.2s; box-shadow: 0 4px 10px rgba(209,138,167,0.3);">
                  ยื่นข้อเสนอการแลกเปลี่ยน
              </button>
          </div>
      `;
      const chatMain = document.querySelector('.chat-main');
      if (chatMain) {
          const chatHeader = chatMain.querySelector('.chat-header');
          if(chatHeader) chatHeader.insertAdjacentHTML('afterend', bannerHtml);
          else chatMain.insertAdjacentHTML('afterbegin', bannerHtml);
          
          document.getElementById('btnProposeTrade').addEventListener('click', function() {
              Swal.fire({
                  title: 'เลือกรูปแบบการแลกเปลี่ยน',
                  html: `
                      <form id="tradeForm" method="post" action="">
                          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['chat_csrf']??'')?>">
                          <input type="hidden" name="request_id" value="${data.requestId}">
                          <div style="text-align: left; margin-bottom: 15px;">
                              <label style="font-size:13px; font-weight:600; color:#8b5266; margin-bottom:6px; display:block;">เลือกรูปแบบการแลกเปลี่ยน</label>
                              <select name="trade_option" id="swalTradeOptionSelect" style="box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid #f2dbe4;border-radius:6px;font-size:14px;color:#4a5568;background:#fff;outline:none;font-family:inherit;" required>
                                  <option value="" disabled selected>-- กรุณาเลือก --</option>
                                  <option value="direct_escrow">ส่งตรงให้กัน (P2P Escrow)</option>
                                  <option value="admin_qc">ส่งผ่านส่วนกลาง (Admin QC)</option>
                                  <option value="meetup">นัดรับ (Meet-up)</option>
                              </select>
                          </div>
                          
                          <div id="swalDeadlineGroup" style="display:none; text-align: left; box-sizing:border-box; background:#fdf5f7; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid #f2dbe4;">
                              <label style="font-size:13px; font-weight:600; color:#8b5266; display:block; margin-bottom:6px;">ระยะเวลาจัดส่ง (วัน)</label>
                              <select name="deadline_days" style="box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid #f2dbe4;border-radius:6px;font-size:14px;color:#4a5568;background:#fff;outline:none;font-family:inherit;">
                                  <option value="3">ภายใน 3 วัน</option>
                                  <option value="5">ภายใน 5 วัน</option>
                                  <option value="7">ภายใน 7 วัน</option>
                              </select>
                          </div>
                          
                          <div id="swalMeetupGroup" style="display:none; text-align: left; box-sizing:border-box; background:#fdf5f7; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid #f2dbe4;">
                              <div style="display:flex;gap:12px;margin-bottom:12px;">
                                  <div style="flex:1;">
                                      <label style="font-size:13px; font-weight:600; color:#8b5266; display:block; margin-bottom:6px;">วันที่</label>
                                      <input type="date" name="meetup_date" style="box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid #f2dbe4;border-radius:6px;font-size:14px;color:#4a5568;background:#fff;outline:none;font-family:inherit;" min="<?=date("Y-m-d")?>">
                                  </div>
                                  <div style="flex:1;">
                                      <label style="font-size:13px; font-weight:600; color:#8b5266; display:block; margin-bottom:6px;">เวลา</label>
                                      <input type="time" name="meetup_time" style="box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid #f2dbe4;border-radius:6px;font-size:14px;color:#4a5568;background:#fff;outline:none;font-family:inherit;">
                                  </div>
                              </div>
                              <label style="font-size:13px; font-weight:600; color:#8b5266; display:block; margin-bottom:6px;">สถานที่นัดรับ</label>
                              <input type="text" name="meetup_location" placeholder="ระบุสถานที่นัดพบ..." style="box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid #f2dbe4;border-radius:6px;font-size:14px;color:#4a5568;background:#fff;outline:none;font-family:inherit;">
                          </div>
                      </form>
                  `,
                  showCancelButton: true,
                  confirmButtonText: 'ยื่นข้อเสนอ',
                  cancelButtonText: 'ยกเลิก',
                  confirmButtonColor: '#b66f68',
                  cancelButtonColor: '#a0aec0',
                  customClass: { popup: 'swal-prompt-font', title: 'swal-prompt-font', htmlContainer: 'swal-prompt-font', confirmButton: 'swal-prompt-font', cancelButton: 'swal-prompt-font' },
                  didOpen: () => {
                      const optSelect = document.getElementById('swalTradeOptionSelect');
                      const dlGroup = document.getElementById('swalDeadlineGroup');
                      const muGroup = document.getElementById('swalMeetupGroup');
                      optSelect.addEventListener('change', function() {
                          if(this.value === 'direct_escrow' || this.value === 'admin_qc') {
                              dlGroup.style.display = 'block';
                              muGroup.style.display = 'none';
                          } else if(this.value === 'meetup') {
                              dlGroup.style.display = 'none';
                              muGroup.style.display = 'block';
                          } else {
                              dlGroup.style.display = 'none';
                              muGroup.style.display = 'none';
                          }
                      });
                  },
                  preConfirm: () => {
                      const form = document.getElementById('tradeForm');
                      if(!form.checkValidity()) {
                          form.reportValidity();
                          return false;
                      }
                      const btn = document.createElement('input');
                      btn.type = 'hidden';
                      btn.name = 'propose_full_trade';
                      btn.value = '1';
                      form.appendChild(btn);
                      form.submit();
                  }
              });
          });
      }
    } else {
JS;
    $content = substr_replace($content, $js_new, $start_pos, $end_pos - $start_pos + 8);
    file_put_contents($file, $content);
    echo "Patched successfully!";
} else {
    echo "Failed to find boundaries in chat.php";
}
