<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../config/config.php';

$userId=(int)($_SESSION['user_id']??0);
$role=$_SESSION['role']??'';
if(!$userId||!in_array($role,['publisher_admin','shop_admin'],true)){
    http_response_code(403);echo json_encode(['success'=>false]);exit;
}
$sellerType=$role==='publisher_admin'?'publisher':'shop';
$sellerStmt=$sellerType==='publisher'
    ?$pdo->prepare('SELECT publisher_id FROM publisher_staff WHERE user_id=? LIMIT 1')
    :$pdo->prepare('SELECT shop_id FROM shop_staff WHERE user_id=? LIMIT 1');
$sellerStmt->execute([$userId]);
$sellerId=(int)$sellerStmt->fetchColumn();
if(!$sellerId){http_response_code(403);echo json_encode(['success'=>false]);exit;}

$year=max(2000,min((int)date('Y')+1,(int)($_GET['year']??date('Y'))));
$period=in_array($_GET['period']??'monthly',['weekly','monthly','yearly'],true)?$_GET['period']:'monthly';
$base=" FROM order_items oi JOIN orders o ON o.order_id=oi.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type=? AND b.seller_id=? AND o.payment_status='paid'";
$weeks=[];$weekIndex=null;$month=null;

if($period==='weekly'){
    $month=max(1,min(12,(int)($_GET['month']??date('n'))));
    $first=new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month));
    $last=$first->modify('last day of this month');
    $cursor=$first->modify('monday this week');
    while($cursor<=$last){
        $end=$cursor->modify('+6 days');
        $weeks[]=['start'=>$cursor->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'label'=>$cursor->format('j/n').' – '.$end->format('j/n')];
        $cursor=$cursor->modify('+7 days');
    }
    $weekRequested=isset($_GET['week'])&&$_GET['week']!=='';
    $weekIndex=max(0,min(count($weeks)-1,(int)($_GET['week']??0)));
    if(!$weekRequested&&$year===(int)date('Y')&&$month===(int)date('n')){
        foreach($weeks as $index=>$range){
            if(date('Y-m-d')>=$range['start']&&date('Y-m-d')<=$range['end']){$weekIndex=$index;break;}
        }
    }
    $range=$weeks[$weekIndex];
    $dayNames=['จ.','อ.','พ.','พฤ.','ศ.','ส.','อา.'];$labels=[];
    $day=new DateTimeImmutable($range['start']);
    for($i=0;$i<7;$i++){$labels[]=$dayNames[$i].' '.$day->format('j/n');$day=$day->modify('+1 day');}
    $data=array_fill(1,7,0.0);
    $stmt=$pdo->prepare("SELECT WEEKDAY(o.created_at)+1 bucket,SUM(oi.price_at_purchase*oi.quantity) total$base AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY bucket");
    $stmt->execute([$sellerType,$sellerId,$range['start'],$range['end']]);
    foreach($stmt as $row)$data[max(1,min(7,(int)$row['bucket']))]=(float)$row['total'];
    $data=array_values($data);
}elseif($period==='yearly'){
    $start=$year-4;$labels=range($start,$year);$data=array_fill($start,5,0.0);
    $stmt=$pdo->prepare("SELECT YEAR(o.created_at) bucket,SUM(oi.price_at_purchase*oi.quantity) total$base AND YEAR(o.created_at) BETWEEN ? AND ? GROUP BY bucket");
    $stmt->execute([$sellerType,$sellerId,$start,$year]);foreach($stmt as $row)$data[(int)$row['bucket']]=(float)$row['total'];$data=array_values($data);
}else{
    $labels=['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];$data=array_fill(1,12,0.0);
    $stmt=$pdo->prepare("SELECT MONTH(o.created_at) bucket,SUM(oi.price_at_purchase*oi.quantity) total$base AND YEAR(o.created_at)=? GROUP BY bucket");
    $stmt->execute([$sellerType,$sellerId,$year]);foreach($stmt as $row)$data[(int)$row['bucket']]=(float)$row['total'];$data=array_values($data);
}
echo json_encode(['success'=>true,'labels'=>$labels,'data'=>$data,'year'=>$year,'period'=>$period,'month'=>$month,'week'=>$weekIndex,'weeks'=>$weeks],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
