<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// 1. Column 1 width
$content = str_replace('.qc-col-1 { flex: 0 0 130px; }', '.qc-col-1 { flex: 0 0 190px; }', $content);
$content = str_replace('<div style="flex: 0 0 130px;">รหัสคำขอ</div>', '<div style="flex: 0 0 190px;">รหัสคำขอ</div>', $content);

// 2. Wrap tracking info gracefully
$content = str_replace(
    '.qc-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 11px; margin-bottom: 12px; }',
    '.qc-details-grid { display: flex; flex-direction: column; gap: 6px; font-size: 11px; margin-bottom: 12px; }',
    $content
);

// 3. Slip row wrap
$content = str_replace(
    '.qc-slip-row { display: flex; align-items: center; justify-content: space-between; font-size: 11px; margin-bottom: 4px;}',
    '.qc-slip-row { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; font-size: 11px; margin-bottom: 4px;}',
    $content
);

// 4. Force slip badge flex layout
$content = str_replace(
    '.qc-slip-badge {',
    '.qc-slip-badge { display: inline-flex; align-items: center; gap: 6px; ',
    $content
);


file_put_contents($file, $content);
echo "Patched layout!";
