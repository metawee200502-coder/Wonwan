<?php
// Ensure this widget is only rendered once per page load
if (defined('WANAWAN_LIVE_CHAT_WIDGET_LOADED')) {
    return;
}
define('WANAWAN_LIVE_CHAT_WIDGET_LOADED', true);

$currentUserName = $_SESSION['display_name'] ?? ($_SESSION['name'] ?? 'ผู้ใช้งาน');
$currentUserRole = $_SESSION['role'] ?? 'guest';
$isSuperAdmin = ($currentUserRole === 'super_admin');
$isSeller = in_array($currentUserRole, ['shop_admin', 'publisher_admin'], true);
?>
<!-- Wanawan Live Support Floating Widget -->
<div id="wanawan-live-chat-root">
    <!-- Re-open pill (shown if floating mascot is dismissed) -->
    <div id="wanawan-chat-reopen" class="wanawan-reopen-pill" style="display: none;" onclick="wanawanChat.showMascot()">
        <span class="wanawan-reopen-icon">💬</span>
        <span>ช่วยเหลือ / แชทสด</span>
    </div>

    <!-- Floating Circular Mascot Widget -->
    <div id="wanawan-floating-widget" class="wanawan-floating-container">
        <!-- Close (X) button on top of mascot -->
        <button class="wanawan-mascot-dismiss" onclick="event.stopPropagation(); wanawanChat.dismissMascot();" title="ปิดปุ่มช่วยเหลือ" aria-label="ปิด">
            ✕
        </button>

        <!-- Circular Mascot Badge -->
        <div class="wanawan-mascot-badge" onclick="wanawanChat.toggleChat()">
            <img src="/assets/image/nong_pink_support.jpg" alt="น้องพิงค์ ดูแลด้วยใจ" class="wanawan-mascot-img" onerror="this.src='/assets/image/minilogo.png'">
            <span class="wanawan-unread-badge" id="wanawan-unread-count" style="display: none;">1</span>
            <span class="wanawan-online-pulse"></span>
        </div>
        <div class="wanawan-mascot-tooltip">
            <?= $isSeller ? 'น้องพิงค์ • ช่วยเหลือร้านค้า & ติดต่อ Super Admin' : 'น้องพิงค์ • ดูแลด้วยใจ พร้อมรับฟัง' ?>
        </div>
    </div>

    <!-- Live Chat Modal Window -->
    <div id="wanawan-chat-modal" class="wanawan-chat-window" style="display: none;">
        <!-- Header -->
        <div class="wanawan-chat-header">
            <div class="wanawan-chat-header-info">
                <div class="wanawan-header-avatar-wrap">
                    <img src="/assets/image/nong_pink_support.jpg" alt="น้องพิงค์" class="wanawan-header-avatar" onerror="this.src='/assets/image/minilogo.png'">
                    <span class="wanawan-header-dot" id="wanawan-header-status-dot"></span>
                </div>
                <div>
                    <h3 class="wanawan-header-title">วนวรรณ Live Support</h3>
                    <div class="wanawan-header-status" id="wanawan-header-status-text">
                        <span class="wanawan-status-indicator">🌸</span> น้องพิงค์ (AI Support) • ดูแลด้วยใจ
                    </div>
                </div>
            </div>
            <div class="wanawan-header-actions">
                <button class="wanawan-btn-icon" onclick="wanawanChat.toggleChat()" title="ย่อหน้าต่าง">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </button>
            </div>
        </div>

        <!-- Queue Banner (Dynamic) -->
        <div id="wanawan-queue-banner" class="wanawan-queue-banner" style="display: none;">
            <div class="wanawan-queue-spinner"></div>
            <div class="wanawan-queue-info">
                <strong>อยู่ในคิวรอ Super Admin: <span id="wanawan-queue-pos">ลำดับที่ 1</span></strong>
                <p id="wanawan-queue-subtext">เจ้าหน้าที่ได้รับแจ้งแล้ว กรุณารอสักครู่ กำลังเตรียมเชื่อมต่อ...</p>
            </div>
            <button type="button" class="wanawan-btn-cancel-queue" onclick="wanawanChat.cancelQueue()" title="ยกเลิกการรอคิว">
                ✕ ยกเลิกคิว
            </button>
        </div>

        <!-- Super Admin Connected Announcement Banner -->
        <div id="wanawan-admin-connected-banner" class="wanawan-admin-connected-banner" style="display: none;">
            <div class="wanawan-admin-shield">🛡️</div>
            <div>
                <strong>กำลังสนทนากับ Super Admin: <span id="wanawan-current-admin-name">Admin</span></strong>
                <p>เจ้าหน้าที่เข้าร่วมห้องสนทนาเรียบร้อยแล้ว สอบถามได้เลยครับ</p>
            </div>
        </div>

        <!-- Suggested FAQ Chips -->
        <div class="wanawan-faq-chips" id="wanawan-faq-container">
            <button class="wanawan-chip wanawan-chip-admin" onclick="wanawanChat.requestAdmin()">
                ⭐ คุยกับ Super Admin
            </button>
            <?php if ($isSeller): ?>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('ข้อมูลเกี่ยวกับเว็บวนวรรณ')">
                    🌿 เกี่ยวกับเว็บวนวรรณ
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('ข้อมูลค่า GP ร้านค้า 15%')">
                    💰 ค่า GP ร้านค้า 15%
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('การดาวน์โหลดรายงานยอดขาย Excel')">
                    📊 โหลดรายงาน Excel
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('การจัดการคลังสินค้าและเพิ่มหนังสือ')">
                    📚 จัดการสต็อก/เพิ่มหนังสือ
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('รายได้และถอนเงินเข้าบัญชี')">
                    💳 รายได้และถอนเงิน
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('คำขอคืนสินค้าและการคืนเงิน')">
                    🛡️ คำขอคืนสินค้า
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('ที่อยู่สำหรับส่งคืนสินค้า')">
                    📍 ที่อยู่ส่งคืนสินค้า
                </button>
            <?php else: ?>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('เว็บวนวรรณคืออะไร')">
                    🌿 วนวรรณคืออะไร
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('เว็บนี้ปลอดภัยไหม ระบบ Escrow')">
                    🛡️ ปลอดภัยมั้ย (Escrow)
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('วิธีสั่งซื้อหนังสือ')">
                    🛒 วิธีสั่งซื้อ
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('การจัดส่งและติดตามพัสดุ')">
                    🚚 ติดตามพัสดุ
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('ระบบแลกเปลี่ยนหนังสือ')">
                    🔄 ลานแลกเปลี่ยน
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('วิธีชำระเงินและสลิป')">
                    💳 วิธีชำระเงิน
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('ข้อมูลการสมัครสมาชิกและเข้าสู่ระบบ')">
                    👤 สมัครสมาชิก/บัญชี
                </button>
                <button class="wanawan-chip" onclick="wanawanChat.sendQuick('เวลาทำการและช่องทางติดต่อ')">
                    ⏰ เวลาทำการ/ติดต่อเว็บ
                </button>
            <?php endif; ?>
        </div>


        <!-- Message List -->
        <div class="wanawan-chat-body" id="wanawan-chat-messages">
            <!-- Messages populated via JS -->
            <div class="wanawan-chat-loading">กำลังโหลดข้อความ...</div>
        </div>

        <!-- Footer / Input Area -->
        <div class="wanawan-chat-footer">
            <div class="wanawan-chat-actions-bar">
                <button type="button" class="wanawan-action-btn-escalate" id="wanawan-escalate-btn" onclick="wanawanChat.requestAdmin()">
                    <span>🛎️</span> ขอคุยกับ Super Admin
                </button>
                <button type="button" class="wanawan-action-btn-cancel" id="wanawan-cancel-queue-btn" style="display: none;" onclick="wanawanChat.cancelQueue()">
                    <span>✕</span> ยกเลิกการรอคิว
                </button>
                <span class="wanawan-chat-privacy">ปลอดภัย & เป็นส่วนตัว</span>
            </div>
            <form class="wanawan-chat-form" id="wanawan-chat-form" onsubmit="event.preventDefault(); wanawanChat.sendMessage();">
                <input type="text" id="wanawan-chat-input" class="wanawan-chat-input" placeholder="พิมพ์ข้อความที่ต้องการสอบถาม..." autocomplete="off">
                <button type="submit" id="wanawan-chat-send-btn" class="wanawan-chat-send-btn" title="ส่งข้อความ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
/* ========================================================
   WANAWAN LIVE SUPPORT FLOATING WIDGET STYLES
   ======================================================== */
#wanawan-live-chat-root {
    font-family: 'Prompt', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 999999;
}

/* Reopen Pill (when mascot is dismissed) */
.wanawan-reopen-pill {
    background: linear-gradient(135deg, #b05467, #C97B8B);
    color: #fff;
    padding: 8px 16px;
    border-radius: 50px;
    box-shadow: 0 4px 18px rgba(201, 123, 139, 0.35);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.wanawan-reopen-pill:hover {
    transform: translateY(-3px) scale(1.04);
    box-shadow: 0 6px 22px rgba(201, 123, 139, 0.45);
}

/* Floating Container */
.wanawan-floating-container {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Dismiss (X) button */
.wanawan-mascot-dismiss {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 24px;
    height: 24px;
    background: #ffffff;
    color: #555555;
    border: 1.5px solid #dcdcdc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
    transition: all 0.2s ease;
    z-index: 10;
}
.wanawan-mascot-dismiss:hover {
    background: #ff5252;
    color: #ffffff;
    border-color: #ff5252;
    transform: scale(1.15) rotate(90deg);
}

/* Circular Mascot Badge */
.wanawan-mascot-badge {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #ffffff;
    padding: 2px;
    box-shadow: 0 10px 28px rgba(201, 123, 139, 0.32), 0 4px 12px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    border: 3px solid #f6b5c4;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.wanawan-mascot-badge:hover {
    transform: translateY(-5px) scale(1.06);
    box-shadow: 0 14px 34px rgba(201, 123, 139, 0.45), 0 6px 16px rgba(0, 0, 0, 0.12);
    border-color: #C97B8B;
}
.wanawan-mascot-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    object-position: center 36%;
    transform: scale(1.24);
    display: block;
}

/* Online Pulse indicator */
.wanawan-online-pulse {
    position: absolute;
    bottom: 2px;
    right: 4px;
    width: 15px;
    height: 15px;
    background: #4caf50;
    border: 2.5px solid #fff;
    border-radius: 50%;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.4);
    animation: wanawanPulse 2s infinite;
}
@keyframes wanawanPulse {
    0% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.6); }
    70% { box-shadow: 0 0 0 8px rgba(76, 175, 80, 0); }
    100% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
}

/* Unread Badge */
.wanawan-unread-badge {
    position: absolute;
    top: -2px;
    left: -2px;
    background: #e53935;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 10px;
    border: 2px solid #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: wanawanBounce 1s infinite alternate;
}
@keyframes wanawanBounce {
    from { transform: translateY(0); }
    to { transform: translateY(-3px); }
}

/* Tooltip */
.wanawan-mascot-tooltip {
    position: absolute;
    bottom: -28px;
    background: rgba(33, 33, 33, 0.88);
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    padding: 4px 10px;
    border-radius: 20px;
    white-space: nowrap;
    opacity: 0;
    transform: translateY(-5px);
    transition: all 0.2s ease;
    pointer-events: none;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
}
.wanawan-floating-container:hover .wanawan-mascot-tooltip {
    opacity: 1;
    transform: translateY(0);
}

/* ========================================================
   CHAT WINDOW
   ======================================================== */
.wanawan-chat-window {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 390px;
    max-width: calc(100vw - 32px);
    height: 600px;
    max-height: calc(100vh - 48px);
    background: #ffffff;
    border-radius: 22px;
    box-shadow: 0 16px 45px rgba(0, 0, 0, 0.18), 0 4px 16px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.08);
    animation: wanawanSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1000000;
}
@keyframes wanawanSlideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Chat Header */
.wanawan-chat-header {
    background: linear-gradient(135deg, #87384a, #C97B8B 60%, #e28fa0);
    color: #ffffff;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 10px rgba(0,0,0,0.12);
}
.wanawan-chat-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.wanawan-header-avatar-wrap {
    position: relative;
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    overflow: hidden;
    border-radius: 50%;
    border: 2px solid #ffffff;
}
.wanawan-header-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    object-position: center 36%;
    transform: scale(1.28);
    background: #fff;
}
.wanawan-header-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #4caf50;
    border: 2px solid #fff;
}
.wanawan-header-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.2px;
}
.wanawan-header-status {
    font-size: 12px;
    color: #e8f5e9;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
}
.wanawan-header-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}
.wanawan-btn-icon {
    background: rgba(255, 255, 255, 0.16);
    border: none;
    color: #ffffff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s;
}
.wanawan-btn-icon:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Queue Banner */
.wanawan-queue-banner {
    background: linear-gradient(135deg, #fff8e1, #ffecb3);
    border-bottom: 1px solid #ffe082;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #795548;
}
.wanawan-queue-spinner {
    width: 22px;
    height: 22px;
    border: 3px solid #ffb300;
    border-top-color: transparent;
    border-radius: 50%;
    animation: wanawanSpin 0.9s linear infinite;
    flex-shrink: 0;
}
@keyframes wanawanSpin {
    to { transform: rotate(360deg); }
}
.wanawan-queue-info strong {
    display: block;
    font-size: 13px;
    color: #d84315;
}
.wanawan-queue-info p {
    margin: 2px 0 0;
    font-size: 11px;
    color: #6d4c41;
}
.wanawan-btn-cancel-queue {
    margin-left: auto;
    background: #ffffff;
    border: 1px solid #fca5a5;
    color: #dc2626;
    padding: 5px 11px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    flex-shrink: 0;
}
.wanawan-btn-cancel-queue:hover {
    background: #fee2e2;
    border-color: #ef4444;
    transform: scale(1.02);
}

/* Admin Connected Banner */
.wanawan-admin-connected-banner {
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    border-bottom: 1px solid #a5d6a7;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1b5e20;
}
.wanawan-admin-shield {
    font-size: 22px;
    flex-shrink: 0;
}
.wanawan-admin-connected-banner strong {
    display: block;
    font-size: 13px;
    color: #1b5e20;
}
.wanawan-admin-connected-banner p {
    margin: 2px 0 0;
    font-size: 11px;
    color: #2e7d32;
}

/* FAQ Chips */
.wanawan-faq-chips {
    padding: 10px 14px;
    background: #fbfbfb;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    gap: 6px;
    overflow-x: auto;
    white-space: nowrap;
    scrollbar-width: thin;
}
.wanawan-faq-chips::-webkit-scrollbar {
    height: 4px;
}
.wanawan-faq-chips::-webkit-scrollbar-thumb {
    background: #e0e0e0;
    border-radius: 4px;
}
.wanawan-chip {
    background: #ffffff;
    border: 1px solid #dedede;
    color: #424242;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.wanawan-chip:hover {
    background: #e8f5e9;
    color: #2e7d32;
    border-color: #81c784;
    transform: translateY(-1px);
}
.wanawan-chip-admin {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    border-color: #ffb74d;
    color: #e65100;
    font-weight: 600;
}
.wanawan-chip-admin:hover {
    background: linear-gradient(135deg, #ffe0b2, #ffcc80);
    border-color: #ffa726;
    color: #bf360c;
}

/* Chat Body */
.wanawan-chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: #f7f9f6;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.wanawan-chat-loading {
    text-align: center;
    color: #9e9e9e;
    font-size: 13px;
    margin: auto;
}

/* Message Bubbles */
.wanawan-msg {
    display: flex;
    flex-direction: column;
    max-width: 82%;
    animation: wanawanFadeIn 0.25s ease;
}
@keyframes wanawanFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

.wanawan-msg-sender {
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 3px;
    padding: 0 4px;
}
.wanawan-msg-text {
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 13.5px;
    line-height: 1.5;
    word-break: break-word;
    white-space: pre-line;
}
.wanawan-msg-time {
    font-size: 10px;
    color: #9e9e9e;
    margin-top: 3px;
    padding: 0 4px;
}

/* User Message (Right) */
.wanawan-msg-user {
    align-self: flex-end;
}
.wanawan-msg-user .wanawan-msg-sender {
    text-align: right;
    color: #6d4c41;
}
.wanawan-msg-user .wanawan-msg-text {
    background: linear-gradient(135deg, #b05467, #C97B8B);
    color: #ffffff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 2px 8px rgba(201, 123, 139, 0.2);
}
.wanawan-msg-user .wanawan-msg-time {
    text-align: right;
}

/* Bot Message (Left) */
.wanawan-msg-bot {
    align-self: flex-start;
}
.wanawan-msg-bot .wanawan-msg-sender {
    color: #b05467;
    display: flex;
    align-items: center;
    gap: 4px;
}
.wanawan-msg-bot .wanawan-msg-text {
    background: #ffffff;
    color: #2c3e50;
    border: 1px solid #e0e0e0;
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.wanawan-bot-escalate-wrap {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px dashed #f8bbd0;
    display: flex;
}
.wanawan-bot-escalate-btn {
    background: linear-gradient(135deg, #d84315, #f4511e);
    color: #ffffff;
    border: none;
    border-radius: 18px;
    padding: 6px 14px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 2px 8px rgba(216, 67, 21, 0.25);
    transition: all 0.2s ease;
}
.wanawan-bot-escalate-btn:hover {
    background: linear-gradient(135deg, #bf360c, #d84315);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(216, 67, 21, 0.35);
}

/* Super Admin Message (Left - Highlighted) */
.wanawan-msg-admin {
    align-self: flex-start;
}
.wanawan-msg-admin .wanawan-msg-sender {
    color: #b71c1c;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 4px;
}
.wanawan-admin-badge {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 10px;
    font-weight: 700;
}
.wanawan-msg-admin .wanawan-msg-text {
    background: linear-gradient(135deg, #ffffff, #fff8f8);
    color: #212121;
    border: 1.5px solid #ffcdd2;
    border-left: 4px solid #e53935;
    border-bottom-left-radius: 4px;
    box-shadow: 0 3px 10px rgba(229, 57, 53, 0.1);
}

/* System Message (Center) */
.wanawan-msg-system {
    align-self: center;
    max-width: 92%;
    text-align: center;
}
.wanawan-msg-system .wanawan-msg-text {
    background: #fff9c4;
    color: #795548;
    border: 1px solid #fff176;
    border-radius: 12px;
    font-size: 12px;
    padding: 8px 14px;
}

/* Session Closed Card */
.wanawan-session-closed-card {
    align-self: center;
    width: 90%;
    margin: 12px auto 8px;
    background: #fdf2f4;
    border: 1.5px dashed #f1a5b8;
    border-radius: 14px;
    padding: 12px 14px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(201, 123, 139, 0.08);
    animation: wanawanFadeIn 0.3s ease;
}
.wanawan-closed-title {
    font-size: 12.5px;
    font-weight: 700;
    color: #9c3b52;
    margin-bottom: 4px;
}
.wanawan-closed-subtitle {
    font-size: 11px;
    color: #7a5860;
    margin-bottom: 10px;
    line-height: 1.4;
}
.wanawan-btn-restart-chat {
    background: linear-gradient(135deg, #b05467, #C97B8B);
    color: #ffffff;
    border: none;
    border-radius: 20px;
    padding: 6px 16px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(201, 123, 139, 0.25);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.wanawan-btn-restart-chat:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 14px rgba(201, 123, 139, 0.35);
}

/* Chat Footer */
.wanawan-chat-footer {
    padding: 10px 14px 14px;
    background: #ffffff;
    border-top: 1px solid #f0f0f0;
}
.wanawan-chat-actions-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.wanawan-action-btn-escalate {
    background: none;
    border: none;
    color: #e65100;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 6px;
    transition: background 0.2s;
}
.wanawan-action-btn-escalate:hover {
    background: #fff3e0;
}
.wanawan-action-btn-cancel {
    background: none;
    border: none;
    color: #dc2626;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 6px;
    transition: background 0.2s;
}
.wanawan-action-btn-cancel:hover {
    background: #fee2e2;
}
.wanawan-chat-privacy {
    font-size: 10px;
    color: #9e9e9e;
}
.wanawan-chat-form {
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
}
.wanawan-chat-input {
    flex: 1;
    border: 1.5px solid #e0e0e0;
    border-radius: 25px;
    padding: 10px 16px;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s;
    background: #fdfdfd;
}
.wanawan-chat-input:focus {
    border-color: #C97B8B;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(201, 123, 139, 0.15);
}
.wanawan-chat-send-btn {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #b05467, #C97B8B);
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(201, 123, 139, 0.25);
    transition: all 0.2s;
    flex-shrink: 0;
}
.wanawan-chat-send-btn:hover {
    transform: scale(1.06);
    background: linear-gradient(135deg, #913c4f, #b05467);
}
.wanawan-chat-send-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    box-shadow: none;
}

/* Mobile Responsiveness */
@media (max-width: 480px) {
    #wanawan-live-chat-root {
        bottom: 16px;
        right: 16px;
    }
    .wanawan-mascot-badge {
        width: 66px;
        height: 66px;
    }
    .wanawan-chat-window {
        width: 100vw;
        height: 100vh;
        max-width: 100vw;
        max-height: 100vh;
        bottom: 0;
        right: 0;
        border-radius: 0;
    }
}
</style>

<script>
window.wanawanChat = (function() {
    let isOpen = false;
    let isDismissed = false;
    let sessionId = null;
    let chatStatus = 'bot'; // 'bot' | 'queued' | 'active' | 'closed'
    let assignedAdminName = null;
    let queuePosition = 0;
    let queueAhead = 0;
    let queueTotal = 0;
    let lastMessageId = 0;
    let pollInterval = null;

    // Check if dismissed in this session
    if (sessionStorage.getItem('wanawan_chat_dismissed') === 'true') {
        isDismissed = true;
    }

    function initUI() {
        const floatWidget = document.getElementById('wanawan-floating-widget');
        const reopenPill = document.getElementById('wanawan-chat-reopen');
        if (isDismissed) {
            floatWidget.style.display = 'none';
            reopenPill.style.display = 'flex';
        } else {
            floatWidget.style.display = 'flex';
            reopenPill.style.display = 'none';
        }
    }

    // Play subtle soft notification chime using Web Audio API
    function playChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.35);
        } catch (e) {}
    }

    function renderClosedSessionCard() {
        const container = document.getElementById('wanawan-chat-messages');
        if (!container || document.getElementById('wanawan-closed-session-card')) return;
        const card = document.createElement('div');
        card.id = 'wanawan-closed-session-card';
        card.className = 'wanawan-session-closed-card';
        card.innerHTML = `
            <div class="wanawan-closed-title">✨ บทสนทนานี้จบลงแล้ว (บันทึกประวัติเรียบร้อย)</div>
            <div class="wanawan-closed-subtitle">หากต้องการสอบถามเพิ่มเติม สามารถเริ่มต้นคุยกับน้องพิงค์ AI ได้ตลอดเวลาค่ะ 🌸</div>
            <button type="button" class="wanawan-btn-restart-chat" onclick="wanawanChat.startNewChat()">
                <span>🔄</span> เริ่มต้นการสนทนาใหม่
            </button>
        `;
        container.appendChild(card);
        container.scrollTop = container.scrollHeight;
    }

    async function initChat() {
        try {
            const res = await fetch('/api/live_support_api.php?action=init');
            const data = await res.json();
            if (data.success) {
                sessionId = data.session_id;
                chatStatus = data.status;
                assignedAdminName = data.assigned_admin_name;
                queuePosition = data.queue_position || 0;
                queueAhead = data.queue_ahead || 0;
                queueTotal = data.queue_total || 0;
                renderMessages(data.messages, true);
                updateStatusHeader();
                if (data.is_closed) {
                    renderClosedSessionCard();
                }
            }
        } catch (err) {
            console.error('Wanawan Live Chat init error:', err);
        }
    }

    async function startNewChat(firstText = null) {
        try {
            const res = await fetch('/api/live_support_api.php?action=start_new_session', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                sessionId = data.session_id;
                chatStatus = data.status;
                assignedAdminName = null;
                queuePosition = 0;
                queueAhead = 0;
                queueTotal = 0;
                lastMessageId = 0;
                const existingClosed = document.getElementById('wanawan-closed-session-card');
                if (existingClosed) existingClosed.remove();
                renderMessages(data.messages, true);
                updateStatusHeader();
                if (firstText) {
                    await sendMessage(firstText);
                }
            }
        } catch (err) {
            console.error('Error starting new session:', err);
        }
    }

    function updateStatusHeader() {
        const dot = document.getElementById('wanawan-header-status-dot');
        const text = document.getElementById('wanawan-header-status-text');
        const queueBanner = document.getElementById('wanawan-queue-banner');
        const adminBanner = document.getElementById('wanawan-admin-connected-banner');
        const queuePosSpan = document.getElementById('wanawan-queue-pos');
        const queueSubtext = document.getElementById('wanawan-queue-subtext');
        const currentAdminSpan = document.getElementById('wanawan-current-admin-name');
        const escalateBtn = document.getElementById('wanawan-escalate-btn');
        const cancelBtn = document.getElementById('wanawan-cancel-queue-btn');

        if (chatStatus === 'queued') {
            dot.style.background = '#ffb300';
            queueBanner.style.display = 'flex';
            adminBanner.style.display = 'none';
            if (escalateBtn) escalateBtn.style.display = 'none';
            if (cancelBtn) cancelBtn.style.display = 'inline-flex';

            const pos = Math.max(1, queuePosition || 1);
            const ahead = Math.max(0, queueAhead !== undefined ? queueAhead : (pos - 1));

            if (pos <= 1 || ahead <= 0) {
                text.innerHTML = '<span class="wanawan-status-indicator">⏳</span> รอรับสาย • ถึงคิวของคุณแล้ว (ลำดับที่ 1)';
                if (queuePosSpan) queuePosSpan.textContent = 'ลำดับที่ 1 (ถึงคิวของคุณแล้ว)';
                if (queueSubtext) queueSubtext.textContent = 'ถึงคิวของคุณแล้ว เจ้าหน้าที่กำลังเตรียมเข้าร่วมห้องสนทนาสักครู่ครับ...';
            } else {
                text.innerHTML = `<span class="wanawan-status-indicator">⏳</span> รอคิวรับสาย (ลำดับที่ ${pos} • รออีก ${ahead} คิว)`;
                if (queuePosSpan) queuePosSpan.textContent = `ลำดับที่ ${pos} (รออีก ${ahead} คิวก่อนหน้า)`;
                if (queueSubtext) queueSubtext.textContent = `มีคิวก่อนหน้าคุณ ${ahead} คิว (คิวทั้งหมด ${queueTotal || pos} คิว) เจ้าหน้าที่จะให้บริการตามลำดับเวลาจริงครับ`;
            }
        } else if (chatStatus === 'active' && assignedAdminName) {
            dot.style.background = '#4caf50';
            text.innerHTML = `<span class="wanawan-status-indicator">🟢</span> สนทนากับ Super Admin: <strong>${escapeHtml(assignedAdminName)}</strong>`;
            queueBanner.style.display = 'none';
            adminBanner.style.display = 'flex';
            if (currentAdminSpan) currentAdminSpan.textContent = assignedAdminName;
            if (escalateBtn) escalateBtn.style.display = 'none';
            if (cancelBtn) cancelBtn.style.display = 'none';
        } else if (chatStatus === 'closed') {
            dot.style.background = '#9e9e9e';
            text.innerHTML = '<span class="wanawan-status-indicator">⚪</span> สิ้นสุดการสนทนา (บันทึกประวัติแล้ว)';
            queueBanner.style.display = 'none';
            adminBanner.style.display = 'none';
            if (escalateBtn) escalateBtn.style.display = 'inline-flex';
            if (cancelBtn) cancelBtn.style.display = 'none';
        } else {
            // bot
            dot.style.background = '#4caf50';
            text.innerHTML = '<span class="wanawan-status-indicator">🌸</span> น้องพิงค์ (AI Support) • ดูแลด้วยใจ';
            queueBanner.style.display = 'none';
            adminBanner.style.display = 'none';
            if (escalateBtn) escalateBtn.style.display = 'inline-flex';
            if (cancelBtn) cancelBtn.style.display = 'none';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatMarkdown(str) {
        if (!str) return '';
        return str.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        try {
            const d = new Date(dateStr.replace(' ', 'T'));
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return '';
        }
    }

    function renderMessages(messages, isInitial = false) {
        const container = document.getElementById('wanawan-chat-messages');
        if (isInitial) {
            container.innerHTML = '';
        }

        let hasNewAdminMsg = false;

        messages.forEach(m => {
            const mid = parseInt(m.message_id, 10);
            if (mid > lastMessageId) lastMessageId = mid;

            const msgDiv = document.createElement('div');
            const type = m.sender_type;

            if (type === 'user') {
                msgDiv.className = 'wanawan-msg wanawan-msg-user';
                msgDiv.innerHTML = `
                    <div class="wanawan-msg-sender">คุณ</div>
                    <div class="wanawan-msg-text">${escapeHtml(m.message_text)}</div>
                    <div class="wanawan-msg-time">${formatTime(m.created_at)}</div>
                `;
            } else if (type === 'admin') {
                hasNewAdminMsg = true;
                msgDiv.className = 'wanawan-msg wanawan-msg-admin';
                msgDiv.innerHTML = `
                    <div class="wanawan-msg-sender">
                        <span>🛡️ ${escapeHtml(m.sender_name)}</span>
                        <span class="wanawan-admin-badge">Super Admin</span>
                    </div>
                    <div class="wanawan-msg-text">${escapeHtml(m.message_text)}</div>
                    <div class="wanawan-msg-time">${formatTime(m.created_at)}</div>
                `;
            } else if (type === 'system') {
                msgDiv.className = 'wanawan-msg wanawan-msg-system';
                msgDiv.innerHTML = `
                    <div class="wanawan-msg-text">${formatMarkdown(escapeHtml(m.message_text))}</div>
                `;
            } else {
                // bot
                const shouldSuggestAdmin = (chatStatus === 'bot') && (m.suggest_admin || /คุยกับ\s*Super\s*Admin/i.test(m.message_text));
                const escalateBtnHtml = shouldSuggestAdmin ? `
                    <div class="wanawan-bot-escalate-wrap">
                        <button type="button" class="wanawan-bot-escalate-btn" onclick="wanawanChat.requestAdmin()">
                            <span>🛎️</span> คุยกับ Super Admin (เข้าคิว)
                        </button>
                    </div>` : '';

                msgDiv.className = 'wanawan-msg wanawan-msg-bot';
                msgDiv.innerHTML = `
                    <div class="wanawan-msg-sender">🌸 ${escapeHtml(m.sender_name || 'น้องพิงค์ (AI Support)')}</div>
                    <div class="wanawan-msg-text">
                        ${formatMarkdown(escapeHtml(m.message_text))}
                        ${escalateBtnHtml}
                    </div>
                    <div class="wanawan-msg-time">${formatTime(m.created_at)}</div>
                `;
            }
            container.appendChild(msgDiv);
        });

        container.scrollTop = container.scrollHeight;

        if (hasNewAdminMsg && !isInitial) {
            playChime();
        }
    }

    async function poll() {
        if (!isOpen) return;
        try {
            const res = await fetch(`/api/live_support_api.php?action=poll&last_id=${lastMessageId}`);
            const data = await res.json();
            if (data.success) {
                const prevStatus = chatStatus;
                const prevQueuePos = queuePosition;
                const prevQueueAhead = queueAhead;

                chatStatus = data.status;
                queuePosition = data.queue_position || 0;
                queueAhead = data.queue_ahead || 0;
                queueTotal = data.queue_total || 0;
                assignedAdminName = data.assigned_admin_name;

                if (prevStatus !== chatStatus || (chatStatus === 'queued' && (prevQueuePos !== queuePosition || prevQueueAhead !== queueAhead))) {
                    updateStatusHeader();
                    if (chatStatus === 'closed') {
                        renderClosedSessionCard();
                    }
                }

                if (data.messages && data.messages.length > 0) {
                    renderMessages(data.messages, false);
                }
            }
        } catch (e) {}
    }

    async function sendMessage(textToSend = null) {
        const input = document.getElementById('wanawan-chat-input');
        const text = (textToSend !== null ? textToSend : input.value).trim();
        if (!text) return;

        if (textToSend === null) {
            input.value = '';
        }

        // If current session was closed, restart cleanly
        if (chatStatus === 'closed') {
            await startNewChat(text);
            return;
        }

        // Add user bubble optimistically
        renderMessages([{
            message_id: lastMessageId + 1,
            sender_type: 'user',
            sender_name: 'คุณ',
            message_text: text,
            created_at: new Date().toISOString()
        }]);

        try {
            const formData = new FormData();
            formData.append('text', text);
            const res = await fetch('/api/live_support_api.php?action=send_message', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                if (data.session_id && data.session_id !== sessionId) {
                    sessionId = data.session_id;
                    chatStatus = data.status || 'bot';
                    updateStatusHeader();
                    const existingClosed = document.getElementById('wanawan-closed-session-card');
                    if (existingClosed) existingClosed.remove();
                }
                if (data.bot_response) {
                    setTimeout(() => {
                        renderMessages([data.bot_response]);
                        playChime();
                    }, 400);
                }
            }
        } catch (err) {
            console.error('Error sending message:', err);
        }
    }

    async function requestAdmin() {
        if (chatStatus === 'queued') {
            alert('คำขอของคุณอยู่ในคิวรอเรียบร้อยแล้วครับ เจ้าหน้าที่กำลังจะมารับสาย');
            return;
        }
        if (chatStatus === 'active') {
            alert(`คุณกำลังสนทนากับ Super Admin: ${assignedAdminName} อยู่แล้วครับ`);
            return;
        }

        try {
            const res = await fetch('/api/live_support_api.php?action=request_admin', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                if (data.session_id) sessionId = data.session_id;
                chatStatus = data.status;
                queuePosition = data.queue_position || 1;
                queueAhead = data.queue_ahead || 0;
                queueTotal = data.queue_total || 1;
                updateStatusHeader();
                const existingClosed = document.getElementById('wanawan-closed-session-card');
                if (existingClosed) existingClosed.remove();
                if (data.system_message) {
                    renderMessages([{
                        message_id: lastMessageId + 1,
                        sender_type: 'system',
                        sender_name: 'ระบบวนวรรณ',
                        message_text: data.system_message,
                        created_at: new Date().toISOString()
                    }]);
                    playChime();
                }
            }
        } catch (err) {
            alert('ไม่สามารถเชื่อมต่อคิวแอดมินได้ กรุณาลองใหม่อีกครั้ง');
        }
    }

    async function cancelQueue() {
        if (!confirm('ต้องการยกเลิกการรอคิวติดต่อ Super Admin ใช่หรือไม่?')) return;
        try {
            const res = await fetch('/api/live_support_api.php?action=cancel_queue', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                chatStatus = 'bot';
                queuePosition = 0;
                queueAhead = 0;
                queueTotal = 0;
                updateStatusHeader();
                if (data.system_message) {
                    renderMessages([data.system_message]);
                    playChime();
                }
            }
        } catch (err) {
            alert('ไม่สามารถยกเลิกคิวได้ กรุณาลองใหม่อีกครั้ง');
        }
    }

    function toggleChat() {
        const modal = document.getElementById('wanawan-chat-modal');
        isOpen = !isOpen;
        if (isOpen) {
            modal.style.display = 'flex';
            document.getElementById('wanawan-unread-count').style.display = 'none';
            if (!sessionId) {
                initChat();
            } else {
                poll();
            }
            if (!pollInterval) {
                pollInterval = setInterval(poll, 2500);
            }
            setTimeout(() => {
                const inp = document.getElementById('wanawan-chat-input');
                if (inp) inp.focus();
            }, 100);
        } else {
            modal.style.display = 'none';
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }
    }

    function openChat() {
        isDismissed = false;
        sessionStorage.removeItem('wanawan_chat_dismissed');
        initUI();
        const modal = document.getElementById('wanawan-chat-modal');
        if (!isOpen) {
            isOpen = true;
            modal.style.display = 'flex';
            const unreadBadge = document.getElementById('wanawan-unread-count');
            if (unreadBadge) unreadBadge.style.display = 'none';
            if (!sessionId) {
                initChat();
            } else {
                poll();
            }
            if (!pollInterval) {
                pollInterval = setInterval(poll, 2500);
            }
            setTimeout(() => {
                const inp = document.getElementById('wanawan-chat-input');
                if (inp) inp.focus();
            }, 100);
        }
    }

    function dismissMascot() {
        isDismissed = true;
        sessionStorage.setItem('wanawan_chat_dismissed', 'true');
        initUI();
    }

    function showMascot() {
        openChat();
    }

    function sendQuick(text) {
        sendMessage(text);
    }

    // Auto init on page load
    document.addEventListener('DOMContentLoaded', () => {
        initUI();
        initChat();
    });

    return {
        openChat,
        startNewChat,
        toggleChat,
        dismissMascot,
        showMascot,
        sendMessage,
        sendQuick,
        requestAdmin,
        cancelQueue
    };
})();
</script>
<!-- End Wanawan Live Support Floating Widget -->
