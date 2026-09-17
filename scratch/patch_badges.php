<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

$old = <<<HTML
                        <?php if(\$isQCPassed): ?>
                            <span class="status-pill success-text">ผ่าน QC ส่วนกลางแล้ว</span>
                        <?php elseif(\$request['status'] === 'pending' && \$tab === 'received'): ?>
                            <span class="status-pill pending-text">รอตอบรับภายใน 24 ชม.</span>
                        <?php endif; ?>
HTML;

$new = <<<HTML
                        <?php 
                        if (\$request['status'] === 'pending' && \$tab === 'received'): ?>
                            <span class="status-pill pending-text">รอตอบรับภายใน 24 ชม.</span>
                        <?php endif; ?>
                        
                        <?php if (\$request['trade_option'] === 'admin_qc' && \$isQCPassed): ?>
                            <span class="status-pill success-text">ผ่าน QC ส่วนกลางแล้ว</span>
                        <?php elseif (\$request['trade_option'] === 'direct_escrow' || \$request['trade_option'] === 'p2p'): ?>
                            <span class="status-pill" style="background:#e0f2fe; color:#0369a1;">ส่งพัสดุถึงกันโดยตรง</span>
                        <?php elseif (\$request['trade_option'] === 'meetup'): ?>
                            <span class="status-pill" style="background:#fef3c7; color:#b45309;">นัดรับสินค้า</span>
                        <?php endif; ?>
HTML;

$c = str_replace($old, $new, $c);
file_put_contents($file, $c);

echo "Added trade option badges.\n";
