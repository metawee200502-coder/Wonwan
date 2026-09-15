<?php
require_once __DIR__ . '/../config/config.php';
print_r($pdo->query('SELECT * FROM chat_conversations WHERE conversation_id = 27')->fetch(PDO::FETCH_ASSOC));
