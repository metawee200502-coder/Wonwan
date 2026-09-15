<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("DESCRIBE chat_messages");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
