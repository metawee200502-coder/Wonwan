<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <span id="modalTitle">แก้ไขหนังสือ</span>
            <button class="btn-close-modal" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action"       value="edit_book_modal">
            <input type="hidden" name="edit_book_id" id="editBookId">

            <div class="modal-group">
                <label>ราคาขาย (บาท)</label>
                <input type="number" step="0.01" name="edit_price" id="editPrice" required min="0">
            </div>
            <div class="modal-group">
                <label>จำนวนสต็อก</label>
                <input type="number" name="edit_stock" id="editStock" required min="0">
            </div>

            <!-- แสดงเฉพาะมือสอง -->
            <div id="usedBookFields" class="modal-group-section" style="display:none;">
                <div class="modal-group">
                    <label>ราคาหน้าปกเดิม (บาท)</label>
                    <input type="number" step="0.01" name="edit_original_price" id="editOriginalPrice" min="0">
                </div>
                <div class="modal-group" style="margin-bottom:0;">
                    <label>สภาพหนังสือ</label>
                    <select name="edit_condition" id="editCondition">
                        <option value="new">เหมือนใหม่</option>
                        <option value="good">สภาพดี</option>
                        <option value="fair">มีตำหนิเล็กน้อย</option>
                        <option value="old">สภาพเก่า</option>
                    </select>
                </div>
            </div>

            <div class="modal-group">
                <label>สถานะ</label>
                <select name="edit_status" id="editStatus">
                    <option value="available">วางขายอยู่</option>
                    <option value="hidden">ซ่อนอยู่</option>
                    <option value="sold">ขายหมดแล้ว</option>
                    <option value="draft">ร่างค้างไว้</option>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeEditModal()">ยกเลิก</button>
                <button type="submit" class="btn-modal-save">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>