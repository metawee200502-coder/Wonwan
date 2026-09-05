<?php
session_start();
require_once __DIR__.'/../config/config.php';

$type=in_array($_GET['type']??'', ['publisher','shop','user'], true)?$_GET['type']:'publisher';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id){http_response_code(404);exit('ไม่พบข้อมูลร้าน');}

$source=match($type){
    'publisher'=>['publishers','publisher_id','company_name'],
    'shop'=>['shops','shop_id','shop_name'],
    default=>['users','user_id','display_name']
};
[$table,$idColumn,$nameColumn]=$source;
$sellerStmt=$pdo->prepare("SELECT * FROM $table WHERE $idColumn=?");
$sellerStmt->execute([$id]);
$seller=$sellerStmt->fetch(PDO::FETCH_ASSOC);
if(!$seller){http_response_code(404);exit('ไม่พบข้อมูลร้าน');}
$sellerName=$type==='user'?($seller[$nameColumn]?:($seller['email']??'ผู้ขาย')):wanawanSellerName($seller[$nameColumn],$type);

$summaryStmt=$pdo->prepare("SELECT COALESCE(AVG(r.rating),0) average_rating,COUNT(r.review_id) review_count FROM books b JOIN book_reviews r ON r.book_id=b.book_id WHERE b.seller_type=? AND b.seller_id=?");
$summaryStmt->execute([$type,$id]);
$summary=$summaryStmt->fetch(PDO::FETCH_ASSOC)?:['average_rating'=>0,'review_count'=>0];
$total=(int)$summary['review_count'];
$page=max(1,(int)($_GET['page']??1));
$perPage=10;
$pages=max(1,(int)ceil($total/$perPage));
$page=min($page,$pages);
$offset=($page-1)*$perPage;
$reviewsStmt=$pdo->prepare("SELECT r.rating,r.review_text,r.created_at,r.seller_reply,r.seller_reply_at,b.book_id,b.title,u.display_name,u.email,u.profile_image FROM books b JOIN book_reviews r ON r.book_id=b.book_id JOIN users u ON u.user_id=r.user_id WHERE b.seller_type=? AND b.seller_id=? ORDER BY r.created_at DESC,r.review_id DESC LIMIT ? OFFSET ?");
$reviewsStmt->bindValue(1,$type);
$reviewsStmt->bindValue(2,$id,PDO::PARAM_INT);
$reviewsStmt->bindValue(3,$perPage,PDO::PARAM_INT);
$reviewsStmt->bindValue(4,$offset,PDO::PARAM_INT);
$reviewsStmt->execute();
$reviews=$reviewsStmt->fetchAll(PDO::FETCH_ASSOC);
function sellerReviewAvatar(?string $value):?string{if(!$value)return null;if(preg_match('#^https?://#i',$value))return $value;$value=ltrim(str_replace('\\','/',$value),'/');return str_starts_with($value,'pages/')?'/'.$value:(str_starts_with($value,'uploads/')?'/pages/'.$value:'/assets/image/'.$value);}
ob_start(function(string $html) use($reviews):string{$index=0;return preg_replace_callback('#(<article class="review">.*?)(</article>)#s',function($match)use(&$index,$reviews){$review=$reviews[$index++]??null;if(!$review||empty($review['seller_reply']))return$match[0];$reply='<div style="grid-column:2;margin-top:12px;padding:13px 16px;border-left:3px solid #d586a4;border-radius:8px;background:#fff5f8"><strong style="color:#a34f70">คำตอบจากร้าน</strong><p style="margin:7px 0">'.nl2br(htmlspecialchars($review['seller_reply'])).'</p><small style="color:#998782">'.date('d/m/Y H:i',strtotime($review['seller_reply_at'])).'</small></div>';return$match[1].$reply.$match[2];},$html)??$html;});
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>รีวิว <?=htmlspecialchars($sellerName)?> - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>"><style>
*{box-sizing:border-box}.page{max-width:980px;margin:auto;padding:42px 24px 90px}.back{display:inline-flex;align-items:center;gap:7px;color:var(--secondary-color);text-decoration:none;margin-bottom:24px}.head{display:flex;justify-content:space-between;align-items:flex-end;gap:25px;padding:28px;border:1px solid #eadbd6;border-radius:18px;background:linear-gradient(135deg,#fff,#fff7f4);margin-bottom:22px}.head h1{margin:0 0 7px;color:var(--secondary-color);font-size:30px}.head p{margin:0;color:var(--text-muted)}.score{text-align:center;min-width:130px}.score strong{display:block;color:#dc9727;font-size:36px}.score span{color:var(--text-muted)}.review{display:grid;grid-template-columns:52px 1fr;gap:16px;padding:24px;border:1px solid #eadbd6;border-radius:16px;background:#fff;margin-bottom:13px}.avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;background:#f7dce6;display:grid;place-items:center;color:#ad5575;font-weight:700}.meta{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.stars{color:#e3a02d;letter-spacing:2px}.date{color:#9b8b84;font-size:13px}.book-link{display:inline-block;margin-top:5px;color:#c46e8d;text-decoration:none;font-size:14px}.review p{white-space:pre-wrap;line-height:1.75;margin:13px 0 0}.empty{text-align:center;padding:80px 20px;border:1px dashed #e1d0ca;border-radius:16px;color:var(--text-muted)}.pagination{display:flex;justify-content:center;gap:8px;margin-top:28px}.pagination a{width:42px;height:42px;display:grid;place-items:center;border:1px solid #dccbc5;border-radius:10px;text-decoration:none;color:var(--secondary-color)}.pagination a.active{background:var(--secondary-color);color:#fff}@media(max-width:600px){.head{align-items:flex-start;flex-direction:column}.score{text-align:left}.review{grid-template-columns:42px 1fr;padding:18px}.avatar{width:42px;height:42px}}
</style></head><body><?php require __DIR__.'/../components/header.php';?><main class="page"><a class="back" href="/pages/partner_detail.php?type=<?=urlencode($type)?>&amp;id=<?=$id?>">‹ กลับไปโปรไฟล์ร้าน</a><header class="head"><div><h1>รีวิวของ <?=htmlspecialchars($sellerName)?></h1><p>ความคิดเห็นจากลูกค้าที่ซื้อสินค้าจริง</p></div><div class="score"><strong><?=number_format((float)$summary['average_rating'],1)?> ★</strong><span><?=number_format($total)?> รีวิว</span></div></header><?php if(!$reviews):?><div class="empty">ร้านนี้ยังไม่มีรีวิวจากผู้ซื้อ</div><?php else:foreach($reviews as $review):$reviewer=$review['display_name']?:$review['email'];?><article class="review"><?php if($avatar=sellerReviewAvatar($review['profile_image']??null)):?><img class="avatar" src="<?=htmlspecialchars($avatar)?>" alt=""><?php else:?><div class="avatar"><?=htmlspecialchars(mb_substr($reviewer,0,1))?></div><?php endif;?><div><div class="meta"><strong><?=htmlspecialchars($reviewer)?></strong><span class="stars"><?=str_repeat('★',(int)$review['rating']).str_repeat('☆',5-(int)$review['rating'])?></span><span class="date"><?=date('d/m/Y',strtotime($review['created_at']))?></span></div><a class="book-link" href="/pages/book_detail.php?id=<?=(int)$review['book_id']?>">รีวิวสินค้า: <?=htmlspecialchars($review['title'])?> ›</a><p><?=htmlspecialchars($review['review_text']?:'ให้คะแนนสินค้าโดยไม่ได้เขียนข้อความเพิ่มเติม')?></p></div></article><?php endforeach;endif;?><?php if($pages>1):?><nav class="pagination"><?php for($i=1;$i<=$pages;$i++):?><a class="<?=$i===$page?'active':''?>" href="?type=<?=urlencode($type)?>&amp;id=<?=$id?>&amp;page=<?=$i?>"><?=$i?></a><?php endfor;?></nav><?php endif;?></main><?php require __DIR__.'/../components/footer.php';?></body></html>
