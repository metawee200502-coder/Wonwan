<?php
require_once __DIR__ . '/../config/config.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$schema = [];
foreach ($tables as $table) {
    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $schema[] = $create['Create Table'] ?? $create['Create View'] ?? '';
}
file_put_contents(__DIR__ . '/schema.sql', implode(";\n\n", $schema));
echo "Schema dumped to scratch/schema.sql";
