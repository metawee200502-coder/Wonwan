<?php
session_start();
$_SESSION['user_id'] = 56;
$_GET['id'] = 27; // conversation_id
ob_start();
include __DIR__ . '/../pages/chat.php';
$html = ob_get_clean();
file_put_contents(__DIR__ . '/rendered_chat_27.html', $html);
echo "Rendered chat 27";
