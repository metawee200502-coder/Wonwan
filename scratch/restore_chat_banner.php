<?php
$file = 'C:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

// Find the corrupted merge point
$search = "
 const composer=document.querySelector('.composer');";
$replace = "
 oldCard.replaceWith(summary);
 
 const msgsArea = document.querySelector('.messages');
 if (msgsArea && data.rawStatus === 'accepted') {
    const banner = document.createElement('div');
    banner.style = 'background: #fff7f2; border-bottom: 1px solid #f2dbe4; padding: 12px; text-align: center;';
    
    if (!data.tradeOption) {
        banner.innerHTML = '<strong style=\"color:#b66f68; font-size:14px;\">⚠️ สถานะกำลังเจรจา</strong><p style=\"margin:4px 0 8px; font-size:12px; color:#8b5266;\">กรุณายื่นข้อเสนอหรือรอให้อีกฝ่ายยื่นข้อเสนอวิธีการแลกเปลี่ยน</p><button id=\"btnBannerPropose\" style=\"background:#d18aa7; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-family:inherit;\">ยื่นข้อเสนอที่นี่</button>';
    } else if (data.tradeOption !== 'meetup') {
        const needsDeadline = (data.tradeOption === 'direct_escrow' || data.tradeOption === 'admin_qc');
        const hasDeadline = data.shippingDeadline > 0;
        
        if (needsDeadline && !hasDeadline) {
             banner.innerHTML = '<strong style=\"color:#856404; font-size:14px;\">⏳ รอข้อสรุปวันจัดส่ง</strong><p style=\"margin:4px 0 0; font-size:12px; color:#856404;\">กรุณาตกลงวันจัดส่งในแชทให้เรียบร้อยก่อนดำเนินการต่อ</p>';
             banner.style.background = '#fff3cd';
             banner.style.borderBottomColor = '#ffeeba';
        } else {
             banner.innerHTML = '<strong style=\"color:#b66f68; font-size:14px;\">📝 ถึงขั้นตอนกรอกสภาพสินค้า</strong><p style=\"margin:4px 0 8px; font-size:12px; color:#8b5266;\">กรุณากรอกฟอร์มยืนยันสภาพสินค้าเพื่อดำเนินการจัดส่ง</p><button id=\"btnBannerCond\" style=\"background:#e67e22; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-family:inherit;\">กรอกฟอร์มยืนยันสภาพสินค้า</button>';
        }
    } else {
         banner.innerHTML = '<strong style=\"color:#166534; font-size:14px;\">📅 นัดรับสำเร็จ</strong><p style=\"margin:4px 0 0; font-size:12px; color:#166534;\">วันที่: ' + (data.meetupDate||'') + ' เวลา: ' + (data.meetupTime||'') + ' น. <br>สถานที่: ' + (data.meetupLocation||'') + '</p>';
         banner.style.background = '#f0fdf4';
         banner.style.borderBottomColor = '#bbf7d0';
    }
    
    msgsArea.parentNode.insertBefore(banner, msgsArea);
    
    const btnProp = banner.querySelector('#btnBannerPropose');
    if(btnProp) btnProp.onclick = () => window.openProposeModal && window.openProposeModal();
    
    const btnCond = banner.querySelector('#btnBannerCond');
    if(btnCond) btnCond.onclick = () => {
        document.getElementById('conditionIframe').src = '/pages/trade_condition_form.php?request_id=' + data.requestId + '&iframe=1&conversation_id=<?= \$conversationId ?>';
        document.getElementById('conditionModal').style.display = 'flex';
    };
 }
 
});
</script>
<?php endif;?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const composer=document.querySelector('.composer');";

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Restored chat.php with banner logic inside data scope";
