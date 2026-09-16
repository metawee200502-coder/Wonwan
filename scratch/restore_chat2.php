<?php
$content = file_get_contents('C:/laragon/www/wanawan_project/chat_backup.php');

// 1. Add SweetAlert2
$content = str_replace('</head>', '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head>', $content);

// 2. Replace the admin_qc block
$start_str = "} else if(data.tradeOption==='admin_qc'){";
$end_str = "} else if(data.tradeOption==='direct_escrow'){";

$start_pos = strpos($content, $start_str);
if ($start_pos === false) die("Start string not found\n");
$end_pos = strpos($content, $end_str, $start_pos);
if ($end_pos === false) die("End string not found\n");

$new_block = "} else if(data.tradeOption==='admin_qc'){
    const cb=document.createElement('div');
    const mySlip=data.isRequester?data.requesterSlipStatus:data.offerSlipStatus;
    const myTr=data.isRequester?data.requesterTrackingToAdmin:data.offerTrackingToAdmin;
    const ptTr=data.isRequester?data.offerTrackingToAdmin:data.requesterTrackingToAdmin;
    const ptQcStatus=data.isRequester?data.offerQcStatus:data.requesterQcStatus;
    const myQcAcc=data.isRequester?data.requesterQcAcceptanceStatus:data.offerQcAcceptanceStatus;
    const ptQcAcc=data.isRequester?data.offerQcAcceptanceStatus:data.requesterQcAcceptanceStatus;
    const myAdminTr=data.isRequester?data.adminTrackingToRequester:data.adminTrackingToOffer;
    const myRv=(data.partnerCondition)?parseInt(data.partnerCondition.is_received):0;

    if(!data.conditionsAccepted){
        if (data.myCondition && !data.partnerCondition) {
            const b = document.createElement('div');
            b.style.cssText = 'padding:12px 16px;background:#fffbeb;color:#92400e;border-radius:8px;display:flex;align-items:center;border:1px solid #fde68a;margin-bottom:12px;gap:12px;';
            b.innerHTML = '<div style=\"color:#d97706;display:flex;\"><svg width=\"20\" height=\"20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><path d=\"M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z\"/></svg></div><div style=\"text-align:left;\"><strong style=\"display:block;font-size:14px;\">รออีกฝ่ายเพิ่มฟอร์มภาพสินค้า</strong><span style=\"font-size:12px;opacity:0.8;\">คุณกรอกฟอร์มแล้ว กรุณารอให้อีกฝ่ายกรอกข้อมูล</span></div>';
            cb.append(b);
        } else if (!data.myCondition && data.partnerCondition) {
            const b = document.createElement('div');
            b.style.cssText = 'padding:12px 16px;background:#fffbeb;color:#92400e;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #fde68a;margin-bottom:12px;gap:10px;';
            b.innerHTML = '<div style=\"display:flex;align-items:center;gap:12px;\"><div style=\"color:#d97706;display:flex;\"><svg width=\"20\" height=\"20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><path d=\"M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01\"/></svg></div><div style=\"text-align:left;\"><strong style=\"display:block;font-size:14px;\">อีกฝ่ายเพิ่มรูปภาพสินค้าแล้ว</strong><span style=\"font-size:12px;opacity:0.8;\">ดูรายละเอียดเพื่อตรวจสอบและกรอกข้อมูลของคุณ</span></div></div>';
            const btn=document.createElement('button');btn.className='btn primary';btn.textContent='ดูแบบฟอร์ม';btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#d97706; border-color:#d97706; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
            btn.onclick=function(){const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');const cid=(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');if(modal&&iframe){iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1&conversation_id='+cid;modal.style.display='flex';}else{window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId+'&conversation_id='+cid;}};b.append(btn);cb.append(b);
        } else if (data.myCondition && data.partnerCondition) {
            const b = document.createElement('div');
            b.style.cssText = 'padding:12px 16px;background:#fefce8;color:#854d0e;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #fef08a;margin-bottom:12px;gap:10px;';
            b.innerHTML = '<div style=\"display:flex;align-items:center;gap:12px;\"><div style=\"color:#eab308;display:flex;\"><svg width=\"20\" height=\"20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><path d=\"M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z\"/></svg></div><div style=\"text-align:left;\"><strong style=\"display:block;font-size:14px;\">ตรวจสอบสภาพสินค้า</strong><span style=\"font-size:12px;opacity:0.8;\">ทั้งสองฝ่ายกรอกข้อมูลแล้ว กรุณาเข้าไปกดยอมรับ</span></div></div>';
            const btn=document.createElement('button');btn.className='btn primary';btn.textContent='ตรวจสอบ';btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#eab308; border-color:#eab308; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
            btn.onclick=function(){const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');const cid=(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');if(modal&&iframe){iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1&conversation_id='+cid;modal.style.display='flex';}else{window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId+'&conversation_id='+cid;}};b.append(btn);cb.append(b);
        } else {
            const banner = makeCondBtn(); 
            if(banner) cb.append(banner);
        }
    }
    else if(!mySlip){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#fff1f2;color:#be123c;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #ffe4e6;margin-bottom:12px;gap:10px;';
        b.innerHTML='<div style=\"display:flex;align-items:center;gap:12px;\"><div style=\"color:#e11d48;display:flex;\"><svg width=\"20\" height=\"20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><rect x=\"2\" y=\"5\" width=\"20\" height=\"14\" rx=\"2\"/><line x1=\"2\" y1=\"10\" x2=\"22\" y2=\"10\"/></svg></div><div style=\"text-align:left;\"><strong style=\"display:block;font-size:14px;\">ชำระค่าบริการส่วนกลาง (QC)</strong><span style=\"font-size:12px;opacity:0.8;\">กรุณาโอนเงินค่าบริการและแนบสลิป</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.textContent='แนบสลิป';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#e11d48; border-color:#e11d48; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M21 12V7H5a2 2 0 0 1 0-4h14v4\"></path><path d=\"M3 5v14a2 2 0 0 0 2 2h16v-5\"></path><path d=\"M18 12a2 2 0 0 0 0 4h4v-4Z\"></path></svg> ชำระค่าบริการส่วนกลาง (QC)';
                iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
            }
        };b.append(btn);cb.append(b);
    }
    else if(mySlip==='pending'||mySlip==='pending_verification'){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#fffbeb;color:#92400e;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #fde68a;margin-bottom:12px;gap:10px;';
        b.innerHTML='<div style=\"display:flex;align-items:center;gap:12px;\"><div style=\"color:#d97706;display:flex;\"><svg width=\"20\" height=\"20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><polyline points=\"12 6 12 12 16 14\"/></svg></div><div style=\"text-align:left;\"><strong style=\"display:block;font-size:14px;\">รอแอดมินตรวจสอบ</strong><span style=\"font-size:12px;opacity:0.8;\">ทีมงานกำลังตรวจสอบสลิปการชำระเงินของคุณ</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary outline';
        btn.textContent='รอแอดมินตรวจสอบ';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; border:1px solid #d97706; color:#d97706; background:transparent; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M21 12V7H5a2 2 0 0 1 0-4h14v4\"></path><path d=\"M3 5v14a2 2 0 0 0 2 2h16v-5\"></path><path d=\"M18 12a2 2 0 0 0 0 4h4v-4Z\"></path></svg> สถานะการชำระเงิน (QC)';
                iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
            }
        };b.append(btn);cb.append(b);
    }
    else if(!myTr){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#eff6ff;color:#1e40af;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bfdbfe;margin-bottom:12px;gap:10px;';
        b.innerHTML='<div style=\"display:flex;align-items:center;gap:12px;\"><div style=\"color:#2563eb;display:flex;\"><svg width=\"20\" height=\"20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><path d=\"M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\"></path></svg></div><div style=\"text-align:left;\"><strong style=\"display:block;font-size:14px;\">แจ้งเลขพัสดุส่วนกลาง (QC)</strong><span style=\"font-size:12px;opacity:0.8;\">กรุณาจัดส่งสินค้าไปยังบริษัท</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.textContent='เพิ่มที่อยู่และส่งสินค้าไปยังบริษัท';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#2563eb; border-color:#2563eb; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\"></path></svg> แจ้งเลขพัสดุส่วนกลาง (QC)';
                iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
            }
        };b.append(btn);cb.append(b);
    }
    else if(ptQcStatus === \'pending\'){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#f3f4f6;color:#374151;border-radius:8px;display:flex;align-items:center;border:1px solid #e5e7eb;margin-bottom:12px;gap:12px;';
        b.innerHTML='<div style=\"color:#4b5563;display:flex;\"><svg width=\"20\" height=\"20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><circle cx=\"12\" cy=\"12\" r=\"10\"></circle><polyline points=\"12 6 12 12 16 14\"></polyline></svg></div><div style=\"text-align:left;\"><strong style=\"display:block;font-size:14px;\">รอสินค้าถึงแอดมิน แล้วตรวจสอบสินค้าอีกครั้ง</strong><span style=\"font-size:12px;opacity:0.8;\">ส่งพัสดุเรียบร้อยแล้ว กรุณารอทีมงานตรวจสอบ</span></div>';
        cb.append(b);
    }
    else if(myQcAcc === \'pending\'){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#eff6ff;color:#1e40af;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bfdbfe;margin-bottom:12px;gap:10px;flex-wrap:wrap;';
        b.innerHTML='<div style=\"display:flex;align-items:center;gap:12px;\"><div style=\"color:#2563eb;display:flex;\"><svg width=\"20\" height=\"20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><path d=\"M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z\"></path><polyline points=\"14 2 14 8 20 8\"></polyline><line x1=\"16\" y1=\"13\" x2=\"8\" y2=\"13\"></line><line x1=\"16\" y1=\"17\" x2=\"8\" y2=\"17\"></line><polyline points=\"10 9 9 9 8 9\"></polyline></svg></div><div style=\"text-align:left;\"><strong style=\"display:block;font-size:14px;\">แอดมินส่งฟอร์มตรวจสภาพสินค้าแล้ว</strong><span style=\"font-size:12px;opacity:0.8;\">กรุณาตรวจสอบและยืนยันผลการประเมินจากส่วนกลาง</span></div></div>';
        
        const btnContainer = document.createElement('div');
        btnContainer.style.cssText = 'display:flex; gap:8px; align-items:center;';

        const btnView=document.createElement('button');
        btnView.className='btn primary';
        btnView.textContent='ดูรายละเอียด';
        btnView.style.cssText='white-space:nowrap; padding:6px 14px; background:#2563eb; border:1px solid #2563eb; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btnView.onclick=function(){
            const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z\"></path></svg> ฟอร์มรายงาน QC จากส่วนกลาง';
                iframe.src='/pages/trade_qc_report_view.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            }
        };
        
        const btnReject=document.createElement('button');
        btnReject.className='btn outline danger';
        btnReject.textContent='ปฏิเสธ';
        btnReject.style.cssText='white-space:nowrap; padding:6px 14px; background:transparent; border:1px solid #ef4444; color:#ef4444; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btnReject.onclick=function(){
            Swal.fire({
                title: \'ปฏิเสธผล QC\',
                text: \'กรุณาระบุเหตุผลที่ปฏิเสธผล QC (การปฏิเสธจะยกเลิกการแลกเปลี่ยนนี้ทันที)\',
                input: \'text\',
                inputPlaceholder: \'เหตุผลการปฏิเสธ\',
                icon: \'warning\',
                showCancelButton: true,
                confirmButtonColor: \'#ef4444\',
                cancelButtonColor: \'#6b7280\',
                confirmButtonText: \'ยืนยันปฏิเสธ\',
                cancelButtonText: \'ยกเลิก\',
                inputValidator: (value) => {
                    if (!value || value.trim() === \'\') {
                        return \'กรุณาระบุเหตุผล!\';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const f = new FormData(); f.append(\'action\', \'reject\'); f.append(\'request_id\', data.requestId); f.append(\'reason\', result.value);
                    fetch(\'/pages/handlers/trade_qc_acceptance.php\', { method: \'POST\', body: f }).then(res=>res.json()).then(res=>{
                        if(res.success) window.location.reload();
                        else Swal.fire(\'ข้อผิดพลาด\', res.message||\'Error\', \'error\');
                    });
                }
            });
        };

        const btnAccept=document.createElement('button');
        btnAccept.className='btn primary';
        btnAccept.textContent='คอนเฟิร์มรับสินค้า';
        btnAccept.style.cssText='white-space:nowrap; padding:6px 14px; background:#10b981; border:1px solid #10b981; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btnAccept.onclick=function(){
            Swal.fire({
                title: \'ยืนยันการรับสินค้า?\',
                text: \'คุณยืนยันที่จะยอมรับผลการตรวจสอบและรับสินค้า ใช่หรือไม่?\',
                icon: \'question\',
                showCancelButton: true,
                confirmButtonColor: \'#10b981\',
                cancelButtonColor: \'#6b7280\',
                confirmButtonText: \'ใช่, ยืนยัน\',
                cancelButtonText: \'ยกเลิก\'
            }).then((result) => {
                if (result.isConfirmed) {
                    const f = new FormData(); f.append(\'action\', \'accept\'); f.append(\'request_id\', data.requestId);
                    fetch(\'/pages/handlers/trade_qc_acceptance.php\', {
                        method: \'POST\', body: f
                    }).then(res=>res.json()).then(res=>{
                        if(res.success) window.location.reload();
                        else Swal.fire(\'ข้อผิดพลาด\', res.message||\'Error\', \'error\');
                    });
                }
            });
        };
        
        btnContainer.append(btnView, btnReject, btnAccept);
        b.append(btnContainer);
        cb.append(b);
    }
    else if(!myAdminTr){
        const b=document.createElement('div');
        b.style.cssText='padding:14px;background:#f0fff4;color:#166534;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bbf7d0';
        b.innerHTML='📦 <strong>รอส่วนกลางจัดส่งสินค้า</strong><br>ยอมรับผลการประเมินแล้ว แอดมินกำลังจัดส่งสินค้าให้คุณ';
        cb.append(b);
    }
    else if(!myRv){
        const sb=document.createElement('div');
        sb.style.cssText='padding:15px;background:#f0fff4;color:#276749;border-radius:8px;font-size:14px;text-align:center;border:1px solid #c6f6d5;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
        sb.innerHTML='📦 <strong>แอดมินจัดส่งสินค้าแล้ว!</strong><br>ขนส่ง/เลขพัสดุ: <span style=\"color:#059669;font-weight:600;\">'+myAdminTr+'</span><br><br>';
        const btn=document.createElement('button');btn.className='btn';btn.style.cssText='background:#48bb78;color:#fff;padding:8px 16px';btn.textContent='✅ ยืนยันได้รับสินค้าแล้ว';
        btn.onclick=function(){
            if(confirm('ยืนยันว่าได้รับสินค้าเรียบร้อยแล้ว?')){
                const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';
                f.innerHTML='<input type=\"hidden\" name=\"action\" value=\"receive_item\"><input type=\"hidden\" name=\"request_id\" value=\"'+data.requestId+'\">';
                document.body.append(f);f.submit();
            }
        };
        sb.append(btn);
        cb.append(sb);
    }
    else {
        const sb=document.createElement('div');
        sb.style.cssText='padding:15px;background:#f0fff4;color:#276749;border-radius:8px;font-size:14px;text-align:center;border:1px solid #c6f6d5;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
        sb.innerHTML='🎉 <strong>คุณได้รับสินค้าแล้ว!</strong><br>การแลกเปลี่ยนฝั่งคุณเสร็จสมบูรณ์';
        cb.append(sb);
    }

    if(cb.children.length>0)insertBanner(cb);

";

$new_content = substr($content, 0, $start_pos) . $new_block . substr($content, $end_pos);
file_put_contents('C:/laragon/www/wanawan_project/pages/chat.php', $new_content);
echo "Restored and patched successfully\n";
?>
