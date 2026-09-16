<?php
$file = 'c:\laragon\www\wanawan_project\pages\chat.php';
$content = file_get_contents($file);

// Find the start of direct_escrow
$pos = strpos($content, '} else if (data.tradeOption === \'direct_escrow\') {');
if ($pos === false) {
    $pos = strpos($content, '} else if(data.tradeOption===\'direct_escrow\'){');
}

if ($pos !== false) {
    // Find the end of the script block
    $endPos = strpos($content, '</script>', $pos);
    
    $before = substr($content, 0, $pos);
    $after = substr($content, $endPos);
    
    $newEscrow = <<<'EOD'
} else if (data.tradeOption === 'direct_escrow') {
    if (!data.conditionsAccepted) {
        const cb = document.createElement('div');
        const banner = getCondBanner();
        if (banner) cb.append(banner);
        insertBanner(cb);
    } else {
        const cb = document.createElement('div');
        const myTr = (data.myCondition) ? data.myCondition.tracking_number : null;
        const ptTr = (data.partnerCondition) ? data.partnerCondition.tracking_number : null;
        const myRv = (data.myCondition) ? data.myCondition.is_received : 0;
        const ptRv = (data.partnerCondition) ? data.partnerCondition.is_received : 0;
        const ptComp = (data.partnerCondition) ? data.partnerCondition.logistics_company : null;
        
        // My Action Banner
        if (!myTr) {
            const mb = document.createElement('div');
            mb.style.cssText = 'padding:14px;background:#f8fafc;color:#475569;border-radius:8px;font-size:14px;text-align:center;border:1px solid #e2e8f0;margin-bottom:10px';
            mb.innerHTML = '<strong>📦 คุณยังไม่ได้จัดส่งสินค้า</strong><br>';
            const btn = document.createElement('button');
            btn.className = 'btn primary';
            btn.textContent = 'แจ้งเลขพัสดุ';
            btn.style.cssText = 'padding:6px 16px;font-size:13px;margin-top:8px;';
            btn.onclick = function() {
                // For now, redirect to the page. 
                // popup could be implemented here later.
                window.location.href = '/pages/trade_p2p_logistics_form.php?request_id=' + data.requestId;
            };
            mb.append(btn);
            cb.append(mb);
        }

        // Partner Action Banner
        if (ptTr) {
            const b = document.createElement('div');
            b.style.cssText = 'padding:14px;background:#f0fdf4;color:#166534;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bbf7d0;margin-bottom:10px';
            b.innerHTML = '<strong>📦 อีกฝ่ายจัดส่งสินค้าแล้ว</strong><br>ขนส่ง: <strong>' + (ptComp || '-') + '</strong><br>เลขพัสดุ: <strong style="color:#1e293b;">' + ptTr + '</strong>';
            
            if (!myRv) {
                const btnContainer = document.createElement('div');
                btnContainer.style.cssText = 'display:flex;gap:10px;justify-content:center;margin-top:10px;flex-wrap:wrap;';
                
                const btnOk = document.createElement('button');
                btnOk.className = 'btn';
                btnOk.style.cssText = 'background:#10b981;color:#fff;padding:8px 16px;font-size:13px;border:0;border-radius:6px;cursor:pointer;flex:1;min-width:140px;';
                btnOk.innerHTML = '<i class="fas fa-check-circle"></i> รับสินค้าสำเร็จ จบกระบวนการ';
                btnOk.onclick = function() {
                    if(confirm('ยืนยันว่าได้รับสินค้าและตรวจสอบสภาพเรียบร้อยแล้ว?\n\nหากยืนยันแล้วจะไม่สามารถยกเลิกได้')) {
                        const f = document.createElement('form');
                        f.method = 'post';
                        f.action = '/pages/handlers/trade_confirm_receive.php';
                        f.innerHTML = '<input type="hidden" name="csrf_token" value="' + (window.csrfToken || '') + '">' +
                                      '<input type="hidden" name="request_id" value="' + data.requestId + '">';
                        document.body.append(f);
                        f.submit();
                    }
                };
                
                const btnProblem = document.createElement('button');
                btnProblem.className = 'btn';
                btnProblem.style.cssText = 'background:#ef4444;color:#fff;padding:8px 16px;font-size:13px;border:0;border-radius:6px;cursor:pointer;flex:1;min-width:140px;';
                btnProblem.innerHTML = '<i class="fas fa-exclamation-triangle"></i> รายงานสินค้ามีปัญหา';
                btnProblem.onclick = function() {
                    window.location.href = '/pages/trade_report_problem.php?request_id=' + data.requestId;
                };
                
                btnContainer.append(btnOk, btnProblem);
                b.append(btnContainer);
            } else {
                b.innerHTML += '<br><span style="color:#2563eb;font-weight:600;display:inline-block;margin-top:8px;">🎉 คุณยืนยันได้รับสินค้าแล้ว</span>';
            }
            cb.append(b);
        } else {
            const b = document.createElement('div');
            b.style.cssText = 'padding:14px;background:#fffbe0;color:#975a16;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fbd38d;margin-bottom:10px';
            b.innerHTML = '<i class="fas fa-clock"></i> <strong>รออีกฝ่ายจัดส่งสินค้า</strong>';
            cb.append(b);
        }

        if (myRv && ptRv) {
            const succ = document.createElement('div');
            succ.style.cssText = 'padding:10px;background:#f0fff4;color:#166534;border-radius:8px;font-size:13px;text-align:center;border:1px solid #bbf7d0;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
            succ.innerHTML = '🎉 <strong>การแลกเปลี่ยนเสร็จสมบูรณ์!</strong>';
            cb.append(succ);
        }

        if (cb.children.length > 0) {
            insertBanner(cb);
        }
    }
}
EOD;

    $final = $before . $newEscrow . "\n" . $after;
    file_put_contents($file, $final);
    echo "Replaced escrow logic!\n";
} else {
    echo "Could not find escrow logic to replace!\n";
}
