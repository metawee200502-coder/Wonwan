<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=wanawan_db', 'root', '');
$stmt = $pdo->query('SELECT is_accepted_by_partner FROM trade_condition_forms WHERE request_id=18');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
