<?php
$chatFile = 'c:\laragon\www\wanawan_project\pages\chat.php';
$chatContent = file_get_contents($chatFile);

// Use regex to replace the entire block
$pattern = '/(if\(myD&&ptD\)\{const dObj=new Date.*?)(if\(\!myTr\)\{.*?\}\s*else\s*\{\s*sb\.innerHTML=\'\\\\ud83c\\\\udf89.*?\}\s*cb\.append\(sb\);\})/';

$replace_block = <<<'EOD'
$1
    const myComp=(data.myCondition)?data.myCondition.logistics_company:null;
    const ptComp=(data.partnerCondition)?data.partnerCondition.logistics_company:null;
    
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
        myBox.innerHTML = '<span style="color:#059669;">✅ <strong>คุณจัดส่งแล้ว</strong></span><br>ขนส่ง: <strong>' + (myComp||'-') + '</strong><br>เลขพัสดุ: <strong style="color:#1e293b;">' + myTr + '</strong>';
        if (ptRv) myBox.innerHTML += '<br><span style="color:#2563eb;font-weight:600;display:inline-block;margin-top:8px;">🎉 อีกฝ่ายได้รับของแล้ว</span>';
    }
    grid.append(myBox);
    
    const ptBox = document.createElement('div');
    ptBox.style.cssText = 'padding:15px;border-radius:8px;font-size:13px;text-align:center;border:1px solid #e2e8f0;background:#f8fafc;box-shadow:0 1px 2px rgba(0,0,0,0.02);';
    if (!ptTr) {
        ptBox.innerHTML = '<strong>⏳ รออีกฝ่ายจัดส่งสินค้า</strong><br><span style="color:#64748b;font-size:12px;">กำหนดภายใน ' + dls + '</span>';
    } else {
        ptBox.innerHTML = '<span style="color:#059669;">📦 <strong>อีกฝ่ายจัดส่งแล้ว!</strong></span><br>ขนส่ง: <strong>' + (ptComp||'-') + '</strong><br>เลขพัสดุ: <strong style="color:#1e293b;">' + ptTr + '</strong>';
        if (!myRv) {
            const btnContainer = document.createElement('div');
            btnContainer.style.cssText = 'display:flex;gap:5px;justify-content:center;margin-top:10px;flex-wrap:wrap;';
            
            const btn = document.createElement('button');btn.className = 'btn';btn.style.cssText = 'background:#10b981;color:#fff;padding:6px 10px;font-size:12px;border:0;cursor:pointer;flex:1;border-radius:4px;';btn.textContent = '✅ ได้รับสินค้า';
            btn.onclick = function() {
                if (confirm('ยืนยันว่าได้รับสินค้าเรียบร้อยแล้ว?')) {
                    const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';
                    f.innerHTML='<input type="hidden" name="action" value="receive_item"><input type="hidden" name="request_id" value="'+data.requestId+'">';
                    document.body.append(f);f.submit();
                }
            };
            
            const btnClaim = document.createElement('button');btnClaim.className = 'btn';btnClaim.style.cssText = 'background:#f43f5e;color:#fff;padding:6px 10px;font-size:12px;border:0;cursor:pointer;flex:1;border-radius:4px;';btnClaim.textContent = '❌ ส่งเคลม/คืน';
            btnClaim.onclick = function() {
                alert('กรุณาติดต่อแอดมิน เพื่อแจ้งปัญหาการเคลมหรือคืนสินค้า');
            };
            
            btnContainer.append(btn, btnClaim);
            ptBox.append(btnContainer);
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
EOD;

$chatContent = preg_replace($pattern, $replace_block, $chatContent);

file_put_contents($chatFile, $chatContent);
echo "Successfully updated P2P logistics banner using regex.\n";
