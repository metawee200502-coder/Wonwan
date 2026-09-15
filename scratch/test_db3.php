<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=wanawan_db', 'root', '');
$stmt = $pdo->query('SELECT request_id, status FROM exchange_requests WHERE exchange_id=13');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
