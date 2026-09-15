<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
    die("Unauthorized");
}

$request_id = (int)($_GET['request_id'] ?? 0);
if (!$request_id) {
    die("Invalid request");
}

$viewer_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM admin_qc_reports WHERE request_id = ? AND item_owner_id = ?");
$stmt->execute([$request_id, $viewer_id]);
$reportToView = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reportToView) {
    die("ไม่พบรายงาน QC สำหรับคุณ");
}

$reqStmt = $pdo->prepare("
    SELECT er.*, t.owner_id as target_owner_id 
    FROM exchange_requests er
    JOIN exchanges t ON er.exchange_id = t.exchange_id
    WHERE er.request_id = ?
");
$reqStmt->execute([$request_id]);
$er = $reqStmt->fetch(PDO::FETCH_ASSOC);

$requester_id = $er['requester_id'];
$offer_id = $er['target_owner_id'];

// Ensure we have correct mapping. The admin form has:
// "ฝั่งผู้ขอแลก (ส่งหนังสือเป้าหมายมา?)" No, requester sends the offered book.
// In admin/qc_exchanges.php:
// $req_user_id = requester, $off_user_id = target_owner
// $req_is_match saved with $off_user_id as item_owner_id
// So $off_user_id is the item owner for req_report.

$is_iframe = isset($_GET['iframe']);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานการตรวจสอบสภาพสินค้า (QC Report)</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/color.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background: <?php echo $is_iframe ? 'transparent' : '#f8f9fa'; ?>; margin:0; padding:15px; color:#333; }
        .qc-container { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: <?php echo $is_iframe ? 'none' : '0 4px 12px rgba(0,0,0,0.1)'; ?>; padding: 20px; }
        .qc-header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; }
        .qc-header h2 { color: var(--primary-color); margin: 0 0 5px 0; font-size: 20px; }
        
        .qc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 600px) { .qc-grid { grid-template-columns: 1fr; } }
        
        .qc-section { border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; background: #f8fafc; }
        .qc-section h3 { margin: 0 0 15px 0; font-size: 16px; color: #1e293b; padding-bottom: 8px; border-bottom: 1px solid #cbd5e1; }
        
        .result-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 14px; margin-bottom: 10px; }
        .result-pass { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .result-fail { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .qc-notes { background: #fff; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 14px; min-height: 60px; margin-bottom: 15px; color: #475569; }
        
        .photo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .photo-item { position: relative; padding-top: 100%; border-radius: 6px; overflow: hidden; background: #e2e8f0; cursor: pointer; border: 1px solid #cbd5e1; }
        .photo-item img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .photo-label { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: #fff; font-size: 11px; text-align: center; padding: 2px 0; }
        
        /* Modal for fullscreen images */
        #imgModal { display: none; position: fixed; z-index: 9999; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); align-items: center; justify-content: center; }
        #imgModal img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        .close-modal { position: absolute; top: 20px; right: 30px; color: #fff; font-size: 30px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="qc-container">
        <div class="qc-header">
            <h2><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:bottom;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> รายงานการตรวจสอบสภาพสินค้า (QC)</h2>
            <p style="margin:5px 0 0 0; color:#64748b; font-size:14px;">รหัสคำขอ: #<?php echo $request_id; ?></p>
        </div>
        
        <div class="qc-grid" style="grid-template-columns: 1fr;">
            <?php 
                $isMatch = (bool)$reportToView['is_match'];
                $photos = json_decode($reportToView['photo_urls'] ?? '[]', true) ?: [];
                $title = 'สภาพหนังสือที่คุณจะได้รับ';
            ?>
            <div class="qc-section">
                <h3><?php echo $title; ?></h3>
                
                <div class="result-badge <?php echo $isMatch ? 'result-pass' : 'result-fail'; ?>">
                    <?php echo $isMatch ? '✅ สภาพตรงตามที่แจ้ง (ผ่าน)' : '❌ สภาพไม่ตรงตามที่แจ้ง (ไม่ผ่าน)'; ?>
                </div>
                
                <div style="font-size:13px; font-weight:600; color:#475569; margin-bottom:4px;">หมายเหตุจากแอดมิน:</div>
                <div class="qc-notes">
                    <?php echo nl2br(htmlspecialchars($reportToView['admin_notes'] ?: '-')); ?>
                </div>
                
                <div style="font-size:13px; font-weight:600; color:#475569; margin-bottom:8px;">ภาพถ่ายสภาพจริง:</div>
                <div class="photo-grid" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
                    <?php 
                    $labels = ['ปกหน้า', 'ปกหลัง', 'สันหนังสือ', 'เพิ่มเติม 1', 'เพิ่มเติม 2', 'เพิ่มเติม 3'];
                    foreach ($photos as $i => $url): 
                    ?>
                    <div class="photo-item" onclick="openFullscreen('<?php echo htmlspecialchars($url); ?>')">
                        <img src="<?php echo htmlspecialchars($url); ?>" alt="QC Photo">
                        <div class="photo-label"><?php echo $labels[$i] ?? 'รูปภาพ'; ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($photos)): ?>
                    <div style="grid-column: 1 / -1; text-align:center; padding:15px; background:#f1f5f9; border-radius:6px; font-size:12px; color:#94a3b8;">
                        ไม่มีรูปภาพแนบ
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div id="imgModal" onclick="this.style.display='none'">
        <span class="close-modal">&times;</span>
        <img id="imgModalContent" src="" onclick="event.stopPropagation()">
    </div>
    
    <script>
        function openFullscreen(src) {
            document.getElementById('imgModalContent').src = src;
            document.getElementById('imgModal').style.display = 'flex';
        }
    </script>
</body>
</html>
