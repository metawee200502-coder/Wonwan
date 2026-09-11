<?php
require_once __DIR__ . '/config/config.php';
$tables = ['users', 'shops', 'publishers', 'shop_staff', 'publisher_staff'];
$schema = "";
foreach($tables as $table) {
    $q = $pdo->query("DESCRIBE $table");
    $schema .= "=== $table ===\n";
    while($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $schema .= json_encode($row) . "\n";
    }
    $schema .= "\n";
}
file_put_contents('scratch/schema_dump.txt', $schema);
echo "Done";
