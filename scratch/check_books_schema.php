<?php
require 'C:/laragon/www/wanawan_project/config/config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM books');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
