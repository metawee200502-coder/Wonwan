<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

// 1. Update backend payload
$oldPayload = <<<'PHP'
  'requesterQcAcceptanceStatus'=>$exchangeRequest['requester_qc_acceptance_status']??'pending',
  'offerQcAcceptanceStatus'=>$exchangeRequest['offer_qc_acceptance_status']??'pending',
PHP;
$newPayload = <<<'PHP'
  'requesterQcAcceptanceStatus'=>$exchangeRequest['requester_qc_acceptance_status']??'pending',
  'offerQcAcceptanceStatus'=>$exchangeRequest['offer_qc_acceptance_status']??'pending',
  'adminTrackingToRequester'=>$exchangeRequest['admin_tracking_to_requester']??null,
  'adminTrackingToOffer'=>$exchangeRequest['admin_tracking_to_offer']??null,
PHP;
$content = str_replace($oldPayload, $newPayload, $content);


// 2. Update frontend banner
$oldBanner = <<<'JS'
     } else if (myQcAcc==='accepted' && ptQcAcc==='accepted') {
         const b=document.createElement('div');b.style.cssText='padding:14px;background:#f0fff4;color:#166534;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bbf7d0';
         b.innerHTML='📦 <strong>รอกลางจัดส่งสินค้า</strong><br>ทั้งสองฝ่ายยอมรับผลประเมินแล้ว แอดมินกำลังจัดส่งสินค้าให้คุณ';
         cb.append(b);
     }
JS;

$newBanner = <<<'JS'
     } else if (myQcAcc==='accepted' && ptQcAcc==='accepted') {
         const myAdminTr = data.isRequester ? data.adminTrackingToRequester : data.adminTrackingToOffer;
         const myRv = (data.myCondition) ? data.myCondition.is_received : 0;
         
         if (myAdminTr) {
             const sb=document.createElement('div');sb.style.cssText='padding:15px;background:#f0fff4;color:#276749;border-radius:8px;font-size:14px;text-align:center;border:1px solid #c6f6d5;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
             
             if (!myRv) {
                 sb.innerHTML='📦 <strong>แอดมินจัดส่งสินค้าแล้ว!</strong><br>ขนส่ง/เลขพัสดุ: <span style="color:#059669;font-weight:600;">'+myAdminTr+'</span><br><br>';
                 const btn=document.createElement('button');btn.className='btn';btn.style.cssText='background:#48bb78;color:#fff;padding:8px 16px';btn.textContent='✅ ยืนยันได้รับสินค้าแล้ว';
                 btn.onclick=function(){
                     if(confirm('ยืนยันว่าได้รับสินค้าเรียบร้อยแล้ว?')){
                         const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';
                         f.innerHTML='<input type="hidden" name="action" value="receive_item"><input type="hidden" name="request_id" value="'+data.requestId+'">';
                         document.body.append(f);f.submit();
                     }
                 };
                 sb.append(btn);
             } else {
                 sb.innerHTML='🎉 <strong>คุณได้รับสินค้าแล้ว!</strong><br>การแลกเปลี่ยนฝั่งคุณเสร็จสมบูรณ์';
             }
             cb.append(sb);
         } else {
             const b=document.createElement('div');b.style.cssText='padding:14px;background:#f0fff4;color:#166534;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bbf7d0';
             b.innerHTML='📦 <strong>รอกลางจัดส่งสินค้า</strong><br>ทั้งสองฝ่ายยอมรับผลประเมินแล้ว แอดมินกำลังจัดส่งสินค้าให้คุณ';
             cb.append(b);
         }
     }
JS;
$content = str_replace($oldBanner, $newBanner, $content);

file_put_contents($file, $content);
echo "Successfully patched chat.php for admin tracking and receive button.\n";
