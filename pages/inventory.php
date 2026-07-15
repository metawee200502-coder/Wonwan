<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

$seller_id = null; $seller_type = null; $seller_name = '';

if ($role === 'publisher_admin') {
    $stmt = $pdo->prepare('SELECT ps.publisher_id, p.company_name FROM publisher_staff ps JOIN publishers p ON ps.publisher_id = p.publisher_id WHERE ps.user_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    if ($row = $stmt->fetch()) { $seller_id = $row['publisher_id']; $seller_type = 'publisher'; $seller_name = $row['company_name']; }
} else {
    $stmt = $pdo->prepare('SELECT ss.shop_id, s.shop_name FROM shop_staff ss JOIN shops s ON ss.shop_id = s.shop_id WHERE ss.user_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    if ($row = $stmt->fetch()) { $seller_id = $row['shop_id']; $seller_type = 'shop'; $seller_name = $row['shop_name']; }
}
if (!$seller_id) { header('Location: dashboard.php'); exit; }

// ==================== POST Handler ====================
$success_msg = ''; $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action   = $_POST['action'];
    $book_ids = array_filter(array_map('intval', $_POST['book_ids'] ?? []));

    try {
        if ($action === 'change_status' && !empty($book_ids)) {
            $new_status = $_POST['new_status'] ?? '';
            if (in_array($new_status, ['available','hidden','sold','draft'])) {
                $pl   = implode(',', array_fill(0, count($book_ids), '?'));
                $stmt = $pdo->prepare("UPDATE books SET status=? WHERE book_id IN ($pl) AND seller_id=? AND seller_type=?");
                $stmt->execute(array_merge([$new_status], $book_ids, [$seller_id, $seller_type]));
                $success_msg = 'เปลี่ยนสถานะสำเร็จ! (' . $stmt->rowCount() . ' รายการ)';
            } else {
                $error_msg = 'กรุณาเลือกสถานะที่ต้องการเปลี่ยน';
            }
        }
        elseif ($action === 'delete_books' && !empty($book_ids)) {
            $pl   = implode(',', array_fill(0, count($book_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM books WHERE book_id IN ($pl) AND seller_id=? AND seller_type=?");
            $stmt->execute(array_merge($book_ids, [$seller_id, $seller_type]));
            $success_msg = 'ลบหนังสือสำเร็จ! (' . $stmt->rowCount() . ' รายการ)';
        }
        elseif ($action === 'edit_book_modal') {
            $stmt = $pdo->prepare("UPDATE books SET price=?, stock_quantity=?, status=?, original_price=?, condition_level=? WHERE book_id=? AND seller_id=? AND seller_type=?");
            $stmt->execute([
                (float)$_POST['edit_price'],
                (int)$_POST['edit_stock'],
                $_POST['edit_status'],
                !empty($_POST['edit_original_price']) ? (float)$_POST['edit_original_price'] : null,
                !empty($_POST['edit_condition']) ? $_POST['edit_condition'] : 'new', // ✅ กัน NOT NULL
                (int)$_POST['edit_book_id'],
                $seller_id, $seller_type
            ]);
            $success_msg = 'อัปเดตข้อมูลหนังสือสำเร็จ!';
        }
        elseif (empty($book_ids) && $action !== 'edit_book_modal') {
            $error_msg = 'กรุณาเลือกหนังสือก่อน';
        }
    } catch (Exception $e) {
        $error_msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

// ==================== Query ====================
$page          = max(1, (int)($_GET['page']   ?? 1));
$limit         = 10;
$offset        = ($page - 1) * $limit;
$tab           = $_GET['tab']    ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$sort          = $_GET['sort']   ?? 'latest';

$where  = 'WHERE b.seller_id = :seller_id AND b.seller_type = :seller_type';
$params = [':seller_id' => $seller_id, ':seller_type' => $seller_type];

if ($tab === 'new')  $where .= ' AND b.book_type = "new"';
elseif ($tab === 'used') $where .= ' AND b.book_type = "used"';

if ($status_filter === 'available') $where .= ' AND b.status = "available"';
elseif ($status_filter === 'hidden') $where .= ' AND b.status = "hidden"';
elseif ($status_filter === 'low_stock') $where .= ' AND b.status = "available" AND b.stock_quantity <= 5';
elseif ($status_filter === 'sold')  $where .= ' AND b.status = "sold"';
elseif ($status_filter === 'draft') $where .= ' AND b.status = "draft"';

$order = match($sort) {
    'price_asc'  => 'b.price ASC',
    'price_desc' => 'b.price DESC',
    'stock_asc'  => 'b.stock_quantity ASC',
    default      => 'b.created_at DESC',
};

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM books b $where");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages   = ceil($total_records / $limit);

$stmt = $pdo->prepare("
    SELECT b.book_id, b.title, b.author, b.category, b.price, b.original_price,
           b.stock_quantity, b.status, b.book_type, b.condition_level, b.created_at
    FROM books b $where ORDER BY $order
    LIMIT " . intval($limit) . " OFFSET " . intval($offset)
);
$stmt->execute($params);
$books = $stmt->fetchAll();

$statsStmt = $pdo->prepare("
    SELECT COUNT(*) as total,
        SUM(book_type='new') as total_new, SUM(book_type='used') as total_used,
        SUM(status='available' AND stock_quantity<=5) as low_stock,
        SUM(status='hidden') as hidden, SUM(status='sold') as sold, SUM(status='draft') as draft
    FROM books WHERE seller_id=? AND seller_type=?
");
$statsStmt->execute([$seller_id, $seller_type]);
$stats = $statsStmt->fetch();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคลังสินค้า - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-family); background: var(--bg-color); color: var(--text-dark); display: flex; min-height: 100vh; }
        .sidebar { width:220px; min-height:100vh; background:var(--card-bg); display:flex; flex-direction:column; padding:24px 0; border-right:1px solid var(--border-color); position:fixed; top:0; left:0; z-index:100; }
        .sidebar-logo { display:flex; align-items:center; gap:10px; padding:0 20px 28px; border-bottom:1px solid var(--border-color); }
        .sidebar-logo img { width:48px; height:48px; object-fit:contain; }
        .sidebar-logo-text { font-size:20px; font-weight:700; color:var(--secondary-color); line-height:1.1; }
        .sidebar-logo-sub { font-size:10px; color:var(--text-muted); }
        .sidebar-nav { flex:1; padding:20px 12px; display:flex; flex-direction:column; gap:4px; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:var(--radius-md); color:var(--text-muted); font-size:var(--text-sm); font-weight:500; text-decoration:none; transition:all 0.2s; }
        .nav-item:hover, .nav-item.active { background:var(--accent-pink-light); color:var(--primary-color); }
        .nav-item.active { font-weight:600; }
        .nav-item svg { width:18px; height:18px; flex-shrink:0; }
        .sidebar-profile { padding:16px 20px; border-top:1px solid var(--border-color); display:flex; align-items:center; gap:10px; }
        .profile-avatar { width:38px; height:38px; border-radius:50%; background:var(--accent-pink-light); display:flex; align-items:center; justify-content:center; color:var(--primary-color); font-weight:700; font-size:16px; overflow:hidden; }
        .profile-avatar img { width:100%; height:100%; object-fit:cover; }
        .profile-name { font-size:var(--text-sm); font-weight:600; color:var(--text-dark); }
        .profile-role { font-size:var(--text-xs); color:var(--primary-color); }
        .profile-seller { font-size:11px; font-weight:700; color:var(--secondary-color); margin-bottom:1px; }
        .topbar { background:var(--card-bg); border-bottom:1px solid var(--border-color); padding:14px 28px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .search-box { display:flex; align-items:center; gap:8px; background:var(--bg-color); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:8px 14px; width:340px; }
        .search-box input { border:none; background:transparent; font-family:var(--font-family); font-size:var(--text-sm); color:var(--text-dark); outline:none; width:100%; }
        .topbar-whoami { display:flex; align-items:center; gap:6px; font-size:var(--text-xs); white-space:nowrap; }
        .topbar-seller { font-weight:700; color:var(--primary-color); }
        .topbar-sep { color:var(--border-color); }
        .topbar-admin { color:var(--text-muted); }
        .breadcrumb { font-size:var(--text-xs); color:var(--text-muted); }
        .breadcrumb a { color:var(--primary-color); text-decoration:none; }
        .main-content { margin-left:220px; flex:1; display:flex; flex-direction:column; min-height:100vh; }
        .page-body { padding:28px; flex:1; }
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; }
        .page-title { font-size:22px; font-weight:700; color:var(--secondary-color); }
        .btn-add { background:var(--primary-color); color:#fff; border:none; border-radius:var(--radius-md); padding:10px 18px; font-family:var(--font-family); font-size:var(--text-sm); font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; text-decoration:none; transition:background 0.2s; }
        .btn-add:hover { background:var(--primary-hover); }
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:16px; margin-bottom:22px; }
        .stat-card { background:var(--card-bg); border-radius:var(--radius-lg); padding:16px 18px; border:1px solid var(--border-color); }
        .stat-card.alert { background:var(--accent-pink-light); border-color:var(--primary-color); }
        .stat-label { font-size:var(--text-xs); color:var(--text-muted); margin-bottom:6px; }
        .stat-value { font-size:24px; font-weight:700; color:var(--primary-color); }
        .stat-card.alert .stat-value { color:var(--secondary-color); }
        .table-card { background:var(--card-bg); border-radius:var(--radius-lg); border:1px solid var(--border-color); overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.03); }
        .table-toolbar { display:flex; align-items:center; gap:8px; padding:14px 20px; border-bottom:1px solid var(--border-color); flex-wrap:wrap; }
        .tab-btn { padding:7px 16px; border-radius:20px; border:none; font-family:var(--font-family); font-size:var(--text-sm); font-weight:500; cursor:pointer; background:transparent; color:var(--text-muted); transition:all 0.2s; text-decoration:none; }
        .tab-btn.active { background:var(--primary-color); color:#fff; }
        .tab-divider { width:1px; height:22px; background:var(--border-color); margin:0 2px; }
        .toolbar-right { margin-left:auto; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .select-control { padding:7px 10px; border-radius:var(--radius-md); border:1px solid var(--border-color); font-family:var(--font-family); font-size:var(--text-xs); background:var(--bg-color); color:var(--text-dark); outline:none; }
        .bulk-actions { display:none; align-items:center; gap:10px; padding:12px 20px; background:var(--accent-pink-light); border-bottom:1px solid var(--border-color); flex-wrap:wrap; }
        .bulk-actions.show { display:flex; }
        .bulk-actions-text { font-size:var(--text-sm); font-weight:600; color:var(--text-dark); }
        .btn-bulk { padding:7px 14px; border-radius:var(--radius-md); border:none; font-family:var(--font-family); font-size:var(--text-sm); font-weight:600; cursor:pointer; transition:all 0.2s; display:inline-flex; align-items:center; gap:5px; }
        .btn-bulk-primary { background:var(--primary-color); color:#fff; }
        .btn-bulk-primary:hover { background:var(--primary-hover); }
        .btn-bulk-danger { background:var(--accent-red); color:#fff; }
        .btn-bulk-danger:hover { opacity:0.85; }
        .inv-table { width:100%; border-collapse:collapse; }
        .inv-table th { padding:12px 16px; font-size:var(--text-xs); font-weight:600; color:var(--text-muted); text-align:left; border-bottom:1px solid var(--border-color); background:var(--bg-color); }
        .inv-table td { padding:13px 16px; border-bottom:1px solid var(--border-color); font-size:var(--text-sm); vertical-align:middle; }
        .inv-table tr:last-child td { border-bottom:none; }
        .inv-table tr:hover td { background:#FAFAFA; }
        .inv-table input[type="checkbox"] { width:16px; height:16px; cursor:pointer; accent-color:var(--primary-color); }
        .book-title { font-weight:600; color:var(--text-dark); margin-bottom:2px; }
        .book-author { font-size:var(--text-xs); color:var(--text-muted); }
        .badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:500; }
        .badge-new { background:#E8F5E9; color:var(--accent-green); }
        .badge-used { background:var(--accent-pink-light); color:var(--primary-color); }
        .price-text { color:var(--primary-color); font-weight:600; }
        .status-dot { display:inline-flex; align-items:center; gap:5px; font-size:var(--text-xs); font-weight:500; }
        .status-dot::before { content:''; width:8px; height:8px; border-radius:50%; display:inline-block; }
        .status-available::before { background:var(--accent-green); } .status-available { color:var(--accent-green); }
        .status-low::before { background:var(--accent-red); } .status-low { color:var(--accent-red); }
        .status-hidden::before { background:var(--text-muted); } .status-hidden { color:var(--text-muted); }
        .status-sold::before { background:#999; } .status-sold { color:#999; }
        .status-draft::before { background:var(--primary-color); } .status-draft { color:var(--primary-color); }
        .action-btn { padding:5px 11px; border-radius:var(--radius-sm); border:1px solid var(--border-color); background:var(--card-bg); font-family:var(--font-family); font-size:var(--text-xs); cursor:pointer; color:var(--text-dark); text-decoration:none; transition:all 0.2s; margin-right:4px; display:inline-block; }
        .action-btn:hover { background:var(--accent-pink-light); color:var(--primary-color); border-color:var(--primary-color); }
        .action-btn-del { border-color:#FECACA; color:var(--accent-red); }
        .action-btn-del:hover { background:#FEF2F2; color:var(--accent-red); border-color:var(--accent-red); }
        .pagination-row { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-top:1px solid var(--border-color); }
        .page-info { font-size:var(--text-xs); color:var(--text-muted); }
        .page-btns { display:flex; gap:4px; }
        .page-btn { padding:6px 11px; border-radius:var(--radius-md); border:1px solid var(--border-color); background:var(--card-bg); font-family:var(--font-family); font-size:var(--text-xs); font-weight:500; cursor:pointer; text-decoration:none; color:var(--text-dark); display:inline-flex; align-items:center; transition:all 0.2s; }
        .page-btn:hover, .page-btn.active { background:var(--primary-color); color:#fff; border-color:var(--primary-color); }
        .page-btn.disabled { opacity:0.4; pointer-events:none; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; justify-content:center; align-items:center; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:var(--card-bg); width:100%; max-width:460px; border-radius:var(--radius-lg); padding:26px; box-shadow:0 8px 24px rgba(0,0,0,0.15); }
        .modal-header { font-size:17px; font-weight:700; color:var(--secondary-color); margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; }
        .btn-close-modal { background:none; border:none; font-size:22px; cursor:pointer; color:var(--text-muted); }
        .modal-group { margin-bottom:13px; }
        .modal-group label { display:block; font-size:var(--text-sm); font-weight:600; margin-bottom:5px; color:var(--text-dark); }
        .modal-group input, .modal-group select { width:100%; padding:9px 12px; border-radius:var(--radius-md); border:1px solid var(--border-color); font-family:var(--font-family); font-size:var(--text-sm); background:var(--bg-color); color:var(--text-dark); outline:none; }
        .modal-group-section { padding:12px; background:var(--accent-pink-light); border-radius:var(--radius-md); margin-bottom:13px; }
        .modal-footer { margin-top:20px; display:flex; justify-content:flex-end; gap:10px; }
        .btn-modal-cancel { padding:8px 16px; border-radius:var(--radius-md); border:1.5px solid var(--border-color); background:var(--card-bg); font-family:var(--font-family); font-size:var(--text-sm); cursor:pointer; color:var(--text-dark); }
        .btn-modal-save { background:var(--primary-color); color:#fff; border:none; padding:8px 20px; border-radius:var(--radius-md); font-family:var(--font-family); font-size:var(--text-sm); font-weight:600; cursor:pointer; }
        .dashboard-footer { border-top:1px solid var(--border-color); padding:14px 28px; font-size:var(--text-xs); color:var(--text-muted); background:var(--card-bg); }
    </style>
</head>
<body>

<?php
$active_menu = 'inventory';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="main-content">
    <?php
    $show_search = true;
    include __DIR__ . '/../components/topbar.php';
    ?>

    <div class="page-body">

        <div class="page-header">
            <h1 class="page-title">จัดการคลังสินค้า</h1>
            <a href="add_book.php" class="btn-add">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                เพิ่มหนังสือใหม่
            </a>
        </div>

        <?php if ($success_msg): ?>
        <script>document.addEventListener('DOMContentLoaded',()=>Swal.fire({icon:'success',title:'<?= addslashes($success_msg) ?>',timer:2000,showConfirmButton:false,iconColor:'#C97B8B'}));</script>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <script>document.addEventListener('DOMContentLoaded',()=>Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด',text:'<?= addslashes($error_msg) ?>',iconColor:'#D9534F',confirmButtonColor:'#C97B8B'}));</script>
        <?php endif; ?>

        <!-- Stats -->
        <?php include __DIR__ . '/../components/inventory/inv_stats.php'; ?>

        <!-- Table Card -->
        <div class="table-card">

            <!-- Toolbar -->
            <?php include __DIR__ . '/../components/inventory/inv_toolbar.php'; ?>

            <!-- ONE FORM ครอบทั้งหมด -->
            <form method="POST" id="mainInventoryForm"
                  action="?tab=<?=$tab?>&status=<?=$status_filter?>&sort=<?=$sort?>&page=<?=$page?>">

                <!-- Bulk Actions -->
                <?php include __DIR__ . '/../components/inventory/inv_bulk_actions.php'; ?>

                <!-- Table -->
                <?php include __DIR__ . '/../components/inventory/inv_table.php'; ?>

            </form>

            <!-- Pagination -->
            <?php include __DIR__ . '/../components/inventory/inv_pagination.php'; ?>

        </div>
    </div>

    <div class="dashboard-footer">© 2024 Wanawan Forest Archive. สงวนลิขสิทธิ์ทั้งหมด</div>
</div>

<!-- Edit Modal -->
<?php include __DIR__ . '/../components/inventory/inv_edit_modal.php'; ?>

<script>
function toggleAll(cb) {
    document.querySelectorAll('.book-checkbox').forEach(c => c.checked = cb.checked);
    updateBulkCount();
}

function updateBulkCount() {
    const n   = document.querySelectorAll('.book-checkbox:checked').length;
    const bar = document.getElementById('bulkActions');
    document.getElementById('bulkCount').textContent = `เลือก ${n} รายการ`;
    n > 0 ? bar.classList.add('show') : bar.classList.remove('show');
    if (!n) document.getElementById('selectAll').checked = false;
}

function deleteSingle(bookId) {
    Swal.fire({
        title:'ยืนยันการลบ?', text:'หนังสือจะถูกลบอย่างถาวร ไม่สามารถกู้คืนได้',
        icon:'warning', iconColor:'#D9534F', showCancelButton:true,
        confirmButtonColor:'#D9534F', cancelButtonColor:'#a0a0a0',
        confirmButtonText:'ใช่, ลบเลย', cancelButtonText:'ยกเลิก', reverseButtons:true
    }).then(r => {
        if (r.isConfirmed) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.innerHTML = `<input type="hidden" name="action" value="delete_books"><input type="hidden" name="book_ids[]" value="${bookId}">`;
            document.body.appendChild(f);
            f.submit();
        }
    });
}

function deleteBulk() {
    Swal.fire({
        title:'ยืนยันลบรายการที่เลือก?', text:'หนังสือที่เลือกทั้งหมดจะถูกลบอย่างถาวร!',
        icon:'warning', iconColor:'#D9534F', showCancelButton:true,
        confirmButtonColor:'#D9534F', cancelButtonColor:'#a0a0a0',
        confirmButtonText:'ใช่, ลบทั้งหมด', cancelButtonText:'ยกเลิก', reverseButtons:true
    }).then(r => {
        if (r.isConfirmed) {
            const form  = document.getElementById('mainInventoryForm');
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'action'; input.value = 'delete_books';
            form.appendChild(input);
            form.submit();
        }
    });
}

function openEditModal(id, title, type, price, stock, status, originalPrice, condition) {
    document.getElementById('editModal').classList.add('show');
    document.getElementById('modalTitle').textContent  = 'แก้ไข: ' + title;
    document.getElementById('editBookId').value        = id;
    document.getElementById('editPrice').value         = price;
    document.getElementById('editStock').value         = stock;
    document.getElementById('editStatus').value        = status;
    const used = document.getElementById('usedBookFields');
    if (type === 'used') {
        used.style.display = 'block';
        document.getElementById('editOriginalPrice').value = originalPrice || '';
        document.getElementById('editCondition').value     = condition || 'good';
    } else {
        used.style.display = 'none';
        document.getElementById('editOriginalPrice').value = '';
        document.getElementById('editCondition').value     = 'new';
    }
}

function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
document.getElementById('editModal').addEventListener('click', function(e) { if (e.target===this) closeEditModal(); });
</script>
</body>
</html>