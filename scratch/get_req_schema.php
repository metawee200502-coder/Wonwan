<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SHOW CREATE TABLE exchange_requests");
echo $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'];
