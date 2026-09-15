<?php
require 'config/config.php';
$stmt = $pdo->query('SHOW TRIGGERS');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
