<?php
require 'config/config.php';
global $pdo;
$stmt = $pdo->query('SELECT return_id, evidence_url FROM return_requests ORDER BY return_id DESC LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
