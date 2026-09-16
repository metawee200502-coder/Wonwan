<?php
$chatPath = __DIR__ . '/../pages/chat.php';
$chatContent = file_get_contents($chatPath);

// We need to replace the entire if(myD&&ptD) block
// The target starts with: if(myD&&ptD){const dObj=new Date
// Ends with: cb.append(sb);}

$pattern = '/if\(myD&&ptD\)\{const dObj=new Date.*?cb\.append\(sb\);\}/s';

$replacement = <<<'EOD'
if(myD&&ptD){
    const dObj=new Date((data.myCondition&&data.myCondition.deposit_approved_at)||(data.partnerCondition&&data.partnerCondition.deposit_approved_at)||new Date());
    dObj.setDate(dObj.getDate()+parseInt(data.shippingDeadline));
    const dls=dObj.toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'});
    const myTr=(data.myCondition)?data.myCondition.tracking_number:null;
    const ptTr=(data.partnerCondition)?data.partnerCondition.tracking_number:null;
    const ptRv=(data.partnerCondition)?data.partnerCondition.is_received:0;
    const myRv=(data.myCondition)?data.myCondition.is_received:0;
    const sb=document.createElement('div');
    
    if(!myTr){
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bbf7d0;margin-bottom:12px;gap:10px;';
        sb.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">การวางมัดจำเสร็จสิ้น!</strong><span style="font-size:12px;opacity:0.8;">จัดส่งสินค้าภายใน '+dls+'</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='แจ้งเลขพัสดุ';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#22c55e; border-color:#22c55e; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            const modal=document.getElementById('conditionModal');
            const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg> แจ้งเลขพัสดุ';
                iframe.src='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId;
            }
        };
        sb.append(btn);
    } else if(!ptTr){
        sb.style.cssText='padding:12px 16px;background:#fefce8;color:#854d0e;border-radius:8px;display:flex;align-items:center;border:1px solid #fef08a;margin-bottom:12px;gap:12px;';
        sb.innerHTML='<div style="color:#eab308;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">จัดส่งสินค้าแล้ว</strong><span style="font-size:12px;opacity:0.8;">รออีกฝ่ายจัดส่ง</span></div>';
    } else if(!ptRv){
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bbf7d0;margin-bottom:12px;gap:10px;';
        sb.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">อีกฝ่ายจัดส่งแล้ว!</strong><span style="font-size:12px;opacity:0.8;">เลขพัสดุ: '+ptTr+'</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='ยืนยันได้รับสินค้า';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#22c55e; border-color:#22c55e; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            if(confirm('ยืนยันว่าได้รับสินค้าในสภาพที่ตกลงไว้?')){
                const f=document.createElement('form');
                f.method='post';
                f.action='/pages/handlers/trade_submit_logistics.php';
                f.innerHTML='<input type="hidden" name="action" value="receive_item"><input type="hidden" name="request_id" value="'+data.requestId+'">';
                document.body.append(f);
                f.submit();
            }
        };
        sb.append(btn);
    } else if(!myRv){
        sb.style.cssText='padding:12px 16px;background:#fefce8;color:#854d0e;border-radius:8px;display:flex;align-items:center;border:1px solid #fef08a;margin-bottom:12px;gap:12px;';
        sb.innerHTML='<div style="color:#eab308;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">คุณได้รับสินค้าแล้ว</strong><span style="font-size:12px;opacity:0.8;">รออีกฝ่ายกดยืนยันรับสินค้า</span></div>';
    } else {
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;border:1px solid #bbf7d0;margin-bottom:12px;gap:12px;';
        sb.innerHTML='<div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">การแลกเปลี่ยนสำเร็จ!</strong></div>';
    }
    cb.append(sb);
}
EOD;

$chatContent = preg_replace($pattern, $replacement, $chatContent, 1, $count);
if ($count > 0) {
    echo "Patched !myTr successfully\n";
} else {
    echo "FAILED to patch !myTr\n";
}

file_put_contents($chatPath, $chatContent);
echo "Done.";
