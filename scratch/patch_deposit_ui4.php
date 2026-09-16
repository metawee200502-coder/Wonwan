<?php
$chatPath = __DIR__ . '/../pages/chat.php';
$chatContent = file_get_contents($chatPath);

// 1. Replace !myD block using preg_replace
$myDPattern = '/if\(!myD\)\{\s*const b=document\.createElement\(\'div\'\);\s*b\.style\.cssText=\'padding:18px;background:linear-gradient.*?cb\.append\(b\);\}/s';
$newMyD = <<<'EOD'
if(!myD){
    const b=document.createElement('div');
    b.id = 'my-deposit-banner';
    b.style.cssText='padding:12px 16px;background:#fff1f2;color:#be123c;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #ffe4e6;margin-bottom:12px;gap:10px;';
    if(myS==='pending_verification' || myS==='reviewing'){
        b.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#e11d48;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รอแอดมินตรวจสอบความถูกต้องของสลิป</strong><span style="font-size:12px;opacity:0.8;">จำนวน ฿'+parseFloat(ptR).toFixed(2)+'</span></div></div>';
    }else{
        b.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#e11d48;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">คุณต้องชำระเงินมัดจำ</strong><span style="font-size:12px;opacity:0.8;">จำนวน ฿'+parseFloat(ptR).toFixed(2)+'</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='ชำระเงิน';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#e11d48; border-color:#e11d48; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
    const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
    if(modal&&iframe){
        modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg> แนบสลิปชำระเงินมัดจำ';
        iframe.src='/pages/trade_upload_slip.php?request_id='+data.requestId+'&iframe=1&conversation_id='+(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');
        modal.style.display='flex';
    } else {
        window.location.href='/pages/trade_upload_slip.php?request_id='+data.requestId;
    }
};b.append(btn);}cb.append(b);}
EOD;

$chatContent = preg_replace($myDPattern, $newMyD, $chatContent, 1, $count);
if ($count > 0) {
    echo "Patched !myD successfully\n";
} else {
    echo "FAILED to patch !myD\n";
}

file_put_contents($chatPath, $chatContent);
echo "Done.";
