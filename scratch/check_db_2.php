<?php
require 'config/config.php';
$q = $pdo->query('SELECT message_id, message_text, HEX(message_text) FROM chat_messages ORDER BY message_id DESC LIMIT 5');
while($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo $r['message_id'].' | '.$r['message_text'].' | '.$r['HEX(message_text)']."\n";
}
