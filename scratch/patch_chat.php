<?php
$file = 'c:\laragon\www\wanawan_project\pages\chat.php';
$c = file_get_contents($file);

// ---- Fix 1: Remove window.onerror debug alert from <head> ----
$c = str_replace(
    '<script>window.onerror = function(msg, url, lineNo, columnNo, error) { alert(\'JS Error: \' + msg + \' at line \' + lineNo); return false; };</script>',
    '',
    $c
);

// ---- Fix 2: Add getCondBanner alias after makeCondBtn is defined ----
// Find the end of makeCondBtn definition (it ends with "return w;};")
// We look for the last "};", which is unique enough after the makeCondBtn closure
$anchor = "btn.onclick=function(){const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');if(modal&&iframe){iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1';modal.style.display='flex';}else{window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId;}};w.append(btn);return w;};";
$replacement = $anchor . "\n const getCondBanner=makeCondBtn;";
$c = str_replace($anchor, $replacement, $c);

// ---- Fix 3: Fix insertBanner to check if ctxBar is visible ----
$c = str_replace(
    'const ctxBar=document.querySelector(\'.chat-context-bar\');const chatHead=document.querySelector(\'.chat-head\');if(ctxBar)ctxBar.after(el);else if(chatHead)chatHead.after(el);',
    'const ctxBar=document.querySelector(\'.chat-context-bar\');const chatHead=document.querySelector(\'.chat-head\');const ctxVisible=ctxBar&&getComputedStyle(ctxBar).display!==\'none\';if(ctxVisible)ctxBar.after(el);else if(chatHead)chatHead.after(el);',
    $c
);

// ---- Apply the new direct_escrow block ----
$oldEscrow = "} else if(data.tradeOption==='direct_escrow'){";
$pos = strpos($c, $oldEscrow);
if ($pos === false) {
    // Try alternate form
    $oldEscrow = "} else if (data.tradeOption === 'direct_escrow') {";
    $pos = strpos($c, $oldEscrow);
}

if ($pos !== false) {
    $endPos = strpos($c, '</script>', $pos);
    $before = substr($c, 0, $pos);
    $after = substr($c, $endPos); // includes </script>

    $newBlock = <<<'EOD'
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

        // My Action: if I haven't shipped yet, show button
        if (!myTr) {
            const mb = document.createElement('div');
            mb.style.cssText = 'padding:14px;background:#f8fafc;color:#475569;border-radius:8px;font-size:14px;text-align:center;border:1px solid #e2e8f0;margin-bottom:10px';
            mb.innerHTML = '<strong>📦 คุณยังไม่ได้จัดส่งสินค้า</strong><br>';
            const btn = document.createElement('button');
            btn.className = 'btn primary';
            btn.textContent = 'แจ้งเลขพัสดุ';
            btn.style.cssText = 'padding:6px 16px;font-size:13px;margin-top:8px;';
            btn.onclick = function() {
                window.location.href = '/pages/trade_p2p_logistics_form.php?request_id=' + data.requestId;
            };
            mb.append(btn);
            cb.append(mb);
        }

        // Partner: if they have shipped, show tracking + receive buttons
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
                btnOk.innerHTML = '✅ รับสินค้าสำเร็จ จบกระบวนการ';
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
                btnProblem.innerHTML = '⚠️ รายงานสินค้ามีปัญหา';
                btnProblem.onclick = function() {
                    window.location.href = '/pages/trade_report_problem.php?request_id=' + data.requestId;
                };

                btnContainer.append(btnOk, btnProblem);
                b.append(btnContainer);
            } else {
                b.innerHTML += '<br><span style="color:#2563eb;font-weight:600;display:inline-block;margin-top:8px;">🎉 คุณยืนยันได้รับสินค้าแล้ว</span>';
            }
            cb.append(b);
        } else if (myTr) {
            // I shipped but partner hasn't yet
            const b = document.createElement('div');
            b.style.cssText = 'padding:14px;background:#fffbe0;color:#975a16;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fbd38d;margin-bottom:10px';
            b.innerHTML = '⏳ <strong>รออีกฝ่ายจัดส่งสินค้า</strong><br>คุณจัดส่งแล้ว กำลังรอให้อีกฝ่ายส่งสินค้ากลับ';
            cb.append(b);
        }

        if (myRv && ptRv) {
            const succ = document.createElement('div');
            succ.style.cssText = 'padding:10px;background:#f0fff4;color:#166534;border-radius:8px;font-size:13px;text-align:center;border:1px solid #bbf7d0;margin-bottom:10px';
            succ.innerHTML = '🎉 <strong>การแลกเปลี่ยนเสร็จสมบูรณ์!</strong>';
            cb.append(succ);
        }

        if (cb.children.length > 0) {
            insertBanner(cb);
        }
    }
}
});
EOD;

    $c = $before . $newBlock . "\n" . $after;
    echo "direct_escrow block replaced!\n";
} else {
    echo "ERROR: Could not find direct_escrow block!\n";
}

// Verify
if (substr_count($c, 'doctype') === 0) {
    echo "ERROR: doctype missing — aborting!\n";
    exit(1);
}
if (substr_count($c, 'getCondBanner=') === 0) {
    echo "ERROR: getCondBanner alias missing — aborting!\n";
    exit(1);
}

file_put_contents($file, $c);
echo "Done! Lines: " . substr_count($c, "\n") . " | getCondBanner: " . substr_count($c, 'getCondBanner') . " | onerror: " . substr_count($c, 'window.onerror') . "\n";
