<?php
$file = 'C:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$targetPhp = "if(\$exchange&&\$exchangeRequest){
 \$exchangeRequestPanel=[
  'target'=>['title'=>\$exchange['title'],'image'=>chatImage(\$exchange['image_cover']??null),'condition'=>(int)(\$exchange['condition_percent']??0)],
  'offer'=>['title'=>\$exchangeRequest['offer_title']?:'ขอพูดคุยก่อน','image'=>chatImage(\$exchangeRequest['offer_image']??null),'condition'=>(int)(\$exchangeRequest['offer_condition']??0)],
  'message'=>\$exchangeRequest['message']?:'สนใจแลกเปลี่ยนสินค้ารายการนี้',
  'status'=>['pending'=>'รอตอบรับ','accepted'=>'กำลังเจรจา','completed'=>'แลกเปลี่ยนสำเร็จ','rejected'=>'ปฏิเสธแล้ว','cancelled'=>'ยกเลิกแล้ว'][\$exchangeRequest['status']]??\$exchangeRequest['status'],
  'manageUrl'=>'/pages/exchange_requests.php?tab='.(intval(\$exchangeRequest['requester_id'])===\$userId?'sent':'received')
 ];
}";

$replacementPhp = "if(\$exchange&&\$exchangeRequest){
 \$exchangeRequestPanel=[
  'requestId'=>(int)\$exchangeRequest['request_id'],
  'rawStatus'=>\$exchangeRequest['status'],
  'tradeOption'=>\$exchangeRequest['trade_option']??'',
  'shippingDeadline'=>(int)(\$exchangeRequest['shipping_deadline_days']??0),
  'meetupDate'=>\$exchangeRequest['meetup_date']??'',
  'meetupTime'=>\$exchangeRequest['meetup_time']??'',
  'meetupLocation'=>\$exchangeRequest['meetup_location']??'',
  'target'=>['title'=>\$exchange['title'],'image'=>chatImage(\$exchange['image_cover']??null),'condition'=>(int)(\$exchange['condition_percent']??0)],
  'offer'=>['title'=>\$exchangeRequest['offer_title']?:'ขอพูดคุยก่อน','image'=>chatImage(\$exchangeRequest['offer_image']??null),'condition'=>(int)(\$exchangeRequest['offer_condition']??0)],
  'message'=>\$exchangeRequest['message']?:'สนใจแลกเปลี่ยนสินค้ารายการนี้',
  'status'=>['pending'=>'รอตอบรับ','accepted'=>'กำลังเจรจา','completed'=>'แลกเปลี่ยนสำเร็จ','rejected'=>'ปฏิเสธแล้ว','cancelled'=>'ยกเลิกแล้ว'][\$exchangeRequest['status']]??\$exchangeRequest['status'],
  'manageUrl'=>'/pages/exchange_requests.php?tab='.((int)\$exchangeRequest['requester_id']===\$userId?'sent':'received')
 ];
}";

// Let's just find the position of "if($exchange&&$exchangeRequest){"
$startPos = strpos($content, 'if($exchange&&$exchangeRequest){');
if ($startPos !== false) {
    $endPos = strpos($content, '}', $startPos);
    if ($endPos !== false) {
        $chunkToReplace = substr($content, $startPos, $endPos - $startPos + 1);
        $content = str_replace($chunkToReplace, $replacementPhp, $content);
        file_put_contents($file, $content);
        echo "Updated chat.php chunk manually.";
    }
}
