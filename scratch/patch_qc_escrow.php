<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// 1. Add fields to SELECT
$search_select = "c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects, c_off.defects_description as off_cond_defects_desc, c_off.notes as off_cond_notes,
           c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects, c_req.defects_description as req_cond_defects_desc, c_req.notes as req_cond_notes";
$replace_select = $search_select . ",
           c_off.deposit_status as off_deposit_status, c_off.deposit_slip_url as off_deposit_slip_url,
           c_req.deposit_status as req_deposit_status, c_req.deposit_slip_url as req_deposit_slip_url";

$content = str_replace($search_select, $replace_select, $content);

// 2. Prepare variables inside foreach
$search_foreach = "<?php foreach (\$reqs as \$r): ?>";
$replace_foreach = "<?php foreach (\$reqs as \$r): 
    \$isP2P = (\$r['trade_option'] === 'direct_escrow');
    \$req_slip_status = \$isP2P ? (\$r['req_deposit_status'] ?: 'pending') : (\$r['requester_slip_status'] ?: 'pending');
    \$req_slip_url = \$isP2P ? \$r['req_deposit_slip_url'] : \$r['requester_slip_url'];
    
    \$off_slip_status = \$isP2P ? (\$r['off_deposit_status'] ?: 'pending') : (\$r['offer_slip_status'] ?: 'pending');
    \$off_slip_url = \$isP2P ? \$r['off_deposit_slip_url'] : \$r['offer_slip_url'];
?>";

$content = str_replace($search_foreach, $replace_foreach, $content);

// 3. Replace requester slip usages
$content = str_replace("\$r['requester_slip_status']", "\$req_slip_status", $content);
$content = str_replace("\$r['requester_slip_url']", "\$req_slip_url", $content);

// 4. Replace offer slip usages
$content = str_replace("\$r['offer_slip_status']", "\$off_slip_status", $content);
$content = str_replace("\$r['offer_slip_url']", "\$off_slip_url", $content);

// 5. Replace approve buttons
// For requester
$search_btn_req = "<?php if (\$req_slip_status === 'pending' && !empty(\$req_slip_url)): ?>
                                    <form method=\"POST\" style=\"margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light); display:flex; gap:5px; justify-content:flex-end;\">";
$replace_btn_req = "<?php if (\$req_slip_status === 'reviewing' && \$isP2P): ?>
                                    <div style=\"margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light); text-align:right;\">
                                        <a href=\"escrow.php?tab=p2p\" style=\"font-size:11px; color:#91576d; text-decoration:underline;\">ไปที่เมนู P2P Escrow เพื่ออนุมัติ</a>
                                    </div>
                                <?php elseif (\$req_slip_status === 'pending' && !empty(\$req_slip_url) && !\$isP2P): ?>
                                    <form method=\"POST\" style=\"margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light); display:flex; gap:5px; justify-content:flex-end;\">";

$content = str_replace($search_btn_req, $replace_btn_req, $content);

// For offerer
$search_btn_off = "<?php if (\$off_slip_status === 'pending' && !empty(\$off_slip_url)): ?>
                                    <form method=\"POST\" style=\"margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light); display:flex; gap:5px; justify-content:flex-end;\">";
$replace_btn_off = "<?php if (\$off_slip_status === 'reviewing' && \$isP2P): ?>
                                    <div style=\"margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light); text-align:right;\">
                                        <a href=\"escrow.php?tab=p2p\" style=\"font-size:11px; color:#91576d; text-decoration:underline;\">ไปที่เมนู P2P Escrow เพื่ออนุมัติ</a>
                                    </div>
                                <?php elseif (\$off_slip_status === 'pending' && !empty(\$off_slip_url) && !\$isP2P): ?>
                                    <form method=\"POST\" style=\"margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light); display:flex; gap:5px; justify-content:flex-end;\">";

$content = str_replace($search_btn_off, $replace_btn_off, $content);

file_put_contents($file, $content);
echo "Patched admin/qc_exchanges.php\n";
