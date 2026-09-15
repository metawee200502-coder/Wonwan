<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("DESCRIBE admin_qc_reports");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
