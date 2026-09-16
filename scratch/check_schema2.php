<?php
require 'c:\laragon\www\wanawan_project\config\config.php';
$stmt = $pdo->query('DESCRIBE exchange_requests');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
