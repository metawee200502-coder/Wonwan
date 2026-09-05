<?php
$file = "c:/laragon/www/wanawan_project/pages/seller_market.php";
$content = file_get_contents($file);

$search1 = '<span class="commission">ค่าคอมมิชชั่น: ฿<?=number_format($commission,2)?></span>';
$replace1 = '<span class="commission" style="color:#d35400;font-weight:600">รายได้สุทธิ: ฿<?=number_format($subtotal - $commission + ($o[\'shipping_fee\']??0),2)?></span>';
$content = str_replace($search1, $replace1, $content);

$search2 = '<div class="order-card">';
$replace2 = '<div class="order-card" style="cursor:pointer" onclick="openDetail(this)" data-detail=\'<?=htmlspecialchars(json_encode([\'order\'=>$o,\'slip\'=>smSlip($o[\'payment_slip_url\']??null),\'commission\'=>$commission,\'net\'=>$subtotal-$commission+($o[\'shipping_fee\']??0),\'subtotal\'=>$subtotal,\'statusText\'=>$status,\'statusClass\'=>$class],JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>\'>';
$content = str_replace($search2, $replace2, $content);

// Remove the detail-btn button from card-actions
$search3 = '<button class="btn detail-btn outline" data-detail=\'<?=htmlspecialchars(json_encode([\'order\'=>$o,\'slip\'=>smSlip($o[\'payment_slip_url\']??null)],JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>\'>ดูรายละเอียด</button>';
$content = str_replace($search3, '', $content);

file_put_contents($file, $content);
echo "Done";
