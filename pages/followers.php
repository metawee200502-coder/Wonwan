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
*{box-sizing:border-box}.followers-wrap{max-width:1120px;margin:auto;padding:42px 40px 90px}.followers-back{display:inline-flex;align-items:center;gap:7px;color:var(--secondary-color);text-decoration:none;margin-bottom:25px}.followers-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:28px}.followers-head h1{margin:0;color:var(--secondary-color);font-size:34px}.followers-head p{margin:6px 0 0;color:var(--text-muted)}.followers-count{color:var(--secondary-color);font-weight:700}.followers-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}.followers-empty{grid-column:1/-1;padding:90px 20px;border:1px dashed #d4dcd2;border-radius:15px;text-align:center;color:var(--text-muted);background:#fff}@media(max-width:900px){.followers-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.followers-wrap{padding:28px 18px 60px}.followers-head{align-items:flex-start;flex-direction:column}.followers-head h1{font-size:28px}.followers-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php require __DIR__.'/../components/header.php';?>
<main class="followers-wrap">
 <a class="followers-back" href="javascript:history.back()">← กลับไปยังโปรไฟล์</a>
 <header class="followers-head"><div><h1>ผู้ติดตาม</h1><p><?=htmlspecialchars($profileName)?></p></div><span class="followers-count"><?=number_format(count($followers))?> คน</span></header>
 <section class="followers-grid">
 <?php if(!$followers):?><div class="followers-empty">โปรไฟล์นี้ยังไม่มีผู้ติดตาม</div>
 <?php else:foreach($followers as $row):
  $partner=['id'=>(int)$row['user_id'],'type'=>'user','name'=>$row['display_name'],'image'=>followerImage($row['profile_image']??null),'followers'=>(int)$row['followers_count'],'books_count'=>(int)$row['books_count'],'profile_url'=>'/pages/exchange_profile.php?user_id='.(int)$row['user_id']];
  require __DIR__.'/../components/partner_card.php';
 endforeach;endif;?>
 </section>
</main>
<?php require __DIR__.'/../components/footer.php';?>
</body>
</html>
