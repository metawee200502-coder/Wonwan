<?php
require 'config/config.php';
$books = $pdo->query('SELECT COUNT(*) as count, seller_type, seller_id FROM books GROUP BY seller_type, seller_id')->fetchAll(PDO::FETCH_ASSOC);
print_r($books);
