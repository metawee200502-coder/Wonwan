<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/google_oauth.php';

try {
    $config = googleOAuthConfig();
    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(24));
    // X-Forwarded-Host = domain จริง ที่ผู้ใช้เห็น (ngrok ส่งมาเมื่อใช้ --host-header)
    $host = strtolower((string) (
        $_SERVER['HTTP_X_FORWARDED_HOST'] ??
        $_SERVER['HTTP_HOST'] ??
        'wanawan_project.test'
    ));
    $bareHost = preg_replace('/:\d+$/', '', $host); // ตัด port ออก
    $allowedHosts = ['wanawan_project.test', 'localhost'];

    // รองรับ tunnel providers ต่าง ๆ
    $tunnelDomains = [
        '.trycloudflare.com',  // Cloudflare quick tunnel
        '.serveo.net',         // serveo.net (SSH)
        '.localhost.run',      // localhost.run (SSH, ใช้ key)
        '.lhr.life',           // localhost.run (SSH, nokey)
        '.ngrok-free.app',     // ngrok static domain
        '.ngrok-free.dev',     // ngrok static domain (.dev)
        '.ngrok.io',           // ngrok paid domain
    ];
    $isTunnelHost = array_reduce($tunnelDomains, fn($carry, $suffix) => $carry || str_ends_with($bareHost, $suffix), false);
    $isAllowed = in_array($bareHost, $allowedHosts, true) || $isTunnelHost;

    // ตรวจ HTTPS จาก header ของ Cloudflare ด้วย (X-Forwarded-Proto)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $scheme = $isHttps ? 'https' : 'http';

    // ตรวจสอบ base path:
    // - vhost (wanawan_project.test) → SCRIPT_NAME = /pages/google_login.php → basePath = ''
    // - subdirectory (localhost/ngrok)  → SCRIPT_NAME = /wanawan_project/pages/google_login.php → basePath = '/wanawan_project'
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = str_contains($scriptName, '/wanawan_project/') ? '/wanawan_project' : '';

    $returnOrigin = $isAllowed
        ? $scheme . '://' . $host . $basePath
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

