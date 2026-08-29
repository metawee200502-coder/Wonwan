/**
 * assets/js/location_distance.js
 *
 * 1. ขอ Geolocation จากบราวเซอร์ของผู้ดู
 * 2. รวบรวม book_id / exchange_id จากการ์ดในหน้า
 * 3. เรียก /process/get_book_distances.php
 * 4. Inject ป้าย "📍 จังหวัด · ~X กม." ลงในการ์ดแต่ละใบ
 *
 * ไม่บังคับ — ถ้าผู้ใช้ไม่ยอมให้ permission ก็แสดงแค่ชื่อจังหวัดจาก DB เท่านั้น
 */
(function () {
    'use strict';

    const API = '/process/get_book_distances.php';
    const TIMEOUT_MS = 10000;

    // ── ดึง ID จากการ์ด ─────────────────────────────────────────────────────
    function collectIds() {
        const bookIds     = [];
        const exchangeIds = [];
        document.querySelectorAll('[data-book-id]').forEach(el => {
            const id = parseInt(el.dataset.bookId, 10);
            if (id > 0) bookIds.push(id);
        });
        document.querySelectorAll('[data-exchange-id]').forEach(el => {
            const id = parseInt(el.dataset.exchangeId, 10);
            if (id > 0) exchangeIds.push(id);
        });
        return { bookIds, exchangeIds };
    }

    // ── สร้าง query string สำหรับ array parameter ──────────────────────────
    function buildParams(lat, lng, bookIds, exchangeIds) {
        const p = new URLSearchParams({ lat, lng });
        bookIds.forEach(id => p.append('book_ids[]', id));
        exchangeIds.forEach(id => p.append('exchange_ids[]', id));
        return p.toString();
    }

    // ── Inject ป้ายตำแหน่งลงใน footer (clean — ไม่มีกรอบ) ──────────────────────
    function injectDistances(data) {
        const PIN_SVG = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;color:var(--primary-color);opacity:.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>`;

        Object.entries(data).forEach(([key, info]) => {
            const [prefix, rawId] = key.split('_');
            let els = [];
            if (prefix === 'b') {
                els = Array.from(document.querySelectorAll(`[data-book-id="${rawId}"]`));
            } else if (prefix === 'e') {
                els = Array.from(document.querySelectorAll(`[data-exchange-id="${rawId}"]`));
            }
            if (!els.length) return;

            const province = info.province || '';
            const label    = info.label   || '';
            if (!province && !label) return;

            let text = '';
            if (province && label) {
                text = `${province} · ~${label}`;
            } else if (province) {
                text = province;
            } else {
                text = `~${label}`;
            }

            els.forEach(el => {
                // 1. ถ้าเป็นหน้า detail (book_detail.php)
                if (el.classList.contains('product-detail-loc')) {
                    const span = el.querySelector('.loc-text');
                    if (span) span.textContent = text;
                    el.style.display = 'inline-flex'; // เผื่อถูกซ่อนไว้ตอนแรกถ้าไม่มีข้อมูล
                    return;
                }

                // 2. ถ้าเป็นการ์ดหนังสือปกติ
                // ลบ badge เก่าก่อน (ป้องกัน inject ซ้ำ)
                el.querySelectorAll('.loc-distance-badge, .loc-province-badge').forEach(b => b.remove());

                const footer = el.querySelector('.book-card__footer');
                if (!footer) return;

                const badge = document.createElement('span');
                badge.className = 'loc-distance-badge loc-province-badge book-card__footer-loc';
                badge.innerHTML = PIN_SVG + '<span class="book-card__footer-loc-text">' + text + '</span>';

                // แทรกเป็น first child (ซ้ายสุด) ก่อน label "มือสอง"
                footer.insertBefore(badge, footer.firstChild);
            });
        });
    }

    // ── Main ────────────────────────────────────────────────────────────────
    function init() {
        const { bookIds, exchangeIds } = collectIds();
        if (!bookIds.length && !exchangeIds.length) return;

        if (!navigator.geolocation) return;

        navigator.geolocation.getCurrentPosition(
            function onSuccess(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;

                const qs = buildParams(lat, lng, bookIds, exchangeIds);
                fetch(`${API}?${qs}`)
                    .then(r => r.ok ? r.json() : Promise.reject('http_error'))
                    .then(data => injectDistances(data))
                    .catch(() => { /* silent — province badges still show from PHP */ });
            },
            function onError() {
                /* ผู้ใช้ปฏิเสธ / error — แสดงแค่ชื่อจังหวัดจาก PHP ก็พอ */
            },
            { timeout: TIMEOUT_MS, maximumAge: 300000, enableHighAccuracy: false }
        );
    }

    // รอ DOM พร้อม
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
