const fs = require('fs');
let c = fs.readFileSync('c:/laragon/www/wanawan_project/pages/cart.php', 'utf8');
let footerStr = "<?php require __DIR__.'/../components/footer.php';?>";
let idx = c.indexOf(footerStr);
if (idx !== -1) {
    let goodPart = c.substring(0, idx + footerStr.length);
    // Let's add the <script> block back because the duplicated block included the <script> block for toggleCartWishlist!
    let scriptPart = `\n<script>
async function toggleCartWishlist(bookId, btn) {
    btn.disabled = true;
    try {
        const res = await fetch('/pages/handlers/wishlist_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle', book_id: bookId, source: 'book' })
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'เกิดข้อผิดพลาด');
        if (data.active) {
            btn.classList.add('cart-btn-wish--active');
            btn.dataset.tooltip = 'นำออกจากรายการโปรด';
        } else {
            btn.classList.remove('cart-btn-wish--active');
            btn.dataset.tooltip = 'เพิ่มในรายการโปรด';
        }
    } catch (e) {
        alert(e.message);
    }
    btn.disabled = false;
}
document.addEventListener('DOMContentLoaded',()=>{const boxes=[...document.querySelectorAll('.cart-select')],all=document.getElementById('select-all'),money=v=>'฿'+v.toLocaleString('th-TH',{minimumFractionDigits:2,maximumFractionDigits:2}),shippingFor=qty=>qty>0?45:0,checkout=document.getElementById('checkout-button');function sync(){document.querySelectorAll('.group').forEach(group=>{const own=[...group.querySelectorAll('.cart-select')],checked=own.filter(b=>b.checked);const control=group.querySelector('.group-select');control.checked=checked.length===own.length;control.indeterminate=checked.length>0&&checked.length<own.length});const checked=boxes.filter(b=>b.checked);all.checked=checked.length===boxes.length;all.indeterminate=checked.length>0&&checked.length<boxes.length;calculate()}function calculate(){let count=0,subtotal=0;const sellers={};boxes.forEach(b=>{if(!b.checked)return;const qty=Number(b.dataset.quantity)||0,line=(Number(b.dataset.price)||0)*qty;count+=qty;subtotal+=line;const key=b.dataset.seller;sellers[key]??={name:b.dataset.sellerName,subtotal:0,count:0};sellers[key].subtotal+=line;sellers[key].count+=qty});const shopCount=Object.keys(sellers).length,shipping=Object.values(sellers).reduce((sum,s)=>sum+shippingFor(s.count),0),discount=subtotal*.05,total=subtotal+shipping-discount;document.getElementById('selected-count').textContent=count;document.getElementById('selected-shops').textContent=shopCount;document.getElementById('selected-subtotal').textContent=money(subtotal);document.getElementById('selected-shipping').textContent=money(shipping);document.getElementById('selected-discount').textContent='− '+money(discount);document.getElementById('selected-total').textContent=money(total);document.getElementById('seller-breakdown').innerHTML=shopCount?Object.values(sellers).map(s=>{const fee=shippingFor(s.count);return\`<div class="seller-sum"><strong>\${escapeHtml(s.name)}</strong><div><span>\${s.count} เล่ม · ค่าส่ง \${money(fee)}</span><span>\${money(s.subtotal+fee-s.subtotal*.05)}</span></div></div>\`}).join(''):'<span style="color:var(--text-muted);font-size:13px">เลือกรายการเพื่อดูยอดแยกร้าน</span>';document.querySelectorAll('.group').forEach(g=>{const chosen=[...g.querySelectorAll('.cart-select:checked')],amount=chosen.reduce((n,b)=>n+Number(b.dataset.price)*Number(b.dataset.quantity),0),qty=chosen.reduce((n,b)=>n+Number(b.dataset.quantity),0);g.querySelector('[data-group-total]').textContent=chosen.length?\`เลือก \${qty} เล่ม · ค่าส่ง \${money(shippingFor(qty))}\`:'ยังไม่ได้เลือกสินค้า'});checkout.disabled=!count;checkout.textContent=count?\`สั่งซื้อ \${count} เล่ม จาก \${shopCount} ร้าน →\`:'กรุณาเลือกรายการสินค้า'}function escapeHtml(v){const d=document.createElement('div');d.textContent=v;return d.innerHTML}all?.addEventListener('change',()=>{boxes.forEach(b=>{if(!b.disabled)b.checked=all.checked});sync()});document.querySelectorAll('.group-select').forEach(c=>c.addEventListener('change',()=>{c.closest('.group').querySelectorAll('.cart-select').forEach(b=>{if(!b.disabled)b.checked=c.checked});sync()}));boxes.forEach(b=>b.addEventListener('change',sync));sync()});
</script></body></html>`;
    fs.writeFileSync('c:/laragon/www/wanawan_project/pages/cart.php', goodPart + scriptPart);
    console.log('Fixed');
} else {
    console.log('Footer not found');
}
