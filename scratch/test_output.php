<?php
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'publisher_admin';
ob_start();
include "C:\laragon\www\wanawan_project\pages\shop_return_address.php";
$output = ob_get_clean();
echo strpos($output, "ทดสอบระบบคืนของคืนเงิน") !== false ? "FOUND" : "NOT FOUND";
