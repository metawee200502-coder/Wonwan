<?php
require __DIR__.'/../config/config.php';
$stmt = $pdo->query("SHOW CREATE TABLE orders");
$res = $stmt->fetch();
file_put_contents(__DIR__.'/orders_schema.txt', $res[1]);
echo "Done";
