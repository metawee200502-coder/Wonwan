<style>
.trust-box {
    margin: 36px 0 70px;
    padding: 42px 50px;
    background: #fbd8e2;
    border-radius: 14px;
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}
.trust-box h2 {
    color: var(--secondary-color);
    font-size: 25px;
    margin: 0 0 12px;
}
.trust-box__intro {
    color: var(--secondary-color);
    font-size: var(--text-sm);
    line-height: 1.7;
}
.trust-box__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
    margin-top: 25px;
}
.trust-box__item {
    display: flex;
    gap: 12px;
    color: var(--secondary-color);
}
.trust-box__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(145deg, #dfa09b, #cb827f);
    color: #fff;
    display: grid;
    place-items: center;
    padding: 0;
    margin: 0;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(181, 103, 122, 0.18);
}
.trust-box__icon svg {
    width: 24px;
    height: 24px;
    stroke-width: 1.8;
}
.trust-box__item strong { display: block; }
.trust-box__item > div > span {
    display: block;
    font-size: var(--text-xs);
    margin-top: 4px;
    line-height: 1.55;
}
.trust-box__shield {
    display: grid;
    place-items: center;
    color: #d99490;
}
.trust-box__shield svg { width: 130px; height: 130px; }
@media (max-width: 700px) {
    .trust-box {
        grid-template-columns: 1fr;
        padding: 28px 22px;
    }
    .trust-box__grid { grid-template-columns: 1fr; }
    .trust-box__shield { display: none; }
}
</style>

<section class="trust-box" id="trust">
    <div>
        <h2>มั่นใจทุกการซื้อขายที่ “วนวรรณ”</h2>
        <p class="trust-box__intro">เรารับรองความปลอดภัยและคุณภาพของหนังสือมือสองทุกเล่มผ่านระบบการตรวจสอบและมาตรการป้องกันที่เข้มงวด</p>
        <div class="trust-box__grid">
            <div class="trust-box__item">
                <span class="trust-box__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v5c0 4.6 2.7 7.7 7 10 4.3-2.3 7-5.4 7-10V6l-7-3Z"/><path d="m8.8 12 2.1 2.1 4.5-4.6"/></svg></span>
                <div><strong>ระบบชำระเงินปลอดภัย</strong><span>เงินจะถูกโอนให้ผู้ขายต่อเมื่อคุณได้รับของแล้วเท่านั้น</span></div>
            </div>
            <div class="trust-box__item">
                <span class="trust-box__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4 8-4v10l-8 4-8-4V7Z"/><path d="m9.5 15 1.6 1.6 3.5-3.6"/></svg></span>
                <div><strong>การันตีสภาพหนังสือ</strong><span>สามารถคืนเงินได้ 100% หากหนังสือไม่ตรงตามสภาพที่ระบุ</span></div>
            </div>
            <div class="trust-box__item">
                <span class="trust-box__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><path d="M4 14a2 2 0 0 1 2-2h1v6H6a2 2 0 0 1-2-2v-2ZM20 14a2 2 0 0 0-2-2h-1v6h1a2 2 0 0 0 2-2v-2Z"/><path d="M17 18c-.8 2-2.5 3-5 3"/></svg></span>
                <div><strong>บริการช่วยเหลือ 24/7</strong><span>ทีมงานพร้อมดูแลคุณในทุกขั้นตอนการสั่งซื้อ</span></div>
            </div>
            <div class="trust-box__item">
                <span class="trust-box__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M8 17H5l-3 3V7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v2"/><path d="M10 12a3 3 0 0 1 3-3h6a3 3 0 0 1 3 3v8l-3-3h-6a3 3 0 0 1-3-3v-2Z"/></svg></span>
                <div><strong>ระบบพูดคุยส่วนตัว</strong><span>สอบถามสภาพหนังสือและขอดูรูปเพิ่มเติมได้โดยตรง</span></div>
            </div>
        </div>
    </div>
    <div class="trust-box__shield" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3Z"/><circle cx="11" cy="11" r="3"/><path d="m13.5 13.5 3 3"/></svg>
    </div>
</section>
