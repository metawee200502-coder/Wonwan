<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
file_put_contents(__DIR__.'/db_tables.txt', implode("\n", $tables));
echo "Tables saved to db_tables.txt";
