<?php
/**
 * Topbar component (แถบด้านบน)
 *
 * ตัวแปรที่เตรียมไว้ก่อน include ได้ (ไม่บังคับ):
 * - $breadcrumbs (array) เช่น [['label'=>'แผงควบคุม','href'=>'dashboard.php'], ['label'=>'จัดการคลังสินค้า','href'=>null]]
 *   ถ้าใส่ $breadcrumbs จะโชว์ breadcrumb แทนช่องค้นหา
 * - $show_search (bool) default true — โชว์ช่องค้นหา (ใช้คู่กับ filterTable() ในหน้านั้นๆ)
 * - $seller_name (string) ชื่อสำนักพิมพ์/ร้านค้า
 */
$breadcrumbs = $breadcrumbs ?? [];
$show_search = $show_search ?? true;
$seller_name = $seller_name ?? '';
?>
<div class="topbar">
    <?php if (!empty($breadcrumbs)): ?>
        <span class="breadcrumb">
            <?php foreach ($breadcrumbs as $i => $bc): ?>
                <?php if (!empty($bc['href'])): ?>
                    <a href="<?= htmlspecialchars($bc['href']) ?>"><?= htmlspecialchars($bc['label']) ?></a>
                <?php else: ?>
                    <?= htmlspecialchars($bc['label']) ?>
                <?php endif; ?>
                <?= $i < count($breadcrumbs) - 1 ? ' &rsaquo; ' : '' ?>
            <?php endforeach; ?>
        </span>
    <?php elseif ($show_search): ?>
        <div class="search-box">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="searchInput" placeholder="ค้นหาบันทึกข้อมูล..." onkeyup="filterTable()">
        </div>
    <?php else: ?>
        <span></span>
    <?php endif; ?>

    <div class="topbar-whoami">
        <span class="topbar-seller"><?= htmlspecialchars($seller_name ?: '-') ?></span>
        <span class="topbar-sep">·</span>
        <span class="topbar-admin"><?= htmlspecialchars($_SESSION['display_name'] ?? 'ผู้ใช้') ?></span>
    </div>
</div>
