<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

$active_menu = 'live_support';
$adminName = $_SESSION['display_name'] ?? 'Super Admin';
$adminId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์บริการแชทสด (Live Support) - วนวรรณ Admin</title>
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?= filemtime(__DIR__ . '/../assets/css/styles.css') ?>">
    <link rel="stylesheet" href="/assets/css/admin-sidebar.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin-sidebar.css') ?>">
    <style>
        body {
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
            font-family: 'Prompt', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .admin-main-wrap {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .support-container {
            display: grid;
            grid-template-columns: 360px 1fr;
            height: 100%;
            overflow: hidden;
            background: #ffffff;
        }

        /* Left Panel - Queue & Sessions List */
        .queue-panel {
            border-right: 1px solid #e0e6ed;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
        }

        .panel-header {
            padding: 20px;
            border-bottom: 1px solid #eef2f5;
            background: #fafbfc;
        }

        .panel-title {
            margin: 0 0 10px;
            font-size: 18px;
            font-weight: 700;
            color: #1a202c;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .status-badge-count {
            background: #e53935;
            color: #fff;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 700;
        }

        .filter-tabs {
            display: flex;
            gap: 6px;
            background: #eef2f6;
            padding: 4px;
            border-radius: 10px;
        }

        .filter-tab {
            flex: 1;
            padding: 7px 4px;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            color: #5a6a85;
            background: none;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-tab.active {
            background: #ffffff;
            color: #1b5e20;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .session-list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .session-card {
            padding: 14px;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .session-card:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            transform: translateY(-1px);
        }

        .session-card.active {
            border-color: #4caf50;
            background: #f1f8f3;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.12);
        }

        .session-card.queued-card {
            border-left: 4px solid #ff9800;
        }

        .session-card.closed-card {
            border-left: 4px solid #94a3b8;
            opacity: 0.85;
        }

        .status-pill {
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: 700;
        }
        .pill-closed  { background: #f1f5f9; color: #64748b; }
        .pill-bot     { background: #f0fdf4; color: #166534; }
        .pill-queued  { background: #fff7ed; color: #c2410c; }
        .pill-active  { background: #eff6ff; color: #1d4ed8; }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .card-name {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
        }

        .queue-rank-badge {
            background: #ea580c;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 10px;
            margin-right: 6px;
            display: inline-flex;
            align-items: center;
            letter-spacing: 0.3px;
            box-shadow: 0 1px 3px rgba(234, 88, 12, 0.25);
            flex-shrink: 0;
        }

        .card-role-tag {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .role-reader { background: #e0f2fe; color: #0369a1; }
        .role-shop_admin { background: #fef3c7; color: #b45309; }
        .role-publisher_admin { background: #fce7f3; color: #be185d; }
        .role-guest { background: #f1f5f9; color: #64748b; }

        .card-snippet {
            font-size: 12px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 6px;
        }

        .card-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #94a3b8;
        }

        .card-assigned {
            color: #059669;
            font-weight: 600;
        }

        .wait-pill {
            background: #fff7ed;
            color: #c2410c;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
        }

        /* Right Panel - Chat Stream */
        .chat-panel {
            display: flex;
            flex-direction: column;
            height: 100vh;
            max-height: 100vh;
            background: #f8fafc;
            overflow: hidden;
        }

        .chat-topbar {
            padding: 16px 24px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-headline {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1b5e20, #43a047);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
        }

        .user-title-group h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .user-title-group p {
            margin: 2px 0 0;
            font-size: 12px;
            color: #64748b;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-claim {
            background: linear-gradient(135deg, #2e7d32, #43a047);
            color: #ffffff;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(46, 125, 50, 0.25);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-claim:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.35);
        }

        .btn-ask-close {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-ask-close:hover { background: #ffedd5; }
        .btn-ask-close:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-close-chat {
            background: #ffffff;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-close-chat:hover {
            background: #fee2e2;
        }

        /* Auto-close countdown banner */
        .autoclose-banner {
            background: linear-gradient(135deg, #fff7ed, #ffedd5);
            border: 1px solid #fed7aa;
            color: #9a3412;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .autoclose-cancel {
            background: #ffffff;
            border: 1px solid #fdba74;
            color: #9a3412;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .autoclose-cancel:hover { background: #fff7ed; }

        /* Message Stream */
        .chat-stream {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .empty-chat-state {
            margin: auto;
            text-align: center;
            color: #94a3b8;
        }
        .empty-chat-state svg {
            width: 64px;
            height: 64px;
            stroke: #cbd5e1;
            margin-bottom: 12px;
        }

        .stream-bubble {
            max-width: 75%;
            display: flex;
            flex-direction: column;
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stream-bubble.user {
            align-self: flex-start;
        }
        .stream-bubble.user .bubble-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            padding: 12px 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }

        .stream-bubble.admin {
            align-self: flex-end;
        }
        .stream-bubble.admin .bubble-box {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #ffffff;
            border-radius: 16px;
            border-bottom-right-radius: 4px;
            padding: 12px 16px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
        }
        .stream-bubble.admin .bubble-author {
            text-align: right;
            color: #1e40af;
        }
        .stream-bubble.admin .bubble-time {
            text-align: right;
        }

        .stream-bubble.bot {
            align-self: flex-start;
        }
        .stream-bubble.bot .bubble-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            padding: 12px 16px;
        }

        .stream-bubble.system {
            align-self: center;
            max-width: 85%;
        }
        .stream-bubble.system .bubble-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            border-radius: 12px;
            font-size: 12.5px;
            text-align: center;
            padding: 8px 16px;
        }

        .bubble-author {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .bubble-box {
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-line;
            word-break: break-word;
        }
        .bubble-time {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* Message Composer */
        .chat-bottombar {
            padding: 16px 24px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
        }

        .composer-form {
            display: flex;
            gap: 12px;
        }

        .composer-input {
            flex: 1;
            padding: 12px 18px;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        .composer-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .composer-btn {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #ffffff;
            border: none;
            padding: 0 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
            transition: all 0.2s;
        }
        .composer-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .composer-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .composer-meta {
            font-size: 11px;
            color: #64748b;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../components/admin_sidebar.php'; ?>

<main class="admin-main-wrap">
    <div class="support-container">
        <!-- Left Column: Queue & Active Sessions -->
        <aside class="queue-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <span>🛎️ ศูนย์ Live Support</span>
                    <span class="status-badge-count" id="queue-badge-count">0 คิว</span>
                </div>
                <div class="filter-tabs">
                    <button class="filter-tab active" data-tab="queued" onclick="switchTab('queued')">
                        รอคิว (<span id="tab-cnt-queued">0</span>)
                    </button>
                    <button class="filter-tab" data-tab="active" onclick="switchTab('active')">
                        สนทนา (<span id="tab-cnt-active">0</span>)
                    </button>
                    <button class="filter-tab" data-tab="bot" onclick="switchTab('bot')">
                        ประวัติแชท (<span id="tab-cnt-bot">0</span>)
                    </button>
                </div>
            </div>

            <div class="session-list" id="session-list-container">
                <div style="text-align: center; color: #94a3b8; padding: 40px 10px; font-size: 13px;">
                    กำลังโหลดรายการ...
                </div>
            </div>
        </aside>

        <!-- Right Column: Active Conversation Stream -->
        <section class="chat-panel">
            <div class="chat-topbar">
                <div class="user-headline" id="chat-user-headline">
                    <div class="user-avatar-circle" id="chat-avatar">?</div>
                    <div class="user-title-group">
                        <h2 id="chat-user-name">กรุณาเลือกการสนทนาจากแถบด้านซ้าย</h2>
                        <p id="chat-user-sub">พร้อมให้คำปรึกษาและช่วยเหลือแบบเรียลไทม์</p>
                    </div>
                </div>
                <div class="topbar-actions" id="chat-topbar-actions" style="display: none;">
                    <button class="btn-claim" id="btn-claim-chat" onclick="claimCurrentSession()">
                        <span>⚡</span> รับเรื่อง / เข้าร่วมสนทนา
                    </button>
                    <button class="btn-ask-close" id="btn-ask-close" onclick="askBeforeClose()">
                        💬 ถามก่อนปิด
                    </button>
                    <button class="btn-close-chat" id="btn-close-chat" onclick="closeCurrentSession()">
                        ✕ จบการสนทนา
                    </button>
                </div>
            </div>

            <div class="chat-stream" id="chat-stream">
                <div class="empty-chat-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <p>เลือกคิวหรือผู้ใช้งานจากรายการด้านซ้ายเพื่อเริ่มสนทนา</p>
                </div>
            </div>

            <div class="chat-bottombar" id="chat-composer-wrap">
                <form class="composer-form" onsubmit="event.preventDefault(); sendAdminMessage();">
                    <input type="text" id="composer-text" class="composer-input" placeholder="กรุณาเลือกการสนทนาจากด้านซ้ายก่อน..." autocomplete="off" disabled>
                    <button type="submit" id="composer-btn" class="composer-btn" disabled>
                        ส่งข้อความ
                    </button>
                </form>
                <div class="composer-meta">
                    <span>🛡️ กำลังตอบกลับในนาม Super Admin:</span>
                    <strong><?= htmlspecialchars($adminName) ?></strong>
                </div>
            </div>
        </section>
    </div>
</main>

<script>
// State
let currentTab       = 'queued';
let currentSessionId = null;
let allQueued        = [];
let allActive        = [];
let allBot           = [];
let pollTimer        = null;

// Flicker-free tracking
let lastListHash          = null;
let lastRenderedSessionId = null;
let lastRenderedMsgId     = null;

const loggedAdminName = <?= json_encode($adminName) ?>;

// Utilities
function escapeHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
function roleLabel(role) {
    switch (role) {
        case 'reader':          return { text: 'ผู้อ่าน',     cls: 'role-reader' };
        case 'shop_admin':      return { text: 'ร้านหนังสือ', cls: 'role-shop_admin' };
        case 'publisher_admin': return { text: 'สำนักพิมพ์',  cls: 'role-publisher_admin' };
        default:                return { text: 'ทั่วไป',      cls: 'role-guest' };
    }
}
function formatTime(str) {
    if (!str) return '';
    try { return new Date(str.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
    catch(e) { return ''; }
}

// Tab switching
function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.filter-tab').forEach(b =>
        b.classList.toggle('active', b.getAttribute('data-tab') === tab));
    lastListHash = null;
    renderSessionList();
}

// Session list - hash-diff to avoid full re-render on every poll
function buildListHash(list) {
    return list.map((s, idx) =>
        [s.session_id, idx, s.status, s.last_message, s.wait_minutes, s.assigned_admin_name].join(':')
    ).join('|') + '|sel=' + currentSessionId;
}

function renderSessionList() {
    const container = document.getElementById('session-list-container');
    const list = currentTab === 'queued' ? allQueued : currentTab === 'active' ? allActive : allBot;

    const hash = buildListHash(list);
    if (hash === lastListHash) return;
    lastListHash = hash;

    if (list.length === 0) {
        container.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:40px 10px;font-size:13px;">ไม่มีรายการในหมวดนี้</div>';
        return;
    }

    container.innerHTML = list.map((s, idx) => {
        const isQueued   = s.status === 'queued';
        const isClosed   = s.status === 'closed';
        const isSelected = String(s.session_id) === String(currentSessionId);
        const role       = roleLabel(s.user_role);
        const wait       = s.wait_minutes ? `<span class="wait-pill">รอ ${s.wait_minutes} นาที</span>` : '';
        const admin      = s.assigned_admin_name ? `<span class="card-assigned">แอดมิน: ${escapeHtml(s.assigned_admin_name)}</span>` : '';
        const queueBadge = isQueued ? `<span class="queue-rank-badge">คิว #${idx + 1}</span>` : '';

        // Status pill
        let statusPill = '';
        if      (s.status === 'closed')  statusPill = `<span class="status-pill pill-closed">✓ ปิดแล้ว</span>`;
        else if (s.status === 'bot')     statusPill = `<span class="status-pill pill-bot">🤖 บอท</span>`;
        else if (s.status === 'queued')  statusPill = `<span class="status-pill pill-queued">⏳ คิวที่ ${idx + 1}</span>`;
        else if (s.status === 'active')  statusPill = `<span class="status-pill pill-active">💬 กำลังสนทนา</span>`;

        let cardClass = `session-card`;
        if (isQueued)   cardClass += ' queued-card';
        if (isClosed)   cardClass += ' closed-card';
        if (isSelected) cardClass += ' active';

        return `<div class="${cardClass}" data-sid="${s.session_id}">
            <div class="card-top">
                <span class="card-name">${queueBadge}${escapeHtml(s.user_name)}</span>
                <span class="card-role-tag ${role.cls}">${role.text}</span>
            </div>
            <div class="card-snippet">${escapeHtml(s.last_message||'เริ่มต้นการสนทนา')}</div>
            <div class="card-bottom">
                <span>${formatTime(s.last_message_time||s.created_at)}</span>
                <div>${statusPill} ${wait} ${admin}</div>
            </div>
        </div>`;
    }).join('');

    container.querySelectorAll('.session-card').forEach(card =>
        card.addEventListener('click', () => selectSession(card.dataset.sid)));
}

// Poll
async function fetchQueueData() {
    try {
        const res  = await fetch('/api/live_support_api.php?action=admin_get_queue');
        const data = await res.json();
        if (!data.success) return;
        allQueued = data.queued || [];
        allActive = data.active || [];
        allBot    = data.bot    || [];
        document.getElementById('queue-badge-count').textContent = `${allQueued.length} คิว`;
        document.getElementById('tab-cnt-queued').textContent    = allQueued.length;
        document.getElementById('tab-cnt-active').textContent    = allActive.length;
        document.getElementById('tab-cnt-bot').textContent       = allBot.length;
        renderSessionList();
        if (currentSessionId) refreshActiveMessages();
    } catch(err) { console.error('fetchQueueData error:', err); }
}

// Select session
async function selectSession(sessionId) {
    currentSessionId      = String(sessionId);
    lastRenderedSessionId = null;
    lastRenderedMsgId     = null;
    lastListHash          = null;
    renderSessionList();
    document.getElementById('chat-topbar-actions').style.display = 'flex';
    document.getElementById('chat-stream').innerHTML = '';
    await refreshActiveMessages();

    // Lock composer if this is a closed/bot session (read-only history view)
    const allSessions = [...allQueued, ...allActive, ...allBot];
    const sess = allSessions.find(s => String(s.session_id) === String(sessionId));
    const inp = document.getElementById('composer-text');
    const btn = document.getElementById('composer-btn');
    if (sess && (sess.status === 'closed' || sess.status === 'bot')) {
        inp.disabled    = true;
        btn.disabled    = true;
        inp.placeholder = sess.status === 'closed'
            ? '📋 บทสนทนานี้ปิดแล้ว (ดูประวัติเท่านั้น)'
            : '🤖 บทสนทนาบอท (ดูประวัติเท่านั้น)';
    } else {
        inp.disabled    = false;
        btn.disabled    = false;
        inp.placeholder = 'พิมพ์ข้อความตอบกลับผู้ใช้...';
        inp.focus();
    }
}

// Append a single bubble (no flicker)
function appendBubble(m, stream) {
    const bubble = document.createElement('div');
    bubble.className   = `stream-bubble ${m.sender_type}`;
    bubble.dataset.mid = m.message_id;
    let authorHtml = '';
    if      (m.sender_type === 'user')  authorHtml = `<div class="bubble-author">👤 ${escapeHtml(m.sender_name)}</div>`;
    else if (m.sender_type === 'admin') authorHtml = `<div class="bubble-author">🛡️ Super Admin: ${escapeHtml(m.sender_name)}</div>`;
    else if (m.sender_type === 'bot')   authorHtml = `<div class="bubble-author">🤖 ${escapeHtml(m.sender_name)}</div>`;
    bubble.innerHTML = `${authorHtml}<div class="bubble-box">${escapeHtml(m.message_text)}</div><div class="bubble-time">${formatTime(m.created_at)}</div>`;
    stream.appendChild(bubble);
}

// Refresh messages - incremental append only
async function refreshActiveMessages() {
    if (!currentSessionId) return;
    try {
        const res  = await fetch(`/api/live_support_api.php?action=admin_get_messages&session_id=${currentSessionId}`);
        const data = await res.json();
        if (!data.success) return;

        const s        = data.session;
        const messages = data.messages || [];
        const role     = roleLabel(s.user_role);

        // Topbar - minimal DOM writes
        const avatarEl  = document.getElementById('chat-avatar');
        const newInitial = (s.user_name || '?')[0].toUpperCase();
        if (avatarEl.textContent !== newInitial) avatarEl.textContent = newInitial;
        const nameEl = document.getElementById('chat-user-name');
        if (nameEl.textContent !== s.user_name) nameEl.textContent = s.user_name;
        
        let queueInfoText = '';
        if (s.status === 'queued') {
            const queueIdx = allQueued.findIndex(q => String(q.session_id) === String(s.session_id));
            if (queueIdx !== -1) {
                const qPos = queueIdx + 1;
                queueInfoText = ` • <span style="color:#c2410c;font-weight:700;">คิวที่ #${qPos} (มีคิวก่อนหน้า ${queueIdx} คิว)</span>`;
            }
        }
        document.getElementById('chat-user-sub').innerHTML = `สถานะ: <strong>${s.status}</strong>${queueInfoText} • ผู้ใช้ประเภท: <strong>${role.text}</strong>${s.assigned_admin_name ? ` • แอดมินผู้ดูแล: <strong>${escapeHtml(s.assigned_admin_name)}</strong>` : ''}`;

        // Claim button
        const claimBtn = document.getElementById('btn-claim-chat');
        claimBtn.style.display = (s.status === 'queued' || s.assigned_admin_name !== loggedAdminName) ? 'inline-flex' : 'none';

        const stream = document.getElementById('chat-stream');

        // New session selected - clear stream
        if (lastRenderedSessionId !== currentSessionId) {
            stream.innerHTML      = '';
            lastRenderedMsgId     = null;
            lastRenderedSessionId = currentSessionId;
        }

        // Empty state
        const placeholder = stream.querySelector('.empty-chat-state');
        if (messages.length === 0) {
            if (!placeholder) stream.innerHTML = '<div class="empty-chat-state"><p>ยังไม่มีข้อความในการสนทนานี้</p></div>';
            return;
        }
        if (placeholder) placeholder.remove();

        // Only append new messages
        const newMessages = messages.filter(m =>
            lastRenderedMsgId === null || Number(m.message_id) > Number(lastRenderedMsgId));
        if (newMessages.length === 0) return;

        const isAtBottom = stream.scrollHeight - stream.clientHeight - stream.scrollTop < 80;
        newMessages.forEach(m => appendBubble(m, stream));
        lastRenderedMsgId = Math.max(...messages.map(m => Number(m.message_id)));
        if (isAtBottom) stream.scrollTop = stream.scrollHeight;

        // Auto-cancel countdown if user replied
        checkUserRepliedToCancel(messages);

    } catch(e) { console.error('refreshActiveMessages error:', e); }
}

// Actions
async function claimCurrentSession() {
    if (!currentSessionId) return;
    try {
        const fd = new FormData(); fd.append('session_id', currentSessionId);
        const data = await (await fetch('/api/live_support_api.php?action=admin_claim', { method:'POST', body:fd })).json();
        if (data.success) { await fetchQueueData(); await refreshActiveMessages(); document.getElementById('composer-text').focus(); }
    } catch(e) { alert('เกิดข้อผิดพลาดในการรับเรื่อง'); }
}
async function sendAdminMessage() {
    if (!currentSessionId) return;
    const input = document.getElementById('composer-text');
    const text  = input.value.trim();
    if (!text) return;
    input.value = '';
    try {
        const fd = new FormData(); fd.append('session_id', currentSessionId); fd.append('text', text);
        const data = await (await fetch('/api/live_support_api.php?action=admin_send_message', { method:'POST', body:fd })).json();
        if (data.success) { await refreshActiveMessages(); fetchQueueData(); }
    } catch(e) { alert('ส่งข้อความไม่สำเร็จ'); }
}
async function closeCurrentSession() {
    if (!currentSessionId) return;
    if (!confirm('ต้องการปิดการสนทนานี้และส่งข้อความขอบคุณผู้ใช้ใช่หรือไม่?')) return;
    cancelAutoClose();
    try {
        const fd = new FormData(); fd.append('session_id', currentSessionId);
        const data = await (await fetch('/api/live_support_api.php?action=admin_close_session', { method:'POST', body:fd })).json();
        if (data.success) { await fetchQueueData(); await refreshActiveMessages(); }
    } catch(e) { alert('เกิดข้อผิดพลาดในการปิดการสนทนา'); }
}

// ── Ask Before Close (3 minutes / auto-detect user reply) ─────────────────────
const AUTO_CLOSE_TOTAL   = 180;   // 3 minutes in seconds
let autoCloseSeconds     = 0;
let autoCloseCountdown   = null;
let autoCloseTimer       = null;
let autoCloseWatchMsgId  = null;  // last msg id when countdown started — detect user reply

function fmtCountdown(s) {
    const m = Math.floor(s / 60);
    const sec = String(s % 60).padStart(2, '0');
    return `${m}:${sec}`;
}

async function askBeforeClose() {
    if (!currentSessionId) return;
    const btn = document.getElementById('btn-ask-close');
    btn.disabled = true;

    // Send the closing prompt message
    const closingMsg = 'ขอบคุณที่ติดต่อวนวรรณนะครับ 🙏 มีอะไรให้ช่วยเพิ่มเติมอีกไหมครับ? หากไม่มีข้อสงสัยเพิ่มเติม ทางทีมงานจะขอปิดบทสนทนานี้ภายใน 3 นาทีนะครับ ⏱️';
    try {
        const fd = new FormData();
        fd.append('session_id', currentSessionId);
        fd.append('text', closingMsg);
        await fetch('/api/live_support_api.php?action=admin_send_message', { method: 'POST', body: fd });
        await refreshActiveMessages();
    } catch(e) {
        btn.disabled = false;
        return;
    }

    // Record the current last-message-id so we can detect if user replies
    autoCloseWatchMsgId = lastRenderedMsgId;

    // Start 3-minute countdown
    autoCloseSeconds = AUTO_CLOSE_TOTAL;
    showAutoCloseBanner();

    autoCloseCountdown = setInterval(() => {
        autoCloseSeconds--;
        const secEl = document.getElementById('autoclose-seconds');
        if (secEl) secEl.textContent = fmtCountdown(autoCloseSeconds);
        if (autoCloseSeconds <= 0) {
            cancelAutoClose();
            forceCloseSession();
        }
    }, 1000);
}

function showAutoCloseBanner() {
    removeBanner();
    const stream = document.getElementById('chat-stream');
    const banner = document.createElement('div');
    banner.id        = 'autoclose-banner';
    banner.className = 'autoclose-banner';
    banner.innerHTML = `
        <span>⏱️ จะปิดบทสนทนาอัตโนมัติใน <strong id="autoclose-seconds">${fmtCountdown(autoCloseSeconds)}</strong> หากลูกค้าไม่ตอบกลับ</span>
        <button class="autoclose-cancel" onclick="cancelAutoClose()">ยกเลิก</button>
    `;
    stream.parentElement.insertBefore(banner, stream);
}

function removeBanner() {
    const b = document.getElementById('autoclose-banner');
    if (b) b.remove();
}

function cancelAutoClose() {
    if (autoCloseCountdown) { clearInterval(autoCloseCountdown); autoCloseCountdown = null; }
    if (autoCloseTimer)     { clearTimeout(autoCloseTimer);      autoCloseTimer = null; }
    autoCloseWatchMsgId = null;
    removeBanner();
    const btn = document.getElementById('btn-ask-close');
    if (btn) btn.disabled = false;
}

// Called after each message refresh — detects user reply and cancels countdown
function checkUserRepliedToCancel(messages) {
    if (!autoCloseCountdown || autoCloseWatchMsgId === null) return;
    // Find any USER message newer than the watch point
    const userReplied = messages.some(m =>
        m.sender_type === 'user' && Number(m.message_id) > Number(autoCloseWatchMsgId)
    );
    if (userReplied) {
        cancelAutoClose();
        // Re-enable ask button after user replied
        const btn = document.getElementById('btn-ask-close');
        if (btn) btn.disabled = false;
    }
}

async function forceCloseSession() {
    if (!currentSessionId) return;
    cancelAutoClose();
    try {
        const fd = new FormData(); fd.append('session_id', currentSessionId);
        const data = await (await fetch('/api/live_support_api.php?action=admin_close_session', { method:'POST', body:fd })).json();
        if (data.success) { await fetchQueueData(); await refreshActiveMessages(); }
    } catch(e) { console.error('Auto-close failed:', e); }
}

document.addEventListener('DOMContentLoaded', () => {
    fetchQueueData();
    pollTimer = setInterval(fetchQueueData, 3000);
});
</script>

</body>
</html>
