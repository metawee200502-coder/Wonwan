document.addEventListener('DOMContentLoaded', () => {
    const config = window.wanawanChatReadStatus;
    if (!config) return;

    const messagesContainer = document.querySelector('.messages');
    const composer = document.querySelector('.composer');
    const makeAvatar = (image, name, className = 'message-avatar') => {
        const avatar = document.createElement(image ? 'img' : 'span');
        avatar.className = className;
        if (image) { avatar.src = image; avatar.alt = name || ''; }
        else avatar.textContent = (name || 'ค').trim().charAt(0).toUpperCase();
        return avatar;
    };
    const unifiedStyle = document.createElement('style');
    unifiedStyle.textContent = '.messages{scrollbar-width:none}.messages::-webkit-scrollbar{display:none}.message-row{display:flex;flex-direction:column;align-items:flex-start;margin:0 auto 10px 0;max-width:min(72%,560px)}.message-row.mine{align-items:flex-end;margin-left:auto;margin-right:0}.message-row .bubble{display:table!important;width:auto!important;min-width:0!important;max-width:100%!important;margin:0!important;padding:10px 14px!important;border-radius:18px 18px 18px 6px!important;overflow-wrap:anywhere}.message-row.mine .bubble{border-radius:18px 18px 6px 18px!important}.message-meta{display:flex;align-items:center;gap:6px;margin:5px 5px 0;color:#9a8d91;font-size:10px;line-height:1}.message-row.mine .message-meta{justify-content:flex-end}.message-meta time{display:inline!important;margin:0!important;color:inherit!important;opacity:1!important}.message-meta .read-receipt{display:inline!important;margin:0!important;color:#b45f81!important;font-size:10px!important;opacity:1!important}.chat-date-divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:#9a8d91;font-size:12px}.chat-date-divider:before,.chat-date-divider:after{content:"";height:1px;flex:1;background:#eadfe2}.chat-date-divider span{white-space:nowrap}.conversation .composer{flex:0 0 auto;min-height:70px!important;padding:12px 14px!important;gap:10px!important;align-items:center!important}.conversation .composer>input[name="message_text"]{height:46px!important;min-width:0!important;padding:0 18px!important}.conversation .composer .chat-attach,.conversation .composer>button{position:relative;width:44px!important;height:44px!important;min-width:44px!important;flex:0 0 44px!important;padding:0!important;border-radius:50%!important;display:grid!important;place-items:center!important;line-height:1!important}.conversation .composer>button{font-size:0!important}.conversation .composer>button:after{display:none!important;content:none!important}.conversation .composer>button svg.send-icon{position:absolute;left:50%;top:50%;width:18px;height:18px;transform:translate(-50%,-50%);pointer-events:none}.chat-attach{font-size:25px!important}.chat-attach>svg{display:none!important}';
    document.head.append(unifiedStyle);
    const avatarStyle = document.createElement('style');
    avatarStyle.textContent = '.message-row{display:flex!important;flex-direction:row!important;align-items:flex-end!important;gap:8px!important;max-width:82%!important}.message-row.mine{justify-content:flex-end!important}.message-stack{display:flex;flex-direction:column;align-items:flex-start;min-width:0;max-width:calc(100% - 38px)}.message-row.mine .message-stack{align-items:flex-end;max-width:100%}.message-avatar{width:30px;height:30px;flex:0 0 30px;border-radius:50%;object-fit:cover;background:#f2dbe4;color:#a95575;display:grid;place-items:center;font-size:12px;font-weight:700}.thread-avatar,.chat-head-avatar{object-fit:cover}.thread-copy{min-width:0;flex:1}';
    document.head.append(avatarStyle);
    const compactMediaStyle = document.createElement('style');
    compactMediaStyle.textContent = '.message-row{width:fit-content!important}.message-stack{width:fit-content!important}.message-stack .bubble{width:fit-content!important}.message-stack .chat-image{display:block!important;width:auto!important;max-width:min(320px,calc(100vw - 150px))!important;height:auto!important;max-height:320px!important;object-fit:cover!important}';
    document.head.append(compactMediaStyle);
    document.querySelectorAll('.threads .thread').forEach((thread) => {
        const id = Number(new URL(thread.href, location.href).searchParams.get('conversation'));
        const peer = (config.threadProfiles || []).find((item) => Number(item.id) === id) || {};
        const copy = document.createElement('span');
        copy.className = 'thread-copy';
        Array.from(thread.children).forEach((child) => copy.append(child));
        thread.append(makeAvatar(peer.image, peer.name, 'thread-avatar'), copy);
    });
    const chatHead = document.querySelector('.chat-head');
    if (chatHead) {
        const avatar = makeAvatar(config.peerImage, config.peerName, 'chat-head-avatar');
        const back = chatHead.querySelector('.chat-back');
        if (back) back.after(avatar); else chatHead.prepend(avatar);
    }
    if (composer) {
        const sendButton = composer.querySelector('button[name="send_message"]');
        if (sendButton) sendButton.innerHTML = '<svg class="send-icon" viewBox="0 0 18 18" aria-hidden="true"><path fill="white" d="M2 2.5 16 9 2 15.5 5 9z"/></svg>';
    }
    if (!messagesContainer || !config.conversationId) return;
    const ownBubbles = Array.from(document.querySelectorAll('.bubble.mine'));
    ownBubbles.forEach((bubble, index) => {
        bubble.dataset.messageId = String(config.ownMessageIds[index] || 0);
    });
    let lastMessageId = Number(config.lastMessageId || 0);
    const settleAtBottom = (smooth = false) => {
        if (!messagesContainer) return;
        requestAnimationFrame(() => requestAnimationFrame(() => {
            messagesContainer.scrollTo({top: messagesContainer.scrollHeight, behavior: smooth ? 'smooth' : 'auto'});
        }));
    };

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

    const showReadReceipt = (lastReadId) => {
        document.querySelectorAll('.read-receipt').forEach((receipt) => receipt.remove());
        const readBubbles = Array.from(document.querySelectorAll('.bubble.mine')).filter((bubble) => Number(bubble.dataset.messageId) <= lastReadId);
        const latestReadBubble = readBubbles.at(-1);
        if (!latestReadBubble) return;
        const receipt = document.createElement('span');
        receipt.className = 'read-receipt';
        receipt.textContent = 'อ่านแล้ว';
        wrapBubble(latestReadBubble).querySelector('.message-meta')?.append(receipt);
    };

    showReadReceipt(Number(config.lastReadMessageId || 0));
    if (messagesContainer) {
        messagesContainer.replaceChildren();
        lastMessageId = 0;
    }

    const appendMessage = (message) => {
        if (!messagesContainer || document.querySelector(`.bubble[data-message-id="${message.id}"]`)) return;
        const previousBubble = Array.from(messagesContainer.querySelectorAll('.bubble')).at(-1);
        if (!previousBubble || previousBubble.dataset.date !== message.date_key) {
            const divider = document.createElement('div');
            divider.className = 'chat-date-divider';
            const label = document.createElement('span');
            label.textContent = message.date_label || '';
            divider.append(label);
            messagesContainer.append(divider);
        }
        const bubble = document.createElement('div');
        bubble.className = `bubble${message.mine ? ' mine' : ''}`;
        bubble.dataset.messageId = String(message.id);
        bubble.dataset.date = message.date_key || '';
        
        if (message.is_system) {
            bubble.classList.add('system');
            bubble.style.background = 'transparent';
            bubble.style.padding = '0';
            bubble.style.border = '0';
            bubble.style.boxShadow = 'none';
            if (message.html_content) {
                bubble.innerHTML = message.html_content;
            }
            const time = document.createElement('time');
            time.textContent = message.time;
            bubble.appendChild(time);
            messagesContainer.appendChild(bubble);
            wrapBubble(bubble);
            return;
        }

        if (message.image) {
            if (message.media_type === 'video') {
                const video = document.createElement('video');
                video.className = 'chat-image';
                video.src = message.image;
                video.controls = true;
                video.preload = 'metadata';
                video.addEventListener('loadedmetadata', () => { if (message.mine) settleAtBottom(false); }, {once:true});
                bubble.appendChild(video);
            } else {
            const imageLink = document.createElement('a');
            imageLink.href = message.image;
            imageLink.target = '_blank';
            imageLink.rel = 'noopener';
            const image = document.createElement('img');
            image.className = 'chat-image';
            image.src = message.image;
            image.alt = 'รูปภาพในแชท';
            image.loading = 'lazy';
            image.addEventListener('load', () => { if (message.mine) settleAtBottom(false); }, {once:true});
            imageLink.appendChild(image);
            bubble.appendChild(imageLink);
            }
        }
        if (message.text) {
            const text = document.createElement('span');
            text.className = 'bubble-text';
            text.textContent = message.text;
            bubble.appendChild(text);
        }
        const time = document.createElement('time');
        time.textContent = message.time;
        bubble.appendChild(time);
        messagesContainer.appendChild(bubble);
        wrapBubble(bubble);
    };

    const refreshMessages = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(`/pages/handlers/chat_messages_poll.php?conversation_id=${encodeURIComponent(config.conversationId)}&after_id=${encodeURIComponent(lastMessageId)}`, {
                headers: {'Accept': 'application/json'},
                cache: 'no-store'
            });
            if (!response.ok) return;
            const result = await response.json();
            if (!result.success) return;
            const wasNearBottom = messagesContainer && messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight < 120;
            result.messages.forEach((message) => {
                appendMessage(message);
                lastMessageId = Math.max(lastMessageId, Number(message.id));
            });
            if (result.messages.length && messagesContainer && wasNearBottom) {
                settleAtBottom(true);
            }
            showReadReceipt(Number(result.last_read_message_id || 0));
        } catch (_) {}
    };

    if (composer) {
        const textInput = composer.querySelector('[name="message_text"]');
        if (textInput) textInput.required = false;
        let attachLabel = composer.querySelector('.chat-attach');
        let fileInput = composer.querySelector('[name="chat_image"]');
        if (!attachLabel || !fileInput) {
            attachLabel = document.createElement('label');
            attachLabel.className = 'chat-attach';
            attachLabel.title = 'ถ่ายภาพหรือเลือกรูป';
            attachLabel.setAttribute('aria-label', 'ถ่ายภาพหรือเลือกรูป');
            attachLabel.textContent = '＋';
            fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.name = 'chat_image';
            fileInput.accept = 'image/jpeg,image/png,image/webp,image/gif';
            attachLabel.appendChild(fileInput);
            composer.insertBefore(attachLabel, textInput);
        }
        attachLabel.querySelector('svg')?.remove();
        if (!Array.from(attachLabel.childNodes).some((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim())) attachLabel.prepend('＋');

        let preview = composer.parentElement?.querySelector('.chat-image-preview');
        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'chat-image-preview';
            preview.hidden = true;
            composer.before(preview);
        }
        let previewImage = preview.querySelector('img');
        if (!previewImage) {
            previewImage = document.createElement('img');
            previewImage.alt = 'ตัวอย่างรูปที่จะส่ง';
            preview.appendChild(previewImage);
        }
        let removeImage = preview.querySelector('button');
        if (!removeImage) {
            removeImage = document.createElement('button');
            removeImage.type = 'button';
            removeImage.textContent = '×';
            removeImage.setAttribute('aria-label', 'นำรูปออก');
            preview.appendChild(removeImage);
        }
        let previewUrl = '';
        const clearImage = () => {
            fileInput.value = '';
            preview.hidden = true;
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = '';
        };
        fileInput.addEventListener('change', () => {
            const file = fileInput.files?.[0];
            if (!file) return clearImage();
            if (file.size > 8 * 1024 * 1024) {
                clearImage();
                alert('รูปภาพต้องมีขนาดไม่เกิน 8 MB');
                return;
            }
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(file);
            previewImage.src = previewUrl;
            preview.hidden = false;
        });
        removeImage.addEventListener('click', clearImage);

        composer.addEventListener('submit', async (event) => {
            event.preventDefault();
            const input = composer.querySelector('[name="message_text"]');
            const button = composer.querySelector('[name="send_message"]');
            if (!input || (!input.value.trim() && !fileInput.files?.length) || button.disabled) return;
            button.disabled = true;
            const body = new FormData(composer);
            try {
                const response = await fetch('/pages/handlers/chat_send_message.php', {method: 'POST', body});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'ส่งข้อความไม่สำเร็จ');
                appendMessage(result.message);
                lastMessageId = Math.max(lastMessageId, Number(result.message.id));
                input.value = '';
                clearImage();
                settleAtBottom(false);
                input.focus({preventScroll:true});
            } catch (error) {
                alert(error.message || 'ส่งข้อความไม่สำเร็จ กรุณาลองใหม่');
            } finally {
                button.disabled = false;
            }
        });
    }

    const refreshReadStatus = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(`/pages/handlers/chat_read_status.php?conversation_id=${encodeURIComponent(config.conversationId)}`, {
                headers: {'Accept': 'application/json'},
                cache: 'no-store'
            });
            if (!response.ok) return;
            const result = await response.json();
            if (result.success) showReadReceipt(Number(result.last_read_message_id || 0));
        } catch (_) {}
    };

    refreshMessages();
    setInterval(refreshMessages, 2000);
    setInterval(refreshReadStatus, 4000);
    document.addEventListener('visibilitychange', () => {
        refreshMessages();
        refreshReadStatus();
    });
});
