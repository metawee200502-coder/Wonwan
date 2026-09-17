<?php
session_start();
$_SESSION["user_id"] = 22; // User for Jamsai
$_SESSION["role"] = "publisher_admin";
ob_start();
require "C:\laragon\www\wanawan_project\config\config.php";
include "C:\laragon\www\wanawan_project\pages\shop_return_address.php";
$html = ob_get_clean();
file_put_contents("C:\laragon\www\wanawan_project\scratch\shop_html.txt", $html);
