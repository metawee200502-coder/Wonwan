<?php
/**
 * ISBN Lookup Handler  v2.0
 * Primary  : Open Library API  — ไม่มี quota จำกัด ค้นได้ฟรีตลอด
 * Fallback : Google Books API  — ใช้เมื่อ Open Library ไม่พบข้อมูล
 */
function handleIsbnLookup(): void {
    header('Content-Type: application/json; charset=utf-8');

    $isbn = preg_replace('/[^0-9Xx]/', '', $_GET['isbn'] ?? '');
    if (!$isbn) {
        echo json_encode(['found' => false, 'message' => 'กรุณากรอกเลข ISBN'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 1. ลอง Open Library ก่อน (ไม่มี quota จำกัด)
    $result = isbnFromOpenLibrary($isbn);
    if ($result !== null) {
        echo json_encode(['found' => true, 'source' => 'openlibrary', 'data' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Fallback: Google Books
    $result = isbnFromGoogleBooks($isbn);
    if ($result !== null) {
        echo json_encode(['found' => true, 'source' => 'google', 'data' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ไม่พบจากทั้งสองแหล่ง
    echo json_encode([
        'found'   => false,
        'message' => 'ไม่พบข้อมูลหนังสือจาก ISBN นี้ กรุณากรอกข้อมูลเอง',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Shared HTTP Helper ────────────────────────────────────────────────────────

function isbnHttpGet(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'WanawanBookLookup/2.0 (contact@wonwan.co.th)',
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp !== false && $err === '') return $resp;
    }
    if (ini_get('allow_url_fopen')) {
        $ctx  = stream_context_create(['http' => [
            'timeout'    => 10,
            'user_agent' => 'WanawanBookLookup/2.0',
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp !== false) return $resp;
    }
    return null;
}

// ─── Category mapping (shared) ────────────────────────────────────────────────

function isbnCategoryThai(array $subjects): string {
    if (empty($subjects)) return '';
    $map = [
        'comic'       => 'การ์ตูน/มังงะ',      'manga'       => 'การ์ตูน/มังงะ',
        'graphic nov' => 'การ์ตูน/มังงะ',
        'psycholog'   => 'จิตวิทยา',
        'education'   => 'การศึกษา/ตำรา',       'study'       => 'การศึกษา/ตำรา',
        'textbook'    => 'การศึกษา/ตำรา',
        'business'    => 'ธุรกิจ/การเงิน',       'economics'   => 'ธุรกิจ/การเงิน',
        'finance'     => 'ธุรกิจ/การเงิน',
        'computer'    => 'เทคโนโลยี',            'technology'  => 'เทคโนโลยี',
        'programming' => 'เทคโนโลยี',
        'health'      => 'สุขภาพ/ความงาม',       'self-help'   => 'สุขภาพ/ความงาม',
        'cooking'     => 'สุขภาพ/ความงาม',
        'travel'      => 'ท่องเที่ยว',
        'religion'    => 'ศาสนา/ปรัชญา',         'philosophy'  => 'ศาสนา/ปรัชญา',
        'history'     => 'ประวัติศาสตร์',
        'nature'      => 'สิ่งแวดล้อม',           'environment' => 'สิ่งแวดล้อม',
        'fiction'     => 'วรรณกรรมต่างประเทศ',   'novel'       => 'วรรณกรรมต่างประเทศ',
        'thai'        => 'วรรณกรรมไทย',
    ];
    // รับ subjects เป็น array ของ string หรือ ['name'=>...]
    $names = array_map(fn($s) => is_array($s) ? ($s['name'] ?? '') : (string)$s, $subjects);
    $haystack = mb_strtolower(implode(' | ', $names));
    foreach ($map as $kw => $thai) {
        if (str_contains($haystack, $kw)) return $thai;
    }
    return '';
}

// ─── Open Library (Primary) ───────────────────────────────────────────────────

function isbnFromOpenLibrary(string $isbn): ?array {
    $url  = "https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data";
    $resp = isbnHttpGet($url);
    if ($resp === null) return null;

    $data = json_decode($resp, true);
    $key  = "ISBN:{$isbn}";
    if (empty($data[$key])) return null;

    $v = $data[$key];

    // ISBN-13
    $isbn13 = '';
    foreach ($v['identifiers']['isbn_13'] ?? [] as $id) { $isbn13 = $id; break; }
    if (!$isbn13) foreach ($v['identifiers']['isbn_10'] ?? [] as $id) { $isbn13 = $id; break; }

    // Cover image (Open Library Covers CDN — ไม่ต้อง API key)
    $cover_url = $v['cover']['large'] ?? $v['cover']['medium'] ?? $v['cover']['small'] ?? '';
    if (!$cover_url) {
        $cover_id = $isbn13 ?: $isbn;
        $cover_url = "https://covers.openlibrary.org/b/isbn/{$cover_id}-L.jpg";
    }

    // Authors
    $authors = array_filter(array_map(fn($a) => $a['name'] ?? '', $v['authors'] ?? []));
    $author_str = implode(', ', $authors);

    // Publisher
    $publisher = '';
    foreach ($v['publishers'] ?? [] as $p) { $publisher = $p['name'] ?? ''; break; }

    // Subjects → category
    $subjects = $v['subjects'] ?? [];
    $category = isbnCategoryThai($subjects);

    // Language
    $lang_map = [
        'tha' => 'ไทย',    'th'  => 'ไทย',
        'eng' => 'อังกฤษ', 'en'  => 'อังกฤษ',
        'jpn' => 'ญี่ปุ่น', 'ja'  => 'ญี่ปุ่น',
        'chi' => 'จีน',    'zh'  => 'จีน',
    ];
    $language = '';
    foreach ($v['languages'] ?? [] as $l) {
        $lkey = basename(trim($l['key'] ?? '', '/'));
        if (isset($lang_map[$lkey])) { $language = $lang_map[$lkey]; break; }
    }

    return [
        'title'          => $v['title'] ?? '',
        'author'         => $author_str,
        'publisher_name' => $publisher,
        'isbn_13'        => $isbn13,
        'category'       => $category,
        'format'         => '',
        'pages'          => $v['number_of_pages'] ?? '',
        'dimensions'     => '',
        'weight_grams'   => '',
        'language'       => $language,
        'publish_date'   => $v['publish_date'] ?? '',
        'synopsis'       => '',   // Open Library data API ไม่ให้ synopsis
        'image_cover'    => $cover_url,
    ];
}

// ─── Google Books (Fallback) ───────────────────────────────────────────────────

function isbnFromGoogleBooks(string $isbn): ?array {
    $api_key  = 'AIzaSyCpliZ7sPMb5eAd0oW1BMRPMl3lHbmcKEI';
    $base_url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";

    // ลอง with key ก่อน
    $resp  = isbnHttpGet("{$base_url}&key={$api_key}");
    $gdata = $resp ? json_decode($resp, true) : [];

    // ถ้า key หมด quota → ลอง no-key
    if (!empty($gdata['error']) && ($gdata['error']['code'] ?? 0) == 429) {
        $resp  = isbnHttpGet($base_url);
        $gdata = $resp ? json_decode($resp, true) : [];
    }

    if (empty($gdata['items'][0]['volumeInfo'])) return null;

    $v        = $gdata['items'][0]['volumeInfo'];
    $lang_map = ['th' => 'ไทย', 'en' => 'อังกฤษ', 'ja' => 'ญี่ปุ่น', 'zh' => 'จีน'];

    // Cover
    $cover_url = '';
    if (!empty($v['imageLinks'])) {
        $img = $v['imageLinks']['thumbnail'] ?? $v['imageLinks']['smallThumbnail'] ?? '';
        if ($img) $cover_url = preg_replace('#^http://#i', 'https://', $img);
    }

    // ISBN-13
    $isbn13 = '';
    foreach ($v['industryIdentifiers'] ?? [] as $id) {
        if (($id['type'] ?? '') === 'ISBN_13') { $isbn13 = $id['identifier'] ?? ''; break; }
    }

    // Category
    $subjects = array_map(fn($c) => ['name' => $c], $v['categories'] ?? []);
    $category = isbnCategoryThai($subjects);

    // Dimensions
    $dimensions = '';
    if (!empty($v['dimensions'])) {
        $w = preg_replace('/[^0-9.]/', '', $v['dimensions']['width']     ?? '');
        $h = preg_replace('/[^0-9.]/', '', $v['dimensions']['height']    ?? '');
        $t = preg_replace('/[^0-9.]/', '', $v['dimensions']['thickness'] ?? '');
        if ($w && $h) $dimensions = $t ? "{$w} x {$h} x {$t} ซม." : "{$w} x {$h} ซม.";
    }

    return [
        'title'          => $v['title'] ?? '',
        'author'         => !empty($v['authors']) ? implode(', ', $v['authors']) : '',
        'publisher_name' => $v['publisher'] ?? '',
        'isbn_13'        => $isbn13,
        'category'       => $category,
        'format'         => '',
        'pages'          => $v['pageCount'] ?? '',
        'dimensions'     => $dimensions,
        'weight_grams'   => '',
        'language'       => $lang_map[$v['language'] ?? ''] ?? '',
        'publish_date'   => $v['publishedDate'] ?? '',
        'synopsis'       => $v['description'] ?? '',
        'image_cover'    => $cover_url,
    ];
}