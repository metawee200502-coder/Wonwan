<?php
$c = file_get_contents('c:/laragon/www/wanawan_project/index.php');
echo (strpos($c, 'exchange') !== false ? 'FOUND' : 'NOT_FOUND');
