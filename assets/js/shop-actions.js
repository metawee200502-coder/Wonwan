function createWanawanPopup() {
    if (document.getElementById('wanawan-popup')) return;
    const style = document.createElement('style');
    style.textContent = `
        .wanawan-popup-backdrop{position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(45,52,43,.36);opacity:0;visibility:hidden;transition:.2s}
        .wanawan-popup-backdrop.show{opacity:1;visibility:visible}
        .wanawan-popup{width:min(420px,100%);padding:34px 30px 26px;text-align:center;background:#fffaf7;border:1px solid #edcfc5;border-radius:22px;box-shadow:0 18px 55px rgba(66,52,45,.2);transform:translateY(12px) scale(.97);transition:.2s}
        .wanawan-popup-backdrop.show .wanawan-popup{transform:none}
        .wanawan-popup-icon{width:72px;height:72px;margin:0 auto 18px;display:grid;place-items:center;border-radius:50%;background:#f8dfe8;color:#d47f9f;font-size:34px}
        .wanawan-popup.error .wanawan-popup-icon{background:#fde5e1;color:#c96d62}
        .wanawan-popup h3{margin:0 0 8px;color:#075969;font-size:23px}
        .wanawan-popup p{margin:0;color:#756b65;line-height:1.6}
        .wanawan-popup-actions{display:flex;gap:10px;margin-top:24px}
        .wanawan-popup-actions button,.wanawan-popup-actions a{flex:1;min-height:44px;display:grid;place-items:center;border-radius:10px;font:inherit;text-decoration:none;cursor:pointer}
        .wanawan-popup-close{border:1px solid #d6958d;background:#fffaf7;color:#c87f78}
        .wanawan-popup-cart{border:1px solid #d6958d;background:#d6958d;color:#fff}
    `;
    document.head.appendChild(style);
    document.body.insertAdjacentHTML('beforeend', `<div class="wanawan-popup-backdrop" id="wanawan-popup"><div class="wanawan-popup"><div class="wanawan-popup-icon">✓</div><h3></h3><p></p><div class="wanawan-popup-actions"><button type="button" class="wanawan-popup-close">เลือกซื้อต่อ</button><a class="wanawan-popup-cart" href="/pages/cart.php">ดูตะกร้าสินค้า</a></div></div></div>`);
    const backdrop = document.getElementById('wanawan-popup');
    backdrop.querySelector('.wanawan-popup-close').addEventListener('click', closeWanawanPopup);
    backdrop.addEventListener('click', event => { if (event.target === backdrop) closeWanawanPopup(); });
}

function showWanawanPopup(title, message, options = {}) {
    createWanawanPopup();
    const backdrop = document.getElementById('wanawan-popup');
    const popup = backdrop.querySelector('.wanawan-popup');
    popup.classList.toggle('error', options.type === 'error');
    popup.querySelector('.wanawan-popup-icon').textContent = options.type === 'error' ? '!' : '✓';
    popup.querySelector('h3').textContent = title;
    popup.querySelector('p').textContent = message;
    const cartLink = popup.querySelector('.wanawan-popup-cart');
    cartLink.style.display = options.showCart === false ? 'none' : 'grid';
    cartLink.href = options.linkHref || '/pages/cart.php';
    cartLink.textContent = options.linkText || 'ดูตะกร้าสินค้า';
    popup.querySelector('.wanawan-popup-close').textContent = options.closeText || 'เลือกซื้อต่อ';
    backdrop.classList.add('show');
}

function closeWanawanPopup() {
    document.getElementById('wanawan-popup')?.classList.remove('show');
}

async function wanawanAction(url, payload) {
    const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
    const data = await response.json();
    if (response.status === 401 && data.login_url) {
        showWanawanPopup('กรุณาเข้าสู่ระบบ', data.message || 'เข้าสู่ระบบก่อนทำรายการ', {
            type:'error',
            linkHref:data.login_url,
            linkText:'เข้าสู่ระบบ',
            closeText:'เลือกซื้อต่อ'
        });
        throw new Error(data.message);
    }
    if (!data.success) throw new Error(data.message || 'เกิดข้อผิดพลาด');
    return data;
}

window.addToCart = async function(bookId) {
    try {
        await wanawanAction('/pages/handlers/cart_handler.php', {action:'add', book_id:bookId});
        showWanawanPopup('เพิ่มลงตะกร้าแล้ว', 'หนังสือถูกเพิ่มลงในตะกร้าสินค้าของคุณเรียบร้อยแล้ว');
    } catch (error) {
        if (error.message !== 'กรุณาเข้าสู่ระบบก่อน') showWanawanPopup('ไม่สามารถเพิ่มสินค้าได้', error.message, {type:'error', showCart:false});
    }
};

window.toggleWishlist = async function(bookId, button, source='book') {
    try {
        const data = await wanawanAction('/pages/handlers/wishlist_handler.php', {action:'toggle', book_id:bookId, source});
        button?.classList.toggle('active', data.active);
        button?.setAttribute('aria-label', data.active ? 'นำออกจากรายการโปรด' : 'เพิ่มในรายการโปรด');
        const label = button?.querySelector('.twin-action-label');
        if (label) label.textContent = data.active ? 'อยู่ในรายการโปรด' : 'รายการโปรด';
    } catch (error) {
        if (error.message !== 'กรุณาเข้าสู่ระบบก่อน') showWanawanPopup('เกิดข้อผิดพลาด', error.message, {type:'error', showCart:false});
    }
};

function rememberCartSelection() {
    const selected = [...document.querySelectorAll('.cart-select')]
        .filter(checkbox => checkbox.checked)
        .map(checkbox => checkbox.closest('.item')?.querySelector('[onclick*="updateCart"]')?.getAttribute('onclick')?.match(/updateCart\((\d+)/)?.[1])
        .filter(Boolean);
    sessionStorage.setItem('wanawan-cart-selection', JSON.stringify(selected));
}

function restoreCartSelection() {
    const saved = sessionStorage.getItem('wanawan-cart-selection');
    if (!saved) return;
    sessionStorage.removeItem('wanawan-cart-selection');
    let selected = [];
    try { selected = JSON.parse(saved); } catch (_) { return; }
    const boxes = [...document.querySelectorAll('.cart-select')];
    boxes.forEach(checkbox => {
        const bookId = checkbox.closest('.item')?.querySelector('[onclick*="updateCart"]')?.getAttribute('onclick')?.match(/updateCart\((\d+)/)?.[1];
        checkbox.checked = selected.includes(bookId);
    });
    boxes[0]?.dispatchEvent(new Event('change', {bubbles:true}));
}

document.addEventListener('DOMContentLoaded', restoreCartSelection);

document.addEventListener('DOMContentLoaded', () => {
    const checkout = document.getElementById('checkout-button');
    if (!checkout) return;
    checkout.addEventListener('click', () => {
        const selected = [...document.querySelectorAll('.cart-select:checked')]
            .map(checkbox => checkbox.closest('.item')?.querySelector('[onclick*="updateCart"]')?.getAttribute('onclick')?.match(/updateCart\((\d+)/)?.[1])
            .filter(Boolean);
        if (!selected.length) return;
        const form = document.createElement('form');
        form.method = 'post'; form.action = '/pages/checkout_summary.php';
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'selected_ids'; input.value = JSON.stringify(selected);
        form.appendChild(input); document.body.appendChild(form); form.submit();
    });
});

document.addEventListener('DOMContentLoaded', () => {
    if (!window.wanawanNotificationTimes) return;
    const formatElapsed = seconds => {
        seconds = Math.max(0, Math.floor(seconds));
        if (seconds < 60) return 'เมื่อสักครู่';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' นาทีที่แล้ว';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' ชั่วโมงที่แล้ว';
        if (seconds < 2592000) return Math.floor(seconds / 86400) + ' วันที่แล้ว';
        if (seconds < 31536000) return Math.floor(seconds / 2592000) + ' เดือนที่แล้ว';
        return Math.floor(seconds / 31536000) + ' ปีที่แล้ว';
    };
    const updateNotificationTimes = () => {
        document.querySelectorAll('.notice[href*="read="]').forEach(notice => {
            const id = new URL(notice.href, location.href).searchParams.get('read');
            const timestamp = Number(window.wanawanNotificationTimes[id]);
            const timeElement = notice.querySelector('.time');
            if (!timestamp || !timeElement) return;
            const dot = timeElement.querySelector('.dot');
            timeElement.textContent = formatElapsed(Date.now() / 1000 - timestamp);
            if (dot) timeElement.appendChild(dot);
        });
    };
    updateNotificationTimes();
    setInterval(updateNotificationTimes, 30000);
});

window.updateCart = async function(bookId, quantity) { try { rememberCartSelection(); await wanawanAction('/pages/handlers/cart_handler.php', {action:'update',book_id:bookId,quantity}); location.reload(); } catch(error) { sessionStorage.removeItem('wanawan-cart-selection'); showWanawanPopup('เกิดข้อผิดพลาด', error.message, {type:'error',showCart:false}); } };
window.removeCart = async function(bookId) { if(!confirm('นำหนังสือออกจากตะกร้าหรือไม่?')) return; try { await wanawanAction('/pages/handlers/cart_handler.php', {action:'remove',book_id:bookId}); location.reload(); } catch(error) { showWanawanPopup('เกิดข้อผิดพลาด', error.message, {type:'error',showCart:false}); } };
window.removeWishlist = async function(bookId) { try { await wanawanAction('/pages/handlers/wishlist_handler.php', {action:'add',book_id:bookId,source:'book'}); await wanawanAction('/pages/handlers/cart_handler.php', {action:'remove',book_id:bookId}); location.reload(); } catch(error) { showWanawanPopup('เกิดข้อผิดพลาด', error.message, {type:'error',showCart:false}); } };
