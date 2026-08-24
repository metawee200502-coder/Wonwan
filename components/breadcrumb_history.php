<?php
// components/breadcrumb_history.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['breadcrumb_history'])) {
    $_SESSION['breadcrumb_history'] = [];
}

$current_url = $_SERVER['REQUEST_URI'];
$current_path = parse_url($current_url, PHP_URL_PATH);

// หน้าที่จะให้รีเซ็ตประวัติกลับไปเป็น "หน้าหลัก" อันเดียว
$is_home = (strpos($current_path, 'home.php') !== false || $current_path == '/' || $current_path == '/index.php');

// แผนที่สำหรับหาชื่อหน้า กรณีที่หน้านั้นไม่ได้ประกาศตัวแปร $breadcrumbs เอาไว้
$url_map = [
    '/pages/home.php' => 'หน้าหลัก',
    '/pages/login.php' => 'เข้าสู่ระบบ',
    '/pages/register.php' => 'สมัครสมาชิก',
    '/pages/cart.php' => 'ตะกร้าสินค้า',
    '/pages/wishlist.php' => 'รายการโปรด',
    '/pages/profile.php' => 'โปรไฟล์ของฉัน',
    '/pages/used_books.php' => 'คลังหนังสือมือสอง',
    '/pages/new_books.php' => 'เลือกอ่านเล่มใหม่',
    '/pages/exchange_books.php' => 'ลานแลกเปลี่ยน',
    '/pages/mood_match.php' => 'Mood Match',
    '/pages/notifications.php' => 'การแจ้งเตือน',
    '/pages/search.php' => 'ค้นหา',
    '/pages/sell_used_book.php' => 'ลงขายหนังสือ',
    '/pages/post_exchange.php' => 'ลงประกาศแลกเปลี่ยน'
];

if ($is_home) {
    $_SESSION['breadcrumb_history'] = [
        ['label' => 'หน้าหลัก', 'url' => '/pages/home.php']
    ];
} else {
    // หาว่า URL ปัจจุบันอยู่ในประวัติไหม (ใช้แค่ path เพื่อเทียบ กรณีมี query string เปลี่ยนแปลง)
    $found_idx = -1;
    foreach ($_SESSION['breadcrumb_history'] as $idx => $crumb) {
        $crumb_path = parse_url($crumb['url'], PHP_URL_PATH);
        if ($crumb_path === $current_path) {
            $found_idx = $idx;
            break;
        }
    }

    $current_label = 'หน้าปัจจุบัน';
    
    // พยายามดึงชื่อหน้าจากตัวแปร $breadcrumbs ที่หน้านั้นๆ อาจจะประกาศไว้
    if (isset($breadcrumbs) && is_array($breadcrumbs) && !empty($breadcrumbs)) {
        $last_static = end($breadcrumbs);
        if (!empty($last_static['label'])) {
            $current_label = $last_static['label'];
        }
    } else {
        // ถ้าไม่มี ลองหาจาก url_map
        if (isset($url_map[$current_path])) {
            $current_label = $url_map[$current_path];
        } else {
            // ดึงชื่อไฟล์มาแสดงแก้ขัด
            $current_label = ucfirst(str_replace(['.php', '_'], ['', ' '], basename($current_path)));
        }
    }

    if ($found_idx !== -1) {
        // ถ้าย้อนกลับมาหน้าที่เคยเข้าแล้ว ให้ตัดประวัติหลังจากหน้านี้ทิ้ง (เหมือน back กลับมา)
        $_SESSION['breadcrumb_history'] = array_slice($_SESSION['breadcrumb_history'], 0, $found_idx + 1);
        // อัปเดต URL เพื่อให้ query string เป็นปัจจุบันที่สุด
        $_SESSION['breadcrumb_history'][$found_idx]['url'] = $current_url;
        // อัปเดต label หากมีการเปลี่ยนแปลง
        $_SESSION['breadcrumb_history'][$found_idx]['label'] = $current_label;
    } else {
        // หน้าใหม่ เอาไปต่อท้ายประวัติ
        
        // ถ้าประวัติว่างเปล่า (เกิดจากการเข้า URL ตรงๆ) ให้ใส่หน้าหลักเข้าไปก่อน
        if (empty($_SESSION['breadcrumb_history'])) {
            $_SESSION['breadcrumb_history'][] = ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'];
        }

        $_SESSION['breadcrumb_history'][] = [
            'label' => $current_label,
            'url' => $current_url
        ];

        // จำกัดความยาวประวัติไม่ให้เกิน 7 หน้า เพื่อไม่ให้รก
        if (count($_SESSION['breadcrumb_history']) > 7) {
            // เก็บหน้าแรกไว้ (หน้าหลัก)
            $first = array_shift($_SESSION['breadcrumb_history']);
            // ลบหน้ากลางๆ ที่เก่าที่สุดทิ้ง
            while(count($_SESSION['breadcrumb_history']) > 5) {
                array_shift($_SESSION['breadcrumb_history']);
            }
            // เอาหน้าแรกกลับมาใส่
            array_unshift($_SESSION['breadcrumb_history'], $first);
        }
    }
}

// นำประวัติไปแทนที่ตัวแปร $breadcrumbs เพื่อให้ header นำไปแสดงผล
$breadcrumbs = $_SESSION['breadcrumb_history'];

// ทำให้ตัวสุดท้ายไม่มีลิงก์ (คลิกไม่ได้) เพื่อบอกว่าเป็นหน้าปัจจุบัน
if (!empty($breadcrumbs)) {
    $last_key = array_key_last($breadcrumbs);
    $breadcrumbs[$last_key]['url'] = '';
}
?>
