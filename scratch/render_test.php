<?php
session_start();
$_SESSION['user_id'] = 2; // Assuming min is user 2 or 1
require 'config/config.php';
$conversationId = 14; // Based on screenshot URL chat.php?conversation=14
$_GET['conversation'] = $conversationId;

ob_start();
include 'pages/chat.php';
$html = ob_get_clean();

// Extract just the messages part
preg_match('/<div class="messages">(.*?)<\/div>/s', $html, $matches);
file_put_contents('scratch/rendered_messages.html', $matches[1] ?? 'NOT FOUND');
echo "Done";
