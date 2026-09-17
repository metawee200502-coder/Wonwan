<?php
require 'C:\laragon\www\wanawan_project\config\config.php';
$stmt = $pdo->query("SELECT user_id FROM publisher_staff WHERE publisher_id = 4");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
