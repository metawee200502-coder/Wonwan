<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/line_oauth.php';

function lineLinkFail(string $origin = 'http://wanawan_project.test'): never
{
    header('Location: ' . rtrim($origin, '/') . '/pages/edit_profile.php?line=error');
    exit;
}

$state = (string) ($_GET['state'] ?? '');
$code = (string) ($_GET['code'] ?? '');
if ($state === '' || $code === '' || isset($_GET['error'])) lineLinkFail();

$pdo->beginTransaction();
try {
    $find = $pdo->prepare('SELECT user_id,return_origin FROM line_link_states WHERE state_hash=? AND expires_at>=NOW() FOR UPDATE');
    $find->execute([hash('sha256', $state)]);
    $linkState = $find->fetch(PDO::FETCH_ASSOC);
    if (!$linkState) throw new RuntimeException('state');
    $pdo->prepare('DELETE FROM line_link_states WHERE state_hash=?')->execute([hash('sha256', $state)]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    lineLinkFail();
}

$origin = (string) $linkState['return_origin'];
try {
    $config = wanawanLineConfig();
    $tokens = wanawanLinePost('https://api.line.me/oauth2/v2.1/token', [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $config['login_callback_uri'],
        'client_id' => $config['login_channel_id'],
        'client_secret' => $config['login_channel_secret'],
    ]);
    if (empty($tokens['id_token'])) throw new RuntimeException('token');
    $identity = wanawanLinePost('https://api.line.me/oauth2/v2.1/verify', [
        'id_token' => $tokens['id_token'],
        'client_id' => $config['login_channel_id'],
    ]);
    $lineUserId = (string) ($identity['sub'] ?? '');
    if (!preg_match('/^U[0-9a-f]{32}$/', $lineUserId)) throw new RuntimeException('identity');
    $update = $pdo->prepare("UPDATE users SET line_user_id=?,notify_preference=CASE WHEN notify_preference='email' THEN 'both' WHEN notify_preference='none' THEN 'line' ELSE notify_preference END WHERE user_id=?");
    $update->execute([$lineUserId, (int) $linkState['user_id']]);
    header('Location: ' . rtrim($origin, '/') . '/pages/edit_profile.php?line=connected');
    exit;
} catch (Throwable $error) {
    lineLinkFail($origin);
}
