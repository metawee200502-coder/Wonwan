<?php
require_once __DIR__ . '/../config/config.php';

echo "Recent Conversations:\n";
$q = $pdo->query("SELECT conversation_id, referenced_exchange_id FROM chat_conversations ORDER BY updated_at DESC LIMIT 5");
print_r($q->fetchAll(PDO::FETCH_ASSOC));

echo "\nRecent Exchange Requests:\n";
$q = $pdo->query("SELECT request_id, exchange_id, requester_id, status FROM exchange_requests ORDER BY request_id DESC LIMIT 5");
print_r($q->fetchAll(PDO::FETCH_ASSOC));
