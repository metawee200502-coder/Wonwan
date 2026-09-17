<?php
$file = 'c:\\laragon\\www\\wanawan_project\\pages\\trade_upload_slip.php';
$content = file_get_contents($file);
$content = preg_replace('/INSERT IGNORE INTO orders \(user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status\)/', "INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method)", $content);
$content = preg_replace('/VALUES \(\?, \?, \?, \?, \?, \'paid\', \'pending\'\)/', "VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A')", $content);
file_put_contents($file, $content);

$file2 = 'c:\\laragon\\www\\wanawan_project\\admin\\escrow.php';
if(file_exists($file2)) {
    $content2 = file_get_contents($file2);
    $content2 = preg_replace('/INSERT IGNORE INTO orders \(user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, slip_image_url\)/', "INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method, slip_image_url)", $content2);
    $content2 = preg_replace('/VALUES \(\?, \?, \?, \?, \?, \'paid\', \'pending\', \?\)/', "VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A', ?)", $content2);
    file_put_contents($file2, $content2);
}

$file3 = 'c:\\laragon\\www\\wanawan_project\\admin\\qc_exchanges.php';
if(file_exists($file3)) {
    $content3 = file_get_contents($file3);
    $content3 = preg_replace('/INSERT IGNORE INTO orders \(user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, slip_image_url\)/', "INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method, slip_image_url)", $content3);
    $content3 = preg_replace('/VALUES \(\?, \?, \?, \?, \?, \'paid\', \'pending\', \?\)/', "VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A', ?)", $content3);
    file_put_contents($file3, $content3);
}

echo "Done regex patch fields\n";
