<?php
function parse_chat_system_message($text, $pdo = null, $userId = null) {
    if (preg_match('/\[CONDITION_FORM_SUBMITTED:(\d+)\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg,#fff7ed 0%,#ffedd5 100%);border:1px solid #fed7aa;padding:10px 14px;border-radius:10px;color:#9a3412;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">' .
            '<span style="font-size:14px;margin-right:4px;">📝</span> <strong>ส่งฟอร์มสภาพสินค้าแล้ว</strong> กรุณาตรวจสอบและยืนยันรายละเอียดหนังสือ' .
            '</div>';
    } elseif (preg_match('/\[CONDITION_FORM_ACCEPTED:(\d+)\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">' .
            '<span style="font-size:14px;margin-right:4px;">✅</span> <strong>ยอมรับฟอร์มสภาพสินค้า</strong> ยอมรับรายละเอียดหนังสือของอีกฝ่ายเรียบร้อยแล้ว' .
            '</div>';
    } elseif (preg_match('/\[P2P_ITEM_RECEIVED:(\d+)\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">' .
            '<span style="font-size:14px;margin-right:4px;">🎉</span> <strong>ยืนยันการรับสินค้า</strong> ได้รับสินค้าเรียบร้อยแล้ว' .
            '</div>';
    } elseif (preg_match('/\[QC_TRACKING_SUBMITTED:(\d+)\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);border:1px solid #d8b4fe;padding:10px 14px;border-radius:10px;color:#6b21a8;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">📦</span> <strong>แจ้งเลขพัสดุ QC</strong> จัดส่งหนังสือให้ส่วนกลางและแจ้งเลขพัสดุเรียบร้อยแล้ว'.
            '</div>';
    } elseif (preg_match('/\[P2P_TRACKING_SUBMITTED:(.*?):(.*?)\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);border:1px solid #bfdbfe;padding:10px 14px;border-radius:10px;color:#1e40af;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">📦</span> <strong>จัดส่งสินค้าแล้ว</strong><br>ขนส่ง: <strong>'.htmlspecialchars($matches[1]).'</strong> เลขพัสดุ: <strong>'.htmlspecialchars($matches[2]).'</strong>'.
            '</div>';
    } elseif (preg_match('/\[P2P_TRACKING_SUBMITTED:(\d+)\]/', $text, $matches)) {
        // Fallback for old messages
        return '<div style="background:linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);border:1px solid #bfdbfe;padding:10px 14px;border-radius:10px;color:#1e40af;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">📦</span> <strong>แจ้งเลขพัสดุ</strong> จัดส่งสินค้าและแจ้งเลขพัสดุเรียบร้อยแล้ว'.
            '</div>';
    } elseif (preg_match('/\[MEETUP_RECEIVED\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);border:1px solid #fef08a;padding:10px 14px;border-radius:10px;color:#854d0e;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">🤝</span> <strong>ยืนยันการนัดรับ</strong> กดยืนยันการรับสินค้าจากการนัดรับแล้ว'.
            '</div>';
    } elseif (preg_match('/\[EXCHANGE_COMPLETED:meetup\]/', $text, $matches)) {
        return '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">🎉</span> <strong>การแลกเปลี่ยนสำเร็จ!</strong> ทั้งสองฝ่ายยืนยันการรับสินค้าเรียบร้อยแล้ว'.
            '</div>';
    }
    return null;
}
