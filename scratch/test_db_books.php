<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("DESCRIBE books");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "books columns: " . implode(', ', $cols) . "\n";
