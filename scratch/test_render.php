<?php
session_start();
$_SESSION["user_id"] = 1;
$_SESSION["role"] = "publisher_admin";
ob_start();
require "C:\laragon\www\wanawan_project\config\config.php";
$userId = 1; // Assuming user_id=1 is Jamsai publisher admin? 
// Let's find out user_id for publisher_id=4
$stmt=$pdo->query("SELECT user_id FROM publisher_staff WHERE publisher_id=4 LIMIT 1");
$_SESSION["user_id"] = $stmt->fetchColumn();

include "C:\laragon\www\wanawan_project\pages\shop_return_address.php";
$output = ob_get_clean();
if (strpos($output, 'ฝ่ายรับคืนสินค้า') !== false) {
    echo "SUCCESS: The name is rendered in the HTML\n";
} else {
    echo "ERROR: The name is NOT rendered in the HTML\n";
}
if (strpos($output, 'ทดสอบระบบคืนของคืนเงิน') !== false) {
    echo "SUCCESS: The address is rendered in the HTML\n";
} else {
    echo "ERROR: The address is NOT rendered in the HTML\n";
}
