(() => {
    if (window.__wanawanNotificationLive) return;
    window.__wanawanNotificationLive = true;

    const style = document.createElement('style');
    style.textContent = '.icon-btn__badge.notification-live-badge{top:-5px!important;right:-7px!important;width:auto!important;height:19px!important;min-width:19px!important;padding:0 5px!important;border:2px solid #fff!important;border-radius:12px!important;display:grid!important;place-items:center!important;background:#cf2f3b!important;color:#fff!important;font-size:10px!important;font-weight:700!important;line-height:1!important}.notification-live-toast-wrap{position:fixed;z-index:20000;top:18px;right:18px;width:min(370px,calc(100vw - 28px));display:grid;gap:10px;pointer-events:none}.notification-live-toast{display:grid;grid-template-columns:42px 1fr auto;gap:11px;align-items:center;padding:13px 14px;border:1px solid #edcbd7;border-radius:15px;background:#fff;box-shadow:0 14px 40px #51333e2b;color:inherit;text-decoration:none;pointer-events:auto;animation:notification-in .25s ease}.notification-live-icon{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:#f7dce7;color:#ad5277}.notification-live-icon svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.notification-live-copy{min-width:0}.notification-live-copy strong,.notification-live-copy span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.notification-live-copy strong{color:#513f46;font-size:14px}.notification-live-copy span{margin-top:3px;color:#88777e;font-size:12px}.notification-live-time{font-size:10px;color:#9b8b91}@keyframes notification-in{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:none}}@media(max-width:600px){.notification-live-toast-wrap{top:10px;right:10px}}';
    document.head.append(style);

    const wrap = document.createElement('div');
    wrap.className = 'notification-live-toast-wrap';
    document.body.append(wrap);
    const icon = '<svg viewBox="0 0 24 24"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>';
    let initialized = Object.prototype.hasOwnProperty.call(window, 'wanawanNotificationInitialId');
    let lastId = Number(window.wanawanNotificationInitialId || 0);
    let busy = false;

    const setBadge = (count) => {
        const headerLink = document.querySelector('a.icon-btn[href*="notifications.php"]');
        const adminLink = document.querySelector('.sidebar-nav a[href*="admin_notifications.php"]');
        if (headerLink) {
            let badge = headerLink.querySelector('.icon-btn__badge');
            if (count && !badge) { badge = document.createElement('span'); badge.className = 'icon-btn__badge notification-live-badge'; headerLink.append(badge); }
            if (badge) { badge.classList.add('notification-live-badge'); badge.textContent = count > 99 ? '99+' : String(count); badge.hidden = !count; badge.title = `${count} รายการที่ยังไม่อ่าน`; }
        }
        if (adminLink) {
            let badge = adminLink.querySelector('.admin-order-badge');
            if (count && !badge) { badge = document.createElement('span'); badge.className = 'admin-order-badge notification-live-badge'; adminLink.append(badge); }
            if (badge) { badge.textContent = count > 99 ? '99+' : String(count); badge.hidden = !count; }
        }
    };

    const setChatBadge = (count, conversations) => {
        const chatLink = document.querySelector('.sidebar-nav a[href*="admin_chat.php"]');
        if (chatLink) {
            let badge = chatLink.querySelector('.admin-chat-unread-badge');
            if (count && !badge) { badge = document.createElement('span'); badge.className = 'admin-order-badge admin-chat-unread-badge'; chatLink.append(badge); }
            if (badge) { badge.textContent = count > 99 ? '99+' : String(count); badge.hidden = !count; }
        }
        document.dispatchEvent(new CustomEvent('wanawan:chat-unread', {detail:{total:count, conversations:conversations || {}}}));
    };

    const showToast = (notice) => {
        const toast = document.createElement('a');
        toast.className = 'notification-live-toast';
        toast.href = notice.url;
        toast.innerHTML = `<span class="notification-live-icon">${icon}</span><span class="notification-live-copy"><strong></strong><span></span></span><time class="notification-live-time"></time>`;
        toast.querySelector('strong').textContent = notice.title;
        toast.querySelector('.notification-live-copy span').textContent = notice.message;
        toast.querySelector('time').textContent = notice.time;
        wrap.prepend(toast);
        document.dispatchEvent(new CustomEvent('wanawan:notification', {detail: notice}));
        window.setTimeout(() => toast.remove(), 6500);
    };

    const poll = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(`/pages/handlers/notification_poll.php?after_id=${lastId}`, {headers:{Accept:'application/json'},cache:'no-store'});
            if (!response.ok) return;
            const result = await response.json();
            if (!result.success) return;
            setBadge(Number(result.unread_count || 0));
            setChatBadge(Number(result.chat_unread_count || 0), result.chat_unread_by_conversation || {});
            if (!initialized) { lastId = Number(result.latest_id || 0); initialized = true; return; }
            result.notifications.forEach(showToast);
            lastId = Math.max(lastId, Number(result.latest_id || 0));
        } catch (_) {} finally { busy = false; }
    };

    poll();
    window.setInterval(poll, 3000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

    const enhanceUserNotificationPage = () => {
        if (!/\/pages\/notifications\.php$/i.test(location.pathname)) return;
        const main = document.querySelector('main.wrap');
        if (!main) return;
        const csrf = main.querySelector('input[name="csrf_token"]')?.value || '';
        const bell = '<svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>';
        const noticeIcons = {
            chat:'<svg viewBox="0 0 24 24"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>',
            exchange:'<svg viewBox="0 0 24 24"><path d="M4 7h14M15 4l3 3-3 3M20 17H6M9 14l-3 3 3 3"/></svg>',
            follow:'<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M19 8v6M16 11h6"/></svg>',
            order:'<svg viewBox="0 0 24 24"><path d="M6 8h12l1 13H5zM9 9V6a3 3 0 0 1 6 0v3"/></svg>',
            general:bell
        };
        const pageStyle = document.createElement('style');
        pageStyle.textContent = '.notice-head .title{display:flex;align-items:center;gap:12px}.notice-head .title svg{width:34px;height:34px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.user-notice-card{display:grid;grid-template-columns:minmax(0,1fr) 66px;margin-bottom:10px}.user-notice-card>.notice{margin:0;border-radius:13px 0 0 13px}.user-notice-delete{border:1px solid #e2b6c3;border-left:0;border-radius:0 13px 13px 0;background:#fff;color:#b34459;font:inherit;cursor:pointer}.user-notice-delete:hover{background:#fff0f4}.notice .icon svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}@media(max-width:600px){.user-notice-card{grid-template-columns:minmax(0,1fr) 54px}}';
        document.head.append(pageStyle);
        const noticeHead = main.querySelector('.notice-head');
        if (noticeHead) { noticeHead.style.justifyContent='flex-start'; noticeHead.style.width='100%'; }
        const heading = main.querySelector('.notice-head .title');
        if (heading) { heading.innerHTML = bell + '<span>การแจ้งเตือน</span>'; heading.style.marginRight='auto'; heading.style.textAlign='left'; }
        const submitDelete = id => {
            const form = document.createElement('form');
            form.method = 'post'; form.hidden = true;
            [['csrf_token',csrf],['delete_one','1'],['notification_id',String(id)]].forEach(([name,value])=>{const input=document.createElement('input');input.name=name;input.value=value;form.append(input)});
            document.body.append(form); form.submit();
        };
        const upgrade = link => {
            if (!link || link.closest('.user-notice-card')) return link?.closest('.user-notice-card');
            const id = Number(new URL(link.href,location.href).searchParams.get('read'));
            const kind=['chat','exchange','follow','order'].find(type=>link.classList.contains(type))||'general';
            const iconNode = link.querySelector('.icon'); if (iconNode) iconNode.innerHTML = noticeIcons[kind];
            const card = document.createElement('article'); card.className='user-notice-card'; card.dataset.notificationId=String(id);
            const del = document.createElement('button'); del.type='button'; del.className='user-notice-delete'; del.textContent='ลบ'; del.setAttribute('aria-label','ลบการแจ้งเตือนนี้'); del.onclick=()=>submitDelete(id);
            link.replaceWith(card); card.append(link,del); return card;
        };
        main.querySelectorAll('a.notice').forEach(upgrade);
        document.addEventListener('wanawan:notification', event => {
            const notice = event.detail;
            if (!notice || main.querySelector(`[data-notification-id="${notice.id}"]`)) return;
            main.querySelector('.empty')?.remove();
            const link=document.createElement('a');link.className='notice unread';link.href=notice.url;
            const iconNode=document.createElement('span');iconNode.className='icon';iconNode.innerHTML=noticeIcons[notice.type]||noticeIcons.general;
            const copy=document.createElement('span'),title=document.createElement('h2'),message=document.createElement('p');title.textContent=notice.title;message.textContent=notice.message;copy.append(title,message);
            const time=document.createElement('span');time.className='time';time.textContent='เมื่อสักครู่ ';const dot=document.createElement('i');dot.className='dot';time.append(dot);link.append(iconNode,copy,time);
            const card=upgrade(link);const head=main.querySelector('.notice-head');if(card&&head)head.after(card);
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhanceUserNotificationPage);
    else enhanceUserNotificationPage();
})();
