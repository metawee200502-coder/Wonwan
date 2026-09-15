<?php
require 'config/config.php';
$cols = $pdo->query("DESCRIBE exchange_requests")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
