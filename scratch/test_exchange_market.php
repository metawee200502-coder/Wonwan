<?php
$c = file_get_contents('c:/laragon/www/wanawan_project/pages/exchange_market.php');
echo (strpos($c, 'book_card') !== false ? 'FOUND' : 'NOT_FOUND');
