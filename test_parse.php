<?php
require_once __DIR__.'/config/chat_system_messages.php';

$text = '[P2P_TRACKING_SUBMITTED:J&T Express:12345678990]';
$result = parse_chat_system_message($text);

if ($result) {
    echo "MATCHED:\n";
    echo $result;
} else {
    echo "NOT MATCHED";
}
