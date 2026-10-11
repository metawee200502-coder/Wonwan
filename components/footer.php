<style>
body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
.site-footer {
    margin-top: auto;
    background-color: var(--card-peach);
    border-top: 1px solid var(--border-color);
    padding: 48px 0 0;
}
.footer-inner {
    display: grid;
    grid-template-columns: 1.6fr 1fr 1fr 1fr;
    gap: 32px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 40px 40px;
}
.footer-brand .brand-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    margin-bottom: 14px;
}
.footer-brand .brand-logo img { height: 44px; }
.footer-brand .brand-logo .name {
    display: block;
    font-size: 18px;
    font-weight: 600;
    color: var(--secondary-color);
    line-height: 1.2;
}
.footer-brand .brand-logo .tag {
    display: block;
    font-size: var(--text-xs);
    color: var(--text-muted);
}
.footer-brand p {
    font-size: var(--text-sm);
    color: var(--text-muted);
    line-height: 1.7;
    margin: 0;
    max-width: 320px;
}
.footer-col h4 {
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--text-dark);
    margin: 0 0 14px;
}
.footer-col ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.footer-col ul li a {
    font-size: var(--text-sm);
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.2s;
}
.footer-col ul li a:hover { color: var(--primary-color); }
.footer-bottom {
    border-top: 1px solid var(--border-color);
    background-color: rgba(0, 0, 0, 0.02);
}
.footer-bottom__inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 14px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.footer-bottom__inner p {
    font-size: var(--text-xs);
    color: var(--text-muted);
    margin: 0;
}
@media (max-width: 768px) {
    .footer-inner { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 1200px) {
    .site-footer { display: none !important; }
}
@media (max-width: 480px) {
}
@media (min-width: 481px) and (max-width: 820px) {
    .footer-inner { padding-left: 22px; padding-right: 22px; gap: 26px; }
}
</style>

<?php if(!empty($enableReservationCountdown)):?>
<style>.reservation-countdown{max-width:1080px;margin:0 auto 22px;padding:16px 20px;border:1px solid #efc2c7;border-radius:13px;background:linear-gradient(135deg,#fff2f3,#fff9f5);display:flex;align-items:center;justify-content:center;gap:18px;color:#70464f}.reservation-countdown strong{color:#b9435b}.reservation-clock{display:flex;align-items:center;gap:5px}.reservation-unit{min-width:58px;padding:8px 7px;border-radius:8px;background:#fff;text-align:center;box-shadow:0 2px 8px #8b4b5a18}.reservation-unit b{display:block;color:#a93651;font-size:20px;font-variant-numeric:tabular-nums}.reservation-unit small{font-size:10px;color:#8d7278}.reservation-separator{font-weight:700;color:#b86b7c}@media(max-width:600px){.reservation-countdown{margin:0 14px 18px;flex-direction:column;gap:10px}.reservation-unit{min-width:52px}}</style>
<div class="reservation-countdown" id="reservationCountdown"><div><strong>กรุณาชำระเงินภายใน 24 ชั่วโมง</strong><div style="font-size:12px;margin-top:3px">ระบบกำลังสำรองสินค้าให้คุณ</div></div><div class="reservation-clock"><span class="reservation-unit"><b id="reserveHours">00</b><small>ชั่วโมง</small></span><span class="reservation-separator">:</span><span class="reservation-unit"><b id="reserveMinutes">00</b><small>นาที</small></span><span class="reservation-separator">:</span><span class="reservation-unit"><b id="reserveSeconds">00</b><small>วินาที</small></span></div></div>
<script>(()=>{const deadline=<?=(int)($reservationCountdownDeadline??0)?>*1000,box=document.getElementById('reservationCountdown');if(!box)return;let expiring=false;const two=value=>String(value).padStart(2,'0');const expire=async()=>{if(expiring)return;expiring=true;box.innerHTML='<strong>หมดเวลาชำระเงิน กำลังยกเลิกคำสั่งซื้อและคืนสินค้า...</strong>';try{await fetch('/pages/handlers/order_reservation_expire.php',{method:'POST'});}finally{location.href='/pages/orders.php?tab=cancelled&expired=1'}};const tick=()=>{const left=Math.max(0,deadline-Date.now()),seconds=Math.floor(left/1000);document.getElementById('reserveHours').textContent=two(Math.floor(seconds/3600));document.getElementById('reserveMinutes').textContent=two(Math.floor(seconds%3600/60));document.getElementById('reserveSeconds').textContent=two(seconds%60);if(left<=0)expire()};<?php if(!empty($reservationCountdownExpired)):?>expire();<?php else:?>tick();setInterval(tick,1000);<?php endif;?>})();</script>
<script>document.querySelector('.title')?.insertAdjacentElement('afterend',document.getElementById('reservationCountdown'));</script>
<?php endif;?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <a href="/pages/home.php" class="brand-logo">
                <img src="/assets/image/logo.png" alt="วนวรรณ" onerror="this.style.display='none'">
                <div>
                    <span class="name">วนวรรณ</span>
                    <span class="tag">Buy • Sell • Exchange</span>
                </div>
            </a>
            <p>สังคมออนไลน์สำหรับนักรักการอ่าน พื้นที่สำหรับแบ่งปันความรู้ สิ่งดี ๆ เรื่องราว และสร้างสรรค์เรื่องราวให้กับกันและกัน</p>
        </div>
        <div class="footer-col">
            <h4>เกี่ยวกับเรา</h4>
            <ul>
                <li><a href="#">About Us</a></li>
                <li><a href="#">Help Center</a></li>
                <li><a href="#">Careers</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>นโยบาย</h4>
            <ul>
                <li><a href="#">Terms of Service</a></li>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Refund Policy</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>สำหรับผู้ใช้</h4>
            <ul>
                <li><a href="#">Seller Center</a></li>
                <li><a href="#">Merchant Guide</a></li>
                <li><a href="#">Advertising</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="footer-bottom__inner">
            <p>© <?= date('Y') ?> วนวรรณ – Buy Sell Exchange</p>
            <p>Supported by Wannawan Archive</p>
        </div>
    </div>
</footer>
<script src="/assets/js/location_distance.js?v=4" defer></script>
<?php require_once __DIR__ . '/live_chat_widget.php'; ?>
