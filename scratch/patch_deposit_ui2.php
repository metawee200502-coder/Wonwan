<?php
$file = 'c:\laragon\www\wanawan_project\pages\chat.php';
$c = file_get_contents($file);

// Replace the !myD block
$old1 = "if(!myD){const b=document.createElement('div');b.style.cssText='padding:15px;background:#fff5f5;color:#c53030;border-radius:8px;font-size:14px;text-align:center;border:1px solid #feb2b2;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';if(myS==='pending_verification'){b.innerHTML='<i class=\"fas fa-hourglass-half\"></i> <strong>\\u0e23\\u0e2d\\u0e41\\u0e2d\\u0e14\\u0e21\\u0e34\\u0e19\\u0e15\\u0e23\\u0e27\\u0e08\\u0e2a\\u0e2d\\u0e1a\\u0e2a\\u0e25\\u0e34\\u0e1b\\u0e40\\u0e07\\u0e34\\u0e19\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33</strong><br>\\u0e3f'+parseFloat(ptR).toFixed(2);}else{b.innerHTML='\\u26a0\\ufe0f <strong>\\u0e04\\u0e38\\u0e13\\u0e15\\u0e49\\u0e2d\\u0e07\\u0e0a\\u0e33\\u0e23\\u0e30\\u0e40\\u0e07\\u0e34\\u0e19\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33</strong><br>\\u0e3f'+parseFloat(ptR).toFixed(2)+'<br><br>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='\\u0e41\\u0e19\\u0e1a\\u0e2a\\u0e25\\u0e34\\u0e1b\\u0e0a\\u0e33\\u0e23\\u0e30\\u0e40\\u0e07\\u0e34\\u0e19';btn.style.cssText='padding:8px 16px';btn.onclick=function(){";

$new1 = <<<'NEW1'
if(!myD){
    const b=document.createElement('div');
    b.style.cssText='padding:18px;background:linear-gradient(135deg, #fff1f2, #ffe4e6);color:#be123c;border-radius:12px;font-size:14px;text-align:center;border:1px solid #fecdd3;box-shadow:0 4px 10px rgba(225, 29, 72, 0.05);margin-bottom:12px;';
    if(myS==='pending_verification' || myS==='reviewing'){
        b.innerHTML='<div style="font-size:24px; margin-bottom:8px;">⏳</div><strong style="font-size:15px; display:block; margin-bottom:4px;">รอแอดมินตรวจสอบการชำระเงิน</strong><span style="font-size:18px; font-weight:600; color:#e11d48;">฿'+parseFloat(ptR).toFixed(2)+'</span>';
    }else{
        b.innerHTML='<div style="font-size:24px; margin-bottom:8px;">💳</div><strong style="font-size:15px; display:block; margin-bottom:4px;">คุณต้องชำระเงินมัดจำ</strong><span style="font-size:18px; font-weight:600; color:#e11d48;">฿'+parseFloat(ptR).toFixed(2)+'</span><br><br>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='<svg style="width:16px; height:16px; display:inline-block; vertical-align:-3px; margin-right:6px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>ชำระเงินมัดจำ';
        btn.style.cssText='padding:10px 20px; background:#e11d48; border-color:#e11d48; color:#fff; border-radius:8px; font-weight:500; font-family:inherit; box-shadow:0 2px 4px rgba(225,29,72,0.2); transition:transform 0.2s;';
        btn.onclick=function(){
NEW1;

$c1 = str_replace($old1, $new1, $c);
if ($c1 === $c) {
    echo "FAILED patching !myD block\n";
} else {
    $c = $c1;
    echo "Patched !myD block successfully\n";
}

// Replace the !ptD block
$old2 = "if(!ptD){const b=document.createElement('div');b.style.cssText='padding:14px;background:#ebf4ff;color:#2b6cb0;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bee3f8;margin-bottom:10px';b.innerHTML='<i class=\"fas fa-clock\"></i> <strong>\\u0e23\\u0e2d\\u0e2d\\u0e35\\u0e01\\u0e1d\\u0e48\\u0e32\\u0e22\\u0e0a\\u0e33\\u0e23\\u0e30\\u0e40\\u0e07\\u0e34\\u0e19\\u0e21\\u0e31\\u0e14\\u0e08\\u0e33</strong><br>\\u0e3f'+parseFloat(myR).toFixed(2);cb.append(b);}";
$new2 = <<<'NEW2'
if(!ptD){
    const b=document.createElement('div');
    b.style.cssText='padding:18px;background:linear-gradient(135deg, #eff6ff, #dbeafe);color:#1e3a8a;border-radius:12px;font-size:14px;text-align:center;border:1px solid #bfdbfe;box-shadow:0 4px 10px rgba(37, 99, 235, 0.05);margin-bottom:12px;';
    b.innerHTML='<div style="font-size:24px; margin-bottom:8px;">⏳</div><strong style="font-size:15px; display:block; margin-bottom:4px;">รออีกฝ่ายชำระเงินมัดจำ</strong><span style="font-size:18px; font-weight:600; color:#2563eb;">฿'+parseFloat(myR).toFixed(2)+'</span>';
    cb.append(b);
}
NEW2;

$c2 = str_replace($old2, $new2, $c);
if ($c2 === $c) {
    echo "FAILED patching !ptD block\n";
} else {
    $c = $c2;
    echo "Patched !ptD block successfully\n";
}

file_put_contents($file, $c);
echo "Done saving chat.php\n";
