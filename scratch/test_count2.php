<?php
$c = file_get_contents('c:/laragon/www/wanawan_project/pages/chat.php');
echo "completed: " . substr_count($c, 'data.status===\'completed\'') . "\n";
echo "แลกเปลี่ยนสำเร็จ: " . substr_count($c, 'แลกเปลี่ยนสำเร็จ') . "\n";
