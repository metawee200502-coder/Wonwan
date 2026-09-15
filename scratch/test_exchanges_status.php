<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=wanawan_db', 'root', '');
$stmt = $pdo->query("SHOW COLUMNS FROM exchanges LIKE 'status'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
