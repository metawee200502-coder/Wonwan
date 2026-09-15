<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("DESCRIBE exchange_requests");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($cols, JSON_PRETTY_PRINT);
