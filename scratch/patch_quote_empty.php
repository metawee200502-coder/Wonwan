<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

$oldQuote = '<?php if(trim($request[\'message\'] ?? \'\')): ?>
                        <div class="quote-box">
                            <div class="quote-icon">“</div>
                            <div><?=nl2br(htmlspecialchars($request[\'message\'])) ?>”</div>
                        </div>
                        <?php endif; ?>';

$newQuote = '                        <div class="quote-box">
                            <div class="quote-icon">“</div>
                            <?php if(trim($request[\'message\'] ?? \'\')): ?>
                                <div><?=nl2br(htmlspecialchars($request[\'message\'])) ?>”</div>
                            <?php else: ?>
                                <div style="color: #9ca3af; font-style: italic;">ไม่มีหมายเหตุแนบมา”</div>
                            <?php endif; ?>
                        </div>';

$c = str_replace($oldQuote, $newQuote, $c);
file_put_contents($file, $c);
echo "Patched message quote box with empty placeholder.\n";
