<?php
require __DIR__.'/../config/config.php';
$stmt = $pdo->prepare("SELECT status, COUNT(*) FROM books WHERE seller_type='publisher' AND seller_id=4 GROUP BY status");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
