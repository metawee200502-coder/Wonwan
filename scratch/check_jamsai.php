<?php
require __DIR__.'/../config/config.php';
$stmt = $pdo->prepare("SELECT * FROM publishers WHERE company_name LIKE '%แจ่มใส%'");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
