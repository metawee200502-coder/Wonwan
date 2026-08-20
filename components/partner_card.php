<?php
$partner = is_array($partner ?? null) ? $partner : [];
$partnerName = (string)($partner['name'] ?? 'พาร์ทเนอร์วนวรรณ');
$partnerImage = $partner['image'] ?? null;
?>
<style>
.partner-card{border:1px solid #d8ddd4;border-radius:12px;padding:24px 22px 20px;background:#fff;text-align:center;display:flex;flex-direction:column;align-items:center;min-width:0}.partner-card__image{width:96px;height:96px;border-radius:10px;background:var(--accent-pink-light);display:grid;place-items:center;overflow:hidden;color:var(--secondary-color);font-size:32px;font-weight:700}.partner-card__image img{width:100%;height:100%;object-fit:cover}.partner-card__name{color:var(--secondary-color);font-size:18px;font-weight:700;margin:12px 0 8px;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.partner-card__stats{display:flex;justify-content:center;margin-bottom:18px}.partner-card__stat{min-width:76px;padding:0 14px;color:var(--secondary-color)}.partner-card__stat+.partner-card__stat{border-left:1px solid #d8ddd4}.partner-card__stat strong,.partner-card__stat span{display:block}.partner-card__stat strong{font-size:18px}.partner-card__stat span{font-size:var(--text-xs);color:var(--text-muted)}.partner-card__actions{display:grid;gap:8px;width:100%;margin-top:auto}.partner-card__btn{height:44px;border-radius:8px;border:1px solid #d3948b;font-family:var(--font-family);font-size:var(--text-md);display:grid;place-items:center;text-decoration:none;cursor:pointer}.partner-card__profile{background:#d3948b;color:#fff}.partner-card__follow{background:#fff;color:#d3948b}.partner-card__follow.is-following{background:#f7dfe8;color:var(--secondary-color)}
</style>
<article class="partner-card">
 <div class="partner-card__image"><?php if($partnerImage): ?><img src="<?=htmlspecialchars($partnerImage)?>" alt="<?=htmlspecialchars($partnerName)?>"><?php else: ?><?=htmlspecialchars(mb_substr($partnerName,0,1))?><?php endif;?></div>
 <h2 class="partner-card__name"><?=htmlspecialchars($partnerName)?></h2>
 <div class="partner-card__stats"><div class="partner-card__stat"><strong data-followers-type="<?=htmlspecialchars($partner['type']??'')?>" data-followers-id="<?=(int)($partner['id']??0)?>"><?=number_format((int)($partner['followers']??0))?></strong><span>ผู้ติดตาม</span></div><div class="partner-card__stat"><strong><?=number_format((int)($partner['books_count']??0))?></strong><span>เล่ม</span></div></div>
 <div class="partner-card__actions"><a class="partner-card__btn partner-card__profile" href="<?=htmlspecialchars($partner['profile_url']??'#')?>">ดูโปรไฟล์</a><?php $follow_type=$partner['type']??'';$follow_id=(int)($partner['id']??0);$follow_class='partner-card__btn partner-card__follow';require __DIR__.'/follow_button.php';?></div>
</article>
