<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['follow_csrf'])) $_SESSION['follow_csrf'] = bin2hex(random_bytes(24));

$followType = in_array($follow_type ?? '', ['publisher','shop','user'], true) ? $follow_type : '';
$followId = (int)($follow_id ?? 0);
$followViewerId = (int)($_SESSION['user_id'] ?? 0);
$followOwnProfile = $followType === 'user' && $followId === $followViewerId;
$followIsActive = false;
if ($followViewerId && $followType && $followId && !$followOwnProfile) {
    $followStateStmt = $pdo->prepare('SELECT 1 FROM profile_follows WHERE follower_user_id=? AND target_type=? AND target_id=?');
    $followStateStmt->execute([$followViewerId, $followType, $followId]);
    $followIsActive = (bool)$followStateStmt->fetchColumn();
}
$followCssClass = trim((string)($follow_class ?? ''));
?>
<?php if ($followType && $followId && !$followOwnProfile): ?>
<button type="button"
        class="<?=htmlspecialchars($followCssClass)?> js-follow-profile <?=$followIsActive?'is-following':''?>"
        data-follow-type="<?=htmlspecialchars($followType)?>"
        data-follow-id="<?=$followId?>"
        data-follow-csrf="<?=htmlspecialchars($_SESSION['follow_csrf'])?>"
        data-login-url="/pages/login.php"
        aria-pressed="<?=$followIsActive?'true':'false'?>"><?=$followIsActive?'กำลังติดตาม':'ติดตาม'?></button>
<?php endif; ?>
<?php
if (empty($GLOBALS['follow_button_script_loaded'])):
    $GLOBALS['follow_button_script_loaded'] = true;
?>
<script>
document.addEventListener('click', async function (event) {
    const button = event.target.closest('.js-follow-profile');
    if (!button || button.disabled) return;
    button.disabled = true;
    const body = new URLSearchParams({
        target_type: button.dataset.followType,
        target_id: button.dataset.followId,
        csrf_token: button.dataset.followCsrf
    });
    try {
        const response = await fetch('/process/follow_profile.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body
        });
        const result = await response.json();
        if (response.status === 401 && result.login_url) {
            location.href = result.login_url;
            return;
        }
        if (!result.success) throw new Error(result.message || 'ไม่สามารถติดตามได้');
        document.querySelectorAll(`.js-follow-profile[data-follow-type="${button.dataset.followType}"][data-follow-id="${button.dataset.followId}"]`).forEach((item) => {
            item.classList.toggle('is-following', result.following);
            item.setAttribute('aria-pressed', result.following ? 'true' : 'false');
            item.textContent = result.following ? 'กำลังติดตาม' : 'ติดตาม';
        });
        document.querySelectorAll(`[data-followers-type="${button.dataset.followType}"][data-followers-id="${button.dataset.followId}"]`).forEach((item) => {
            item.textContent = Number(result.followers_count).toLocaleString('th-TH');
        });
    } catch (error) {
        alert(error.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
    } finally {
        button.disabled = false;
    }
});
</script>
<?php endif; ?>
