<?php
session_start();
$_SESSION["user_id"] = 22; // User for Jamsai
$_SESSION["role"] = "publisher_admin";
require "C:\laragon\www\wanawan_project\config\config.php";

$userId = 22;
$role = "publisher_admin";
$sellerType = 'publisher';
$sq = $pdo->prepare('SELECT p.publisher_id id, p.company_name name, p.return_contact_name, p.return_contact_phone, p.return_address FROM publisher_staff ps JOIN publishers p ON p.publisher_id = ps.publisher_id WHERE ps.user_id = ? LIMIT 1');
$sq->execute([$userId]);
$sellerRow = $sq->fetch(PDO::FETCH_ASSOC);

print_r($sellerRow);
