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
    <title>Book Genome - ถอดรหัส DNA การอ่านของคุณ - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        /* =========================================================
           BOOK GENOME PAGE STYLES — ใช้สีของแบรนด์วนวรรณ
           ========================================================= */
        * { box-sizing: border-box; }
        body { 
            background-color: var(--bg-color); /* #fff9fa */
            color: var(--text-dark);
            font-family: var(--font-family);
        }
        
        .genome-wrap { max-width: 1200px; margin: 0 auto; padding: 40px 20px 80px; }
        
        /* Header Title */
        .page-title { text-align: center; margin-bottom: 40px; }
        .page-title h1 { font-size: 36px; margin: 0 0 10px; color: var(--secondary-color); font-weight: 700; }
        .page-title p { color: var(--text-muted); font-size: 16px; margin: 0; }
        
        /* Panel (Setup & Result) */
        .glass-panel { 
            background: #ffffff; 
            border: 1px solid var(--border-color); 
            border-radius: 24px; 
            padding: 35px; 
            margin: 0 auto 30px; 
            box-shadow: 0 12px 34px rgba(91,58,67,0.06); 
            max-width: 800px;
        }
        
        .section-title { font-size: 18px; font-weight: 600; color: var(--secondary-color); margin: 0 0 25px; display: flex; align-items: center; gap: 10px; }
        .section-title svg { color: var(--primary-color); }
        
        /* Sliders */
        .dna-grid { display: grid; gap: 30px; }
        .slider-group { position: relative; }
        .slider-labels { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; font-weight: 500; color: var(--text-muted); }
        
        input[type="range"] {
            -webkit-appearance: none; width: 100%; height: 6px; background: var(--accent-pink-light); border-radius: 5px; outline: none; transition: 0.2s;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none; appearance: none; width: 22px; height: 22px; border-radius: 50%; background: var(--primary-color); cursor: pointer; border: 3px solid #fff; box-shadow: 0 0 10px rgba(154, 59, 92, 0.3); transition: 0.2s;
        }
        input[type="range"]::-webkit-slider-thumb:hover { transform: scale(1.2); }
        
        /* Attention Input */
        .attention-input {
            width: 100%; background: #fffcfd; border: 1px solid var(--border-color); border-radius: 16px; padding: 18px 20px; color: var(--text-dark); font-family: var(--font-family); font-size: 15px; resize: vertical; min-height: 100px; outline: none; transition: 0.3s;
        }
        .attention-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(154, 59, 92, 0.1); }
        .attention-input::placeholder { color: #a19799; }
        
        /* Submit Btn */
        .btn-analyze {
            width: 100%; padding: 18px; border-radius: 16px; border: none; background: var(--primary-color); color: #fff; font-family: var(--font-family); font-size: 18px; font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 10px 25px rgba(154, 59, 92, 0.2);
        }
        .btn-analyze:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(154, 59, 92, 0.3); }
        .btn-analyze:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
        
        .loading-spinner {
            display: none; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* =========================================================
           DASHBOARD RESULT (3 Columns)
           ========================================================= */
        .result-dashboard { display: none; margin-top: 40px; animation: fadeUp 0.6s ease; }
        .result-dashboard.show { display: block; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .dashboard-grid { display: grid; grid-template-columns: 1fr 340px 1fr; gap: 40px; align-items: start; }
        
        /* ── Column 1: Metrics ── */
        .metrics-col h3 { font-size: 20px; margin: 0 0 24px; color: var(--secondary-color); font-weight: 700; }
        .metric-card { background: #fff; border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; margin-bottom: 16px; box-shadow: 0 4px 15px rgba(91,58,67,0.04); border-left: 4px solid var(--primary-color); }
        .metric-card.alt-color { border-left-color: var(--secondary-color); }
        
        .metric-head { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 12px; }
        .metric-head h4 { margin: 0; font-size: 15px; color: var(--text-dark); font-weight: 600; }
        .metric-head span { font-size: 18px; font-weight: 700; color: var(--primary-color); }
        .metric-card.alt-color .metric-head span { color: var(--secondary-color); }
        
        .metric-bar-bg { height: 6px; background: var(--accent-pink-light); border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
        .metric-card.alt-color .metric-bar-bg { background: #e0eff5; }
        .metric-bar-fill { height: 100%; background: var(--primary-color); border-radius: 3px; width: 0%; transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1); }
        .metric-card.alt-color .metric-bar-fill { background: var(--secondary-color); }
        
        .metric-desc { font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.5; }

        /* ── Column 2: Center Node ── */
        .neural-col { display: flex; flex-direction: column; align-items: center; position: relative; padding-top: 20px; }
        .neural-wire { position: absolute; top: 0; bottom: 0; left: 50%; transform: translateX(-50%); width: 100px; z-index: 0; }
        .neural-wire path { fill: none; stroke-width: 1.5; stroke: var(--primary-color); opacity: 0.3; vector-effect: non-scaling-stroke; }
        
        .neural-node { position: relative; z-index: 1; border-radius: 50%; display: grid; place-items: center; color: white; background: var(--secondary-color); margin-bottom: 60px; box-shadow: 0 10px 20px rgba(26,92,115,0.2); }
        .node-small { width: 40px; height: 40px; }
        .node-large { width: 60px; height: 60px; background: var(--primary-color); transform: translateX(-30px); box-shadow: 0 10px 20px rgba(154,59,92,0.2); }
        
        .book-cover-node { z-index: 2; margin: 20px 0 40px; position: relative; cursor: pointer; transition: 0.3s; }
        .book-cover-node:hover { transform: translateY(-5px) scale(1.02); }
        .book-cover-node img { width: 180px; height: 260px; object-fit: cover; border-radius: 12px; box-shadow: 0 20px 40px rgba(91,58,67,0.15); border: 4px solid #fff; }
        
        .buy-btn-float { position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); background: var(--primary-color); color: white; padding: 10px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; text-decoration: none; box-shadow: 0 10px 20px rgba(154,59,92,0.3); white-space: nowrap; transition: 0.2s; }
        .buy-btn-float:hover { background: #83314e; }

        .convergence-label { background: #fff; padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 600; color: var(--secondary-color); box-shadow: 0 4px 15px rgba(91,58,67,0.08); z-index: 1; border: 1px solid var(--border-color); }

        /* ── Column 3: Insight ── */
        .insight-col { display: flex; flex-direction: column; gap: 24px; }
        
        .insight-card { background: #fff; border-radius: 24px; padding: 32px; box-shadow: 0 12px 34px rgba(91,58,67,0.06); border: 1px solid var(--border-color); }
        .insight-header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        .insight-header h3 { margin: 0; font-size: 20px; color: var(--text-dark); font-weight: 700; }
        .insight-header svg { color: var(--primary-color); }
        
        .insight-quote { font-size: 16px; font-style: italic; color: var(--secondary-color); line-height: 1.6; padding-left: 20px; border-left: 4px solid var(--primary-color); margin-bottom: 24px; font-weight: 600; }
        
        .insight-text h4 { font-size: 15px; color: var(--text-dark); margin: 0 0 12px; font-weight: 600; }
        .insight-text p { font-size: 15px; color: var(--text-muted); line-height: 1.7; margin: 0 0 24px; }
        
        .tags-container { display: flex; flex-wrap: wrap; gap: 8px; }
        .tag { background: var(--accent-pink-light); padding: 6px 14px; border-radius: 16px; font-size: 13px; font-weight: 600; color: var(--primary-color); border: 1px solid #ead4d8; }

        .geo-card { background: #fff; border-radius: 24px; padding: 24px; display: flex; gap: 20px; align-items: center; box-shadow: 0 12px 34px rgba(91,58,67,0.06); border: 1px solid var(--border-color); }
        .geo-map { width: 70px; height: 70px; background: var(--accent-pink-light); border-radius: 12px; flex-shrink: 0; display: grid; place-items: center; color: var(--primary-color); }
        .geo-info h4 { font-size: 12px; color: var(--primary-color); text-transform: uppercase; margin: 0 0 4px; font-weight: 700; }
        .geo-info h3 { font-size: 18px; margin: 0 0 4px; color: var(--text-dark); }
        .geo-info p { font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.4; }
        
        .btn-reset { text-align: center; margin-top: 50px; }
        .btn-reset button { background: transparent; border: 1px solid var(--primary-color); color: var(--primary-color); padding: 12px 30px; border-radius: 20px; font-size: 15px; font-weight: 600; cursor: pointer; transition: 0.2s; font-family: var(--font-family); }
        .btn-reset button:hover { background: var(--primary-color); color: #fff; box-shadow: 0 4px 10px rgba(154,59,92,0.2); }
        
        .btn-outline { background: transparent; border: 1px solid var(--primary-color); color: var(--primary-color); padding: 10px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.3s; font-family: var(--font-family); }
        .btn-outline:hover { background: var(--primary-color); color: #fff; box-shadow: 0 4px 10px rgba(154,59,92,0.2); }

        /* Grid View */
        #gridArea { display: none; margin-top: 40px; animation: fadeUp 0.6s ease; }
        .grid-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .grid-header h2 { margin: 0; color: var(--secondary-color); font-size: 24px; font-weight: 700; }
        .books-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
        
        .book-card-genome { background: #fff; border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; transition: 0.3s; box-shadow: 0 4px 15px rgba(91,58,67,0.04); display: flex; flex-direction: column; }
        .book-card-genome:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(91,58,67,0.1); border-color: #ead4d8; }
        .book-card-genome img { width: 100%; height: 280px; object-fit: cover; }
        .book-card-genome-info { padding: 20px; display: flex; flex-direction: column; flex: 1; }
        .book-card-genome-info h3 { margin: 0 0 6px; font-size: 16px; color: var(--secondary-color); font-weight: 700; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; font-family: var(--font-family); }
        .book-card-genome-info p { margin: 0 0 20px; font-size: 14px; color: var(--text-muted); font-family: var(--font-family); }
        .btn-deep-dive { margin-top: auto; width: 100%; padding: 12px; border-radius: 12px; border: 1px solid var(--primary-color); background: transparent; color: var(--primary-color); font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.3s; font-family: var(--font-family); }
        .btn-deep-dive:hover { background: var(--primary-color); color: #fff; box-shadow: 0 4px 10px rgba(154,59,92,0.2); }

        @media (max-width: 1024px) {
            .books-grid { grid-template-columns: repeat(3, 1fr); }
            .dashboard-grid { grid-template-columns: 1fr; gap: 40px; }
            .neural-col { order: -1; }
            .neural-wire { display: none; }
            .neural-node { display: none; }
            .book-cover-node { margin: 0 0 20px; }
            .convergence-label { display: none; }
        }
        @media (max-width: 768px) {
            .books-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .books-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php 
$breadcrumbs = [
    ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'],
    ['label' => 'Book Genome', 'url' => ''],
];
require __DIR__ . '/../components/header.php'; 
?>

<main class="genome-wrap">
    <div class="page-title">
        <h1>ถอดรหัส DNA การอ่านของคุณ 🧬</h1>
        <p>ค้นหาหนังสือที่เป็น "The Perfect Match" ด้วยเทคโนโลยีความสนใจ</p>
    </div>
    
    <!-- Setup Panel -->
    <div class="glass-panel" id="setupPanel">
        <h2 class="section-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            ปรับแต่ง Reading DNA
        </h2>
        
        <div class="dna-grid">
            <div class="slider-group">
                <div class="slider-labels">
                    <span>ค่อยเป็นค่อยไป (Slow-burn)</span>
                    <span>เดินเรื่องเร็ว (Fast-paced)</span>
                </div>
                <input type="range" id="dnaPace" min="0" max="100" value="50">
            </div>
            
            <div class="slider-group">
                <div class="slider-labels">
                    <span>อ่านง่าย สบายๆ (Simple)</span>
                    <span>ซับซ้อน ลุ่มลึก (Complex)</span>
                </div>
                <input type="range" id="dnaComplexity" min="0" max="100" value="50">
            </div>
            
            <div class="slider-group">
                <div class="slider-labels">
                    <span>หม่น / จริงจัง (Dark)</span>
                    <span>สว่าง / สดใส (Light)</span>
                </div>
                <input type="range" id="dnaTone" min="0" max="100" value="50">
            </div>
        </div>
        
        <h2 class="section-title" style="margin-top: 45px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
            Dynamic Attention (ความสนใจปัจจุบัน)
        </h2>
        <textarea id="attentionInput" class="attention-input" placeholder="เป้าหมายปัจจุบัน: เช่น กำลังเครียดเรื่องงาน อยากได้เล่มที่อ่านแล้วมีไฟ หรือ อยากหนีโลกความจริง..."></textarea>
        
        <button type="button" class="btn-analyze" id="analyzeBtn" style="margin-top: 30px;">
            <span>เริ่มค้นหาหนังสือ 12 เล่ม (Broad Search)</span>
            <div class="loading-spinner" id="spinner"></div>
        </button>
    </div>
    
    <!-- Grid Dashboard (Phase 2) -->
    <div id="gridArea">
        <div class="grid-header">
            <h2>หนังสือ 12 เล่มที่ตรงกับ DNA ของคุณ</h2>
            <button type="button" class="btn-outline" onclick="resetGenome()">ค้นหาใหม่ทั้งหมด</button>
        </div>
        <div class="books-grid" id="booksGridContainer">
            <!-- Book cards injected here -->
        </div>
    </div>
    
    <!-- Deep Dive Dashboard (Phase 3) -->
    <div class="result-dashboard" id="resultArea">
        <div class="page-title" style="margin-bottom: 50px;">
            <h1 style="font-size: 28px;" id="bookTitleDisplay">...</h1>
            <p style="color: var(--primary-color); font-weight: 600;">94% SEQUENCED - การวิเคราะห์เสร็จสมบูรณ์</p>
        </div>
        
        <div class="dashboard-grid">
            
            <!-- Left: Metrics -->
            <div class="metrics-col">
                <h3>มาตรวัดมิติการอ่าน</h3>
                
                <div class="metric-card">
                    <div class="metric-head">
                        <h4>ความลึกซึ้งทางอารมณ์</h4>
                        <span id="m-emo-val">0%</span>
                    </div>
                    <div class="metric-bar-bg"><div class="metric-bar-fill" id="m-emo-bar"></div></div>
                    <p class="metric-desc">ระดับความเข้มข้นของการสั่นสะเทือนอารมณ์ความรู้สึก</p>
                </div>
                
                <div class="metric-card alt-color">
                    <div class="metric-head">
                        <h4>ความซับซ้อนเชิงความคิด</h4>
                        <span id="m-int-val">0%</span>
                    </div>
                    <div class="metric-bar-bg"><div class="metric-bar-fill" id="m-int-bar"></div></div>
                    <p class="metric-desc">การท้าทายกรอบความคิดและปรัชญาเบื้องหลัง</p>
                </div>
                
                <div class="metric-card alt-color">
                    <div class="metric-head">
                        <h4>ความเร็วในการเดินเรื่อง</h4>
                        <span id="m-pace-val">0%</span>
                    </div>
                    <div class="metric-bar-bg"><div class="metric-bar-fill" id="m-pace-bar"></div></div>
                    <p class="metric-desc">จังหวะการเล่าเรื่องและการสร้างความตึงเครียด</p>
                </div>
                
                <div class="metric-card">
                    <div class="metric-head">
                        <h4>การดำดิ่งสู่โลกภายใน</h4>
                        <span id="m-inty-val">0%</span>
                    </div>
                    <div class="metric-bar-bg"><div class="metric-bar-fill" id="m-inty-bar"></div></div>
                    <p class="metric-desc">การเจาะลึกเข้าไปในความนึกคิดและจิตใจของตัวละคร</p>
                </div>
            </div>
            
            <!-- Center: Neural -->
            <div class="neural-col">
                <svg class="neural-wire" viewBox="0 0 100 800" preserveAspectRatio="none">
                    <path d="M50 0 Q 30 150 50 300 T 50 600 T 50 800" />
                </svg>
                
                <div class="neural-node node-small">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                
                <div class="neural-node node-large">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                </div>
                
                <div class="book-cover-node">
                    <img src="/assets/image/minilogo.png" id="resultCover" alt="ปกหนังสือ">
                    <a href="#" id="buyLink" class="buy-btn-float">เปิดอ่านเลย</a>
                </div>
                
                <div class="neural-node node-small" style="background:var(--secondary-color); transform: translateX(30px);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                </div>
                
                <div class="convergence-label">จุดบรรจบทางความสนใจ</div>
            </div>
            
            <!-- Right: Insight -->
            <div class="insight-col">
                <div class="insight-card">
                    <div class="insight-header">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        <h3>บทวิเคราะห์จาก AI</h3>
                    </div>
                    
                    <div class="insight-quote" id="insightQuote">"วาทกรรมสรุปจะแสดงที่นี่"</div>
                    
                    <div class="insight-text">
                        <h4>เหตุผลที่เล่มนี้คู่ควรกับคุณ:</h4>
                        <p id="insightText">กำลังประมวลผลบทวิเคราะห์...</p>
                    </div>
                    
                    <div class="tags-container" id="tagsContainer">
                        <!-- Tags injected via JS -->
                    </div>
                </div>
            </div>
            
        </div>
        
        <div class="btn-reset">
            <button type="button" onclick="backToGrid()">กลับไปดู 12 เล่ม (Back to Grid)</button>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../components/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const savedDNA = JSON.parse(localStorage.getItem('wanawan_dna') || 'null');
    if (savedDNA) {
        document.getElementById('dnaPace').value = savedDNA.pace;
        document.getElementById('dnaComplexity').value = savedDNA.complexity;
        document.getElementById('dnaTone').value = savedDNA.tone;
    }
});

function getEnvironmentContext() {
    const hour = new Date().getHours();
    let timeContext = 'ระหว่างวัน';
    if (hour >= 5 && hour < 10) timeContext = 'ยามเช้า';
    else if (hour >= 10 && hour < 16) timeContext = 'กลางวัน';
    else if (hour >= 16 && hour < 20) timeContext = 'ยามเย็น';
    else timeContext = 'ก่อนนอน';
    
    return {
        time: timeContext,
        environment: 'สภาพแวดล้อมปัจจุบัน'
    };
}

function backToGrid() {
    document.getElementById('resultArea').classList.remove('show');
    setTimeout(() => {
        document.getElementById('resultArea').style.display = 'none';
        document.getElementById('gridArea').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, 300);
}

function resetGenome() {
    document.getElementById('gridArea').style.display = 'none';
    document.getElementById('resultArea').style.display = 'none';
    document.getElementById('setupPanel').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function animateValue(id, val) {
    document.getElementById(id + '-val').textContent = val + '%';
    setTimeout(() => {
        document.getElementById(id + '-bar').style.width = val + '%';
    }, 50);
}

// Global scope to save DNA
let currentDNA = null;
let currentAttention = '';

document.getElementById('analyzeBtn').addEventListener('click', async () => {
    const pace = document.getElementById('dnaPace').value;
    const complexity = document.getElementById('dnaComplexity').value;
    const tone = document.getElementById('dnaTone').value;
    const attention = document.getElementById('attentionInput').value;
    
    currentDNA = { pace, complexity, tone };
    currentAttention = attention;
    
    localStorage.setItem('wanawan_dna', JSON.stringify(currentDNA));
    
    const btn = document.getElementById('analyzeBtn');
    const spinner = document.getElementById('spinner');
    
    btn.disabled = true;
    spinner.style.display = 'block';
    btn.querySelector('span').textContent = 'กำลังค้นหา 12 เล่ม...';
    
    try {
        const response = await fetch('/pages/handlers/book_genome_search_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                dna: currentDNA,
                attention: currentAttention,
                context: getEnvironmentContext()
            })
        });
        
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'ระบบขัดข้อง');
        
        // Render Grid
        const grid = document.getElementById('booksGridContainer');
        grid.innerHTML = '';
        data.books.forEach(book => {
            const card = document.createElement('div');
            card.className = 'book-card-genome';
            card.innerHTML = `
                <img src="${book.image_cover}" alt="ปกหนังสือ">
                <div class="book-card-genome-info">
                    <h3>${book.title}</h3>
                    <p>${book.author || 'ไม่ระบุ'}</p>
                    <button type="button" class="btn-deep-dive" onclick="deepDive(${book.book_id})">เจาะลึก DNA เล่มนี้</button>
                </div>
            `;
            grid.appendChild(card);
        });
        
        document.getElementById('setupPanel').style.display = 'none';
        document.getElementById('gridArea').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
    } catch (err) {
        alert('เกิดข้อผิดพลาด: ' + err.message);
    } finally {
        btn.disabled = false;
        spinner.style.display = 'none';
        btn.querySelector('span').textContent = 'เริ่มค้นหาหนังสือ 12 เล่ม (Broad Search)';
    }
});

async function deepDive(bookId) {
    document.getElementById('gridArea').style.display = 'none';
    document.getElementById('resultArea').style.display = 'block';
    
    document.getElementById('bookTitleDisplay').textContent = 'กำลังถอดรหัสเชิงลึก...';
    
    try {
        const response = await fetch('/pages/handlers/book_genome_analyze_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_book_id: bookId,
                dna: currentDNA,
                attention: currentAttention,
                context: getEnvironmentContext()
            })
        });
        
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'ระบบขัดข้อง');
        
        const ai = data.ai_data;
        const book = data.book;
        
        document.getElementById('bookTitleDisplay').textContent = book.title;
        
        // Metrics
        animateValue('m-emo', ai.metrics.emotional_depth || 85);
        animateValue('m-int', ai.metrics.intellectual_rigor || 92);
        animateValue('m-pace', ai.metrics.narrative_pace || 45);
        animateValue('m-inty', ai.metrics.interiority || 78);
        
        // Center
        document.getElementById('resultCover').src = book.image_cover;
        document.getElementById('buyLink').href = `/pages/book_detail.php?id=${bookId}&source=genome`;
        
        // Insight
        document.getElementById('insightQuote').textContent = `"${ai.insight_quote}"`;
        document.getElementById('insightText').textContent = ai.insight_text;
        
        const tagsContainer = document.getElementById('tagsContainer');
        tagsContainer.innerHTML = '';
        if (ai.tags && Array.isArray(ai.tags)) {
            ai.tags.forEach(tag => {
                const span = document.createElement('span');
                span.className = 'tag';
                span.textContent = tag;
                tagsContainer.appendChild(span);
            });
        }
        
        void document.getElementById('resultArea').offsetWidth;
        document.getElementById('resultArea').classList.add('show');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
    } catch (err) {
        alert('เกิดข้อผิดพลาด: ' + err.message);
        backToGrid();
    }
}
</script>
</body>
</html>
