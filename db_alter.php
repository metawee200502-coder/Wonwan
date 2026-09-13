<?php
require 'config/config.php';
$pdo->exec("ALTER TABLE shop_staff ADD COLUMN position VARCHAR(100) DEFAULT 'Customer Support' AFTER staff_role");
$pdo->exec("ALTER TABLE publisher_staff ADD COLUMN position VARCHAR(100) DEFAULT 'Customer Support' AFTER staff_role");
echo 'OK';
