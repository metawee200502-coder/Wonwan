<?php
require 'config/config.php';
$stmt = $pdo->query("DESCRIBE exchange_requests");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "exchange_requests:\n";
foreach($cols as $c) {
    echo $c['Field'] . " " . $c['Type'] . "\n";
}
