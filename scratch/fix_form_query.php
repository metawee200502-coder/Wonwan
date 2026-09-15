<?php
$file = 'C:/laragon/www/wanawan_project/pages/trade_condition_form.php';
$content = file_get_contents($file);

$targetPhp = "    FROM exchange_requests r
    JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
    JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
    WHERE r.request_id = ? AND (r.requester_id = ? OR e_req.owner_id = ?)";

$replacementPhp = "    FROM exchange_requests r
    JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
    LEFT JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
    WHERE r.request_id = ? AND (r.requester_id = ? OR e_req.owner_id = ?)";

$content = str_replace($targetPhp, $replacementPhp, $content);
file_put_contents($file, $content);
echo "Updated trade_condition_form.php JOINs.";
