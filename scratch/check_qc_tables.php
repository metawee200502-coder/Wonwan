<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SHOW TABLES LIKE 'admin_qc_reports'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("SHOW COLUMNS FROM `exchange_requests` LIKE 'requester_qc_acceptance_status'");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
