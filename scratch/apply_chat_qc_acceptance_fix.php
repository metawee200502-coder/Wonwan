<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$oldLogic = <<<'JS'
   else if(myTr&&!ptTr){const b=document.createElement('div');b.style.cssText='padding:14px;background:#eff6ff;color:#1e40af;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bfdbfe';b.innerHTML='\ud83d\udce6 <strong>\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e1e\u0e31\u0e2a\u0e14\u0e38\u0e40\u0e23\u0e35\u0e22\u0e1a\u0e23\u0e49\u0e2d\u0e22\u0e41\u0e25\u0e49\u0e27</strong><br>\u0e23\u0e2d\u0e43\u0e2b\u0e49\u0e41\u0e2d\u0e14\u0e21\u0e34\u0e19\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e41\u0e25\u0e30\u0e08\u0e30\u0e21\u0e35\u0e01\u0e32\u0e23\u0e2a\u0e48\u0e07\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e21\u0e32\u0e43\u0e2b\u0e49';cb.append(b);}
   else if(myTr&&ptTr){const b=document.createElement('div');b.style.cssText='padding:14px;background:#f3e8ff;color:#6b21a8;border-radius:8px;font-size:14px;text-align:center;border:1px solid #d8b4fe';b.innerHTML='\ud83d\udd0d <strong>\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e1e\u0e31\u0e2a\u0e14\u0e38\u0e40\u0e23\u0e35\u0e22\u0e1a\u0e23\u0e49\u0e2d\u0e22\u0e41\u0e25\u0e49\u0e27 (\u0e17\u0e31\u0e49\u0e07\u0e2a\u0e2d\u0e07\u0e1d\u0e48\u0e32\u0e22)</strong><br>\u0e23\u0e2d\u0e43\u0e2b\u0e49\u0e41\u0e2d\u0e14\u0e21\u0e34\u0e19\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e41\u0e25\u0e30\u0e08\u0e30\u0e21\u0e35\u0e01\u0e32\u0e23\u0e2a\u0e48\u0e07\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e21\u0e32\u0e43\u0e2b\u0e49';cb.append(b);}
JS;

$newLogic = <<<'JS'
   else if(myTr&&!ptTr){const b=document.createElement('div');b.style.cssText='padding:14px;background:#eff6ff;color:#1e40af;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bfdbfe';b.innerHTML='\ud83d\udce6 <strong>\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e1e\u0e31\u0e2a\u0e14\u0e38\u0e40\u0e23\u0e35\u0e22\u0e1a\u0e23\u0e49\u0e2d\u0e22\u0e41\u0e25\u0e49\u0e27</strong><br>\u0e23\u0e2d\u0e43\u0e2b\u0e49\u0e41\u0e2d\u0e14\u0e21\u0e34\u0e19\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e41\u0e25\u0e30\u0e08\u0e30\u0e21\u0e35\u0e01\u0e32\u0e23\u0e2a\u0e48\u0e07\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e21\u0e32\u0e43\u0e2b\u0e49';cb.append(b);}
   else if(myTr&&ptTr){
    const reqQc=data.requesterQcStatus;
    const offQc=data.offerQcStatus;
    if(reqQc!=='pending'&&offQc!=='pending'){
     const myQcAcc=data.isRequester?data.requesterQcAcceptanceStatus:data.offerQcAcceptanceStatus;
     const ptQcAcc=data.isRequester?data.offerQcAcceptanceStatus:data.requesterQcAcceptanceStatus;
     
     if (myQcAcc==='pending') {
         const b=document.createElement('div');b.style.cssText='padding:15px;background:#f0fdf4;color:#166534;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bbf7d0;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
         b.innerHTML='📋 <strong>แอดมินส่งฟอร์มตรวจสภาพสินค้าแล้ว</strong><br>กรุณาตรวจสอบและยืนยันผลการประเมินจากส่วนกลาง<br><br>';
         
         const btnView=document.createElement('button');btnView.className='btn primary';btnView.textContent='ดูฟอร์ม QC';btnView.style.cssText='padding:8px 16px;background:#059669;border-color:#059669;margin-right:10px;';
         btnView.onclick=function(){
             const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
             if(modal&&iframe){
                 modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> ฟอร์มรายงาน QC จากส่วนกลาง';
                 iframe.src='/pages/trade_qc_report_view.php?request_id='+data.requestId+'&iframe=1';
                 modal.style.display='flex';
             }
         };
         
         const btnReject=document.createElement('button');btnReject.className='btn outline danger';btnReject.textContent='ปฏิเสธ';btnReject.style.cssText='padding:8px 16px;margin-right:10px;';
         btnReject.onclick=function(){
             const r = prompt('กรุณาระบุเหตุผลที่ปฏิเสธผล QC (การปฏิเสธจะยกเลิกการแลกเปลี่ยนนี้ทันที):');
             if(r!==null && r.trim()!==''){
                 const f = document.createElement('form'); f.method = 'post'; f.action = '/pages/handlers/trade_qc_acceptance.php';
                 f.innerHTML = '<input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="'+data.requestId+'"><input type="hidden" name="reason" value="'+r+'">';
                 
                 // Since we use fetch instead of full page reload to keep it modern
                 fetch('/pages/handlers/trade_qc_acceptance.php', {
                     method: 'POST', body: new FormData(f)
                 }).then(res=>res.json()).then(res=>{
                     if(res.success) window.location.reload();
                     else alert(res.message||'Error');
                 });
             }
         };
         
         const btnAccept=document.createElement('button');btnAccept.className='btn primary';btnAccept.textContent='ตกลง';btnAccept.style.cssText='padding:8px 16px;background:#2563eb;border-color:#2563eb;';
         btnAccept.onclick=function(){
             if(confirm('คุณยืนยันที่จะยอมรับผลการตรวจสอบจาก QC ใช่หรือไม่?')){
                 const f = new FormData(); f.append('action', 'accept'); f.append('request_id', data.requestId);
                 fetch('/pages/handlers/trade_qc_acceptance.php', {
                     method: 'POST', body: f
                 }).then(res=>res.json()).then(res=>{
                     if(res.success) window.location.reload();
                     else alert(res.message||'Error');
                 });
             }
         };
         
         b.append(btnView, btnReject, btnAccept); cb.append(b);
     } else if (myQcAcc==='accepted' && ptQcAcc==='pending') {
         const b=document.createElement('div');b.style.cssText='padding:14px;background:#fef3c7;color:#92400e;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fde68a';
         b.innerHTML='⏳ <strong>รออีกฝ่ายยืนยันผล QC</strong><br>คุณกดยอมรับแล้ว กรุณารอให้อีกฝ่ายตรวจสอบและยืนยัน';
         
         const btnView=document.createElement('button');btnView.className='btn outline primary';btnView.textContent='ดูฟอร์ม QC';btnView.style.cssText='padding:4px 12px;margin-top:10px;font-size:12px;';
         btnView.onclick=function(){
             const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
             if(modal&&iframe){
                 modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> ฟอร์มรายงาน QC จากส่วนกลาง';
                 iframe.src='/pages/trade_qc_report_view.php?request_id='+data.requestId+'&iframe=1';
                 modal.style.display='flex';
             }
         };
         b.append(btnView);
         cb.append(b);
     } else if (myQcAcc==='accepted' && ptQcAcc==='accepted') {
         const b=document.createElement('div');b.style.cssText='padding:14px;background:#f0fff4;color:#166534;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bbf7d0';
         b.innerHTML='📦 <strong>รอกลางจัดส่งสินค้า</strong><br>ทั้งสองฝ่ายยอมรับผลประเมินแล้ว แอดมินกำลังจัดส่งสินค้าให้คุณ';
         cb.append(b);
     }
    } else {
     const b=document.createElement('div');b.style.cssText='padding:14px;background:#f3e8ff;color:#6b21a8;border-radius:8px;font-size:14px;text-align:center;border:1px solid #d8b4fe';b.innerHTML='\ud83d\udd0d <strong>\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e1e\u0e31\u0e2a\u0e14\u0e38\u0e40\u0e23\u0e35\u0e22\u0e1a\u0e23\u0e49\u0e2d\u0e22\u0e41\u0e25\u0e49\u0e27 (\u0e17\u0e31\u0e49\u0e07\u0e2a\u0e2d\u0e07\u0e1d\u0e48\u0e32\u0e22)</strong><br>\u0e23\u0e2d\u0e43\u0e2b\u0e49\u0e41\u0e2d\u0e14\u0e21\u0e34\u0e19\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e41\u0e25\u0e30\u0e08\u0e30\u0e21\u0e35\u0e01\u0e32\u0e23\u0e2a\u0e48\u0e07\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e21\u0e32\u0e43\u0e2b\u0e49';cb.append(b);
    }
   }
JS;

$content = str_replace($oldLogic, $newLogic, $content);
file_put_contents($file, $content);
echo "Successfully patched chat.php for QC Acceptance.\n";
