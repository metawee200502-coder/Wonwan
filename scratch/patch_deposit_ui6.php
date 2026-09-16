<?php
$chatPath = __DIR__ . '/../pages/chat.php';
$chatContent = file_get_contents($chatPath);

// 1. Update the !myTr banner to match the form theme (soft pink banner, gradient button, Kanit font)
$oldMyTrBanner = <<<'EOD'
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bbf7d0;margin-bottom:12px;gap:10px;';
        sb.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">การวางมัดจำเสร็จสิ้น!</strong><span style="font-size:12px;opacity:0.8;">จัดส่งสินค้าภายใน '+dls+'</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='แจ้งเลขพัสดุ';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#22c55e; border-color:#22c55e; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
EOD;

$newMyTrBanner = <<<'EOD'
        sb.style.cssText='padding:12px 16px;background:#fdf5f7;color:#8b5266;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #f8e1e7;margin-bottom:12px;gap:10px;';
        sb.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#b66f68;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">การวางมัดจำเสร็จสิ้น!</strong><span style="font-size:12px;opacity:0.8;">จัดส่งสินค้าภายใน '+dls+'</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='แจ้งเลขพัสดุ';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:linear-gradient(135deg, #d18aa7, #b66f68); border:none; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:"Kanit", sans-serif;';
EOD;

if (strpos($chatContent, "sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;") !== false) {
    $chatContent = str_replace($oldMyTrBanner, $newMyTrBanner, $chatContent);
    echo "Patched !myTr banner successfully\n";
} else {
    echo "FAILED to patch !myTr banner\n";
}

// 2. Update iframe background in conditionModal to #fff instead of #f8fafc
$oldIframeBg = '<iframe id="conditionIframe" style="width:100%;flex:1;border:none;background:#f8fafc;" src=""></iframe>';
$newIframeBg = '<iframe id="conditionIframe" style="width:100%;flex:1;border:none;background:#fff;" src=""></iframe>';
if (strpos($chatContent, $oldIframeBg) !== false) {
    $chatContent = str_replace($oldIframeBg, $newIframeBg, $chatContent);
    echo "Patched iframe background successfully\n";
} else {
    echo "FAILED to patch iframe background\n";
}

file_put_contents($chatPath, $chatContent);
echo "Done saving chat.php\n";


// 3. Patch trade_p2p_logistics_form.php to remove .card box-shadow and padding when in iframe
$logisticsPath = __DIR__ . '/../pages/trade_p2p_logistics_form.php';
$logisticsContent = file_get_contents($logisticsPath);

$oldStyles = <<<'EOD'
        <?php if (!empty($_REQUEST['iframe'])): ?>
        body { min-height: auto; background: transparent; padding: 10px; }
        .card { box-shadow: none; max-width: 100%; padding: 10px; }
        <?php endif; ?>
EOD;

$newStyles = <<<'EOD'
        <?php if (!empty($_REQUEST['iframe'])): ?>
        body { min-height: auto; background: transparent; padding: 20px; }
        .card { box-shadow: none; max-width: 100%; padding: 0; background: transparent; }
        <?php endif; ?>
EOD;

if (strpos($logisticsContent, "body { min-height: auto; background: transparent; padding: 10px; }") !== false) {
    $logisticsContent = str_replace($oldStyles, $newStyles, $logisticsContent);
    file_put_contents($logisticsPath, $logisticsContent);
    echo "Patched trade_p2p_logistics_form.php successfully\n";
} else {
    echo "FAILED to patch trade_p2p_logistics_form.php\n";
}
