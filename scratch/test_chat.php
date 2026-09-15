<?php
require __DIR__ . '/../config/config.php';
$stmt = $pdo->query('SELECT message_id, message_text, HEX(message_text) FROM chat_messages ORDER BY message_id DESC LIMIT 5');
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
