<?php
require_once __DIR__ . '/config/config.php';
$q = $pdo->query("DESCRIBE users");
while($row = $q->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
