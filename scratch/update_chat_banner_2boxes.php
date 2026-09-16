<?php
$chatFile = 'c:\laragon\www\wanawan_project\pages\chat.php';
$chatContent = file_get_contents($chatFile);

$search_block = "if(myD&&ptD){const dObj=new Date((data.myCondition&&data.myCondition.deposit_approved_at)||(data.partnerCondition&&data.partnerCondition.deposit_approved_at)||new Date());dObj.setDate(dObj.getDate()+parseInt(data.shippingDeadline));const dls=dObj.toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'});const myTr=(data.myCondition)?data.myCondition.tracking_number:null;const ptTr=(data.partnerCondition)?data.partnerCondition.tracking_number:null;const ptRv=(data.partnerCondition)?data.partnerCondition.is_received:0;const myRv=(data.myCondition)?data.myCondition.is_received:0;const sb=document.createElement('div');sb.style.cssText='padding:15px;background:#f0fff4;color:#276749;border-radius:8px;font-size:14px;text-align:center;border:1px solid #c6f6d5;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';if(!myTr){
    sb.innerHTML='\u2705 <strong>\u0e01\u0e32\u0e23\u0e27\u0e32\u0e07\u0e21\u0e31\u0e14\u0e08\u0e33\u0e40\u0e2a\u0e23\u0e47\u0e08\u0e2a\u0e34\u0e49\u0e19!</strong><br>\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e20\u0e32\u0e22\u0e43\u0e19 '+dls+'<br><br>';
    const btn=document.createElement('button');btn.className='btn primary';btn.textContent='\ud83d\udce6 แจ้งเลขพัสดุ';btn.style.cssText='padding:8px 16px';
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
}else if(!ptTr){sb.innerHTML='\ud83d\udce6 <strong>\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e41\u0e25\u0e49\u0e27</strong><br>\u0e23\u0e2d\u0e2d\u0e35\u0e01\u0e1d\u0e48\u0e32\u0e22\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07';}else if(!ptRv){sb.innerHTML='\ud83d\udce6 <strong>\u0e2d\u0e35\u0e01\u0e1d\u0e48\u0e32\u0e22\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e41\u0e25\u0e49\u0e27!</strong><br>\u0e40\u0e25\u0e02\u0e1e\u0e31\u0e2a\u0e14\u0e38: '+ptTr+'<br><br>';const btn=document.createElement('button');btn.className='btn';btn.style.cssText='background:#48bb78;color:#fff;padding:8px 16px';btn.textContent='\u2705 \u0e22\u0e37\u0e19\u0e22\u0e31\u0e19\u0e44\u0e14\u0e49\u0e23\u0e31\u0e1a\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e41\u0e25\u0e49\u0e27';btn.onclick=function(){if(confirm('\u0e22\u0e37\u0e19\u0e22\u0e31\u0e19\u0e27\u0e48\u0e32\u0e44\u0e14\u0e49\u0e23\u0e31\u0e1a\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e43\u0e19\u0e2a\u0e20\u0e32\u0e1e\u0e17\u0e35\u0e48\u0e15\u0e01\u0e25\u0e07\u0e44\u0e27\u0e49?')){const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';f.innerHTML='<input type=\"hidden\" name=\"action\" value=\"receive_item\"><input type=\"hidden\" name=\"request_id\" value=\"'+data.requestId+'\">';document.body.append(f);f.submit();}};sb.append(btn);}else if(!myRv){sb.innerHTML='\u2705 <strong>\u0e04\u0e38\u0e13\u0e44\u0e14\u0e49\u0e23\u0e31\u0e1a\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e41\u0e25\u0e49\u0e27</strong><br>\u0e23\u0e2d\u0e2d\u0e35\u0e01\u0e1d\u0e48\u0e32\u0e22\u0e01\u0e14\u0e22\u0e37\u0e19\u0e22\u0e31\u0e19\u0e23\u0e31\u0e1a\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32';}else{sb.innerHTML='\ud83c\udf89 <strong>\u0e41\u0e25\u0e01\u0e40\u0e1b\u0e25\u0e35\u0e48\u0e22\u0e19\u0e2a\u0e33\u0e40\u0e23\u0e47\u0e08!</strong>';}cb.append(sb);}";

$replace_block = <<<'EOD'
if(myD&&ptD){const dObj=new Date((data.myCondition&&data.myCondition.deposit_approved_at)||(data.partnerCondition&&data.partnerCondition.deposit_approved_at)||new Date());dObj.setDate(dObj.getDate()+parseInt(data.shippingDeadline));const dls=dObj.toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'});const myTr=(data.myCondition)?data.myCondition.tracking_number:null;const ptTr=(data.partnerCondition)?data.partnerCondition.tracking_number:null;const ptRv=(data.partnerCondition)?data.partnerCondition.is_received:0;const myRv=(data.myCondition)?data.myCondition.is_received:0;
    
    const grid = document.createElement('div');
    grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:10px;margin-bottom:10px;';
    
    const myBox = document.createElement('div');
    myBox.style.cssText = 'padding:15px;border-radius:8px;font-size:13px;text-align:center;border:1px solid #e2e8f0;background:#f8fafc;box-shadow:0 1px 2px rgba(0,0,0,0.02);';
    if (!myTr) {
        myBox.innerHTML = '<strong>📦 คุณยังไม่ได้จัดส่ง</strong><br><span style="color:#64748b;font-size:12px;">กรุณาจัดส่งภายใน ' + dls + '</span><br><br>';
        const btn=document.createElement('button');btn.className='btn primary';btn.textContent='แจ้งเลขพัสดุ';btn.style.cssText='padding:6px 12px;font-size:12px;';
        btn.onclick=function(){
            const modal=document.getElementById('logisticsModal');const iframe=document.getElementById('logisticsIframe');
            if(modal&&iframe){
                iframe.src='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId+'&conversation_id='+data.conversationId+'&iframe=1';
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId+'&conversation_id='+data.conversationId;
            }
        };
        myBox.append(btn);
    } else {
        myBox.innerHTML = '<span style="color:#059669;">✅ <strong>คุณจัดส่งแล้ว</strong></span><br>เลขพัสดุ: <strong style="color:#1e293b;">' + myTr + '</strong>';
        if (ptRv) myBox.innerHTML += '<br><span style="color:#2563eb;font-weight:600;display:inline-block;margin-top:8px;">🎉 อีกฝ่ายได้รับของแล้ว</span>';
    }
    grid.append(myBox);
    
    const ptBox = document.createElement('div');
    ptBox.style.cssText = 'padding:15px;border-radius:8px;font-size:13px;text-align:center;border:1px solid #e2e8f0;background:#f8fafc;box-shadow:0 1px 2px rgba(0,0,0,0.02);';
    if (!ptTr) {
        ptBox.innerHTML = '<strong>⏳ รออีกฝ่ายจัดส่งสินค้า</strong><br><span style="color:#64748b;font-size:12px;">กำหนดภายใน ' + dls + '</span>';
    } else {
        ptBox.innerHTML = '<span style="color:#059669;">📦 <strong>อีกฝ่ายจัดส่งแล้ว!</strong></span><br>เลขพัสดุ: <strong style="color:#1e293b;">' + ptTr + '</strong>';
        if (!myRv) {
            const btn = document.createElement('button');btn.className = 'btn';btn.style.cssText = 'background:#10b981;color:#fff;padding:6px 12px;margin-top:10px;font-size:12px;';btn.textContent = '✅ ยืนยันได้รับสินค้า';
            btn.onclick = function() {
                if (confirm('ยืนยันว่าได้รับสินค้าเรียบร้อยแล้ว?')) {
                    const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';
                    f.innerHTML='<input type="hidden" name="action" value="receive_item"><input type="hidden" name="request_id" value="'+data.requestId+'">';
                    document.body.append(f);f.submit();
                }
            };
            ptBox.append(document.createElement('br'), btn);
        } else {
            ptBox.innerHTML += '<br><span style="color:#2563eb;font-weight:600;display:inline-block;margin-top:8px;">🎉 คุณได้รับของแล้ว</span>';
        }
    }
    grid.append(ptBox);
    cb.append(grid);
    
    if (myRv && ptRv) {
        const succ = document.createElement('div');
        succ.style.cssText = 'padding:10px;background:#f0fff4;color:#166534;border-radius:8px;font-size:13px;text-align:center;border:1px solid #bbf7d0;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
        succ.innerHTML = '🎉 <strong>การแลกเปลี่ยนเสร็จสมบูรณ์!</strong>';
        cb.append(succ);
    }
}
EOD;

$chatContent = str_replace($search_block, $replace_block, $chatContent);
file_put_contents($chatFile, $chatContent);
echo "Successfully updated P2P logistics banner in chat.php\n";
