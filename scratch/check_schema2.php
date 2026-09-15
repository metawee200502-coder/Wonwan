<?php
require 'c:/laragon/www/wanawan_project/config/config.php';
foreach ($pdo->query('SHOW COLUMNS FROM exchange_requests') as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
