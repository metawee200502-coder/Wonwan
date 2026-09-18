<?php
$file = __DIR__ . '/../admin/disputes.php';
$code = file_get_contents($file);

// Replace confirm in form
$code = str_replace(
    'onsubmit="return confirm(\'ยืนยันการตัดสิน?\')"',
    'onsubmit="event.preventDefault(); var form = this; showConfirmModal(\'ยืนยันการตัดสินข้อพิพาทนี้ใช่หรือไม่?\', function(){ form.submit(); })"',
    $code
);

// Add custom modal HTML and CSS
$modalHtml = <<<HTML
<style>
.custom-confirm-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.custom-confirm-modal.open { display:flex; animation: fadeIn 0.2s ease; }
.custom-confirm-box { background:#fff; width:400px; max-width:90%; border-radius:16px; padding:24px; box-shadow:0 10px 40px rgba(0,0,0,0.2); text-align:center; transform:scale(0.95); transition:transform 0.2s ease; }
.custom-confirm-modal.open .custom-confirm-box { transform:scale(1); }
.custom-confirm-icon { width:64px; height:64px; background:#ccfbf1; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; color:#0f5132; }
.custom-confirm-title { font-size:18px; font-weight:800; color:#1e1b3a; margin-bottom:10px; }
.custom-confirm-actions { display:flex; gap:12px; margin-top:24px; }
.custom-confirm-btn { flex:1; padding:12px; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; transition:opacity 0.2s; }
.custom-confirm-cancel { background:#f3f4f6; color:#4b5563; }
.custom-confirm-ok { background:#115e59; color:#fff; }
.custom-confirm-btn:hover { opacity:0.85; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
</style>
<div class="custom-confirm-modal" id="customConfirmModal">
  <div class="custom-confirm-box">
    <div class="custom-confirm-icon"><svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"></path><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg></div>
    <div class="custom-confirm-title" id="customConfirmMsg">ยืนยันการดำเนินการ?</div>
    <div class="custom-confirm-actions">
      <button class="custom-confirm-btn custom-confirm-cancel" onclick="closeConfirmModal()">ยกเลิก</button>
      <button class="custom-confirm-btn custom-confirm-ok" id="customConfirmOkBtn">ยืนยัน</button>
    </div>
  </div>
</div>
HTML;

$code = str_replace('<div class="toast-msg"', $modalHtml . "\n<div class=\"toast-msg\"", $code);

// Add JS logic for modal
$jsFuncs = <<<JS
var confirmCallback = null;
function showConfirmModal(msg, callback) {
    document.getElementById('customConfirmMsg').innerText = msg;
    confirmCallback = callback;
    document.getElementById('customConfirmModal').classList.add('open');
}
function closeConfirmModal() {
    document.getElementById('customConfirmModal').classList.remove('open');
    confirmCallback = null;
}
document.getElementById('customConfirmOkBtn').addEventListener('click', function() {
    if(confirmCallback) confirmCallback();
    closeConfirmModal();
});
JS;

$code = str_replace('var CSRF=', $jsFuncs . "\nvar CSRF=", $code);

// Refactor reviewReturn
$oldReviewReturn = <<<JS
function reviewReturn(id,action){
  var msg=action==='approve'?'ยืนยันการอนุมัติ? คำขอจะถูกส่งให้ร้านค้ายืนยันรับสินค้าคืน':'ยืนยันการปฏิเสธคำขอนี้?';
  if(!confirm(msg))return;
  var note=(document.getElementById('note-'+id)||{value:''}).value.trim();
  var btnA=document.getElementById('btn-approve-'+id),btnR=document.getElementById('btn-reject-'+id);
  [btnA,btnR].forEach(function(b){if(b){b.disabled=true;b.textContent='กำลังดำเนินการ...'}});
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('return_id',id);fd.append('action',action);fd.append('admin_note',note);
  fetch('/api/superadmin_review_return.php',{method:'POST',body:fd})
    .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
    .then(function(d){
      if(d.ok){showToast(d.message,false);var c=document.getElementById('rcard-'+id);
        if(c){c.style.transition='opacity .4s,transform .4s';c.style.opacity='0';c.style.transform='translateX(24px)';
          setTimeout(function(){c.remove();var cnt=document.querySelectorAll('.return-card').length;
            if(cnt===0){var l=document.getElementById('return-list');if(l)l.innerHTML='<div class="empty-state"><div class="empty-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div><h3>ไม่มีคำขอที่รอพิจารณา</h3></div>'}},440)}}
      else{showToast(d.message||'เกิดข้อผิดพลาด',true);resetBtns(id)}})
    .catch(function(){showToast('เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ',true);resetBtns(id)});
}
JS;

$newReviewReturn = <<<JS
function reviewReturn(id,action){
  var msg=action==='approve'?'ยืนยันการอนุมัติ? คำขอจะถูกส่งให้ร้านค้ายืนยันรับสินค้าคืน':'ยืนยันการปฏิเสธคำขอนี้?';
  showConfirmModal(msg, function() {
      var note=(document.getElementById('note-'+id)||{value:''}).value.trim();
      var btnA=document.getElementById('btn-approve-'+id),btnR=document.getElementById('btn-reject-'+id);
      [btnA,btnR].forEach(function(b){if(b){b.disabled=true;b.textContent='กำลังดำเนินการ...'}});
      var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('return_id',id);fd.append('action',action);fd.append('admin_note',note);
      fetch('/api/superadmin_review_return.php',{method:'POST',body:fd})
        .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
        .then(function(d){
          if(d.ok){showToast(d.message,false);var c=document.getElementById('rcard-'+id);
            if(c){c.style.transition='opacity .4s,transform .4s';c.style.opacity='0';c.style.transform='translateX(24px)';
              setTimeout(function(){c.remove();var cnt=document.querySelectorAll('.return-card').length;
                if(cnt===0){var l=document.getElementById('return-list');if(l)l.innerHTML='<div class="empty-state"><div class="empty-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div><h3>ไม่มีคำขอที่รอพิจารณา</h3></div>'}},440)}}
          else{showToast(d.message||'เกิดข้อผิดพลาด',true);resetBtns(id)}})
        .catch(function(){showToast('เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ',true);resetBtns(id)});
  });
}
JS;

$code = str_replace($oldReviewReturn, $newReviewReturn, $code);

// Refactor processRefund
$oldProcessRefund = <<<JS
function processRefund(id,amtLabel){
  if(!confirm('ยืนยันการโอนเงิน ฿'+amtLabel+' เข้า Wonwan Wallet ของลูกค้า?\\n\\nการดำเนินการนี้ไม่สามารถยกเลิกได้'))return;
  var btn=document.getElementById('btn-refund-'+id);
  if(btn){btn.disabled=true;btn.textContent='⏳ กำลังโอนเงิน...'}
  var note=(document.getElementById('rnote-'+id)||{value:''}).value.trim();
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('return_id',id);fd.append('refund_note',note);
  fetch('/api/superadmin_process_refund.php',{method:'POST',body:fd})
    .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
    .then(function(d){
      if(d.ok){
        showToast('<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> '+d.message,false);
        var card=document.getElementById('rcard-'+id);
        if(card){card.style.transition='opacity .5s,transform .5s';card.style.opacity='0';card.style.transform='translateX(28px)';
          setTimeout(function(){card.remove();var cnt=document.querySelectorAll('.return-card').length;
            if(cnt===0){var l=document.getElementById('return-list');if(l)l.innerHTML='<div class="empty-state"><div class="empty-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div><h3>ดำเนินการโอนเงินครบทุกรายการแล้ว</h3></div>'}},520)}
      }else{showToast('<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> '+(d.message||'เกิดข้อผิดพลาด'),true);if(btn){btn.disabled=false;btn.textContent='<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> โอนเงิน ฿'+amtLabel+' เข้า Wallet ลูกค้า'}}
    })
    .catch(function(){showToast('<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ กรุณาลองใหม่',true);if(btn){btn.disabled=false;btn.textContent='<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> โอนเงิน ฿'+amtLabel+' เข้า Wallet ลูกค้า'}});
}
JS;

$newProcessRefund = <<<JS
function processRefund(id,amtLabel){
  showConfirmModal('ยืนยันการโอนเงิน ฿'+amtLabel+' เข้า Wonwan Wallet ของลูกค้า? การดำเนินการนี้ไม่สามารถยกเลิกได้', function() {
      var btn=document.getElementById('btn-refund-'+id);
      if(btn){btn.disabled=true;btn.textContent='⏳ กำลังโอนเงิน...'}
      var note=(document.getElementById('rnote-'+id)||{value:''}).value.trim();
      var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('return_id',id);fd.append('refund_note',note);
      fetch('/api/superadmin_process_refund.php',{method:'POST',body:fd})
        .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
        .then(function(d){
          if(d.ok){
            showToast('<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> '+d.message,false);
            var card=document.getElementById('rcard-'+id);
            if(card){card.style.transition='opacity .5s,transform .5s';card.style.opacity='0';card.style.transform='translateX(28px)';
              setTimeout(function(){card.remove();var cnt=document.querySelectorAll('.return-card').length;
                if(cnt===0){var l=document.getElementById('return-list');if(l)l.innerHTML='<div class="empty-state"><div class="empty-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div><h3>ดำเนินการโอนเงินครบทุกรายการแล้ว</h3></div>'}},520)}
          }else{showToast('<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> '+(d.message||'เกิดข้อผิดพลาด'),true);if(btn){btn.disabled=false;btn.textContent='<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> โอนเงิน ฿'+amtLabel+' เข้า Wallet ลูกค้า'}}
        })
        .catch(function(){showToast('<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ กรุณาลองใหม่',true);if(btn){btn.disabled=false;btn.textContent='<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> โอนเงิน ฿'+amtLabel+' เข้า Wallet ลูกค้า'}});
  });
}
JS;

$code = str_replace($oldProcessRefund, $newProcessRefund, $code);

file_put_contents($file, $code);
echo "Done replacing Confirm Modals.\n";
