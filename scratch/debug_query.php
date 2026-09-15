<?php
require_once __DIR__ . '/../config/config.php';

$q = $pdo->prepare("SELECT * FROM chat_conversations WHERE conversation_id=27");
$q->execute();
$conv = $q->fetch(PDO::FETCH_ASSOC);

echo "Conversation 27:\n";
print_r($conv);

$q = $pdo->prepare("SELECT er.*,offer.title offer_title,offer.image_cover offer_image,offer.condition_percent offer_condition FROM exchange_requests er LEFT JOIN exchanges offer ON offer.exchange_id=er.offered_exchange_id WHERE er.exchange_id=? AND er.requester_id IN (?,?) ORDER BY er.request_id DESC LIMIT 1");
$q->execute([(int)$conv['referenced_exchange_id'],(int)$conv['customer_user_id'],(int)$conv['seller_id']]);
$req = $q->fetch(PDO::FETCH_ASSOC);

echo "\nExchange Request Panel Query Result:\n";
var_dump($req);

