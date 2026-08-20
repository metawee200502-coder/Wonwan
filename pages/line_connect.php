<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/line_oauth.php';

try {
    $config = wanawanLineConfig();
    $state = bin2hex(random_bytes(32));
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'wanawan_project.test'));
    $hostOnly = preg_replace('/:\d+$/', '', $host);
    $origin = in_array($hostOnly, ['wanawan_project.test', 'localhost'], true)
        ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $host
        : 'http://wanawan_project.test';
    $pdo->prepare('DELETE FROM line_link_states WHERE expires_at<NOW()')->execute();
    $save = $pdo->prepare('INSERT INTO line_link_states(state_hash,user_id,return_origin,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))');
    $save->execute([hash('sha256', $state), (int) $_SESSION['user_id'], $origin]);
    $params = [
        'response_type' => 'code',
        'client_id' => $config['login_channel_id'],
        'redirect_uri' => $config['login_callback_uri'],
        'state' => $state,
        'scope' => 'profile openid',
        'bot_prompt' => 'aggressive',
    ];
    header('Location: https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    exit;
} catch (Throwable $error) {
    header('Location: /pages/edit_profile.php?line=error');
    exit;
}

