<?php
$chatPath = __DIR__ . '/../pages/chat.php';
$chatContent = file_get_contents($chatPath);

// 1. Replace !myD block
$oldMyD = <<<'EOD'
     if(!myD){
    const b=document.createElement('div');
    b.style.cssText='padding:18px;background:linear-gradient(135deg, #fff1f2, #ffe4e6);color:#be123c;border-radius:12px;font-size:14px;text-align:center;border:1px solid #fecdd3;box-shadow:0 4px 10px rgba(225, 29, 72, 0.05);margin-bottom:12px;';
    if(myS==='pending_verification' || myS==='reviewing'){
        b.innerHTML='<div style="font-size:24px; margin-bottom:8px;">⏳</div><strong style="font-size:15px; display:block; margin-bottom:4px;">รอแอดมินตรวจสอบการชำระเงิน</strong><span style="font-size:18px; font-weight:600; color:#e11d48;">฿'+parseFloat(ptR).toFixed(2)+'</span>';
    }else{
        b.innerHTML='<div style="font-size:24px; margin-bottom:8px;">💳</div><strong style="font-size:15px; display:block; margin-bottom:4px;">คุณต้องชำระเงินมัดจำ</strong><span style="font-size:18px; font-weight:600; color:#e11d48;">฿'+parseFloat(ptR).toFixed(2)+'</span><br><br>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='<svg style="width:16px; height:16px; display:inline-block; vertical-align:-3px; margin-right:6px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>ชำระเงินมัดจำ';
        btn.style.cssText='padding:10px 20px; background:#e11d48; border-color:#e11d48; color:#fff; border-radius:8px; font-weight:500; font-family:inherit; box-shadow:0 2px 4px rgba(225,29,72,0.2); transition:transform 0.2s;';
        btn.onclick=function(){
    const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
    if(modal&&iframe){
        modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg> แนบสลิปชำระเงินมัดจำ';
        iframe.src='/pages/trade_upload_slip.php?request_id='+data.requestId+'&iframe=1';
        modal.style.display='flex';
    } else {
        window.location.href='/pages/trade_upload_slip.php?request_id='+data.requestId;
    }
};b.append(btn);}cb.append(b);}
EOD;

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

if (strpos($chatContent, "const btn=document.createElement('button');\n        btn.className='btn primary';\n        btn.innerHTML='<svg") !== false) {
    $chatContent = str_replace($oldMyD, $newMyD, $chatContent);
    echo "Patched !myD block successfully\n";
} else {
    echo "Could not find old !myD block\n";
}

// 2. Replace !ptD block
$oldPtD = <<<'EOD'
     if(!ptD){
    const b=document.createElement('div');
    b.style.cssText='padding:18px;background:linear-gradient(135deg, #eff6ff, #dbeafe);color:#1e3a8a;border-radius:12px;font-size:14px;text-align:center;border:1px solid #bfdbfe;box-shadow:0 4px 10px rgba(37, 99, 235, 0.05);margin-bottom:12px;';
    b.innerHTML='<div style="font-size:24px; margin-bottom:8px;">⏳</div><strong style="font-size:15px; display:block; margin-bottom:4px;">รออีกฝ่ายชำระเงินมัดจำ</strong><span style="font-size:18px; font-weight:600; color:#2563eb;">฿'+parseFloat(myR).toFixed(2)+'</span>';
    cb.append(b);
}
EOD;

$newPtD = <<<'EOD'
     if(!ptD){
    const b=document.createElement('div');
    b.style.cssText='padding:12px 16px;background:#eff6ff;color:#1e40af;border-radius:8px;display:flex;align-items:center;border:1px solid #dbeafe;margin-bottom:12px;gap:12px;';
    b.innerHTML='<div style="color:#3b82f6;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รออีกฝ่ายชำระเงินมัดจำ</strong><span style="font-size:12px;opacity:0.8;">จำนวน ฿'+parseFloat(myR).toFixed(2)+'</span></div>';
    cb.append(b);
}
EOD;

if (strpos($chatContent, "b.innerHTML='<div style=\"font-size:24px; margin-bottom:8px;\">⏳</div>") !== false) {
    $chatContent = str_replace($oldPtD, $newPtD, $chatContent);
    echo "Patched !ptD block successfully\n";
} else {
    echo "Could not find old !ptD block\n";
}

// 3. Update Message Listener
$oldListener = <<<'EOD'
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
EOD;

$newListener = <<<'EOD'
     window.addEventListener('message', function(e) {
         if(e.data && e.data.action === 'deposit_paid') {
             const banner = document.getElementById('my-deposit-banner');
             if(banner) {
                 banner.innerHTML = '<div style="display:flex;align-items:center;gap:12px;"><div style="color:#e11d48;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รอแอดมินตรวจสอบความถูกต้องของสลิป</strong><span style="font-size:12px;opacity:0.8;">จำนวน ฿'+parseFloat(e.data.amount).toFixed(2)+'</span></div></div>';
             }
         }
         if(e.data && (e.data.action === 'close_condition_modal' || e.data.action === 'close_condition_modal_no_reload')) {
             const m = document.getElementById('conditionModal');
             if(m) {
                 m.style.display = 'none';
                 document.getElementById('conditionIframe').src = '';
                 if (e.data.action === 'close_condition_modal') {
                     window.location.reload();
                 }
             }
         }
     });
EOD;

if (strpos($chatContent, "window.addEventListener('message', function(e) {") !== false) {
    $chatContent = str_replace($oldListener, $newListener, $chatContent);
    echo "Patched listener successfully\n";
} else {
    echo "Could not find old listener block\n";
}

file_put_contents($chatPath, $chatContent);
echo "Done saving chat.php\n";

// Now patch trade_upload_slip.php
$slipPath = __DIR__ . '/../pages/trade_upload_slip.php';
$slipContent = file_get_contents($slipPath);

$oldPostMessage = "<?php if(\$isIframe): ?><script>setTimeout(() => window.parent.postMessage({action: 'close_condition_modal'}, '*'), 1500);</script><?php endif; ?>";

$newPostMessage = <<<'EOD'
        <?php if($isIframe): ?>
        <script>
        setTimeout(() => {
            window.parent.postMessage({action: 'deposit_paid', amount: <?= $requiredDeposit ?>}, '*');
            window.parent.postMessage({action: 'close_condition_modal_no_reload'}, '*');
        }, 1500);
        </script>
        <?php endif; ?>
EOD;

if (strpos($slipContent, $oldPostMessage) !== false) {
    $slipContent = str_replace($oldPostMessage, $newPostMessage, $slipContent);
    file_put_contents($slipPath, $slipContent);
    echo "Patched trade_upload_slip.php successfully\n";
} else {
    echo "Could not find postMessage in trade_upload_slip.php\n";
}
