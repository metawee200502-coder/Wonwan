<?php
$file = 'C:\laragon\www\wanawan_project\pages\return_request.php';
$content = file_get_contents($file);

// 1. Transparent background
$content = preg_replace('/body\s*\{\s*font-family[^}]*background:\s*#f8f9fa;[^}]*\}/', 'body { font-family: var(--font-family); background: transparent; color: #1a1a2e; min-height: 100vh; }', $content);
$content = preg_replace('/body\s*\{\s*background:\s*#f8f9fa;\s*\}/', 'body { background: transparent; }', $content);

// 2. Fix JS innerHTML
$content = str_replace("btn.textContent = '<svg", "btn.innerHTML = '<svg", $content);
$content = str_replace("t.textContent = msg;", "t.innerHTML = msg;", $content);

// 3. Fix close/back buttons for iframe
$content = preg_replace('#<a href="/pages/orders\.php" class="modal-close"[^>]*>#', '<a href="#" class="modal-close" title="ปิด" onclick="event.preventDefault(); window.parent.postMessage(\'close_rr_modal\', \'*\');">', $content);
$content = preg_replace('#<a href="/pages/orders\.php" class="btn-back">#', '<a href="#" class="btn-back" onclick="event.preventDefault(); window.parent.postMessage(\'close_rr_modal\', \'*\');">', $content);

// 4. Change JS fetch logic to direct form submission
$js_regex = '#form\.addEventListener\(\'submit\', async function\(e\)\s*\{.*?\}\);#s';
$new_js = <<<JS
form.addEventListener('submit', function(e) {
    const reason   = document.getElementById('reason').value;
    const tracking = document.getElementById('return_tracking').value.trim();
    const file     = fileInput ? fileInput.files[0] : null;
    const msgs = [];
    if (!reason)   msgs.push('กรุณาเลือกสาเหตุการคืนสินค้า');
    if (!file && !document.getElementById('form-error').textContent) msgs.push('กรุณาแนบรูปหลักฐาน');
    if (!tracking) msgs.push('กรุณากรอกเลขพัสดุที่ส่งคืน');

    if (msgs.length) {
        e.preventDefault();
        errDiv.innerHTML = msgs.join(' &middot; ');
        errDiv.style.display = 'flex';
        errDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '⏳ กำลังส่งคำขอ…';
});
JS;
$content = preg_replace($js_regex, $new_js, $content);

// 5. Add form attributes and error div
$content = str_replace('<form id="return-form" novalidate>', '<form id="return-form" method="POST" action="" enctype="multipart/form-data" novalidate>', $content);
$content = str_replace('<div id="form-error" class="alert alert-error" style="display:none;"></div>', '<?php if(!empty($formError)): ?><div id="form-error" class="alert alert-error" style="display:flex;"><?=htmlspecialchars($formError)?></div><?php else: ?><div id="form-error" class="alert alert-error" style="display:none;"></div><?php endif; ?>', $content);

// 6. Insert direct PHP processing at the top
$php_logic = <<<'PHP'
<?php
/**
 * pages/return_request.php?order_id=X
 */
session_start();
if (empty($_SESSION['user_id'])) { header('Location:/pages/login.php'); exit; }
if (in_array($_SESSION['role'] ?? '', ['shop_admin','publisher_admin','super_admin'], true)) {
    header('Location:/pages/dashboard.php'); exit;
}
require_once __DIR__ . '/../config/config.php';

$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

if ($orderId <= 0) { header('Location:/pages/orders.php'); exit; }

$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['return_request_csrf']) || !hash_equals($_SESSION['return_request_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $formError = 'คำขอหมดอายุ กรุณาลองใหม่';
    } else {
        $reason         = trim($_POST['reason'] ?? '');
        $returnTracking = trim($_POST['return_tracking'] ?? '');
        $allowedReasons = ['damaged', 'not_as_described', 'wrong_item'];

        if (!in_array($reason, $allowedReasons, true)) $formError = 'กรุณาเลือกสาเหตุการคืน';
        elseif ($returnTracking === '') $formError = 'กรุณากรอกเลขพัสดุที่ส่งคืน';
        elseif (empty($_FILES['evidence']) || $_FILES['evidence']['error'] === UPLOAD_ERR_NO_FILE) $formError = 'กรุณาแนบรูปหลักฐาน';
        elseif ($_FILES['evidence']['error'] !== UPLOAD_ERR_OK) $formError = 'เกิดข้อผิดพลาดระหว่างอัปโหลดไฟล์';
        else {
            $file       = $_FILES['evidence'];
            $maxSize    = 5 * 1024 * 1024;
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['size'] > $maxSize) $formError = 'ไฟล์ต้องมีขนาดไม่เกิน 5MB';
            elseif (!in_array($ext, $allowedExt, true)) $formError = 'รองรับเฉพาะไฟล์ JPG, PNG หรือ WebP';
            else {
                $orderStmt = $pdo->prepare("SELECT o.order_id, o.shipping_status FROM orders o WHERE o.order_id = ? AND o.user_id = ? LIMIT 1");
                $orderStmt->execute([$orderId, $userId]);
                $orderChk = $orderStmt->fetch(PDO::FETCH_ASSOC);

                if (!$orderChk) $formError = 'ไม่พบคำสั่งซื้อ';
                elseif ($orderChk['shipping_status'] !== 'delivered') $formError = 'สถานะคำสั่งซื้อไม่สามารถคืนได้';
                else {
                    $existStmt = $pdo->prepare('SELECT return_id FROM return_requests WHERE order_id = ? LIMIT 1');
                    $existStmt->execute([$orderId]);
                    if ($existStmt->fetchColumn()) $formError = 'คำสั่งซื้อนี้มีคำขอคืนสินค้าอยู่แล้ว';
                    else {
                        $sellerStmt = $pdo->prepare("SELECT b.seller_type, b.seller_id FROM order_items oi JOIN books b ON b.book_id = oi.book_id WHERE oi.order_id = ? LIMIT 1");
                        $sellerStmt->execute([$orderId]);
                        $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);

                        if (!$seller) $formError = 'ไม่พบข้อมูลผู้ขาย';
                        else {
                            $amountStmt = $pdo->prepare("SELECT COALESCE(SUM(oi.price_at_purchase * oi.quantity), 0) subtotal, o.shipping_fee FROM orders o JOIN order_items oi ON oi.order_id = o.order_id WHERE o.order_id = ? GROUP BY o.order_id");
                            $amountStmt->execute([$orderId]);
                            $amounts = $amountStmt->fetch(PDO::FETCH_ASSOC);
                            $refundAmount = (float)($amounts['subtotal'] ?? 0) + (float)($amounts['shipping_fee'] ?? 0);

                            $uploadDir = __DIR__ . '/../uploads/returns/';
                            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                            $filename    = uniqid('ret_', true) . '.' . $ext;
                            $destination = $uploadDir . $filename;

                            if (move_uploaded_file($file['tmp_name'], $destination)) {
                                $evidenceUrl = 'uploads/returns/' . $filename;
                                $pdo->beginTransaction();
                                try {
                                    $insertStmt = $pdo->prepare("INSERT INTO return_requests (order_id, user_id, seller_type, seller_id, reason, evidence_url, return_tracking, refund_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                                    $insertStmt->execute([$orderId, $userId, $seller['seller_type'], $seller['seller_id'], $reason, $evidenceUrl, $returnTracking, $refundAmount]);
                                    
                                    $notifyIds = wanawanSellerUserIds($pdo, $seller['seller_type'], (int)$seller['seller_id']);
                                    foreach ($notifyIds as $adminId) {
                                        wanawanNotifyUser($pdo, $adminId, 'มีคำขอคืนสินค้าใหม่', 'ลูกค้ายื่นขอคืนสินค้าสำหรับคำสั่งซื้อ #' . $orderId, 'order', ['related_order_id' => $orderId]);
                                    }
                                    $pdo->commit();
                                    echo "<script>window.parent.postMessage('reload_orders', '*');</script>";
                                    exit;
                                } catch (Throwable $e) {
                                    $pdo->rollBack();
                                    @unlink($destination);
                                    $formError = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
                                }
                            } else {
                                $formError = 'บันทึกไฟล์ไม่สำเร็จ';
                            }
                        }
                    }
                }
            }
        }
    }
}
PHP;

$content = preg_replace('#<\?php\s*/\*\*.*?\*/\s*session_start\(\);.*?require_once __DIR__ \. \'/../config/config\.php\';\s*\$userId.*?\$orderId.*?if \(\$orderId <= 0\).*?exit;\s*\}#s', $php_logic, $content);

file_put_contents($file, $content);
echo "All fixes applied!\n";
