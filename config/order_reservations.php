<?php
require_once __DIR__.'/order_notifications.php';

function wanawanExpirePendingOrders(PDO $pdo,?int $userId=null):array
{
    $ownTransaction=!$pdo->inTransaction();if($ownTransaction)$pdo->beginTransaction();
    try{
        $sql="SELECT order_id,user_id,order_number FROM orders WHERE payment_status='pending' AND stock_reserved=1 AND reservation_expires_at IS NOT NULL AND reservation_expires_at<=NOW()";
        $params=[];if($userId!==null){$sql.=' AND user_id=?';$params[]=$userId;}$sql.=' FOR UPDATE';
        $stmt=$pdo->prepare($sql);$stmt->execute($params);$orders=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $items=$pdo->prepare('SELECT book_id,quantity FROM order_items WHERE order_id=?');$restore=$pdo->prepare('UPDATE books SET stock_quantity=stock_quantity+? WHERE book_id=?');
        $cancel=$pdo->prepare("UPDATE orders SET payment_status='cancelled',shipping_status='cancelled',stock_reserved=0,cancellation_reason='หมดเวลาชำระเงิน 24 ชั่วโมง' WHERE order_id=? AND payment_status='pending' AND stock_reserved=1");
        foreach($orders as $order){$items->execute([$order['order_id']]);foreach($items->fetchAll(PDO::FETCH_ASSOC) as $item)$restore->execute([(int)$item['quantity'],(int)$item['book_id']]);$cancel->execute([$order['order_id']]);wanawanNotifyOrderBuyer($pdo,$order,'คำสั่งซื้อหมดเวลาชำระเงิน','คำสั่งซื้อถูกยกเลิกอัตโนมัติและคืนสินค้าเข้าสต็อกแล้ว');}
        if($ownTransaction)$pdo->commit();return array_map('intval',array_column($orders,'order_id'));
    }catch(Throwable $e){if($ownTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function wanawanCancelReservedOrder(PDO $pdo,int $orderId,int $userId,string $reason='ลูกค้ายกเลิกคำสั่งซื้อ'):bool
{
    $ownTransaction=!$pdo->inTransaction();if($ownTransaction)$pdo->beginTransaction();
    try{$stmt=$pdo->prepare("SELECT order_id FROM orders WHERE order_id=? AND user_id=? AND payment_status='pending' AND stock_reserved=1 FOR UPDATE");$stmt->execute([$orderId,$userId]);if(!$stmt->fetchColumn()){if($ownTransaction)$pdo->commit();return false;}$items=$pdo->prepare('SELECT book_id,quantity FROM order_items WHERE order_id=?');$items->execute([$orderId]);$restore=$pdo->prepare('UPDATE books SET stock_quantity=stock_quantity+? WHERE book_id=?');foreach($items as $item)$restore->execute([(int)$item['quantity'],(int)$item['book_id']]);$pdo->prepare("UPDATE orders SET payment_status='cancelled',shipping_status='cancelled',stock_reserved=0,cancellation_reason=? WHERE order_id=?")->execute([$reason,$orderId]);if($ownTransaction)$pdo->commit();return true;}catch(Throwable $e){if($ownTransaction&&$pdo->inTransaction())$pdo->rollBack();throw$e;}
}
