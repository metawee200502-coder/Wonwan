<?php
require __DIR__.'/../config/config.php';
$type = 'publisher';
$id = 4;
$perPage = 12;
$offset = 0;
$booksStmt=$pdo->prepare("SELECT * FROM books WHERE seller_type=? AND seller_id=? AND status='available' ORDER BY created_at DESC,book_id DESC LIMIT ? OFFSET ?");
$booksStmt->bindValue(1,$type);
$booksStmt->bindValue(2,$id,PDO::PARAM_INT);
$booksStmt->bindValue(3,$perPage,PDO::PARAM_INT);
$booksStmt->bindValue(4,$offset,PDO::PARAM_INT);
$booksStmt->execute();
$books=$booksStmt->fetchAll(PDO::FETCH_ASSOC);
echo count($books) . " books found.";
