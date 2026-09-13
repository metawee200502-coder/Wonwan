<?php
session_start();
require_once __DIR__.'/../config/config.php';

$userId = $_SESSION['user_id'] ?? 2; // Default to user 2 (assuming they are seller)

$q=$pdo->prepare('SELECT s.shop_id id, s.shop_name name, s.bank_name, s.bank_account_number, s.bank_account_name FROM shop_staff ss JOIN shops s ON s.shop_id=ss.shop_id WHERE ss.user_id=? LIMIT 1');
if (!$q->execute([$userId])) {
    print_r($q->errorInfo());
} else {
    print_r($q->fetch(PDO::FETCH_ASSOC));
}
