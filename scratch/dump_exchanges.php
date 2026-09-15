<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("DESCRIBE exchanges");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
