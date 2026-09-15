<?php
require 'config/config.php';
$stmt = $pdo->query('SELECT request_id, exchange_id, offered_exchange_id FROM exchange_requests WHERE status="accepted"');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
