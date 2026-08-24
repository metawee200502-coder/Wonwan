<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mood Match - ให้หนังสือช่วยเยียวยาความรู้สึก - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        * { box-sizing: border-box; }
        .mood-page { 
            min-height: calc(100vh - 74px); 
            background: linear-gradient(145deg, #fffcf9 0%, #fff0f4 100%); 
            padding: 30px 20px; 
            font-family: var(--font-family); 
        }
        .mood-shell {
            max-width: 800px; 
            height: calc(100vh - 134px); 
            min-height: 600px;
            margin: auto; 
            display: flex; 
            flex-direction: column;
            border: 1px solid #f2e2e6; 
            border-radius: 22px; 
            background: #fff; 
            box-shadow: 0 15px 45px rgba(119,77,88,0.08);
            overflow: hidden;
        }
        
        /* Header */
        .mood-head {
            height: 78px; 
            flex: 0 0 auto; 
            padding: 18px 25px; 
            border-bottom: 1px solid #f9ecee; 
            display: flex; 
            align-items: center; 
            gap: 15px;
            background: #fffcfd;
        }
        .mood-back {
            color: var(--secondary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            transition: 0.2s;
            margin-right: 5px;
            background: #fdfaf6;
            border: 1px solid #f4e8e1;
        }
        .mood-back:hover { background: #f4e8e1; color: #a64364; }
        .mood-orb {
            width: 44px; 
            height: 44px; 
            display: grid; 
            place-items: center; 
            border-radius: 50%; 
            color: #fff; 
            background: linear-gradient(135deg, #e68cae, #c65e82); 
            box-shadow: 0 5px 15px rgba(230, 140, 174, 0.4);
            font-size: 22px;
        }
        .mood-head strong { display: block; color: var(--secondary-color); font-size: 19px; }
        .mood-head small { color: var(--text-muted); font-size: 13px; }
        .mood-clear {
            margin-left: auto; 
            border: 0; 
            background: transparent; 
            color: #b36a79; 
            font-family: inherit; 
            font-weight: 500;
            cursor: pointer; 
            padding: 8px 12px;
            border-radius: 8px;
            transition: 0.2s;
        }
        .mood-clear:hover { background: #fff0f4; }
        
        /* Messages */
        .mood-messages {
            flex: 1 1 auto; 
            overflow-x: hidden; 
            overflow-y: auto; 
            padding: 25px; 
            scroll-behavior: smooth;
            -ms-overflow-style: none; /* IE and Edge */
            scrollbar-width: none; /* Firefox */
        }
        .mood-messages::-webkit-scrollbar { display: none; /* Chrome, Safari and Opera */ }
        
        .message { display: flex; margin-bottom: 20px; }
        .message.user { justify-content: flex-end; }
        .bubble {
            max-width: 80%; 
            padding: 14px 18px; 
            border-radius: 18px; 
            line-height: 1.6; 
            font-size: 15px; 
            white-space: pre-wrap; 
            overflow-wrap: anywhere;
        }
        .assistant .bubble { background: #fdfaf6; border: 1px solid #f4e8e1; color: var(--text-dark); border-bottom-left-radius: 5px; }
        .user .bubble { background: var(--secondary-color); color: #fff; border-bottom-right-radius: 5px; }
        
        .bubble a {
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            margin: 4px 4px 4px 0; 
            padding: 6px 12px; 
            border: 1px solid #e1b8c4; 
            border-radius: 8px; 
            background: #fff; 
            color: #a64364; 
            font-weight: 700; 
            text-decoration: none;
            transition: 0.2s;
        }
        .bubble a::after { content: '↗'; font-size: 13px; }
        .bubble a:hover { background: #fff0f4; border-color: #d37d97; }
        .assistant .bubble strong { font-weight: 700; color: #a64364; }

        /* Rich Book Card in Chat */
        .chat-book-card {
            display: flex;
            gap: 15px;
            background: #fff;
            border: 1px solid #f2e2e6;
            border-radius: 12px;
            padding: 12px;
            margin: 15px 0;
            box-shadow: 0 4px 15px rgba(119,77,88,0.05);
        }
        .chat-book-img {
            width: 70px;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            background: #faf4f5;
        }
        .chat-book-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .chat-book-title {
            font-weight: 700;
            color: var(--secondary-color);
            margin: 0 0 10px;
            font-size: 16px;
        }
        .chat-book-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .chat-btn {
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: inherit;
        }
        .chat-btn.buy {
            background: var(--secondary-color);
            color: #fff;
        }
        .chat-btn.buy:hover { background: #154c5e; }
        .chat-btn.sample {
            background: #fdfaf6;
            border: 1px solid #d37d97;
            color: #a64364;
        }
        .chat-btn.sample:hover { background: #fff0f4; }
        .chat-btn.similar {
            background: #fdfaf6;
            border: 1px solid #c9b1bd;
            color: #795d6a;
        }
        .chat-btn.similar:hover { background: #f7f1f4; }
        .chat-btn.more-recommendations {
            background: #fff;
            border: 1px solid #d37d97;
            color: #a64364;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .chat-btn.more-recommendations:hover { background: #fff0f4; }
        
        /* Welcome & Emotion Chips */
        .mood-welcome { text-align: center; max-width: 580px; margin: 20px auto 40px; color: var(--text-muted); }
        .mood-welcome .mood-orb { width: 60px; height: 60px; margin: 0 auto 15px; font-size: 30px; }
        .mood-welcome h2 { color: var(--secondary-color); margin: 0 0 10px; font-size: 24px; }
        
        .emotion-drawer-wrap {
            flex: 0 0 auto;
            position: relative;
        }
        .emotion-toggle {
            position: absolute;
            top: -28px;
            left: 50%;
            transform: translateX(-50%);
            background: #fffcfd;
            border: 1px solid #f9ecee;
            border-bottom: none;
            border-radius: 12px 12px 0 0;
            width: 50px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #b36a79;
            z-index: 10;
            transition: 0.3s;
        }
        .emotion-toggle:hover { background: #fff0f4; }
        .emotion-toggle svg { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .emotion-toggle.open svg { transform: rotate(180deg); }
        .emotion-drawer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.4s ease;
            background: #fffcfd;
            border-top: 1px solid #f9ecee;
        }
        .emotion-drawer.open {
            max-height: 250px;
            padding: 20px;
        }
        
        .emotion-chips { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #eabcc9; 
            background: #fff; 
            color: #b1486c; 
            border-radius: 20px; 
            padding: 10px 18px; 
            font-family: inherit; 
            font-size: 14px;
            font-weight: 500;
            cursor: pointer; 
            transition: 0.2s;
        }
        .chip:hover { background: #fcecf0; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(177, 72, 108, 0.1); }
        
        /* Form */
        .typing { display: none; padding: 0 28px 10px; color: #a49195; font-size: 13px; font-style: italic; }
        .typing.show { display: block; }
        
        .mood-form { flex: 0 0 auto; padding: 15px 20px 20px; border-top: 1px solid #f9ecee; background: #fff; }
        .mood-input-wrap {
            display: flex; 
            gap: 10px; 
            padding: 8px 8px 8px 20px; 
            border: 1px solid #eecdd5; 
            border-radius: 20px; 
            background: #fffcfd;
            align-items: flex-end;
            transition: 0.3s;
        }
        .mood-input-wrap:focus-within { border-color: #c65e82; background: #fff; box-shadow: 0 0 0 3px rgba(198, 94, 130, 0.1); }
        .mood-input {
            flex: 1; 
            resize: none; 
            border: 0; 
            background: transparent;
            outline: 0; 
            font: 15px/1.5 var(--font-family); 
            max-height: 120px; 
            padding: 10px 0;
            color: var(--text-dark);
        }
        .mood-input::placeholder { color: #b8a6aa; }
        .mood-send {
            width: 44px; 
            height: 44px; 
            flex-shrink: 0;
            border: 0; 
            border-radius: 14px; 
            background: var(--secondary-color); 
            color: #fff; 
            cursor: pointer; 
            display: grid;
            place-items: center;
            transition: 0.2s;
        }
        .mood-send svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
        .mood-send:hover:not(:disabled) { background: #154c5e; transform: scale(1.05); }
        .mood-send:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .error-msg { display: none; margin: 0 20px 10px; padding: 12px 16px; background: #fff0f0; color: #b44949; border-radius: 10px; font-size: 14px; }
        .error-msg.show { display: block; }

        @media (max-width: 760px) {
            .mood-page { padding: 0; }
            .mood-shell { height: calc(100vh - 68px); border-radius: 0; border: none; }
            .mood-head { height: 70px; padding: 15px; }
            .mood-welcome h2 { font-size: 20px; }
            .bubble { max-width: 90%; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/../components/header.php'; ?>

<main class="mood-page">
    <section class="mood-shell">
        <header class="mood-head">
            <a href="/pages/home.php" class="mood-back" aria-label="กลับไปหน้าหลัก">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
            </a>
            <span class="mood-orb">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
            </span>
            <div>
                <strong>Mood Match</strong>
                <small>บอกความรู้สึก แล้วให้หนังสือเยียวยาคุณ</small>
            </div>
            <button class="mood-clear" type="button" id="clearBtn">เริ่มคุยใหม่</button>
        </header>
        
        <div class="mood-messages" id="messages">
            <div class="mood-welcome" id="welcome">
                <span class="mood-orb">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                </span>
                <h2>วันนี้คุณรู้สึกอย่างไรบ้าง?</h2>
                <p>พิมพ์บอกเราสิว่าวันนี้เจอเรื่องอะไรมา หรือเลือกจากอารมณ์ด้านล่างนี้ได้เลย</p>
            </div>
        </div>
        
        <div class="typing" id="typing">✨ Mood Match กำลังหาหนังสือที่ใช่ให้คุณ...</div>
        <div class="error-msg" id="error"></div>
        
        <div class="emotion-drawer-wrap">
            <button class="emotion-toggle open" id="drawerToggle" aria-label="ซ่อน/แสดง อารมณ์ด่วน">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="18 15 12 9 6 15"></polyline>
                </svg>
            </button>
            <div class="emotion-drawer open" id="emotionDrawer">
                <div class="emotion-chips">
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        กำลังอินเลิฟ
                    </button>
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        คนคุย / กำลังจีบ
                    </button>
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
                        เพิ่งไปเดทมา
                    </button>
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path><line x1="15" y1="3" x2="9" y2="15"></line></svg>
                        อกหักมา
                    </button>
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                        เครียดจังเลย
                    </button>
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                        มีไฟฮึดสู้
                    </button>
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="6" width="18" height="12" rx="2" ry="2"></rect><line x1="23" y1="13" x2="23" y2="11"></line><line x1="5" y1="12" x2="5.01" y2="12"></line></svg>
                        หมดไฟทำงาน
                    </button>
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                        รู้สึกเหงาๆ
                    </button>
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                        อยากหัวเราะ
                    </button>
                    <button class="chip">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        ตื่นเต้นสุดๆ
                    </button>
                </div>
            </div>
        </div>
        
        <form class="mood-form" id="chatForm">
            <div class="mood-input-wrap">
                <textarea class="mood-input" id="messageInput" rows="1" placeholder="พิมพ์บอกความรู้สึกของคุณที่นี่..."></textarea>
                <button class="mood-send" id="sendBtn" type="submit" aria-label="ส่งข้อความ">
                    <svg viewBox="0 0 24 24">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../components/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const MOOD_SESSION_KEY = 'wanawan_mood_match_session';
    let sessionData = JSON.parse(sessionStorage.getItem(MOOD_SESSION_KEY)) || {
        chatHistory: [],
        booksMetadata: {}
    };
    
    let chatHistory = [];
    let booksMetadata = sessionData.booksMetadata || {};
    
    const messages = document.getElementById('messages');
    const input = document.getElementById('messageInput');
    const form = document.getElementById('chatForm');
    const send = document.getElementById('sendBtn');
    const typing = document.getElementById('typing');
    const errorBox = document.getElementById('error');
    const drawerToggle = document.getElementById('drawerToggle');
    const emotionDrawer = document.getElementById('emotionDrawer');
    
    // Toggle Category Drawer
    drawerToggle.addEventListener('click', () => {
        drawerToggle.classList.toggle('open');
        emotionDrawer.classList.toggle('open');
        setTimeout(scrollBottom, 400); // Adjust scroll after transition
    });
    
    // Auto-resize textarea
    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
        if (this.value === '') this.style.height = 'auto';
    });
    
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    
    document.getElementById('clearBtn').addEventListener('click', () => {
        if(confirm('ต้องการเริ่มคุยใหม่ใช่หรือไม่? ประวัติเดิมจะถูกลบทั้งหมด')) {
            sessionStorage.removeItem(MOOD_SESSION_KEY);
            location.reload();
        }
    });
    
    document.querySelectorAll('.chip').forEach(btn => {
        btn.addEventListener('click', () => {
            input.value = btn.textContent.trim();
            input.style.height = 'auto';
            drawerToggle.classList.remove('open');
            emotionDrawer.classList.remove('open');
            form.requestSubmit();
        });
    });
    
    function scrollBottom() {
        messages.scrollTop = messages.scrollHeight;
    }
    
    window.requestSimilar = function(bookTitle) {
        input.value = `ขอหนังสือที่มีบรรยากาศและแนวเรื่องคล้ายกับเรื่อง '${bookTitle}'`;
        input.style.height = 'auto';
        drawerToggle.classList.remove('open');
        emotionDrawer.classList.remove('open');
        form.requestSubmit();
    };
    
    window.requestMore = function() {
        input.value = `ขอคำแนะนำหนังสือเพิ่มเติมอีก 3 เล่ม`;
        input.style.height = 'auto';
        drawerToggle.classList.remove('open');
        emotionDrawer.classList.remove('open');
        form.requestSubmit();
    };
    
    function formatMarkdown(text, booksMetadata = null) {
        // Convert bold
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        const renderedBooks = new Set();
        
        // Convert links [title](url)
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, title, url) => {
            const bookIdMatch = url.match(/id=(\d+)/);
            if (bookIdMatch && booksMetadata && booksMetadata[bookIdMatch[1]]) {
                const bookId = bookIdMatch[1];
                if (!renderedBooks.has(bookId)) {
                    renderedBooks.add(bookId);
                    const book = booksMetadata[bookId];
                    const safeTitle = book.title.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    return `
                        <div class="chat-book-card">
                            <img class="chat-book-img" src="${book.image_cover}" alt="ปก ${book.title}" loading="lazy">
                            <div class="chat-book-info">
                                <h4 class="chat-book-title">${book.title}</h4>
                                <div class="chat-book-actions">
                                    <a href="/pages/book_detail.php?id=${bookId}&source=mood_match" class="chat-btn buy">ซื้อเลย</a>
                                    <a href="/pages/book_detail.php?id=${bookId}&source=mood_match#sample" class="chat-btn sample">อ่านตัวอย่าง</a>
                                    <button type="button" class="chat-btn similar" onclick="window.requestSimilar('${safeTitle}')">หาแนวนี้อีก</button>
                                </div>
                            </div>
                        </div>
                    `;
                }
                // If it's a duplicate link (e.g. at the bottom), just render it as normal text or remove if it says "ลิงก์สินค้า"
                if (title === 'ลิงก์สินค้า') return '';
                return `<strong style="color: #a64364;">${title}</strong>`;
            }
            return `<a href="${url}">${title}</a>`;
        });
        
        return text;
    }
    
    function addMessage(role, text, newBooks = null, skipSave = false) {
        if (newBooks) {
            Object.assign(booksMetadata, newBooks);
        }
        
        const welcome = document.getElementById('welcome');
        if (welcome) welcome.remove();
        
        const row = document.createElement('div');
        row.className = 'message ' + role;
        
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        
        if (role === 'assistant') {
            bubble.innerHTML = formatMarkdown(text, booksMetadata);
            
            if (bubble.querySelector('.chat-book-card')) {
                const moreBtn = document.createElement('button');
                moreBtn.type = 'button';
                moreBtn.className = 'chat-btn more-recommendations';
                moreBtn.textContent = 'แนะนำเพิ่มเติม (More)';
                moreBtn.onclick = () => window.requestMore();
                
                const moreWrap = document.createElement('div');
                moreWrap.style.textAlign = 'right';
                moreWrap.style.marginTop = '15px';
                moreWrap.appendChild(moreBtn);
                
                bubble.appendChild(moreWrap);
            }
        } else {
            bubble.textContent = text;
        }
        
        row.appendChild(bubble);
        messages.appendChild(row);
        scrollBottom();
        
        // Add to history and update session
        chatHistory.push({ role: role, content: text });
        if (!skipSave) {
            sessionStorage.setItem(MOOD_SESSION_KEY, JSON.stringify({
                chatHistory: chatHistory,
                booksMetadata: booksMetadata
            }));
        }
    }
    
    // Restore session if exists
    if (sessionData.chatHistory && sessionData.chatHistory.length > 0) {
        const loadedHistory = [...sessionData.chatHistory];
        loadedHistory.forEach(msg => {
            addMessage(msg.role, msg.content, null, true);
        });
        // Collapse drawer if chat is active
        drawerToggle.classList.remove('open');
        emotionDrawer.classList.remove('open');
    }
    
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text || send.disabled) return;
        
        addMessage('user', text);
        
        input.value = '';
        input.style.height = 'auto';
        send.disabled = true;
        typing.classList.add('show');
        errorBox.classList.remove('show');
        scrollBottom();
        
        try {
            const response = await fetch('/pages/handlers/mood_match_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    mood: text,
                    history: chatHistory.slice(0, -1) // Send all history except the one we just pushed
                })
            });
            
            const data = await response.json();
            
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'ระบบขัดข้อง กรุณาลองใหม่');
            }
            
            addMessage('assistant', data.reply, data.books);
            
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.add('show');
            // Remove the user message from history array so they can try again
            chatHistory.pop(); 
        } finally {
            send.disabled = false;
            typing.classList.remove('show');
            input.focus();
            scrollBottom();
        }
    });
});
</script>
</body>
</html>
