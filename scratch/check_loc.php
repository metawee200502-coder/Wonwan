<?php
require_once __DIR__ . '/../config/config.php';

$shops = $pdo->query("SELECT COUNT(*) FROM shops WHERE latitude IS NOT NULL")->fetchColumn();
$users = $pdo->query("SELECT COUNT(*) FROM users WHERE latitude IS NOT NULL")->fetchColumn();
$books = $pdo->query("SELECT COUNT(*) FROM books WHERE seller_lat IS NOT NULL")->fetchColumn();

echo "Shops with loc: $shops\n";
echo "Users with loc: $users\n";
echo "Books with loc: $books\n";
