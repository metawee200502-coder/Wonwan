<style>
.site-footer {
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
@media (max-width: 480px) {
    .footer-inner {
        grid-template-columns: 1fr;
        padding-left: 20px;
        padding-right: 20px;
    }
    .footer-bottom__inner {
        padding-left: 20px;
        padding-right: 20px;
        flex-direction: column;
        gap: 6px;
        text-align: center;
    }
}
</style>

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
