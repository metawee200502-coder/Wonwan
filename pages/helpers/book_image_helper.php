<?php
function handleBookImages(array $img_fields, string $upload_base): array {
    $result = [];
    foreach ($img_fields as $field) {
        $result[$field] = null;
        if (!empty($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $ext  = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $name = $field . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES[$field]['tmp_name'], $upload_base . $name);
            $result[$field] = 'uploads/books/' . $name;
        } elseif (!empty($_POST[$field . '_url']) && preg_match('#^https?://#i', $_POST[$field . '_url'])) {
            $src_url  = $_POST[$field . '_url'];
            $img_data = false;
            if (function_exists('curl_init')) {
                $ch = curl_init($src_url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WanawanBookImage/1.0)',
                ]);
                $img_data = curl_exec($ch);
                curl_close($ch);
            } elseif (ini_get('allow_url_fopen')) {
                $img_data = @file_get_contents(
                    $src_url, false,
                    stream_context_create(['http' => ['timeout' => 10]])
                );
            }
            if ($img_data !== false) {
                $ext = strtolower(pathinfo(parse_url($src_url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) $ext = 'jpg';
                $name = $field . '_' . uniqid() . '.' . $ext;
                file_put_contents($upload_base . $name, $img_data);
                $result[$field] = 'uploads/books/' . $name;
            }
        }
    }
    return $result;
}