<?php
require '../config/config.php';
$stmt = $pdo->query('SHOW CREATE TABLE orders');
print_r($stmt->fetch());
