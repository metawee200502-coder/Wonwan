<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query('DESCRIBE users');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);
