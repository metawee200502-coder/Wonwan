<?php
// รับ $categories และ $seller_name จาก add_book.php
?>
<div class="section-card">
    <div class="section-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
        </svg>
        รายละเอียดหนังสือ
    </div>

    <div class="form-group" style="margin-bottom:14px">
        <label>ISBN <span style="font-size:var(--text-xs);color:var(--text-muted)">(กรอกแล้วกด "ค้นหา" เพื่อดึงข้อมูลอัตโนมัติ)</span></label>
        <div class="isbn-row">
            <input type="text" name="isbn" id="isbn_input" class="form-control" placeholder="กรอกเลข ISBN">
            <button type="button" class="btn-lookup" id="btnLookup" onclick="lookupISBN()">
                <svg id="lookupIcon" xmlns="http://www.w3.org/2000/svg" width="15" height="15"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <span id="lookupText">ค้นหา</span>
            </button>
        </div>
    </div>

    <div class="form-group" style="margin-bottom:14px">
        <label>ISBN-13</label>
        <input type="text" name="isbn_13" id="isbn_13" class="form-control" placeholder="ISBN-13">
    </div>

    <div class="form-grid-2" style="margin-bottom:14px">
        <div class="form-group">
            <label>ชื่อหนังสือ <span>*</span></label>
            <input type="text" name="title" id="book_title" class="form-control" placeholder="ระบุชื่อหนังสือ">
        </div>
        <div class="form-group">
            <label>ผู้เขียน</label>
            <input type="text" name="author" id="book_author" class="form-control" placeholder="ระบุผู้แต่ง">
        </div>
    </div>

    <div class="form-grid-2" style="margin-bottom:14px">
        <div class="form-group">
            <label>สำนักพิมพ์</label>
            <input type="text" name="publisher_name" id="publisher_name" class="form-control"
                   value="<?= htmlspecialchars($seller_name ?? '') ?>" readonly>
        </div>
        <div class="form-group">
            <label>หมวดหมู่</label>
            <select name="category" id="book_category" class="form-control">
                <option value="">เลือกหมวดหมู่</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>"><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-grid-3" style="margin-bottom:14px">
        <div class="form-group">
            <label>รูปแบบ (Format)</label>
            <select name="format" id="book_format" class="form-control">
                <option value="">เลือกรูปแบบ</option>
                <option>Paperback</option>
                <option>Hardcover</option>
                <option>E-Book</option>
                <option>Pocket Book</option>
            </select>
        </div>
        <div class="form-group">
            <label>จำนวนหน้า</label>
            <input type="number" name="pages" id="book_pages" class="form-control" placeholder="เช่น 320" min="1">
        </div>
        <div class="form-group">
            <label>ภาษา</label>
            <select name="language" id="book_language" class="form-control">
                <option value="ไทย">ไทย</option>
                <option value="อังกฤษ">อังกฤษ</option>
                <option value="ญี่ปุ่น">ญี่ปุ่น</option>
                <option value="จีน">จีน</option>
                <option value="อื่นๆ">อื่นๆ</option>
            </select>
        </div>
    </div>

    <div class="form-grid-2" style="margin-bottom:14px">
        <div class="form-group">
            <label>ขนาด (เช่น 14.5 x 21 x 2.2 ซม.)</label>
            <input type="text" name="dimensions" id="book_dimensions" class="form-control" placeholder="กว้าง x ยาว x หนา ซม.">
        </div>
        <div class="form-group">
            <label>น้ำหนัก (กรัม)</label>
            <input type="number" name="weight_grams" id="book_weight" class="form-control" placeholder="เช่น 350">
        </div>
    </div>

    <div class="form-group" style="margin-bottom:14px">
        <label>จำนวนสต็อก</label>
        <input type="number" name="stock_quantity" class="form-control" value="1" min="1">
    </div>

    <div class="form-group">
        <label>เรื่องย่อ / คำอธิบาย</label>
        <textarea name="synopsis" id="book_synopsis" class="form-control" rows="4"
                  placeholder="ระบุเรื่องย่อหรือรายละเอียดหนังสือ"></textarea>
    </div>
</div>
