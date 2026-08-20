<?php
session_start();header('Content-Type: application/json; charset=utf-8');require_once __DIR__.'/../../config/config.php';require_once __DIR__.'/../../config/order_reservations.php';
if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['success'=>false]);exit;}
$expired=wanawanExpirePendingOrders($pdo,(int)$_SESSION['user_id']);echo json_encode(['success'=>true,'expired_order_ids'=>$expired]);
