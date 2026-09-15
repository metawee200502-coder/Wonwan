<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=wanawan_db', 'root', '');
$stmt = $pdo->query('SELECT * FROM exchange_requests ORDER BY request_id DESC LIMIT 1');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
