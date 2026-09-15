<?php
require 'config/config.php';
$stmt = $pdo->query('SELECT * FROM admin_qc_reports WHERE request_id=11');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
