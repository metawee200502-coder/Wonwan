<?php
require_once __DIR__ . '/../config/config.php';
$tables = ['users', 'shops', 'publishers'];
foreach ($tables as $table) {
    $stmt = $pdo->query("DESCRIBE $table");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "$table columns: " . implode(', ', $cols) . "<br>";
}
