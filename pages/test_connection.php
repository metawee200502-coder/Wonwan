<?php
// ============================================================
// test_connection.php — ทดสอบว่าเครื่อง localhost เชื่อมต่ออินเทอร์เน็ตได้ไหม
// วิธีใช้: เอาไฟล์นี้ไปวางในโฟลเดอร์เดียวกับ add_book.php
// แล้วเปิดผ่านเบราว์เซอร์ เช่น http://localhost/wanawan/test_connection.php
// ============================================================

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:monospace; font-size:14px; line-height:1.6;'>";

echo "===== ตรวจสอบพื้นฐาน =====\n";
echo "PHP Version: " . phpversion() . "\n";
echo "cURL extension โหลดอยู่ไหม: " . (function_exists('curl_init') ? "✅ ใช่" : "❌ ไม่ใช่ — ต้องเปิด extension=curl ใน php.ini") . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? "✅ เปิดอยู่" : "❌ ปิดอยู่") . "\n";
echo "openssl extension: " . (extension_loaded('openssl') ? "✅ โหลดอยู่" : "❌ ไม่ได้โหลด") . "\n";
echo "curl.cainfo (php.ini): " . (ini_get('curl.cainfo') ?: "(ไม่ได้ตั้งค่า)") . "\n";
echo "openssl.cafile (php.ini): " . (ini_get('openssl.cafile') ?: "(ไม่ได้ตั้งค่า)") . "\n";

echo "\n===== ทดสอบเชื่อมต่อ Google Books ด้วย cURL =====\n";
if (function_exists('curl_init')) {
    $ch = curl_init("https://www.googleapis.com/books/v1/volumes?q=isbn:9780141439518");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_VERBOSE        => false,
    ]);
    $result = curl_exec($ch);
    $errno  = curl_errno($ch);
    $error  = curl_error($ch);
    $info   = curl_getinfo($ch);
    curl_close($ch);

    if ($result === false) {
        echo "❌ cURL ล้มเหลว\n";
        echo "   Error code: {$errno}\n";
        echo "   Error message: {$error}\n";
        echo "\n";
        if (strpos($error, 'SSL certificate') !== false || strpos($error, 'certificate') !== false) {
            echo "   >>> นี่คือปัญหา SSL certificate ไม่ครบ (เจอบ่อยมากใน XAMPP Windows) <<<\n";
            echo "   วิธีแก้: ดูขั้นตอน 'แก้ปัญหา SSL certificate' ด้านล่าง\n";
        } elseif (strpos($error, 'Could not resolve host') !== false || strpos($error, 'resolve') !== false) {
            echo "   >>> เครื่องนี้หา DNS/อินเทอร์เน็ตไม่เจอเลย เช็คว่าต่อเน็ตอยู่ไหม หรือมี Firewall/Antivirus บล็อก <<<\n";
        } elseif (strpos($error, 'Connection timed out') !== false || strpos($error, 'timeout') !== false) {
            echo "   >>> เชื่อมต่อได้แต่ timeout เช็ค Firewall/Antivirus/Proxy ที่อาจบล็อก outbound port 443 <<<\n";
        }
    } else {
        echo "✅ เชื่อมต่อสำเร็จ! HTTP Status: {$info['http_code']}\n";
        $data = json_decode($result, true);
        if (!empty($data['items'][0]['volumeInfo']['title'])) {
            echo "✅ ดึงข้อมูลหนังสือได้จริง: " . $data['items'][0]['volumeInfo']['title'] . "\n";
        } else {
            echo "⚠️ เชื่อมต่อได้ แต่ไม่พบข้อมูลหนังสือ (แปลก เพราะ ISBN นี้ต้องมีข้อมูลแน่ๆ) response แรกๆ:\n";
            echo substr($result, 0, 300) . "\n";
        }
    }
} else {
    echo "❌ ข้าม เพราะไม่มี cURL extension\n";
}

echo "\n===== ทดสอบเชื่อมต่อด้วย file_get_contents (fallback) =====\n";
if (ini_get('allow_url_fopen')) {
    $context = stream_context_create(['http' => ['timeout' => 8]]);
    $resp = @file_get_contents("https://www.googleapis.com/books/v1/volumes?q=isbn:9780141439518", false, $context);
    if ($resp === false) {
        $err = error_get_last();
        echo "❌ ล้มเหลว: " . ($err['message'] ?? 'ไม่ทราบสาเหตุ') . "\n";
    } else {
        echo "✅ เชื่อมต่อสำเร็จผ่าน file_get_contents\n";
    }
} else {
    echo "❌ ข้าม เพราะ allow_url_fopen ปิดอยู่\n";
}

echo "\n===== สรุป =====\n";
echo "ถ้าทั้ง 2 วิธีข้างบน ❌ ทั้งคู่ แปลว่าเครื่องนี้เชื่อมต่ออินเทอร์เน็ตออกไม่ได้จริงๆ\n";
echo "ให้เช็ค Windows Firewall / Antivirus ว่าบล็อก Apache/PHP (httpd.exe, php.exe) ไว้หรือเปล่า\n";

echo "</pre>";
