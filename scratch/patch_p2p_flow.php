<?php
// ============================================================
// Patch 1: Fix direct_escrow banner logic in chat.php
// ============================================================
$chatFile = 'c:\laragon\www\wanawan_project\pages\chat.php';
$c = file_get_contents($chatFile);
$orig = strlen($c);

// Replace the !conditionsAccepted simple case with smart 3-phase logic
$old = "if(!data.conditionsAccepted){const cb=document.createElement('div');const banner = getCondBanner(); if(banner) cb.append(banner);insertBanner(cb);}";
$new = <<<'JS'
if(!data.conditionsAccepted){
    const cb=document.createElement('div');
    if(!data.myCondition){
        // Phase 1: I haven't filled form yet
        const banner=getCondBanner();
        if(banner)cb.append(banner);
    } else if(!data.partnerCondition){
        // Phase 2: I filled, waiting for partner
        const w=document.createElement('div');
        w.style.cssText='padding:14px;background:#fef9c3;color:#854d0e;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fde047;margin-bottom:10px';
        w.innerHTML='⏳ <strong>รออีกฝ่ายแนบฟอร์มสินค้า</strong><br><small>คุณส่งฟอร์มสภาพสินค้าแล้ว กรุณารอให้อีกฝ่ายส่งฟอร์มของตน</small>';
        cb.append(w);
    } else {
        // Phase 3: Both filled, need to review & accept each other's form
        const w=document.createElement('div');
        w.style.cssText='padding:14px;background:#f0fdf4;color:#166534;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bbf7d0;margin-bottom:10px';
        w.innerHTML='📋 <strong>ทั้งสองฝ่ายส่งฟอร์มแล้ว!</strong><br><small>กรุณาตรวจสอบรายละเอียดหนังสือของอีกฝ่ายและกดยืนยัน</small><br>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.textContent='🔍 ตรวจสอบรายละเอียดหนังสือ';
        btn.style.cssText='padding:8px 16px;margin-top:8px;';
        btn.onclick=function(){
            const modal=document.getElementById('conditionModal');
            const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML='📋 ตรวจสอบฟอร์มสภาพสินค้า';
                iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId;
            }
        };
        w.append(btn);
        cb.append(w);
    }
    if(cb.children.length>0)insertBanner(cb);
}
JS;

$c1 = str_replace($old, $new, $c);
if ($c1 === $c) {
    echo "Patch 1 FAILED: target string not found in chat.php\n";
    exit(1);
}
$c = $c1;
echo "Patch 1 OK: direct_escrow banner logic updated\n";

// Verify
if (substr_count($c, 'doctype') < 1) { echo "ERROR: doctype missing!\n"; exit(1); }
file_put_contents($chatFile, $c);
echo "chat.php saved: +" . (strlen($c) - $orig) . " bytes\n";


// ============================================================
// Patch 2: Add CONDITION_FORM_SUBMITTED system message handler
// ============================================================
$sysFile = 'c:\laragon\www\wanawan_project\config\chat_system_messages.php';
$s = file_get_contents($sysFile);

// Add CONDITION_FORM_SUBMITTED and CONDITION_FORM_ACCEPTED handlers
$old2 = "if (preg_match('/\\[P2P_ITEM_RECEIVED:(\\d+)\\]/', \$text, \$matches)) {";
$new2 = <<<'PHP'
if (preg_match('/\[CONDITION_FORM_SUBMITTED:(\d+)\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg,#fff7ed 0%,#ffedd5 100%);border:1px solid #fed7aa;padding:10px 14px;border-radius:10px;color:#9a3412;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">' .
            '<span style="font-size:14px;margin-right:4px;">📝</span> <strong>ส่งฟอร์มสภาพสินค้าแล้ว</strong> กรุณาตรวจสอบและยืนยันรายละเอียดหนังสือ' .
            '</div>';
    } elseif (preg_match('/\[CONDITION_FORM_ACCEPTED:(\d+)\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">' .
            '<span style="font-size:14px;margin-right:4px;">✅</span> <strong>ยอมรับฟอร์มสภาพสินค้า</strong> ยอมรับรายละเอียดหนังสือของอีกฝ่ายเรียบร้อยแล้ว' .
            '</div>';
    } elseif (preg_match('/\[P2P_ITEM_RECEIVED:(\d+)\]/', $text, $matches)) {
PHP;

$s1 = str_replace($old2, $new2, $s);
if ($s1 === $s) {
    echo "Patch 2 FAILED: target string not found in chat_system_messages.php\n";
    exit(1);
}
$s = $s1;
file_put_contents($sysFile, $s);
echo "Patch 2 OK: CONDITION_FORM_SUBMITTED/ACCEPTED system messages added\n";


// ============================================================
// Patch 3: Fix trade_condition_form.php to pass conversation_id via URL
// so system message gets inserted properly
// ============================================================
// The form already handles $conversationId from GET/POST at line 11
// But the modal iframe src needs conversation_id — we need to add it to the button URL
$chatFile2 = 'c:\laragon\www\wanawan_project\pages\chat.php';
$c2 = file_get_contents($chatFile2);

// In the phase 3 button, add conversation_id  
// Also update the original makeCondBtn iframe src to pass conversationId
$old3 = "iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1';modal.style.display='flex';}else{window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId;}};w.append(btn);return w;};";
$new3 = "iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1&conversation_id='+(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');modal.style.display='flex';}else{window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId;}};w.append(btn);return w;};";
$c3 = str_replace($old3, $new3, $c2);
if ($c3 !== $c2) {
    file_put_contents($chatFile2, $c3);
    echo "Patch 3 OK: conversation_id added to condition form URL\n";
} else {
    echo "Patch 3 SKIPPED (already patched or string changed)\n";
}

// Also fix the phase3 review button URL
$old4 = "iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1';";
$new4 = "iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1&conversation_id='+(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');";
$c4 = str_replace($old4, $new4, $c3);
if ($c4 !== $c3) {
    file_put_contents($chatFile2, $c4);
    echo "Patch 3b OK: all remaining condition form iframe URLs updated\n";
} else {
    echo "Patch 3b SKIPPED\n";
}

// Final validation
$final = file_get_contents($chatFile);
echo "\n=== Final verification ===\n";
echo "doctype: " . substr_count($final, 'doctype') . "\n";
echo "getCondBanner: " . substr_count($final, 'getCondBanner') . "\n";
echo "รออีกฝ่ายแนบฟอร์ม: " . substr_count($final, 'รออีกฝ่ายแนบฟอร์ม') . "\n";
echo "ตรวจสอบรายละเอียดหนังสือ: " . substr_count($final, 'ตรวจสอบรายละเอียดหนังสือ') . "\n";
echo "PHP syntax: ";
$r = shell_exec('C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l "c:\laragon\www\wanawan_project\pages\chat.php" 2>&1');
echo trim($r) . "\n";
