<?php
// รับ $stats จาก inventory.php
?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">รายการทั้งหมด</div>
        <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">มือหนึ่ง</div>
        <div class="stat-value"><?= number_format($stats['total_new'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">มือสอง</div>
        <div class="stat-value"><?= number_format($stats['total_used'] ?? 0) ?></div>
    </div>
    <div class="stat-card alert">
        <div class="stat-label">ใกล้หมด</div>
        <div class="stat-value"><?= number_format($stats['low_stock'] ?? 0) ?></div>
    </div>
</div>