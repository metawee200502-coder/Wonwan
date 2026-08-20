<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['success'=>false]);exit;}
require_once __DIR__.'/../../config/config.php';
$userId=(int)$_SESSION['user_id'];$conversationId=(int)($_GET['conversation_id']??0);
$access=$pdo->prepare("SELECT 1 FROM chat_conversations WHERE conversation_id=? AND (customer_user_id=? OR (seller_type='user' AND seller_id=?))");
$access->execute([$conversationId,$userId,$userId]);
if(!$access->fetchColumn()){http_response_code(403);echo json_encode(['success'=>false]);exit;}
$stmt=$pdo->prepare('SELECT COALESCE(MAX(message_id),0) FROM chat_messages WHERE conversation_id=? AND sender_user_id=? AND is_read=1');
$stmt->execute([$conversationId,$userId]);
echo json_encode(['success'=>true,'last_read_message_id'=>(int)$stmt->fetchColumn()]);
