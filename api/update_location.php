<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/config.php';

$json = json_decode(file_get_contents('php://input'), true);

if (!isset($json['lat']) || !isset($json['lng'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing coordinates']);
    exit;
}

$lat = (float)$json['lat'];
$lng = (float)$json['lng'];
$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('UPDATE users SET latitude = ?, longitude = ? WHERE user_id = ?');
    $stmt->execute([$lat, $lng, $userId]);
    
    echo json_encode(['status' => 'success', 'message' => 'Location updated']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
