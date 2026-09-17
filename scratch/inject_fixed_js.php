<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

$regex = '/<script>\s*function openExchangeDetails.*?<\/script>/s';
$newJs = file_get_contents('c:/laragon/www/wanawan_project/scratch/fixed_js.txt');

$newC = preg_replace($regex, $newJs, $c);
file_put_contents($file, $newC);
echo "Fixed JS injected.\n";
