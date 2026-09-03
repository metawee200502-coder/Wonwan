<?php
require_once __DIR__ . '/../config/config.php';

// 1. Give users a location from their books
$usersWithBooks = $pdo->query("SELECT DISTINCT seller_id FROM books WHERE seller_type = 'user' AND seller_lat IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
foreach ($usersWithBooks as $uid) {
    // Check if user has loc
    $uLoc = $pdo->query("SELECT latitude FROM users WHERE user_id = $uid")->fetchColumn();
    if ($uLoc === null) {
        // Get their first book location
        $loc = $pdo->query("SELECT seller_lat, seller_lng FROM books WHERE seller_type = 'user' AND seller_id = $uid AND seller_lat IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($loc) {
            $pdo->prepare("UPDATE users SET latitude = ?, longitude = ? WHERE user_id = ?")->execute([$loc['seller_lat'], $loc['seller_lng'], $uid]);
        }
    }
}

// 2. Give shops a location from their books
$shopsWithBooks = $pdo->query("SELECT DISTINCT seller_id FROM books WHERE seller_type = 'shop' AND seller_lat IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
foreach ($shopsWithBooks as $sid) {
    $sLoc = $pdo->query("SELECT latitude FROM shops WHERE shop_id = $sid")->fetchColumn();
    if ($sLoc === null) {
        $loc = $pdo->query("SELECT seller_lat, seller_lng FROM books WHERE seller_type = 'shop' AND seller_id = $sid AND seller_lat IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($loc) {
            $pdo->prepare("UPDATE shops SET latitude = ?, longitude = ? WHERE shop_id = ?")->execute([$loc['seller_lat'], $loc['seller_lng'], $sid]);
        }
    }
}

// 3. Force all books to exactly match their seller's profile location
$users = $pdo->query("SELECT user_id, latitude, longitude FROM users WHERE latitude IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    $pdo->prepare("UPDATE books SET seller_lat = ?, seller_lng = ? WHERE seller_type = 'user' AND seller_id = ?")
        ->execute([$u['latitude'], $u['longitude'], $u['user_id']]);
}

$shops = $pdo->query("SELECT shop_id, latitude, longitude FROM shops WHERE latitude IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($shops as $s) {
    $pdo->prepare("UPDATE books SET seller_lat = ?, seller_lng = ? WHERE seller_type = 'shop' AND seller_id = ?")
        ->execute([$s['latitude'], $s['longitude'], $s['shop_id']]);
}

echo "Sync complete.";
