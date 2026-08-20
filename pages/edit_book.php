<?php
session_start();
require_once '../config/config.php';

// ==================== ตรวจสอบ Login & สิทธิ์ร้านค้า ====================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

$seller_id   = null;
$seller_type = null;
$seller_name = '';

if ($role === 'publisher_admin') {
    $stmt = $pdo->prepare('
        SELECT ps.publisher_id, p.company_name
        FROM publisher_staff ps
        JOIN publishers p ON ps.publisher_id = p.publisher_id
        WHERE ps.user_id = ? LIMIT 1
    ');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if ($row) {
        $seller_id   = $row['publisher_id'];
        $seller_type = 'publisher';
        $seller_name = $row['company_name'];
    }
} elseif ($role === 'shop_admin') {
    $stmt = $pdo->prepare('
        SELECT ss.shop_id, s.shop_name
        FROM shop_staff ss
        JOIN shops s ON ss.shop_id = s.shop_id
        WHERE ss.user_id = ? LIMIT 1
    ');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if ($row) {
        $seller_id   = $row['shop_id'];
        $seller_type = 'shop';
        $seller_name = $row['shop_name'];
    }
}

if (!$seller_id && $role === 'reader') {
    $seller_id=(int)$user_id;
    $seller_type='user';
    $seller_name=$_SESSION['display_name']??'ผู้ขายทั่วไป';
}

if (!$seller_id) {
    header('Location: dashboard.php');
    exit;
}

// ==================== ดึงข้อมูลหนังสือที่จะแก้ไข ====================
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("
    SELECT * FROM books 
    WHERE book_id = ? AND seller_id = ? AND seller_type = ?
");
$stmt->execute([$book_id, $seller_id, $seller_type]);
$book = $stmt->fetch();

if (!$book) {
    // ถ้าไม่พบสินค้า หรือไม่ใช่เจ้าของ ให้เด้งกลับหน้าคลังสินค้า
    header('Location: inventory.php');
    exit;
}

function editBookImageUrl(?string $value): string {
    if (!$value) return '';
    $value = str_replace('\\', '/', trim($value));
    if (preg_match('#^https?://#i', $value)) return $value;
    $value = ltrim($value, '/');
    if (str_starts_with($value, 'pages/uploads/')) return '/'.$value;
    if (str_starts_with($value, 'uploads/')) {
        if (is_file(__DIR__.'/../'.$value)) return '/'.$value;
        return '/pages/'.$value;
    }
    if (str_starts_with($value, 'assets/')) return '/'.$value;
    return '/assets/image/books/'.$value;
}

function editBookLocalImage(?string $value): ?string {
    if (!$value || preg_match('#^https?://#i', $value)) return null;
    $value = ltrim(str_replace('\\', '/', $value), '/');
    $candidates = str_starts_with($value, 'pages/uploads/')
        ? [__DIR__.'/'.substr($value, 6)]
        : [__DIR__.'/../'.$value, __DIR__.'/'.$value];
    foreach ($candidates as $path) {
        $real = realpath($path);
        $root = realpath(__DIR__.'/../uploads');
        $pageRoot = realpath(__DIR__.'/uploads');
        if ($real && (($root && str_starts_with($real, $root.DIRECTORY_SEPARATOR)) || ($pageRoot && str_starts_with($real, $pageRoot.DIRECTORY_SEPARATOR)))) return $real;
    }
    return null;
}

// โพสต์ของบัญชีทั่วไปใช้หน้าฟอร์มฝั่งหน้าร้าน ไม่แสดง Sidebar ระบบร้านใหญ่
if($seller_type==='user'){
    header('Location: /pages/edit_used_book.php?id='.$book_id);
    exit;
}

// ==================== ประมวลผลเมื่อกดบันทึกข้อมูล (POST) ====================
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title           = $_POST['title'] ?? '';
    $author          = $_POST['author'] ?? '';
    $category        = $_POST['category'] ?? '';
    $price           = floatval($_POST['price'] ?? 0);
    $original_price  = floatval($_POST['original_price'] ?? 0);
    $stock_quantity  = intval($_POST['stock_quantity'] ?? 0);
    $status          = $_POST['status'] ?? 'available';
    $condition_level = $_POST['condition_level'] ?? null;
    $book_type       = $book['book_type']; // ล็อกประเภทสินค้าไว้ไม่ให้เปลี่ยนตอนแก้ไข

    $newFiles = [];
    $imagesSaved = false;
    try {
        // จัดการอัปโหลดรูปภาพปกใหม่ (ถ้ามีการเลือกรูปใหม่)
        $imageFields = ['image_cover','image_back','image_inside','image_edge'];
        $images = [];
        $oldFilesToDelete = [];
        $uploadDir = __DIR__.'/uploads/books/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์รูปสินค้าได้');
        }
        $allowedMimes = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        foreach ($imageFields as $field) {
            $images[$field] = $book[$field] ?? null;
            $remove = !empty($_POST['remove_'.$field]);
            $file = $_FILES[$field] ?? null;
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
                    throw new RuntimeException('รูปภาพต้องมีขนาดไม่เกิน 5 MB');
                }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                $ext = $allowedMimes[$mime] ?? null;
                if (!$ext) throw new RuntimeException('รองรับรูปภาพเฉพาะ JPG, PNG และ WEBP');
                $name = $field.'_'.bin2hex(random_bytes(8)).'.'.$ext;
                $target = $uploadDir.$name;
                if (!move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('อัปโหลดรูปภาพไม่สำเร็จ');
                $newFiles[] = $target;
                if ($old = editBookLocalImage($images[$field])) $oldFilesToDelete[] = $old;
                $images[$field] = 'uploads/books/'.$name;
            } elseif ($remove) {
                if ($old = editBookLocalImage($images[$field])) $oldFilesToDelete[] = $old;
                $images[$field] = null;
            }
        }

        // อัปเดตข้อมูลลงตาราง books
        $updateStmt = $pdo->prepare("
            UPDATE books SET 
                title = ?, author = ?, category = ?, price = ?, 
                original_price = ?, stock_quantity = ?, status = ?, 
                condition_level = ?, image_cover = ?, image_back = ?, image_inside = ?, image_edge = ?
            WHERE book_id = ? AND seller_id = ? AND seller_type = ?
        ");
        $updateStmt->execute([
            $title, $author, $category, $price, 
            $original_price, $stock_quantity, $status, 
            $condition_level, $images['image_cover'], $images['image_back'], $images['image_inside'], $images['image_edge'],
            $book_id, $seller_id, $seller_type
        ]);
        $imagesSaved = true;
        foreach (array_unique($oldFilesToDelete) as $oldFile) @unlink($oldFile);

        $success_msg = 'อัปเดตข้อมูลหนังสือเรียบร้อยแล้ว!';
        
        // ดึงข้อมูลใหม่มาแสดงในฟอร์มหลังบันทึกสำเร็จ
        $stmt->execute([$book_id, $seller_id, $seller_type]);
        $book = $stmt->fetch();
        
    } catch (Throwable $e) {
        if (!$imagesSaved) foreach ($newFiles as $newFile) if (is_file($newFile)) @unlink($newFile);
        $error_msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลหนังสือ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-family); background: var(--bg-color); color: var(--text-dark); display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 220px; min-height: 100vh; background: var(--card-bg);
            display: flex; flex-direction: column; padding: 24px 0;
            border-right: 1px solid var(--border-color); position: fixed; top: 0; left: 0; z-index: 100;
        }
        .main-content { margin-left: 220px; flex: 1; display: flex; flex-direction: column; }
        .page-body { padding: 28px; flex: 1; max-width: 900px; }
        
        .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
        .btn-back { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; color: var(--text-muted); font-size: var(--text-sm); font-weight: 500; transition: color 0.2s; }
        .btn-back:hover { color: var(--primary-color); }
        .page-title { font-size: 22px; font-weight: 700; color: var(--secondary-color); }

        .form-card { background: var(--card-bg); border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); margin-bottom: 20px; }
        .form-section-title { font-size: 16px; font-weight: 700; color: var(--secondary-color); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px; }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: var(--text-sm); font-weight: 600; color: var(--text-dark); margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color); font-family: var(--font-family); font-size: var(--text-sm); outline: none; transition: border-color 0.2s; background: var(--bg-color); }
        .form-control:focus { border-color: var(--primary-color); }
        
        .price-summary-box { background: var(--accent-pink-light); border: 1px dashed var(--primary-color); padding: 12px 16px; border-radius: var(--radius-md); margin-top: 10px; font-size: var(--text-sm); color: var(--secondary-color); font-weight: 600; display: none; }
        
        .btn-submit-group { display: flex; gap: 12px; margin-top: 24px; }
        .btn-save { background: var(--primary-color); color: #fff; border: none; border-radius: var(--radius-md); padding: 12px 24px; font-family: var(--font-family); font-size: var(--text-sm); font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-save:hover { background: var(--primary-hover); }
        .btn-cancel { background: #fff; color: var(--text-muted); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 24px; font-family: var(--font-family); font-size: var(--text-sm); font-weight: 500; text-decoration: none; text-align: center; transition: all 0.2s; }
        .btn-cancel:hover { background: var(--bg-color); color: var(--text-dark); }
        
        .badge-type { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .type-new { background: #E8F5E9; color: var(--accent-green); }
        .type-used { background: var(--accent-pink-light); color: var(--primary-color); }
        .current-cover-img { width: 100px; height: 140px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-bottom: 10px; display: block; }
        .image-edit-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .image-edit-item { padding:16px; border:1px solid var(--border-color); border-radius:var(--radius-md); background:var(--bg-color); }
        .image-edit-item .current-cover-img { width:120px; height:160px; }
        .remove-image { display:flex; align-items:center; gap:7px; margin:10px 0; color:var(--accent-red); font-size:var(--text-sm); cursor:pointer; }
        .remove-image input { accent-color:var(--accent-red); }
        @media(max-width:650px){.image-edit-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>

<?php
$active_menu = 'inventory';
// นำเข้า Sidebar ของระบบ
include __DIR__ . '/../components/sidebar.php';
?>

<div class="main-content">
    <?php 
    $show_search = false;
    // นำเข้า Topbar ของระบบ
    include __DIR__ . '/../components/topbar.php'; 
    ?>

    <div class="page-body">
        <div class="page-header">
            <a href="inventory.php" class="btn-back">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                กลับไปคลังสินค้า
            </a>
            <h1 class="page-title">แก้ไขรายละเอียดหนังสือ</h1>
            <span class="badge-type <?= $book['book_type'] === 'new' ? 'type-new' : 'type-used' ?>">
                <?= $book['book_type'] === 'new' ? 'สินค้ามือหนึ่ง' : 'สินค้ามือสอง' ?>
            </span>
        </div>

        <?php if ($success_msg): ?>
        <script>
            Swal.fire({
                icon: 'success', 
                title: 'สำเร็จ', 
                text: '<?= addslashes($success_msg) ?>', 
                confirmButtonColor: '#C97B8B'
            });
        </script>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <script>
            Swal.fire({
                icon: 'error', 
                title: 'เกิดข้อผิดพลาด', 
                text: '<?= addslashes($error_msg) ?>',
                confirmButtonColor: '#C97B8B'
            });
        </script>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="editBookForm">
            
            <div class="form-card">
                <div class="form-section-title">ข้อมูลทั่วไปของหนังสือ</div>
                <?php 
                // ดึงไฟล์ Component รายละเอียดมาใช้งาน
                // เพื่อให้ Component ฉลาด วนวรรณจะแนะนำให้ใส่ค่า <?= $book['...'] ?> ไว้ในไฟล์ Component นั้นๆ ด้วยค่ะ
                include __DIR__ . '/components/form_details.php'; 
                ?>
            </div>

            <div class="form-card">
                <div class="form-section-title">ราคา คลังสินค้า และสภาพสินค้า</div>
                
                <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">ราคาขายปัจจุบัน (บาท) <span style="color:var(--primary-color)">*</span></label>
                        <input type="number" step="0.01" name="price" id="inputPrice" class="form-control" required value="<?= htmlspecialchars($book['price']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">จำนวนในคลังสินค้า (เล่ม) <span style="color:var(--primary-color)">*</span></label>
                        <input type="number" name="stock_quantity" class="form-control" required value="<?= htmlspecialchars($book['stock_quantity']) ?>">
                    </div>
                </div>

                <?php if ($book['book_type'] === 'used'): ?>
                    <div id="usedBookSection" style="margin-top: 16px; padding-top: 16px; border-top: 1px dashed var(--border-color);">
                        <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div class="form-group">
                                <label class="form-label">ราคาปกเดิม / ราคาเต็ม (บาท)</label>
                                <input type="number" step="0.01" name="original_price" id="inputOriginalPrice" class="form-control" value="<?= htmlspecialchars($book['original_price'] ?? 0) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">สภาพหนังสือมือสอง</label>
                                <select name="condition_level" class="form-control">
                                    <option value="like_new" <?= $book['condition_level'] === 'like_new' ? 'selected' : '' ?>>เหมือนใหม่ (95-99%)</option>
                                    <option value="very_good" <?= $book['condition_level'] === 'very_good' ? 'selected' : '' ?>>สภาพดีมาก (85-94%)</option>
                                    <option value="good" <?= $book['condition_level'] === 'good' ? 'selected' : '' ?>>สภาพดี (70-84%)</option>
                                    <option value="fair" <?= $book['condition_level'] === 'fair' ? 'selected' : '' ?>>สภาพพอใช้ (50-69%)</option>
                                </select>
                            </div>
                        </div>

                        <div class="price-summary-box" id="priceSummaryBox"></div>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-top: 16px;">
                    <label class="form-label">สถานะการแสดงผลในระบบ</label>
                    <select name="status" class="form-control">
                        <option value="available" <?= $book['status'] === 'available' ? 'selected' : '' ?>>วางขายอยู่</option>
                        <option value="hidden" <?= $book['status'] === 'hidden' ? 'selected' : '' ?>>ซ่อนไว้ชั่วคราว</option>
                        <option value="draft" <?= $book['status'] === 'draft' ? 'selected' : '' ?>>บันทึกเป็นร่าง</option>
                    </select>
                </div>
            </div>

            <div class="form-card">
                <div class="form-section-title">รูปภาพหน้าปกสินค้า</div>
                <div class="form-group">
                    <label class="form-label">รูปภาพหน้าปกปัจจุบัน</label>
                    <div class="image-edit-grid">
                    <?php foreach (['image_cover'=>'ภาพปก','image_back'=>'ภาพด้านหลัง','image_inside'=>'ภาพด้านใน','image_edge'=>'ภาพสันหนังสือ'] as $imageField=>$imageLabel): ?>
                    <div class="image-edit-item">
                    <label class="form-label"><?= htmlspecialchars($imageLabel) ?></label>
                    <?php if (!empty($book[$imageField])): ?>
                        <img src="<?= htmlspecialchars(editBookImageUrl($book[$imageField])) ?>" class="current-cover-img" alt="<?= htmlspecialchars($imageLabel) ?>" onerror="this.onerror=null;this.src='/assets/image/minilogo.png'">
                    <?php else: ?>
                        <div style="font-size:var(--text-xs); color:var(--text-muted); margin-bottom: 10px;">ยังไม่มีการอัปโหลดรูปภาพปก</div>
                    <?php endif; ?>
                    
                    <label class="form-label" style="margin-top: 12px;">อัปโหลดรูปภาพปกใหม่เพื่อเปลี่ยน</label>
                    <input type="file" name="<?= $imageField ?>" class="form-control image-input" accept="image/jpeg,image/png,image/webp">
                    <?php if (!empty($book[$imageField])): ?>
                    <label class="remove-image"><input type="checkbox" name="remove_<?= $imageField ?>" value="1"> ลบภาพเดิม</label>
                    <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="btn-submit-group">
                <button type="submit" class="btn-save">บันทึกการแก้ไขข้อมูล</button>
                <a href="inventory.php" class="btn-cancel">ยกเลิก</a>
            </div>

        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const inputPrice = document.getElementById('inputPrice');
    const inputOriginalPrice = document.getElementById('inputOriginalPrice');
    const summaryBox = document.getElementById('priceSummaryBox');

    document.querySelectorAll('.image-input').forEach(input => {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) return;
            const item = input.closest('.image-edit-item');
            let preview = item.querySelector('.current-cover-img');
            if (!preview) {
                preview = document.createElement('img');
                preview.className = 'current-cover-img';
                input.parentNode.insertBefore(preview, input);
            }
            preview.src = URL.createObjectURL(file);
            const remove = item.querySelector('.remove-image input');
            if (remove) remove.checked = false;
        });
    });

    function calculateDiscount() {
        if (!inputOriginalPrice) return; // หากเป็นสินค้ามือหนึ่งจะข้ามฟังก์ชันนี้ไปลยค่ะ

        const salePrice = parseFloat(inputPrice.value) || 0;
        const originalPrice = parseFloat(inputOriginalPrice.value) || 0;

        if (originalPrice > 0 && salePrice < originalPrice) {
            const discountAmt = originalPrice - salePrice;
            const discountPercent = Math.round((discountAmt / originalPrice) * 100);

            summaryBox.style.display = 'block';
            summaryBox.innerHTML = `
                <svg style="width:16px; height:16px; vertical-align:middle; margin-right:4px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5a2 2 0 10-2 2h2zm0 0h4m-4 0H8m12 3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                สินค้านี้ลดราคาลงไป ฿${discountAmt.toFixed(2)} บาท (ประหยัดคุ้มค่าถึง ${discountPercent}%) จากราคาปกเดิม!
            `;
        } else {
            summaryBox.style.display = 'none';
        }
    }

    // ทำงานทันทีเมื่อโหลดหน้าเว็บเสร็จ (เพื่อคำนวณข้อมูลเดิมที่มีใน DB)
    calculateDiscount();

    // ทำงานทุกครั้งเมื่อมีการพิมพ์อัปเดตตัวเลขราคาล่าสุด
    if (inputPrice) inputPrice.addEventListener('input', calculateDiscount);
    if (inputOriginalPrice) inputOriginalPrice.addEventListener('input', calculateDiscount);
});
</script>
</body>
</html>
