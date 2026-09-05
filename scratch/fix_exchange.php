<?php
$content = file_get_contents('../pages/exchange_requests.php');
// We will replace from <style> (line 58) to the end of the file.
$pos = strpos($content, '<style>');
if ($pos !== false) {
    $newContent = substr($content, 0, $pos);
    $newContent .= <<<'HTML'
<style>
* { box-sizing:border-box }
.account { display:flex; min-height:850px; background:#fff }
.manage { flex:1; max-width:1080px; margin:0 auto; padding:36px 40px 80px; min-width:0 }
.title { margin:0 0 14px; color:var(--secondary-color); font-size:32px; font-weight:700; }
.tabs { display:flex; gap:12px; margin-bottom:20px }
.tabs a { min-width:160px; padding:10px 24px; border-radius:24px; background:#f2e6e1; color:var(--text-dark); text-align:center; text-decoration:none; font-weight:600; font-size:15px; }
.tabs a.active { background:var(--secondary-color); color:#fff }
.tools { display:flex; align-items:center; justify-content:space-between; gap:18px; margin-bottom:24px; background:#fff; padding:16px 20px; border-radius:12px; border:1px solid #f0f0f0; box-shadow:0 2px 8px rgba(0,0,0,0.02); }
.filters { display:flex; gap:12px; flex:1; }
.filters select { width:200px; height:40px; border:1px solid #e0e5e2; border-radius:20px; padding:0 16px; background:#fff; font:inherit; color:#555; outline:none; cursor:pointer; }
.result { color:#888; font-size:14px; }
.notice { padding:13px 17px; border-radius:9px; margin-bottom:16px; }
.notice.ok { background:#e8f4e4; color:#3d7139; }
.notice.bad { background:#fde5e1; color:#a44943; }

.request-list { display:grid; gap:24px }
.request-card { border:1px solid #f0f0f0; border-radius:16px; background:#fff; box-shadow:0 6px 16px rgba(0,0,0,0.03); display:flex; flex-direction:column; overflow:hidden; }
.request-head { display:flex; align-items:center; justify-content:space-between; padding:20px 24px; }
.person { display:flex; align-items:center; gap:12px }
.avatar { width:52px; height:52px; border-radius:50%; background:#f5dfe5; display:grid; place-items:center; color:var(--secondary-color); font-weight:700; overflow:hidden; }
.avatar img { width:100%; height:100%; object-fit:cover; }
.person strong { color:var(--secondary-color); font-size:16px; display:block; }
.person span { color:#888; font-size:13px; display:block; margin-top:2px; }
.status { padding:6px 16px; border-radius:20px; font-size:13px; font-weight:700 }
.status.pending { background:#fff1df; color:#ef6d23 }
.status.progress { background:#e7f1f3; color:#176273 }
.status.done { background:#e0f5df; color:#198a23 }
.status.rejected, .status.cancelled { background:#fde7e3; color:#c4443d }

.request-body { padding:0 24px; display:flex; flex-direction:column; gap:16px; }
.exchange-pair { background:#fbfaf8; border-radius:16px; display:grid; grid-template-columns:1fr 60px 1fr; align-items:center; gap:12px; padding:24px; border:1px solid #f4f0ed; }
.book { display:flex; flex-direction:column; align-items:center; text-align:center; min-width:0; }
.book small { font-size:13px; font-weight:600; margin-bottom:16px; display:flex; align-items:center; gap:6px; }
.book small.mine { color:#555; }
.book small.mine::before { content:'✓'; color:inherit; }
.book small.offer { color:#c4443d; }
.book small.offer::before { content:'+'; font-size:16px; font-weight:bold; }
.cover { width:110px; height:160px; border-radius:8px; background:#f0f0f0; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.08); }
.cover img { width:100%; height:100%; object-fit:cover; }
.cover.empty-offer { background:#fff8f8; border:1.5px dashed #e69d9b; box-shadow:none; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#c4443d; }
.cover.empty-offer svg { margin-bottom:8px; opacity:0.8; }
.book strong { margin-top:14px; color:var(--secondary-color); font-size:15px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width:100%; }
.book .condition { font-size:13px; color:#888; margin-top:4px; }
.swap { width:44px; height:44px; border-radius:50%; background:#fff; display:grid; place-items:center; margin:auto; box-shadow:0 2px 8px rgba(0,0,0,0.06); color:#4e8490; border:1px solid #eee; }

.request-message { background:#f7f9f9; border-left:3px solid #4e8490; border-radius:6px; padding:16px 20px; color:#444; font-size:14px; display:flex; gap:10px; line-height:1.5; }
.request-message::before { content:'“'; font-size:24px; color:#4e8490; line-height:0.8; font-family:serif; font-weight:700; }

.actions-footer { padding:18px 24px; border-top:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-top:8px; }
.actions-left { flex:1; min-width:200px; display:flex; align-items:center; }
.actions-group { display:flex; gap:10px; }
.btn { min-height:40px; border:1px solid #cbd2cd; border-radius:20px; display:inline-flex; align-items:center; justify-content:center; padding:0 20px; background:#fff; color:var(--secondary-color); text-decoration:none; font:inherit; font-weight:600; font-size:14px; cursor:pointer; transition:0.2s; white-space:nowrap; }
.btn:hover { background:#f5f5f5; }
.btn.primary { background:var(--secondary-color); border-color:var(--secondary-color); color:#fff; }
.btn.primary:hover { background:#1a3237; }
.btn.danger { border-color:#ef4e47; color:#df3d36; }
.btn.danger:hover { background:#fde7e3; }
.status-msg.danger { color:#df3d36; font-size:13px; display:flex; align-items:center; gap:6px; }

.exchange-status-control { position:relative; width:220px; }
.exchange-status-select { width:100%; height:40px; padding:0 36px 0 16px; border:1px solid #cbd2cd; border-radius:20px; background:#fff; color:var(--secondary-color); font:inherit; font-weight:600; font-size:14px; cursor:pointer; outline:none; appearance:none; }
.exchange-status-control::after { content:''; position:absolute; right:16px; top:50%; width:8px; height:8px; border-right:2px solid var(--secondary-color); border-bottom:2px solid var(--secondary-color); transform:translateY(-70%) rotate(45deg); pointer-events:none; }

.tracking-form { display:flex; gap:8px; align-items:center; width:100%; max-width:350px; }
.tracking-form input { height:40px; border:1px solid #cbd2cd; border-radius:20px; padding:0 16px; font:inherit; outline:none; flex:1; }
.qc-passed { font-size:13px; color:#155724; background:#d4edda; padding:10px 16px; border-radius:8px; margin-left:12px; }

.empty { padding:90px 20px; text-align:center; border:1px dashed #ccd3cd; border-radius:14px; color:var(--text-muted); }

/* Responsive iPad */
@media(max-width:1050px) {
    .manage { padding:30px 20px 80px; }
    .tools { flex-direction:column; align-items:flex-start; gap:12px; }
    .filters { width:100%; flex-direction:column; }
    .filters select { width:100%; }
    .actions-footer { flex-direction:column; align-items:flex-start; }
    .actions-left { width:100%; max-width:none; }
    .actions-group { width:100%; justify-content:flex-end; }
    .exchange-status-control { width:100%; max-width:280px; }
}
@media(max-width:600px) {
    .account { display:block; }
    .exchange-pair { grid-template-columns:1fr 40px 1fr; padding:16px; gap:8px; }
    .cover { width:90px; height:130px; }
    .request-head { flex-direction:column; align-items:flex-start; gap:12px; }
    .actions-group { flex-direction:column; }
    .actions-group .btn { width:100%; }
    .tabs a { flex:1; min-width:0; padding:10px; font-size:14px; }
    .tracking-form { flex-direction:column; max-width:none; }
    .tracking-form .btn { width:100%; }
    .qc-passed { margin-left:0; margin-top:10px; width:100%; }
}
</style>
</head>
<body>
<?php require __DIR__.'/../components/header.php';?>
<div class="account">
<?php require __DIR__.'/../components/profile_sidebar.php';?>
<main class="manage">
 <h1 class="title">จัดการคำขอแลกเปลี่ยน</h1>
 <nav class="tabs">
  <a class="<?=$tab==='received'?'active':''?>" href="?tab=received">คำขอที่ได้รับ</a>
  <a class="<?=$tab==='sent'?'active':''?>" href="?tab=sent">คำขอที่ส่งไป</a>
 </nav>
 <?php if($message):?><div class="notice ok"><?=htmlspecialchars($message)?></div><?php endif;?>
 <?php if($error):?><div class="notice bad"><?=htmlspecialchars($error)?></div><?php endif;?>
 <div class="tools">
  <form class="filters">
   <input type="hidden" name="tab" value="<?=$tab?>">
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
   [$statusLabel,$statusClass]=xrStatus($request['status']);
   $otherName=$tab==='received'?$request['requester_name']:$request['owner_name'];
   $otherImage=$tab==='received'?$request['requester_image']:$request['owner_image'];
   $otherId=$tab==='received'?(int)$request['requester_id']:(int)$request['owner_id'];
   $isEmptyOffer = empty($tab==='received'?$request['offer_title']:$request['target_title']);
 ?>
  <article class="request-card">
   <header class="request-head">
    <div class="person">
     <span class="avatar">
      <?php if($img=xrImage($otherImage)):?><img src="<?=htmlspecialchars($img)?>" alt=""><?php else:?><?=htmlspecialchars(mb_substr($otherName,0,1))?><?php endif;?>
     </span>
     <span>
      <strong><?=htmlspecialchars($otherName)?></strong>
      <span><?=$tab==='received'?'ผู้ยื่นคำขอ':'ผู้รับคำขอ'?> &middot; ยื่นเมื่อ <?=date('d M', strtotime($request['created_at']))?></span>
     </span>
    </div>
    <span class="status <?=$statusClass?>"><?=$statusLabel?></span>
   </header>

   <div class="request-body">
    <div class="exchange-pair">
     <div class="book">
      <small class="mine">หนังสือของคุณ</small>
      <div class="cover">
       <?php if($img=xrImage($tab==='received'?$request['target_image']:$request['offer_image'])):?><img src="<?=htmlspecialchars($img)?>" alt=""><?php endif;?>
      </div>
      <strong><?=htmlspecialchars(($tab==='received'?$request['target_title']:$request['offer_title'])?:'ยังไม่ระบุหนังสือ')?></strong>
      <span class="condition">สภาพ <?=(int)($tab==='received'?$request['target_condition']:$request['offer_condition'])?>%</span>
     </div>
     
     <div class="swap">
         <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
     </div>
     
     <div class="book">
      <small class="offer">หนังสือที่เสนอ</small>
      <div class="cover <?= $isEmptyOffer ? 'empty-offer' : '' ?>">
       <?php if(!$isEmptyOffer && $img=xrImage($tab==='received'?$request['offer_image']:$request['target_image'])):?>
        <img src="<?=htmlspecialchars($img)?>" alt="">
       <?php elseif($isEmptyOffer):?>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span style="font-size:12px; margin-top:4px;">ขอพูดคุยก่อน</span>
       <?php endif;?>
      </div>
      <strong><?=htmlspecialchars(($tab==='received'?$request['offer_title']:$request['target_title'])?:'เสนอตามตกลง')?></strong>
      <span class="condition"><?= $isEmptyOffer ? 'พูดคุยผ่านแชท' : 'สภาพ '.(int)($tab==='received'?$request['offer_condition']:$request['target_condition']).'%' ?></span>
     </div>
    </div>
    
    <div class="request-message">
     <span><?=htmlspecialchars($request['message']?:'สนใจแลกเปลี่ยนหนังสือรายการนี้') ?></span>
    </div>
   </div>

   <footer class="actions-footer">
    <div class="actions-left">
     <?php if($tab==='received' && in_array($request['status'], ['pending','accepted'])): ?>
      <form method="post" class="exchange-status-control">
       <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>">
       <input type="hidden" name="request_id" value="<?=$request['request_id']?>">
       <select class="exchange-status-select" name="request_action" onchange="if(this.value) { this.form.submit(); this.disabled=true; }">
        <?php if($request['status']==='pending'): ?>
         <option value="">พิจารณาคำขอ...</option>
         <option value="accept">ตอบรับคำขอ</option>
         <option value="complete">แลกเปลี่ยนสำเร็จ</option>
         <option value="reject">ปฏิเสธ</option>
        <?php elseif($request['status']==='accepted'): ?>
         <option value="">กำลังเจรจา</option>
         <option value="complete">แลกเปลี่ยนสำเร็จ</option>
         <option value="reject">ปฏิเสธ</option>
        <?php endif; ?>
       </select>
      </form>
     <?php elseif($tab==='sent' && $request['status']==='rejected'): ?>
      <span class="status-msg danger">
       <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
       คำขอนี้ถูกปฏิเสธแล้วเมื่อ <?= date('d M', strtotime($request['updated_at'])) ?>
      </span>
     <?php elseif($request['status']==='cancelled'): ?>
      <span class="status-msg danger">คำขอนี้ถูกยกเลิกแล้ว</span>
     <?php elseif($request['status']==='completed'): ?>
      <span style="color:#198a23;font-size:14px;font-weight:600;">แลกเปลี่ยนสำเร็จแล้ว</span>
     <?php endif; ?>
    </div>
    
    <div class="actions-group">
     <a class="btn" href="/pages/book_detail.php?source=exchange&amp;id=<?=$request['exchange_id']?>">ดูรายละเอียด</a>
     
     <?php if($tab==='sent'&&$request['status']==='pending'): ?>
      <form method="post" onsubmit="return confirm('ต้องการยกเลิกคำขอนี้ใช่หรือไม่?')">
       <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>">
       <input type="hidden" name="request_id" value="<?=$request['request_id']?>">
       <button class="btn danger" name="request_action" value="cancel">ยกเลิกคำขอ</button>
      </form>
     <?php endif; ?>

     <?php if(in_array($request['status'], ['pending','accepted','completed'])): ?>
      <a class="btn primary" href="/pages/chat.php?seller_type=user&amp;seller_id=<?=$otherId?>&amp;exchange_id=<?=$request['exchange_id']?>">ส่งข้อความ</a>
     <?php else: ?>
      <a class="btn" href="/pages/chat.php?seller_type=user&amp;seller_id=<?=$otherId?>&amp;exchange_id=<?=$request['exchange_id']?>">ประวัติการสนทนา</a>
     <?php endif; ?>
    </div>
   </footer>
  </article>
 <?php endforeach; endif; ?>
 </section>
</main>
</div>
<?php require __DIR__.'/../components/footer.php';?>
</body>
</html>
HTML;
    file_put_contents('../pages/exchange_requests.php', $newContent);
    echo "Successfully updated exchange_requests.php\n";
} else {
    echo "Failed to find <style> tag.\n";
}
?>
