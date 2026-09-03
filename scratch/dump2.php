<?php
require_once __DIR__ . '/../config/config.php';
$tables = ['users', 'publishers', 'shops', 'orders'];
foreach ($tables as $t) {
    echo "TABLE: $t\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM $t");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . "\n";
    }
    echo "\n";
}
