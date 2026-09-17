<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'shop_admin';
$_SESSION['shop_return_csrf'] = 'test';
$_POST['csrf_token'] = 'test';
require 'api/update_shop_return_address.php';
