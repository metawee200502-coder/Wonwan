<?php
require 'c:/laragon/www/wanawan_project/config/config.php';

$ids = [20, 21, 22]; // direct_escrow, admin_qc, meetup
foreach ($ids as $requestId) {
    $sql = "SELECT er.*, 
        target.title as target_title, target.owner_id,
        offer.title as offer_title,
        requester.display_name as requester_name,
        owner.display_name as owner_name
        FROM exchange_requests er 
        JOIN exchanges target ON target.exchange_id = er.exchange_id
        JOIN users requester ON requester.user_id = er.requester_id
        JOIN users owner ON owner.user_id = target.owner_id
        LEFT JOIN exchanges offer ON offer.exchange_id = er.offered_exchange_id
        WHERE er.request_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Req $requestId: trade_option = " . $req['trade_option'] . "\n";
}
