<?php
session_start();
if(empty($_SESSION['user_id'])){header('Location:/pages/login.php');exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/order_notifications.php';
$userId=(int)$_SESSION['user_id'];
if(empty($_SESSION['seller_market_csrf']))$_SESSION['seller_market_csrf']=bin2hex(random_bytes(24));
$message='';$error='';

function sellerOwnsOrder(PDO $pdo,int $orderId,int $userId):?array{
    $q=$pdo->prepare("SELECT o.* FROM orders o JOIN order_items oi ON oi.order_id=o.order_id JOIN books b ON b.book_id=oi.book_id WHERE o.order_id=? AND b.seller_type='user' AND b.seller_id=? LIMIT 1");
    $q->execute([$orderId,$userId]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function notifyBuyer(PDO $pdo,array $order,string $title,string $message):void{
    wanawanNotifyOrderBuyer($pdo,$order,$title,$message);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($_SESSION['seller_market_csrf'],(string)($_POST['csrf_token']??'')))$error='ไม่สามารถยืนยันคำขอได้ กรุณาลองใหม่';
    else{
        $orderId=(int)($_POST['order_id']??0);$order=sellerOwnsOrder($pdo,$orderId,$userId);
        if(!$order)$error='ไม่พบคำสั่งซื้อที่คุณมีสิทธิ์จัดการ';
        elseif(isset($_POST['update_status'])){
            $status=(string)($_POST['status']??'');
            $map=[
                'delivered'=>["shipping_status='delivered'",'คำสั่งซื้อสำเร็จแล้ว','ผู้ขายอัปเดตว่าสินค้าจัดส่งสำเร็จแล้ว'],
                'cancelled'=>["payment_status='cancelled',shipping_status='cancelled'",'คำสั่งซื้อถูกยกเลิก','ผู้ขายยกเลิกคำสั่งซื้อ กรุณาติดต่อผู้ขายหากต้องการรายละเอียด']
            ];
            if(!isset($map[$status]))$error='สถานะที่เลือกไม่ถูกต้อง';
            else{[$set,$title,$body]=$map[$status];$pdo->beginTransaction();try{$pdo->prepare("UPDATE orders SET $set WHERE order_id=?")->execute([$orderId]);notifyBuyer($pdo,$order,$title,$body);$pdo->commit();$message='อัปเดตสถานะและแจ้งผู้ซื้อเรียบร้อยแล้ว';}catch(Throwable $e){$pdo->rollBack();$error='ไม่สามารถอัปเดตสถานะได้';}}
        }elseif(isset($_POST['save_tracking'])){
            $courier=trim((string)($_POST['courier']??''));$tracking=trim((string)($_POST['tracking_number']??''));
            if($order['payment_status']!=='paid')$error='กรุณาตรวจสอบสลิปให้ผ่านก่อนบันทึกเลขพัสดุ';
            elseif($courier===''||!preg_match('/^[A-Za-z0-9\-]{5,100}$/',$tracking))$error='กรุณากรอกบริษัทขนส่งและเลขพัสดุให้ถูกต้อง';
            else{$pdo->beginTransaction();try{$pdo->prepare("INSERT INTO order_shipments(order_id,seller_type,seller_id,courier,tracking_number,status,shipped_at) VALUES(?,'user',?,?,?,'shipped',NOW()) ON DUPLICATE KEY UPDATE courier=VALUES(courier),tracking_number=VALUES(tracking_number),status='shipped',shipped_at=NOW()")->execute([$orderId,$userId,$courier,$tracking]);$pdo->prepare("UPDATE orders SET shipping_status='shipped',shipping_courier=?,tracking_number=?,shipped_at=NOW() WHERE order_id=?")->execute([$courier,$tracking,$orderId]);notifyBuyer($pdo,$order,'ผู้ขายจัดส่งสินค้าแล้ว','จัดส่งผ่าน '.$courier.' เลขพัสดุ '.$tracking);$pdo->commit();$message='บันทึกเลขพัสดุและแจ้งผู้ซื้อเรียบร้อยแล้ว';}catch(Throwable $e){$pdo->rollBack();$error='ไม่สามารถบันทึกข้อมูลจัดส่งได้';}}
        }
    }
}

$tab=$_GET['tab']??'all';
$search=trim($_GET['search']??'');
$search=ltrim($search, '#');
$where="b.seller_type='user' AND b.seller_id=:uid";
$params=[':uid'=>$userId];

if($tab==='packing')$where.=" AND o.payment_status='paid' AND o.shipping_status IN('pending','packing')";
elseif($tab==='shipped')$where.=" AND o.shipping_status='shipped'";
elseif($tab==='done')$where.=" AND o.shipping_status='delivered'";
elseif($tab==='cancelled')$where.=" AND (o.payment_status='cancelled' OR o.shipping_status='cancelled')";
elseif($tab==='returned')$where.=" AND o.shipping_status='returned'";

if($search!==''){
    $where.=" AND (o.order_number LIKE :search OR b.title LIKE :search OR o.shipping_name LIKE :search)";
    $params[':search']='%'.$search.'%';
}

$q=$pdo->prepare("SELECT DISTINCT o.* FROM orders o JOIN order_items oi ON oi.order_id=o.order_id JOIN books b ON b.book_id=oi.book_id WHERE $where ORDER BY o.created_at DESC");
$q->execute($params);$orders=$q->fetchAll(PDO::FETCH_ASSOC);
$iq=$pdo->prepare("SELECT oi.*,b.title,b.author,b.image_cover,b.condition_level,b.category FROM order_items oi JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=? AND b.seller_type='user' AND b.seller_id=?");
foreach($orders as &$o){$iq->execute([$o['order_id'],$userId]);$o['items']=$iq->fetchAll(PDO::FETCH_ASSOC);}unset($o);

// Calculate total revenue
$revQ=$pdo->prepare("SELECT SUM(oi.price_at_purchase * oi.quantity) as total_revenue FROM order_items oi JOIN orders o ON o.order_id=oi.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type='user' AND b.seller_id=? AND o.shipping_status='delivered'");
$revQ->execute([$userId]);
$totalRevenue = (float)$revQ->fetchColumn();
$netRevenue = $totalRevenue * 0.85; // Mocking 15% GP deduction for display

$sq=$pdo->prepare("SELECT COUNT(*) total,SUM(payment_status='paid' AND shipping_status IN('pending','packing')) paid,SUM(shipping_status='shipped') shipping,SUM(shipping_status='delivered') completed,SUM(payment_status='cancelled' OR shipping_status='cancelled') cancelled,SUM(shipping_status='returned') returned FROM (SELECT DISTINCT o.* FROM orders o JOIN order_items oi ON oi.order_id=o.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type='user' AND b.seller_id=?) x");$sq->execute([$userId]);$stat=$sq->fetch(PDO::FETCH_ASSOC)?:[];
function smImg(?string $v):?string{if(!$v)return null;$v=ltrim(str_replace('\\','/',$v),'/');return preg_match('#^https?://#',$v)?$v:(str_starts_with($v,'uploads/')?'/pages/'.$v:'/assets/image/books/'.$v);}
function smSlip(?string $v):?string{if(!$v)return null;$v=ltrim(str_replace('\\','/',$v),'/');return '/pages/'.$v;}
function smStatus(array $o):array{if($o['shipping_status']==='returned')return['คืนสินค้า/คืนเงิน','cancel'];if($o['payment_status']==='cancelled'||$o['shipping_status']==='cancelled')return['ยกเลิกแล้ว','cancel'];if($o['shipping_status']==='delivered')return['สำเร็จแล้ว','done'];if($o['shipping_status']==='shipped')return['กำลังจัดส่ง','ship'];if($o['payment_status']==='paid')return['รอจัดส่ง','paid'];return['รอชำระ','wait'];}
$profile_active='market';
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ตลาดซื้อขายหนังสือ - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
<style>
* {box-sizing:border-box}
body {background: #fdfdfd;}
.account{display:flex;min-height:850px;background:#fdfdfd;}
.manage{flex:1;max-width:1160px;margin:0 auto;padding:36px 42px 80px;min-width:0}

.manage .header-area { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.manage .header-text { max-width: 60%; }
.manage .title{margin:0 0 10px;color:#111827;font-size:32px;font-weight:700;}
.manage .subtitle { color: #6b7280; font-size: 15px; margin: 0; line-height: 1.5; }
.manage .header-actions { display: flex; gap: 12px; }
.manage .btn-outline { padding: 10px 20px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; color: #374151; font-family: inherit; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; transition: background .2s; }
.manage .btn-outline:hover { background: #f9fafb; border-color: #9ca3af; }
.manage .btn-primary { padding: 10px 20px; border: 1px solid #0f5132; border-radius: 8px; background: #0f5132; color: #fff; font-family: inherit; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; transition: background .2s; }
.manage .btn-primary:hover { background: #0a3622; }

/* Export dropdown & animations */
.export-wrap { position: relative; }
.export-menu {
    display: none;
    position: absolute;
    right: 0; top: calc(100% + 6px);
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05);
    min-width: 235px;
    z-index: 200;
    overflow: hidden;
}
.export-wrap.open .export-menu { display: block; }
.export-menu a {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px;
    font-size: 14px; color: #374151; font-weight: 500;
    text-decoration: none; transition: background .15s, color .15s;
}
.export-menu a:hover { background: #f0fdf4; color: #166534; }
.export-menu hr { border: none; border-top: 1px solid #f3f4f6; margin: 0; }

.btn-export-market {
    padding: 10px 20px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; color: #374151; font-family: inherit; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; transition: all .2s ease;
}
.btn-export-market:hover:not(:disabled) { background: #f9fafb; border-color: #9ca3af; }
.btn-export-market:disabled { opacity: 0.92; cursor: wait; }
.btn-export-market.loading { background: #f3f4f6; color: #115e59; border-color: #115e59; }
.btn-export-market.success { background: #15803D !important; color: #fff !important; border-color: #15803D !important; }

@keyframes spinIcon {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.spin-icon { animation: spinIcon 0.75s linear infinite; }

.dashboard-card { background: #115e59; border-radius: 16px; padding: 24px 32px; color: #fff; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(17, 94, 89, 0.1); }
.dashboard-left { display: flex; gap: 20px; align-items: center; }
.dashboard-number { font-size: 64px; font-weight: 700; line-height: 1; margin: 0; }
.dashboard-title h2 { margin: 0 0 4px 0; font-size: 24px; font-weight: 700; }
.dashboard-title p { margin: 0; font-size: 14px; color: rgba(255,255,255,0.8); }
.dashboard-stats { display: flex; gap: 32px; border-left: 1px solid rgba(255,255,255,0.2); padding-left: 32px; }
.stat-col p { margin: 0 0 4px 0; font-size: 12px; color: rgba(255,255,255,0.7); }
.stat-col h3 { margin: 0; font-size: 18px; font-weight: 700; }

.status-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
.status-card { background: #fff; border: 1px solid #f3f4f6; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; flex-direction: column; align-items: center; gap: 8px; }
.status-card h3 { margin: 0; font-size: 32px; font-weight: 700; color: #111827; line-height: 1; }
.status-card-label { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: #4b5563; }
.dot { width: 8px; height: 8px; border-radius: 50%; }
.dot.yellow { background: #f59e0b; }
.dot.blue { background: #3b82f6; }
.dot.green { background: #10b981; }
.dot.red { background: #ef4444; }
.status-card-sub { font-size: 12px; color: #10b981; background: #ecfdf5; padding: 2px 8px; border-radius: 12px; font-weight: 600; }
.status-card-sub.muted { color: #9ca3af; background: transparent; font-weight: 400; padding: 0; }

.tabs { display: flex; gap: 12px; margin-bottom: 24px; width: fit-content; background: #fff; padding: 4px; border: 1px solid #f3f4f6; border-radius: 24px; }
.tabs a { padding: 8px 20px; border-radius: 20px; background: transparent; color: #6b7280; text-decoration: none; font-weight: 600; font-size: 14px; transition: all .2s; display: flex; align-items: center; gap: 8px; }
.tabs a .badge { background: #f3f4f6; color: #4b5563; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 700; }
.tabs a.active { background: #115e59; color: #fff; }
.tabs a.active .badge { background: rgba(255,255,255,0.2); color: #fff; }
.tabs a:not(.active):hover { background: #f9fafb; color: #374151; }

.tools-container { display: flex; gap: 12px; margin-bottom: 24px; }
.search-box { position: relative; flex: 1; }
.search-box input { width: 100%; height: 44px; padding: 0 16px 0 40px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; font: inherit; outline: none; transition: border-color .2s; }
.search-box input:focus { border-color: #115e59; background: #fff; }
.search-box svg { position: absolute; left: 14px; top: 14px; color: #9ca3af; width: 16px; height: 16px; }
.filter-select { height: 44px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0 32px 0 16px; background: #f9fafb url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") no-repeat right 8px center/20px; font: inherit; color: #4b5563; outline: none; cursor: pointer; appearance: none; }
.filter-select:focus { border-color: #115e59; background-color: #fff; }

.orders-list{display:flex;flex-direction:column;gap:24px;margin-bottom:30px}
.order-card{background:#fff;border-radius:12px;border:1px solid #f3f4f6;box-shadow:0 4px 6px -1px rgba(0,0,0,0.02);overflow:hidden; display: flex; flex-direction: column;}
.card-header{display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid #f9fafb;}
.order-number{color:#4b5563;font-size:14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.order-date{color:#9ca3af;font-size:13px; margin-left: 16px; border-left: 1px solid #e5e7eb; padding-left: 16px;}
.order-status{font-weight:600;font-size:12px; padding: 6px 12px; border-radius: 20px; display: flex; align-items: center; gap: 6px;}
.order-status::before { content:''; width:6px; height:6px; border-radius:50%; }
.order-status.wait{color:#d97706; background: #fffbeb;}
.order-status.wait::before { background: #d97706; }
.order-status.paid{color:#059669; background: #ecfdf5;}
.order-status.paid::before { background: #059669; }
.order-status.ship{color:#2563eb; background: #eff6ff;}
.order-status.ship::before { background: #2563eb; }
.order-status.done{color:#059669; background: #ecfdf5; border: 1px solid #d1fae5;}
.order-status.done::before { background: #059669; }
.order-status.cancel{color:#dc2626; background: #fef2f2;}
.order-status.cancel::before { background: #dc2626; }

.card-body{padding:24px;display:flex; gap: 20px; border-bottom:1px solid #f9fafb;}
.book-image{width:80px;height:115px;object-fit:cover;border-radius:6px;background:#f3f4f6;border:1px solid #e5e7eb; box-shadow: 0 2px 4px rgba(0,0,0,0.05);}
.book-details{flex:1;display:flex;flex-direction:column; justify-content: flex-start;}
.book-badges { display: flex; gap: 8px; margin-bottom: 8px; }
.badge-cat { background: #f3f4f6; color: #4b5563; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; }
.badge-cond { color: #6b7280; font-size: 11px; }
.book-title{font-weight:700;font-size:16px;color:#111827;margin-bottom:8px; line-height: 1.4;}
.buyer-info { color: #6b7280; font-size: 13px; margin-bottom: 8px; }
.book-price{color:#111827;font-weight:700;font-size:16px;}
.book-qty{color:#9ca3af;font-size:14px; font-weight: 600; align-self: flex-start;}

.card-footer{padding:16px 24px;display:flex;justify-content:space-between;align-items:center;background:#fafafa;}
.footer-left { display: flex; align-items: center; gap: 16px; font-size: 14px; color: #4b5563; }
.net-income { font-weight: 700; color: #059669; }
.footer-status-text { color: #6b7280; font-size: 13px; display: flex; align-items: center; gap: 6px; border-left: 1px solid #e5e7eb; padding-left: 16px; }
.footer-status-text.success { color: #059669; }
.card-actions{display:flex;gap:12px;}

.pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 32px; font-size: 14px; color: #6b7280; }
.page-controls { display: flex; gap: 8px; }
.page-btn { padding: 6px 12px; border: 1px solid #e5e7eb; background: #fff; border-radius: 6px; color: #374151; font-weight: 500; cursor: pointer; text-decoration: none; }
.page-btn:hover { background: #f9fafb; }
.page-btn.active { background: #115e59; color: #fff; border-color: #115e59; }

.bottom-banner { background: #0f5132; border-radius: 16px; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; margin-top: 40px; color: #fff; }
.banner-left { display: flex; align-items: center; gap: 20px; }
.shield-icon { width: 56px; height: 56px; background: rgba(255,255,255,0.1); border-radius: 12px; display: grid; place-items: center; flex-shrink: 0; }
.banner-text h3 { margin: 0 0 6px 0; font-size: 18px; font-weight: 700; }
.banner-text p { margin: 0; font-size: 14px; color: rgba(255,255,255,0.8); line-height: 1.5; max-width: 600px; }
.banner-btn { background: #fff; color: #0f5132; padding: 12px 24px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 14px; transition: background .2s; white-space: nowrap; }
.banner-btn:hover { background: #f3f4f6; }
.empty{text-align:center;padding:80px;color:#9ca3af; background: #fff; border-radius: 12px; border: 1px dashed #d1d5db; font-weight: 500;}

/* Modal styles omitted for brevity, keeping them mostly intact but simplified if needed. We'll use SweetAlert for simple alerts if possible, but keeping original modals is safer */
.modal{position:fixed;inset:0;background:rgba(17,24,39,0.5);display:none;align-items:center;justify-content:center;padding:20px;z-index:9999; backdrop-filter: blur(4px);}
.modal.open{display:flex}.modal-box{width:min(900px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); font-family: inherit;}
.modal-title{margin-top:0;color:#111827;border-bottom:1px solid #f3f4f6;padding-bottom:16px; font-size: 20px; font-weight: 700;}
.field{margin:16px 0}.field label{display:block;font-weight:600;color:#374151;margin-bottom:8px; font-size: 14px;}
.field input, .field select{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font:inherit; outline:none;}
.field input:focus, .field select:focus { border-color: #115e59; }
.modal-actions{display:flex;gap:12px;margin-top:24px; justify-content: flex-end;}
.notice{padding:14px 18px;border-radius:8px;margin-bottom:24px; font-weight: 500;}
.ok{background:#ecfdf5;color:#065f46; border: 1px solid #a7f3d0;}
.bad{background:#fef2f2;color:#991b1b; border: 1px solid #fecaca;}

@media(max-width:1050px){
  .manage{padding:30px 24px}
  .dashboard-card{flex-direction:column;gap:24px;align-items:flex-start}
  .dashboard-stats{border-left:none;padding-left:0;border-top:1px solid rgba(255,255,255,0.2);padding-top:24px;width:100%;justify-content:space-between}
}
@media(max-width:900px){
  .manage .title{font-size:24px}
  .manage .subtitle{font-size:13px}
  .manage .btn-outline,.manage .btn-primary{padding:9px 14px;font-size:13px;gap:5px}
  .dashboard-number{font-size:48px}
  .dashboard-title h2{font-size:20px}
  .dashboard-title p{font-size:13px}
  .stat-col h3{font-size:16px}
  .manage .tabs{overflow-x:auto;-webkit-overflow-scrolling:touch;flex-wrap:nowrap;width:100%;white-space:nowrap;scrollbar-width:none}
  .manage .tabs::-webkit-scrollbar{display:none}
  .manage .tabs a{padding:7px 14px;font-size:12px;white-space:nowrap;flex-shrink:0}
  .tools-container{gap:8px}
  .search-box input{height:38px;font-size:13px;padding:0 12px 0 34px}
  .search-box svg{top:11px;width:14px;height:14px}
  .filter-select{height:38px;font-size:13px;padding:0 26px 0 11px}
}
@media(max-width:800px){
  .account{display:block}
  .profile-sidebar{display:none!important}
  .manage{padding:12px 13px 60px;max-width:100%}
  /* Header — scoped to .manage to avoid conflicting with site header */
  .manage .header-area{flex-direction:column;gap:10px;margin-bottom:12px}
  .manage .header-text{max-width:100%}
  .manage .title{font-size:17px!important;margin-bottom:3px;font-weight:700}
  .manage .subtitle{display:none}
  .manage .header-actions{width:100%;gap:8px}
  .manage .header-actions .btn-outline,.manage .header-actions .btn-primary{flex:1;justify-content:center;font-size:12px;padding:8px 10px;gap:5px}
  /* Dashboard card */
  .dashboard-card{padding:14px 16px;margin-bottom:16px;border-radius:12px;flex-direction:column;gap:14px;align-items:flex-start}
  .dashboard-left{gap:14px}
  .dashboard-number{font-size:36px}
  .dashboard-title h2{font-size:16px;margin-bottom:2px}
  .dashboard-title p{font-size:12px}
  .dashboard-stats{border-left:none;padding-left:0;border-top:1px solid rgba(255,255,255,0.2);padding-top:12px;width:100%;justify-content:space-between;gap:16px}
  .stat-col p{font-size:11px}
  .stat-col h3{font-size:15px}
  /* Status cards */
  .status-cards{grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:16px}
  .status-card{padding:14px;border-radius:10px}
  .status-card h3{font-size:24px}
  .status-card-label{font-size:12px;gap:5px}
  .dot{width:7px;height:7px}
  .status-card-sub{font-size:11px}
  /* Tabs */
  .tabs{overflow-x:auto;-webkit-overflow-scrolling:touch;flex-wrap:nowrap;width:100%;margin-bottom:14px;padding:3px;border-radius:20px}
  .tabs a{padding:7px 11px;font-size:12px;white-space:nowrap;flex-shrink:0;gap:5px}
  .tabs a .badge{font-size:10px;padding:1px 5px}
  /* Tools / Filter */
  .tools-container{flex-direction:column;gap:7px;margin-bottom:14px}
  .search-box input{height:34px!important;font-size:11px!important;padding:0 12px 0 32px!important;border-radius:16px!important}
  .search-box svg{top:9px!important;left:10px!important;width:13px!important;height:13px!important}
  .filter-select{width:100%;height:34px!important;font-size:11px!important;padding:0 26px 0 10px!important;border-radius:8px!important}
  /* Order cards */
  .orders-list{gap:14px;margin-bottom:20px}
  .order-card{border-radius:10px}
  .card-header{padding:11px 14px;flex-wrap:wrap;gap:6px}
  .order-number{font-size:12px;gap:5px}
  .order-date{font-size:11px;margin-left:8px;padding-left:8px}
  .order-status{font-size:11px;padding:4px 9px}
  .card-body{padding:14px;gap:12px}
  .book-image{width:66px;height:95px;border-radius:5px}
  .book-title{font-size:14px;margin-bottom:5px}
  .buyer-info{font-size:12px;margin-bottom:5px}
  .book-price{font-size:14px}
  .book-qty{font-size:12px}
  .badge-cat{font-size:10px;padding:2px 6px}
  .card-footer{padding:10px 14px;flex-direction:column;gap:8px;align-items:flex-start}
  .footer-left{font-size:12px;gap:10px}
  .footer-status-text{font-size:11px;padding-left:10px}
  .card-actions{width:100%;gap:8px}
  .card-actions .btn-outline,.card-actions .btn-primary{flex:1;justify-content:center;font-size:12px;padding:7px 10px;min-height:36px}
  /* Banner */
  .bottom-banner{padding:16px;border-radius:12px;margin-top:24px;flex-direction:column;gap:14px}
  .banner-left{flex-direction:column;text-align:center;align-items:center}
  .shield-icon{width:40px;height:40px;border-radius:8px}
  .banner-text h3{font-size:14px}
  .banner-text p{font-size:12px}
  .banner-btn{width:100%;text-align:center;padding:10px 16px;font-size:13px;border-radius:8px}
}
@media(max-width:480px){
  .manage{padding:10px 11px 60px}
  .title{font-size:16px!important}
  .dashboard-number{font-size:30px}
  .status-cards{gap:8px}
  .status-card{padding:12px}
  .status-card h3{font-size:20px}
  .card-header{flex-direction:column;align-items:flex-start}
  .order-date{margin-left:0;border-left:none;padding-left:0}
  .book-image{width:58px;height:84px}
  .book-title{font-size:13px}
  .tabs a{padding:6px 9px;font-size:11px}
}

</style>
</head>
<body>
<?php require __DIR__.'/../components/header.php';?>
<div class="account">
    <?php require __DIR__.'/../components/profile_sidebar.php';?>
    <main class="manage">
        
        <div class="header-area">
            <div class="header-text">
                <h1 class="title">ตลาดซื้อขายหนังสือ</h1>
                <p class="subtitle">ตรวจสอบและติดตามสถานะคำสั่งซื้อ ประวัติการขาย และการจัดส่งหนังสือทั้งหมดในบัญชีของคุณ</p>
            </div>
            <div class="header-actions">
                <div class="export-wrap" id="exportWrap">
                    <button type="button" class="btn-export-market" id="exportBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        ส่งออกรายงาน
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="export-menu" id="exportMenu">
                        <a href="#" onclick="doExport('orders');return false;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            ประวัติคำสั่งซื้อ (.xlsx)
                        </a>
                        <a href="#" onclick="doExport('stock');return false;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                            รายการหนังสือที่ลงขาย (.xlsx)
                        </a>
                        <hr>
                        <a href="#" onclick="doExport('summary');return false;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            สรุปยอดขายรายเดือน (.xlsx)
                        </a>
                    </div>
                </div>
                <a href="/pages/sell_used_book.php" class="btn-primary">
                    + ลงขายหนังสือเล่มใหม่
                </a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-left">
                <h2 class="dashboard-number"><?=number_format((int)($stat['total']??0))?></h2>
                <div class="dashboard-title">
                    <h2>คำสั่งซื้อทั้งหมด</h2>
                    <p>รวมทุกสถานะ ทั้งที่จัดส่งสำเร็จ และยกเลิกในระบบ</p>
                </div>
            </div>
            <div class="dashboard-stats">
                <div class="stat-col">
                    <p>มูลค่าคำสั่งซื้อรวม</p>
                    <h3>฿<?=number_format($netRevenue, 2)?></h3>
                </div>
                <div class="stat-col">
                    <p>อัตราจัดส่งตรงเวลา</p>
                    <h3>100%</h3>
                </div>
                <div class="stat-col">
                    <p>คะแนนความพึงพอใจ</p>
                    <h3>4.9 / 5.0</h3>
                </div>
            </div>
        </div>

        <div class="status-cards">
            <div class="status-card">
                <h3><?=number_format((int)($stat['paid']??0))?></h3>
                <div class="status-card-label"><div class="dot yellow"></div> รอจัดส่ง</div>
                <div class="status-card-sub muted">ต้องจัดส่งภายใน 48 ชม.</div>
            </div>
            <div class="status-card">
                <h3><?=number_format((int)($stat['shipping']??0))?></h3>
                <div class="status-card-label"><div class="dot blue"></div> กำลังจัดส่ง</div>
                <div class="status-card-sub muted">พัสดุอยู่ในระบบขนส่ง</div>
            </div>
            <div class="status-card">
                <h3><?=number_format((int)($stat['completed']??0))?></h3>
                <div class="status-card-label"><div class="dot green"></div> สำเร็จแล้ว</div>
                <div class="status-card-sub">โอนเงินเข้า Wallet แล้ว</div>
            </div>
            <div class="status-card">
                <h3><?=number_format((int)($stat['cancelled']??0))?></h3>
                <div class="status-card-label"><div class="dot red"></div> ยกเลิก/คืนสินค้า</div>
                <div class="status-card-sub muted">ปิดรายการเรียบร้อย</div>
            </div>
        </div>

        <?php if($message):?><div class="notice ok"><?=htmlspecialchars($message)?></div><?php endif;?>
        <?php if($error):?><div class="notice bad"><?=htmlspecialchars($error)?></div><?php endif;?>

        <form>
            <div class="tabs">
                <a class="<?=$tab==='all'?'active':''?>" href="?tab=all">ทั้งหมด <span class="badge"><?=number_format((int)($stat['total']??0))?></span></a>
                <a class="<?=$tab==='packing'?'active':''?>" href="?tab=packing">รอจัดส่ง <span class="badge"><?=number_format((int)($stat['paid']??0))?></span></a>
                <a class="<?=$tab==='shipped'?'active':''?>" href="?tab=shipped">กำลังจัดส่ง <span class="badge"><?=number_format((int)($stat['shipping']??0))?></span></a>
                <a class="<?=$tab==='done'?'active':''?>" href="?tab=done">สำเร็จแล้ว <span class="badge"><?=number_format((int)($stat['completed']??0))?></span></a>
                <a class="<?=$tab==='returned'?'active':''?>" href="?tab=returned">คืนสินค้า/คืนเงิน <span class="badge"><?=number_format((int)($stat['returned']??0))?></span></a>
                <a class="<?=$tab==='cancelled'?'active':''?>" href="?tab=cancelled">ยกเลิก <span class="badge"><?=number_format((int)($stat['cancelled']??0))?></span></a>
            </div>

            <div class="tools-container">
                <input type="hidden" name="tab" value="<?=$tab?>">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="ค้นหาด้วยรหัสคำสั่งซื้อ (#WN-...), ชื่อหนังสือ, หรือชื่อผู้ซื้อ..." value="<?=htmlspecialchars($search)?>">
                </div>
                <select class="filter-select" name="date" onchange="this.form.submit()">
                    <option value="">ช่วงเวลา: 30 วันล่าสุด</option>
                    <option value="all">ช่วงเวลา: ทั้งหมด</option>
                </select>
                <select class="filter-select" name="courier" onchange="this.form.submit()">
                    <option value="">ขนส่งทั้งหมด (Kerry, Flash, EMS)</option>
                </select>
            </div>
        </form>

        <?php if(!$orders):?>
            <div class="empty">ยังไม่มีคำสั่งซื้อในหมวดนี้</div>
        <?php else:?>
            <div class="orders-list">
                <?php foreach($orders as $o):
                    [$status,$class]=smStatus($o);
                    $isCancelled=($o['payment_status']==='cancelled'||in_array($o['shipping_status'],['cancelled','returned'],true));
                    $rawSubtotal=array_sum(array_map(fn($i)=>$i['price_at_purchase']*$i['quantity'],$o['items']));
                    $subtotal=$isCancelled?0:$rawSubtotal;
                    $commission=$isCancelled?0:$subtotal*0.15;
                    $shippingFee=$isCancelled?0:(float)($o['shipping_fee']??0);
                    $netIncome=$subtotal-$commission+$shippingFee;
                    $total_items=array_sum(array_column($o['items'],'quantity'));
                    
                    if($o['shipping_status']==='delivered') {
                        $footerStatus = '<span class="footer-status-text success"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> โอนเงินเข้า Wanawan Wallet เรียบร้อย</span>';
                    } elseif ($isCancelled) {
                        $footerStatus = '<span class="footer-status-text">คำสั่งซื้อถูกยกเลิกเนื่องจากเกินกำหนดชำระ</span>';
                    } elseif ($o['shipping_status']==='shipped') {
                        $footerStatus = '<span class="footer-status-text">ผู้ซื้อกำลังรอรับพัสดุ ('.htmlspecialchars($o['shipping_courier']).')</span>';
                    } else {
                        $footerStatus = '';
                    }
                ?>
                <div class="order-card">
                    <div class="card-header">
                        <div class="order-number">
                            รหัสคำสั่งซื้อ: <?=htmlspecialchars($o['order_number'])?>
                            <svg style="cursor:pointer; color:#9ca3af;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            <span class="order-date">สั่งซื้อเมื่อ <?=date('d-m-Y H:i',strtotime($o['created_at']))?></span>
                        </div>
                        <span class="order-status <?=$class?>"><?=$status?></span>
                    </div>

                    <?php foreach($o['items'] as $index=>$item):if($index>0)continue;?>
                    <div class="card-body">
                        <img class="book-image" src="<?=htmlspecialchars(smImg($item['image_cover']??null)?:'/assets/image/placeholder.png')?>" alt="">
                        <div class="book-details">
                            <div class="book-badges">
                                <span class="badge-cat"><?=htmlspecialchars($item['category']??'ทั่วไป')?></span>
                                <span class="badge-cond">สภาพ <?php $cMap=['new'=>'100','good'=>'90','fair'=>'70','old'=>'50']; echo $cMap[$item['condition_level']??'good']??'90'; ?>%</span>
                            </div>
                            <div class="book-title"><?=htmlspecialchars($item['title']??'หนังสือ')?></div>
                            <div class="buyer-info">ผู้ซื้อ: คุณ <?=htmlspecialchars($o['shipping_name']??'ไม่ระบุ')?></div>
                            <div class="book-price"><?=$isCancelled?'฿0.00':'฿'.number_format($item['price_at_purchase'],2)?></div>
                        </div>
                        <div class="book-qty">x<?=$item['quantity']?></div>
                    </div>
                    <?php endforeach;if(count($o['items'])>1):?>
                    <div style="text-align:center;padding:12px;border-bottom:1px solid #f9fafb;color:#6b7280;font-size:13px;background:#fdfdfd">ดูสินค้าเพิ่มเติมอีก <?=count($o['items'])-1?> รายการ</div>
                    <?php endif;?>

                    <div class="card-footer">
                        <div class="footer-left">
                            <span>จำนวน: <strong><?=$total_items?> ชิ้น</strong></span>
                            <span>รายได้สุทธิ: <strong class="net-income">฿<?=number_format($netIncome,2)?></strong></span>
                            <?=$footerStatus?>
                        </div>
                        <div class="card-actions">
                            <?php if($o['payment_status']==='paid'&&!in_array($o['shipping_status'],['shipped','delivered','cancelled','returned'],true)):?>
                                <button class="btn-primary ship-btn" data-order="<?=$o['order_id']?>" data-number="<?=htmlspecialchars($o['order_number'])?>">อัปโหลดข้อมูลจัดส่ง</button>
                            <?php else: ?>
                                <button class="btn-outline" onclick="openDetail(this)" data-detail='<?=htmlspecialchars(json_encode(['order'=>$o,'slip'=>smSlip($o['payment_slip_url']??null),'commission'=>$commission,'net'=>$netIncome,'subtotal'=>$subtotal,'shippingFee'=>$shippingFee,'statusText'=>$status,'statusClass'=>$class,'isCancelled'=>$isCancelled],JSON_UNESCAPED_UNICODE),ENT_QUOTES)?>'>ดูรายละเอียด</button>
                                <?php if($isCancelled): ?>
                                    <button class="btn-outline" style="color:#0f5132; border-color:#0f5132;">ติดต่อผู้ซื้อ</button>
                                <?php elseif($o['shipping_status']==='delivered'): ?>
                                    <button class="btn-outline" style="color:#0f5132; border-color:#0f5132;">ลงขายรายการใหม่อีกครั้ง</button>
                                <?php endif; ?>
                            <?php endif;?>
                        </div>
                    </div>
                </div>
                <?php endforeach;?>
            </div>

            <div class="pagination">
                <span>แสดง <?=count($orders)?> รายการจากทั้งหมด <?=count($orders)?> รายการ (หน้า 1 จาก 1)</span>
                <div class="page-controls">
                    <button class="page-btn">ก่อนหน้า</button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn">ถัดไป</button>
                </div>
            </div>
        <?php endif;?>

        <div class="bottom-banner">
            <div class="banner-left">
                <div class="shield-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                </div>
                <div class="banner-text">
                    <h3>มาตรฐานการซื้อขายปลอดภัยผ่าน Wanawan Hub Escrow</h3>
                    <p>ยอดเงินทุกรายการสั่งซื้อจะถูกพักไว้อย่างปลอดภัยที่ระบบส่วนกลาง จนกว่าผู้ซื้อจะได้รับพัสดุและตรวจสอบสภาพหนังสือตรงปก ผู้ขายจึงมั่นใจได้ว่าจัดส่งแล้วได้รับเงินแน่นอน 100% ปราศจากการฉ้อโกง</p>
                </div>
            </div>
            <a href="#" class="banner-btn">อ่านข้อกำหนดความปลอดภัย</a>
        </div>
    </main>
</div>

<!-- Kept original modals with updated styles -->
<div class="modal" id="detailModal">
    <div class="modal-box" style="width:min(600px,100%);padding:0;overflow-y:auto;overflow-x:hidden;background:#f5f5f5;scrollbar-width:none;-ms-overflow-style:none">
        <div id="dHeader" style="background:#0f5132;color:#fff;padding:24px 32px">
            <h2 style="margin:0;font-size:20px" id="dStatusText"></h2>
            <p style="margin:4px 0 0 0;opacity:0.9; font-size: 14px;" id="dStatusSub"></p>
        </div>
        <div style="background:#fff;padding:20px 32px;margin-bottom:8px;font-size:15px">
            <div style="display:flex;justify-content:space-between;margin-bottom:12px;color:#4b5563">
                <span>ยอดรวมสินค้า</span><strong id="dSubtotal" style="color:#111827"></strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:12px;color:#4b5563">
                <span>ค่าจัดส่ง</span><strong id="dShippingFee" style="color:#111827"></strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:16px;color:#4b5563">
                <span>ค่าธรรมเนียม GP 15%</span><strong id="dCommission" style="color:#dc2626"></strong>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:16px;border-top:1px solid #f3f4f6;padding-top:16px">
                <strong style="color: #111827">รายได้สุทธิ</strong><strong id="dNetIncome" style="color:#059669;font-size:18px"></strong>
            </div>
        </div>
        <div style="background:#fff;padding:20px 32px;margin-bottom:8px" id="dItems"></div>
        <div style="background:#fff;padding:20px 32px;margin-bottom:8px">
            <h3 style="margin-top:0;font-size:16px; margin-bottom: 12px; color: #111827;">ข้อมูลจัดส่ง</h3>
            <p style="color:#374151;margin-bottom:4px; font-weight: 600;" id="dBuyer"></p>
            <p style="color:#6b7280;font-size:14px" id="dAddress"></p>
        </div>
        <div style="background:#fff;padding:20px 32px;color:#6b7280;font-size:14px">
            <h3 style="margin-top:0;font-size:16px; margin-bottom: 12px; color: #111827;">ไทม์ไลน์คำสั่งซื้อ</h3>
            <div style="display:flex;justify-content:space-between;margin-bottom:12px">
                <span>รหัสคำสั่งซื้อ</span><span id="dNumberInfo" style="color: #111827; font-weight: 600;"></span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <span>สั่งซื้อเมื่อ</span><span id="dDateInfo" style="color: #111827;"></span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px" id="dShippedRow">
                <span>จัดส่งเมื่อ</span><span id="dShippedInfo" style="color: #111827;"></span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px" id="dDeliveredRow">
                <span>สำเร็จเมื่อ</span><span id="dDeliveredInfo" style="color: #111827;"></span>
            </div>
        </div>
        <div style="background:#fff;padding:20px 32px;display:flex;gap:12px;justify-content:flex-end" id="statusForms"></div>
        <div style="padding: 0 32px 32px 32px; background: #fff;">
            <button class="btn-outline" style="width: 100%; justify-content: center;" type="button" data-close>ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<div class="modal" id="shippingModal">
    <div class="modal-box" style="width:min(620px,100%)">
        <h2 class="modal-title">กรอกข้อมูลการจัดส่งสินค้า</h2>
        <form method="post" id="shippingForm">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['seller_market_csrf'])?>">
            <input type="hidden" name="order_id" id="shipOrderId">
            <input type="hidden" name="courier" id="realCourier">
            <p id="shipNumber" style="font-weight: 600; color: #4b5563; margin-bottom: 20px;"></p>
            <div class="field">
                <label>บริษัทขนส่ง</label>
                <select id="courierSelect" required onchange="const c=document.getElementById('courierCustom');if(this.value==='อื่นๆ'){c.style.display='block';c.required=true;}else{c.style.display='none';c.required=false;c.value='';}">
                    <option value="">-- เลือกบริษัทขนส่ง --</option>
                    <option value="Kerry Express">Kerry Express</option>
                    <option value="Flash Express">Flash Express</option>
                    <option value="J&T Express">J&T Express</option>
                    <option value="ไปรษณีย์ไทย (EMS)">ไปรษณีย์ไทย (EMS)</option>
                    <option value="ไปรษณีย์ไทย (ลงทะเบียน)">ไปรษณีย์ไทย (ลงทะเบียน)</option>
                    <option value="Shopee Express">Shopee Express</option>
                    <option value="อื่นๆ">อื่นๆ (ระบุเอง)</option>
                </select>
                <input type="text" id="courierCustom" placeholder="ระบุชื่อบริษัทขนส่ง" style="display:none; margin-top: 10px;">
            </div>
            <div class="field">
                <label>เลขพัสดุ (Tracking Number)</label>
                <input name="tracking_number" required maxlength="100">
            </div>
            <div class="modal-actions">
                <button class="btn-outline" type="button" data-close>ยกเลิก</button>
                <button class="btn-primary" name="save_tracking" onclick="document.getElementById('realCourier').value=(document.getElementById('courierSelect').value==='อื่นๆ'?document.getElementById('courierCustom').value.trim():document.getElementById('courierSelect').value)">บันทึกและแจ้งผู้ซื้อ</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__.'/../components/footer.php';?>

<script>
const csrf=<?=json_encode($_SESSION['seller_market_csrf'])?>,detailModal=document.getElementById('detailModal'),shippingModal=document.getElementById('shippingModal');
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
function openDetail(el){
    const d=JSON.parse(el.dataset.detail),o=d.order;
    const h=document.getElementById("dHeader");
    document.getElementById("dStatusText").textContent=d.statusText;
    document.getElementById("dStatusSub").textContent=o.shipping_status==="delivered"?"ผู้ซื้อยืนยันได้รับสินค้า":"กรุณาตรวจสอบข้อมูล";
    h.style.background=d.statusClass==="done"?"#059669":d.statusClass==="wait"?"#d97706":d.statusClass==="paid"?"#059669":d.statusClass==="ship"?"#2563eb":"#dc2626";
    document.getElementById("dSubtotal").textContent="฿"+Number(d.subtotal).toLocaleString(undefined,{minimumFractionDigits:2});
    document.getElementById("dShippingFee").textContent="฿"+Number(d.shippingFee??0).toLocaleString(undefined,{minimumFractionDigits:2});
    document.getElementById("dCommission").textContent="-฿"+Number(d.commission).toLocaleString(undefined,{minimumFractionDigits:2});
    document.getElementById("dNetIncome").textContent="฿"+Number(d.net).toLocaleString(undefined,{minimumFractionDigits:2});
    document.getElementById("dNumberInfo").textContent=o.order_number;
    document.getElementById("dDateInfo").textContent=o.created_at;
    
    document.getElementById("dShippedInfo").textContent=o.shipped_at || "-";
    document.getElementById("dShippedRow").style.display=o.shipped_at ? "flex" : "none";
    document.getElementById("dDeliveredInfo").textContent=o.delivered_at || "-";
    document.getElementById("dDeliveredRow").style.display=o.delivered_at ? "flex" : "none";
    if(o.payment_status==='paid' || ['shipped','delivered'].includes(o.shipping_status)){
        document.getElementById("dBuyer").textContent=o.shipping_name+" · "+o.shipping_phone;
        document.getElementById("dAddress").textContent=o.shipping_address;
    }else{
        document.getElementById("dBuyer").textContent="รอแอดมินตรวจสอบการชำระเงิน";
        document.getElementById("dAddress").textContent="(ที่อยู่จัดส่งจะแสดงหลังจากแอดมินอนุมัติสลิปแล้ว)";
    }
    document.getElementById("dItems").innerHTML=o.items.map(i=>`<div style="display:flex;gap:16px;margin-bottom:16px;align-items:center; border: 1px solid #f3f4f6; padding: 12px; border-radius: 8px;"><img src="${i.image_cover?(i.image_cover.startsWith('uploads')?'/pages/'+i.image_cover:'/assets/image/books/'+i.image_cover):'/assets/image/placeholder.png'}" style="width:54px;height:76px;object-fit:cover;border-radius:6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);"><div style="flex:1"><div style="font-weight:700;color:#111827;font-size:14px; margin-bottom: 4px;">${esc(i.title)}</div><div style="color:#6b7280;font-size:13px">x${i.quantity}</div></div><strong style="color:${d.isCancelled?'#9ca3af':'#111827'}; font-size: 15px;">${d.isCancelled?'฿0.00':'฿'+Number(i.price_at_purchase).toLocaleString(undefined,{minimumFractionDigits:2})}</strong></div>`).join("");
    
    let buttons="";
    if(o.shipping_status==="shipped")buttons+=form(o.order_id,"delivered","จัดส่งสำเร็จ","btn-primary");
    if(!["delivered","cancelled","returned"].includes(o.shipping_status))buttons+=form(o.order_id,"cancelled","ยกเลิกคำสั่งซื้อ","btn-outline");
    document.getElementById("statusForms").innerHTML=buttons;
    detailModal.classList.add("open");
}
function form(id,status,label,cls=''){return `<form method="post" style="display:inline-block;"><input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="order_id" value="${id}"><input type="hidden" name="status" value="${status}"><button class="${cls}" name="update_status">${label}</button></form>`}
document.querySelectorAll('.ship-btn').forEach(btn=>btn.addEventListener('click',(e)=>{e.stopPropagation(); document.getElementById('shipOrderId').value=btn.dataset.order;document.getElementById('shipNumber').textContent='คำสั่งซื้อ #'+btn.dataset.number;shippingModal.classList.add('open')}));
document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>b.closest('.modal').classList.remove('open')));document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')}));

// ─── Export Dropdown & Animated Loading ────────────────────────────────────
const exportWrap = document.getElementById('exportWrap');
const exportBtn  = document.getElementById('exportBtn');
if (exportBtn && exportWrap) {
    exportBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (exportBtn.disabled) return;
        exportWrap.classList.toggle('open');
    });
    document.addEventListener('click', () => exportWrap.classList.remove('open'));
    document.getElementById('exportMenu')?.addEventListener('click', (e) => e.stopPropagation());
}

async function doExport(type, extraParams) {
    if (!exportBtn || exportBtn.disabled) return;
    exportWrap?.classList.remove('open');

    const originalHtml = exportBtn.innerHTML;
    exportBtn.disabled = true;
    exportBtn.classList.add('loading');
    exportBtn.innerHTML = `
        <svg class="spin-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
        กำลังเตรียมชีท...
    `;

    try {
        const url = '/pages/dashboard_export.php?type=' + type + (extraParams || '');
        const res = await fetch(url);
        if (!res.ok) throw new Error('Export request failed: ' + res.status);

        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^";]+)"?/);
        const filename = match ? match[1] : `${type}_export.xlsx`;

        const blob = await res.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
            document.body.removeChild(a);
            window.URL.revokeObjectURL(downloadUrl);
        }, 1500);

        exportBtn.classList.remove('loading');
        exportBtn.classList.add('success');
        exportBtn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            ดาวน์โหลดสำเร็จ!
        `;
        setTimeout(() => {
            exportBtn.innerHTML = originalHtml;
            exportBtn.disabled = false;
            exportBtn.classList.remove('success');
        }, 2000);
    } catch (err) {
        console.error('Export error:', err);
        alert('เกิดข้อผิดพลาดในการสร้างไฟล์รายงาน Excel กรุณาลองใหม่อีกครั้ง');
        exportBtn.innerHTML = originalHtml;
        exportBtn.disabled = false;
        exportBtn.classList.remove('loading', 'success');
    }
}
</script>
</body>
</html>
