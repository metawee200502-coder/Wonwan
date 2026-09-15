<?php
require_once __DIR__ . '/../config/config.php';
$userId=56;
$conversationId=27;

$q=$pdo->prepare("SELECT c.*,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN COALESCE(NULLIF(cu.display_name,''),cu.email) ELSE COALESCE(p.company_name,s.shop_name,NULLIF(su.display_name,''),su.email) END seller_name,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN cu.profile_image ELSE COALESCE(p.profile_image,s.profile_image,su.profile_image) END profile_image FROM chat_conversations c LEFT JOIN publishers p ON c.seller_type='publisher' AND p.publisher_id=c.seller_id LEFT JOIN shops s ON c.seller_type='shop' AND s.shop_id=c.seller_id LEFT JOIN users su ON c.seller_type='user' AND su.user_id=c.seller_id LEFT JOIN users cu ON cu.user_id=c.customer_user_id WHERE c.conversation_id=? AND (c.customer_user_id=? OR (c.seller_type='user' AND c.seller_id=?))");
$q->execute([$userId,$userId,$conversationId,$userId,$userId]);
$active=$q->fetch(PDO::FETCH_ASSOC);
echo "Active:\n";
print_r($active);

$q=$pdo->prepare('SELECT * FROM exchanges WHERE exchange_id=?');
$q->execute([$active['referenced_exchange_id']]);
$exchange=$q->fetch(PDO::FETCH_ASSOC);
echo "Exchange:\n";
print_r($exchange);

$q=$pdo->prepare("SELECT er.*,offer.title offer_title,offer.image_cover offer_image,offer.condition_percent offer_condition FROM exchange_requests er LEFT JOIN exchanges offer ON offer.exchange_id=er.offered_exchange_id WHERE er.exchange_id=? AND er.requester_id IN (?,?) ORDER BY er.request_id DESC LIMIT 1");
$q->execute([(int)$active['referenced_exchange_id'],(int)$active['customer_user_id'],(int)$active['seller_id']]);
$exchangeRequest=$q->fetch(PDO::FETCH_ASSOC)?:null;
echo "Exchange Request:\n";
print_r($exchangeRequest);
