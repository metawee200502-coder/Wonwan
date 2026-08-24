<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: /pages/login.php?redirect='.urlencode('/pages/post_exchange.php')); exit; }
require_once __DIR__ . '/../config/config.php';
$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $isbn=preg_replace('/[^0-9Xx]/','',$_POST['isbn']??''); $title=trim($_POST['title']??''); $author=trim($_POST['author']??'');
    $category=trim($_POST['category']??''); $condition=$_POST['condition_percent']??''; $wanted=$_POST['wanted_type']??'';
    $wantedDetail=trim($_POST['wanted_detail']??''); $story=trim($_POST['story_reason']??'');
    $defects=$_POST['defects']??[]; $shipping=$_POST['shipping']??[];
    if (!in_array(strlen($isbn),[10,13],true)) $errors[]='กรุณากรอก ISBN 10 หรือ 13 หลัก';
    if ($title==='') $errors[]='กรุณากรอกชื่อหนังสือ';
    if (!in_array($condition,['99','90','80','70'],true)) $errors[]='กรุณาเลือกสภาพหนังสือ';
    if (!in_array($wanted,['specific','category','any'],true)) $errors[]='กรุณาเลือกรูปแบบที่ต้องการแลก';
    if (!$shipping) $errors[]='กรุณาเลือกวิธีจัดส่งอย่างน้อย 1 วิธี';
    $imageFields=['image_cover','image_back','image_inside','image_edge']; $paths=[]; $uploaded=[];
    $uploadDir=__DIR__.'/uploads/exchanges'; if (!is_dir($uploadDir)) mkdir($uploadDir,0775,true);
    foreach ($imageFields as $field) {
        if (empty($_FILES[$field]['name'])) { $paths[$field]=null; continue; }
        $file=$_FILES[$field];
        if ($file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024) { $errors[]='รูปภาพต้องมีขนาดไม่เกิน 5 MB'; continue; }
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($extensions[$mime])) { $errors[]='รองรับเฉพาะรูป JPG, PNG และ WEBP'; continue; }
        $name='exchange_'.bin2hex(random_bytes(10)).'.'.$extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'],$uploadDir.'/'.$name)) { $errors[]='อัปโหลดรูปภาพไม่สำเร็จ'; continue; }
        $uploaded[]=$uploadDir.'/'.$name; $paths[$field]='uploads/exchanges/'.$name;
    }
    if (!$paths['image_cover']) $errors[]='กรุณาอัปโหลดรูปหน้าปก';
    if (!$errors) {
        $stmt=$pdo->prepare('INSERT INTO exchanges (owner_id,isbn,title,author,category,condition_percent,defect_types,image_cover,image_back,image_inside,image_edge,wanted_type,wanted_detail,shipping_methods,story_reason,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"open")');
        $stmt->execute([(int)$_SESSION['user_id'],$isbn,$title,$author?:null,$category?:null,$condition,implode(',',$defects),$paths['image_cover'],$paths['image_back'],$paths['image_inside'],$paths['image_edge'],$wanted,$wantedDetail?:null,implode(',',$shipping),$story?:null]);
        $success = 'โพสต์ประกาศแลกเปลี่ยนสำเร็จ!';
        $insertedExchangeId = $pdo->lastInsertId();
        $cover = '/pages/' . ltrim($paths['image_cover'], '/');
    }
    if (empty($success)) {
        foreach ($uploaded as $file) if (is_file($file)) unlink($file);
    }
}
function old(string $key): string { return htmlspecialchars($_POST[$key]??''); }
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ลงประกาศหนังสือแลกเปลี่ยน - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css">
<style>*{box-sizing:border-box}.page{max-width:1080px;margin:auto;padding:35px 40px 70px}.crumb{color:#766c66;margin-bottom:24px}.crumb a{color:inherit;text-decoration:none}.heading{color:var(--secondary-color);font-size:34px;margin:0}.sub{color:#567342;margin:4px 0 28px}.section{border:1px solid #e1d8d1;border-radius:13px;padding:26px 30px;margin-bottom:4px;background:#fff}.section h2{display:flex;align-items:center;font-size:18px;color:var(--secondary-color);margin:0 0 22px}.num{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:#f0e8e1;color:var(--secondary-color);margin-right:12px;flex-shrink:0}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}.field{margin-bottom:18px}.field label{display:block;color:#746b65;font-size:14px;margin-bottom:7px}.field input,.field select,.field textarea{width:100%;border:1px solid #d8d8d0;border-radius:8px;padding:11px 13px;font:inherit;background:#fff}.field textarea{min-height:100px;resize:vertical}.isbn-row{display:flex;gap:10px}.isbn-row input{flex:1}.lookup{border:0;border-radius:8px;padding:0 20px;background:var(--secondary-color);color:#fff;cursor:pointer}.lookup-message{font-size:13px;margin-top:6px;color:#567342}.choices{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.choice input{position:absolute;opacity:0}.choice span{display:block;padding:18px 8px;text-align:center;border:1px solid #d8d8d0;border-radius:10px;cursor:pointer}.choice input:checked+span{background:#d3948b;color:#fff;border-color:#d3948b}.checks{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.check{padding:12px;background:#f7f4f2;border-radius:7px;color:#635d58}.uploads{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.upload{height:180px;border:2px dashed #cbd2c3;border-radius:10px;display:grid;place-items:center;text-align:center;color:#777;cursor:pointer;overflow:hidden}.upload input{display:none}.upload img{width:100%;height:100%;object-fit:cover}.actions{display:flex;gap:14px;margin-top:26px}.publish{padding:15px 34px;border:0;border-radius:8px;background:#d3948b;color:#fff;font:inherit;font-weight:600;cursor:pointer}.cancel{padding:14px 28px;border:1px solid #d3948b;border-radius:8px;color:#d3948b;text-decoration:none}.errors{padding:16px 20px;border-radius:10px;background:#fde5e1;color:#9b443c;margin-bottom:18px}.required{color:#c96d62}@media(max-width:700px){.page{padding:25px 16px}.grid2,.choices,.checks,.uploads{grid-template-columns:1fr 1fr}.section{padding:22px 18px}}@media(max-width:430px){.grid2,.choices,.checks,.uploads{grid-template-columns:1fr}.isbn-row{flex-direction:column}.lookup{height:44px}}</style></head><body>
<?php require __DIR__.'/../components/header.php';?><main class="page"><h1 class="heading"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:bottom; margin-right:8px; color:var(--secondary-color);"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>ลงประกาศหนังสือของคุณ</h1><p class="sub">แบ่งปันความรู้และเรื่องราวที่คุณรักให้แก่คนอื่นในชุมชนนี้</p>
<?php if($errors):?><div class="errors"><?=implode('<br>',array_map('htmlspecialchars',$errors))?></div><?php endif; ?>
<?php if(!empty($success)): ?>
<div class="modal-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:4000;">
  <div class="modal-content" style="background:#fff; padding:30px; border-radius:12px; max-width:400px; width:90%; text-align:center;">
    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#567342" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
    <h2 style="margin-top:0; color:var(--secondary-color); margin-bottom:15px;"><?=htmlspecialchars($success)?></h2>
    
    <?php if(!empty($cover)): ?>
    <img src="<?=htmlspecialchars($cover)?>" style="width:110px; height:150px; object-fit:cover; border-radius:8px; box-shadow:0 3px 10px rgba(0,0,0,0.15); margin:0 auto 15px; display:block; border:1px solid #eee;">
    <?php endif; ?>

    <div style="background:#f7f4f2; padding:15px; border-radius:8px; margin-bottom:20px; text-align:left; font-size:15px;">
      <p style="margin:5px 0;"><strong>ชื่อหนังสือ:</strong> <?=old('title')?></p>
      <p style="margin:5px 0;"><strong>ต้องการแลกกับ:</strong> <?=old('wanted_type')==='specific'?'หนังสือเฉพาะเล่ม':(old('wanted_type')==='category'?'ตามหมวดหมู่':'เปิดรับทุกข้อเสนอ')?></p>
      <p style="margin:5px 0;"><strong>หมวดหมู่:</strong> <?=old('category') ?: '-'?></p>
    </div>
    <div style="display:flex; gap:10px; justify-content:center;">
      <a href="/pages/post_exchange.php" class="cancel" style="flex:1; display:flex; align-items:center; justify-content:center; padding:12px;">เพิ่มโพสต์ใหม่</a>
      <a href="/pages/book_detail.php?source=exchange&id=<?=$insertedExchangeId?>" class="publish" style="flex:1; text-decoration:none; display:flex; align-items:center; justify-content:center; padding:12px;">ดูโพสต์</a>
    </div>
  </div>
</div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" id="postForm">
<section class="section"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></span>รายละเอียดหนังสือ</h2><div class="field"><label>ISBN <span class="required">*</span></label><div class="isbn-row"><input id="isbn" name="isbn" value="<?=old('isbn')?>" maxlength="17" placeholder="ISBN 10 หรือ 13 หลัก" required><button class="lookup" type="button" id="lookup">ค้นหาข้อมูล</button></div><div class="lookup-message" id="lookup-message">กรอก ISBN เพื่อค้นหาชื่อ ผู้แต่ง และหมวดหมู่จากข้อมูลในระบบ</div></div><div class="grid2"><div class="field"><label>ชื่อหนังสือ <span class="required">*</span></label><input id="title" name="title" value="<?=old('title')?>" required></div><div class="field"><label>ผู้แต่ง</label><input id="author" name="author" value="<?=old('author')?>"></div></div><div class="field"><label>หมวดหมู่</label><input id="category" name="category" value="<?=old('category')?>" placeholder="เช่น นวนิยาย วรรณกรรมไทย จิตวิทยา"></div></section>
<section class="section"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></span>สภาพหนังสือ</h2><div class="choices"><?php foreach(['99'=>'99% เหมือนใหม่','90'=>'90% ดี','80'=>'80% พอใช้','70'=>'<70% อ่านเล่น'] as $v=>$label):?><label class="choice"><input type="radio" name="condition_percent" value="<?=$v?>" <?=($_POST['condition_percent']??'')===$v?'checked':''?> required><span><?=$label?></span></label><?php endforeach;?></div><p>ตำหนิเฉพาะจุด (เลือกได้มากกว่า 1 ข้อ)</p><div class="checks"><?php foreach(['จุดเหลือง','รอยพับ','ลายเซ็นเจ้าของเดิม','สันกาวหลุด','รอยไฮไลต์'] as $d):?><label class="check"><input type="checkbox" name="defects[]" value="<?=$d?>" <?=in_array($d,$_POST['defects']??[],true)?'checked':''?>> <?=$d?></label><?php endforeach;?></div></section>
<section class="section"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg></span>รายละเอียดสภาพจริง</h2><p>กรุณาอัปโหลดรูปหน้าปก และรูปมุมอื่นเพื่อให้ผู้สนใจเห็นสภาพจริง</p><div class="uploads"><?php foreach(['image_cover'=>'หน้าปก *','image_back'=>'หลังปก','image_inside'=>'สันหนังสือ','image_edge'=>'ขอบกระดาษ'] as $field=>$label):?><label class="upload"><input type="file" name="<?=$field?>" accept="image/jpeg,image/png,image/webp" <?=$field==='image_cover'?'required':''?>><span>▧<br><?=$label?></span></label><?php endforeach;?></div></section>
<section class="section"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg></span>ความต้องการในการแลกเปลี่ยน</h2><div class="choices"><?php foreach(['specific'=>'หนังสือเฉพาะเล่ม','category'=>'ตามหมวดหมู่','any'=>'เปิดรับทุกข้อเสนอ'] as $v=>$label):?><label class="choice"><input type="radio" name="wanted_type" value="<?=$v?>" <?=($_POST['wanted_type']??'')===$v?'checked':''?> required><span><?=$label?></span></label><?php endforeach;?></div><div class="field" style="margin-top:18px"><label>หนังสือหรือหมวดหมู่ที่กำลังตามหา</label><textarea name="wanted_detail"><?=old('wanted_detail')?></textarea></div></section>
<section class="section"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg></span>การจัดส่งและนัดรับ</h2><div class="checks"><?php foreach(['ไปรษณีย์ไทย','บริษัทขนส่งเอกชน','นัดรับ'] as $s):?><label class="check"><input type="checkbox" name="shipping[]" value="<?=$s?>" <?=in_array($s,$_POST['shipping']??[],true)?'checked':''?>> <?=$s?></label><?php endforeach;?></div></section>
<section class="section"><h2><span class="num"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></span>เรื่องราวของหนังสือเล่มนี้</h2><div class="field"><label>ทำไมคุณถึงส่งต่อหนังสือเล่มนี้?</label><textarea name="story_reason" placeholder="แบ่งปันความทรงจำหรือเหตุผลที่คุณรักหนังสือเล่มนี้..."><?=old('story_reason')?></textarea></div></section>
<div class="actions">
<button class="publish" type="button" onclick="showReviewModal()">ลงประกาศแลกเปลี่ยน</button>
<a class="cancel" href="/pages/exchange_books.php">ยกเลิก</a>
</div>
<!-- Review Modal -->
<div id="reviewModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:3000;">
  <style>.modal-content::-webkit-scrollbar { display: none; }</style>
  <div class="modal-content" style="background:#fff; padding:25px; border-radius:12px; max-width:500px; width:90%; max-height:80vh; overflow-y:auto; scrollbar-width:none; -ms-overflow-style:none;">
    <h2 style="margin-top:0; color:var(--secondary-color);">ตรวจสอบข้อมูลก่อนโพสต์</h2>
    <div id="reviewContent" class="review-details" style="margin-bottom:20px; font-size:15px; line-height:1.6;"></div>
    <div class="modal-actions" style="display:flex; gap:12px; justify-content:flex-end;">
      <button class="cancel" style="background:#fff; cursor:pointer;" type="button" onclick="closeReviewModal()">แก้ไขรายละเอียดเพิ่ม</button>
      <button class="publish" type="button" onclick="confirmPost()">โพสต์เลย</button>
    </div>
  </div>
</div>
</form></main><?php require __DIR__.'/../components/footer.php';?>
<script>
let modalImages = [];
let currentImageIndex = 0;

function showReviewModal() {
  const form = document.getElementById('postForm');
  if(!form.checkValidity()) { form.reportValidity(); return; }
  const fd = new FormData(form);
  let html = '';
  
  modalImages = [];
  ['image_cover', 'image_back', 'image_inside', 'image_edge'].forEach(key => {
    const fileInput = document.querySelector('input[name="' + key + '"]');
    const preview = fileInput ? fileInput.parentElement.querySelector('img') : null;
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

  const conds = {'99':'99% เหมือนใหม่', '90':'90% ดี', '80':'80% พอใช้', '70':'<70% อ่านเล่น'};
  const wanted = {'specific':'หนังสือเฉพาะเล่ม', 'category':'ตามหมวดหมู่', 'any':'เปิดรับทุกข้อเสนอ'};
  const shippings = fd.getAll('shipping[]');
  const defects = fd.getAll('defects[]');

  html += pLine('ISBN', fd.get('isbn'));
  html += pLine('ชื่อหนังสือ', fd.get('title'));
  html += pLine('ผู้แต่ง', fd.get('author'));
  html += pLine('หมวดหมู่', fd.get('category'));
  html += pLine('สภาพหนังสือ', conds[fd.get('condition_percent')] || '-');
  html += pLine('ตำหนิ', defects.length > 0 ? defects.join(', ') : 'ไม่มี');
  html += pLine('ต้องการแลกกับ', wanted[fd.get('wanted_type')] || '-');
  html += pLine('รายละเอียดเพิ่มเติม', (fd.get('wanted_detail') || '').replace(/\n/g, '<br>'));
  html += pLine('การจัดส่ง', shippings.length > 0 ? shippings.join(', ') : '-');
  html += pLine('เรื่องราวหนังสือ', (fd.get('story_reason') || '').replace(/\n/g, '<br>'));
  
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
function confirmPost() { document.getElementById('postForm').submit(); }

document.getElementById('lookup').addEventListener('click',async()=>{const isbn=document.getElementById('isbn').value,msg=document.getElementById('lookup-message');msg.textContent='กำลังค้นหา...';try{const data=await fetch('/pages/handlers/isbn_lookup.php?isbn='+encodeURIComponent(isbn)).then(r=>r.json());msg.textContent=data.message||'พบข้อมูลหนังสือแล้ว';if(data.success){['title','author','category'].forEach(k=>{if(data.book[k])document.getElementById(k).value=data.book[k];});}}catch(e){msg.textContent='ค้นหาข้อมูลไม่สำเร็จ';}});document.querySelectorAll('.upload input').forEach(input=>input.addEventListener('change',()=>{const file=input.files[0],label=input.parentElement;if(!file)return;const reader=new FileReader;reader.onload=()=>{label.querySelector('span').style.display='none';let preview=label.querySelector('img');if(!preview){preview=document.createElement('img');preview.alt='ตัวอย่างรูป';label.appendChild(preview);}preview.src=reader.result;};reader.readAsDataURL(file);}));</script></body></html>
