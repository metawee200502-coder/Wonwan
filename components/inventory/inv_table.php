<?php
// รับ $books จาก inventory.php
?>
<table class="inv-table">
    <thead>
        <tr>
            <th style="width:40px;">
                <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
            </th>
            <th>สินค้า</th>
            <th>หมวดหมู่/สภาพ</th>
            <th>ราคา</th>
            <th>จำนวนคงเหลือ</th>
            <th>สถานะ</th>
            <th>จัดการ</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($books)): ?>
        <tr>
            <td colspan="7" style="text-align:center;padding:60px;color:var(--text-muted)">
                ยังไม่มีสินค้าที่ตรงกับเงื่อนไข —
                <a href="add_book.php" style="color:var(--primary-color)">เพิ่มหนังสือใหม่</a>
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($books as $b):
            $is_low = $b['stock_quantity'] <= 5;
            if ($b['status'] === 'draft') {
                $status_class = 'status-draft';
                $status_label = 'ร่างค้างไว้';
            } elseif ($b['status'] === 'available') {
                $status_class = $is_low ? 'status-low' : 'status-available';
                $status_label = $is_low ? 'ใกล้หมด' : 'วางขายอยู่';
            } elseif ($b['status'] === 'hidden') {
                $status_class = 'status-hidden';
                $status_label = 'ซ่อนอยู่';
            } else {
                $status_class = 'status-sold';
                $status_label = 'ขายหมดแล้ว';
            }
        ?>
        <tr>
            <td>
                <input type="checkbox" name="book_ids[]"
                       value="<?= $b['book_id'] ?>"
                       class="book-checkbox"
                       onchange="updateBulkCount()">
            </td>
            <td>
                <div class="book-title"><?= htmlspecialchars($b['title']) ?></div>
                <div class="book-author">นักเขียน: <?= htmlspecialchars($b['author'] ?? '-') ?></div>
            </td>
            <td>
                <?= htmlspecialchars($b['category'] ?? '-') ?><br>
                <span class="badge <?= $b['book_type']==='new'?'badge-new':'badge-used' ?>">
                    <?= $b['book_type']==='new' ? 'มือหนึ่ง' : 'มือสอง ('.($b['condition_level']??'').')' ?>
                </span>
            </td>
            <td><span class="price-text">฿<?= number_format($b['price'],2) ?></span></td>
            <td><?= number_format($b['stock_quantity']) ?> เล่ม</td>
            <td><span class="status-dot <?= $status_class ?>"><?= $status_label ?></span></td>
            <td>
                <button type="button" class="action-btn"
                    onclick="openEditModal(
                        <?= $b['book_id'] ?>,
                        '<?= htmlspecialchars($b['title'], ENT_QUOTES) ?>',
                        '<?= $b['book_type'] ?>',
                        <?= $b['price'] ?>,
                        <?= $b['stock_quantity'] ?>,
                        '<?= $b['status'] ?>',
                        '<?= $b['original_price'] ?? '' ?>',
                        '<?= $b['condition_level'] ?? 'new' ?>'
                    )">แก้ไข</button>
                <button type="button" class="action-btn action-btn-del"
                        onclick="deleteSingle(<?= $b['book_id'] ?>)">ลบ</button>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>