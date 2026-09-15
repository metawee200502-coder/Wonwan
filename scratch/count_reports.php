<?php
require 'config/config.php';
$stmt = $pdo->query('SELECT COUNT(*) FROM admin_qc_reports');
print_r($stmt->fetch());
