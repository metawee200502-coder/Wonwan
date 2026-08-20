<?php
function handleImportCsv(PDO $pdo, int $seller_id, string $seller_type, string $seller_name): array {
    if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'ไม่พบไฟล์ที่อัปโหลด'];
    }
    if (strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        return ['error' => 'รองรับเฉพาะไฟล์ .csv เท่านั้น'];
    }

    $handle = fopen($_FILES['excel_file']['tmp_name'], 'r');
    $header = fgetcsv($handle);
    if (!$header) { fclose($handle); return ['error'=>'ไฟล์ไม่มีหัวตาราง']; }
    $header = array_map(fn($name) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string)$name))), $header);
    $column = array_flip($header);
    foreach (['title','price'] as $required) {
        if (!isset($column[$required])) { fclose($handle); return ['error'=>"ไม่พบคอลัมน์ {$required} ในไฟล์"]; }
    }

    $imported=0; $skipped=0; $import_errors=[]; $row_num=1;
    while (($row=fgetcsv($handle)) !== false) {
        $row_num++;
        if (!array_filter($row, fn($value)=>trim((string)$value)!=='')) continue;
        $get = fn(string $name, string $default='') => isset($column[$name]) ? trim((string)($row[$column[$name]] ?? '')) : $default;
        $title=$get('title'); $price=$get('price');
        if ($title==='' || $price==='' || !is_numeric($price)) { $skipped++; $import_errors[]="แถวที่ {$row_num}: ชื่อหนังสือหรือราคาไม่ถูกต้อง"; continue; }

        $images=[]; $invalidImage=false;
        foreach (['image_cover','image_back','image_inside','image_edge'] as $field) {
            $images[$field]=$get($field);
            if ($images[$field]!=='' && !preg_match('#^https?://#i',$images[$field])) $invalidImage=true;
        }
        if ($invalidImage) { $skipped++; $import_errors[]="แถวที่ {$row_num}: ลิงก์รูปต้องขึ้นต้นด้วย http:// หรือ https://"; continue; }
        if ($images['image_cover']==='') { $skipped++; $import_errors[]="แถวที่ {$row_num}: ต้องใส่ลิงก์รูปหน้าปก"; continue; }

        $bookType=in_array($get('book_type'),['new','used'],true)?$get('book_type'):'used';
        $condition=$bookType==='new'?'new':(in_array($get('condition_level'),['good','fair','old'],true)?$get('condition_level'):'good');
        $defects=$bookType==='used' ? ($get('defects_detail')?:null) : null;
        $originalPrice=$bookType==='used' && $get('original_price')!=='' ? (float)$get('original_price') : null;
        try {
            $stmt=$pdo->prepare("INSERT INTO books (seller_id,seller_type,title,author,isbn,isbn_13,publisher_name,category,format,pages,dimensions,weight_grams,language,publish_date,synopsis,book_type,condition_level,defects_detail,price,original_price,stock_quantity,image_cover,image_back,image_inside,image_edge,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'available',NOW())");
            $stmt->execute([
                $seller_id,$seller_type,$title,$get('author')?:null,$get('isbn')?:null,$get('isbn_13')?:null,
                $seller_name?:null,$get('category')?:null,$get('format')?:null,
                $get('pages')!==''?(int)$get('pages'):null,$get('dimensions')?:null,
                $get('weight_grams')!==''?(int)$get('weight_grams'):null,$get('language')?:null,null,
                $get('synopsis')?:null,$bookType,$condition,$defects,(float)$price,
                $originalPrice,max(1,(int)($get('stock_quantity','1')?:1)),
                $images['image_cover'],$images['image_back']?:null,$images['image_inside']?:null,$images['image_edge']?:null
            ]);
            $imported++;
        } catch (PDOException $e) { $skipped++; $import_errors[]="แถวที่ {$row_num}: ".$e->getMessage(); }
    }
    fclose($handle);
    $msg="นำเข้าสำเร็จ {$imported} รายการ".($skipped?" (ข้าม {$skipped} แถว)\n".implode("\n",array_slice($import_errors,0,3)):"");
    if ($skipped && $imported===0) return ['error'=>$msg];
    return ['success'=>$msg];
}
