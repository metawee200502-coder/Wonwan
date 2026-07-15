<?php
function handleIsbnLookup(): void {
    header('Content-Type: application/json; charset=utf-8');

    $isbn = preg_replace('/[^0-9Xx]/', '', $_GET['isbn'] ?? '');
    if (!$isbn) {
        echo json_encode(['found' => false, 'message' => 'กรุณากรอกเลข ISBN']);
        exit;
    }

    define('GOOGLE_BOOKS_API_KEY', 'AIzaSyCpliZ7sPMb5eAd0oW1BMRPMl3lHbmcKEI');
    $api_url    = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";
    $api_url   .= GOOGLE_BOOKS_API_KEY !== '' ? '&key=' . GOOGLE_BOOKS_API_KEY : '';
    $resp       = false;
    $debug_note = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($api_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WanawanBookLookup/1.0)',
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) $debug_note = 'curl_error: ' . curl_error($ch);
        curl_close($ch);
    }

    if ($resp === false && ini_get('allow_url_fopen')) {
        $resp = @file_get_contents($api_url, false, stream_context_create(['http' => ['timeout' => 8]]));
        if ($resp === false) {
            $err        = error_get_last();
            $debug_note = 'file_get_contents_error: ' . ($err['message'] ?? 'unknown');
        }
    }

    if ($resp === false) {
        echo json_encode([
            'found'   => false,
            'message' => 'เซิร์ฟเวอร์เชื่อมต่อ Google Books ไม่ได้',
            'debug'   => $debug_note,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $gdata = json_decode($resp, true);

    if (!empty($gdata['error'])) {
        $g_code    = $gdata['error']['code'] ?? '';
        $g_message = $gdata['error']['message'] ?? '';
        echo json_encode([
            'found'   => false,
            'message' => $g_code == 429
                ? 'โควตาการค้นหา Google Books วันนี้เต็มแล้ว'
                : 'Google Books ตอบกลับข้อผิดพลาด: ' . $g_message,
            'debug'   => "google_api_error_{$g_code}: {$g_message}",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($gdata['items'][0]['volumeInfo'])) {
        echo json_encode(['found' => false, 'message' => 'ไม่พบข้อมูลหนังสือจาก ISBN นี้ กรุณากรอกข้อมูลเอง']);
        exit;
    }

    $v        = $gdata['items'][0]['volumeInfo'];
    $lang_map = ['th' => 'ไทย', 'en' => 'อังกฤษ', 'ja' => 'ญี่ปุ่น', 'zh' => 'จีน'];

    $image_cover_url = '';
    if (!empty($v['imageLinks'])) {
        $img = $v['imageLinks']['thumbnail'] ?? $v['imageLinks']['smallThumbnail'] ?? '';
        if ($img) $image_cover_url = preg_replace('#^http://#i', 'https://', $img);
    }

    $isbn13_val = '';
    foreach ($v['industryIdentifiers'] ?? [] as $ident) {
        if (($ident['type'] ?? '') === 'ISBN_13') { $isbn13_val = $ident['identifier'] ?? ''; break; }
    }

    $category_map = [
        'comic' => 'การ์ตูน/มังงะ',         'graphic novel' => 'การ์ตูน/มังงะ',
        'psycholog' => 'จิตวิทยา',            'education' => 'การศึกษา/ตำรา',
        'study aids' => 'การศึกษา/ตำรา',      'business' => 'ธุรกิจ/การเงิน',
        'economic' => 'ธุรกิจ/การเงิน',       'computer' => 'เทคโนโลยี',
        'technology' => 'เทคโนโลยี',          'health' => 'สุขภาพ/ความงาม',
        'self-help' => 'สุขภาพ/ความงาม',      'travel' => 'ท่องเที่ยว',
        'religio' => 'ศาสนา/ปรัชญา',          'philosoph' => 'ศาสนา/ปรัชญา',
        'history' => 'ประวัติศาสตร์',          'nature' => 'สิ่งแวดล้อม',
        'fiction' => 'วรรณกรรมต่างประเทศ',
    ];
    $category_guess = '';
    if (!empty($v['categories'])) {
        $cat_haystack = mb_strtolower(implode(' / ', $v['categories']));
        foreach ($category_map as $kw => $thai) {
            if (str_contains($cat_haystack, $kw)) { $category_guess = $thai; break; }
        }
    }

    $dimensions_str = '';
    if (!empty($v['dimensions'])) {
        $w = preg_replace('/[^0-9.]/', '', $v['dimensions']['width']     ?? '');
        $h = preg_replace('/[^0-9.]/', '', $v['dimensions']['height']    ?? '');
        $t = preg_replace('/[^0-9.]/', '', $v['dimensions']['thickness'] ?? '');
        if ($w && $h) $dimensions_str = $t ? "{$w} x {$h} x {$t} ซม." : "{$w} x {$h} ซม.";
    }

    echo json_encode([
        'found'  => true,
        'source' => 'google',
        'data'   => [
            'title'          => $v['title'] ?? '',
            'author'         => !empty($v['authors']) ? implode(', ', $v['authors']) : '',
            'publisher_name' => $v['publisher'] ?? '',
            'isbn_13'        => $isbn13_val,
            'category'       => $category_guess,
            'format'         => '',
            'pages'          => $v['pageCount'] ?? '',
            'dimensions'     => $dimensions_str,
            'weight_grams'   => '',
            'language'       => $lang_map[$v['language'] ?? ''] ?? '',
            'publish_date'   => $v['publishedDate'] ?? '',
            'synopsis'       => $v['description'] ?? '',
            'image_cover'    => $image_cover_url,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}