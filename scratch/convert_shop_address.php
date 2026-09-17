<?php
$file = 'C:\laragon\www\wanawan_project\pages\shop_return_address.php';
$content = file_get_contents($file);

$postLogic = <<<'EOF'
$active_menu = 'return_address';

$formError = '';
$formSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactName  = trim($_POST['return_contact_name'] ?? '');
    $contactPhone = trim($_POST['return_contact_phone'] ?? '');
    $address      = trim($_POST['return_address'] ?? '');

    if ($contactName === '') $formError = 'กรุณากรอกชื่อผู้ติดต่อ / ผู้รับ';
    elseif ($contactPhone === '') $formError = 'กรุณากรอกเบอร์โทรศัพท์';
    elseif ($address === '') $formError = 'กรุณากรอกที่อยู่เต็ม';
    elseif (!preg_match('/^0\d{8,9}$/', $contactPhone)) $formError = 'เบอร์โทรศัพท์ไม่ถูกต้อง (เช่น 0812345678)';
    else {
        try {
            if ($sellerType === 'publisher') {
                $upd = $pdo->prepare('UPDATE publishers SET return_contact_name = ?, return_contact_phone = ?, return_address = ? WHERE publisher_id = ?');
            } else {
                $upd = $pdo->prepare('UPDATE shops SET return_contact_name = ?, return_contact_phone = ?, return_address = ? WHERE shop_id = ?');
            }
            $upd->execute([$contactName, $contactPhone, $address, $sellerId]);
            logSellerAction($pdo, $sellerType, $sellerId, $userId, 'UPDATE_RETURN_ADDRESS', (string)$sellerId, 'อัปเดตที่อยู่รับสินค้าคืน');
            $formSuccess = 'บันทึกข้อมูลเรียบร้อยแล้ว';
            
            $sellerRow['return_contact_name'] = $contactName;
            $sellerRow['return_contact_phone'] = $contactPhone;
            $sellerRow['return_address'] = $address;
        } catch (Throwable $e) {
            $formError = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
        }
    }
}
EOF;
$content = str_replace('$active_menu = \'return_address\';', $postLogic, $content);

// Form tag
$content = preg_replace('/<form\s+id="address-form"[^>]*>/', '<form id="address-form" method="POST" action="">', $content);

// Notifications
$noticeHtml = <<<'EOF'
            <div id="msg-container">
                <?php if ($formError): ?>
                    <div class="notice notice-error" style="margin-bottom:20px"><?=htmlspecialchars($formError)?></div>
                <?php elseif ($formSuccess): ?>
                    <div class="notice notice-success" style="margin-bottom:20px"><?=htmlspecialchars($formSuccess)?></div>
                <?php endif; ?>
            </div>
EOF;
$content = preg_replace('/<div\s+id="msg-container"><\/div>/', $noticeHtml, $content);

// Remove fetch from JS
$jsRegex = '/form\.addEventListener\(\'submit\',.*?\}\);/s';
$newJs = <<<'EOF'
form.addEventListener('submit', function(e) {
        const contactName  = document.getElementById('return_contact_name').value.trim();
        const contactPhone = document.getElementById('return_contact_phone').value.trim();
        const address      = document.getElementById('return_address').value.trim();
        
        let err = '';
        if (!contactName) err = 'กรุณากรอกชื่อผู้ติดต่อ / ผู้รับ';
        else if (!contactPhone) err = 'กรุณากรอกเบอร์โทรศัพท์';
        else if (!address) err = 'กรุณากรอกที่อยู่เต็ม';
        else if (!/^0\d{8,9}$/.test(contactPhone)) err = 'เบอร์โทรศัพท์ไม่ถูกต้อง';

        if (err) {
            e.preventDefault();
            showNotice('error', err);
            return;
        }
        
        saveBtn.disabled = true;
        saveBtn.textContent = '⏳ กำลังบันทึกข้อมูล…';
    });
EOF;
$content = preg_replace($jsRegex, $newJs, $content);

file_put_contents($file, $content);
echo "Done";
