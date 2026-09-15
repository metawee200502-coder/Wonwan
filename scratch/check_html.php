<?php
session_start();
$_SESSION['user_id'] = 57;
$_SESSION['chat_csrf'] = 'abc1234';
$_GET['conversation'] = 27;

ob_start();
require __DIR__ . '/../pages/chat.php';
$html = ob_get_clean();

file_put_contents(__DIR__ . '/chat_output.html', $html);
echo "DONE";
