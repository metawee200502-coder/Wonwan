<?php
require __DIR__.'/../config/config.php';
$stmt = $pdo->prepare("SELECT COUNT(*), seller_type, seller_id FROM books GROUP BY seller_type, seller_id");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
