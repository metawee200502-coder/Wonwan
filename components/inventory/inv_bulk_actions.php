<div class="bulk-actions" id="bulkActions">
    <span class="bulk-actions-text" id="bulkCount">เลือก 0 รายการ</span>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center;">
        <select name="new_status" class="select-control">
            <option value="">-- เปลี่ยนสถานะเป็น... --</option>
            <option value="available">วางขายอยู่</option>
            <option value="hidden">ซ่อนอยู่</option>
            <option value="sold">ขายหมดแล้ว</option>
            <option value="draft">ร่างค้างไว้</option>
        </select>
        <button type="submit" name="action" value="change_status" class="btn-bulk btn-bulk-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            นำไปใช้
        </button>
        <button type="button" class="btn-bulk btn-bulk-danger" onclick="deleteBulk()">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            ลบทิ้ง
        </button>
    </div>
</div>