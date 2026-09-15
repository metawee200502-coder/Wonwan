<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

// 1. Update condition form title setting in getCondBanner
$oldCond1 = <<<'JS'
          btn.onclick=function(){
              const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
              if(modal&&iframe){
                  iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1';
                  modal.style.display='flex';
              } else { window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId; }
          };
JS;
$newCond1 = <<<'JS'
          btn.onclick=function(){
              const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
              if(modal&&iframe){
                  modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> ฟอร์มยืนยันสภาพสินค้า';
                  iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1';
                  modal.style.display='flex';
              } else { window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId; }
          };
JS;
$content = str_replace($oldCond1, $newCond1, $content);

$oldCond2 = <<<'JS'
          vBtn.onclick=function(){
              const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
              if(modal&&iframe){
                  iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&readonly=1&iframe=1';
                  modal.style.display='flex';
              } else { window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId+'&readonly=1'; }
          };
JS;
$newCond2 = <<<'JS'
          vBtn.onclick=function(){
              const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
              if(modal&&iframe){
                  modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> ฟอร์มยืนยันสภาพสินค้า';
                  iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&readonly=1&iframe=1';
                  modal.style.display='flex';
              } else { window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId+'&readonly=1'; }
          };
JS;
$content = str_replace($oldCond2, $newCond2, $content);

// 2. Update upload slip logic
$oldSlip = <<<'JS'
btn.onclick=function(){window.location.href='/pages/trade_upload_slip.php?request_id='+data.requestId;};
JS;
$newSlip = <<<'JS'
btn.onclick=function(){
    const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
    if(modal&&iframe){
        modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg> แนบสลิปชำระเงินมัดจำ';
        iframe.src='/pages/trade_upload_slip.php?request_id='+data.requestId+'&iframe=1';
        modal.style.display='flex';
    } else {
        window.location.href='/pages/trade_upload_slip.php?request_id='+data.requestId;
    }
};
JS;

// Only replace the upload slip button, not the QC logistics one
// Wait, the QC slip is around line 211, and P2P slip is around line 230.
// Let's replace both for safety (both QC slip and direct escrow slip).
$content = str_replace($oldSlip, $newSlip, $content);

// 3. Setup event listener for closing modal in chat.php
$oldMessageListener = "</script>\n<?php endif;?>";
$newMessageListener = <<<'JS'
window.addEventListener('message', function(e){
    if(e.data && e.data.action === 'close_condition_modal'){
        document.getElementById('conditionModal').style.display='none';
        document.getElementById('conditionIframe').src='';
        window.location.reload();
    }
});
</script>
<?php endif;?>
JS;
if (strpos($content, 'close_condition_modal') === false) {
    $content = str_replace("</script>\n<?php endif;?>", $newMessageListener, $content);
}

file_put_contents($file, $content);
echo "Updated chat.php for slip popup successfully.\n";
