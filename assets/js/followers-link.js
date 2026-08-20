document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-followers-type][data-followers-id]').forEach((count) => {
        const type = count.dataset.followersType;
        const id = Number(count.dataset.followersId || 0);
        const target = count.closest('.stat, .profile-stat, .partner-card__stat') || count;
        if (!type || !id || target.classList.contains('followers-link')) return;
        target.classList.add('followers-link');
        target.setAttribute('role', 'link');
        target.setAttribute('tabindex', '0');
        target.setAttribute('aria-label', `ดูรายชื่อผู้ติดตาม ${count.textContent.trim()} คน`);
        const open = () => {
            window.location.href = `/pages/followers.php?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`;
        };
        target.addEventListener('click', open);
        target.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
    });
});
