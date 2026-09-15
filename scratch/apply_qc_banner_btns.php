<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

// 1. Pending Verification State
$oldPending = <<<'JS'
   else if(mySlip==='pending'||mySlip==='pending_verification'){const b=document.createElement('div');b.style.cssText='padding:14px;background:#fffbeb;color:#92400e;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fde68a;margin-bottom:10px';b.innerHTML='\u23f3 <strong>\u0e23\u0e2d\u0e41\u0e2d\u0e14\u0e21\u0e34\u0e19\u0e22\u0e37\u0e19\u0e22\u0e31\u0e19\u0e2a\u0e25\u0e34\u0e1b</strong><br>\u0e17\u0e35\u0e21\u0e07\u0e32\u0e19\u0e01\u0e33\u0e25\u0e31\u0e07\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e01\u0e32\u0e23\u0e0a\u0e33\u0e23\u0e30\u0e40\u0e07\u0e34\u0e19\u0e02\u0e2d\u0e07\u0e04\u0e38\u0e13';cb.append(b);}
JS;

$newPending = <<<'JS'
   else if(mySlip==='pending'||mySlip==='pending_verification'){const b=document.createElement('div');b.style.cssText='padding:14px;background:#fffbeb;color:#92400e;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fde68a;margin-bottom:10px';b.innerHTML='\u23f3 <strong>\u0e23\u0e2d\u0e41\u0e2d\u0e14\u0e21\u0e34\u0e19\u0e22\u0e37\u0e19\u0e22\u0e31\u0e19\u0e2a\u0e25\u0e34\u0e1b</strong><br>\u0e17\u0e35\u0e21\u0e07\u0e32\u0e19\u0e01\u0e33\u0e25\u0e31\u0e07\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e01\u0e32\u0e23\u0e0a\u0e33\u0e23\u0e30\u0e40\u0e07\u0e34\u0e19\u0e02\u0e2d\u0e07\u0e04\u0e38\u0e13<br><br>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='รอแอดมินตรวจสอบสลิป';btn.style.cssText='padding:8px 16px;';btn.onclick=function(){
    const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
    if(modal&&iframe){
        modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg> สถานะการชำระเงิน (QC)';
        iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
        modal.style.display='flex';
    } else {
        window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
    }
};b.append(btn);cb.append(b);}
JS;

$content = str_replace($oldPending, $newPending, $content);


// 2. Approved State
$oldApproved = <<<'JS'
   else if(mySlip==='approved'&&!myTr){const b=document.createElement('div');b.style.cssText='padding:15px;background:#f0fff4;color:#276749;border-radius:8px;font-size:14px;text-align:center;border:1px solid #c6f6d5;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';b.innerHTML='\u2705 <strong>\u0e2a\u0e25\u0e34\u0e1b\u0e44\u0e14\u0e49\u0e23\u0e31\u0e1a\u0e01\u0e32\u0e23\u0e2d\u0e19\u0e38\u0e21\u0e31\u0e15\u0e34!</strong><br>\u0e01\u0e23\u0e38\u0e13\u0e32\u0e01\u0e23\u0e2d\u0e01\u0e17\u0e35\u0e48\u0e2d\u0e22\u0e39\u0e48\u0e41\u0e25\u0e30\u0e40\u0e25\u0e02\u0e1e\u0e31\u0e2a\u0e14\u0e38\u0e17\u0e35\u0e48\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e43\u0e2b\u0e49 QC<br><br>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='\ud83d\udce6 \u0e01\u0e23\u0e2d\u0e01\u0e17\u0e35\u0e48\u0e2d\u0e22\u0e39\u0e48\u0e41\u0e25\u0e30\u0e40\u0e25\u0e02\u0e1e\u0e31\u0e2a\u0e14\u0e38';btn.style.cssText='padding:8px 16px';btn.onclick=function(){
    const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
    if(modal&&iframe){
        modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg> ชำระค่าบริการส่วนกลาง (QC)';
        iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
        modal.style.display='flex';
    } else {
        window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
    }
};b.append(btn);cb.append(b);}
JS;

$newApproved = <<<'JS'
   else if(mySlip==='approved'&&!myTr){const b=document.createElement('div');b.style.cssText='padding:15px;background:#f0fff4;color:#276749;border-radius:8px;font-size:14px;text-align:center;border:1px solid #c6f6d5;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';b.innerHTML='\u2705 <strong>\u0e2a\u0e25\u0e34\u0e1b\u0e44\u0e14\u0e49\u0e23\u0e31\u0e1a\u0e01\u0e32\u0e23\u0e2d\u0e19\u0e38\u0e21\u0e31\u0e15\u0e34!</strong><br>\u0e01\u0e23\u0e38\u0e13\u0e32\u0e01\u0e23\u0e2d\u0e01\u0e17\u0e35\u0e48\u0e2d\u0e22\u0e39\u0e48\u0e41\u0e25\u0e30\u0e40\u0e25\u0e02\u0e1e\u0e31\u0e2a\u0e14\u0e38\u0e17\u0e35\u0e48\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e43\u0e2b\u0e49 QC<br><br>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='ที่อยู่ในการจัดส่งและกรอกเลขพัสดุ';btn.style.cssText='padding:8px 16px';btn.onclick=function(){
    const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
    if(modal&&iframe){
        modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg> แจ้งเลขพัสดุส่วนกลาง (QC)';
        iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
        modal.style.display='flex';
    } else {
        window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
    }
};b.append(btn);cb.append(b);}
JS;

$content = str_replace($oldApproved, $newApproved, $content);

file_put_contents($file, $content);
echo "Updated chat.php QC buttons successfully.\n";
