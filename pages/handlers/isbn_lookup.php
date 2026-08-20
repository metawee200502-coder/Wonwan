<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
$isbn = preg_replace('/[^0-9Xx]/', '', $_GET['isbn'] ?? '');
if (!in_array(strlen($isbn), [10, 13], true)) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'กรุณากรอก ISBN 10 หรือ 13 หลัก'], JSON_UNESCAPED_UNICODE); exit;
}
$stmt = $pdo->prepare("SELECT title,author,category FROM books WHERE REPLACE(REPLACE(isbn,'-',''),' ','')=? OR REPLACE(REPLACE(isbn_13,'-',''),' ','')=? ORDER BY book_id DESC LIMIT 1");
$stmt->execute([$isbn,$isbn]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$book) {
    $stmt = $pdo->prepare("SELECT title,author,category FROM exchanges WHERE REPLACE(REPLACE(isbn,'-',''),' ','')=? ORDER BY exchange_id DESC LIMIT 1");
    $stmt->execute([$isbn]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
}
echo json_encode($book ? ['success'=>true,'book'=>$book] : ['success'=>false,'message'=>'ยังไม่พบ ISBN นี้ในระบบ สามารถกรอกข้อมูลเองได้'], JSON_UNESCAPED_UNICODE);
