<?php
$chatFile = 'c:\laragon\www\wanawan_project\pages\chat.php';
$chatContent = file_get_contents($chatFile);

// 1. Ensure small modal is added if not exists
if (strpos($chatContent, 'id="logisticsModal"') === false) {
    $search_modal = "<div id=\"deleteConfirmModal\" class=\"confirm-modal-overlay\">";
    $replace_modal = "<div id=\"logisticsModal\" style=\"display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px);animation:fadeIn 0.2s ease-out;\">
     <div style=\"background:#fff;width:95%;max-width:500px;border-radius:12px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.2);display:flex;flex-direction:column;height:650px;animation:popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);\">
         <div style=\"padding:15px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;background:#fffaf0;\">
             <h3 style=\"margin:0;color:#175564;font-size:16px;display:flex;align-items:center;gap:8px;\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M22 11.08V12a10 10 0 1 1-5.93-9.14\"></path><polyline points=\"22 4 12 14.01 9 11.01\"></polyline></svg> แจ้งเลขพัสดุ</h3>
             <button onclick=\"document.getElementById('logisticsModal').style.display='none';document.getElementById('logisticsIframe').src='';\" style=\"background:none;border:none;font-size:24px;cursor:pointer;color:#999;padding:0;line-height:1;\">&times;</button>
         </div>
         <iframe id=\"logisticsIframe\" style=\"width:100%;flex:1;border:none;background:#f8fafc;\" src=\"\"></iframe>
     </div>
 </div>
 <div id=\"deleteConfirmModal\" class=\"confirm-modal-overlay\">";
    $chatContent = str_replace($search_modal, $replace_modal, $chatContent);
}

// 2. Change the tracking button behavior to open the small modal
// We know it is minified, so let's match the exact minified string:
$search_btn = "if(!myTr){sb.innerHTML='\\u2705 <strong>\\u0e01\\u0e32\\u0e23\\u0e27\\u0e32\\u0e07\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33\\u0e40\\u0e2a\\u0e23\\u0e47\\u0e08\\u0e2a\\u0e34\\u0e49\\u0e19!</strong><br>\\u0e08\\u0e31\\u0e14\\u0e2a\\u0e48\\u0e07\\u0e2a\\u0e34\\u0e19\\u0e04\\u0e49\\u0e32\\u0e20\\u0e32\\u0e22\\u0e43\\u0e19 '+dls+'<br><br>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='\\ud83d\\udce6 \\u0e41\\u0e08\\u0e49\\u0e07\\u0e40\\u0e25\\u0e02\\u0e1e\\u0e31\\u0e2a\\u0e14\\u0e38';btn.style.cssText='padding:8px 16px';btn.onclick=function(){window.location.href='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId;};sb.append(btn);}";

$replace_btn = "if(!myTr){
    sb.innerHTML='\\u2705 <strong>\\u0e01\\u0e32\\u0e23\\u0e27\\u0e32\\u0e07\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33\\u0e40\\u0e2a\\u0e23\\u0e47\\u0e08\\u0e2a\\u0e34\\u0e49\\u0e19!</strong><br>\\u0e08\\u0e31\\u0e14\\u0e2a\\u0e48\\u0e07\\u0e2a\\u0e34\\u0e19\\u0e04\\u0e49\\u0e32\\u0e20\\u0e32\\u0e22\\u0e43\\u0e19 '+dls+'<br><br>';
    const btn=document.createElement('button');btn.className='btn primary';btn.textContent='\\ud83d\\udce6 แจ้งเลขพัสดุ';btn.style.cssText='padding:8px 16px';
    btn.onclick=function(){
        const modal=document.getElementById('logisticsModal');const iframe=document.getElementById('logisticsIframe');
        if(modal&&iframe){
            iframe.src='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId+'&conversation_id='+data.conversationId+'&iframe=1';
            modal.style.display='flex';
        } else {
            window.location.href='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId+'&conversation_id='+data.conversationId;
        }
    };
    sb.append(btn);
}";

$chatContent = str_replace($search_btn, $replace_btn, $chatContent);

file_put_contents($chatFile, $chatContent);
echo "Successfully restored banner logic and small modal in chat.php\n";
