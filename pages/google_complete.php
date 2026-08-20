<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/config.php';

$token = (string) ($_GET['token'] ?? '');
if ($token === '') {
    header('Location: /pages/login.php?google_error=1');
    exit;
}

$pdo->beginTransaction();
try {
    $find = $pdo->prepare('SELECT h.user_id,u.email,u.role,u.display_name,u.profile_image FROM oauth_login_handoffs h JOIN users u ON u.user_id=h.user_id WHERE h.token_hash=? AND h.expires_at>=NOW() FOR UPDATE');
    $find->execute([hash('sha256', $token)]);
    $user = $find->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new RuntimeException('handoff');
    $pdo->prepare('DELETE FROM oauth_login_handoffs WHERE token_hash=?')->execute([hash('sha256', $token)]);
    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['display_name'] = $user['display_name'] ?: $user['email'];
    $profile = (string) ($user['profile_image'] ?? '');
    $_SESSION['avatar'] = preg_match('#^https?://#i', $profile) ? $profile : ($profile !== '' ? '/pages/' . ltrim($profile, '/') : '');
    $redirect = (string)($_SESSION['login_redirect'] ?? '');
    unset($_SESSION['login_redirect']);
    header('Location: ' . ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//') ? $redirect : '/pages/home.php'));
    exit;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: /pages/login.php?google_error=1');
    exit;
}
