<?php
$c = file_get_contents('c:/laragon/www/wanawan_project/pages/chat.php');
echo (strpos($c, 'QC_PAYMENT_SUBMITTED') !== false ? 'FOUND' : 'NOT_FOUND');
