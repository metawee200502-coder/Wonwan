<?php
session_start();
$_SESSION["user_id"] = 22;
$_SESSION["role"] = "publisher_admin";
ob_start();
require "C:\laragon\www\wanawan_project\config\config.php";
include "C:\laragon\www\wanawan_project\pages\shop_return_address.php";
$html = ob_get_clean();

preg_match('/<textarea.*?>(.*?)<\/textarea>/s', $html, $matches);
echo "TEXTAREA CONTENT: " . ($matches[1] ?? 'NOT FOUND');
