<?php
require __DIR__.'/../config/config.php';
$stmt = $pdo->prepare("SELECT * FROM publishers WHERE publisher_id=1");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
