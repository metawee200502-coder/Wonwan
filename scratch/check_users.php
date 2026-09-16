<?php
require 'c:\laragon\www\wanawan_project\config\config.php';
print_r($pdo->query('DESCRIBE wallet_transactions')->fetchAll(PDO::FETCH_ASSOC));
