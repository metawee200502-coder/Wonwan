<?php
require 'config/config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM admin_qc_reports');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
