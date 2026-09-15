<?php
require 'config/config.php';
$reqs = $pdo->query("SELECT request_id, exchange_id, offered_exchange_id, status FROM exchange_requests")->fetchAll(PDO::FETCH_ASSOC);
print_r($reqs);
