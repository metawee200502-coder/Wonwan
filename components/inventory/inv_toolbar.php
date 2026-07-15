<?php
// รับ $tab, $status_filter, $sort จาก inventory.php
?>
<div class="table-toolbar">
    <a href="?tab=all&status=<?= $status_filter ?>&sort=<?= $sort ?>"
       class="tab-btn <?= $tab==='all'?'active':'' ?>">ทั้งหมด</a>
    <a href="?tab=new&status=<?= $status_filter ?>&sort=<?= $sort ?>"
       class="tab-btn <?= $tab==='new'?'active':'' ?>">มือหนึ่ง</a>
    <div class="tab-divider"></div>
    <a href="?tab=used&status=<?= $status_filter ?>&sort=<?= $sort ?>"
       class="tab-btn <?= $tab==='used'?'active':'' ?>">มือสอง</a>

    <div class="toolbar-right">
        <!-- Filter สถานะ -->
        <form method="GET" style="display:flex;align-items:center;gap:6px;margin:0;">
            <input type="hidden" name="tab"  value="<?= $tab ?>">
            <input type="hidden" name="sort" value="<?= $sort ?>">
            <label style="font-size:var(--text-xs);color:var(--text-muted)">สถานะ:</label>
            <select name="status" class="select-control" onchange="this.form.submit()">
                <option value="all"       <?= $status_filter==='all'       ?'selected':'' ?>>ทั้งหมด</option>
                <option value="available" <?= $status_filter==='available' ?'selected':'' ?>>วางขายอยู่</option>
                <option value="low_stock" <?= $status_filter==='low_stock' ?'selected':'' ?>>ใกล้หมด</option>
                <option value="hidden"    <?= $status_filter==='hidden'    ?'selected':'' ?>>ซ่อนอยู่</option>
                <option value="sold"      <?= $status_filter==='sold'      ?'selected':'' ?>>ขายหมดแล้ว</option>
                <option value="draft"     <?= $status_filter==='draft'     ?'selected':'' ?>>ร่างค้างไว้</option>
            </select>
        </form>
        <!-- จัดเรียง -->
        <form method="GET" style="display:flex;align-items:center;gap:6px;margin:0;">
            <input type="hidden" name="tab"    value="<?= $tab ?>">
            <input type="hidden" name="status" value="<?= $status_filter ?>">
            <label style="font-size:var(--text-xs);color:var(--text-muted)">จัดเรียง:</label>
            <select name="sort" class="select-control" onchange="this.form.submit()">
                <option value="latest"     <?= $sort==='latest'    ?'selected':'' ?>>ใหม่สุด</option>
                <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>ราคา ต่ำ→สูง</option>
                <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>ราคา สูง→ต่ำ</option>
                <option value="stock_asc"  <?= $sort==='stock_asc' ?'selected':'' ?>>สต็อกน้อยสุด</option>
            </select>
        </form>
    </div>
</div>