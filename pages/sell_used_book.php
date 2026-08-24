<?php
session_start();
if(empty($_SESSION['user_id'])){header('Location:/pages/login.php');exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/helpers/book_db_helper.php';
require_once __DIR__.'/helpers/book_image_helper.php';
require_once __DIR__.'/handlers/handle_save_book.php';
$userId=(int)$_SESSION['user_id'];
$stmt=$pdo->prepare('SELECT display_name,email FROM users WHERE user_id=?');$stmt->execute([$userId]);$user=$stmt->fetch(PDO::FETCH_ASSOC);if(!$user)exit('ไม่พบผู้ใช้');
$sellerName=$user['display_name']?:strstr($user['email'],'@',true);$success='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $_POST['book_type']='used';$_POST['stock_quantity']=1;
    $result=handleSaveBook($pdo,$userId,'user',$sellerName,isset($_POST['draft'])?'draft':'available');
    $success=$result['success']??'';$error=$result['error']??'';
    $cover=$result['cover']??'';
}
$categories=['วรรณกรรมไทย','วรรณกรรมต่างประเทศ','การ์ตูน/มังงะ','จิตวิทยา','การศึกษา/ตำรา','ธุรกิจ/การเงิน','เทคโนโลยี','สุขภาพ/ความงาม','ท่องเที่ยว','ศาสนา/ปรัชญา','ประวัติศาสตร์','สิ่งแวดล้อม','อื่น ๆ'];
function old(string $key,string $default=''):string{return htmlspecialchars((string)($_POST[$key]??$default));}
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ลงขายหนังสือมือสอง - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css"><style>
*{box-sizing:border-box}.sell-page{max-width:1050px;margin:auto;padding:38px 28px 80px}.breadcrumb{color:var(--text-muted);margin-bottom:22px}.breadcrumb a{color:var(--secondary-color)}.sell-title{color:var(--secondary-color);font-size:34px;margin:0}.sell-subtitle{color:var(--text-muted);margin:7px 0 30px}.panel{border:1px solid #dfd6ce;border-radius:14px;padding:28px;margin-bottom:20px;background:#fff}.panel h2{display:flex;align-items:center;font-size:20px;color:var(--secondary-color);margin:0 0 22px}.num{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:#f0e8e1;color:var(--secondary-color);margin-right:12px;flex-shrink:0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.grid.three{grid-template-columns:repeat(3,1fr)}.field{margin-bottom:17px}.field label{display:block;font-weight:600;color:#665d57;margin-bottom:7px}.field input,.field select,.field textarea{width:100%;border:1px solid #ccd3c5;border-radius:8px;padding:12px 13px;font:inherit;background:#fff}.field textarea{min-height:110px;resize:vertical}.condition{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.condition label{border:1px solid #d8d2ca;border-radius:9px;padding:14px;text-align:center;cursor:pointer}.uploads{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}.upload{min-height:250px;border:2px dashed #cbd2c4;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:14px;color:var(--text-muted);cursor:pointer;overflow:hidden;transition:border-color .2s,background .2s}.upload:hover{border-color:#d3948b;background:#fff8f5}.upload-title{font-weight:600;margin-bottom:10px}.upload input{width:100%;font-size:12px}.upload-preview{display:none;width:100%;height:205px;object-fit:contain;border-radius:8px;background:#f7f3f0;margin-bottom:10px}.upload.has-preview{border-style:solid;border-color:#d3948b;justify-content:flex-start}.upload.has-preview .upload-preview{display:block}.upload.has-preview .upload-title{color:var(--secondary-color)}.shipping{display:flex;gap:20px;flex-wrap:wrap}.shipping label{padding:11px 15px;border:1px solid #d8d2ca;border-radius:8px}.actions{display:flex;gap:12px}.btn{min-width:180px;height:50px;border-radius:8px;border:1px solid #d3948b;font:inherit;font-weight:600;cursor:pointer}.publish{background:#d3948b;color:#fff}.draft{background:#fff;color:#b56f68}.message{padding:14px 18px;border-radius:9px;margin-bottom:18px}.success{background:#eaf5e5;color:#47723e}.error{background:#fde7e3;color:#a44540}@media(max-width:750px){.sell-page{padding:25px 15px 60px}.panel{padding:20px}.grid,.grid.three,.condition{grid-template-columns:1fr}.uploads{grid-template-columns:1fr}.actions{flex-direction:column}.btn{width:100%}}
</style></head><body><?php require __DIR__.'/../components/header.php';?><main class="sell-page"><h1 class="sell-title"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:bottom; margin-right:8px; color:var(--secondary-color);"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>ลงขายหนังสือมือสองของคุณ</h1><p class="sell-subtitle">กรอกรายละเอียดและภาพสภาพจริง เพื่อให้ผู้อ่านตัดสินใจได้ง่ายขึ้น</p>
<?php if($success): ?>
<div class="modal-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:4000;">
  <div class="modal-content" style="background:#fff; padding:30px; border-radius:12px; max-width:400px; width:90%; text-align:center;">
    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#567342" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
    <h2 style="margin-top:0; color:var(--secondary-color); margin-bottom:15px;"><?=htmlspecialchars($success)?></h2>
    
    <?php if(!empty($cover)): ?>
    <img src="<?=htmlspecialchars($cover)?>" style="width:110px; height:150px; object-fit:cover; border-radius:8px; box-shadow:0 3px 10px rgba(0,0,0,0.15); margin:0 auto 15px; display:block; border:1px solid #eee;">
    <?php endif; ?>

    <div style="background:#f7f4f2; padding:15px; border-radius:8px; margin-bottom:20px; text-align:left; font-size:15px;">
      <p style="margin:5px 0;"><strong>ชื่อหนังสือ:</strong> <?=old('title')?></p>
      <p style="margin:5px 0;"><strong>ราคาขาย:</strong> ฿<?=old('price')?></p>
      <p style="margin:5px 0;"><strong>หมวดหมู่:</strong> <?=old('category') ?: '-'?></p>
    </div>
    <div style="display:flex; gap:10px; justify-content:center;">
      <a href="/pages/sell_used_book.php" class="btn draft" style="flex:1; text-decoration:none; display:flex; align-items:center; justify-content:center;">เพิ่มโพสต์ใหม่</a>
      <a href="/pages/profile.php?tab=selling" class="btn publish" style="flex:1; text-decoration:none; display:flex; align-items:center; justify-content:center;">ดูโพสต์ของฉัน</a>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if($error):?><div class="message error"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post" enctype="multipart/form-data" id="postForm">
<section class="panel"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></span>รายละเอียดหนังสือ</h2><div class="grid"><div class="field"><label>ISBN</label><input name="isbn" value="<?=old('isbn')?>" placeholder="ISBN หรือ ISBN-13"></div><div class="field"><label>ชื่อหนังสือ *</label><input name="title" value="<?=old('title')?>" required></div><div class="field"><label>ผู้เขียน</label><input name="author" value="<?=old('author')?>"></div><div class="field"><label>สำนักพิมพ์</label><input name="publisher_name" value="<?=old('publisher_name')?>"></div><div class="field"><label>หมวดหมู่</label><select name="category"><option value="">เลือกหมวดหมู่</option><?php foreach($categories as $cat):?><option value="<?=htmlspecialchars($cat)?>" <?=old('category')===$cat?'selected':''?>><?=htmlspecialchars($cat)?></option><?php endforeach;?></select></div><div class="field"><label>ภาษา</label><select name="language"><option>ไทย</option><option>อังกฤษ</option><option>ญี่ปุ่น</option><option>จีน</option><option>อื่น ๆ</option></select></div></div><div class="grid three"><div class="field"><label>รูปแบบ</label><select name="format"><option value="">เลือกรูปแบบ</option><option>Paperback</option><option>Hardcover</option><option>Pocket Book</option></select></div><div class="field"><label>จำนวนหน้า</label><input type="number" min="1" name="pages" value="<?=old('pages')?>"></div><div class="field"><label>ขนาดหนังสือ</label><input name="dimensions" value="<?=old('dimensions')?>" placeholder="กว้าง x ยาว x หนา ซม."></div></div><div class="field"><label>เรื่องย่อ / รายละเอียด</label><textarea name="synopsis"><?=old('synopsis')?></textarea></div></section>
<section class="panel"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg></span>สภาพและราคา</h2><div class="condition"><label><input type="radio" name="condition_level" value="good" checked> สภาพดี</label><label><input type="radio" name="condition_level" value="fair"> มีตำหนิเล็กน้อย</label><label><input type="radio" name="condition_level" value="old"> สภาพเก่า</label></div><div class="grid" style="margin-top:18px"><div class="field"><label>ราคาขาย *</label><input type="number" min="1" step="0.01" name="price" value="<?=old('price')?>" required></div><div class="field"><label>ราคาปก</label><input type="number" min="0" step="0.01" name="original_price" value="<?=old('original_price')?>"></div></div><div class="field"><label>รายละเอียดตำหนิ</label><textarea name="defects_detail"><?=old('defects_detail')?></textarea></div><input type="hidden" name="stock_quantity" value="1"><input type="hidden" name="book_type" value="used"></section>
<section class="panel"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg></span>รูปภาพสภาพจริง</h2><div class="uploads"><?php foreach(['image_cover'=>'หน้าปก *','image_back'=>'หลังปก','image_inside'=>'ด้านใน','image_edge'=>'สัน/ขอบ'] as $field=>$label):?><label class="upload" id="slot-<?=$field?>"><img class="upload-preview" id="preview-<?=$field?>" alt="ตัวอย่าง <?=$label?>"><span class="upload-title"><?=$label?></span><input type="file" name="<?=$field?>" accept="image/jpeg,image/png,image/webp" onchange="showBookPreview(this,'<?=$field?>')" <?=$field==='image_cover'?'required':''?>></label><?php endforeach;?></div></section>
<section class="panel"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg></span>วิธีจัดส่ง</h2><div class="shipping"><?php foreach(['ไปรษณีย์ไทย','บริษัทขนส่งเอกชน','นัดรับ'] as $s):?><label><input type="checkbox" name="shipping[]" value="<?=$s?>" <?=($s==='ไปรษณีย์ไทย')?'checked':''?>> <?=$s?></label><?php endforeach;?></div></section>
<div class="actions">
<button class="btn publish" type="button" onclick="showReviewModal('publish')">ลงขายหนังสือ</button>
<button class="btn draft" type="button" onclick="showReviewModal('draft')">บันทึกร่าง</button>
</div>
<!-- Review Modal -->
<div id="reviewModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:3000;">
  <style>.modal-content::-webkit-scrollbar { display: none; }</style>
  <div class="modal-content" style="background:#fff; padding:25px; border-radius:12px; max-width:500px; width:90%; max-height:80vh; overflow-y:auto; scrollbar-width:none; -ms-overflow-style:none;">
    <h2 style="margin-top:0; color:var(--secondary-color);">ตรวจสอบข้อมูลก่อนโพสต์</h2>
    <div id="reviewContent" class="review-details" style="margin-bottom:20px; font-size:15px; line-height:1.6;"></div>
    <div class="modal-actions" style="display:flex; gap:12px; justify-content:flex-end;">
      <button class="btn draft" type="button" onclick="closeReviewModal()">แก้ไขรายละเอียดเพิ่ม</button>
      <button class="btn publish" type="button" onclick="confirmPost()">โพสต์เลย</button>
    </div>
  </div>
</div>
</form></main><?php require __DIR__.'/../components/footer.php';?><script>
let currentAction = 'publish';
let modalImages = [];
let currentImageIndex = 0;

function showReviewModal(action) {
  const form = document.getElementById('postForm');
  if(!form.checkValidity()) { form.reportValidity(); return; }
  currentAction = action;
  const fd = new FormData(form);
  let html = '';
  
  modalImages = [];
  ['image_cover', 'image_back', 'image_inside', 'image_edge'].forEach(key => {
    const preview = document.getElementById('preview-' + key);
    if (preview && preview.src && !preview.src.endsWith(window.location.pathname) && preview.src !== window.location.href) {
      modalImages.push(preview.src);
    }
  });

  if (modalImages.length > 0) {
    html += '<div style="display:flex; align-items:center; justify-content:center; gap:15px; margin-bottom:15px; background:#f7f3f0; padding:15px; border-radius:8px;">';
    
    if (modalImages.length > 1) {
      html += '<button type="button" onclick="changeModalImage(-1)" style="background:#fff; border:1px solid #ddd; border-radius:50%; width:36px; height:36px; cursor:pointer; font-size:18px; color:#555; display:flex; align-items:center; justify-content:center; flex-shrink:0;">&#10094;</button>';
    } else {
      html += '<div style="width:36px;"></div>';
    }

    html += '<div style="display:flex; flex-direction:column; align-items:center;">';
    html += '<img id="modalCarouselImage" src="' + modalImages[0] + '" style="height:150px; max-width:100%; border-radius:8px; object-fit:contain; box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
    if (modalImages.length > 1) {
      html += '<div id="modalImageIndicator" style="margin-top:8px; font-size:13px; color:#777; font-weight:500;">1 / ' + modalImages.length + '</div>';
    }
    html += '</div>';

    if (modalImages.length > 1) {
      html += '<button type="button" onclick="changeModalImage(1)" style="background:#fff; border:1px solid #ddd; border-radius:50%; width:36px; height:36px; cursor:pointer; font-size:18px; color:#555; display:flex; align-items:center; justify-content:center; flex-shrink:0;">&#10095;</button>';
    } else {
      html += '<div style="width:36px;"></div>';
    }
    html += '</div>';
  }

  const pLine = (label, val) => `<p style="margin:6px 0; border-bottom:1px solid #eee; padding-bottom:5px; display:flex;"><strong style="color:#665d57; width:130px; flex-shrink:0;">${label}:</strong> <span style="flex:1; word-break:break-word;">${val||'-'}</span></p>`;

  const cond = fd.get('condition_level');
  const condText = cond === 'good' ? 'สภาพดี' : (cond === 'fair' ? 'มีตำหนิเล็กน้อย' : 'สภาพเก่า');
  
  const shippings = fd.getAll('shipping[]');
  const shippingTexts = shippings.length > 0 ? shippings.join(', ') : '-';

  html += pLine('ISBN', fd.get('isbn'));
  html += pLine('ชื่อหนังสือ', fd.get('title'));
  html += pLine('ผู้เขียน', fd.get('author'));
  html += pLine('สำนักพิมพ์', fd.get('publisher_name'));
  html += pLine('หมวดหมู่', fd.get('category'));
  html += pLine('ภาษา', fd.get('language'));
  html += pLine('รูปแบบ', fd.get('format'));
  html += pLine('จำนวนหน้า', fd.get('pages') ? fd.get('pages') + ' หน้า' : '-');
  html += pLine('ขนาด', fd.get('dimensions'));
  html += pLine('เรื่องย่อ', (fd.get('synopsis') || '').replace(/\n/g, '<br>'));
  html += pLine('สภาพหนังสือ', condText);
  html += pLine('ราคาขาย', fd.get('price') ? '฿' + fd.get('price') : '-');
  html += pLine('ราคาปก', fd.get('original_price') ? '฿' + fd.get('original_price') : '-');
  html += pLine('รายละเอียดตำหนิ', (fd.get('defects_detail') || '').replace(/\n/g, '<br>'));
  html += pLine('วิธีจัดส่ง', shippingTexts);

  document.getElementById('reviewContent').innerHTML = html;
  document.getElementById('reviewModal').style.display = 'flex';
}

function changeModalImage(dir) {
  if (modalImages.length === 0) return;
  currentImageIndex += dir;
  if (currentImageIndex < 0) currentImageIndex = modalImages.length - 1;
  if (currentImageIndex >= modalImages.length) currentImageIndex = 0;
  document.getElementById('modalCarouselImage').src = modalImages[currentImageIndex];
  const ind = document.getElementById('modalImageIndicator');
  if (ind) ind.innerText = (currentImageIndex + 1) + ' / ' + modalImages.length;
}

function closeReviewModal() { document.getElementById('reviewModal').style.display = 'none'; }
function confirmPost() {
  const form = document.getElementById('postForm');
  const actionInput = document.createElement('input');
  actionInput.type = 'hidden'; actionInput.name = currentAction; actionInput.value = '1';
  form.appendChild(actionInput);
  form.submit();
}
const previewUrls={};function showBookPreview(input,key){const file=input.files&&input.files[0];const slot=document.getElementById('slot-'+key);const preview=document.getElementById('preview-'+key);if(previewUrls[key])URL.revokeObjectURL(previewUrls[key]);if(!file){preview.removeAttribute('src');slot.classList.remove('has-preview');return}previewUrls[key]=URL.createObjectURL(file);preview.src=previewUrls[key];slot.classList.add('has-preview')}</script></body></html>
