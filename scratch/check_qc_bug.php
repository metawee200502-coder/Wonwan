<?php
require 'config/config.php';
$res = $pdo->query('SELECT request_id, requester_qc_acceptance_status, offer_qc_acceptance_status FROM exchange_requests ORDER BY request_id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
