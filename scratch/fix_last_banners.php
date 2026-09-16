<?php
$content = file_get_contents('C:/laragon/www/wanawan_project/pages/chat.php');

$search_str = <<<'SEARCH'
    else if(!myAdminTr){
        const b=document.createElement('div');
        b.style.cssText='padding:14px;background:#f0fff4;color:#166534;border-radius:8px;font-size:14px;text-align:center;border:1px solid #bbf7d0';
        b.innerHTML='📦 <strong>รอส่วนกลางจัดส่งสินค้า</strong><br>ยอมรับผลการประเมินแล้ว แอดมินกำลังจัดส่งสินค้าให้คุณ';
        cb.append(b);
    }
    else if(!myRv){
        const sb=document.createElement('div');
        sb.style.cssText='padding:15px;background:#f0fff4;color:#276749;border-radius:8px;font-size:14px;text-align:center;border:1px solid #c6f6d5;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
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
        cb.append(sb);
    }
    else {
        const sb=document.createElement('div');
        sb.style.cssText='padding:15px;background:#f0fff4;color:#276749;border-radius:8px;font-size:14px;text-align:center;border:1px solid #c6f6d5;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';
        sb.innerHTML='🎉 <strong>คุณได้รับสินค้าแล้ว!</strong><br>การแลกเปลี่ยนฝั่งคุณเสร็จสมบูรณ์';
        cb.append(sb);
    }
SEARCH;

$replace_str = <<<'REPLACE'
    else if(!myAdminTr){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;border:1px solid #bbf7d0;margin-bottom:12px;gap:12px;';
        b.innerHTML='<div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รอส่วนกลางจัดส่งสินค้า</strong><span style="font-size:12px;opacity:0.8;">ยอมรับผลการประเมินแล้ว แอดมินกำลังจัดส่งสินค้าให้คุณ</span></div>';
        cb.append(b);
    }
    else if(!myRv){
        const sb=document.createElement('div');
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bbf7d0;margin-bottom:12px;gap:10px;flex-wrap:wrap;';
        sb.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">แอดมินจัดส่งสินค้าแล้ว!</strong><span style="font-size:12px;opacity:0.8;">ขนส่ง/เลขพัสดุ: <span style="font-weight:600;">'+myAdminTr+'</span></span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.textContent='ยืนยันได้รับสินค้าแล้ว';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#10b981; border:1px solid #10b981; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            Swal.fire({
                title: 'ยืนยันการรับสินค้า?',
                text: 'ยืนยันว่าได้รับสินค้าจากแอดมินเรียบร้อยแล้วใช่หรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ใช่, ได้รับแล้ว',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';
                    f.innerHTML='<input type="hidden" name="action" value="receive_item"><input type="hidden" name="request_id" value="'+data.requestId+'">';
                    document.body.append(f);f.submit();
                }
            });
        };
        sb.append(btn);
        cb.append(sb);
    }
    else {
        const sb=document.createElement('div');
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;border:1px solid #bbf7d0;margin-bottom:12px;gap:12px;';
        sb.innerHTML='<div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">คุณได้รับสินค้าแล้ว!</strong><span style="font-size:12px;opacity:0.8;">การแลกเปลี่ยนฝั่งคุณเสร็จสมบูรณ์</span></div>';
        cb.append(sb);
    }
REPLACE;

// Replace exactly string in content
$pos = strpos($content, $search_str);
if ($pos !== false) {
    $content = substr_replace($content, $replace_str, $pos, strlen($search_str));
    file_put_contents('C:/laragon/www/wanawan_project/pages/chat.php', $content);
    echo "Successfully updated remaining banners\n";
} else {
    echo "Could not find target string\n";
}
?>
