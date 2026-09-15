<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$m = ['display_name' => 'Tester'];
$isMine = false;
$active = ['profile_image' => 'active.jpg'];

$html = '<img src="'.htmlspecialchars($m['profile_image'] ?? ($isMine ? $_SESSION['profile_image'] ?? '/assets/image/default-avatar.png' : $active['profile_image'] ?? '/assets/image/default-avatar.png')).'">';
echo $html;
