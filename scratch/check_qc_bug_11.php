<?php
require 'config/config.php';
$stmt = $pdo->query("SELECT * FROM exchange_requests WHERE request_id = 11");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($res);
