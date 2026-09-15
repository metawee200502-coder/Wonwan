<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query('SHOW CREATE TABLE exchange_requests');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'];
