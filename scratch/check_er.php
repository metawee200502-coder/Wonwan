<?php
require_once __DIR__ . '/../config/config.php';
$active_referenced_exchange_id = 13;
$active_customer_user_id = 57;
$active_seller_id = 56;

$q=$pdo->prepare("SELECT er.*,offer.title offer_title,offer.image_cover offer_image,offer.condition_percent offer_condition FROM exchange_requests er LEFT JOIN exchanges offer ON offer.exchange_id=er.offered_exchange_id WHERE er.exchange_id=? AND er.requester_id IN (?,?) ORDER BY er.updated_at DESC LIMIT 1");
$q->execute([(int)$active_referenced_exchange_id,(int)$active_customer_user_id,(int)$active_seller_id]);
$exchangeRequest=$q->fetch(PDO::FETCH_ASSOC)?:null;

echo "=== EXCHANGE REQUEST PHP QUERY ===\n";
var_dump($exchangeRequest);
