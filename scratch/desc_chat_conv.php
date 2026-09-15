<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query("DESCRIBE chat_conversations");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
