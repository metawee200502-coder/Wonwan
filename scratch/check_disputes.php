<?php
require 'config/config.php';
global $pdo;
$stmt = $pdo->query('SELECT dispute_id, proof_image FROM disputes ORDER BY dispute_id DESC LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
