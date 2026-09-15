<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=wanawan_db', 'root', '');
$stmt = $pdo->query('DESCRIBE trade_condition_forms');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . "\n";
}
