<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$modalHtml = <<<'HTML'
 <div id="conditionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px);animation:fadeIn 0.2s ease-out;">
     <div style="background:#fff;width:95%;max-width:640px;border-radius:12px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.2);display:flex;flex-direction:column;height:90vh;animation:popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
         <div style="padding:15px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;background:#fffaf0;">
             <h3 style="margin:0;color:#92400e;font-size:16px;display:flex;align-items:center;gap:8px;">📝 ฟอร์มยืนยันสภาพสินค้า</h3>
             <button onclick="document.getElementById('conditionModal').style.display='none';document.getElementById('conditionIframe').src='';" style="background:none;border:none;font-size:24px;cursor:pointer;color:#999;padding:0;line-height:1;">&times;</button>
         </div>
         <iframe id="conditionIframe" style="width:100%;flex:1;border:none;background:#f8fafc;" src=""></iframe>
     </div>
 </div>
 <div id="deleteConfirmModal" class="confirm-modal-overlay">
HTML;

$jsLogic = <<<'JS'
 document.addEventListener('DOMContentLoaded', () => {
     window.addEventListener('message', function(e) {
         if(e.data && e.data.action === 'close_condition_modal') {
             const m = document.getElementById('conditionModal');
             if(m) {
                 m.style.display = 'none';
                 document.getElementById('conditionIframe').src = '';
                 window.location.reload();
             }
         }
     });
     
     document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
JS;

if (strpos($content, 'id="conditionModal"') === false) {
    $content = str_replace('<div id="deleteConfirmModal" class="confirm-modal-overlay">', $modalHtml, $content);
    $content = str_replace(
        "document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {",
        $jsLogic,
        $content
    );
    file_put_contents($file, $content);
    echo "Added condition modal successfully.\n";
} else {
    echo "Condition modal already exists.\n";
}
