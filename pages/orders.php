<?php
session_start();
if(empty($_SESSION['user_id'])){header('Location:/pages/login.php');exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/order_reservations.php';
$userId=(int)$_SESSION['user_id'];
wanawanExpirePendingOrders($pdo,$userId);

if($_SERVER['REQUEST_METHOD']==='POST'){
 $orderId=(int)($_POST['order_id']??0);
 $stmt=$pdo->prepare('SELECT * FROM orders WHERE order_id=? AND user_id=?');
 $stmt->execute([$orderId,$userId]);
 $target=$stmt->fetch(PDO::FETCH_ASSOC);
 
 if($target&&isset($_POST['cancel'])&&$target['payment_status']==='pending'){
     if(!wanawanCancelReservedOrder($pdo,$orderId,$userId))
         $pdo->prepare("UPDATE orders SET payment_status='cancelled',shipping_status='cancelled',cancellation_reason='ผู้ซื้อเป็นผู้ยกเลิกคำสั่งซื้อ' WHERE order_id=?")->execute([$orderId]);
     wanawanNotifyUser($pdo, $userId, 'ยกเลิกคำสั่งซื้อแล้ว', 'คำสั่งซื้อ #'.$target['order_number'].' ถูกยกเลิกและคืนสินค้าที่สำรองแล้ว', 'order');
 }
 
 if($target&&isset($_POST['buy_again'])){
     $items=$pdo->prepare('SELECT book_id,quantity FROM order_items WHERE order_id=? AND book_id IS NOT NULL');
     $items->execute([$orderId]);
     $add=$pdo->prepare('INSERT INTO cart_items(user_id,book_id,quantity) VALUES(?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)');
     foreach($items as $i)$add->execute([$userId,$i['book_id'],$i['quantity']]);
     header('Location:/pages/cart.php');exit;
 }
 
 if($target&&isset($_POST['confirm_received'])&&$target['shipping_status']==='shipped'){
  $pdo->beginTransaction();
  try{
   $pdo->prepare("UPDATE orders SET shipping_status='delivered',delivered_at=NOW() WHERE order_id=?")->execute([$orderId]);
   
   $shopIdQuery = $pdo->prepare("SELECT b.seller_type, b.seller_id FROM order_items oi JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=? AND b.seller_type IN ('shop','publisher','user') LIMIT 1");
   $shopIdQuery->execute([$orderId]);
   $seller = $shopIdQuery->fetch(PDO::FETCH_ASSOC);
   
   if ($seller) {
       $sellerType = $seller['seller_type'];
       $sellerId = $seller['seller_id'];
       
       $subtotal = array_sum(array_map(function($i){ return (float)($i['price_at_purchase']*$i['quantity']); }, $pdo->query("SELECT price_at_purchase, quantity FROM order_items WHERE order_id=$orderId")->fetchAll(PDO::FETCH_ASSOC)));
       $shippingFee = (float)($target['shipping_fee'] ?? 0);
       
       $gpRate = ($sellerType === 'user') ? 0.15 : 0.10;
       $gpAmount = $subtotal * $gpRate;
       $netPayout = ($subtotal - $gpAmount) + $shippingFee;

       $pdo->prepare("UPDATE orders SET gp_amount=?, net_payout=? WHERE order_id=?")->execute([$gpAmount, $netPayout, $orderId]);

       $walletQuery = $pdo->prepare("SELECT wallet_id FROM vendor_wallets WHERE seller_type=? AND seller_id=? FOR UPDATE");
       $walletQuery->execute([$sellerType, $sellerId]);
       $wallet = $walletQuery->fetch(PDO::FETCH_ASSOC);
       
       if (!$wallet) {
           $pdo->prepare("INSERT INTO vendor_wallets (seller_type, seller_id, available_balance) VALUES (?, ?, ?)")->execute([$sellerType, $sellerId, $netPayout]);
           $walletId = $pdo->lastInsertId();
       } else {
           $walletId = $wallet['wallet_id'];
           $pdo->prepare("UPDATE vendor_wallets SET available_balance = available_balance + ? WHERE wallet_id=?")->execute([$netPayout, $walletId]);
       }

       $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, order_id, type, amount, status) VALUES (?, ?, 'EARNING', ?, 'SUCCESS')")->execute([$walletId, $orderId, $netPayout]);
   }
   
   wanawanNotifyUser($pdo, $userId, 'ยืนยันการรับสินค้าแล้ว', 'คำสั่งซื้อ #'.$target['order_number'].' ยืนยันรับสินค้าแล้ว (เงินโอนเข้ากระเป๋าร้านค้าแล้ว)', 'order');
   $pdo->commit();
  }catch(Throwable $e){$pdo->rollBack();}
 }
 header('Location:/pages/orders.php?tab='.urlencode($_GET['tab']??'all'));exit;
}

// Counts for Tabs
$counts = ['all'=>0, 'pending'=>0, 'shipping'=>0, 'completed'=>0, 'returned'=>0, 'cancelled'=>0];
$allOrdersStmt = $pdo->prepare("SELECT payment_status, shipping_status FROM orders WHERE user_id=?");
$allOrdersStmt->execute([$userId]);
foreach($allOrdersStmt as $row) {
    $counts['all']++;
    if ($row['shipping_status'] === 'returned') {
        $counts['returned']++;
    } elseif ($row['payment_status'] === 'cancelled' || $row['shipping_status'] === 'cancelled') {
        $counts['cancelled']++;
    } elseif ($row['shipping_status'] === 'delivered') {
        $counts['completed']++;
    } elseif ($row['payment_status'] === 'pending') {
        $counts['pending']++;
    } elseif ($row['payment_status'] === 'paid' && in_array($row['shipping_status'], ['pending','packing','shipped'])) {
        $counts['shipping']++;
    }
}

$tab=$_GET['tab']??'all';
$q=trim($_GET['q']??'');
$q=ltrim($q, '#');
$where=['o.user_id=:uid'];
$params=[':uid'=>$userId];

if($tab==='pending') $where[]="o.payment_status='pending'";
elseif($tab==='shipping') $where[]="o.payment_status='paid' AND o.shipping_status IN ('pending','packing','shipped')";
elseif($tab==='completed') $where[]="o.shipping_status='delivered'";
elseif($tab==='cancelled') $where[]="(o.payment_status='cancelled' OR o.shipping_status='cancelled')";
elseif($tab==='returned') $where[]="o.shipping_status='returned'";

if($q!==''){
    $where[]='(o.order_number LIKE :q_order OR EXISTS(SELECT 1 FROM order_items sx JOIN books bx ON bx.book_id=sx.book_id WHERE sx.order_id=o.order_id AND bx.title LIKE :q_title))';
    $params[':q_order']='%'.$q.'%';
    $params[':q_title']='%'.$q.'%';
}

$sql='SELECT o.* FROM orders o WHERE '.implode(' AND ',$where).' ORDER BY o.created_at DESC';
$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$orderRows=$stmt->fetchAll(PDO::FETCH_ASSOC);

$orders=[];
$itemStmt=$pdo->prepare("SELECT oi.*, b.title, b.author, b.image_cover, b.seller_type, b.seller_id, r.review_id, COALESCE(p.company_name,s.shop_name,NULLIF(u.display_name,''),u.email,'ร้านวนวรรณ') seller_name FROM order_items oi LEFT JOIN books b ON b.book_id=oi.book_id LEFT JOIN book_reviews r ON r.order_item_id=oi.order_item_id AND r.user_id=:review_user LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id LEFT JOIN users u ON b.seller_type='user' AND u.user_id=b.seller_id WHERE oi.order_id=:order_id");
foreach($orderRows as $o){
    $itemStmt->execute([':review_user'=>$userId,':order_id'=>$o['order_id']]);
    $o['items']=$itemStmt->fetchAll(PDO::FETCH_ASSOC);
    $orders[]=$o;
}

function orderImage(?string $v):?string{
    if(!$v)return null;
    if(preg_match('#^https?://#i',$v))return $v;
    $v=ltrim(str_replace('\\','/',$v),'/');
    return str_starts_with($v,'uploads/')?'/pages/'.$v:'/assets/image/books/'.$v;
}

function orderSellerName(array $item):string{
    return wanawanSellerName((string)($item['seller_name']??'ร้านวนวรรณ'),(string)($item['seller_type']??'user'));
}

function orderState(array $o):array{
    if($o['shipping_status']==='returned') return ['คืนสินค้า/คืนเงิน','cancelled'];
    if($o['payment_status']==='cancelled'||$o['shipping_status']==='cancelled') return ['ยกเลิกแล้ว','cancelled'];
    if($o['shipping_status']==='delivered') return ['สำเร็จแล้ว','completed'];
    if($o['payment_status']==='pending') return ['รอการชำระ','pending'];
    if($o['shipping_status']==='shipped') return ['กำลังจัดส่ง','shipping'];
    if($o['shipping_status']==='packing') return ['กำลังเตรียมจัดส่ง','shipping'];
    return ['ชำระแล้ว','shipping'];
}

function formatThaiDate($datetime) {
    $ts = strtotime($datetime);
    $months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return date('d', $ts) . ' ' . $months[date('n', $ts)] . ' ' . (date('Y', $ts)) . ' ' . date('H:i', $ts) . ' น.';
}

$profile_active='orders';

// ดึง return_request ที่มีอยู่แล้วของผู้ใช้ (สำหรับแสดง/ซ่อนปุ่มคืนสินค้า)
$existingReturnsMap = [];
if ($orders) {
    $orderIds = [];
    foreach ($orders as $__o) {
        if (!empty($__o['order_id'])) $orderIds[] = (int)$__o['order_id'];
    }
    if ($orderIds) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        try {
            $retStmt = $pdo->prepare("SELECT order_id, status FROM return_requests WHERE order_id IN ($placeholders) AND user_id = ?");
            $retStmt->execute(array_merge($orderIds, [$userId]));
            foreach ($retStmt->fetchAll(PDO::FETCH_ASSOC) as $ret) {
                $existingReturnsMap[(int)$ret['order_id']] = $ret['status'];
            }
        } catch (Throwable $e) { /* ตาราง return_requests ยังไม่มี migration ก็ไม่ crash */ }
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>รายการคำสั่งซื้อ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        *{box-sizing:border-box}
        body { background: #f9f9f9; }
        .account{display:flex;width:100%;min-height:900px}
        .orders-content{flex:1;max-width:1160px;margin:0 auto;padding:40px 50px 90px;min-width:0; background: #f9f9f9;}
        
        .header-area { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 20px;}
        .title{font-size:32px;color:#111827;margin:0 0 8px; font-weight:700;}
        .subtitle{margin:0;color:#4b5563; font-size: 14px; max-width: 600px; line-height: 1.6;}
        
        .header-actions { display: flex; gap: 12px; }
        .btn-action-outline { border: 1px solid #d1d5db; background: #fff; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; color: #374151; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-family: inherit;}
        .btn-action-outline:hover { background: #f3f4f6; }
        .btn-action-solid { border: 1px solid #115e59; background: #115e59; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; color: #fff; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-family: inherit;}
        .btn-action-solid:hover { background: #0f5132; }
        
        .tabs{display:flex;border-bottom:1px solid #e5e7eb;margin-bottom:24px;overflow-x:auto; gap: 32px; padding-bottom: 2px;}
        .tabs a{display:flex;align-items:center;gap:8px;padding:12px 0;text-align:center;color:#6b7280;text-decoration:none;font-weight:600;font-size:15px;border-bottom:3px solid transparent; white-space: nowrap;}
        .tabs a.active{color:#115e59;border-color:#115e59}
        .tab-count { background: #f3f4f6; color: #4b5563; border-radius: 20px; padding: 2px 10px; font-size: 12px; font-weight: 700; }
        .tabs a.active .tab-count { background: #ccfbf1; color: #115e59; }
        .tabs a:hover:not(.active) { color: #374151; }
        
        .filters-area { display: flex; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;}
        .search-box { position: relative; width: 360px; max-width: 100%; }
        .search-box input { width: 100%; height: 42px; border: 1px solid #d1d5db; border-radius: 22px; padding: 0 20px 0 42px; font: inherit; font-size: 14px; outline: none; }
        .search-box input:focus { border-color: #115e59; }
        .search-box svg { position: absolute; left: 16px; top: 13px; color: #9ca3af; }
        
        .filter-dropdowns { display: flex; gap: 12px; }
        .dropdown { height: 42px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; padding: 0 16px; font: inherit; font-size: 14px; color: #374151; display: inline-flex; align-items: center; cursor: pointer; outline: none;}
        
        .order-card{border:1px solid #e5e7eb;border-radius:16px;margin-bottom:24px;background:#fff;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.02); transition: box-shadow 0.2s; cursor: pointer;}
        .order-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,.05); }
        
        .order-head{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-bottom:1px solid #f3f4f6; background: #fcfcfc;}
        .seller-info { display: flex; align-items: center; gap: 12px; }
        .store-icon { width: 32px; height: 32px; border-radius: 8px; background: #fee2e2; color: #dc2626; display: grid; place-items: center; }
        .store-icon.shop { background: #e0f2fe; color: #0284c7; }
        .store-icon.publisher { background: #fce7f3; color: #db2777; }
        .seller-name { font-size: 15px; font-weight: 700; color: #111827; }
        .seller-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 12px; background: #f3f4f6; color: #4b5563; }
        .seller-badge.official { background: #dcfce7; color: #166534; }
        
        .badge{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700; display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent;}
        .badge.pending{background:#fffbeb;color:#d97706; border-color: #fef3c7;}
        .badge.cancelled{background:#fef2f2;color:#dc2626; border-color: #fee2e2;}
        .badge.shipping{background:#eff6ff;color:#2563eb; border-color: #dbeafe;}
        .badge.completed{background:#ecfdf5;color:#059669; border-color: #d1fae5;}
        
        .order-body { padding: 24px; display: flex; justify-content: space-between; border-bottom: 1px solid #f3f4f6; gap: 24px;}
        @media(max-width: 768px) { .order-body { flex-direction: column; } }
        
        .book-info { display: flex; gap: 20px; flex: 1;}
        .cover{width:90px;height:120px;border-radius:8px;background:#f3f4f6;overflow:hidden; flex-shrink:0; border: 1px solid #e5e7eb;}
        .cover img{width:100%;height:100%;object-fit:cover}
        .details h3{margin:0 0 6px; font-size: 16px; color: #111827;}
        .details p.author{margin:0 0 6px;color:#6b7280; font-size: 14px;}
        .details p.qty{margin:0 0 12px;color:#4b5563; font-size: 14px;}
        .edition-badge { background: #f3f4f6; color: #4b5563; font-size: 12px; padding: 4px 10px; border-radius: 6px; display: inline-block; font-weight: 500;}
        
        .success-text { display: flex; align-items: center; gap: 6px; color: #059669; font-size: 13px; font-weight: 600; margin-top: 12px;}
        
        .price-info { text-align: right; display: flex; flex-direction: column; justify-content: center; min-width: 120px;}
        @media(max-width: 768px) { .price-info { text-align: left; } }
        .price-label { font-size: 13px; color: #6b7280; margin-bottom: 4px;}
        .price-amount { font-size: 24px; font-weight: 700; color: #115e59; }
        
        .cancel-alert { background: #fef2f2; border-bottom: 1px solid #fee2e2; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #dc2626;}
        .alert-content { display: flex; align-items: center; gap: 8px; }
        .alert-note { color: #9ca3af; font-size: 12px; }
        
        .order-foot{display:flex;align-items:center;justify-content:space-between;padding:16px 24px; background: #fcfcfc;}
        @media(max-width: 768px) { .order-foot { flex-direction: column; align-items: flex-start; gap: 16px; } }
        .foot-info { display: flex; align-items: center; gap: 12px; font-size: 13px; color: #6b7280; flex-wrap: wrap;}
        .foot-info strong { color: #111827; }
        .copy-btn { background: transparent; border: none; color: #115e59; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-family: inherit; font-size: 13px; font-weight: 600; padding: 0;}
        .copy-btn:hover { text-decoration: underline; }
        .divider { color: #d1d5db; }
        
        .foot-actions{display:flex;gap:12px;flex-wrap:wrap;}
        .btn-outline { border: 1px solid #d1d5db; background: #fff; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #374151; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-family: inherit; transition: all 0.2s;}
        .btn-outline:hover { background: #f9fafb; border-color: #9ca3af; }
        .btn-outline.review { border-color: #fde047; color: #854d0e; background: #fefce8; }
        .btn-outline.review:hover { background: #fef9c3; border-color: #facc15; }
        .btn-solid { border: 1px solid #115e59; background: #115e59; padding: 8px 24px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #fff; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-family: inherit; transition: all 0.2s;}
        .btn-solid:hover { background: #0f5132; }
        .btn-cancel { border: 1px solid #fee2e2; background: #fef2f2; color: #dc2626; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit;}
        .btn-cancel:hover { background: #fee2e2; }

        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 12px 0 40px; font-size: 13px; color: #6b7280; }
        .page-controls { display: flex; gap: 8px; }
        .page-btn { padding: 6px 12px; border: 1px solid #e5e7eb; background: #fff; border-radius: 6px; color: #374151; font-weight: 500; cursor: pointer; font-family: inherit;}
        .page-btn.active { background: #115e59; color: #fff; border-color: #115e59; }

        .bottom-banner { background: #115e59; border-radius: 16px; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; color: #fff; gap: 24px;}
        @media(max-width: 768px) { .bottom-banner { flex-direction: column; align-items: flex-start; } }
        .banner-content { display: flex; gap: 20px; align-items: flex-start; }
        .shield-icon { width: 48px; height: 48px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; display: grid; place-items: center; flex-shrink: 0; }
        .banner-text h3 { margin: 0 0 8px 0; font-size: 16px; font-weight: 700; }
        .banner-text p { margin: 0; font-size: 13px; opacity: 0.9; line-height: 1.6; max-width: 700px;}
        .btn-banner { background: #fff; color: #115e59; border: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; white-space: nowrap; font-family: inherit;}
        .btn-banner:hover { background: #f3f4f6; }
        
        .empty { text-align: center; padding: 80px 20px; color: #6b7280; background: #fff; border-radius: 16px; border: 1px dashed #d1d5db; margin-bottom: 24px;}
        
        /* Modal remains mostly the same but updated style matches */
        .od-modal{position:fixed;inset:0;background:rgba(17,24,39,0.5);display:none;align-items:center;justify-content:center;padding:20px;z-index:9999;backdrop-filter:blur(2px)}
        .od-modal.open{display:flex}
        .od-box{width:min(680px,100%);max-height:90vh;overflow-y:auto;background:#f9fafb;border-radius:20px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); font-family: inherit;}
        .od-head{padding:24px;border-radius:20px 20px 0 0;color:#fff}
        .od-head h2{margin:0;font-size:18px}
        .od-head p{margin:4px 0 0;font-size:13px;opacity:.9}
        .od-section{background:#fff;margin-bottom:8px;padding:20px 24px}
        .od-section h3{margin:0 0 16px;font-size:15px;color:#111827;border-bottom:1px solid #f3f4f6;padding-bottom:12px; display: flex; align-items: center; gap: 8px;}
        .od-row{display:flex;justify-content:space-between;padding:6px 0;font-size:14px;color:#4b5563}
        .od-row strong{color:#111827}
        .od-item{display:flex;gap:16px;align-items:center;padding:12px 0;border-bottom:1px solid #f3f4f6}
        .od-item:last-child{border:0}
        .od-item img{width:56px;height:76px;object-fit:cover;border-radius:6px;background:#f3f4f6}
        .od-item-info{flex:1}
        .od-item-title{font-weight:600;color:#111827;font-size:15px;margin:0 0 4px}
        .od-item-sub{font-size:13px;color:#6b7280}
        .od-item-price{font-weight:700;color:#115e59;white-space:nowrap}
        .od-tracking{padding:16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;color:#166534;font-size:14px;margin-bottom:8px}
        .od-footer-btns{display:flex;gap:12px;flex-wrap:wrap;padding:20px 24px;background:#fff;border-radius:0 0 20px 20px; justify-content: flex-end;}
        .od-btn{height:42px;padding:0 20px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font:inherit;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;text-decoration:none;white-space:nowrap;}
        .od-btn:hover { background: #f9fafb; }
        .od-btn.close-btn { background: #f3f4f6; border-color: #f3f4f6; color: #374151;}
        .od-btn.close-btn:hover { background: #e5e7eb; }
        .grand-total{display:flex;justify-content:space-between;padding:12px 0 4px;font-size:16px;font-weight:700;color:#115e59;border-top:1px solid #f3f4f6;margin-top:8px}

        /* ───── Responsive ───── */
        @media(max-width:1000px){
            .orders-content{padding:32px 24px 70px}
        }
        @media(max-width:900px){
            .title{font-size:26px}
            .subtitle{font-size:13px}
            .btn-action-outline,.btn-action-solid{padding:9px 13px;font-size:13px;gap:6px}
            .tabs{gap:20px}
            .tabs a{font-size:14px;padding:10px 0}
            .search-box{width:100%}
            .filters-area{flex-direction:column;gap:10px}
            .filter-dropdowns{width:100%}
            .filter-dropdowns .dropdown{flex:1}
        }
        @media(max-width:800px){
            .account{display:block}
            /* ซ่อน profile sidebar บนมือถือ (profile_sidebar.php จัดการเองอยู่แล้วที่ 1200px) */
            .profile-sidebar{display:none!important}
            .orders-content{padding:12px 14px 60px;max-width:100%}
            .title{font-size:16px!important;margin-bottom:3px;font-weight:700}
            .subtitle{display:none}
            .header-area{margin-bottom:10px;gap:6px}
            .header-actions{display:none}
            /* Tabs ปรับให้กะทัดรัด scroll ได้ */
            .tabs{gap:0;padding-bottom:0;margin-bottom:16px;-webkit-overflow-scrolling:touch}
            .tabs a{font-size:12px;gap:5px;padding:10px 12px;border-bottom-width:2.5px}
            .tab-count{padding:1px 6px;font-size:10px;border-radius:10px}
            /* Filters */
            .filters-area{margin-bottom:12px;gap:7px}
            .search-box{width:100%}
            .search-box input{height:30px!important;font-size:11px!important;padding:0 12px 0 30px!important;border-radius:16px!important;line-height:30px!important}
            .search-box svg{top:8px!important;left:10px!important;width:13px!important;height:13px!important}
            .filter-dropdowns{gap:6px}
            .dropdown{height:30px!important;font-size:11px!important;padding:0 8px!important;border-radius:8px!important;line-height:30px!important}
            /* Order Card */
            .order-card{border-radius:12px;margin-bottom:14px}
            .order-head{padding:11px 14px;gap:8px}
            .seller-info{gap:8px}
            .store-icon{width:28px;height:28px;border-radius:6px;flex-shrink:0}
            .store-icon svg{width:14px;height:14px}
            .seller-name{font-size:13px}
            .seller-badge{font-size:10px;padding:2px 6px}
            .badge{padding:5px 10px;font-size:11px;gap:4px}
            /* Body */
            .order-body{padding:12px 14px;gap:12px;flex-direction:column}
            .book-info{gap:12px}
            .cover{width:72px;height:96px;border-radius:7px;flex-shrink:0}
            .details h3{font-size:14px;margin-bottom:4px;line-height:1.35}
            .details p.author{font-size:12px;margin-bottom:4px}
            .details p.qty{font-size:12px;margin-bottom:8px}
            .edition-badge{font-size:11px;padding:3px 8px}
            .price-info{text-align:left;min-width:unset}
            .price-label{font-size:12px}
            .price-amount{font-size:18px}
            /* Foot */
            .order-foot{padding:10px 14px;flex-direction:column;align-items:flex-start;gap:8px;background:#fafafa}
            .foot-info{font-size:11px;gap:6px;line-height:1.5}
            .foot-actions{width:100%;gap:6px;flex-wrap:wrap}
            .btn-outline,.btn-solid,.btn-cancel{font-size:11px;padding:7px 10px;border-radius:8px;flex:1;justify-content:center;min-width:0;height:34px}
            /* Cancel Alert */
            .cancel-alert{padding:10px 14px;font-size:11px;flex-direction:column;gap:5px}
            /* Banner */
            .bottom-banner{padding:16px;border-radius:12px;flex-direction:column;gap:14px}
            .shield-icon{width:38px;height:38px;border-radius:8px}
            .banner-text h3{font-size:14px;margin-bottom:5px}
            .banner-text p{font-size:12px}
            .btn-banner{width:100%;padding:11px;font-size:13px;border-radius:8px;text-align:center}
        }
        @media(max-width:480px){
            .orders-content{padding:12px 12px 60px}
            .title{font-size:18px}
            .tabs a{font-size:11px;padding:9px 10px;gap:4px}
            .tab-count{padding:1px 5px;font-size:10px}
            .cover{width:64px;height:86px}
            .details h3{font-size:13px}
            .price-amount{font-size:17px}
            .foot-info{font-size:11px}
            .btn-outline,.btn-solid,.btn-cancel{font-size:11px;padding:7px 10px}
        }
    </style>
</head>
<body>
    <?php require __DIR__.'/../components/header.php';?>
    <div class="account">
        <?php require __DIR__.'/../components/profile_sidebar.php';?>
        <main class="orders-content">
            <div class="header-area">
                <div>
                    <h1 class="title">รายการคำสั่งซื้อของฉัน</h1>
                    <p class="subtitle">ติดตามสถานะและประวัติการสั่งซื้อหนังสือของคุณทั้งหมดได้ที่นี่ พร้อมระบบคุ้มครองความปลอดภัยผ่าน Wanawan Escrow</p>
                </div>
            </div>
            
            <nav class="tabs">
                <?php 
                $tabsDef = [
                    'all' => ['ทั้งหมด', $counts['all']],
                    'pending' => ['รอการชำระ', $counts['pending']],
                    'shipping' => ['กำลังจัดส่ง', $counts['shipping']],
                    'completed' => ['สำเร็จแล้ว', $counts['completed']],
                    'returned' => ['คืนสินค้า/คืนเงิน', $counts['returned'] ?? 0],
                    'cancelled' => ['ยกเลิกแล้ว', $counts['cancelled']]
                ];
                foreach($tabsDef as $key=>$data):
                ?>
                <a class="<?=$tab===$key?'active':''?>" href="?tab=<?=$key?>">
                    <?=$data[0]?> <span class="tab-count"><?=$data[1]?></span>
                </a>
                <?php endforeach;?>
            </nav>
            
            <div class="filters-area">
                <form class="search-box">
                    <input type="hidden" name="tab" value="<?=htmlspecialchars($tab)?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input name="q" value="<?=htmlspecialchars($q)?>" placeholder="ค้นหาชื่อหนังสือ หรือหมายเลขคำสั่งซื้อ">
                </form>
                <div class="filter-dropdowns">
                    <select class="dropdown">
                        <option>ช่วงเวลา: 30 วันล่าสุด</option>
                    </select>
                    <select class="dropdown">
                        <option>ประเภทร้าน: ทั้งหมด</option>
                    </select>
                </div>
            </div>

            <?php if(!$orders):?>
                <div class="empty">ไม่พบรายการคำสั่งซื้อในหมวดนี้</div>
            <?php else: foreach($orders as $o):
                list($state,$stateClass)=orderState($o);
                
                // Determine Seller Badge
                $sellerType = $o['items'][0]['seller_type'] ?? 'user';
                $sellerName = htmlspecialchars(implode(', ',array_unique(array_map('orderSellerName',$o['items'])))?:'ร้านวนวรรณ');
                $sellerBadge = '';
                $reviewableItems = count(array_filter($o['items'], fn($item) => empty($item['review_id'])));
                $iconClass = 'store-icon user';
                $iconSvg = '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>';
                
                if ($sellerType === 'publisher') {
                    $sellerBadge = '<span class="seller-badge official">ร้านค้าทางการ</span>';
                    $iconClass = 'store-icon publisher';
                    $iconSvg = '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>';
                } elseif ($sellerType === 'shop') {
                    $sellerBadge = '<span class="seller-badge shop">ร้านค้า</span>';
                    $iconClass = 'store-icon shop';
                    $iconSvg = '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>';
                }
            ?>
            <article class="order-card" onclick="openOrderDetail(this)" data-od='<?=$orderJson?>'>
                <header class="order-head">
                    <div class="seller-info">
                        <div class="<?=$iconClass?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?=$iconSvg?></svg>
                        </div>
                        <span class="seller-name"><?=$sellerName?></span>
                        <?=$sellerBadge?>
                    </div>
                    <?php
                        $statusIcon = '';
                        if($stateClass === 'completed') $statusIcon = '<polyline points="20 6 9 17 4 12"></polyline>';
                        elseif($stateClass === 'cancelled') $statusIcon = '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>';
                        elseif($stateClass === 'pending') $statusIcon = '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>';
                        else $statusIcon = '<rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>';
                    ?>
                    <span class="badge <?=$stateClass?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><?=$statusIcon?></svg>
                        <?=$state?>
                    </span>
                </header>
                
                <div class="order-body">
                    <div class="book-info">
                        <?php $firstItem = $o['items'][0] ?? null; ?>
                        <div class="cover">
                            <?php if($firstItem && ($img = orderImage($firstItem['image_cover'] ?? null))):?>
                                <img src="<?=htmlspecialchars($img)?>" alt="">
                            <?php else: ?>
                                <img src="/assets/image/placeholder.png" alt="">
                            <?php endif;?>
                        </div>
                        <div class="details">
                            <h3><?=htmlspecialchars(($firstItem['title'] ?? null)?:'หนังสือ')?></h3>
                            <p class="author">ผู้เขียน: <?=htmlspecialchars(($firstItem['author'] ?? null)?:'-')?></p>
                            <p class="qty">จำนวน: <strong>×<?=count($o['items'])==1 ? $firstItem['quantity'] : count($o['items'])?> เล่ม</strong></p>
                            <?php if(count($o['items']) > 1): ?>
                                <span class="edition-badge">+ ดูสินค้าอีก <?=count($o['items'])-1?> เล่มในออเดอร์นี้</span>
                            <?php else: ?>
                                <span class="edition-badge">จัดพิมพ์ครั้งที่ 1 (ของแถมรอบพรีออเดอร์ครบ)</span>
                            <?php endif; ?>
                            
                            <?php if($stateClass === 'completed'): ?>
                            <div class="success-text">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg> ผู้ซื้อได้รับพัสดุเรียบร้อยแล้ว
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="price-info">
                        <div class="price-label">ยอดรวมคำสั่งซื้อ</div>
                        <div class="price-amount">฿<?=number_format($o['total_amount'],2)?></div>
                    </div>
                </div>
                
                <?php if($stateClass === 'cancelled'): ?>
                <div class="cancel-alert">
                    <div class="alert-content">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span>เหตุผลที่ยกเลิก: <?=htmlspecialchars($o['cancellation_reason'] ?? 'คำสั่งซื้อถูกยกเลิกเนื่องจากเกินกำหนดเวลาชำระเงิน')?></span>
                    </div>
                    <span class="alert-note">ไม่มีการตัดเงินจากระบบ</span>
                </div>
                <?php endif; ?>

                <footer class="order-foot" onclick="event.stopPropagation()">
                    <div class="foot-info">
                        <span>หมายเลขคำสั่งซื้อ: <strong><?=htmlspecialchars($o['order_number'])?></strong> <button class="copy-btn" onclick="navigator.clipboard.writeText('<?=htmlspecialchars($o['order_number'])?>'); alert('คัดลอกแล้ว');"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> คัดลอก</button></span>
                        <span class="divider">|</span>
                        <span><?=formatThaiDate($o['created_at'])?></span>
                    </div>
                    <div class="foot-actions">
                        <a href="/pages/order_receipt.php?number=<?=urlencode($o['order_number'])?>" target="_blank" class="btn-outline">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> ดูใบเสร็จ
                        </a>
                        
                        <?php if($stateClass==='completed' && $reviewableItems):?>
                        <a href="/pages/review_order.php?order_id=<?=(int)$o['order_id']?>" class="btn-outline review">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg> ให้คะแนน / รีวิว
                        </a>
                        <?php endif; ?>
                        <?php
                        // ปุ่ม "ขอคืนสินค้า" — แสดงเฉพาะ delivered และยังไม่เคยยื่นคำขอ
                        $returnStatus = $existingReturnsMap[(int)$o['order_id']] ?? null;
                        if ($o['shipping_status'] === 'delivered'):
                            if ($returnStatus === null):
                        ?>
                        <button type="button" class="btn-outline" style="color:#d97706;border-color:#fde68a;background:#fffbeb;cursor:pointer;" onclick="openReturnPopup(<?=(int)$o['order_id']?>, event)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
                            ขอคืนสินค้า
                        </button>
                        <?php elseif ($returnStatus === 'pending'): ?>
                        <span class="btn-outline" style="color:#d97706;border-color:#fde68a;background:#fffbeb;cursor:default;opacity:.8;">
                            ⏳ รอพิจารณาคืน
                        </span>
                        <?php elseif ($returnStatus === 'approved'): ?>
                        <span class="btn-outline" style="color:#059669;border-color:#a7f3d0;background:#d1fae5;cursor:default;opacity:.8;">
                            ✅ คืนเงินแล้ว
                        </span>
                        <?php elseif ($returnStatus === 'rejected'): ?>
                        <span class="btn-outline" style="color:#dc2626;border-color:#fecaca;background:#fee2e2;cursor:default;opacity:.8;">
                            ❌ คำขอถูกปฏิเสธ
                        </span>
                        <?php endif; endif; ?>
                        
                        <?php if($stateClass==='pending'):?>
                        <form method="post" style="margin:0; display:inline-block;">
                            <input type="hidden" name="order_id" value="<?=$o['order_id']?>">
                            <button class="btn-cancel" name="cancel" onclick="return confirm('แน่ใจหรือไม่ว่าต้องการยกเลิกคำสั่งซื้อ?');">ยกเลิก</button>
                        </form>
                        <a href="/pages/payment.php?order=<?=$o['order_id']?>" class="btn-solid" style="background:#d97706; border-color:#d97706;">ชำระเงิน</a>
                        
                        <?php elseif($stateClass==='shipping' && $o['shipping_status']==='shipped'):?>
                        <form method="post" style="margin:0; display:inline-block;">
                            <input type="hidden" name="order_id" value="<?=$o['order_id']?>">
                            <button class="btn-solid" name="confirm_received">ยืนยันได้รับสินค้า</button>
                        </form>
                        
                        <?php else: ?>
                        <form method="post" style="margin:0; display:inline-block;">
                            <input type="hidden" name="order_id" value="<?=$o['order_id']?>">
                            <button class="btn-solid" name="buy_again"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg> ซื้ออีกครั้ง</button>
                        </form>
                        <?php endif;?>
                    </div>
                </footer>
            </article>
            <?php endforeach; endif;?>

            <div class="pagination">
                <span>แสดง <?=$counts['all']?> จากทั้งหมด <?=$counts['all']?> รายการ</span>
                <div class="page-controls">
                    <button class="page-btn">ก่อนหน้า</button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn">ถัดไป</button>
                </div>
            </div>

            <div class="bottom-banner">
                <div class="banner-content">
                    <div class="shield-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    </div>
                    <div class="banner-text">
                        <h3>มาตรฐานการชำระเงินและการคุ้มครองผู้ซื้อ Wanawan Hub Escrow</h3>
                        <p>ยอดเงินทุกคำสั่งซื้อจะถูกพักไว้อย่างปลอดภัยที่ระบบส่วนกลาง จนกว่าคุณจะได้รับพัสดุและตรวจสอบสภาพหนังสือตรงตามตกลง หากสินค้าไม่ตรงปกหรือไม่ได้รับของ สามารถกดขอคืนเงินเต็มจำนวนได้ทันที 100% ปราศจากการฉ้อโกง</p>
                    </div>
                </div>
                <button class="btn-banner">อ่านข้อกำหนดความปลอดภัย</button>
            </div>

        </main>
    </div>

    <div class="od-modal" id="odModal"><div class="od-box"><div class="od-head" id="odHead"><h2 id="odTitle"></h2><p id="odSub"></p></div><div class="od-section" id="odItems"></div><div class="od-section"><h3><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>ข้อมูลการจัดส่ง</h3><div class="od-row"><span>ผู้รับ</span><strong id="odBuyer"></strong></div><div class="od-row"><span>เบอร์โทร</span><strong id="odPhone"></strong></div><div class="od-row"><span>ที่อยู่</span><strong id="odAddress" style="max-width:320px;text-align:right"></strong></div><div id="odTrackingBox" style="display:none" class="od-tracking"><strong id="odCourier"></strong><div>หมายเลขพัสดุ: <span id="odTracking" style="font-weight:700;letter-spacing:.5px"></span></div></div></div><div class="od-section"><h3><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>สรุปยอดชำระ</h3><div class="od-row"><span>ยอดรวมสินค้า</span><strong id="odSubtotal"></strong></div><div class="od-row"><span>ค่าจัดส่ง</span><strong id="odShippingFee"></strong></div><div class="grand-total"><span>ยอดสุทธิ</span><strong id="odTotal"></strong></div></div><div class="od-footer-btns" id="odFooterBtns"></div></div></div>

    <script>
    const odModal=document.getElementById('odModal');
    function openOrderDetail(el){
      if(event.target.closest('a,button,form'))return;
      const d=JSON.parse(el.dataset.od);
      const colors={completed:'#059669',shipping:'#2563eb',pending:'#d97706',cancelled:'#dc2626'};
      document.getElementById('odHead').style.background=colors[d.stateClass]||'#115e59';
      document.getElementById('odTitle').textContent=d.seller;
      document.getElementById('odSub').textContent=d.state+' · #'+d.number;
      document.getElementById('odBuyer').textContent=d.buyer;
      document.getElementById('odPhone').textContent=d.phone;
      document.getElementById('odAddress').textContent=d.address;
      const trackBox=document.getElementById('odTrackingBox');
      if(d.tracking){trackBox.style.display='block';document.getElementById('odCourier').textContent='บริษัทขนส่ง: '+(d.courier||'ไม่ระบุ');document.getElementById('odTracking').textContent=d.tracking;}
      else trackBox.style.display='none';
      document.getElementById('odSubtotal').textContent='฿'+d.subtotal.toLocaleString(undefined,{minimumFractionDigits:2});
      document.getElementById('odShippingFee').textContent='฿'+d.shipping_fee.toLocaleString(undefined,{minimumFractionDigits:2});
      document.getElementById('odTotal').textContent='฿'+d.total.toLocaleString(undefined,{minimumFractionDigits:2});
      document.getElementById('odItems').innerHTML='<h3><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>รายการสินค้า</h3>'+d.items.map(i=>`<div class="od-item"><img src="${i.img||'/assets/image/placeholder.png'}" onerror="this.src='/assets/image/placeholder.png'"><div class="od-item-info"><div class="od-item-title">${i.title}</div><div class="od-item-sub">${i.author} · x${i.qty}</div></div><div class="od-item-price">฿${(i.price*i.qty).toLocaleString(undefined,{minimumFractionDigits:2})}</div></div>`).join('');
      let btns=`<button class="od-btn close-btn" onclick="odModal.classList.remove('open')">ปิด</button>`;
      document.getElementById('odFooterBtns').innerHTML=btns;
      odModal.classList.add('open');
    }
    odModal.addEventListener('click',e=>{if(e.target===odModal)odModal.classList.remove('open')});
    document.addEventListener('keydown',e=>{if(e.key==='Escape')odModal.classList.remove('open')});
    </script>

    <?php require __DIR__.'/../components/footer.php';?>
<div id="rr-modal-container" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:99999;">
    <iframe id="rr-modal-iframe" src="" style="width:100%; height:100%; border:none; background:transparent;"></iframe>
</div>
<script>
function openReturnPopup(orderId, event) {
    if(event) { event.preventDefault(); event.stopPropagation(); }
    const container = document.getElementById('rr-modal-container');
    const iframe = document.getElementById('rr-modal-iframe');
    iframe.src = '/pages/return_request.php?order_id=' + orderId;
    container.style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeReturnPopup() {
    const container = document.getElementById('rr-modal-container');
    const iframe = document.getElementById('rr-modal-iframe');
    container.style.display = 'none';
    iframe.src = '';
    document.body.style.overflow = '';
}
window.addEventListener('message', function(e) {
    if (e.data === 'close_rr_modal') {
        closeReturnPopup();
    } else if (e.data === 'reload_orders') {
        window.location.reload();
    }
});
</script>
</body>
</html>


