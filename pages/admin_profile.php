<?php
session_start();
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/post_notifications.php';
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['publisher_admin','shop_admin'], true)) { header('Location: dashboard.php'); exit; }
$userId = (int)$_SESSION['user_id'];
$isShop=($_SESSION['role']??'')==='shop_admin';
$sellerType=$isShop?'shop':'publisher';
$stmt = $isShop
    ? $pdo->prepare('SELECT s.*,s.shop_id publisher_id,s.shop_name company_name FROM shop_staff ss JOIN shops s ON s.shop_id=ss.shop_id WHERE ss.user_id=? LIMIT 1')
    : $pdo->prepare('SELECT p.* FROM publisher_staff ps JOIN publishers p ON p.publisher_id=ps.publisher_id WHERE ps.user_id=? LIMIT 1');
$stmt->execute([$userId]);
$publisher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$publisher) { header('Location: dashboard.php'); exit; }
$publisherId=(int)$publisher['publisher_id']; $seller_name=$publisher['company_name']; $role=$_SESSION['role']; $active_menu='profile';
$postTable=$isShop?'shop_posts':'publisher_posts';$postOwnerColumn=$isShop?'shop_id':'publisher_id';
if (empty($_SESSION['publisher_profile_csrf'])) $_SESSION['publisher_profile_csrf']=bin2hex(random_bytes(24));
$success=''; $error='';

function publisherMediaUrl(?string $value): string {
    if (!$value) return '';
    $value=str_replace('\\','/',trim($value));
    if (preg_match('#^https?://#i',$value)) return $value;
    $value=ltrim($value,'/');
    if (str_starts_with($value,'pages/')) return '/'.$value;
    if (str_starts_with($value,'uploads/')) {
        if (is_file(__DIR__.'/../'.$value)) return '/'.$value;
        return '/pages/'.$value;
    }
    if (str_starts_with($value,'assets/')) return '/'.$value;
    return '/assets/image/'.$value;
}
function savePublisherImage(array $file,string $prefix,string $folder): string {
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>5*1024*1024) throw new RuntimeException('รูปภาพต้องมีขนาดไม่เกิน 5 MB');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
    if (!$ext) throw new RuntimeException('รองรับเฉพาะรูป JPG, PNG และ WEBP');
    $dir=__DIR__.'/uploads/'.$folder.'/';
    if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์รูปภาพได้');
    $name=$prefix.'_'.bin2hex(random_bytes(8)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'],$dir.$name)) throw new RuntimeException('อัปโหลดรูปภาพไม่สำเร็จ');
    return 'uploads/'.$folder.'/'.$name;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['publisher_profile_csrf'],(string)($_POST['csrf_token']??''))) $error='คำขอหมดอายุ กรุณาลองใหม่';
    else try {
        if (isset($_POST['save_profile'])) {
            $name=trim((string)($_POST['company_name']??'')); $bio=trim((string)($_POST['bio']??''));
            if ($name==='') throw new RuntimeException('กรุณากรอกชื่อสำนักพิมพ์');
            $profileImage=$publisher['profile_image'];
            if (!empty($_FILES['profile_image']['name'])) $profileImage=savePublisherImage($_FILES['profile_image'],($isShop?'shop_':'publisher_').$publisherId,$isShop?'shop_profiles':'publisher_profiles');
            elseif (!empty($_POST['remove_profile_image'])) $profileImage=null;
            $q=$isShop?$pdo->prepare('UPDATE shops SET shop_name=?,description=?,profile_image=? WHERE shop_id=?'):$pdo->prepare('UPDATE publishers SET company_name=?,description=?,profile_image=? WHERE publisher_id=?');
            $q->execute([$name,$bio?:null,$profileImage,$publisherId]);
            $success='บันทึกโปรไฟล์สำนักพิมพ์แล้ว';
        } elseif (isset($_POST['create_post'])) {
            $content=trim((string)($_POST['content']??'')); $image=null;
            $allowedBackgrounds=['#FFFFFF','#FCE8EF','#FFF1DF','#E7F3EC','#E6F1F7','#EFE8F8'];
            $background=in_array($_POST['background_color']??'',$allowedBackgrounds,true)?$_POST['background_color']:'#FFFFFF';
            $isBold=!empty($_POST['is_bold'])?1:0;$isItalic=!empty($_POST['is_italic'])?1:0;
            if (!empty($_FILES['post_image']['name'])) $image=savePublisherImage($_FILES['post_image'],'post_'.$publisherId,$isShop?'shop_posts':'publisher_posts');
            if ($content==='' && !$image) throw new RuntimeException('กรุณาเขียนข้อความหรือแนบรูปภาพ');
            $pdo->prepare("INSERT INTO $postTable($postOwnerColumn,content,image_url,background_color,is_bold,is_italic) VALUES(?,?,?,?,?,?)")->execute([$publisherId,$content,$image,$background,$isBold,$isItalic]);
            $delivery=wanawanNotifyPostFollowers($pdo,$sellerType,$publisherId,(string)$publisher['company_name'],$content);
            $success='เผยแพร่ประกาศแล้ว · แจ้งผู้ติดตาม '.number_format($delivery['followers']).' คน';
        } elseif (isset($_POST['delete_post'])) {
            $pdo->prepare("DELETE FROM $postTable WHERE post_id=? AND $postOwnerColumn=?")->execute([(int)$_POST['post_id'],$publisherId]);
            $success='ลบประกาศแล้ว';
        }
        $stmt->execute([$userId]); $publisher=$stmt->fetch(PDO::FETCH_ASSOC); $seller_name=$publisher['company_name'];
    } catch (Throwable $e) { $error=$e->getMessage(); }
}
$postsStmt=$pdo->prepare("SELECT * FROM $postTable WHERE $postOwnerColumn=? ORDER BY created_at DESC,post_id DESC");
$postsStmt->execute([$publisherId]); $posts=$postsStmt->fetchAll(PDO::FETCH_ASSOC);
$bookCountStmt=$pdo->prepare("SELECT COUNT(*) FROM books WHERE seller_type=? AND seller_id=? AND status='available'");$bookCountStmt->execute([$sellerType,$publisherId]);$bookCount=(int)$bookCountStmt->fetchColumn();
$profileImage=publisherMediaUrl($publisher['profile_image']??null);
$displayPublisherName=wanawanSellerName($publisher['company_name'],$sellerType);
$latestBooksStmt=$pdo->prepare("SELECT book_id,title,author,price,image_cover,book_type FROM books WHERE seller_type=? AND seller_id=? AND status='available' ORDER BY created_at DESC,book_id DESC LIMIT 8");
$latestBooksStmt->execute([$sellerType,$publisherId]);$latestBooks=$latestBooksStmt->fetchAll(PDO::FETCH_ASSOC);
ob_start(function(string $html) use ($latestBooks,$displayPublisherName,$publisher,$posts,$isShop,$publisherId): string {
    $cards='';
    foreach($latestBooks as $item){
        $image=publisherMediaUrl($item['image_cover']??null);
        $cover=$image?'<img src="'.htmlspecialchars($image).'" alt="" style="width:88px;height:124px;object-fit:cover;border-radius:6px">':'<img src="/assets/image/minilogo.png" alt="" style="width:74px;height:74px;object-fit:contain">';
        $cards.='<a href="/pages/book_detail.php?id='.(int)$item['book_id'].'" style="color:inherit;text-decoration:none;border:1px solid #eadde1;border-radius:13px;overflow:hidden;background:#fff;box-shadow:0 3px 10px #6c44500d">'
            .'<div style="height:170px;background:#fae8e1;display:grid;place-items:center">'.$cover.'</div>'
            .'<div style="padding:13px"><strong style="display:block;color:#295e67;margin-bottom:5px">'.htmlspecialchars($item['title']).'</strong>'
            .'<small style="display:block;color:#887980;margin-bottom:8px">'.htmlspecialchars($item['author']?:'-').'</small><b style="color:#c56f8e">฿'.number_format((float)$item['price'],2).'</b></div></a>';
    }
    if($cards==='')$cards='<div style="grid-column:1/-1;text-align:center;padding:36px;color:#8b7d82">ยังไม่มีหนังสือที่เปิดขาย</div>';
    $section='<section class="panel" style="margin-top:20px"><h2>หนังสือที่ลูกค้าเห็นบนโปรไฟล์</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:13px">'.$cards.'</div></section>';
    $marker='<section class="panel" style="margin-top:20px"><h2>โพสต์ของสำนักพิมพ์</h2>';
    $html=str_replace($marker,$section.$marker,$html);
    $html=str_replace('<div class="store-info"><h2>'.htmlspecialchars($publisher['company_name']).'</h2>','<div class="store-info"><h2>'.htmlspecialchars($displayPublisherName).'</h2>',$html);
    $html=str_replace(['สร้างโพสต์ใหม่','เผยแพร่โพสต์','โพสต์ของสำนักพิมพ์','แชร์ข่าวหนังสือใหม่ กิจกรรม หรือเรื่องราวจากสำนักพิมพ์...','ต้องการลบโพสต์นี้ใช่หรือไม่?'],['เพิ่มประกาศใหม่','เผยแพร่ประกาศ','ประกาศของสำนักพิมพ์','เขียนประกาศ ข่าวหนังสือใหม่ กิจกรรม หรือเรื่องราวจากสำนักพิมพ์...','ต้องการลบประกาศนี้ใช่หรือไม่?'],$html);
    $controls='<div class="announcement-style-controls"><strong>สีพื้นหลังประกาศ</strong><div class="color-options">';
    foreach(['#FFFFFF'=>'ขาว','#FCE8EF'=>'ชมพู','#FFF1DF'=>'ครีม','#E7F3EC'=>'เขียว','#E6F1F7'=>'ฟ้า','#EFE8F8'=>'ม่วง'] as $color=>$label)$controls.='<label title="'.$label.'"><input type="radio" name="background_color" value="'.$color.'"'.($color==='#FFFFFF'?' checked':'').'><span style="background:'.$color.'"></span></label>';
    $controls.='</div><div class="text-options"><label><input type="checkbox" name="is_bold" value="1"> <b>ตัวหนา</b></label><label><input type="checkbox" name="is_italic" value="1"> <i>ตัวเอียง</i></label></div></div>';
    $html=preg_replace('#(<textarea name="content"[^>]*>.*?</textarea>)#s','$1'.$controls,$html,1)??$html;
    $postStyles=array_map(fn($post)=>['background'=>$post['background_color']??'#FFFFFF','bold'=>!empty($post['is_bold']),'italic'=>!empty($post['is_italic'])],$posts);
    $extra='<style>.announcement-style-controls{margin:12px 0 16px;padding:13px;border:1px solid #eadde1;border-radius:10px;background:#fffafb}.announcement-style-controls>strong{display:block;font-size:13px;margin-bottom:9px}.color-options{display:flex;gap:9px;flex-wrap:wrap}.color-options input{position:absolute;opacity:0}.color-options span{display:block;width:30px;height:30px;border:2px solid #e1d4d8;border-radius:50%;cursor:pointer;box-shadow:inset 0 0 0 3px #fff}.color-options input:checked+span{border-color:#b9587d;transform:scale(1.1)}.text-options{display:flex;gap:18px;margin-top:12px;font-size:13px}.post{margin:0 0 12px!important;padding:15px!important;border:1px solid #eadde1!important;border-radius:12px!important}.post img{width:220px!important;height:140px!important;max-height:none!important;object-fit:cover!important;border-radius:9px!important;margin-top:8px!important}.post p{margin:8px 0!important}</style><script>document.addEventListener("DOMContentLoaded",()=>{const styles='.json_encode($postStyles,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';document.querySelectorAll(".post").forEach((post,index)=>{const s=styles[index]||{};post.style.backgroundColor=s.background||"#FFFFFF";const text=post.querySelector("p");if(text){text.style.fontWeight=s.bold?"700":"400";text.style.fontStyle=s.italic?"italic":"normal";}});});</script>';
    if($isShop){
        $html=str_replace(
            ['โปรไฟล์สำนักพิมพ์','ชื่อสำนักพิมพ์','แนะนำสำนักพิมพ์','ของสำนักพิมพ์','จากสำนักพิมพ์','สำนักพิมพ์...','type=publisher&amp;id='.$publisherId],
            ['โปรไฟล์ร้านหนังสือ','ชื่อร้านหนังสือ','แนะนำร้านหนังสือ','ของร้านหนังสือ','จากร้านหนังสือ','ร้านหนังสือ...','type=shop&amp;id='.$publisherId],
            $html
        );
    }
    return str_replace('</body>',$extra.'</body>',$html);
});
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>โปรไฟล์สำนักพิมพ์</title><link rel="stylesheet" href="../assets/css/styles.css"><style>
*{box-sizing:border-box}body{margin:0;background:#faf8f7;color:var(--text-dark);display:flex;min-height:100vh}.main{margin-left:260px;width:calc(100% - 260px);padding:36px}.page-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.page-head h1{color:var(--secondary-color);margin:0}.preview-link,.btn{border:0;border-radius:9px;padding:11px 17px;background:var(--secondary-color);color:#fff;text-decoration:none;font:inherit;cursor:pointer}.layout{display:grid;grid-template-columns:minmax(320px,430px) minmax(0,1fr);gap:24px}.panel{background:#fff;border:1px solid #eadde1;border-radius:16px;padding:22px;margin-bottom:20px}.panel h2{margin:0 0 17px;color:var(--secondary-color);font-size:20px}.field{margin-bottom:16px}.field label{display:block;font-weight:600;margin-bottom:7px}.field input[type=text],.field textarea{width:100%;padding:11px 13px;border:1px solid #d9cdd1;border-radius:8px;font:inherit}.field textarea{min-height:120px;resize:vertical}.avatar-preview{width:112px;height:112px;border-radius:18px;overflow:hidden;background:#f5dce5;display:grid;place-items:center;font-size:38px;color:var(--secondary-color);margin-bottom:12px}.avatar-preview img{width:100%;height:100%;object-fit:cover}.remove{display:block;color:#b83e4b;margin:9px 0}.notice{padding:12px 15px;border-radius:9px;margin-bottom:18px}.ok{background:#e7f4e5;color:#39713a}.bad{background:#fde5e3;color:#a63838}.store-preview{background:#fff;border:1px solid #eadde1;border-radius:18px;overflow:hidden}.cover{height:105px;background:linear-gradient(130deg,#f8dfe8,#f1c9d7)}.store-head{display:flex;gap:20px;padding:0 24px 22px}.store-avatar{width:112px;height:112px;border:6px solid #fff;border-radius:20px;margin-top:-48px;background:#f7e3ea;overflow:hidden;display:grid;place-items:center;font-size:38px;color:var(--secondary-color)}.store-avatar img{width:100%;height:100%;object-fit:cover}.store-info{padding-top:18px}.store-info h2{margin:0 0 7px;color:var(--secondary-color)}.store-info p{margin:0;color:#766b70;line-height:1.6}.stats{display:flex;gap:28px;margin-top:14px;color:#7b6870}.composer textarea{width:100%;min-height:95px;padding:12px;border:1px solid #d9cdd1;border-radius:9px;font:inherit;resize:vertical}.post{border-top:1px solid #eee1e5;padding:18px 0}.post:first-of-type{border-top:0}.post-meta{display:flex;justify-content:space-between;color:#8b7d82;font-size:13px;margin-bottom:9px}.post p{white-space:pre-wrap;line-height:1.65}.post img{width:100%;max-height:420px;object-fit:cover;border-radius:12px;margin-top:9px}.delete{border:0;background:none;color:#b83e4b;cursor:pointer}.empty{text-align:center;color:#8b7d82;padding:40px}.hint{font-size:12px;color:#897c81;margin-top:6px}@media(max-width:950px){.layout{grid-template-columns:1fr}.main{margin-left:220px;width:calc(100% - 220px)}}@media(max-width:700px){body{display:block}.main{margin:0;width:100%;padding:18px}.sidebar{position:static!important;width:100%!important}.page-head{align-items:flex-start;gap:12px;flex-direction:column}}
</style></head><body><?php include __DIR__.'/../components/sidebar.php';?><main class="main"><div class="page-head"><h1>โปรไฟล์สำนักพิมพ์</h1><a class="preview-link" href="/pages/partner_detail.php?type=publisher&amp;id=<?=$publisherId?>" target="_blank">ดูหน้าร้านจริง ↗</a></div><?php if($success):?><div class="notice ok"><?=htmlspecialchars($success)?></div><?php endif;?><?php if($error):?><div class="notice bad"><?=htmlspecialchars($error)?></div><?php endif;?><div class="layout"><div><section class="panel"><h2>แก้ไขข้อมูลร้าน</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['publisher_profile_csrf'])?>"><div class="avatar-preview"><?php if($profileImage):?><img id="avatarImg" src="<?=htmlspecialchars($profileImage)?>" alt=""><?php else:?><span id="avatarLetter"><?=htmlspecialchars(mb_substr($publisher['company_name'],0,1))?></span><?php endif;?></div><div class="field"><label>รูปโปรไฟล์</label><input id="profileFile" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp"><div class="hint">JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB</div><?php if($profileImage):?><label class="remove"><input type="checkbox" name="remove_profile_image" value="1"> ลบรูปโปรไฟล์เดิม</label><?php endif;?></div><div class="field"><label>ชื่อสำนักพิมพ์</label><input type="text" name="company_name" required maxlength="255" value="<?=htmlspecialchars($publisher['company_name'])?>"></div><div class="field"><label>Bio / แนะนำสำนักพิมพ์</label><textarea name="bio" maxlength="2000" placeholder="เล่าเรื่องราว แนวหนังสือ และจุดเด่นของสำนักพิมพ์..."><?=htmlspecialchars($publisher['description']??'')?></textarea></div><button class="btn" name="save_profile">บันทึกโปรไฟล์</button></form></section><section class="panel composer"><h2>สร้างโพสต์ใหม่</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['publisher_profile_csrf'])?>"><textarea name="content" maxlength="5000" placeholder="แชร์ข่าวหนังสือใหม่ กิจกรรม หรือเรื่องราวจากสำนักพิมพ์..."></textarea><div class="field" style="margin-top:10px"><input type="file" name="post_image" accept="image/jpeg,image/png,image/webp"></div><button class="btn" name="create_post">เผยแพร่โพสต์</button></form></section></div><div><section class="store-preview"><div class="cover"></div><div class="store-head"><div class="store-avatar"><?php if($profileImage):?><img src="<?=htmlspecialchars($profileImage)?>" alt=""><?php else:?><?=htmlspecialchars(mb_substr($publisher['company_name'],0,1))?><?php endif;?></div><div class="store-info"><h2><?=htmlspecialchars($publisher['company_name'])?></h2><p><?=nl2br(htmlspecialchars($publisher['description']?:'เพิ่ม Bio เพื่อแนะนำสำนักพิมพ์ของคุณ'))?></p><div class="stats"><span><?=number_format((int)($publisher['followers_count']??0))?> ผู้ติดตาม</span><span><?=number_format($bookCount)?> หนังสือ</span></div></div></div></section><section class="panel" style="margin-top:20px"><h2>โพสต์ของสำนักพิมพ์</h2><?php if(!$posts):?><div class="empty">ยังไม่มีโพสต์ ลองแชร์ข่าวสารแรกของสำนักพิมพ์ได้เลย</div><?php else:foreach($posts as $post):?><article class="post"><div class="post-meta"><span><?=date('d/m/Y H:i',strtotime($post['created_at']))?></span><form method="post" onsubmit="return confirm('ต้องการลบโพสต์นี้ใช่หรือไม่?')"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['publisher_profile_csrf'])?>"><input type="hidden" name="post_id" value="<?=(int)$post['post_id']?>"><button class="delete" name="delete_post">ลบโพสต์</button></form></div><?php if($post['content']!==''):?><p><?=htmlspecialchars($post['content'])?></p><?php endif;?><?php if($postImg=publisherMediaUrl($post['image_url']??null)):?><img src="<?=htmlspecialchars($postImg)?>" alt="รูปประกอบโพสต์"><?php endif;?></article><?php endforeach;endif;?></section></div></div></main><script>document.getElementById('profileFile')?.addEventListener('change',e=>{const f=e.target.files[0];if(!f)return;let img=document.getElementById('avatarImg');if(!img){img=document.createElement('img');img.id='avatarImg';document.querySelector('.avatar-preview').replaceChildren(img)}img.src=URL.createObjectURL(f)});</script></body></html>
