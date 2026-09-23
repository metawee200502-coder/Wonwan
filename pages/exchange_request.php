<?php
session_start();
if(empty($_SESSION['user_id'])){header('Location:/pages/login.php?redirect='.urlencode($_SERVER['REQUEST_URI']??'/pages/exchange_books.php'));exit;}
require_once __DIR__.'/../config/config.php';
$viewerId=(int)$_SESSION['user_id'];$exchangeId=filter_input(INPUT_GET,'exchange_id',FILTER_VALIDATE_INT)?:0;
$stmt=$pdo->prepare("SELECT e.*,COALESCE(NULLIF(u.display_name,''),u.email) owner_name FROM exchanges e JOIN users u ON u.user_id=e.owner_id WHERE e.exchange_id=? AND e.status='open'");
$stmt->execute([$exchangeId]);$target=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$target){http_response_code(404);exit('ไม่พบรายการแลกเปลี่ยน หรือรายการนี้ปิดรับคำขอแล้ว');}
if((int)$target['owner_id']===$viewerId){header('Location:/pages/book_detail.php?source=exchange&id='.$exchangeId);exit;}
if(empty($_SESSION['exchange_request_csrf']))$_SESSION['exchange_request_csrf']=bin2hex(random_bytes(24));
$offers=$pdo->prepare("SELECT exchange_id,title,author FROM exchanges WHERE owner_id=? AND status='open' AND exchange_id<>? ORDER BY created_at DESC");
$offers->execute([$viewerId,$exchangeId]);$myOffers=$offers->fetchAll(PDO::FETCH_ASSOC);$success='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['exchange_request_csrf'],$_POST['csrf_token']??''))$error='คำขอไม่ถูกต้อง กรุณาลองใหม่';
 else{$offeredId=(int)($_POST['offered_exchange_id']??0);$message=trim($_POST['message']??'');
  if($offeredId){$valid=$pdo->prepare("SELECT 1 FROM exchanges WHERE exchange_id=? AND owner_id=? AND status='open'");$valid->execute([$offeredId,$viewerId]);if(!$valid->fetchColumn())$error='หนังสือที่คุณเสนอไม่พร้อมแลกเปลี่ยน';}
  
  // Check if there is an active request
  $checkActive = $pdo->prepare("SELECT 1 FROM exchange_requests WHERE exchange_id=? AND requester_id=? AND status IN ('pending', 'accepted')");
  $checkActive->execute([$exchangeId, $viewerId]);
  if ($checkActive->fetchColumn()) {
      $error = 'คุณได้ยื่นคำขอสำหรับรายการนี้ไปแล้ว และกำลังอยู่ในสถานะรอตอบรับหรือกำลังเจรจา';
  }
  
  if(!$error){$pdo->beginTransaction();try{$save=$pdo->prepare("INSERT INTO exchange_requests(exchange_id,requester_id,offered_exchange_id,message,status) VALUES(?,?,?,?,'pending')");$save->execute([$exchangeId,$viewerId,$offeredId?:null,$message?:null]);$requester=$pdo->prepare("SELECT COALESCE(NULLIF(display_name,''),email) FROM users WHERE user_id=?");$requester->execute([$viewerId]);$requesterName=$requester->fetchColumn()?:'สมาชิกวนวรรณ';wanawanNotifyUser($pdo, (int)$target['owner_id'], 'มีคำขอแลกเปลี่ยนใหม่', $requesterName.' ยื่นคำขอแลกเปลี่ยนหนังสือ “'.$target['title'].'”', 'exchange');$pdo->commit();$success='ส่งคำขอแลกเปลี่ยนเรียบร้อยแล้ว';}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error='ไม่สามารถส่งคำขอได้ กรุณาลองใหม่';}}
 }
}
function erImage(?string $v):?string{if(!$v)return null;if(preg_match('#^https?://#i',$v))return $v;$v=ltrim(str_replace('\\','/',$v),'/');return str_starts_with($v,'uploads/')?'/pages/'.$v:'/assets/image/books/'.$v;}
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ยื่นคำขอแลกเปลี่ยน - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><style>
:root {
  --primary: #0F4C3A;
  --secondary: #e67e22;
  --bg-color: #f9f6f0;
  --text-dark: #2c3e50;
  --text-muted: #7f8c8d;
  --border-color: #e5e0db;
  --accent-red: #d38f83;
  --accent-red-hover: #c27a69;
}
body { background-color: var(--bg-color); }
* { box-sizing: border-box; }
.request-page { max-width: 1000px; margin: auto; padding: 40px 20px 80px; }
.back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 15px; margin-bottom: 20px; transition: color 0.2s; }
.back-link:hover { color: var(--primary); }
.page-title { color: var(--primary); font-size: 32px; font-weight: 700; margin: 0 0 10px; }
.page-subtitle { color: var(--text-muted); font-size: 16px; margin: 0 0 40px; }

.exchange-container { position: relative; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start; }
@media (max-width: 768px) { .exchange-container { grid-template-columns: 1fr; } }

.card { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); padding: 30px; border: 1px solid rgba(0,0,0,0.05); }

/* Left Card - Target */
.target-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.badge-target { background: #e8f5e9; color: var(--primary); padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
.target-id { color: var(--text-muted); font-size: 13px; font-family: monospace; letter-spacing: 0.5px; }

.book-preview-box { background: #fdf5f2; border-radius: 16px; padding: 15px; text-align: center; margin-bottom: 25px; position: relative; display: flex; align-items: center; justify-content: center; min-height: 250px; }
.book-preview-box img { max-width: 100%; max-height: 220px; border-radius: 8px; box-shadow: 0 8px 16px rgba(0,0,0,0.1); object-fit: contain; }

.book-info-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.book-title { font-size: 24px; font-weight: 700; color: var(--primary); margin: 0; }
.book-category { font-size: 13px; color: var(--text-muted); }
.book-author { color: var(--text-muted); font-size: 15px; margin: 0 0 20px; }

.meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
.meta-box { background: #fcfbfa; border: 1px solid var(--border-color); border-radius: 12px; padding: 15px; }
.meta-label { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
.meta-value { display: flex; align-items: center; gap: 10px; font-weight: 600; color: var(--text-dark); font-size: 14px; }
.meta-avatar { width: 28px; height: 28px; background: var(--primary); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }
.status-dot { width: 8px; height: 8px; background: #2ecc71; border-radius: 50%; display: inline-block; }

.target-footer { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-muted); padding-top: 15px; border-top: 1px dashed var(--border-color); }
.target-footer a { color: var(--text-dark); text-decoration: none; font-weight: 500; }

/* Swap Icon */
.swap-icon-wrapper { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 44px; height: 44px; background: #fff; border: 1px solid var(--border-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.05); z-index: 10; font-size: 18px; }
@media (max-width: 768px) { .swap-icon-wrapper { top: auto; bottom: -22px; transform: translateX(-50%); position: relative; margin: 0 auto; display: none; } }

/* Right Card - Form */
.form-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
.badge-offer { background: #fdf1e9; color: var(--secondary); padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
.form-header-right { font-size: 13px; color: var(--text-muted); }

.form-group { margin-bottom: 25px; }
.form-label-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.form-label { font-weight: 700; color: var(--text-dark); font-size: 16px; margin: 0; }
.add-new-btn { color: var(--accent-red); font-size: 13px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; }
.add-new-btn:hover { text-decoration: underline; }

.custom-select { width: 100%; appearance: none; background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%237f8c8d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 15px center; border: 1px solid var(--border-color); padding: 14px 40px 14px 15px; border-radius: 12px; font-size: 15px; color: var(--text-dark); cursor: pointer; transition: all 0.2s; font-family: inherit; }
.custom-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15, 76, 58, 0.1); }

.offer-status-row { display: flex; justify-content: space-between; font-size: 13px; color: var(--text-muted); margin-top: 10px; }
.offer-ready { color: #2ecc71; font-weight: 500; display: flex; align-items: center; gap: 5px; }

.custom-textarea { width: 100%; border: 1px solid var(--border-color); background: #fff; padding: 15px; border-radius: 12px; min-height: 120px; font-size: 15px; color: var(--text-dark); resize: vertical; transition: all 0.2s; font-family: inherit; }
.custom-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15, 76, 58, 0.1); }

.tags-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
.tag-btn { background: #f0ebe4; border: none; color: var(--text-muted); font-size: 12px; padding: 6px 12px; border-radius: 15px; cursor: pointer; transition: background 0.2s; font-family: inherit; }
.tag-btn:hover { background: #e0d9d0; }

.security-notice { background: #f9f7f4; border: 1px solid #efe8df; border-radius: 12px; padding: 15px; display: flex; gap: 12px; align-items: flex-start; margin-bottom: 25px; }
.security-icon { color: var(--primary); font-size: 18px; margin-top: 2px; }
.security-text { font-size: 13px; color: var(--text-dark); line-height: 1.5; margin: 0; }
.security-text strong { color: var(--text-dark); }

.submit-btn { width: 100%; background: var(--accent-red); color: #fff; border: none; padding: 16px; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; transition: background 0.2s; font-family: inherit; }
.submit-btn:hover { background: var(--accent-red-hover); }
.submit-subtext { text-align: center; font-size: 12px; color: var(--text-muted); margin-top: 12px; }

/* Steps Section */
.steps-section { margin-top: 80px; text-align: center; }
.steps-title { color: var(--primary); font-size: 20px; font-weight: 700; margin-bottom: 40px; }
.steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; }
@media (max-width: 768px) { .steps-grid { grid-template-columns: 1fr; } }
.step-card { background: #fff; padding: 30px 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.step-number { width: 40px; height: 40px; background: #e8f5e9; color: var(--primary); font-size: 18px; font-weight: 700; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.step-title { font-weight: 700; color: var(--text-dark); font-size: 16px; margin-bottom: 10px; }
.step-desc { font-size: 14px; color: var(--text-muted); line-height: 1.6; margin: 0; }

.notice { padding:14px 18px; border-radius:12px; margin-bottom:30px; font-weight: 500; }
.ok { background:#e8f5e9; color:#2e7d32; border: 1px solid #c8e6c9; }
.bad { background:#ffebee; color:#c62828; border: 1px solid #ffcdd2; }
.after-actions { display:flex; gap:15px; margin-top:20px; }
.after-actions a { flex:1; text-align:center; padding:14px; border-radius:10px; text-decoration:none; font-weight:600; font-size:15px; transition: all 0.2s; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: #0a3629; }
.btn-outline { border: 1px solid var(--primary); color: var(--primary); }
.btn-outline:hover { background: #e8f5e9; }
</style></head><body><?php require __DIR__.'/../components/header.php';?><main class="request-page">
<a class="back-link" href="/pages/book_detail.php?source=exchange&amp;id=<?=$exchangeId?>"><i class="fas fa-arrow-left"></i> กลับไปหน้ารายละเอียดหนังสือ</a>
<h1 class="page-title">ยื่นคำขอแลกเปลี่ยน</h1>
<p class="page-subtitle">เลือกหนังสือที่คุณต้องการเสนอแลก หรือส่งข้อความพูดคุยรายละเอียดเพิ่มเติมกับเจ้าของหนังสือ</p>

<?php if($success):?><div class="notice ok"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($success)?></div><?php endif;?>
<?php if($error):?><div class="notice bad"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif;?>

<div class="exchange-container">
  <!-- Target Book (Left) -->
  <div class="card target-card">
    <div class="target-header">
      <span class="badge-target"><i class="fas fa-check"></i> หนังสือเป้าหมาย (ต้องการเล่มนี้)</span>
      <span class="target-id">ID: EX-<?=str_pad($target['exchange_id'], 4, '0', STR_PAD_LEFT)?></span>
    </div>
    
    <div class="book-preview-box">
      <?php if($img=erImage($target['image_cover'])):?>
        <img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($target['title'])?>">
      <?php else: ?>
        <div style="color:var(--text-muted);">ไม่มีรูปภาพ</div>
      <?php endif;?>
    </div>
    
    <div class="book-info-top">
      <h2 class="book-title"><?=htmlspecialchars($target['title'])?></h2>
      <span class="book-category">หมวดหมู่: ทั่วไป / วรรณกรรม</span>
    </div>
    <p class="book-author"><?=htmlspecialchars($target['author']?:'-')?></p>
    
    <div class="meta-grid">
      <div class="meta-box">
        <div class="meta-label">เจ้าของหนังสือ</div>
        <div class="meta-value">
          <div class="meta-avatar"><?=mb_substr($target['owner_name'],0,1)?></div>
          <?=htmlspecialchars($target['owner_name'])?>
        </div>
      </div>
      <div class="meta-box">
        <div class="meta-label">สภาพหนังสือ</div>
        <div class="meta-value">
          <span class="status-dot"></span> สภาพ <?=number_format((int)$target['condition_percent'])?>%
        </div>
      </div>
    </div>
    
    <div class="target-footer">
      <span>ประเมินความสมบูรณ์: สภาพดีเยี่ยม ไร้รอยยับ</span>
      <a href="/pages/book_detail.php?source=exchange&amp;id=<?=$exchangeId?>">ดูเงื่อนไขเพิ่มเติม <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>

  <div class="swap-icon-wrapper"><i class="fas fa-exchange-alt"></i></div>

  <!-- Offer Form (Right) -->
  <div class="card form-card">
    <?php if(!$success):?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_request_csrf'])?>">
      
      <div class="form-header">
        <span class="badge-offer"><i class="fas fa-plus"></i> ข้อเสนอของคุณ (Your Offer)</span>
        <span class="form-header-right">จากชั้นหนังสือของคุณ</span>
      </div>
      
      <div class="form-group">
        <div class="form-label-row">
          <label class="form-label">หนังสือที่คุณเสนอแลก</label>
          <a href="/pages/add_exchange.php" class="add-new-btn">+ เพิ่มเล่มใหม่</a>
        </div>
        <select name="offered_exchange_id" class="custom-select">
          <option value="">ยังไม่ระบุ / ขอพูดคุยก่อน</option>
          <?php foreach($myOffers as $offer):?>
            <option value="<?=$offer['exchange_id']?>" <?=((int)($_POST['offered_exchange_id']??0)===(int)$offer['exchange_id'])?'selected':''?>>
              <?=htmlspecialchars($offer['title'].(!empty($offer['author'])?' — '.$offer['author']:''))?>
            </option>
          <?php endforeach;?>
        </select>
        <div class="offer-status-row">
          <span class="offer-ready"><i class="fas fa-check-circle"></i> สถานะ: พร้อมส่งทันที</span>
          <span>ประเมินสภาพ: 95%</span>
        </div>
      </div>
      
      <div class="form-group">
        <label class="form-label" style="display:block; margin-bottom:10px;">ข้อความถึงเจ้าของหนังสือ</label>
        <textarea name="message" class="custom-textarea" placeholder="แนะนำหนังสือที่ต้องการเสนอ หรือแจ้งรายละเอียดที่อยากสอบถาม เช่น สภาพหนังสือ นัดรับ หรือส่งผ่านระบบส่วนกลาง..."><?=htmlspecialchars($_POST['message']??'')?></textarea>
        <div class="tags-row">
          <button type="button" class="tag-btn" onclick="document.querySelector('textarea[name=\'message\']').value += (document.querySelector('textarea[name=\'message\']').value ? ' ' : '') + 'หนังสือสภาพกริบ'">+ หนังสือสภาพกริบ</button>
          <button type="button" class="tag-btn" onclick="document.querySelector('textarea[name=\'message\']').value += (document.querySelector('textarea[name=\'message\']').value ? ' ' : '') + 'มีรูปมุมเพิ่มเติม'">+ มีรูปมุมเพิ่มเติม</button>
          <button type="button" class="tag-btn" onclick="document.querySelector('textarea[name=\'message\']').value += (document.querySelector('textarea[name=\'message\']').value ? ' ' : '') + 'ส่งผ่านจุด Hub'">+ ส่งผ่านจุด Hub</button>
        </div>
      </div>
      
      <div class="security-notice">
        <div class="security-icon"><i class="fas fa-shield-alt"></i></div>
        <p class="security-text">
          ระบบปกป้องการแลกเปลี่ยน: หนังสือของคุณจะได้รับการตรวจสอบสภาพที่จุด Hub กลางก่อนจัดส่งถึงผู้รับ เพื่อความมั่นใจของทั้งสองฝ่าย
        </p>
      </div>
      
      <button type="submit" class="submit-btn">
        ยืนยันยื่นคำขอแลกเปลี่ยน <i class="fas fa-arrow-right"></i>
      </button>
      <div class="submit-subtext">เจ้าของหนังสือจะมีเวลาพิจารณาคำขอภายใน 48 ชั่วโมง</div>
    </form>
    <?php else:?>
      <div style="text-align:center; padding: 40px 20px;">
        <i class="fas fa-paper-plane" style="font-size:48px; color:var(--primary); margin-bottom:20px;"></i>
        <h3 style="color:var(--text-dark); margin-bottom:10px;">ส่งคำขอสำเร็จ</h3>
        <p style="color:var(--text-muted); margin-bottom:30px;">เจ้าของหนังสือจะได้รับแจ้งเตือนและมีเวลาพิจารณาคำขอภายใน 48 ชั่วโมง</p>
        <div class="after-actions">
          <a href="/pages/chat.php?seller_type=user&amp;seller_id=<?=(int)$target['owner_id']?>&amp;exchange_id=<?=$exchangeId?>" class="btn-outline">ส่งข้อความเพิ่มเติม</a>
          <a href="/pages/exchange_books.php" class="btn-primary">กลับลานแลกเปลี่ยน</a>
        </div>
      </div>
    <?php endif;?>
  </div>
</div>

<div class="steps-section">
  <h2 class="steps-title">ขั้นตอนการแลกเปลี่ยนหนังสือผ่าน Wanawan</h2>
  <div class="steps-grid">
    <div class="step-card">
      <div class="step-number">1</div>
      <h3 class="step-title">ส่งคำขอแลกเปลี่ยน</h3>
      <p class="step-desc">เลือกหนังสือที่คุณมีและส่งคำขอพร้อมข้อความทักทายไปยังเจ้าของ</p>
    </div>
    <div class="step-card">
      <div class="step-number">2</div>
      <h3 class="step-title">เจ้าของกดยอมรับข้อเสนอ</h3>
      <p class="step-desc">เมื่อได้รับการตอบรับ ทั้งสองฝ่ายจะได้รับใบปะหน้าพัสดุสำหรับส่งหนังสือ</p>
    </div>
    <div class="step-card">
      <div class="step-number">3</div>
      <h3 class="step-title">รับหนังสือเล่มใหม่</h3>
      <p class="step-desc">หนังสือถูกส่งตรงถึงบ้านคุณอย่างปลอดภัย พร้อมเปิดโลกการอ่านใบใหม่</p>
    </div>
  </div>
</div>
</main>
<?php require __DIR__.'/../components/footer.php';?>
</body></html>
