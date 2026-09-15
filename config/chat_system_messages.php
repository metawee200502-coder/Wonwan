<?php
function parse_chat_system_message($text, $pdo = null, $userId = null) {
    if (preg_match('/\[P2P_ITEM_RECEIVED:(\d+)\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">' .
            '<span style="font-size:14px;margin-right:4px;">🎉</span> <strong>ยืนยันการรับสินค้า</strong> ได้รับสินค้าเรียบร้อยแล้ว' .
            '</div>';
    } elseif (preg_match('/\[QC_TRACKING_SUBMITTED:(\d+)\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);border:1px solid #d8b4fe;padding:10px 14px;border-radius:10px;color:#6b21a8;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">📦</span> <strong>แจ้งเลขพัสดุ QC</strong> จัดส่งหนังสือให้ส่วนกลางและแจ้งเลขพัสดุเรียบร้อยแล้ว'.
            '</div>';
    }
    return null;
}
