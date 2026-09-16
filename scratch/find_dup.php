<?php
$lines = file('c:\laragon\www\wanawan_project\pages\chat.php');
foreach($lines as $i => $line) {
    if (strpos($line, 'ยืนยันได้รับสินค้าแล้ว') !== false) {
        echo "Line " . ($i+1) . ":\n" . substr($line, 0, 200) . "...\n";
    }
}
