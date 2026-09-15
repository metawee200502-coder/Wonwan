<?php
require_once __DIR__ . '/../config/config.php';
$userId = 57; // Customer user ID
$conversationId = 27;

$q=$pdo->prepare("SELECT c.*,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN COALESCE(NULLIF(cu.display_name,''),cu.email) ELSE COALESCE(p.company_name,s.shop_name,NULLIF(su.display_name,''),su.email) END seller_name,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN cu.profile_image ELSE COALESCE(p.profile_image,s.profile_image,su.profile_image) END profile_image FROM chat_conversations c LEFT JOIN publishers p ON c.seller_type='publisher' AND p.publisher_id=c.seller_id LEFT JOIN shops s ON c.seller_type='shop' AND s.shop_id=c.seller_id LEFT JOIN users su ON c.seller_type='user' AND su.user_id=c.seller_id LEFT JOIN users cu ON cu.user_id=c.customer_user_id WHERE c.conversation_id=? AND (c.customer_user_id=? OR (c.seller_type='user' AND c.seller_id=?))");
$q->execute([$userId,$userId,$conversationId,$userId,$userId]);
$active=$q->fetch(PDO::FETCH_ASSOC);

$q=$pdo->prepare('SELECT * FROM exchanges WHERE exchange_id=?');
$q->execute([$active['referenced_exchange_id']]);
$exchange=$q->fetch(PDO::FETCH_ASSOC);

$q=$pdo->prepare("SELECT er.*,offer.title offer_title,offer.image_cover offer_image,offer.condition_percent offer_condition FROM exchange_requests er LEFT JOIN exchanges offer ON offer.exchange_id=er.offered_exchange_id WHERE er.exchange_id=? AND er.requester_id IN (?,?) ORDER BY er.updated_at DESC LIMIT 1");
$q->execute([(int)$active['referenced_exchange_id'],(int)$active['customer_user_id'],(int)$active['seller_id']]);
$exchangeRequest=$q->fetch(PDO::FETCH_ASSOC)?:null;

$q=$pdo->prepare('SELECT COUNT(*) FROM trade_condition_forms WHERE request_id=? AND is_accepted_by_partner=1');
$q->execute([(int)$exchangeRequest['request_id']]);
$conditionsAccepted = ((int)$q->fetchColumn() >= 2);

$target_item_owner_id = (int)($exchange['owner_id'] ?? 0);
$myConditionStmt = $pdo->prepare('SELECT required_deposit_amount, deposit_slip_url, deposit_status, deposit_approved_at, tracking_number, is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=? ORDER BY is_accepted_by_partner DESC, form_id DESC LIMIT 1');
$myConditionStmt->execute([(int)$exchangeRequest['request_id'], $userId]);
$myCondition = $myConditionStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$partnerId = ((int)$exchangeRequest['requester_id'] === $userId) ? $target_item_owner_id : (int)$exchangeRequest['requester_id'];
$partnerConditionStmt = $pdo->prepare('SELECT required_deposit_amount, deposit_slip_url, deposit_status, deposit_approved_at, tracking_number, is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=? ORDER BY is_accepted_by_partner DESC, form_id DESC LIMIT 1');
$partnerConditionStmt->execute([(int)$exchangeRequest['request_id'], $partnerId]);
$partnerCondition = $partnerConditionStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$exchangeRequestPanel=[
  'requestId'=>(int)$exchangeRequest['request_id'],
  'rawStatus'=>$exchangeRequest['status'],
  'tradeOption'=>$exchangeRequest['trade_option']??'',
  'shippingDeadline'=>(int)($exchangeRequest['shipping_deadline_days']??0),
  'conditionsAccepted'=>$conditionsAccepted,
  'myCondition'=>$myCondition,
  'partnerCondition'=>$partnerCondition,
  'target'=>['title'=>$exchange['title'],'condition'=>(int)($exchange['condition_percent']??0)],
  'offer'=>['title'=>$exchangeRequest['offer_title']?:'ขอพูดคุยก่อน','condition'=>(int)($exchangeRequest['offer_condition']??0)],
  'message'=>$exchangeRequest['message']?:'สนใจแลกเปลี่ยนสินค้ารายการนี้',
  'status'=>['pending'=>'รอตอบรับ','accepted'=>'กำลังเจรจา','completed'=>'แลกเปลี่ยนสำเร็จ','rejected'=>'ปฏิเสธแล้ว','cancelled'=>'ยกเลิกแล้ว'][$exchangeRequest['status']]??$exchangeRequest['status'],
  'manageUrl'=>'/pages/exchange_requests.php?tab='.((int)$exchangeRequest['requester_id']===$userId?'sent':'received')
];

$jsPayload = json_encode($exchangeRequestPanel, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

$script = "
const data=$jsPayload;
console.log(data);
";
echo $script;
