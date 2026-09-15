<?php
require 'config/config.php';
$stmt = $pdo->query('SELECT owner_id FROM exchanges WHERE exchange_id = 4');
$res = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($res);
