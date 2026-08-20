<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../config/config.php';
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'กรุณาเข้าสู่ระบบก่อน','login_url'=>'/pages/login.php']); exit; }
$input=json_decode(file_get_contents('php://input'),true)?:$_POST;$action=$input['action']??'add';$bookId=(int)($input['book_id']??0);$userId=(int)$_SESSION['user_id'];
try {
 if($action==='add'){$book=$pdo->prepare("SELECT stock_quantity,book_type,seller_type FROM books WHERE book_id=? AND status='available'");$book->execute([$bookId]);$row=$book->fetch(PDO::FETCH_ASSOC);if(!$row||(int)$row['stock_quantity']<1)throw new RuntimeException('สินค้าหมดหรือไม่พร้อมจำหน่าย');$stock=(int)$row['stock_quantity'];$maxQty=($row['book_type']==='used'&&$row['seller_type']==='user')?1:$stock;$stmt=$pdo->prepare("INSERT INTO cart_items(user_id,book_id,quantity) VALUES(?,?,1) ON DUPLICATE KEY UPDATE quantity=LEAST(quantity+1,?)");$stmt->execute([$userId,$bookId,$maxQty]);}
 elseif($action==='update'){$qty=max(1,(int)($input['quantity']??1));$book=$pdo->prepare("SELECT book_type,seller_type FROM books WHERE book_id=?");$book->execute([$bookId]);$row=$book->fetch(PDO::FETCH_ASSOC);if($row&&$row['book_type']==='used'&&$row['seller_type']==='user')$qty=1;$stmt=$pdo->prepare("UPDATE cart_items ci JOIN books b ON b.book_id=ci.book_id SET ci.quantity=LEAST(?,b.stock_quantity) WHERE ci.user_id=? AND ci.book_id=?");$stmt->execute([$qty,$userId,$bookId]);}
 elseif($action==='remove'){$stmt=$pdo->prepare('DELETE FROM cart_items WHERE user_id=? AND book_id=?');$stmt->execute([$userId,$bookId]);}
 else throw new RuntimeException('คำสั่งไม่ถูกต้อง');
 $count=$pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id=?');$count->execute([$userId]);echo json_encode(['success'=>true,'count'=>(int)$count->fetchColumn()]);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
