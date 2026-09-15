<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$oldModalTitle = '<h3 style="margin:0;color:#92400e;font-size:16px;display:flex;align-items:center;gap:8px;">📝 ฟอร์มยืนยันสภาพสินค้า</h3>';
$newModalTitle = '<h3 style="margin:0;color:#175564;font-size:16px;display:flex;align-items:center;gap:8px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> ฟอร์มยืนยันสภาพสินค้า</h3>';

$content = str_replace($oldModalTitle, $newModalTitle, $content);

file_put_contents($file, $content);
echo "Updated chat.php modal title successfully.\n";
