<?php
require 'config/config.php';
$stmt = $pdo->query("SELECT * FROM chat_messages WHERE msg_type='system_alert' ORDER BY message_id DESC LIMIT 5");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
