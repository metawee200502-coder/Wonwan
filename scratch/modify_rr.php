<?php
$file = 'C:\laragon\www\wanawan_project\pages\return_request.php';
$content = file_get_contents($file);

// Replace header and body start with modal layout
$content = preg_replace(
    '#<header class="rr-header">.*?</header>#is',
    '<div class="modal-overlay">
    <div class="modal-content">
        <a href="/pages/orders.php" class="modal-close" title="ปิด">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </a>
        <div class="modal-header-title">
            <h2>ขอคืนสินค้า / คืนเงิน</h2>
            <p>คำสั่งซื้อ #<?= htmlspecialchars($order[\'order_number\']) ?></p>
        </div>',
    $content
);

// Close modal tags before footer
$content = preg_replace(
    '#<div class="toast-msg" id="toast"></div>#is',
    '    </div>
</div>
<div class="toast-msg" id="toast"></div>',
    $content
);

// Remove footer inclusion since it\'s a popup (we don\'t want footer)
$content = preg_replace('#<\?php include __DIR__ \. \'/../includes/footer\.php\'; \?>#is', '', $content);
$content = str_replace('</body>', '', $content);
$content = str_replace('</html>', '</body></html>', $content);

// Add modal CSS and use color.css variables
$css_add = <<<'CSS'
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex; align-items: flex-start; justify-content: center;
            z-index: 9999; padding: 40px 20px;
            backdrop-filter: blur(5px);
            overflow-y: auto;
        }
        .modal-content {
            background: var(--bg-color);
            border-radius: 20px;
            width: 100%; max-width: 900px;
            position: relative;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin: auto;
            animation: modalFadeIn 0.3s ease-out;
        }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-close {
            position: absolute; top: 20px; right: 20px;
            background: #fff; width: 36px; height: 36px; border-radius: 50%;
            display: grid; place-items: center; color: var(--text-dark);
            text-decoration: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: 0.2s; z-index: 10;
        }
        .modal-close:hover { transform: scale(1.05); color: var(--accent-red); }
        .modal-header-title {
            padding: 24px 32px 0;
            text-align: center;
        }
        .modal-header-title h2 { color: var(--secondary-color); font-size: 24px; font-weight: 700; margin-bottom: 4px; }
        .modal-header-title p { color: var(--text-muted); font-size: 14px; }
        
        .rr-wrap { padding: 24px 32px 40px; }
        body { background: #f8f9fa; }
        
        /* Apply colors from styles.css */
        .card { background: var(--card-bg); border-color: var(--border-color); }
        .card-title { color: var(--secondary-color); border-bottom-color: var(--border-color); }
        .card-title .icon { color: var(--primary-color); background: var(--accent-pink-light) !important; }
        .item-title { color: var(--text-dark); }
        .item-price { color: var(--secondary-color); }
        .addr-box { background: var(--card-peach); border-color: var(--border-color); }
        .addr-box .addr-warning { color: var(--accent-red); }
        .addr-box .addr-title { color: var(--text-dark); }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(201,123,139,.15); }
        .upload-zone { border-color: var(--primary-color); background: #fff; }
        .upload-zone:hover, .upload-zone.drag { border-color: var(--primary-hover); background: var(--accent-pink-light); }
        .upload-zone .upload-icon { color: var(--primary-color); }
        .btn-submit { background: var(--primary-color); box-shadow: 0 4px 15px rgba(201,123,139,.3); }
        .btn-submit:hover { background: var(--primary-hover); opacity: 1; }
        .summary-total .amount { color: var(--secondary-color); }
        .alert-info { background: #eef7f9; color: var(--secondary-color); border-color: #bee1e8; }
        .status-pending { background: #fdf5e6; color: #d99a27; border-color: #fce3b3; }
        .status-approved { background: #eaf5e6; color: #47733e; border-color: #cde6c5; }
        .status-rejected { background: #fbecec; color: #b45d5d; border-color: #f1c9c9; }
        .btn-back { background: var(--secondary-color); }
        .btn-back:hover { background: #0f3e4a; }
CSS;

$content = preg_replace('#<style>#', "<style>\n" . $css_add, $content);

// Replace emojis with SVGs
$emojis = [
    '📋' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14h6"/><path d="M9 10h6"/></svg>',
    '✅' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    '❌' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    '📦' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
    '📍' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
    '📝' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',
    '🔧' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-3px"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
    '📷' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-3px"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
    '🖼️' => '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
    '💰' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    '📮' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    '⚠️' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-3px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    '⏳' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 15 15"/></svg>'
];

$content = str_replace(array_keys($emojis), array_values($emojis), $content);

// Small fix for btn-submit JS text (which uses ✅ and ⏳)
$content = str_replace(
    ["btn.textContent = '⏳ กำลังส่งคำขอ…';", "btn.textContent = '✅ ยืนยันการขอคืนสินค้า';"],
    ["btn.innerHTML = '⏳ กำลังส่งคำขอ…';", "btn.innerHTML = '✅ ยืนยันการขอคืนสินค้า';"],
    $content
);
$content = str_replace(
    ["'⏳ กำลังส่งคำขอ…'", "'✅ ยืนยันการขอคืนสินค้า'"],
    ["'<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\" style=\"vertical-align:-4px\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><polyline points=\"12 6 12 12 15 15\"/></svg> กำลังส่งคำขอ…'", "'<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"18\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\" style=\"vertical-align:-4px\"><path d=\"M22 11.08V12a10 10 0 1 1-5.93-9.14\"/><polyline points=\"22 4 12 14.01 9 11.01\"/></svg> ยืนยันการขอคืนสินค้า'"],
    $content
);

// Final fix for btn-submit HTML content (replace the actual HTML label)
$content = preg_replace(
    '#<button type="submit" class="btn-submit" id="submit-btn">\s*<svg.*?</svg> ยืนยันการขอคืนสินค้า\s*</button>#is',
    '<button type="submit" class="btn-submit" id="submit-btn" style="display:flex;align-items:center;justify-content:center;gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        ยืนยันการขอคืนสินค้า
    </button>',
    $content
);

file_put_contents($file, $content);
echo "Modifications applied successfully.\n";
?>
