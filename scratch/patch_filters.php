<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// 1. Replace the Select filters
$old_filters = <<< 'HTML'
            <div class="qc-filters">
                <span>คัดกรอง:</span>
                <select><option>สถานะ: ทั้งหมด (<?= count($reqs) ?>)</option></select>
                <select><option>ประเภท: ทั้งหมด</option></select>
                <button class="btn-outline" style="padding:6px 12px;"><i class="fas fa-filter"></i></button>
            </div>
HTML;

$new_filters = <<< 'HTML'
            <div class="qc-filters">
                <span>คัดกรอง:</span>
                <select id="qcStatusFilter">
                    <option value="all">สถานะ: ทั้งหมด (<?= count($reqs) ?>)</option>
                    <option value="pending_qc">รอส่งรายงาน QC</option>
                    <option value="waiting_customer">รอลูกค้ายืนยัน</option>
                    <option value="ready_to_ship">รอพิมพ์ใบปะหน้า</option>
                    <option value="shipped">จัดส่งเรียบร้อย</option>
                </select>
                <select id="qcTypeFilter">
                    <option value="all">ประเภท: ทั้งหมด</option>
                    <option value="admin_qc">ผ่านจุดตรวจส่วนกลาง</option>
                    <option value="direct_escrow">ส่งตรงให้กัน (P2P)</option>
                    <option value="meetup">นัดรับ (Meet-up)</option>
                </select>
                <button class="btn-outline" style="padding:6px 12px;" onclick="document.getElementById('qcSearchInput').value=''; document.getElementById('qcStatusFilter').value='all'; document.getElementById('qcTypeFilter').value='all'; document.getElementById('qcSearchInput').dispatchEvent(new Event('input'));"><i class="fas fa-undo"></i></button>
            </div>
HTML;
$content = str_replace($old_filters, $new_filters, $content);

// 2. Replace .qc-card loop with PHP evaluation for data attributes
$old_loop = <<< 'PHP'
                <?php foreach ($reqs as $r): ?>
                    <div class="qc-card">
                        <!-- Column 1 -->
PHP;

$new_loop = <<< 'PHP'
                <?php foreach ($reqs as $r): 
                    $data_status = 'pending_qc';
                    if ($r['requester_qc_acceptance_status'] === 'accepted' && $r['offer_qc_acceptance_status'] === 'accepted') {
                        if (!empty($r['admin_tracking_to_requester'])) {
                            $data_status = 'shipped';
                        } else {
                            $data_status = 'ready_to_ship';
                        }
                    } elseif ($r['admin_qc_requester_status'] !== 'pending' && $r['admin_qc_offer_status'] !== 'pending') {
                        $data_status = 'waiting_customer';
                    }
                    $data_type = $r['trade_option'];
                ?>
                    <div class="qc-card" data-status="<?= htmlspecialchars($data_status) ?>" data-type="<?= htmlspecialchars($data_type) ?>">
                        <!-- Column 1 -->
PHP;
$content = str_replace($old_loop, $new_loop, $content);


// 3. Replace the JS
$old_js = <<< 'JS'
        // Search Filter
        document.getElementById('qcSearchInput')?.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.qc-card');
            
            cards.forEach(card => {
                const textContent = card.innerText.toLowerCase();
                if (textContent.includes(term)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
JS;

$new_js = <<< 'JS'
        // Search & Filter Logic
        function filterQcCards() {
            const term = document.getElementById('qcSearchInput')?.value.toLowerCase() || '';
            const statusFilter = document.getElementById('qcStatusFilter')?.value || 'all';
            const typeFilter = document.getElementById('qcTypeFilter')?.value || 'all';
            const cards = document.querySelectorAll('.qc-card');
            
            cards.forEach(card => {
                const textContent = card.innerText.toLowerCase();
                const cardStatus = card.getAttribute('data-status');
                const cardType = card.getAttribute('data-type');
                
                let matchSearch = textContent.includes(term);
                let matchStatus = (statusFilter === 'all' || statusFilter === cardStatus);
                let matchType = (typeFilter === 'all' || typeFilter === cardType);
                
                if (matchSearch && matchStatus && matchType) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        document.getElementById('qcSearchInput')?.addEventListener('input', filterQcCards);
        document.getElementById('qcStatusFilter')?.addEventListener('change', filterQcCards);
        document.getElementById('qcTypeFilter')?.addEventListener('change', filterQcCards);
JS;

$content = str_replace($old_js, $new_js, $content);

file_put_contents($file, $content);
echo "Patched filters!";
