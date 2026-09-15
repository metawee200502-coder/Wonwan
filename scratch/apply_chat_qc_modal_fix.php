<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$oldQC = <<<'JS'
btn.onclick=function(){window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;};
JS;
$newQC = <<<'JS'
btn.onclick=function(){
    const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
    if(modal&&iframe){
        modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg> ชำระค่าบริการส่วนกลาง (QC)';
        iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
        modal.style.display='flex';
    } else {
        window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
    }
};
JS;

$content = str_replace($oldQC, $newQC, $content);

file_put_contents($file, $content);
echo "Updated chat.php QC logistics modal successfully.\n";
