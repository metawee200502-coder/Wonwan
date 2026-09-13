<?php
require 'config/config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE DEFAULT NULL AFTER user_id");
} catch(Exception $e) {}

try {
    $pdo->exec("ALTER TABLE shop_staff ADD COLUMN contact_email VARCHAR(255) NULL AFTER position");
} catch(Exception $e) {}

try {
    $pdo->exec("ALTER TABLE publisher_staff ADD COLUMN contact_email VARCHAR(255) NULL AFTER position");
} catch(Exception $e) {}

echo 'OK';
