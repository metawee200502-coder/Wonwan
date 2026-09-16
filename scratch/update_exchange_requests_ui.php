<?php
$file_path = 'C:/laragon/www/wanawan_project/pages/exchange_requests.php';
$content = file_get_contents($file_path);

// Find where HTML starts
$html_start = strpos($content, '?>' . "\n" . '<style>');
if ($html_start === false) {
    $html_start = strpos($content, '?>' . "\r\n" . '<style>');
}

if ($html_start !== false) {
    $php_logic = substr($content, 0, $html_start + 2); // Includes ?>
    
    // Modify PHP logic to add search and counts
    $php_logic = str_replace(
        '$tab=($_GET[\'tab\']??\'received\')===\'sent\'?\'sent\':\'received\';$status=$_GET[\'status\']??\'\';$category=trim($_GET[\'category\']??\'\');',
        '$tab=($_GET[\'tab\']??\'received\')===\'sent\'?\'sent\':\'received\';$status=$_GET[\'status\']??\'\';$category=trim($_GET[\'category\']??\'\');$search=trim($_GET[\'search\']??\'\');',
        $php_logic
    );
    
    $php_logic = str_replace(
        'if($category!==\'\'){$where[]=\'target.category=:category\';$params[\':category\']=$category;}',
        'if($category!==\'\'){$where[]=\'target.category=:category\';$params[\':category\']=$category;}
if($search!==\'\'){$where[]=\'(target.title LIKE :search OR offer.title LIKE :search OR requester.display_name LIKE :search OR owner.display_name LIKE :search OR er.request_id = :search_exact)\';$params[\':search\']=\'%\'.$search.\'%\';$params[\':search_exact\']=str_replace(\'EX-\',\'\',$search);}',
        $php_logic
    );
    
    $php_logic = str_replace(
        '$total=$tab===\'received\'?$pdo->prepare("SELECT COUNT(*) FROM exchange_requests er JOIN exchanges e ON e.exchange_id=er.exchange_id WHERE e.owner_id=?"):$pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE requester_id=?");$total->execute([$userId]);$allCount=(int)$total->fetchColumn();',
        '$total=$tab===\'received\'?$pdo->prepare("SELECT COUNT(*) FROM exchange_requests er JOIN exchanges e ON e.exchange_id=er.exchange_id WHERE e.owner_id=?"):$pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE requester_id=?");$total->execute([$userId]);$allCount=(int)$total->fetchColumn();
$rcStmt=$pdo->prepare("SELECT COUNT(*) FROM exchange_requests er JOIN exchanges e ON e.exchange_id=er.exchange_id WHERE e.owner_id=?");$rcStmt->execute([$userId]);$rcCount=(int)$rcStmt->fetchColumn();
$stStmt=$pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE requester_id=?");$stStmt->execute([$userId]);$stCount=(int)$stStmt->fetchColumn();',
        $php_logic
    );

    // Provide the new HTML/CSS structure
    $new_html = <<<'EOD'

<style>
* {box-sizing:border-box}
body {background: #f9f9f9;}
.account{display:flex;min-height:850px;background:#fdfdfd;}
.manage{flex:1;max-width:1160px;margin:0 auto;padding:36px 42px 80px;min-width:0}
.header-area { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.header-text { max-width: 60%; }
.title{margin:0 0 8px;color:#1f2937;font-size:32px;font-weight:700;}
.subtitle { color: #6b7280; font-size: 15px; margin: 0; }
.btn-add { padding: 10px 20px; border: 1px solid #6b7280; border-radius: 20px; background: #fff; color: #374151; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; }
.btn-add:hover { background: #f3f4f6; }
.tabs{display:flex;gap:12px;margin-bottom:24px; width:fit-content}
.tabs a{padding:8px 24px;border-radius:24px;background:#fff;border:1px solid #e5e7eb;color:#6b7280;text-decoration:none;font-weight:600;font-size:14px;transition:all .2s; display:flex; align-items:center; gap:8px;}
.tabs a .badge { background: #f3f4f6; color: #4b5563; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; }
.tabs a.active{background:#115e59;color:#fff;border-color:#115e59;}
.tabs a.active .badge { background: #fff; color: #115e59; }
.tabs a:not(.active):hover{background:#f9fafb;}

.tools-container { background: #fff; border: 1px solid #f3f4f6; border-radius: 12px; padding: 12px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.filters{display:flex;gap:12px; flex:1; align-items:center;}
.search-box { position: relative; width: 350px; }
.search-box input { width: 100%; height: 40px; padding: 0 16px 0 36px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; font: inherit; outline: none; }
.search-box svg { position: absolute; left: 12px; top: 12px; color: #9ca3af; width: 16px; height: 16px; }
.filters select{width:180px;height:40px;border:1px solid #e5e7eb;border-radius:8px;padding:0 12px;background:#f9fafb;font: inherit;color:#4b5563; outline: none; cursor:pointer;}
.result{color:#6b7280;font-size: 14px; font-weight: 500; background: #f3f4f6; padding: 8px 16px; border-radius: 8px;}

.notice{padding:13px 17px;border-radius:9px;margin-bottom:16px}.ok{background:#e8f4e4;color:#3d7139}.bad{background:#fde5e1;color:#a44943}

.request-list{display:grid;gap:24px}
.request-card{border:1px solid #f3f4f6;border-radius:16px;background:#fff;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.03); display:flex; flex-direction:column;}
.request-head{display:flex;align-items:center;justify-content:space-between;padding:20px 24px; border-bottom:1px solid #f9fafb;}
.person{display:flex;align-items:center;gap:16px}
.avatar{width:48px;height:48px;border-radius:50%;overflow:hidden;background:#fce7f3;display:grid;place-items:center;color:#db2777;font-weight:700;font-size:20px;}
.avatar img{width:100%;height:100%;object-fit:cover}
.person-info { display: flex; flex-direction: column; gap: 4px; }
.name-row { display: flex; align-items: center; gap: 8px; }
.person-name { color: #111827; font-size: 16px; font-weight: 700; }
.role-badge { background: #fef3c7; color: #b45309; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 12px; }
.req-id { color: #9ca3af; font-size: 13px; }
.status-group { display: flex; align-items: center; gap: 8px; }
.status-pill { display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.status-pill.success { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
.status-pill.success-text { color: #059669; background: transparent; }
.status-pill.pending { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
.status-pill.pending-text { color: #4b5563; background: #f3f4f6; }
.status-pill.rejected { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

.request-body{display:grid;grid-template-columns: 1fr 340px; }
.exchange-pair{display:grid;grid-template-columns:1fr 80px 1fr;align-items:center;gap:16px;padding:24px; background: #fcfcfc; border-radius: 12px; margin: 24px 0 24px 24px; border: 1px solid #f3f4f6;}
.book{text-align:center;min-width:0; display:flex; flex-direction:column; align-items:center;}
.book-label { background: #fffbeb; color: #d97706; font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 12px; margin-bottom: 16px; border: 1px solid #fde68a;}
.cover{width:140px;height:200px;margin:auto;border-radius:8px;background:#f3f4f6;overflow:hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);}
.cover img{width:100%;height:100%;object-fit:cover}
.book-title {display:block;margin-top:16px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; font-weight: 700; width: 100%; font-size: 15px;}
.book-cond {display:block;color:#6b7280; font-size: 13px; margin-top: 4px;}
.swap{display:flex;flex-direction:column;align-items:center;justify-content:center;color:#6b7280;text-align:center; gap:8px;}
.swap-icon { width: 44px; height: 44px; border-radius: 50%; background: #fff; border: 1px solid #e5e7eb; display: grid; place-items: center; color: #059669; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.swap-text { font-size: 12px; font-weight: 600; color: #4b5563; }

.request-side{padding:24px;display:flex;flex-direction:column; gap: 16px;}
.quote-box { background: #fffbeb; border-radius: 12px; padding: 16px; position: relative; color: #4b5563; font-style: italic; font-size: 14px; line-height: 1.6; display:flex; gap:12px; border: 1px solid #fef3c7; }
.quote-icon { color: #f59e0b; flex-shrink: 0; font-size: 24px; line-height: 1; font-family: serif; font-weight: bold; }
.actions{display:flex;flex-direction:column;gap:10px;margin-top:auto}
.btn{min-height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 16px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s; font-size:14px; gap:8px;}
.btn-outline { background: #fff; border: 1px solid #d1d5db; color: #374151; }
.btn-outline:hover { background: #f9fafb; }
.btn-primary { background: #115e59; border: 1px solid #115e59; color: #fff; }
.btn-primary:hover { background: #0f514e; }
.btn-text-danger { background: transparent; border: none; color: #dc2626; font-size: 13px; text-decoration: underline; margin-top: 4px; }
.btn-text-danger:hover { color: #b91c1c; }

.status-dropdown { width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 13px; color: #4b5563; background: #f9fafb; cursor: pointer; outline: none; margin-top: 8px;}
.footer-note { border-top: 1px solid #f3f4f6; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; background: #fafafa; font-size: 13px; color: #6b7280; }
.footer-note-link { color: #4b5563; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 4px; }
.footer-note-link:hover { text-decoration: underline; }
.footer-success { color: #059669; display: flex; align-items: center; gap: 6px; }

.empty{padding:90px 20px;text-align:center;border:1px dashed #d1d5db;border-radius:14px;color:#9ca3af; background: #fff; font-size: 15px;}

.bottom-banner { background: #115e59; border-radius: 16px; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; margin-top: 40px; color: #fff; }
.banner-left { display: flex; align-items: center; gap: 20px; }
.shield-icon { width: 56px; height: 56px; background: rgba(255,255,255,0.1); border-radius: 12px; display: grid; place-items: center; flex-shrink: 0; }
.banner-text h3 { margin: 0 0 8px 0; font-size: 18px; font-weight: 700; }
.banner-text p { margin: 0; font-size: 14px; color: rgba(255,255,255,0.8); line-height: 1.5; max-width: 600px; }
.banner-btn { background: #fff; color: #115e59; padding: 12px 24px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 14px; transition: background .2s; white-space: nowrap; }
.banner-btn:hover { background: #f3f4f6; }

@media(max-width:1050px){.manage{padding:30px 24px}.request-body{grid-template-columns:1fr}.request-side{border-left:0;border-top:1px solid #f3f4f6; margin: 0 24px; padding-left: 0; padding-right: 0;}.exchange-pair {margin: 24px;}.bottom-banner { flex-direction: column; gap: 24px; text-align: center; align-items: stretch; }.banner-left { flex-direction: column; text-align: center; }.banner-btn { text-align: center; }}
@media(max-width:800px){.account{display:block}.manage{padding:28px 18px 60px}.tools-container{align-items:flex-start;flex-direction:column}.filters{width:100%; flex-direction: column;}.search-box, .search-box input, .filters select { width: 100%; }.tabs{overflow-x:auto}.tabs a{min-width:160px; white-space: nowrap;}.exchange-pair{padding:22px 16px}.header-area { flex-direction: column; gap: 16px; }.header-text { max-width: 100%; }}
@media(max-width:520px){.title{font-size:26px}.request-head{align-items:flex-start; flex-direction: column; gap: 12px;}.avatar{width:40px;height:40px}.exchange-pair{grid-template-columns:1fr 38px 1fr;gap:5px}.cover{width:100%;max-width:105px;height:145px}.swap-icon{width: 32px; height: 32px; font-size: 16px;}.result{font-size:13px; align-self: flex-start;}.footer-note { flex-direction: column; align-items: flex-start; gap: 8px; }}
</style>
</head>
<body>
<?php require __DIR__.'/../components/header.php';?>
<div class="account">
    <?php require __DIR__.'/../components/profile_sidebar.php';?>
    <main class="manage">
        
        <div class="header-area">
            <div class="header-text">
                <h1 class="title">จัดการคำขอแลกเปลี่ยน</h1>
                <p class="subtitle">ตรวจสอบ ติดตามสถานะ และดำเนินการคำขอแลกเปลี่ยนหนังสือของคุณทั้งหมดผ่านศูนย์กลางความปลอดภัย</p>
            </div>
            <a href="/pages/create_exchange.php" class="btn-add">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> เพิ่มหนังสือที่ต้องการแลก
            </a>
        </div>

        <nav class="tabs">
            <a class="<?=$tab==='received'?'active':''?>" href="?tab=received">คำขอที่ได้รับ <span class="badge"><?=$rcCount?></span></a>
            <a class="<?=$tab==='sent'?'active':''?>" href="?tab=sent">คำขอที่ส่งไป <span class="badge"><?=$stCount?></span></a>
        </nav>

        <?php if($message):?><div class="notice ok"><?=htmlspecialchars($message)?></div><?php endif;?>
        <?php if($error):?><div class="notice bad"><?=htmlspecialchars($error)?></div><?php endif;?>

        <div class="tools-container">
            <form class="filters">
                <input type="hidden" name="tab" value="<?=$tab?>">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="ค้นหาชื่อหนังสือ, ผู้ใช้ หรือรหัสคำขอ..." value="<?=htmlspecialchars($search)?>">
                </div>
                <select name="category" onchange="this.form.submit()">
                    <option value="">ทุกหมวดหมู่</option>
                    <?php foreach($categories as $value):?>
                        <option value="<?=htmlspecialchars($value)?>" <?=$category===$value?'selected':''?>><?=htmlspecialchars($value)?></option>
                    <?php endforeach;?>
                </select>
                <select name="status" onchange="this.form.submit()">
                    <option value="">ทุกสถานะ</option>
                    <?php foreach(['pending'=>'รอตอบรับ','accepted'=>'กำลังเจรจา','completed'=>'แลกเปลี่ยนสำเร็จ','rejected'=>'ปฏิเสธแล้ว','cancelled'=>'ยกเลิกแล้ว'] as $value=>$label):?>
                        <option value="<?=$value?>" <?=$status===$value?'selected':''?>><?=$label?></option>
                    <?php endforeach;?>
                </select>
            </form>
            <span class="result">แสดง <?=number_format(count($requests))?> รายการ จาก <?=number_format($allCount)?> รายการ</span>
        </div>

        <section class="request-list">
            <?php if(!$requests):?>
                <div class="empty">ยังไม่มีคำขอแลกเปลี่ยนในหมวดนี้</div>
            <?php else:
            foreach($requests as $request):
                $otherName=$tab==='received'?$request['requester_name']:$request['owner_name'];
                $otherImage=$tab==='received'?$request['requester_image']:$request['owner_image'];
                $otherId=$tab==='received'?(int)$request['requester_id']:(int)$request['owner_id'];
                
                $statusPillClass = 'pending';
                $statusPillText = 'รอตอบรับ';
                if($request['status'] === 'accepted') { $statusPillClass = 'success'; $statusPillText = 'กำลังเจรจา'; }
                if($request['status'] === 'completed') { $statusPillClass = 'success'; $statusPillText = 'แลกเปลี่ยนสำเร็จ'; }
                if($request['status'] === 'rejected') { $statusPillClass = 'rejected'; $statusPillText = 'ปฏิเสธแล้ว'; }
                if($request['status'] === 'cancelled') { $statusPillClass = 'rejected'; $statusPillText = 'ยกเลิกแล้ว'; }

                $isQCPassed = ($request['trade_option'] === 'admin_qc' && $request['admin_qc_offer_status'] === 'passed' && $request['admin_qc_requester_status'] === 'passed');
            ?>
            <article class="request-card">
                <header class="request-head">
                    <div class="person">
                        <span class="avatar">
                            <?php if($img=xrImage($otherImage)):?>
                                <img src="<?=htmlspecialchars($img)?>" alt="">
                            <?php else:?>
                                <?=htmlspecialchars(mb_substr($otherName,0,1))?>
                            <?php endif;?>
                        </span>
                        <div class="person-info">
                            <div class="name-row">
                                <span class="person-name"><?=htmlspecialchars($otherName)?></span>
                                <span class="role-badge"><?=$tab==='received'?'ผู้ยื่นคำขอ':'ผู้รับคำขอ'?></span>
                            </div>
                            <span class="req-id">รหัสคำขอ: #EX-<?=date('Y-md', strtotime($request['created_at']))?> - <?=date('d M Y H:i', strtotime($request['created_at']))?> น.</span>
                        </div>
                    </div>
                    <div class="status-group">
                        <span class="status-pill <?=$statusPillClass?>">
                            <?php if(in_array($statusPillClass, ['success', 'success-text'])): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <?php endif; ?>
                            <?=$statusPillText?>
                        </span>
                        <?php if($isQCPassed): ?>
                            <span class="status-pill success-text">ผ่าน QC ส่วนกลางแล้ว</span>
                        <?php elseif($request['status'] === 'pending' && $tab === 'received'): ?>
                            <span class="status-pill pending-text">ตอบรับภายใน 24 ชม.</span>
                        <?php endif; ?>
                    </div>
                </header>
                
                <div class="request-body">
                    <div class="exchange-pair">
                        <div class="book">
                            <span class="book-label"><?=$tab==='received'?'หนังสือของคุณ':'หนังสือของคุณ'?></span>
                            <div class="cover">
                                <?php if($img=xrImage($tab==='received'?$request['target_image']:$request['offer_image'])):?>
                                    <img src="<?=htmlspecialchars($img)?>" alt="">
                                <?php endif;?>
                            </div>
                            <strong class="book-title"><?=htmlspecialchars(($tab==='received'?$request['target_title']:$request['offer_title'])?:'ยังไม่ระบุหนังสือ')?></strong>
                            <small class="book-cond">สภาพ <?=htmlspecialchars(($tab==='received'?$request['target_condition']:$request['offer_condition'])?:'90')?>%</small>
                        </div>
                        
                        <div class="swap">
                            <div class="swap-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3l4 4-4 4"/><path d="M20 7H4"/><path d="M8 21l-4-4 4-4"/><path d="M4 17h16"/></svg>
                            </div>
                            <span class="swap-text"><?=($request['status']==='pending')?'รอจับคู่':'จับคู่สลับเล่ม'?></span>
                        </div>
                        
                        <div class="book">
                            <span class="book-label"><?=$tab==='received'?'หนังสือที่เสนอ':'หนังสือที่เสนอ'?></span>
                            <div class="cover">
                                <?php if($img=xrImage($tab==='received'?$request['offer_image']:$request['target_image'])):?>
                                    <img src="<?=htmlspecialchars($img)?>" alt="">
                                <?php endif;?>
                            </div>
                            <strong class="book-title"><?=htmlspecialchars(($tab==='received'?$request['offer_title']:$request['target_title'])?:'ขอพูดคุยก่อน')?></strong>
                            <small class="book-cond">เจ้าของ: <?=htmlspecialchars($otherName)?> (สภาพ <?=htmlspecialchars(($tab==='received'?$request['offer_condition']:$request['target_condition'])?:'90')?>%)</small>
                        </div>
                    </div>
                    
                    <aside class="request-side">
                        <div class="quote-box">
                            <div class="quote-icon">“</div>
                            <div><?=nl2br(htmlspecialchars($request['message']?:'สนใจแลกเปลี่ยนหนังสือรายการนี้ พร้อมจัดส่งผ่าน Wanawan Hub ทันทีครับ')) ?>”</div>
                        </div>
                        
                        <div class="actions">
                            <?php if($tab==='received' && $request['status']==='pending'): ?>
                                <form method="post" style="display:flex; flex-direction:column; gap:10px; width:100%;">
                                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>">
                                    <input type="hidden" name="request_id" value="<?=$request['request_id']?>">
                                    <button class="btn btn-primary" name="request_action" value="accept">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> ตอบรับคำขอแลกเปลี่ยน
                                    </button>
                                    <a class="btn btn-outline" href="/pages/chat.php?seller_type=user&seller_id=<?=$otherId?>&exchange_id=<?=$request['exchange_id']?>">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg> สอบถามเพิ่มเติม / เจรจา
                                    </a>
                                    <button type="submit" class="btn-text-danger" name="request_action" value="reject" onclick="return confirm('ยืนยันที่จะปฏิเสธคำขอนี้?');">ปฏิเสธคำขอนี้</button>
                                </form>
                            <?php else: ?>
                                <a class="btn btn-outline" href="/pages/book_detail.php?source=exchange&id=<?=$request['exchange_id']?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ดูรายละเอียด
                                </a>
                                <a class="btn btn-primary" href="/pages/chat.php?seller_type=user&seller_id=<?=$otherId?>&exchange_id=<?=$request['exchange_id']?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg> ส่งข้อความ
                                </a>
                                
                                <?php if($request['status'] === 'accepted' || $request['status'] === 'completed'): ?>
                                    <form method="post" style="width:100%;">
                                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>">
                                        <input type="hidden" name="request_id" value="<?=$request['request_id']?>">
                                        <select name="request_action" class="status-dropdown" onchange="if(this.value) this.form.submit();">
                                            <option value="">● <?=$statusPillText?> <?=($request['status']==='completed')?'(ปิดรายการแล้ว)':'(คลิกเพื่อเปลี่ยน)'?></option>
                                            <?php if($request['status'] === 'accepted'): ?>
                                                <option value="complete">เปลี่ยนเป็น: แลกเปลี่ยนสำเร็จ</option>
                                                <?php if($tab==='received'): ?><option value="reject">เปลี่ยนเป็น: ปฏิเสธ</option><?php endif; ?>
                                                <option value="cancel">เปลี่ยนเป็น: ยกเลิก</option>
                                            <?php endif; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>

                                <?php if($tab==='sent' && $request['status']==='pending'): ?>
                                    <form method="post" style="width:100%; text-align:center;">
                                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>">
                                        <input type="hidden" name="request_id" value="<?=$request['request_id']?>">
                                        <button class="btn-text-danger" name="request_action" value="cancel" onclick="return confirm('ต้องการยกเลิกคำขอนี้ใช่หรือไม่?')">ยกเลิกคำขอ</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </aside>
                </div>
                
                <?php if($request['status'] === 'completed' || $isQCPassed): ?>
                    <div class="footer-note">
                        <span class="footer-success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            ส่งมอบพัสดุปลายทางเรียบร้อย: หมายเลขพัสดุ Flash FL-<?=$request['request_id']?>882918
                        </span>
                        <a href="#" class="footer-note-link">ดูใบเสร็จและบันทึกการส่งมอบ</a>
                    </div>
                <?php elseif($request['status'] === 'pending' && $tab === 'received'): ?>
                    <div class="footer-note">
                        <span>เมื่อคุณกด "ตอบรับ" ระบบจะสร้างโค้ดส่งพัสดุผ่านจุดดรอปของ Wanawan Hub ทันที</span>
                        <span style="font-weight:600; color:#4b5563;">ไม่มีค่าธรรมเนียมตัวกลาง</span>
                    </div>
                <?php endif; ?>
            </article>
            <?php endforeach;endif;?>
        </section>

        <div class="bottom-banner">
            <div class="banner-left">
                <div class="shield-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                </div>
                <div class="banner-text">
                    <h3>แลกเปลี่ยนมั่นใจด้วยระบบตัวกลาง Hub Escrow</h3>
                    <p>หนังสือทุกเล่มจะได้รับการตรวจสอบคุณภาพ (QC) และยืนยันสภาพจริงโดยทีมงานส่วนกลางก่อนสลับจัดส่งให้แก่สมาชิก เพื่อความพึงพอใจและโปร่งใสสูงสุดของนักอ่านทุกคน</p>
                </div>
            </div>
            <a href="#" class="banner-btn">อ่านข้อกำหนดความปลอดภัย</a>
        </div>
    </main>
</div>
<?php require __DIR__.'/../components/footer.php';?>
</body>
</html>
EOD;

    $content = $php_logic . $new_html;
    file_put_contents($file_path, $content);
    echo "Successfully rewrote exchange_requests.php with new UI\n";
} else {
    echo "Could not find HTML start marker\n";
}
?>
