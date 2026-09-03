<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query("SELECT role, COUNT(*) FROM users GROUP BY role");
print_r($stmt2->fetchAll(PDO::FETCH_KEY_PAIR));
