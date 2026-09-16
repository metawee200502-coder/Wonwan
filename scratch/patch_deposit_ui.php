<?php
$file = 'c:\laragon\www\wanawan_project\pages\chat.php';
$c = file_get_contents($file);
$orig = strlen($c);

// Replace the entire deposit logic block (conditionsAccepted = true) 
// with a much cleaner, clearer version
$old = <<<'OLD'
   else{
     const myS=(data.myCondition&&data.myCondition.deposit_status)?data.myCondition.deposit_status:'pending';
     const ptS=(data.partnerCondition&&data.partnerCondition.deposit_status)?data.partnerCondition.deposit_status:'pending';
     const myR=(data.myCondition&&data.myCondition.required_deposit_amount!=null)?data.myCondition.required_deposit_amount:null;
     const ptR=(data.partnerCondition&&data.partnerCondition.required_deposit_amount!=null)?data.partnerCondition.required_deposit_amount:null;
     const cb=document.createElement('div');
OLD;

$new = <<<'NEW'
   else{
     // conditionsAccepted = true → DEPOSIT PHASE
     // myR = value of MY book (partner must deposit this to protect me)
     // ptR = value of PARTNER's book (I must deposit this to protect partner)
     const myS=(data.myCondition&&data.myCondition.deposit_status)?data.myCondition.deposit_status:'pending';
     const ptS=(data.partnerCondition&&data.partnerCondition.deposit_status)?data.partnerCondition.deposit_status:'pending';
     const myR=(data.myCondition&&data.myCondition.required_deposit_amount!=null)?parseFloat(data.myCondition.required_deposit_amount):null;
     const ptR=(data.partnerCondition&&data.partnerCondition.required_deposit_amount!=null)?parseFloat(data.partnerCondition.required_deposit_amount):null;
     const cb=document.createElement('div');
     // Header: show escrow explanation
     const hdr=document.createElement('div');
     hdr.style.cssText='padding:10px 14px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:8px;font-size:12px;color:#166534;margin-bottom:10px;text-align:center';
     hdr.innerHTML='🔒 <strong>ระบบวางมัดจำ P2P Escrow</strong><br><small>ฝ่ายละวางมัดจำเท่ากับมูลค่าหนังสือของอีกฝ่าย เพื่อป้องกันการโกง</small>';
     cb.append(hdr);
NEW;

$c1 = str_replace($old, $new, $c);
if ($c1 === $c) {
    // Try without leading spaces
    echo "Exact match failed, searching...\n";
    $pos = strpos($c, 'const myS=(data.myCondition&&data.myCondition.deposit_status)');
    echo "Found deposit block at: " . $pos . "\n";
    exit(1);
}
$c = $c1;
echo "Deposit header added OK\n";

// Now improve the "myR===null" case — instead of a JS prompt, show a proper inline form
$old2 = "if(myR===null){const b=document.createElement('div');b.style.cssText='padding:15px;background:#fff5eb;color:#c05621;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fbd38d;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';b.innerHTML='\\ud83d\\udcb0 <strong>\\u0e01\\u0e33\\u0e2b\\u0e19\\u0e14\\u0e40\\u0e07\\u0e34\\u0e19\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33</strong><br>\\u0e01\\u0e23\\u0e38\\u0e13\\u0e32\\u0e23\\u0e30\\u0e1a\\u0e38\\u0e21\\u0e39\\u0e25\\u0e04\\u0e48\\u0e32\\u0e2a\\u0e34\\u0e19\\u0e04\\u0e49\\u0e32\\u0e40\\u0e1e\\u0e37\\u0e48\\u0e2d\\u0e40\\u0e23\\u0e35\\u0e22\\u0e01\\u0e40\\u0e01\\u0e47\\u0e1a\\u0e40\\u0e07\\u0e34\\u0e19\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33\\u0e08\\u0e32\\u0e01\\u0e2d\\u0e35\\u0e01\\u0e1d\\u0e48\\u0e32\\u0e22<br><br>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='\\u0e40\\u0e2a\\u0e19\\u0e2d\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33';btn.style.cssText='padding:8px 16px;background:#dd6b20;border-color:#dd6b20';btn.onclick=function(){let a=prompt('\\u0e01\\u0e23\\u0e2d\\u0e01\\u0e21\\u0e39\\u0e25\\u0e04\\u0e48\\u0e32\\u0e2a\\u0e34\\u0e19\\u0e04\\u0e49\\u0e32 (\\u0e1a\\u0e32\\u0e17):');if(a!==null&&a.trim()!==''){a=parseFloat(a);if(!isNaN(a)&&a>=0){const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';f.innerHTML='<input type=\"hidden\" name=\"action\" value=\"set_deposit\"><input type=\"hidden\" name=\"request_id\" value=\"'+data.requestId+'\"><input type=\"hidden\" name=\"amount\" value=\"'+a+'\">';document.body.append(f);f.submit();}else alert('\\u0e01\\u0e23\\u0e38\\u0e13\\u0e32\\u0e01\\u0e23\\u0e2d\\u0e01\\u0e15\\u0e31\\u0e27\\u0e40\\u0e25\\u0e02\\u0e17\\u0e35\\u0e48\\u0e16\\u0e39\\u0e01\\u0e15\\u0e49\\u0e2d\\u0e07');}};b.append(btn);cb.append(b);}";

$new2 = <<<'NEW2'
if(myR===null){
        const b=document.createElement('div');
        b.style.cssText='padding:14px;background:#fff7ed;color:#9a3412;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fed7aa;margin-bottom:10px';
        b.innerHTML='💰 <strong>ระบุมูลค่าหนังสือของคุณ</strong><br><small>เพื่อให้อีกฝ่ายวางมัดจำประกัน กรุณาระบุมูลค่า</small><br>';
        const inputRow=document.createElement('div');
        inputRow.style.cssText='display:flex;gap:8px;justify-content:center;margin-top:10px;flex-wrap:wrap;';
        const inp=document.createElement('input');
        inp.type='number';inp.min='0';inp.step='0.01';inp.placeholder='มูลค่า (บาท)';
        inp.style.cssText='padding:7px 12px;border:1px solid #fed7aa;border-radius:6px;font-size:13px;width:150px;font-family:inherit;';
        const btn=document.createElement('button');
        btn.className='btn primary';btn.textContent='ยืนยัน';
        btn.style.cssText='padding:7px 16px;background:#ea580c;border-color:#ea580c;';
        btn.onclick=function(){
            const a=parseFloat(inp.value);
            if(!isNaN(a)&&a>=0){
                const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';
                f.innerHTML='<input type="hidden" name="action" value="set_deposit"><input type="hidden" name="request_id" value="'+data.requestId+'"><input type="hidden" name="amount" value="'+a+'">';
                document.body.append(f);f.submit();
            }else{inp.focus();}
        };
        inputRow.append(inp,btn);
        b.append(inputRow);
        cb.append(b);
     }
NEW2;

$c2 = str_replace($old2, $new2, $c);
if ($c2 === $c) {
    echo "Patch 2 FAILED\n";
    exit(1);
}
$c = $c2;
echo "Deposit input inline form OK\n";

// Improve the !myD banner (need to pay deposit)
$old3 = "if(!myD){const b=document.createElement('div');b.style.cssText='padding:15px;background:#fff5f5;color:#c53030;border-radius:8px;font-size:14px;text-align:center;border:1px solid #feb2b2;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';if(myS==='pending_verification'){b.innerHTML='<i class=\"fas fa-hourglass-half\"></i> <strong>\\u0e23\\u0e2d\\u0e41\\u0e2d\\u0e14\\u0e21\\u0e34\\u0e19\\u0e15\\u0e23\\u0e27\\u0e08\\u0e2a\\u0e2d\\u0e1a\\u0e2a\\u0e25\\u0e34\\u0e1b\\u0e40\\u0e07\\u0e34\\u0e19\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33</strong><br>\\u0e3f'+parseFloat(ptR).toFixed(2);}else{b.innerHTML='\\u26a0\\ufe0f <strong>\\u0e04\\u0e38\\u0e13\\u0e15\\u0e49\\u0e2d\\u0e07\\u0e0a\\u0e33\\u0e23\\u0e30\\u0e40\\u0e07\\u0e34\\u0e19\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33</strong><br>\\u0e3f'+parseFloat(ptR).toFixed(2)+'<br><br>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='\\u0e41\\u0e19\\u0e1a\\u0e2a\\u0e25\\u0e34\\u0e1b\\u0e0a\\u0e33\\u0e23\\u0e30\\u0e40\\u0e07\\u0e34\\u0e19';btn.style.cssText='padding:8px 16px';btn.onclick=function(){";

$new3 = <<<'NEW3'
if(!myD){
        const b=document.createElement('div');
        b.style.cssText='padding:15px;background:#fef2f2;color:#991b1b;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fca5a5;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
        if(myS==='pending_verification'){
            b.innerHTML='⏳ <strong>รอแอดมินตรวจสอบสลิปเงินมัดจำ</strong><br><small>จำนวน: ฿'+parseFloat(ptR).toFixed(2)+'</small>';
        } else {
            b.innerHTML='💳 <strong>วางมัดจำ ฿'+parseFloat(ptR).toFixed(2)+'</strong><br><small>เพื่อคุ้มครองอีกฝ่าย หากคุณโกงจะถูกหักเงินนี้</small><br>';
            const btn=document.createElement('button');btn.className='btn primary';btn.textContent='💸 โอนเงินวางมัดจำ';btn.style.cssText='padding:8px 20px;margin-top:10px;background:#dc2626;border-color:#dc2626;font-size:13px;';btn.onclick=function(){
NEW3;

$c3 = str_replace($old3, $new3, $c);
if ($c3 === $c) {
    echo "Patch 3 (deposit button) FAILED - trying to find manually\n";
    $pos = strpos($c, 'const myD=');
    echo "myD pos: $pos\n";
} else {
    $c = $c3;
    echo "Deposit payment banner improved OK\n";
}

// Improve !ptD banner (waiting for partner to pay)
$old4 = "if(!ptD){const b=document.createElement('div');b.style.cssText='padding:14px;background:#ebf4ff;color:#2b6cb0;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bee3f8;margin-bottom:10px';b.innerHTML='<i class=\"fas fa-clock\"></i> <strong>\\u0e23\\u0e2d\\u0e2d\\u0e35\\u0e01\\u0e1d\\u0e48\\u0e32\\u0e22\\u0e0a\\u0e33\\u0e23\\u0e30\\u0e40\\u0e07\\u0e34\\u0e19\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33</strong><br>\\u0e3f'+parseFloat(myR).toFixed(2);cb.append(b);}";
$new4 = "if(!ptD){const b=document.createElement('div');b.style.cssText='padding:14px;background:#eff6ff;color:#1e40af;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bfdbfe;margin-bottom:10px';b.innerHTML='⏳ <strong>รออีกฝ่ายวางมัดจำ</strong><br><small>อีกฝ่ายต้องวาง ฿'+parseFloat(myR).toFixed(2)+'</small>';cb.append(b);}";
$c4 = str_replace($old4, $new4, $c);
if ($c4 !== $c) {
    $c = $c4;
    echo "Waiting banner improved OK\n";
} else {
    echo "Waiting banner skip (not found)\n";
}

// Verify
if (substr_count($c, 'doctype') < 1) { echo "ERROR: doctype missing!\n"; exit(1); }

file_put_contents($file, $c);
echo "\nDone! Diff: +" . (strlen($c)-$orig) . " bytes\n";
$r = shell_exec('C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe -l "c:\laragon\www\wanawan_project\pages\chat.php" 2>&1');
echo "PHP: " . trim($r) . "\n";
