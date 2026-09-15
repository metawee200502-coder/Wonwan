<?php
$c = file_get_contents('c:/laragon/www/wanawan_project/pages/chat.php');
echo (strpos($c, 'data.adminTrackingToRequester') !== false ? 'FOUND' : 'NOT FOUND') . "\n";
