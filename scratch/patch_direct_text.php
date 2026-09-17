<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

$old = "else if (data.trade_option === 'direct_escrow' || data.trade_option === 'p2p') tradeOptionText = 'ส่งพัสดุถึงกันโดยตรง (ระบบคนกลาง)';";
$new = "else if (data.trade_option === 'direct_escrow' || data.trade_option === 'p2p') tradeOptionText = 'ส่งพัสดุถึงกันโดยตรง';";

$c = str_replace($old, $new, $c);
file_put_contents($file, $c);

echo "Updated tradeOptionText for direct_escrow.\n";
