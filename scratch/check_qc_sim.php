<?php
require 'config/config.php';
// reset request 11
$pdo->query("UPDATE exchange_requests SET requester_qc_acceptance_status = 'pending', offer_qc_acceptance_status = 'pending' WHERE request_id = 11");

// simulate user A (requester) click
$reqId = 11;
$side = 'requester';
$decision = 'accepted';
if ($side === 'requester') {
    $pdo->query("UPDATE exchange_requests SET requester_qc_acceptance_status = 'accepted' WHERE request_id = $reqId");
} else {
    $pdo->query("UPDATE exchange_requests SET offer_qc_acceptance_status = 'accepted' WHERE request_id = $reqId");
}

$q = $pdo->query("SELECT requester_qc_acceptance_status, offer_qc_acceptance_status FROM exchange_requests WHERE request_id = $reqId")->fetch();
print_r($q);
