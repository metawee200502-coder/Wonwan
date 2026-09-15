<?php
$c = file_get_contents('c:/laragon/www/wanawan_project/pages/chat.php');
echo (strpos($c, 'data.rawStatus===\'completed\'') !== false ? 'FOUND' : 'NOT FOUND');
