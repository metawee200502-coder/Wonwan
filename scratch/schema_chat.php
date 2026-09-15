<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SHOW CREATE TABLE chat_messages");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
