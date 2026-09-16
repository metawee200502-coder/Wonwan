<?php
session_start();
$_SESSION['user_id'] = 56;
$_GET['conversation'] = 28;
ob_start();
include('pages/chat.php');
file_put_contents('scratch/chat_output_28.html', ob_get_clean());
echo "Done\n";
