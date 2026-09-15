<?php
session_start();
$_SESSION['user_id'] = 1; // Assuming user_id 1 is min
header('Location: http://wanawan_project.test/pages/chat.php?conversation=1');
