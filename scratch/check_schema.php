<?php
$pdo = new PDO('mysql:host=localhost;dbname=wanawan_project', 'root', '');
foreach ($pdo->query('SHOW COLUMNS FROM exchange_requests') as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}
