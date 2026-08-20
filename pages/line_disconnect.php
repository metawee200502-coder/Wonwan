<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}
require_once __DIR__ . '/../config/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string) ($_SESSION['line_disconnect_csrf'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit;
}
$pdo->prepare("UPDATE users SET line_user_id=NULL,notify_preference=CASE WHEN notify_preference='both' THEN 'email' WHEN notify_preference='line' THEN 'none' ELSE notify_preference END WHERE user_id=?")
    ->execute([(int) $_SESSION['user_id']]);
unset($_SESSION['line_disconnect_csrf']);
header('Location: /pages/edit_profile.php?line=disconnected');
exit;

