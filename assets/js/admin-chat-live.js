document.addEventListener('DOMContentLoaded', () => {
    const config = window.wanawanAdminChat;
    const container = document.querySelector('.messages');
    if (!config) return;
    const makeAvatar = (image, name, className = 'message-avatar') => {
        const avatar = document.createElement(image ? 'img' : 'span');
        avatar.className = className;
        if (image) { avatar.src = image; avatar.alt = name || ''; }
        else avatar.textContent = (name || 'ล').trim().charAt(0).toUpperCase();
        return avatar;
    };

    const style = document.createElement('style');
    style.textContent = '.conversation{min-height:0!important;overflow:hidden!important}.messages{min-height:0!important;overflow-y:auto!important;overscroll-behavior:contain;scroll-behavior:smooth;scrollbar-width:none}.messages::-webkit-scrollbar{display:none}.message-row{display:flex;flex-direction:column;align-items:flex-start;margin:0 auto 10px 0;max-width:min(72%,560px)}.message-row.mine{align-items:flex-end;margin-left:auto;margin-right:0}.message-row .bubble{display:table!important;width:auto!important;max-width:100%!important;margin:0!important;padding:10px 14px!important;border-radius:18px 18px 18px 6px!important;overflow-wrap:anywhere}.message-row.mine .bubble{border-radius:18px 18px 6px 18px!important}.message-meta{display:flex;gap:6px;align-items:center;margin:5px 5px 0;color:#9a8d91;font-size:10px;line-height:1}.message-row.mine .message-meta{justify-content:flex-end}.message-meta time{display:inline!important;margin:0!important;color:inherit}.read-receipt{color:#b45f81;font-size:10px}.chat-date-divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:#9a8d91;font-size:12px}.chat-date-divider:before,.chat-date-divider:after{content:"";height:1px;flex:1;background:#eadfe2}.chat-date-divider span{white-space:nowrap}.conversation .composer{flex:0 0 auto;min-height:70px!important;padding:12px 14px!important;gap:10px!important;align-items:center!important}.conversation .composer>input[name="message_text"]{height:46px!important;min-width:0!important;padding:0 18px!important}.conversation .composer .admin-chat-attach,.conversation .composer>button{width:44px!important;height:44px!important;min-width:44px!important;flex:0 0 44px!important;padding:0!important;aspect-ratio:1;border-radius:50%!important;display:grid!important;place-items:center!important;line-height:1!important}.conversation .composer>button{font-size:0}.conversation .composer>button:after{content:"➤";font-size:17px;transform:translateX(1px)}';
    document.head.append(style);
    const sendIconStyle = document.createElement('style');
    sendIconStyle.textContent = '.conversation .composer>button{position:relative!important}.conversation .composer>button:after{display:none!important;content:none!important}.conversation .composer>button svg{position:absolute!important;left:50%!important;top:50%!important;width:18px!important;height:18px!important;transform:translate(-50%,-50%)!important;display:block!important;overflow:visible!important;pointer-events:none}';
    document.head.append(sendIconStyle);
    const avatarStyle = document.createElement('style');
    avatarStyle.textContent = '.thread{display:flex!important;align-items:center;gap:11px;padding:14px 16px!important}.thread-copy{min-width:0;flex:1}.thread-avatar,.chat-head-avatar,.message-avatar{border-radius:50%;object-fit:cover;background:#f2dbe4;color:#a95575;display:grid;place-items:center;font-weight:700}.thread-avatar{width:42px;height:42px;flex:0 0 42px}.chat-head-avatar{width:40px;height:40px;flex:0 0 40px}.chat-head{display:flex;align-items:center;gap:11px}.message-row{display:flex!important;flex-direction:row!important;align-items:flex-end!important;gap:8px!important;max-width:82%!important}.message-row.mine{justify-content:flex-end!important}.message-stack{display:flex;flex-direction:column;align-items:flex-start;min-width:0;max-width:calc(100% - 38px)}.message-row.mine .message-stack{align-items:flex-end;max-width:100%}.message-avatar{width:30px;height:30px;flex:0 0 30px;font-size:12px}';
    document.head.append(avatarStyle);
    const unreadStyle = document.createElement('style');
    unreadStyle.textContent = '.thread-unread-badge{margin-left:auto;min-width:23px;height:23px;padding:0 6px;border-radius:13px;display:grid;place-items:center;background:#cf2f3b;color:#fff;font-size:11px;font-weight:700;line-height:1}.thread-unread-badge[hidden]{display:none!important}';
    document.head.append(unreadStyle);
    const compactMediaStyle = document.createElement('style');
    compactMediaStyle.textContent = '.message-row{width:fit-content!important}.message-stack{width:fit-content!important}.message-stack .bubble{width:fit-content!important}.message-stack .chat-media{display:block!important;width:auto!important;max-width:min(320px,calc(100vw - 150px))!important;height:auto!important;max-height:320px!important;object-fit:cover!important}';
    document.head.append(compactMediaStyle);
    document.querySelectorAll('.threads .thread').forEach((thread) => {
        const id = Number(new URL(thread.href, location.href).searchParams.get('conversation'));
        const peer = (config.threadProfiles || []).find((item) => Number(item.id) === id) || {};
        const copy = document.createElement('span');
        copy.className = 'thread-copy';
        Array.from(thread.children).forEach((child) => copy.append(child));
        thread.dataset.conversationId = String(id);
        thread.append(makeAvatar(peer.image, peer.name, 'thread-avatar'), copy);
        if (Number(peer.unread || 0) > 0) { const badge = document.createElement('span'); badge.className = 'thread-unread-badge'; badge.textContent = Number(peer.unread) > 99 ? '99+' : String(peer.unread); thread.append(badge); }
    });
    const updateThreadUnread = (counts = {}) => document.querySelectorAll('.threads .thread').forEach(thread => { const count = Number(counts[thread.dataset.conversationId] || 0); let badge = thread.querySelector('.thread-unread-badge'); if (count && !badge) { badge = document.createElement('span'); badge.className = 'thread-unread-badge'; thread.append(badge); } if (badge) { badge.textContent = count > 99 ? '99+' : String(count); badge.hidden = !count; } });
    document.addEventListener('wanawan:chat-unread', event => updateThreadUnread(event.detail?.conversations || {}));
    const chatHead = document.querySelector('.chat-head');
    if (chatHead) chatHead.prepend(makeAvatar(config.peerImage, config.peerName, 'chat-head-avatar'));
    const sendButton = document.querySelector('.conversation .composer>button');
    if (sendButton) {
        sendButton.innerHTML = '<svg viewBox="0 0 18 18" aria-hidden="true"><path fill="white" d="M2 2.5 16 9 2 15.5 5 9z"/></svg>';
    }
    if (!container || !config.conversationId) return;

    let lastMessageId = Number(config.lastMessageId || 0);
    let loading = false;
    const nearBottom = () => container.scrollHeight - container.scrollTop - container.clientHeight < 140;
    const scrollBottom = (smooth = false) => requestAnimationFrame(() => requestAnimationFrame(() => container.scrollTo({top: container.scrollHeight, behavior: smooth ? 'smooth' : 'auto'})));
    requestAnimationFrame(() => scrollBottom(false));

    const wrapBubble = (bubble) => {
        const existingRow = bubble.closest('.message-row');
        if (existingRow) return existingRow;
        const row = document.createElement('div');
        row.className = `message-row${bubble.classList.contains('mine') ? ' mine' : ''}`;
        bubble.before(row);
        if (!bubble.classList.contains('mine')) row.append(makeAvatar(config.peerImage, config.peerName));
        const stack = document.createElement('div');
        stack.className = 'message-stack';
        row.append(stack);
        stack.append(bubble);
        const meta = document.createElement('div');
        meta.className = 'message-meta';
        const time = bubble.querySelector('time');
        const receipt = bubble.querySelector('.read-receipt');
        if (time) meta.append(time);
        if (receipt) meta.append(receipt);
        stack.append(meta);
        return row;
    };

    container.querySelectorAll('.bubble').forEach(wrapBubble);

    const showReadReceipt = (lastReadId) => {
        container.querySelectorAll('.read-receipt').forEach((receipt) => receipt.remove());
        const readBubbles = Array.from(container.querySelectorAll('.bubble.mine')).filter((bubble) => Number(bubble.dataset.messageId) <= lastReadId);
        const latestReadBubble = readBubbles.at(-1);
        if (!latestReadBubble) return;
        const receipt = document.createElement('span');
        receipt.className = 'read-receipt';
        receipt.textContent = 'อ่านแล้ว';
        const row = wrapBubble(latestReadBubble);
        row.querySelector('.message-meta')?.append(receipt);
    };

    const appendMessage = (message) => {
        if (container.querySelector(`[data-message-id="${message.id}"]`)) return;
        const previousBubble = Array.from(container.querySelectorAll('.bubble')).at(-1);
        if (!previousBubble || previousBubble.dataset.date !== message.date_key) {
            const divider = document.createElement('div');
            divider.className = 'chat-date-divider';
            const label = document.createElement('span');
            label.textContent = message.date_label;
            divider.append(label);
            container.append(divider);
        }
        const bubble = document.createElement('div');
        bubble.className = `bubble${message.mine ? ' mine' : ''}`;
        bubble.dataset.messageId = String(message.id);
        bubble.dataset.date = message.date_key || '';
        if (message.image) {
            const media = document.createElement(message.media_type === 'video' ? 'video' : 'img');
            media.className = 'chat-media';
            media.src = message.image;
            if (message.media_type === 'video') { media.controls = true; media.preload = 'metadata'; }
            media.addEventListener(message.media_type === 'video' ? 'loadedmetadata' : 'load', () => { if (message.mine) scrollBottom(false); }, {once:true});
            bubble.append(media);
        }
        if (message.text) {
            const text = document.createElement('span');
            text.style.whiteSpace = 'pre-wrap';
            text.textContent = message.text;
            bubble.append(text);
        }
        const time = document.createElement('time');
        time.textContent = message.time;
        bubble.append(time);
        container.append(bubble);
        wrapBubble(bubble);
    };

    const composer = document.getElementById('adminChatComposer');
    composer?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = composer.querySelector('[name="message_text"]');
        const mediaInput = composer.querySelector('[name="chat_media"]');
        const button = composer.querySelector('[name="send_message"]');
        if ((!input?.value.trim() && !mediaInput?.files?.length) || button?.disabled) return;
        if (button) button.disabled = true;
        try {
            const response = await fetch(composer.action, {method:'POST',body:new FormData(composer),headers:{Accept:'application/json'}});
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'ส่งข้อความไม่สำเร็จ');
            appendMessage(result.message);
            lastMessageId = Math.max(lastMessageId, Number(result.message.id));
            if (input) input.value = '';
            if (mediaInput) mediaInput.value = '';
            composer.querySelector('.admin-media-preview')?.remove();
            scrollBottom(false);
            input?.focus({preventScroll:true});
        } catch (error) { alert(error.message || 'ส่งข้อความไม่สำเร็จ กรุณาลองใหม่'); }
        finally { if (button) button.disabled = false; }
    });

    const refresh = async () => {
        if (loading || document.hidden) return;
        loading = true;
        try {
            const shouldFollow = nearBottom();
            const response = await fetch(`/pages/handlers/chat_messages_poll.php?conversation_id=${encodeURIComponent(config.conversationId)}&after_id=${encodeURIComponent(lastMessageId)}`, {headers:{Accept:'application/json'},cache:'no-store'});
            if (!response.ok) return;
            const result = await response.json();
            if (!result.success) return;
            result.messages.forEach((message) => { appendMessage(message); lastMessageId = Math.max(lastMessageId, Number(message.id)); });
            showReadReceipt(Number(result.last_read_message_id || 0));
            if (result.messages.length && shouldFollow) scrollBottom(true);
        } catch (_) {} finally { loading = false; }
    };

    window.setInterval(refresh, 2500);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
});
