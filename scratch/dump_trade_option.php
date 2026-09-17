<?php
require 'c:/laragon/www/wanawan_project/config/config.php';
$stmt = $pdo->query("SELECT request_id, trade_option FROM exchange_requests ORDER BY request_id DESC LIMIT 20");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);
