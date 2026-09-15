<?php
require 'config/config.php';
$q = $pdo->query('DESCRIBE users');
while ($r = $q->fetch()) {
    echo $r['Field'] . "\n";
}
