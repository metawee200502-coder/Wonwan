<?php
session_start();
if(empty($_SESSION['user_id'])){header('Location:/pages/login.php');exit;}
require_once __DIR__.'/../config/config.php';
$userId=(int)$_SESSION['user_id'];$profile_active='exchange';
if(empty($_SESSION['exchange_manage_csrf']))$_SESSION['exchange_manage_csrf']=bin2hex(random_bytes(24));
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['exchange_manage_csrf'],$_POST['csrf_token']??''))$error='คำขอไม่ถูกต้อง กรุณาลองใหม่';
 else{
  $requestId=(int)($_POST['request_id']??0);$action=$_POST['request_action']??'';
  $q=$pdo->prepare("SELECT er.*,e.owner_id,e.title target_title FROM exchange_requests er JOIN exchanges e ON e.exchange_id=er.exchange_id WHERE er.request_id=? AND (er.requester_id=? OR e.owner_id=?)");
  $q->execute([$requestId,$userId,$userId]);$request=$q->fetch(PDO::FETCH_ASSOC);
  if(!$request)$error='ไม่พบคำขอแลกเปลี่ยน';
  else{
   $isOwner=(int)$request['owner_id']===$userId;$allowed=$isOwner?['accept','reject','complete','track_owner','set_trade_option']:['cancel','track_requester','set_trade_option'];
   if(!in_array($action,$allowed,true))$error='คุณไม่มีสิทธิ์ดำเนินการนี้';
   elseif($action==='accept'&&$request['status']!=='pending')$error='คำขอนี้ไม่อยู่ในสถานะรอตอบรับ';
   elseif($action==='complete'&&!in_array($request['status'],['pending','accepted'],true))$error='ไม่สามารถยืนยันการแลกเปลี่ยนสำหรับคำขอนี้ได้';
   elseif(in_array($action,['reject','cancel'],true)&&!in_array($request['status'],['pending','accepted'],true))$error='ไม่สามารถเปลี่ยนสถานะคำขอนี้ได้';
   else{
    $pdo->beginTransaction();
    try{
     if($action==='set_trade_option'){
         $option = $_POST['trade_option'] ?? '';
         if(in_array($option, ['meetup', 'direct_escrow', 'admin_qc'])){
             $pdo->prepare('UPDATE exchange_requests SET trade_option=? WHERE request_id=?')->execute([$option, $requestId]);
             $message = 'บันทึกรูปแบบการแลกเปลี่ยนเรียบร้อยแล้ว';
         } else {
             throw new Exception('Invalid option');
         }
     }else{
         $newStatus=['accept'=>'accepted','reject'=>'rejected','complete'=>'completed','cancel'=>'cancelled'][$action];
         if($newStatus)$pdo->prepare('UPDATE exchange_requests SET status=? WHERE request_id=?')->execute([$newStatus,$requestId]);
         if (in_array($action, ['reject','cancel'], true) && in_array($request['trade_option'], ['direct_escrow', 'admin_qc'])) {
                $feeOrders = $pdo->prepare("SELECT order_id, order_number, subtotal, user_id FROM orders WHERE order_number LIKE ? AND shipping_status = 'pending'");
                $feeOrders->execute(['EXC-FEE-'.$requestId.'-%']);
                foreach ($feeOrders->fetchAll() as $fee) {
                    $pdo->prepare("UPDATE orders SET shipping_status = 'cancelled' WHERE order_number = ?")->execute([$fee['order_number']]);
                    // Refund full deposit
                    $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, available_balance = available_balance + ? WHERE seller_type = 'user' AND seller_id = ?")->execute([$fee['subtotal'], $fee['subtotal'], $fee['user_id']]);
                    $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, status, created_at, order_id) SELECT wallet_id, 'REFUND', ?, 'SUCCESS', NOW(), ? FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ?")->execute([$fee['subtotal'], $fee['order_id'], $fee['user_id']]);
                }
            }

         if($action==='accept'){$pdo->prepare("UPDATE exchanges SET status='in_progress' WHERE exchange_id IN (?,?)")->execute([(int)$request['exchange_id'],(int)($request['offered_exchange_id']??0)]);}
         elseif($action==='complete'){$pdo->prepare("UPDATE exchanges SET status='completed' WHERE exchange_id IN (?,?)")->execute([(int)$request['exchange_id'],(int)($request['offered_exchange_id']??0)]);

            // Mark fees as delivered and deduct escrow_balance
            if ($request['trade_option'] === 'direct_escrow' || $request['trade_option'] === 'admin_qc') {
                $feeOrders = $pdo->prepare("SELECT order_number, subtotal, gp_amount, user_id FROM orders WHERE order_number LIKE ?");
                $feeOrders->execute(['EXC-FEE-'.$requestId.'-%']);
                foreach ($feeOrders->fetchAll() as $fee) {
                    $pdo->prepare("UPDATE orders SET shipping_status = 'delivered', delivered_at = NOW() WHERE order_number = ?")->execute([$fee['order_number']]);
                    
                    // Deduct full deposit from escrow
                    $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance - ? WHERE seller_type = 'user' AND seller_id = ?")->execute([$fee['subtotal'], $fee['order_id'], $fee['user_id']]);
                    
                    // Refund the remainder (subtotal - gp_amount)
                    $refund = $fee['subtotal'] - $fee['gp_amount'];
                    if ($refund > 0) {
                        $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance + ? WHERE seller_type = 'user' AND seller_id = ?")->execute([$refund, $fee['order_id'], $fee['user_id']]);
                        // Record refund transaction
                        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, status, created_at, order_id) SELECT wallet_id, 'REFUND', ?, 'SUCCESS', NOW(), ? FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ?")->execute([$refund, $fee['order_id'], $fee['user_id']]);
                    }
                }
            }}
         elseif(in_array($action,['reject','cancel'],true)&&$request['status']==='accepted'){$pdo->prepare("UPDATE exchanges SET status='open' WHERE exchange_id IN (?,?) AND status='in_progress'")->execute([(int)$request['exchange_id'],(int)($request['offered_exchange_id']??0)]);}
            // Cancel fees and refund escrow_balance
            if (in_array($request['trade_option'], ['direct_escrow', 'admin_qc'])) {
                $feeOrders = $pdo->prepare("SELECT order_id, order_number, subtotal, user_id FROM orders WHERE order_number LIKE ? AND shipping_status = 'pending'");
                $feeOrders->execute(['EXC-FEE-'.$requestId.'-%']);
                foreach ($feeOrders->fetchAll() as $fee) {
                    $pdo->prepare("UPDATE orders SET shipping_status = 'cancelled' WHERE order_number = ?")->execute([$fee['order_number']]);
                    // Refund full deposit
                    $pdo->prepare("UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, available_balance = available_balance + ? WHERE seller_type = 'user' AND seller_id = ?")->execute([$fee['subtotal'], $fee['subtotal'], $fee['user_id']]);
                    $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, status, created_at, order_id) SELECT wallet_id, 'REFUND', ?, 'SUCCESS', NOW(), ? FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ?")->execute([$fee['subtotal'], $fee['order_id'], $fee['user_id']]);
                }
            }
         elseif($action==='track_owner'){$pdo->prepare("UPDATE exchange_requests SET offer_tracking_to_admin=? WHERE request_id=?")->execute([trim($_POST['tracking']??''),$requestId]);}
         elseif($action==='track_requester'){$pdo->prepare("UPDATE exchange_requests SET requester_tracking_to_admin=? WHERE request_id=?")->execute([trim($_POST['tracking']??''),$requestId]);}
         $recipient=$isOwner?(int)$request['requester_id']:(int)$request['owner_id'];
         $statusText=['accept'=>'ตอบรับคำขอแล้ว','reject'=>'ปฏิเสธคำขอแล้ว','complete'=>'ยืนยันการแลกเปลี่ยนสำเร็จแล้ว','cancel'=>'ยกเลิกคำขอแล้ว','track_owner'=>'อัปเดตเลขพัสดุแล้ว','track_requester'=>'อัปเดตเลขพัสดุแล้ว'][$action];
         wanawanNotifyUser($pdo, $recipient, 'อัปเดตคำขอแลกเปลี่ยน', $statusText.' สำหรับ “'.$request['target_title'].'”', 'exchange');
         $message=$statusText;
     }
     $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error='ไม่สามารถอัปเดตคำขอได้ กรุณาลองใหม่';}
   }
  }
 }
}
$tab=($_GET['tab']??'received')==='sent'?'sent':'received';$status=$_GET['status']??'';$category=trim($_GET['category']??'');$search=trim($_GET['search']??'');
$where=$tab==='received'?['target.owner_id=:uid']:['er.requester_id=:uid'];$params=[':uid'=>$userId];
if(in_array($status,['pending','accepted','completed','rejected','cancelled'],true)){$where[]='er.status=:status';$params[':status']=$status;}
if($category!==''){$where[]='target.category=:category';$params[':category']=$category;}
if($search!==''){
    $where[]='(target.title LIKE :search OR offer.title LIKE :search OR requester.display_name LIKE :search OR owner.display_name LIKE :search OR er.request_id = :search_exact)';
    $params[':search']='%'.$search.'%';
    $params[':search_exact']=str_replace('EX-','',$search);
}
$sql="SELECT er.*,target.title target_title,target.author target_author,target.image_cover target_image,target.condition_percent target_condition,target.category,target.owner_id,
 offer.title offer_title,offer.author offer_author,offer.image_cover offer_image,offer.condition_percent offer_condition,
 COALESCE(NULLIF(requester.display_name,''),requester.email) requester_name,requester.profile_image requester_image,
 COALESCE(NULLIF(owner.display_name,''),owner.email) owner_name,owner.profile_image owner_image
 FROM exchange_requests er JOIN exchanges target ON target.exchange_id=er.exchange_id
 JOIN users requester ON requester.user_id=er.requester_id JOIN users owner ON owner.user_id=target.owner_id
 LEFT JOIN exchanges offer ON offer.exchange_id=er.offered_exchange_id WHERE ".implode(' AND ',$where)." ORDER BY er.updated_at DESC,er.request_id DESC";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$requests=$stmt->fetchAll(PDO::FETCH_ASSOC);
$categories=$pdo->query("SELECT DISTINCT category FROM exchanges WHERE category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$total=$tab==='received'?$pdo->prepare("SELECT COUNT(*) FROM exchange_requests er JOIN exchanges e ON e.exchange_id=er.exchange_id WHERE e.owner_id=?"):$pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE requester_id=?");$total->execute([$userId]);$allCount=(int)$total->fetchColumn();
$rcStmt=$pdo->prepare("SELECT COUNT(*) FROM exchange_requests er JOIN exchanges e ON e.exchange_id=er.exchange_id WHERE e.owner_id=?");$rcStmt->execute([$userId]);$rcCount=(int)$rcStmt->fetchColumn();
$stStmt=$pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE requester_id=?");$stStmt->execute([$userId]);$stCount=(int)$stStmt->fetchColumn();

function xrImage(?string $v):?string{if(!$v)return null;if(preg_match('#^https?://#i',$v))return $v;$v=ltrim(str_replace('\\','/',$v),'/');$name=basename($v);if(is_file(__DIR__.'/uploads/profiles/'.$name))return '/pages/uploads/profiles/'.$name;return str_starts_with($v,'uploads/')?'/pages/'.$v:'/assets/image/books/'.$v;}
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>จัดการคำขอแลกเปลี่ยน - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
<style>
* {box-sizing:border-box}
body {background: #fdfdfd;}
.account{display:flex;min-height:850px;background:#fdfdfd;}
.manage{flex:1;max-width:1160px;margin:0 auto;padding:36px 42px 80px;min-width:0}
.header-area { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.header-text { max-width: 65%; }
.title{margin:0 0 10px;color:#111827;font-size:32px;font-weight:700;}
.subtitle { color: #6b7280; font-size: 15px; margin: 0; line-height: 1.5; }
.btn-add { padding: 10px 20px; border: 1px solid #4b5563; border-radius: 20px; background: #fff; color: #374151; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; transition: background .2s; }
.btn-add:hover { background: #f3f4f6; }

.tabs{display:flex;gap:12px;margin-bottom:24px; width:fit-content; background: #fff;}
.tabs a{padding:8px 24px;border-radius:24px;background:#fff;border:1px solid #e5e7eb;color:#6b7280;text-decoration:none;font-weight:600;font-size:14px;transition:all .2s; display:flex; align-items:center; gap:8px;}
.tabs a .badge { background: #f3f4f6; color: #4b5563; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; }
.tabs a.active{background:#115e59;color:#fff;border-color:#115e59;}
.tabs a.active .badge { background: #fff; color: #115e59; }
.tabs a:not(.active):hover{background:#f9fafb;}

.tools-container { background: #fff; border: 1px solid #f3f4f6; border-radius: 12px; padding: 14px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
.filters{display:flex;gap:12px; flex:1; align-items:center;}
.search-box { position: relative; width: 320px; }
.search-box input { width: 100%; height: 42px; padding: 0 16px 0 40px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; font: inherit; outline: none; transition: border-color .2s; }
.search-box input:focus { border-color: #115e59; background: #fff; }
.search-box svg { position: absolute; left: 14px; top: 13px; color: #9ca3af; width: 16px; height: 16px; }
.filters select{width:180px;height:42px;border:1px solid #e5e7eb;border-radius:8px;padding:0 12px;background:#f9fafb;font: inherit;color:#4b5563; outline: none; cursor:pointer;}
.filters select:focus { border-color: #115e59; background: #fff; }
.result{color:#4b5563;font-size: 13px; font-weight: 600; background: #f3f4f6; padding: 8px 16px; border-radius: 8px;}

.notice{padding:14px 18px;border-radius:8px;margin-bottom:20px; font-weight: 500;}
.ok{background:#ecfdf5;color:#065f46; border: 1px solid #a7f3d0;}
.bad{background:#fef2f2;color:#991b1b; border: 1px solid #fecaca;}

.request-list{display:grid;gap:24px}
.request-card{border:1px solid #f3f4f6;border-radius:16px;background:#fff;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.03); display:flex; flex-direction:column;}
.request-head{display:flex;align-items:center;justify-content:space-between;padding:16px 24px; border-bottom:1px solid #f9fafb;}
.person{display:flex;align-items:center;gap:14px}
.avatar{width:46px;height:46px;border-radius:50%;overflow:hidden;background:#fce7f3;display:grid;place-items:center;color:#db2777;font-weight:700;font-size:20px;}
.avatar img{width:100%;height:100%;object-fit:cover}
.person-info { display: flex; flex-direction: column; gap: 4px; }
.name-row { display: flex; align-items: center; gap: 8px; }
.person-name { color: #111827; font-size: 15px; font-weight: 700; }
.role-badge { background: #fef3c7; color: #b45309; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 12px; }
.req-id { color: #9ca3af; font-size: 12px; }

.status-group { display: flex; align-items: center; gap: 10px; }
.status-pill { display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.status-pill.success { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
.status-pill.success-text { color: #059669; background: transparent; }
.status-pill.pending { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
.status-pill.pending-text { color: #4b5563; background: #f3f4f6; }
.status-pill.rejected { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

.request-body{display:grid;grid-template-columns: minmax(0,1fr) 350px; }
.exchange-pair{display:grid;grid-template-columns:1fr 60px 1fr;align-items:center;gap:16px;padding:24px; background: #fdfdfd; border-radius: 16px; margin: 24px 0 24px 24px; border: 1px solid #f3f4f6; box-shadow: inset 0 2px 4px rgba(0,0,0,0.01);}
.book{text-align:center;min-width:0; display:flex; flex-direction:column; align-items:center;}
.book-label { background: #fffbeb; color: #d97706; font-size: 11px; font-weight: 700; padding: 4px 14px; border-radius: 16px; margin-bottom: 16px; border: 1px solid #fde68a;}
.cover{width:130px;height:185px;margin:auto;border-radius:8px;background:#f3f4f6;overflow:hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.08);}
.cover img{width:100%;height:100%;object-fit:cover}
.book-title {display:block;margin-top:16px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; font-weight: 700; width: 100%; font-size: 15px;}
.book-cond {display:block;color:#6b7280; font-size: 13px; margin-top: 4px;}
.swap{display:flex;flex-direction:column;align-items:center;justify-content:center;color:#6b7280;text-align:center; gap:8px;}
.swap-icon { width: 40px; height: 40px; border-radius: 50%; background: #fff; border: 1px solid #e5e7eb; display: grid; place-items: center; color: #115e59; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
.swap-text { font-size: 11px; font-weight: 700; color: #4b5563; }

.request-side{padding:24px;display:flex;flex-direction:column; gap: 16px;}
.quote-box { background: #fffbeb; border-radius: 12px; padding: 16px 20px; position: relative; color: #4b5563; font-style: italic; font-size: 14px; line-height: 1.6; display:flex; gap:12px; border: 1px solid #fef3c7; }
.quote-icon { color: #f59e0b; flex-shrink: 0; font-size: 24px; line-height: 1; font-family: serif; font-weight: bold; }
.actions{display:flex;flex-direction:column;gap:12px;margin-top:auto}
.btn{min-height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 16px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s; font-size:14px; gap:8px;}
.btn-outline { background: #fff; border: 1px solid #6b7280; color: #374151; }
.btn-outline:hover { background: #f9fafb; border-color: #4b5563; }
.btn-primary { background: #0f5132; border: 1px solid #0f5132; color: #fff; }
.btn-primary:hover { background: #0a3622; border-color: #0a3622; }
.btn-text-danger { background: transparent; border: none; color: #dc2626; font-size: 13px; font-weight: 600; text-decoration: underline; margin-top: 4px; display: block; width: 100%; text-align: center; cursor: pointer; transition: color .2s;}
.btn-text-danger:hover { color: #991b1b; }

.status-dropdown { width: 100%; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 13px; color: #4b5563; background: #f9fafb; cursor: pointer; outline: none; margin-top: 8px; font-weight: 600;}
.footer-note { border-top: 1px solid #f3f4f6; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; font-size: 13px; color: #6b7280; }
.footer-note-link { color: #4b5563; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 4px; }
.footer-note-link:hover { text-decoration: underline; }
.footer-success { color: #059669; display: flex; align-items: center; gap: 6px; }

.empty{padding:80px 20px;text-align:center;border:1px dashed #d1d5db;border-radius:16px;color:#9ca3af; background: #fff; font-size: 15px; font-weight: 500;}

.bottom-banner { background: #0f5132; border-radius: 16px; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; margin-top: 40px; color: #fff; }
.banner-left { display: flex; align-items: center; gap: 20px; }
.shield-icon { width: 56px; height: 56px; background: rgba(255,255,255,0.1); border-radius: 12px; display: grid; place-items: center; flex-shrink: 0; color: #fff;}
.banner-text h3 { margin: 0 0 6px 0; font-size: 18px; font-weight: 700; }
.banner-text p { margin: 0; font-size: 14px; color: rgba(255,255,255,0.8); line-height: 1.5; max-width: 600px; }
.banner-btn { background: #fff; color: #0f5132; padding: 12px 24px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 14px; transition: background .2s; white-space: nowrap; }
.banner-btn:hover { background: #f3f4f6; }

@media(max-width:1050px){.manage{padding:30px 24px}.request-body{grid-template-columns:1fr}.request-side{border-left:0;border-top:1px solid #f3f4f6; margin: 0 24px; padding-left: 0; padding-right: 0;}.exchange-pair {margin: 24px;}.bottom-banner { flex-direction: column; gap: 24px; text-align: center; align-items: stretch; }.banner-left { flex-direction: column; text-align: center; }.banner-btn { text-align: center; }}
@media(max-width:800px){.account{display:block}.manage{padding:28px 18px 60px}.tools-container{align-items:flex-start;flex-direction:column}.filters{width:100%; flex-direction: column;}.search-box, .search-box input, .filters select { width: 100%; }.tabs{overflow-x:auto; width:100%; padding-bottom: 4px;}.tabs a{min-width:150px; white-space: nowrap; justify-content: center;}.exchange-pair{padding:22px 16px}.header-area { flex-direction: column; gap: 16px; }.header-text { max-width: 100%; }}
@media(max-width:520px){.title{font-size:26px}.request-head{align-items:flex-start; flex-direction: column; gap: 12px;}.avatar{width:40px;height:40px}.exchange-pair{grid-template-columns:1fr 32px 1fr;gap:5px}.cover{width:100%;max-width:110px;height:160px}.swap-icon{width: 32px; height: 32px;}.swap-icon svg { width: 16px; height: 16px;}.result{font-size:13px; align-self: flex-start;}.footer-note { flex-direction: column; align-items: flex-start; gap: 12px; }}
</style>
</head>
<body>
<?php require __DIR__.'/../components/header.php';?>
<div class="account">
    <?php require __DIR__.'/../components/profile_sidebar.php';?>
    <main class="manage">
        
        <div class="header-area">
            <div class="header-text">
                <h1 class="title">จัดการคำขอแลกเปลี่ยน</h1>
                <p class="subtitle">ตรวจสอบ ติดตามสถานะ และดำเนินการคำขอแลกเปลี่ยนหนังสือของคุณทั้งหมดผ่านศูนย์กลางความปลอดภัย</p>
            </div>
            <a href="/pages/create_exchange.php" class="btn-add">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> เพิ่มหนังสือที่ต้องการแลก
            </a>
        </div>

        <nav class="tabs">
            <a class="<?=$tab==='received'?'active':''?>" href="?tab=received">คำขอที่ได้รับ <span class="badge"><?=$rcCount?></span></a>
            <a class="<?=$tab==='sent'?'active':''?>" href="?tab=sent">คำขอที่ส่งไป <span class="badge"><?=$stCount?></span></a>
        </nav>

        <?php if($message):?><div class="notice ok"><?=htmlspecialchars($message)?></div><?php endif;?>
        <?php if($error):?><div class="notice bad"><?=htmlspecialchars($error)?></div><?php endif;?>

        <div class="tools-container">
            <form class="filters">
                <input type="hidden" name="tab" value="<?=$tab?>">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="ค้นหาชื่อหนังสือ, ผู้ใช้ หรือรหัสคำขอ..." value="<?=htmlspecialchars($search)?>">
                </div>
                <select name="category" onchange="this.form.submit()">
                    <option value="">ทุกหมวดหมู่</option>
                    <?php foreach($categories as $value):?>
                        <option value="<?=htmlspecialchars($value)?>" <?=$category===$value?'selected':''?>><?=htmlspecialchars($value)?></option>
                    <?php endforeach;?>
                </select>
                <select name="status" onchange="this.form.submit()">
                    <option value="">แลกเปลี่ยนสำเร็จ</option>
                    <option value="">ทุกสถานะ</option>
                    <?php foreach(['pending'=>'รอตอบรับ','accepted'=>'กำลังเจรจา','completed'=>'แลกเปลี่ยนสำเร็จ','rejected'=>'ปฏิเสธแล้ว','cancelled'=>'ยกเลิกแล้ว'] as $value=>$label):?>
                        <option value="<?=$value?>" <?=$status===$value?'selected':''?>><?=$label?></option>
                    <?php endforeach;?>
                </select>
            </form>
            <span class="result">แสดง <?=number_format(count($requests))?> รายการ จาก <?=number_format($allCount)?> รายการ</span>
        </div>

        <section class="request-list">
            <?php if(!$requests):?>
                <div class="empty">ยังไม่มีคำขอแลกเปลี่ยนในหมวดนี้</div>
            <?php else:
            foreach($requests as $request):
                $otherName=$tab==='received'?$request['requester_name']:$request['owner_name'];
                $otherImage=$tab==='received'?$request['requester_image']:$request['owner_image'];
                $otherId=$tab==='received'?(int)$request['requester_id']:(int)$request['owner_id'];
                
                $statusPillClass = 'pending';
                $statusPillText = 'รอตอบรับ';
                if($request['status'] === 'accepted') { $statusPillClass = 'success'; $statusPillText = 'กำลังเจรจา'; }
                if($request['status'] === 'completed') { $statusPillClass = 'success'; $statusPillText = 'แลกเปลี่ยนสำเร็จ'; }
                if($request['status'] === 'rejected') { $statusPillClass = 'rejected'; $statusPillText = 'ปฏิเสธแล้ว'; }
                if($request['status'] === 'cancelled') { $statusPillClass = 'rejected'; $statusPillText = 'ยกเลิกแล้ว'; }

                $isQCPassed = ($request['trade_option'] === 'admin_qc' && $request['admin_qc_offer_status'] === 'passed' && $request['admin_qc_requester_status'] === 'passed');
            ?>
            <article class="request-card">
                <header class="request-head">
                    <div class="person">
                        <span class="avatar">
                            <?php if($img=xrImage($otherImage)):?>
                                <img src="<?=htmlspecialchars($img)?>" alt="">
                            <?php else:?>
                                <?=htmlspecialchars(mb_substr($otherName,0,1))?>
                            <?php endif;?>
                        </span>
                        <div class="person-info">
                            <div class="name-row">
                                <span class="person-name"><?=htmlspecialchars($otherName)?></span>
                                <span class="role-badge"><?=$tab==='received'?'ผู้ยื่นคำขอ':'ผู้รับคำขอ'?></span>
                            </div>
                            <span class="req-id">รหัสคำขอ: <?=formatExchangeOrderId($request['request_id'])?> - <?=date('d M Y H:i', strtotime($request['created_at']))?> น.</span>
                        </div>
                    </div>
                    <div class="status-group">
                        <span class="status-pill <?=$statusPillClass?>">
                            <?php if(in_array($statusPillClass, ['success', 'success-text'])): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <?php endif; ?>
                            <?=$statusPillText?>
                        </span>
                        <?php if($isQCPassed): ?>
                            <span class="status-pill success-text">ผ่าน QC ส่วนกลางแล้ว</span>
                        <?php elseif($request['status'] === 'pending' && $tab === 'received'): ?>
                            <span class="status-pill pending-text">ตอบรับภายใน 24 ชม.</span>
                        <?php endif; ?>
                    </div>
                </header>
                
                <div class="request-body">
                    <div class="exchange-pair">
                        <div class="book">
                            <span class="book-label"><?=$tab==='received'?'หนังสือของคุณ':'หนังสือของคุณ'?></span>
                            <div class="cover">
                                <?php if($img=xrImage($tab==='received'?$request['target_image']:$request['offer_image'])):?>
                                    <img src="<?=htmlspecialchars($img)?>" alt="">
                                <?php endif;?>
                            </div>
                            <strong class="book-title"><?=htmlspecialchars(($tab==='received'?$request['target_title']:$request['offer_title'])?:'ยังไม่ระบุหนังสือ')?></strong>
                            <small class="book-cond">สภาพ <?=htmlspecialchars(($tab==='received'?$request['target_condition']:$request['offer_condition'])?:'90')?>% <?=$tab==='received'?'(ปกอ่อน ไร้รอยขีดเขียน)':'(ไม่มีไฮไลท์)'?></small>
                        </div>
                        
                        <div class="swap">
                            <div class="swap-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3l4 4-4 4"/><path d="M20 7H4"/><path d="M8 21l-4-4 4-4"/><path d="M4 17h16"/></svg>
                            </div>
                            <span class="swap-text"><?=($request['status']==='pending')?'รอจับคู่':'จับคู่สลับเล่ม'?></span>
                        </div>
                        
                        <div class="book">
                            <span class="book-label"><?=$tab==='received'?'หนังสือที่เสนอ':'หนังสือที่เสนอ'?></span>
                            <div class="cover">
                                <?php if($img=xrImage($tab==='received'?$request['offer_image']:$request['target_image'])):?>
                                    <img src="<?=htmlspecialchars($img)?>" alt="">
                                <?php endif;?>
                            </div>
                            <strong class="book-title"><?=htmlspecialchars(($tab==='received'?$request['offer_title']:$request['target_title'])?:'ขอพูดคุยก่อน')?></strong>
                            <small class="book-cond">เจ้าของ: <?=htmlspecialchars($otherName)?> (สภาพ <?=htmlspecialchars(($tab==='received'?$request['offer_condition']:$request['target_condition'])?:'90')?>%)</small>
                        </div>
                    </div>
                    
                    <aside class="request-side">
                                                <div class="quote-box">
                            <div class="quote-icon">“</div>
                            <?php if(trim($request['message'] ?? '')): ?>
                                <div><?=nl2br(htmlspecialchars($request['message'])) ?>”</div>
                            <?php else: ?>
                                <div style="color: #9ca3af; font-style: italic;">ไม่มีหมายเหตุแนบมา”</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="actions">
                            <?php if($tab==='received' && $request['status']==='pending'): ?>
                                <form method="post" style="display:flex; flex-direction:column; gap:12px; width:100%;">
                                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>">
                                    <input type="hidden" name="request_id" value="<?=$request['request_id']?>">
                                    <button class="btn btn-primary" name="request_action" value="accept">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> ตอบรับคำขอแลกเปลี่ยน
                                    </button>
                                    <a class="btn btn-outline" href="/pages/chat.php?seller_type=user&seller_id=<?=$otherId?>&exchange_id=<?=$request['exchange_id']?>">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg> สอบถามเพิ่มเติม / เจรจา
                                    </a>
                                    <button type="submit" class="btn-text-danger" name="request_action" value="reject" onclick="return confirm('ยืนยันที่จะปฏิเสธคำขอนี้?');">ปฏิเสธคำขอนี้</button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline" onclick="openExchangeDetails(<?=$request['request_id']?>)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> ดูรายละเอียด
                                </button>
                                <a class="btn btn-primary" href="/pages/chat.php?seller_type=user&seller_id=<?=$otherId?>&exchange_id=<?=$request['exchange_id']?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg> ส่งข้อความ
                                </a>
                                
                                <?php if($request['status'] === 'accepted' || $request['status'] === 'completed'): ?>
                                    <form method="post" style="width:100%;">
                                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>">
                                        <input type="hidden" name="request_id" value="<?=$request['request_id']?>">
                                        <select name="request_action" class="status-dropdown" onchange="if(this.value) this.form.submit();">
                                            <option value="">● แลกเปลี่ยนสำเร็จ <?=($request['status']==='completed')?'(ปิดรายการแล้ว)':'(อัพเดทสถานะ)'?></option>
                                            <?php if($request['status'] === 'accepted'): ?>
                                                <option value="complete">ยืนยันแลกเปลี่ยนสำเร็จ</option>
                                                <?php if($tab==='received'): ?><option value="reject">ปฏิเสธ</option><?php endif; ?>
                                                <option value="cancel">ยกเลิกคำขอ</option>
                                            <?php endif; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>

                                <?php if($tab==='sent' && $request['status']==='pending'): ?>
                                    <form method="post" style="width:100%; text-align:center;">
                                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>">
                                        <input type="hidden" name="request_id" value="<?=$request['request_id']?>">
                                        <button class="btn-text-danger" name="request_action" value="cancel" onclick="return confirm('ต้องการยกเลิกคำขอนี้ใช่หรือไม่?')">ยกเลิกคำขอ</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </aside>
                </div>
                
                <?php if($request['status'] === 'completed' || $isQCPassed): ?>
                    <div class="footer-note">
                        <span class="footer-success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            ส่งมอบพัสดุปลายทางเรียบร้อย: หมายเลขพัสดุ Kerry KER-TH<?=$request['request_id']?>882918
                        </span>
                        <a href="#" class="footer-note-link">ดูใบเสร็จและบันทึกการส่งมอบ</a>
                    </div>
                <?php elseif($request['status'] === 'pending' && $tab === 'received'): ?>
                    <div class="footer-note">
                        <span>เมื่อคุณกด "ตอบรับ" ระบบจะสร้างโค้ดส่งพัสดุผ่านจุดดรอปของ Wanawan Hub ทันที</span>
                        <span style="font-weight:600; color:#4b5563;">ไม่มีค่าธรรมเนียมตัวกลาง</span>
                    </div>
                <?php endif; ?>
            </article>
            <?php endforeach;endif;?>
        </section>

        <div class="bottom-banner">
            <div class="banner-left">
                <div class="shield-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                </div>
                <div class="banner-text">
                    <h3>แลกเปลี่ยนมั่นใจด้วยระบบตัวกลาง Hub Escrow</h3>
                    <p>หนังสือทุกเล่มจะได้รับการตรวจสอบคุณภาพ (QC) และยืนยันสภาพจริงโดยทีมงานส่วนกลางก่อนสลับจัดส่งให้แก่สมาชิก เพื่อความพึงพอใจและโปร่งใสสูงสุดของนักอ่านทุกคน</p>
                </div>
            </div>
            <a href="#" class="banner-btn">อ่านข้อกำหนดความปลอดภัย</a>
        </div>
    </main>
</div>
<?php require __DIR__.'/../components/footer.php';?>

<!-- Exchange Details Modal -->
<div id="exchangeDetailsModal" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div class="modal-content" style="background:#fff; width:100%; max-width:640px; border-radius:24px; padding:32px; max-height:90vh; overflow-y:auto; position:relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <button onclick="closeExchangeDetails()" style="position:absolute; right:24px; top:24px; background:#f3f4f6; border:none; font-size:20px; cursor:pointer; color:#4b5563; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:background 0.2s;">&times;</button>
        <h2 style="margin:0 0 24px 0; font-size:22px; color:#111827; display:flex; align-items:center; gap:10px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#115e59" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            สรุปรายการ <span id="modal-req-id" style="color:#115e59;"></span>
        </h2>
        
        <div style="background:#f8fafc; padding:20px; border-radius:16px; margin-bottom:20px; border: 1px solid #f1f5f9;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="flex:1; text-align:center;">
                    <span style="display:inline-block; padding:4px 12px; background:#e0e7ff; color:#3730a3; border-radius:12px; font-size:12px; font-weight:600; margin-bottom:8px;">หนังสือของฉัน</span>
                    <p style="margin:0; font-weight:600; color:#1e293b; font-size:15px;" id="modal-my-book"></p>
                </div>
                <div style="padding:0 24px; color:#94a3b8;">
                    <div style="width:40px; height:40px; background:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#115e59" stroke-width="2"><path d="M8 3L12 7L8 11M16 21L12 17L16 13M12 7H4M20 17H12"></path></svg>
                    </div>
                </div>
                <div style="flex:1; text-align:center;">
                    <span style="display:inline-block; padding:4px 12px; background:#fef3c7; color:#92400e; border-radius:12px; font-size:12px; font-weight:600; margin-bottom:8px;">หนังสือของ <span id="modal-partner-name"></span></span>
                    <p style="margin:0; font-weight:600; color:#1e293b; font-size:15px;" id="modal-partner-book"></p>
                </div>
            </div>
            
            <div style="text-align:center; margin-top:16px; padding-top:16px; border-top:1px solid #e2e8f0;">
                <span style="color:#64748b; font-size:13px;">รูปแบบการแลกเปลี่ยน: </span><strong style="color:#115e59; font-size:14px;" id="modal-trade-option"></strong>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
            <div style="background:#fff; padding:20px; border-radius:16px; border: 1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
                <h3 style="margin:0 0 12px 0; font-size:15px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    ลำดับเวลา
                </h3>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span style="color:#64748b;">เริ่มเจรจา:</span>
                        <span id="modal-created-at" style="color:#334155; font-weight:500;"></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span style="color:#64748b;">ทำรายการล่าสุด:</span>
                        <span id="modal-updated-at" style="color:#334155; font-weight:500;"></span>
                    </div>
                </div>
            </div>
            
            <div style="background:#fff; padding:20px; border-radius:16px; border: 1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
                <h3 style="margin:0 0 12px 0; font-size:15px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    การจัดส่งพัสดุ
                </h3>
                <div id="modal-logistics-content" style="display:flex; flex-direction:column; gap:8px; font-size:13px;">
                </div>
            </div>
        </div>

        <div id="modal-qc-section" style="background:linear-gradient(to right, #ecfdf5, #f0fdf4); padding:20px; border-radius:16px; margin-bottom:20px; border:1px solid #a7f3d0; display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3 style="margin:0 0 6px 0; font-size:15px; color:#065f46; display:flex; align-items:center; gap:6px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path></svg>
                        แบบฟอร์มตรวจสอบสภาพ
                    </h3>
                    <p style="margin:0; font-size:13px; color:#047857;">ดูภาพถ่ายและประเมินสภาพหนังสืออย่างละเอียด</p>
                </div>
                <a href="#" id="modal-qc-link" style="background:#10b981; color:#fff; padding:8px 16px; border-radius:20px; text-decoration:none; font-size:13px; font-weight:600; box-shadow:0 2px 4px rgba(16, 185, 129, 0.2); transition:all 0.2s;">เปิดดูฟอร์ม QC</a>
            </div>
        </div>

        <div style="background:#fff; padding:20px; border-radius:16px; border: 1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
            <h3 style="margin:0 0 16px 0; font-size:15px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                สรุปข้อมูลการเงิน
            </h3>
            <div id="modal-financial-content" style="font-size:14px; color:#334155;">
                <div style="text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;">กำลังโหลด...</div>
            </div>
        </div>

    </div>
</div>

<script>
function openExchangeDetails(requestId) {
    document.getElementById('exchangeDetailsModal').style.display = 'flex';
    document.getElementById('modal-financial-content').innerHTML = '<div style="text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;">กำลังโหลดข้อมูล...</div>';
    let optEl = document.getElementById('modal-trade-option');
    if (optEl) optEl.textContent = 'กำลังโหลด...';
    
    fetch('/api/get_exchange_details.php?request_id=' + requestId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('เกิดข้อผิดพลาด: ' + (data.error || 'ไม่สามารถโหลดข้อมูลได้'));
                closeExchangeDetails();
                return;
            }
            
            document.getElementById('modal-req-id').textContent = data.request_id;
            document.getElementById('modal-my-book').textContent = data.my_book;
            document.getElementById('modal-partner-name').textContent = data.partner_name;
            document.getElementById('modal-partner-book').textContent = data.partner_book;
            document.getElementById('modal-created-at').textContent = data.created_at;
            document.getElementById('modal-updated-at').textContent = data.updated_at;
            
            let tradeOptionText = '';
            if (data.trade_option === 'admin_qc') tradeOptionText = 'แลกเปลี่ยนผ่านระบบกลาง (QC)';
            else if (data.trade_option === 'direct_escrow' || data.trade_option === 'p2p') tradeOptionText = 'ส่งพัสดุถึงกันโดยตรง';
            else if (data.trade_option === 'meetup') tradeOptionText = 'นัดรับสินค้า (ไม่ผ่านระบบ)';
            else if (!data.trade_option || data.trade_option === '') tradeOptionText = 'ไม่ระบุรูปแบบ (ข้อมูลเก่า)';
            else tradeOptionText = data.trade_option;
            
            let optEl = document.getElementById('modal-trade-option');
            if (optEl) optEl.textContent = tradeOptionText;
            
            // Logistics
            let logHtml = '';
            if (data.trade_option === 'admin_qc') {
                let secTitle = document.querySelector('#modal-logistics-content').previousElementSibling;
                if(secTitle) secTitle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg> การจัดส่งพัสดุ';
                logHtml += '<div style="display:flex; justify-content:space-between;"><span>พัสดุคุณ -> แอดมิน:</span><span style="font-weight:500;">' + (data.logistics.my_tracking || '-') + '</span></div>';
                logHtml += '<div style="display:flex; justify-content:space-between; margin-top:8px;"><span>พัสดุแอดมิน -> คุณ:</span><span style="font-weight:500;">' + (data.logistics.admin_tracking || '-') + '</span></div>';
            } else if (data.trade_option === 'direct_escrow') {
                let secTitle = document.querySelector('#modal-logistics-content').previousElementSibling;
                if(secTitle) secTitle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg> การจัดส่งพัสดุ';
                logHtml += '<div style="display:flex; justify-content:space-between;"><span>พัสดุคุณ -> คู่แลก:</span><span style="font-weight:500;">' + (data.logistics.my_tracking || '-') + '</span></div>';
                logHtml += '<div style="display:flex; justify-content:space-between; margin-top:8px;"><span>พัสดุคู่แลก -> คุณ:</span><span style="font-weight:500;">' + (data.logistics.partner_tracking || '-') + '</span></div>';
            } else if (data.trade_option === 'meetup') {
                let secTitle = document.querySelector('#modal-logistics-content').previousElementSibling;
                if(secTitle) secTitle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ข้อมูลการนัดรับ';
                let d = data.logistics.meetup_date;
                let dateStr = "ยังไม่ระบุ";
                if(d) {
                    let dateObj = new Date(d);
                    dateStr = dateObj.toLocaleDateString('th-TH', {year:'numeric', month:'short', day:'numeric'});
                }
                logHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#64748b;">วันที่:</span><span style="font-weight:500;">' + dateStr + '</span></div>';
                logHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#64748b;">เวลา:</span><span style="font-weight:500;">' + (data.logistics.meetup_time ? data.logistics.meetup_time.substr(0,5) + ' น.' : 'ยังไม่ระบุ') + '</span></div>';
                logHtml += '<div style="display:flex; flex-direction:column; margin-top:4px;"><span style="color:#64748b;">สถานที่:</span><span style="font-weight:500; margin-top:2px;">' + (data.logistics.meetup_location || 'ยังไม่ระบุ') + '</span></div>';
            } else {
                let secTitle = document.querySelector('#modal-logistics-content').previousElementSibling;
                if(secTitle) secTitle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg> การจัดส่งพัสดุ';
                logHtml += '<div style="text-align:center; color:#94a3b8; padding:4px 0;">ไม่มีข้อมูลพัสดุ</div>';
            }
            document.getElementById('modal-logistics-content').innerHTML = logHtml;
            
            // QC Form
            if (data.has_qc) {
                document.getElementById('modal-qc-section').style.display = 'block';
                document.getElementById('modal-qc-link').href = '/pages/trade_condition_form.php?request_id=' + data.raw_request_id;
            } else {
                document.getElementById('modal-qc-section').style.display = 'none';
            }
            
            // Financials
            let finHtml = '';
            
            // For admin_qc, it's a fixed 100 THB rate (50 service + 50 shipping), no refund.
            if (data.trade_option === 'admin_qc') {
                finHtml += '<div style="display:flex; flex-direction:column; gap:8px; width:100%;">';
                finHtml += '<div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">ค่าบริการตรวจเช็คสภาพ:</span><span style="font-weight:500; color:#334155;">50.00 บาท</span></div>';
                finHtml += '<div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">ค่าจัดส่งพัสดุ:</span><span style="font-weight:500; color:#334155;">50.00 บาท</span></div>';
                finHtml += '<div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:8px; border-top:1px dashed #e2e8f0;"><span style="color:#0f172a; font-weight:600;">ยอดชำระแล้ว:</span><span style="font-weight:700; color:#115e59;">100.00 บาท</span></div>';
                finHtml += '</div>';
            } 
            else if (data.trade_option === 'meetup') {
                finHtml = '<div style="text-align:center; padding:16px; color:#047857; background:#f0fdf4; border-radius:12px; border:1px solid #bbf7d0;"><div style="font-weight:600; margin-bottom:4px;">ฟรีค่าธรรมเนียม</div><div style="font-size:13px;">การแลกเปลี่ยนแบบนัดรับไม่มีค่าใช้จ่ายและไม่ต้องวางมัดจำ</div></div>';
            }
            else if (data.financials) {
                let fin = data.financials;
                
                finHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:12px; padding-bottom:12px; border-bottom:1px dashed #e2e8f0; width:100%;">';
                finHtml += '<span style="color:#64748b;">ยอดเงินมัดจำ (โอนไว้):</span><span style="font-weight:600; color:#111827;">' + fin.subtotal.toLocaleString('en-US', {minimumFractionDigits:2}) + ' บาท</span>';
                finHtml += '</div>';
                
                if (fin.fee > 0) {
                    finHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:12px;">';
                    finHtml += '<span style="color:#ef4444;">หักค่าธรรมเนียมแพลตฟอร์ม:</span><span style="font-weight:600; color:#ef4444;">-' + fin.fee.toLocaleString('en-US', {minimumFractionDigits:2}) + ' บาท</span>';
                    finHtml += '</div>';
                }
                
                if (fin.refund_amount) {
                    finHtml += '<div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:12px; border-radius:8px;">';
                    finHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:4px;">';
                    finHtml += '<span style="color:#166534; font-weight:600;">ยอดเงินที่คืนเข้ากระเป๋า:</span><span style="font-weight:700; color:#059669; font-size:15px;">+' + fin.refund_amount.toLocaleString('en-US', {minimumFractionDigits:2}) + ' บาท</span>';
                    finHtml += '</div>';
                    finHtml += '<div style="display:flex; justify-content:space-between; font-size:12px; color:#047857;">';
                    finHtml += '<span>สถานะ: <span style="background:#059669; color:#fff; padding:2px 6px; border-radius:4px; font-weight:600; font-size:11px;">' + (fin.refund_status === 'SUCCESS' ? 'สำเร็จ' : fin.refund_status) + '</span></span>';
                    finHtml += '<span>เวลา: ' + fin.refund_date + '</span>';
                    finHtml += '</div>';
                    finHtml += '</div>';
                } else if (data.status === 'completed') {
                    finHtml += '<div style="text-align:center; padding:10px; color:#d97706; background:#fef3c7; border-radius:8px; font-size:13px;">กำลังดำเนินการคืนเงินมัดจำ...</div>';
                }
            } else {
                finHtml = '<div style="text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;">ไม่มีข้อมูลการโอนเงิน (อาจทำรายการก่อนมีระบบการเงิน)</div>';
            }
            
            document.getElementById('modal-financial-content').innerHTML = finHtml;
            
        })
        .catch(err => {
            document.getElementById('modal-financial-content').innerHTML = '<div style="color:red; text-align:center; padding:16px;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
        });
}

function closeExchangeDetails() {
    document.getElementById('exchangeDetailsModal').style.display = 'none';
}
</script>

</body>
</html>



