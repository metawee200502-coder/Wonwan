<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/google_oauth.php';

try {
    $config = googleOAuthConfig();
    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(24));
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'wanawan_project.test'));
    $allowedHosts = ['wanawan_project.test', 'localhost'];
    $returnOrigin = in_array(preg_replace('/:\d+$/', '', $host), $allowedHosts, true)
        ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $host
        : 'http://wanawan_project.test';

    $pdo->prepare('DELETE FROM oauth_login_states WHERE expires_at < NOW()')->execute();
    $save = $pdo->prepare('INSERT INTO oauth_login_states(state_hash,nonce,return_origin,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))');
    $save->execute([hash('sha256', $state), $nonce, $returnOrigin]);

    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
        'prompt' => 'select_account',
    ];
    header('Location: ' . $config['authorization_endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    exit;
} catch (Throwable $error) {
    header('Location: /pages/login.php?google_error=config');
    exit;
}

