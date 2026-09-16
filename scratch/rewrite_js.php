<?php
$file = 'c:\laragon\www\wanawan_project\pages\chat.php';
$content = file_get_contents($file);

// Find the start of direct_escrow
$pos = strpos($content, '} else if(data.tradeOption===\'direct_escrow\'){');

if ($pos !== false) {
    // Find the end of the script block
    $endPos = strpos($content, '</script>', $pos);
    
    $before = substr($content, 0, $pos);
    $after = substr($content, $endPos); // Includes </script>
    
    $newEscrow = <<<'EOD'
} else if (data.tradeOption === 'direct_escrow') {
    if (!data.conditionsAccepted) {
        const cb = document.createElement('div');
        const banner = getCondBanner();
        if (banner) cb.append(banner);
        insertBanner(cb);
    } else {
        const cb = document.createElement('div');
        const myR = (data.myCondition) ? data.myCondition.required_deposit_amount : null;
        const myS = (data.myCondition) ? data.myCondition.deposit_status : null;
        const ptR = (data.partnerCondition) ? data.partnerCondition.required_deposit_amount : null;
        const ptS = (data.partnerCondition) ? data.partnerCondition.deposit_status : null;
        
        if (myR === null) {
            const b = document.createElement('div');
            b.style.cssText = 'padding:14px;background:#fff5f5;color:#c53030;border-radius:8px;font-size:14px;text-align:center;border:1px solid #feb2b2';
            b.innerHTML = '⚠️ <strong>ระบบยังไม่พร้อม</strong><br>ไม่พบข้อมูลเงื่อนไข กรุณาติดต่อแอดมิน';
            cb.append(b);
        }
        if (ptR === null) {
            // Wait for partner
        }
        if (myR !== null && ptR !== null) {
            const myD = (parseFloat(ptR) === 0) || (myS === 'approved');
            const ptD = (parseFloat(myR) === 0) || (ptS === 'approved');
            
            if (!myD) {
                const b = document.createElement('div');
                b.style.cssText = 'padding:15px;background:#fff5f5;color:#c53030;border-radius:8px;font-size:14px;text-align:center;border:1px solid #feb2b2;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
                if (myS === 'pending_verification') {
                    b.innerHTML = '<i class="fas fa-hourglass-half"></i> <strong>รอแอดมินตรวจสอบสลิปเงินมัดจำ</strong><br>฿' + parseFloat(ptR).toFixed(2);
                } else {
                    b.innerHTML = '⚠️ <strong>คุณต้องชำระเงินมัดจำ</strong><br>฿' + parseFloat(ptR).toFixed(2) + '<br><br>';
                    const btn = document.createElement('button');
                    btn.className = 'btn primary';
                    btn.textContent = 'แนบสลิปชำระเงิน';
                    btn.style.cssText = 'padding:8px 16px';
                    btn.onclick = function() {
                        const modal = document.getElementById('conditionModal');
                        const iframe = document.getElementById('conditionIframe');
                        if (modal && iframe) {
                            modal.querySelector('h3').innerHTML = 'แนบสลิปชำระเงินมัดจำ';
                            iframe.src = '/pages/trade_upload_slip.php?request_id=' + data.requestId + '&iframe=1';
                            modal.style.display = 'flex';
                        } else {
                            window.location.href = '/pages/trade_upload_slip.php?request_id=' + data.requestId;
                        }
                    };
                    b.append(btn);
                }
                cb.append(b);
            }
            if (!ptD) {
                const b = document.createElement('div');
                b.style.cssText = 'padding:14px;background:#ebf4ff;color:#2b6cb0;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bee3f8;margin-bottom:10px';
                b.innerHTML = '<i class="fas fa-clock"></i> <strong>รออีกฝ่ายชำระเงินมัดจำ</strong><br>฿' + parseFloat(myR).toFixed(2);
                cb.append(b);
            }
            if (myD && ptD) {
                const dObj = new Date((data.myCondition && data.myCondition.deposit_approved_at) || (data.partnerCondition && data.partnerCondition.deposit_approved_at) || new Date());
                const sd = parseInt(data.shippingDeadline);
                if (!isNaN(sd)) dObj.setDate(dObj.getDate() + sd);
                const dls = dObj.toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' });
                const myTr = (data.myCondition) ? data.myCondition.tracking_number : null;
                const ptTr = (data.partnerCondition) ? data.partnerCondition.tracking_number : null;
                const ptRv = (data.partnerCondition) ? data.partnerCondition.is_received : 0;
                const myRv = (data.myCondition) ? data.myCondition.is_received : 0;
                
                const myComp = (data.myCondition) ? data.myCondition.logistics_company : null;
                const ptComp = (data.partnerCondition) ? data.partnerCondition.logistics_company : null;
                
                const grid = document.createElement('div');
                grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:10px;margin-bottom:10px;';
                
                const myBox = document.createElement('div');
                myBox.style.cssText = 'padding:15px;border-radius:8px;font-size:13px;text-align:center;border:1px solid #e2e8f0;background:#f8fafc;box-shadow:0 1px 2px rgba(0,0,0,0.02);';
                if (!myTr) {
                    myBox.innerHTML = '<strong>📦 คุณยังไม่ได้จัดส่ง</strong><br><span style="color:#64748b;font-size:12px;">กรุณาจัดส่งภายใน ' + dls + '</span><br><br>';
                    const btn = document.createElement('button');
                    btn.className = 'btn primary';
                    btn.textContent = 'แจ้งเลขพัสดุ';
                    btn.style.cssText = 'padding:6px 12px;font-size:12px;';
                    btn.onclick = function() {
                        const modal = document.getElementById('logisticsModal');
                        const iframe = document.getElementById('logisticsIframe');
                        if (modal && iframe) {
                            iframe.src = '/pages/trade_p2p_logistics_form.php?request_id=' + data.requestId + '&conversation_id=' + data.conversationId + '&iframe=1';
                            modal.style.display = 'flex';
                        } else {
                            window.location.href = '/pages/trade_p2p_logistics_form.php?request_id=' + data.requestId + '&conversation_id=' + data.conversationId;
                        }
                    };
                    myBox.append(btn);
                } else {
                    myBox.innerHTML = '<span style="color:#059669;">✅ <strong>คุณจัดส่งแล้ว</strong></span><br>ขนส่ง: <strong>' + (myComp || '-') + '</strong><br>เลขพัสดุ: <strong style="color:#1e293b;">' + myTr + '</strong>';
                    if (ptRv) myBox.innerHTML += '<br><span style="color:#2563eb;font-weight:600;display:inline-block;margin-top:8px;">🎉 อีกฝ่ายได้รับของแล้ว</span>';
                }
                grid.append(myBox);
                
                const ptBox = document.createElement('div');
                ptBox.style.cssText = 'padding:15px;border-radius:8px;font-size:13px;text-align:center;border:1px solid #e2e8f0;background:#f8fafc;box-shadow:0 1px 2px rgba(0,0,0,0.02);';
                if (!ptTr) {
                    ptBox.innerHTML = '<strong>⏳ รออีกฝ่ายจัดส่งสินค้า</strong><br><span style="color:#64748b;font-size:12px;">กำหนดภายใน ' + dls + '</span>';
                } else {
                    ptBox.innerHTML = '<span style="color:#059669;">📦 <strong>อีกฝ่ายจัดส่งแล้ว!</strong></span><br>ขนส่ง: <strong>' + (ptComp || '-') + '</strong><br>เลขพัสดุ: <strong style="color:#1e293b;">' + ptTr + '</strong>';
                    if (!myRv) {
                        const btnContainer = document.createElement('div');
                        btnContainer.style.cssText = 'display:flex;gap:5px;justify-content:center;margin-top:10px;flex-wrap:wrap;';
                        
                        const btn = document.createElement('button');
                        btn.className = 'btn';
                        btn.style.cssText = 'background:#10b981;color:#fff;padding:6px 10px;font-size:12px;border:0;cursor:pointer;flex:1;border-radius:4px;';
                        btn.textContent = '✅ ได้รับสินค้า';
                        btn.onclick = function() {
                            if (confirm('ยืนยันว่าได้รับสินค้าเรียบร้อยแล้ว?')) {
                                const f = document.createElement('form');
                                f.method = 'post';
                                f.action = '/pages/handlers/trade_submit_logistics.php';
                                f.innerHTML = '<input type="hidden" name="action" value="receive_item"><input type="hidden" name="request_id" value="' + data.requestId + '">';
                                document.body.append(f);
                                f.submit();
                            }
                        };
                        
                        btnContainer.append(btn); // REMOVED CLAIM BUTTON!
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
            }
            if (cb.children.length > 0) insertBanner(cb);
        }
    }
}
});
EOD;

    // We also need to fix the duplicate DOMContentLoaded in the last script tag
    // since it broke JS parsing entirely for that block!
    $after = str_replace("document.addEventListener('DOMContentLoaded', () => {\n      document.addEventListener('DOMContentLoaded', () => {", "document.addEventListener('DOMContentLoaded', () => {", $after);

    $final = $before . $newEscrow . "\n" . $after;
    file_put_contents($file, $final);
    echo "Fixed direct_escrow logic!\n";
} else {
    echo "Could not find direct_escrow block.\n";
}
