<?php
session_start();
require_once '../config/config.php';

// โหลด helpers และ handlers
require_once __DIR__ . '/helpers/book_db_helper.php';
require_once __DIR__ . '/helpers/book_image_helper.php';
require_once __DIR__ . '/handlers/handle_save_book.php';
require_once __DIR__ . '/handlers/handle_import_csv.php';
require_once __DIR__ . '/handlers/handle_isbn_lookup.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

$seller_id = null; $seller_type = null; $seller_name = '';

if ($role === 'publisher_admin') {
    $stmt = $pdo->prepare('
        SELECT ps.publisher_id, p.company_name
        FROM publisher_staff ps
        JOIN publishers p ON ps.publisher_id = p.publisher_id
        WHERE ps.user_id = ? LIMIT 1
    ');
    $stmt->execute([$user_id]);
    if ($row = $stmt->fetch()) {
        $seller_id   = $row['publisher_id'];
        $seller_type = 'publisher';
        $seller_name = $row['company_name'];
    }
} else {
    $stmt = $pdo->prepare('
        SELECT ss.shop_id, s.shop_name
        FROM shop_staff ss
        JOIN shops s ON ss.shop_id = s.shop_id
        WHERE ss.user_id = ? LIMIT 1
    ');
    $stmt->execute([$user_id]);
    if ($row = $stmt->fetch()) {
        $seller_id   = $row['shop_id'];
        $seller_type = 'shop';
        $seller_name = $row['shop_name'];
    }
}
if (!$seller_id) { header('Location: dashboard.php'); exit; }

// AJAX ISBN Lookup
if (isset($_GET['ajax']) && $_GET['ajax'] === 'lookup_isbn') {
    handleIsbnLookup();
}

$categories = [
    'วรรณกรรมไทย','วรรณกรรมต่างประเทศ','การ์ตูน/มังงะ','จิตวิทยา',
    'การศึกษา/ตำรา','ธุรกิจ/การเงิน','เทคโนโลยี','สุขภาพ/ความงาม',
    'ท่องเที่ยว','ศาสนา/ปรัชญา','ประวัติศาสตร์','สิ่งแวดล้อม','อื่นๆ'
];

$success_msg    = '';
$error_msg      = '';
$current_action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $current_action = $_POST['action'];

    if (in_array($current_action, ['save_book','save_draft'])) {
        $status = $current_action === 'save_draft' ? 'draft' : 'available';
        $result = handleSaveBook($pdo, $seller_id, $seller_type, $seller_name, $status);
    } elseif ($current_action === 'import_excel') {
        $result = handleImportCsv($pdo, $seller_id, $seller_type);
    }

    $success_msg = $result['success'] ?? '';
    $error_msg   = $result['error']   ?? '';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มหนังสือใหม่ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-family); background: var(--bg-color); color: var(--text-dark); display: flex; min-height: 100vh; }
        .sidebar { width: 220px; min-height: 100vh; background: var(--card-bg); display: flex; flex-direction: column; padding: 24px 0; border-right: 1px solid var(--border-color); position: fixed; top: 0; left: 0; z-index: 100; }
        .sidebar-logo { display: flex; align-items: center; gap: 10px; padding: 0 20px 28px; border-bottom: 1px solid var(--border-color); }
        .sidebar-logo img { width: 48px; height: 48px; object-fit: contain; }
        .sidebar-logo-text { font-size: 20px; font-weight: 700; color: var(--secondary-color); line-height: 1.1; }
        .sidebar-logo-sub { font-size: 10px; color: var(--text-muted); }
        .sidebar-nav { flex: 1; padding: 20px 12px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: var(--radius-md); color: var(--text-muted); font-size: var(--text-sm); font-weight: 500; text-decoration: none; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { background: var(--accent-pink-light); color: var(--primary-color); }
        .nav-item.active { font-weight: 600; }
        .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
        .sidebar-profile { padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
        .profile-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--accent-pink-light); display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-weight: 700; font-size: 16px; overflow: hidden; }
        .profile-name { font-size: var(--text-sm); font-weight: 600; color: var(--text-dark); }
        .profile-role { font-size: var(--text-xs); color: var(--primary-color); }
        .profile-seller { font-size: 11px; font-weight: 700; color: var(--secondary-color); margin-bottom: 1px; }
        .main-content { margin-left: 220px; flex: 1; }
        .topbar { background: var(--card-bg); border-bottom: 1px solid var(--border-color); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; gap: 10px; position: sticky; top: 0; z-index: 50; }
        .breadcrumb { font-size: var(--text-xs); color: var(--text-muted); }
        .breadcrumb a { color: var(--primary-color); text-decoration: none; }
        .topbar-whoami { display: flex; align-items: center; gap: 6px; font-size: var(--text-xs); white-space: nowrap; }
        .topbar-seller { font-weight: 700; color: var(--primary-color); }
        .topbar-sep { color: var(--border-color); }
        .topbar-admin { color: var(--text-muted); }
        .page-body { padding: 28px; max-width: 1100px; }
        .page-header { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 28px; }
        .page-header-icon { width: 44px; height: 44px; background: var(--secondary-color); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; }
        .page-title { font-size: 22px; font-weight: 700; color: var(--secondary-color); }
        .page-subtitle { font-size: var(--text-sm); color: var(--text-muted); margin-top: 2px; }
        .import-bar { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 16px 20px; margin-bottom: 22px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .import-bar-label { font-size: var(--text-sm); font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 6px; }
        .import-bar-sub { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }
        .import-file-btn { padding: 8px 16px; border-radius: var(--radius-md); border: 1px dashed var(--primary-color); background: var(--accent-pink-light); color: var(--primary-color); font-family: var(--font-family); font-size: var(--text-sm); cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
        .btn-import-submit { padding: 8px 18px; border-radius: var(--radius-md); border: none; background: var(--secondary-color); color: #fff; font-family: var(--font-family); font-size: var(--text-sm); font-weight: 600; cursor: pointer; transition: opacity 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-import-submit:hover { opacity: 0.85; }
        .template-link { font-size: var(--text-xs); color: var(--primary-color); margin-left: auto; display: inline-flex; align-items: center; gap: 5px; }
        .form-layout { display: grid; grid-template-columns: 1fr 260px; gap: 22px; }
        @media (max-width: 900px) { .form-layout { grid-template-columns: 1fr; } }
        .section-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 22px; margin-bottom: 18px; }
        .section-title { display: flex; align-items: center; gap: 8px; font-size: var(--text-md); font-weight: 600; color: var(--text-dark); margin-bottom: 18px; }
        .section-title svg { color: var(--primary-color); }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: var(--text-sm); font-weight: 500; color: var(--text-dark); }
        .form-group label span { color: var(--accent-red); }
        .form-control { padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: var(--font-family); font-size: var(--text-sm); color: var(--text-dark); background: var(--bg-color); outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--secondary-color); }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .isbn-row { display: flex; gap: 8px; }
        .isbn-row .form-control { flex: 1; }
        .btn-lookup { padding: 10px 14px; border-radius: var(--radius-md); border: none; background: var(--primary-color); color: #fff; font-family: var(--font-family); font-size: var(--text-sm); cursor: pointer; font-weight: 500; white-space: nowrap; transition: background 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-lookup:hover { background: var(--primary-hover); }
        .condition-group { display: flex; gap: 8px; flex-wrap: wrap; }
        .condition-btn { padding: 8px 16px; border-radius: 20px; border: 1.5px solid var(--border-color); background: var(--card-bg); font-family: var(--font-family); font-size: var(--text-sm); cursor: pointer; color: var(--text-muted); transition: all 0.2s; }
        .condition-btn.active { border-color: var(--primary-color); background: var(--accent-pink-light); color: var(--primary-color); font-weight: 600; }
        .image-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; }
        .image-slot { border: 2px dashed var(--border-color); border-radius: var(--radius-md); aspect-ratio: 3/4; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); font-size: var(--text-xs); text-align: center; gap: 6px; position: relative; overflow: hidden; background: var(--bg-color); transition: border-color 0.2s; }
        .image-slot:hover { border-color: var(--primary-color); background: var(--accent-pink-light); }
        .image-slot input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .image-slot img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .image-slot-label { font-size: var(--text-xs); color: var(--text-muted); margin-top: 4px; }
        .image-required-note { font-size: var(--text-xs); color: var(--text-muted); margin-top: 8px; }
        .shipping-option { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1.5px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 8px; cursor: pointer; transition: border-color 0.2s; }
        .shipping-option:has(input:checked) { border-color: var(--primary-color); background: var(--accent-pink-light); }
        .shipping-option input { accent-color: var(--primary-color); width: 16px; height: 16px; }
        .shipping-label { font-size: var(--text-sm); font-weight: 500; color: var(--text-dark); }
        .shipping-sub { font-size: var(--text-xs); color: var(--text-muted); }
        .right-panel { position: sticky; top: 70px; }
        .tip-card { background: var(--accent-pink-light); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 18px; margin-bottom: 16px; }
        .tip-title { font-size: var(--text-sm); font-weight: 600; color: var(--secondary-color); margin-bottom: 10px; }
        .tip-item { display: flex; align-items: flex-start; gap: 8px; font-size: var(--text-xs); color: var(--text-dark); margin-bottom: 7px; }
        .tip-item svg { color: var(--primary-color); flex-shrink: 0; margin-top: 1px; }
        .btn-submit { width: 100%; padding: 12px; border-radius: var(--radius-md); border: none; background: var(--primary-color); color: #fff; font-family: var(--font-family); font-size: var(--text-md); font-weight: 600; cursor: pointer; transition: background 0.2s; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover { background: var(--primary-hover); }
        .btn-draft { width: 100%; padding: 11px; border-radius: var(--radius-md); border: 1.5px solid var(--border-color); background: var(--card-bg); font-family: var(--font-family); font-size: var(--text-md); color: var(--text-dark); cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-draft:hover { background: var(--bg-color); }
        .book-type-toggle { display: flex; gap: 0; border: 1.5px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 18px; }
        .book-type-btn { flex: 1; padding: 9px; text-align: center; font-family: var(--font-family); font-size: var(--text-sm); font-weight: 500; cursor: pointer; border: none; background: var(--card-bg); color: var(--text-muted); transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 7px; }
        .book-type-btn.active { background: var(--secondary-color); color: #fff; }
        .book-type-btn.active svg { color: #fff; }
        .book-type-btn svg { color: var(--text-muted); transition: color 0.2s; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .icon-spin { animation: spin 0.8s linear infinite; display: inline-block; }
    </style>
</head>
<body>

<?php
$active_menu = 'inventory';
// components อยู่ที่ root ต้องขึ้น 1 ระดับจาก pages/
include __DIR__ . '/../components/sidebar.php';
?>

<div class="main-content">
    <?php
    $breadcrumbs = [
        ['label' => 'แผงควบคุม',        'href' => 'dashboard.php'],
        ['label' => 'จัดการคลังสินค้า',  'href' => 'inventory.php'],
        ['label' => 'เพิ่มหนังสือใหม่',  'href' => null],
    ];
    include __DIR__ . '/../components/topbar.php';
    ?>

    <div class="page-body">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <div>
                <div class="page-title">ลงขายหนังสือของคุณ</div>
                <div class="page-subtitle">แบ่งปันความรู้และเรื่องราวที่คุณรักให้แก่คนอ่านในชุมชนนี้</div>
            </div>
        </div>

        <!-- Import Excel Bar -->
        <div class="import-bar">
            <div>
                <div class="import-bar-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--primary-color)"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    นำเข้าข้อมูลจาก Excel
                </div>
                <div class="import-bar-sub">ส่งออกไฟล์ Excel เป็น CSV แล้วอัปโหลด — รองรับหลายรายการพร้อมกัน</div>
            </div>
            <form method="POST" enctype="multipart/form-data" style="display:flex;align-items:center;gap:8px;">
                <input type="hidden" name="action" value="import_excel">
                <input type="file" name="excel_file" accept=".csv" id="csvFile" style="display:none"
                       onchange="document.getElementById('csvLabel').textContent = this.files[0]?.name ?? 'เลือกไฟล์ CSV'">
                <label for="csvFile" class="import-file-btn" id="csvLabel">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    เลือกไฟล์ CSV
                </label>
                <button type="submit" class="btn-import-submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    นำเข้าข้อมูล
                </button>
            </form>
            <a href="#" class="template-link" onclick="downloadTemplate(); return false;">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                ดาวน์โหลด Template
            </a>
        </div>

        <!-- Main Form -->
        <form method="POST" enctype="multipart/form-data" id="bookForm">
            <input type="hidden" name="action" id="formAction" value="save_book">

            <div class="form-layout">
                <div>
                    <!-- Book Type Toggle -->
                    <div class="book-type-toggle">
                        <button type="button" class="book-type-btn active" id="typeNewBtn" onclick="setBookType('new')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            มือหนึ่ง (New)
                        </button>
                        <button type="button" class="book-type-btn" id="typeUsedBtn" onclick="setBookType('used')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            มือสอง (Used)
                        </button>
                    </div>
                    <input type="hidden" name="book_type" id="book_type" value="new">

                    <!-- include components — path ขึ้น 1 ระดับจาก pages/ ไปหา components/ ที่ root -->
                    <?php include __DIR__ . '/../components/book_form/form_images.php'; ?>
                    <?php include __DIR__ . '/../components/book_form/form_details.php'; ?>
                    <?php include __DIR__ . '/../components/book_form/form_condition.php'; ?>
                    <?php include __DIR__ . '/../components/book_form/form_shipping.php'; ?>
                </div>

                <?php include __DIR__ . '/../components/book_form/form_tips.php'; ?>
            </div>
        </form>

    </div>
</div>

<?php if ($success_msg): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($current_action === 'save_draft'): ?>
    Swal.fire({
        icon: 'success',
        title: 'บันทึกร่างเรียบร้อย!',
        text: 'สามารถแก้ไขและลงขายได้ภายหลังในหน้าคลังสินค้า',
        iconColor: '#C97B8B',
        confirmButtonText: 'ดูคลังสินค้า',
        showCancelButton: true,
        cancelButtonText: 'แก้ไขต่อ',
    }).then(r => { if (r.isConfirmed) window.location.href = 'inventory.php'; });
    <?php else: ?>
    Swal.fire({
        icon: 'success',
        title: '<?= addslashes($success_msg) ?>',
        iconColor: '#C97B8B',
        background: '#FFFFFF',
        confirmButtonText: 'ดูคลังสินค้า',
        showCancelButton: true,
        cancelButtonText: 'เพิ่มอีกเล่ม',
    }).then(r => { if (r.isConfirmed) window.location.href = 'inventory.php'; });
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php if ($error_msg): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: <?= json_encode($error_msg, JSON_UNESCAPED_UNICODE) ?>,
        iconColor: '#D9534F'
    });
});
</script>
<?php endif; ?>

<script>
function setFormAction(action) {
    document.getElementById('formAction').value = action;
}

function setBookType(type) {
    document.getElementById('book_type').value = type;
    document.getElementById('typeNewBtn').className = 'book-type-btn' + (type === 'new' ? ' active' : '');
    document.getElementById('typeUsedBtn').className = 'book-type-btn' + (type === 'used' ? ' active' : '');
    const isUsed = type === 'used';
    document.getElementById('conditionSection').style.display      = isUsed ? '' : 'none';
    document.getElementById('defectsSection').style.display        = isUsed ? '' : 'none';
    document.getElementById('originalPriceGroup').style.display    = isUsed ? '' : 'none';
    document.getElementById('priceGrid').style.gridTemplateColumns = isUsed ? '1fr 1fr' : '1fr';
    document.getElementById('conditionPriceTitleText').textContent  = isUsed ? 'สภาพและราคา' : 'ราคาขาย';
    if (!isUsed) {
        document.getElementById('condition_level').value = 'new';
        document.getElementById('originalPriceGroup').querySelector('input').value = '';
    }
}
document.addEventListener('DOMContentLoaded', () => setBookType(document.getElementById('book_type').value));

function setCondition(val) {
    document.getElementById('condition_level').value = val;
    document.querySelectorAll('.condition-btn').forEach(btn => {
        btn.className = 'condition-btn' + (btn.getAttribute('onclick').includes(val) ? ' active' : '');
    });
}

function previewImage(input, slotId, urlInputId) {
    const slot = document.getElementById(slotId);
    slot.querySelector('img')?.remove();
    if (input.files?.[0]) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(input.files[0]);
        slot.appendChild(img);
        const u = document.getElementById(urlInputId);
        if (u) u.value = '';
    }
}

function previewImageFromUrl(slotId, urlInputId, imageUrl) {
    const slot = document.getElementById(slotId);
    slot.querySelector('img')?.remove();
    const img = document.createElement('img');
    img.src = imageUrl;
    slot.appendChild(img);
    const u = document.getElementById(urlInputId);
    if (u) u.value = imageUrl;
}

async function lookupISBN() {
    const isbn = document.getElementById('isbn_input').value.trim();
    if (!isbn) return;
    const btn    = document.getElementById('btnLookup');
    const textEl = document.getElementById('lookupText');
    document.getElementById('lookupIcon').outerHTML =
        `<svg id="lookupIcon" class="icon-spin" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
    textEl.textContent = 'กำลังค้นหา...';
    btn.disabled = true;
    try {
        const result = await fetch(`add_book.php?ajax=lookup_isbn&isbn=${encodeURIComponent(isbn)}`).then(r => r.json());
        if (result.found) {
            const b = result.data;
            const setVal = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
            setVal('book_title', b.title);       setVal('book_author', b.author);
            setVal('publisher_name', b.publisher_name); setVal('isbn_13', b.isbn_13);
            setVal('book_category', b.category); setVal('book_format', b.format);
            setVal('book_pages', b.pages);       setVal('book_dimensions', b.dimensions);
            setVal('book_weight', b.weight_grams); setVal('book_language', b.language);
            setVal('book_synopsis', b.synopsis);
            if (b.publish_date) setVal('book_publish_date', b.publish_date.length === 4 ? b.publish_date + '-01-01' : b.publish_date);
            if (b.image_cover) previewImageFromUrl('slot_image_cover', 'image_cover_url', b.image_cover);
            Swal.fire({ icon: 'success', title: 'พบข้อมูลหนังสือ', text: 'ดึงข้อมูลจาก Google Books เรียบร้อย', iconColor: '#80c97b', timer: 2200, showConfirmButton: false });
        } else {
            Swal.fire({ icon: 'info', title: 'ไม่พบข้อมูล', text: result.message || 'กรุณากรอกข้อมูลเอง', iconColor: '#C97B8B' });
        }
    } catch {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถค้นหาได้ในขณะนี้' });
    } finally {
        document.getElementById('lookupIcon').outerHTML =
            `<svg id="lookupIcon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>`;
        document.getElementById('lookupText').textContent = 'ค้นหา';
        btn.disabled = false;
    }
}

function downloadTemplate() {
    const header  = 'title,author,isbn,price,stock_quantity,category,book_type,image_cover,image_back,image_inside,image_edge\n';
    const example = 'ตัวอย่างชื่อหนังสือ,ชื่อผู้แต่ง,9789748480093,350,10,วรรณกรรมไทย,used,https://example.com/cover.jpg,,,\n';
    const blob = new Blob(['\uFEFF' + header + example], { type: 'text/csv;charset=utf-8;' });
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'book_import_template.csv' });
    a.click();
}
</script>
</body>
</html>