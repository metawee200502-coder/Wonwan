<?php
require 'config/config.php';
$stmt = $pdo->query('SELECT * FROM chat_messages WHERE conversation_id=14 ORDER BY message_id DESC LIMIT 10');
$messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

foreach($messages as $m) {
    $text = $m['message_text'];
    $isSystem = false;
    
    if(preg_match('/\[PROPOSE_FULL_TRADE:([^\]]+)\]/i', $text, $matches)){
        $isSystem = true;
        echo "MATCHED: " . $text . "\n";
    } else {
        echo "FAILED: " . $text . "\n";
    }
}
