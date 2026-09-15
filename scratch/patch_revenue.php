<?php
$file = 'c:\laragon\www\wanawan_project\admin\revenue.php';
$content = file_get_contents($file);

// 1. Update the SQL query for $history
$old_query = <<< 'PHP'
        SELECT o.*,
               (SELECT GROUP_CONCAT(DISTINCT COALESCE(s.shop_name, p.company_name, u.display_name) SEPARATOR ', ')
                FROM order_items oi
                JOIN books b ON b.book_id = oi.book_id
                LEFT JOIN shops s ON b.seller_type = 'shop' AND b.seller_id = s.shop_id
                LEFT JOIN publishers p ON b.seller_type = 'publisher' AND b.seller_id = p.publisher_id
                LEFT JOIN users u ON b.seller_type = 'user' AND b.seller_id = u.user_id
                WHERE oi.order_id = o.order_id) as seller_names
        FROM orders o
PHP;
$new_query = <<< 'PHP'
        SELECT o.*,
               (SELECT GROUP_CONCAT(DISTINCT COALESCE(s.shop_name, p.company_name, u.display_name) SEPARATOR ', ')
                FROM order_items oi
                JOIN books b ON b.book_id = oi.book_id
                LEFT JOIN shops s ON b.seller_type = 'shop' AND b.seller_id = s.shop_id
                LEFT JOIN publishers p ON b.seller_type = 'publisher' AND b.seller_id = p.publisher_id
                LEFT JOIN users u ON b.seller_type = 'user' AND b.seller_id = u.user_id
                WHERE oi.order_id = o.order_id) as seller_names,
               (SELECT MAX(b.seller_type) FROM order_items oi JOIN books b ON oi.book_id = b.book_id WHERE oi.order_id = o.order_id) as seller_type
        FROM orders o
PHP;
$content = str_replace($old_query, $new_query, $content);

// 2. Replace CSS
$css_start = '<style>';
$css_end = '</style>';
$css_start_pos = strpos($content, $css_start);
$css_end_pos = strpos($content, $css_end, $css_start_pos) + strlen($css_end);
// Wait, there are TWO <style> tags in the head. Let's just do a regex replace for the entire head section's CSS.
$new_css = <<< 'HTML'
    <style>
        body { background: #f8fafc; margin: 0; display: flex; min-height: 100vh; font-family: 'Prompt', sans-serif; }
        .admin-main-wrap { flex: 1; min-width: 0; padding: 30px 40px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; }
        .page-title { margin: 0; color: #0f172a; font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        .page-subtitle { color: #64748b; font-size: 13px; margin-top: 8px; }
        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #ecfdf5; color: #10b981; border-radius: 20px; font-size: 11px; font-weight: 600; border: 1px solid #a7f3d0; }
        .status-badge::before { content: ""; width: 6px; height: 6px; background: #10b981; border-radius: 50%; }
        
        .header-actions { display: flex; gap: 12px; }
        .btn-outline { background: white; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 8px; color: #475569; font-size: 13px; font-family: inherit; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-outline:hover { background: #f1f5f9; border-color: #cbd5e1; color: #0f172a; }
        .btn-dark { background: #0f172a; border: none; padding: 10px 16px; border-radius: 8px; color: white; font-size: 13px; font-family: inherit; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-dark:hover { background: #1e293b; }
        
        /* Dashboard Layout */
        .dashboard-grid { display: grid; grid-template-columns: 380px 1fr; gap: 20px; margin-bottom: 25px; align-items: stretch; }
        
        /* Green Wallet Card */
        .wallet-card { background: #044b3f; border-radius: 16px; padding: 30px; color: white; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; min-height: 220px; box-sizing: border-box; }
        .wallet-card::after { content: "฿"; position: absolute; right: 10px; bottom: -20px; font-size: 180px; font-weight: 800; color: rgba(255,255,255,0.04); line-height: 1; pointer-events: none; }
        .wallet-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; position: relative; z-index: 2; }
        .wallet-card-title { font-size: 13px; font-weight: 600; opacity: 0.9; }
        .wallet-card-tag { background: rgba(255,255,255,0.15); padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .wallet-card-amount { font-size: 46px; font-weight: bold; margin-bottom: 5px; display: flex; align-items: baseline; gap: 8px; position: relative; z-index: 2; }
        .wallet-card-amount span { font-size: 32px; font-weight: normal; }
        .wallet-card-note { font-size: 11px; opacity: 0.8; display: flex; align-items: center; gap: 6px; margin-bottom: 30px; position: relative; z-index: 2; }
        
        .wallet-actions { display: flex; gap: 12px; position: relative; z-index: 2; }
        .wallet-actions button { flex: 1; border-radius: 8px; padding: 12px; font-weight: 600; font-size: 13px; font-family: inherit; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-withdraw { background: white; color: #044b3f; border: none; }
        .btn-withdraw:hover { background: #f8fafc; }
        .btn-manage-bank { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }
        .btn-manage-bank:hover { background: rgba(255,255,255,0.2); }
        
        /* Stats Box */
        .stats-box { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 25px 30px; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box; }
        .stats-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .stats-header h3 { margin: 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .stats-filters { display: flex; gap: 10px; }
        .stats-filters select { padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: inherit; font-size: 12px; color: #475569; outline: none; background: white; }
        
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .stat-item { background: #f8fafc; border: 1px solid #f1f5f9; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-item span { display: block; color: #64748b; font-size: 12px; margin-bottom: 6px; }
        .stat-item strong { display: block; color: #0f172a; font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .stat-item .sub { font-size: 11px; color: #94a3b8; }
        .growth-pos { color: #10b981 !important; }
        .growth-neg { color: #ef4444 !important; }
        .growth-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; background: #ecfdf5; color: #10b981; margin-top: 4px; }

        /* History Layout */
        .history-wrap { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; }
        .history-tabs-container { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px; }
        .history-tabs { display: flex; gap: 20px; }
        .history-tab { color: #64748b; text-decoration: none; font-size: 14px; font-weight: 600; position: relative; padding-bottom: 12px; transition: color 0.2s; }
        .history-tab.active { color: #0f172a; }
        .history-tab.active::after { content: ''; position: absolute; bottom: -13px; left: 0; right: 0; height: 2px; background: #0f172a; border-radius: 2px 2px 0 0; }
        
        .history-filters { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; }
        .history-search { flex: 1; position: relative; }
        .history-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px; }
        .history-search input { width: 100%; padding: 8px 12px 8px 35px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; outline: none; box-sizing: border-box; }
        
        .tablebox { width: 100%; overflow-x: auto; }
        .orders { width: 100%; border-collapse: collapse; min-width: 900px; }
        .orders th { background: transparent; color: #64748b; font-weight: 600; font-size: 12px; padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .orders td { padding: 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 13px; vertical-align: middle; }
        .orders tr:hover { background: #f8fafc; cursor: pointer; }
        .orders tr:last-child td { border-bottom: none; }
        
        /* Seller Cell */
        .seller-cell { display: flex; align-items: center; gap: 10px; }
        .seller-initial { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #0f172a; font-size: 14px; }
        .seller-info strong { display: block; color: #0f172a; font-size: 13px; margin-bottom: 2px; }
        .seller-info span { display: block; color: #94a3b8; font-size: 11px; }

        .type-shop { background: #e0f2fe; color: #0284c7; }
        .type-publisher { background: #fef3c7; color: #d97706; }
        .type-user { background: #f3e8ff; color: #9333ea; }
        
        .status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-pill.success { background: #ecfdf5; color: #10b981; border: 1px solid #a7f3d0; }
        .status-pill.success::before { content: ""; width: 6px; height: 6px; background: #10b981; border-radius: 50%; }

        .pagination-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; font-size: 12px; color: #64748b; padding: 0 10px; }
        .pagination-controls { display: flex; gap: 5px; }
        .page-btn { border: 1px solid #e2e8f0; background: white; padding: 4px 10px; border-radius: 6px; color: #64748b; cursor: pointer; font-family: inherit; font-size: 12px; }
        .page-btn.active { background: #044b3f; color: white; border-color: #044b3f; }
        
        .info-alert { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 15px 20px; margin-top: 25px; display: flex; align-items: flex-start; gap: 12px; font-size: 12px; color: #047857; line-height: 1.6; }
        .info-alert i { font-size: 16px; margin-top: 2px; }

        /* Modals */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
        .modal.open { display: flex; }
        .modal-box { background: #fff; width: 450px; border-radius: 20px; padding: 30px; position: relative; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .modal-close { position: absolute; right: 25px; top: 25px; font-size: 24px; color: #999; cursor: pointer; background: none; border: none; padding: 0; }
        .modal-title { margin: 0 0 25px 0; color: #0f172a; font-size: 20px; font-weight: bold; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; outline:none; }
        .form-group input:focus, .form-group select:focus { border-color: #0f172a; }
        
        .btn-submit { font-family: inherit; width: 100%; background: #0f172a; color: #fff; border: none; padding: 14px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #1e293b; }
        .btn-cancel { font-family: inherit; width: 100%; background: #f1f5f9; color: #475569; border: none; padding: 14px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.2s; }
        
        /* Bank List in Modal */
        .bank-item { border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; transition: border-color 0.2s; cursor: pointer; }
        .bank-item:hover { border-color: #cbd5e1; background: #f8fafc; }
        .bank-item.selected { border-color: #0f172a; background: #f8fafc; }
        .bank-info strong { display: block; color: #0f172a; font-size: 14px; margin-bottom: 4px; font-weight: 600; }
        .bank-info span { color: #64748b; font-size: 12px; }

    </style>
    <style>
        /* odModal Styling */
        .od-modal{position:fixed;inset:0;background:#0005;display:none;align-items:center;justify-content:center;padding:20px;z-index:9999;backdrop-filter:blur(2px)}
        .od-modal.open{display:flex}
        .od-box{width:min(680px,100%);max-height:90vh;overflow-y:auto;overflow-x:hidden;scrollbar-width:none;-ms-overflow-style:none;background:#f8fafc;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.22)}
        .od-box::-webkit-scrollbar{display:none}
        .od-head{padding:25px 30px;border-radius:20px 20px 0 0;color:#fff}
        .od-head h2{margin:0;font-size:20px;font-weight:bold;}
        .od-head p{margin:6px 0 0;font-size:13px;opacity:.9}
        .od-section{background:#fff;margin-bottom:8px;padding:25px 30px; border-radius:12px;}
        .od-section h3{margin:0 0 15px;font-size:15px;color:#0f172a;border-bottom:1px solid #f1f5f9;padding-bottom:12px;}
        .od-row{display:flex;justify-content:space-between;padding:8px 0;font-size:14px;color:#64748b}
        .od-row strong{color:#0f172a}
        .od-item{display:flex;gap:15px;align-items:center;padding:15px 0;border-bottom:1px solid #f1f5f9}
        .od-item:last-child{border:0}
        .od-item img{width:60px;height:80px;object-fit:cover;border-radius:8px;background:#f8fafc; border:1px solid #e2e8f0;}
        .od-item-info{flex:1}
        .od-item-title{font-weight:600;color:#0f172a;font-size:14px;margin:0 0 5px}
        .od-item-sub{font-size:12px;color:#64748b}
        .od-item-price{font-weight:600;color:#0f172a;white-space:nowrap}
        .od-tracking{padding:15px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;color:#334155;font-size:13px;margin-bottom:8px}
        .od-footer-btns{display:flex;gap:12px;flex-wrap:wrap;padding:20px 30px;background:#fff;border-radius:0 0 20px 20px}
        .od-btn{height:42px;padding:0 20px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;font:inherit;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;color:#475569;transition:all .2s}
        .od-btn.close-btn{margin-left:auto;border-color:#e2e8f0}
        .od-btn.close-btn:hover{background:#f1f5f9; color:#0f172a;}
        .grand-total{display:flex;justify-content:space-between;padding:15px 0 5px;font-size:16px;font-weight:700;color:#044b3f;border-top:1px dashed #e2e8f0;margin-top:10px}
    </style>
HTML;

$content = preg_replace('/<style>.*?<\/style>\s*<style>.*?<\/style>/s', $new_css, $content);

// 3. Replace the Body HTML Structure
$body_old = <<< 'HTML'
    <div class="admin-main-wrap">
        <h1 class="page-title">รายได้ของระบบ</h1>
HTML;
// We will replace the entire body section up to the Modals
// Let's use a regex to capture everything from <div class="admin-main-wrap"> to <!-- Modals -->
$body_new = <<< 'HTML'
    <div class="admin-main-wrap">
        <div class="page-header">
            <div>
                <h1 class="page-title">รายได้ของระบบ <span class="status-badge">อัปเดตเรียลไทม์ (Active)</span></h1>
                <div class="page-subtitle">ภาพรวมรายได้ค่าธรรมเนียมส่วนต่าง (GP) ยอดเงินที่สามารถถอนได้ และประวัติการทำรายการทางการเงินทั้งหมด</div>
            </div>
            <div class="header-actions">
                <button class="btn-outline"><i class="fas fa-file-export"></i> ส่งออกรายงาน (Export)</button>
                <button class="btn-dark" onclick="location.reload()"><i class="fas fa-sync-alt"></i> รีเฟรชสถานะ</button>
            </div>
        </div>

        <?php if($success): ?>
            <script>Swal.fire({icon: 'success', title: 'สำเร็จ', text: '<?= htmlspecialchars($success) ?>', timer: 2000, showConfirmButton: false});</script>
        <?php endif; ?>
        <?php if($error): ?>
            <script>Swal.fire({icon: 'error', title: 'ผิดพลาด', text: '<?= htmlspecialchars($error) ?>'});</script>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Left: Wallet Card -->
            <div class="wallet-card">
                <div>
                    <div class="wallet-card-header">
                        <span class="wallet-card-title">ยอดเงินที่สามารถถอนได้</span>
                        <span class="wallet-card-tag">พร้อมเบิกจ่าย</span>
                    </div>
                    <div class="wallet-card-amount">
                        <span>฿</span>
                        <?= number_format($availableBalance, 2) ?>
                    </div>
                    <div class="wallet-card-note">
                        <i class="far fa-clock"></i> ยอดสะสมรอรอบโอนถัดไป • ตัดรอบทุกวันศุกร์
                    </div>
                </div>
                <div class="wallet-actions">
                    <button class="btn-withdraw" onclick="openWithdrawModal()">
                        <i class="fas fa-download"></i> ถอนเงิน
                    </button>
                    <button class="btn-manage-bank" onclick="openBankModal()">
                        <i class="fas fa-id-card"></i> จัดการบัญชี
                    </button>
                </div>
            </div>

            <!-- Right: Stats Box -->
            <div class="stats-box">
                <div class="stats-header">
                    <h3><i class="fas fa-chart-bar" style="color:#044b3f;"></i> สถิติภาพรวม</h3>
                    <form method="get" class="stats-filters">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                        <select name="stat_year" onchange="this.form.submit()">
                            <?php for($y=date('Y'); $y>=date('Y')-3; $y--): ?>
                                <option value="<?= $y ?>" <?= $statYear==$y?'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="stat_month" onchange="this.form.submit()">
                            <option value="0">ตลอดทั้งปี</option>
                            <?php 
                            $thaiMonths = ['1'=>'มกราคม','2'=>'กุมภาพันธ์','3'=>'มีนาคม','4'=>'เมษายน','5'=>'พฤษภาคม','6'=>'มิถุนายน','7'=>'กรกฎาคม','8'=>'สิงหาคม','9'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];
                            for($m=1; $m<=12; $m++): ?>
                                <option value="<?= $m ?>" <?= $statMonth==$m?'selected':'' ?>><?= $thaiMonths[$m] ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <span>รายได้สุทธิ (ช่วงที่เลือก)</span>
                        <strong>฿<?= number_format($statEarnings, 2) ?></strong>
                        <div class="sub">ค่าธรรมเนียม GP รวม</div>
                    </div>
                    <div class="stat-item">
                        <span>ถอนเงินไปแล้วทั้งหมด</span>
                        <strong>฿<?= number_format($totalWithdrawn, 2) ?></strong>
                        <div class="sub">โอนเข้าธนาคารสำเร็จ</div>
                    </div>
                    <div class="stat-item">
                        <span>คำสั่งซื้อที่สำเร็จแล้ว</span>
                        <strong><?= number_format($statOrders) ?></strong>
                        <div class="sub">รายการแลกเปลี่ยน/สั่งซื้อ</div>
                    </div>
                    <div class="stat-item">
                        <span>อัตราการเติบโต (เทียบช่วงก่อนหน้า)</span>
                        <strong class="<?= $growthClass ?>"><?= $growth > 0 ? '+' : '' ?><?= number_format($growth, 1) ?>%</strong>
                        <div class="growth-badge">สถานะสม่ำเสมอ</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Wrap -->
        <div class="history-wrap">
            <div class="history-tabs-container">
                <div class="history-tabs">
                    <a href="?tab=revenue&stat_year=<?= $statYear ?>&stat_month=<?= $statMonth ?>" class="history-tab <?= $tab==='revenue' ? 'active' : '' ?>">ประวัติรายได้จาก GP <span style="background:#ecfdf5; color:#10b981; padding:2px 8px; border-radius:10px; font-size:11px; margin-left:6px;"><?= count($history) ?></span></a>
                    <a href="?tab=withdraw&stat_year=<?= $statYear ?>&stat_month=<?= $statMonth ?>" class="history-tab <?= $tab==='withdraw' ? 'active' : '' ?>">ประวัติการถอน (Transaction History)</a>
                    <a href="#" class="history-tab" style="color:#cbd5e1;">รอบการโอนเงิน (Payout Cycles)</a>
                </div>
                <div style="font-size:12px; color:#94a3b8;">รอบคำนวณมาตรฐาน: คำสั่งซื้อสำเร็จสมบูรณ์</div>
            </div>

            <div class="history-filters">
                <div class="history-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="ค้นหาหมายเลขคำสั่งซื้อ (#WN...), ชื่อร้านค้า...">
                </div>
                <select style="padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #475569; outline: none;">
                    <option>ประเภทร้านค้า: ทั้งหมด</option>
                </select>
                <select style="padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #475569; outline: none;">
                    <option>ช่วงวันที่: 30 วันล่าสุด</option>
                </select>
                <button class="btn-outline" style="padding:8px 12px;"><i class="fas fa-filter"></i></button>
            </div>

            <div class="tablebox">
                <table class="orders">
                    <thead>
                        <?php if($tab === 'revenue'): ?>
                            <tr>
                                <th>วันที่-เวลา</th>
                                <th>หมายเลขคำสั่งซื้อ</th>
                                <th>ร้านค้าที่ขาย</th>
                                <th>ยอดรวมคำสั่งซื้อ</th>
                                <th style="text-align:center">อัตรา GP</th>
                                <th style="text-align:right">รายได้ระบบ (GP)</th>
                                <th>สถานะ</th>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th>วันที่-เวลา</th>
                                <th>บัญชีปลายทาง</th>
                                <th style="text-align:right">จำนวนเงิน (บาท)</th>
                                <th>สถานะ</th>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php if (!$history): ?>
                            <tr><td colspan="7" style="text-align:center;padding:70px;color:#888;">ยังไม่มีประวัติธุรกรรม</td></tr>
                        <?php else: foreach ($history as $index => $h): ?>
                            <?php if($tab === 'revenue'): 
                                $orderJson = htmlspecialchars(json_encode([
                                    'number' => $h['order_number'],
                                    'seller' => $h['seller_names'] ?: 'ไม่ระบุ',
                                    'buyer' => $h['shipping_name'],
                                    'phone' => $h['shipping_phone'],
                                    'address' => $h['shipping_address'],
                                    'courier' => $h['shipping_courier'] ?? '',
                                    'tracking' => $h['tracking_number'] ?? '',
                                    'subtotal' => (float)($h['subtotal'] ?? 0),
                                    'shipping_fee' => (float)($h['shipping_fee'] ?? 0),
                                    'total' => (float)($h['total_amount'] ?? 0),
                                    'gp_amount' => (float)($h['gp_amount'] ?? 0),
                                    'items' => array_map(fn($i) => [
                                        'title' => $i['title'] ?: 'หนังสือ',
                                        'author' => $i['author'] ?? '',
                                        'qty' => $i['quantity'],
                                        'price' => (float)$i['price_at_purchase'],
                                        'img' => orderImage($i['image_cover']) ?? ''
                                    ], $h['items'])
                                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                
                                // Determine Seller Details
                                $sellerName = $h['seller_names'] ?: 'ไม่ระบุ';
                                $initial = mb_substr($sellerName, 0, 1);
                                $typeClass = 'type-user';
                                $typeText = 'ผู้ใช้ทั่วไป (P2P Exchange)';
                                $gpRate = '15%';
                                
                                if ($h['seller_type'] === 'publisher') {
                                    $typeClass = 'type-publisher';
                                    $typeText = 'สำนักพิมพ์พันธมิตร (Publisher)';
                                    $gpRate = '10%';
                                } elseif ($h['seller_type'] === 'shop') {
                                    $typeClass = 'type-shop';
                                    $typeText = 'ร้านค้าแนะนำ (Verified)';
                                    $gpRate = '10%';
                                }
                            ?>
                                <tr class="clickable-row" onclick="openOrderDetail(this)" data-od='<?= $orderJson ?>'>
                                    <td>
                                        <?= $h['delivered_at'] ? date('d M Y', strtotime($h['delivered_at'])) : '-' ?><br>
                                        <span style="font-size:11px; color:#94a3b8;"><?= $h['delivered_at'] ? date('H:i', strtotime($h['delivered_at'])) : '-' ?> น.</span>
                                    </td>
                                    <td>
                                        <strong>#<?= htmlspecialchars($h['order_number']) ?></strong> <i class="far fa-copy" style="color:#cbd5e1; font-size:12px; margin-left:4px;"></i><br>
                                        <span style="color:#0ea5e9; font-size:11px;">ดูรายละเอียด &rsaquo;</span>
                                    </td>
                                    <td>
                                        <div class="seller-cell">
                                            <div class="seller-initial <?= $typeClass ?>"><?= htmlspecialchars($initial) ?></div>
                                            <div class="seller-info">
                                                <strong><?= htmlspecialchars($sellerName) ?></strong>
                                                <span><?= $typeText ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>฿<?= number_format($h['subtotal'], 2) ?></td>
                                    <td style="text-align:center; color:#64748b;"><?= $gpRate ?></td>
                                    <td style="text-align:right; color:#10b981; font-weight:700;">+ ฿<?= number_format($h['gp_amount'], 2) ?></td>
                                    <td><span class="status-pill success">เข้ากระเป๋าเงินแล้ว</span></td>
                                </tr>
                            <?php else: ?>
                                <?php $bInfo = json_decode($h['bank_account_info'], true); ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($h['created_at'])) ?><br><span style="font-size:11px; color:#94a3b8;"><?= date('H:i', strtotime($h['created_at'])) ?> น.</span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($bInfo['bank_name'] ?? 'ไม่ระบุ') ?></strong><br>
                                        <span style="font-size:12px;color:#64748b;"><?= htmlspecialchars($bInfo['account_number'] ?? '') ?> (<?= htmlspecialchars($bInfo['account_name'] ?? '') ?>)</span>
                                    </td>
                                    <td style="text-align:right; font-weight:700;">฿<?= number_format($h['amount'], 2) ?></td>
                                    <td><span class="status-pill success">สำเร็จ</span></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-footer">
                <div>แสดง <strong>1 - <?= count($history) ?></strong> จากทั้งหมด <strong><?= count($history) ?></strong> รายการ</div>
                <div class="pagination-controls">
                    <button class="page-btn">ก่อนหน้า</button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn">ถัดไป</button>
                </div>
            </div>
        </div>
        
        <div class="info-alert">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>เกณฑ์การคำนวณรายได้ส่วนแบ่งระบบ (System Commission Policy):</strong> รายได้ส่วนแบ่งระบบ (GP) จะถูกคำนวณและโอนยอดเข้ากระเป๋า 'ยอดเงินที่สามารถถอนได้' โดยอัตโนมัติทันทีที่การแลกเปลี่ยนหรือคำสั่งซื้อผ่านการตรวจสอบและลูกค้ายืนยันรับพัสดุเรียบร้อยแล้ว หากมีรายการข้อพิพาทหรือการขอคืนสินค้า รายได้ในส่วนนั้นจะถูกระงับชั่วคราวตามมาตรฐาน SOP ส่วนกลาง
            </div>
        </div>

    </div>
HTML;

$content = preg_replace('/<div class="admin-main-wrap">.*?<!-- Modals -->/s', $body_new . "\n    <!-- Modals -->", $content);

// 4. Update Modal Header Background Color
$content = str_replace('background: #00796B;', 'background: #0f172a;', $content);

file_put_contents($file, $content);
echo "Patched revenue UI!";
