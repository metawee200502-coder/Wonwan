<?php
$c = file_get_contents('c:\laragon\www\wanawan_project\pages\chat.php');
echo "Count 1: " . substr_count($c, 'ยืนยันได้รับสินค้าแล้ว') . "\n";
echo "Count 2: " . substr_count($c, '\u0e22\u0e37\u0e19\u0e22\u0e31\u0e19\u0e44\u0e14\u0e49\u0e23\u0e31\u0e1a\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e41\u0e25\u0e49\u0e27') . "\n";
