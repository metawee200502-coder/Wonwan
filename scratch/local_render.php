<?php
session_start();
$_SESSION['user_id'] = 1;
require 'config/config.php';
$conversationId = 14;
$_GET['conversation'] = $conversationId;

ini_set('error_log', __DIR__ . '/debug.log');

ob_start();
include 'pages/chat.php';
ob_get_clean();
echo "Done";
