<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/google_oauth.php';

function googleCallbackFail(string $origin = 'http://wanawan_project.test'): never
{
    header('Location: ' . rtrim($origin, '/') . '/pages/login.php?google_error=1');
    exit;
}

$state = (string) ($_GET['state'] ?? '');
$code = (string) ($_GET['code'] ?? '');
if ($state === '' || $code === '' || isset($_GET['error'])) {
    googleCallbackFail();
}

$pdo->beginTransaction();
try {
    $lookup = $pdo->prepare('SELECT nonce,return_origin FROM oauth_login_states WHERE state_hash=? AND expires_at>=NOW() FOR UPDATE');
    $lookup->execute([hash('sha256', $state)]);
    $loginState = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$loginState) {
        throw new RuntimeException('state');
    }
    $pdo->prepare('DELETE FROM oauth_login_states WHERE state_hash=?')->execute([hash('sha256', $state)]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    googleCallbackFail();
}

$origin = (string) $loginState['return_origin'];
try {
    $config = googleOAuthConfig();
    $tokens = googleOAuthPost($config['token_endpoint'], [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);
    if (empty($tokens['id_token'])) {
        throw new RuntimeException('token');
    }
    $identity = googleOAuthGetJson('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode((string) $tokens['id_token']));
    if (
        !hash_equals($config['client_id'], (string) ($identity['aud'] ?? '')) ||
        !in_array((string) ($identity['iss'] ?? ''), ['accounts.google.com', 'https://accounts.google.com'], true) ||
        (int) ($identity['exp'] ?? 0) <= time() ||
        !hash_equals((string) $loginState['nonce'], (string) ($identity['nonce'] ?? '')) ||
        !filter_var($identity['email'] ?? '', FILTER_VALIDATE_EMAIL) ||
        !in_array($identity['email_verified'] ?? false, [true, 'true', 1, '1'], true)
    ) {
        throw new RuntimeException('identity');
    }

    $googleId = (string) $identity['sub'];
    $email = strtolower(trim((string) $identity['email']));
    $name = trim((string) ($identity['name'] ?? '')) ?: strstr($email, '@', true);
    $picture = filter_var($identity['picture'] ?? '', FILTER_VALIDATE_URL) ? (string) $identity['picture'] : null;

    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT * FROM users WHERE google_id=? OR email=? ORDER BY google_id=? DESC LIMIT 1 FOR UPDATE');
    $find->execute([$googleId, $email, $googleId]);
    $user = $find->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        if (!empty($user['google_id']) && !hash_equals((string) $user['google_id'], $googleId)) {
            throw new RuntimeException('account');
        }
        $pdo->prepare("UPDATE users SET google_id=?,display_name=COALESCE(NULLIF(display_name,''),?),profile_image=COALESCE(NULLIF(profile_image,''),?) WHERE user_id=?")
            ->execute([$googleId, $name, $picture, (int) $user['user_id']]);
        $userId = (int) $user['user_id'];
    } else {
        $insert = $pdo->prepare("INSERT INTO users(email,google_id,display_name,profile_image,password_hash,role,notify_preference) VALUES(?,?,?,?,NULL,'reader','email')");
        $insert->execute([$email, $googleId, $name, $picture]);
        $userId = (int) $pdo->lastInsertId();
    }

    $handoff = bin2hex(random_bytes(32));
    $pdo->prepare('DELETE FROM oauth_login_handoffs WHERE expires_at<NOW()')->execute();
    $pdo->prepare('INSERT INTO oauth_login_handoffs(token_hash,user_id,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 2 MINUTE))')
        ->execute([hash('sha256', $handoff), $userId]);
    $pdo->commit();

    header('Location: ' . rtrim($origin, '/') . '/pages/google_complete.php?token=' . rawurlencode($handoff));
    exit;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    googleCallbackFail($origin);
}

