<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$oldSent1 = <<<'JS'
   else if(myTr&&!ptTr){const b=document.createElement('div');b.style.cssText='padding:14px;background:#eff6ff;color:#1e40af;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bfdbfe';b.innerHTML='\ud83d\udce6 <strong>\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e43\u0e2b\u0e49 QC \u0e41\u0e25\u0e49\u0e27</strong><br>\u0e23\u0e2d\u0e2d\u0e35\u0e01\u0e1d\u0e48\u0e32\u0e22\u0e2a\u0e48\u0e07\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e43\u0e2b\u0e49\u0e17\u0e35\u0e21\u0e07\u0e32\u0e19 QC';cb.append(b);}
JS;

$newSent1 = <<<'JS'
   else if(myTr&&!ptTr){const b=document.createElement('div');b.style.cssText='padding:14px;background:#eff6ff;color:#1e40af;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bfdbfe';b.innerHTML='\ud83d\udce6 <strong>\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e1e\u0e31\u0e2a\u0e14\u0e38\u0e40\u0e23\u0e35\u0e22\u0e1a\u0e23\u0e49\u0e2d\u0e22\u0e41\u0e25\u0e49\u0e27</strong><br>\u0e23\u0e2d\u0e43\u0e2b\u0e49\u0e41\u0e2d\u0e14\u0e21\u0e34\u0e19\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e41\u0e25\u0e30\u0e08\u0e30\u0e21\u0e35\u0e01\u0e32\u0e23\u0e2a\u0e48\u0e07\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e21\u0e32\u0e43\u0e2b\u0e49';cb.append(b);}
JS;
$content = str_replace($oldSent1, $newSent1, $content);


$oldSent2 = <<<'JS'
   else if(myTr&&ptTr){const b=document.createElement('div');b.style.cssText='padding:14px;background:#f3e8ff;color:#6b21a8;border-radius:8px;font-size:14px;text-align:center;border:1px solid #d8b4fe';b.innerHTML='\ud83d\udd0d <strong>QC \u0e01\u0e33\u0e25\u0e31\u0e07\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32</strong><br>\u0e17\u0e35\u0e21\u0e07\u0e32\u0e19\u0e01\u0e33\u0e25\u0e31\u0e07\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e17\u0e31\u0e49\u0e07\u0e2a\u0e2d\u0e07\u0e1d\u0e48\u0e32\u0e22';cb.append(b);}
JS;

$newSent2 = <<<'JS'
   else if(myTr&&ptTr){const b=document.createElement('div');b.style.cssText='padding:14px;background:#f3e8ff;color:#6b21a8;border-radius:8px;font-size:14px;text-align:center;border:1px solid #d8b4fe';b.innerHTML='\ud83d\udd0d <strong>\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e1e\u0e31\u0e2a\u0e14\u0e38\u0e40\u0e23\u0e35\u0e22\u0e1a\u0e23\u0e49\u0e2d\u0e22\u0e41\u0e25\u0e49\u0e27 (\u0e17\u0e31\u0e49\u0e07\u0e2a\u0e2d\u0e07\u0e1d\u0e48\u0e32\u0e22)</strong><br>\u0e23\u0e2d\u0e43\u0e2b\u0e49\u0e41\u0e2d\u0e14\u0e21\u0e34\u0e19\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e41\u0e25\u0e30\u0e08\u0e30\u0e21\u0e35\u0e01\u0e32\u0e23\u0e2a\u0e48\u0e07\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e21\u0e32\u0e43\u0e2b\u0e49';cb.append(b);}
JS;
$content = str_replace($oldSent2, $newSent2, $content);

file_put_contents($file, $content);
echo "Updated chat.php QC shipment states successfully.\n";
