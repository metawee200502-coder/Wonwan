<?php
require 'config/config.php';
$stmt = $pdo->query('SELECT user_id, email, role FROM users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
