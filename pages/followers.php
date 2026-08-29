<?php
session_start();
require_once __DIR__.'/../config/config.php';

$type=in_array($_GET['type']??'', ['user','publisher','shop'],true)?$_GET['type']:'user';
$targetId=max(1,(int)($_GET['id']??0));
$targetConfig=[
    'user'=>['table'=>'users','id'=>'user_id','name'=>"COALESCE(NULLIF(display_name,''),email)"],
    'publisher'=>['table'=>'publishers','id'=>'publisher_id','name'=>'company_name'],
    'shop'=>['table'=>'shops','id'=>'shop_id','name'=>'shop_name'],
][$type];
$targetStmt=$pdo->prepare("SELECT {$targetConfig['name']} profile_name FROM {$targetConfig['table']} WHERE {$targetConfig['id']}=?");
$targetStmt->execute([$targetId]);
$profileName=$targetStmt->fetchColumn();
if(!$profileName){http_response_code(404);exit('ไม่พบโปรไฟล์');}

$stmt=$pdo->prepare("
    SELECT u.user_id,COALESCE(NULLIF(u.display_name,''),u.email) display_name,u.profile_image,
           (SELECT COUNT(*) FROM profile_follows pf2 WHERE pf2.target_type='user' AND pf2.target_id=u.user_id) followers_count,
           ((SELECT COUNT(*) FROM exchanges e WHERE e.owner_id=u.user_id AND e.status<>'completed')
            +(SELECT COUNT(*) FROM books b WHERE b.seller_type='user' AND b.seller_id=u.user_id AND b.status='available')) books_count
    FROM profile_follows pf
    JOIN users u ON u.user_id=pf.follower_user_id
    WHERE pf.target_type=? AND pf.target_id=?
    ORDER BY pf.created_at DESC
");
$stmt->execute([$type,$targetId]);
$followers=$stmt->fetchAll(PDO::FETCH_ASSOC);

function followerImage(?string $value):?string{
    if(!$value)return null;
    if(preg_match('#^https?://#i',$value))return $value;
    $value=ltrim(str_replace('\\','/',$value),'/');
    return str_starts_with($value,'uploads/')?'/pages/'.$value:'/assets/image/profiles/'.$value;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ผู้ติดตามของ <?=htmlspecialchars($profileName)?> - วนวรรณ</title>
<link rel="stylesheet" href="../assets/css/styles.css">
<style>
*{box-sizing:border-box}.followers-wrap{max-width:1160px;margin:auto;padding:50px 40px 90px}.followers-head{display:flex;align-items:center;gap:15px;margin-bottom:28px}.followers-head h1{margin:0;color:var(--secondary-color);font-size:32px;display:flex;align-items:center;gap:10px}.followers-head p{margin:4px 0 0;color:var(--text-muted);font-size:15px}.followers-count{font-size:18px;color:var(--text-muted);font-weight:normal}.followers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:22px}.followers-empty{grid-column:1/-1;padding:80px 0;color:var(--text-muted);font-size:16px;text-align:center}@media(max-width:900px){.followers-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.followers-wrap{padding:28px 18px 60px}.followers-head h1{font-size:26px}.followers-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php require __DIR__.'/../components/header.php';?>
<main class="followers-wrap">
 <header class="followers-head">
  <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="padding:8px;background:#fdf2f5;border-radius:12px"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
  <div>
   <h1>ผู้ติดตาม <span class="followers-count">(<?=number_format(count($followers))?> คน)</span></h1>
   <p><?=htmlspecialchars($profileName)?></p>
  </div>
 </header>
 <section class="followers-grid">
 <?php if(!$followers):?>
  <div class="followers-empty">
   ยังไม่มีใครติดตาม
  </div>
 <?php else:foreach($followers as $row):
  $partner=['id'=>(int)$row['user_id'],'type'=>'user','name'=>$row['display_name'],'image'=>followerImage($row['profile_image']??null),'followers'=>(int)$row['followers_count'],'books_count'=>(int)$row['books_count'],'profile_url'=>'/pages/exchange_profile.php?user_id='.(int)$row['user_id']];
  require __DIR__.'/../components/partner_card.php';
 endforeach;endif;?>
 </section>
</main>
<?php require __DIR__.'/../components/footer.php';?>
</body>
</html>
