<?php
// รับ $page, $total_pages, $total_records, $offset, $limit, $tab, $status_filter, $sort
?>
<div class="pagination-row">
    <span class="page-info">
        แสดง <?= $offset+1 ?> ถึง <?= min($offset+$limit, $total_records) ?>
        จากทั้งหมด <?= number_format($total_records) ?> รายการ
    </span>
    <div class="page-btns">
        <a href="?tab=<?=$tab?>&status=<?=$status_filter?>&sort=<?=$sort?>&page=<?=$page-1?>"
           class="page-btn <?= $page<=1?'disabled':'' ?>">&#8249; ก่อนหน้า</a>
        <?php foreach (range(max(1,$page-2), min($total_pages,$page+2)) as $p): ?>
            <a href="?tab=<?=$tab?>&status=<?=$status_filter?>&sort=<?=$sort?>&page=<?=$p?>"
               class="page-btn <?= $p==$page?'active':'' ?>"><?= $p ?></a>
        <?php endforeach; ?>
        <a href="?tab=<?=$tab?>&status=<?=$status_filter?>&sort=<?=$sort?>&page=<?=$page+1?>"
           class="page-btn <?= $page>=$total_pages||$total_pages===0?'disabled':'' ?>">ถัดไป &#8250;</a>
    </div>
</div>