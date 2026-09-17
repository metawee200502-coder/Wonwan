<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

// 1. Clear text on open
$c = str_replace(
    "document.getElementById('modal-financial-content').innerHTML = '<div style=\"text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;\">กำลังโหลดข้อมูล...</div>';",
    "document.getElementById('modal-financial-content').innerHTML = '<div style=\"text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;\">กำลังโหลดข้อมูล...</div>';\n    let optEl = document.getElementById('modal-trade-option');\n    if (optEl) optEl.textContent = 'กำลังโหลด...';",
    $c
);

// 2. Handle empty trade_option gracefully
$oldJS = "else tradeOptionText = data.trade_option;";
$newJS = "else if (!data.trade_option || data.trade_option === '') tradeOptionText = 'ไม่ระบุรูปแบบ (ข้อมูลเก่า)';\n            else tradeOptionText = data.trade_option;";
$c = str_replace($oldJS, $newJS, $c);

file_put_contents($file, $c);
echo "Patched exchange_requests.php again for tradeOption.\n";
