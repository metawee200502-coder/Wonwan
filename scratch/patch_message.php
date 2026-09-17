<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

$oldQuote = '<div class="quote-box">
                            <div class="quote-icon">“</div>
                            <div><?=nl2br(htmlspecialchars($request[\'message\']?:\'สนใจแลกเปลี่ยนหนังสือรายการนี้ พร้อมจัดส่งผ่าน Wanawan Hub ทันทีครับ\')) ?>”</div>
                        </div>';

$newQuote = '<?php if(trim($request[\'message\'])): ?>
                        <div class="quote-box">
                            <div class="quote-icon">“</div>
                            <div><?=nl2br(htmlspecialchars($request[\'message\'])) ?>”</div>
                        </div>
                        <?php endif; ?>';

$c = str_replace($oldQuote, $newQuote, $c);
file_put_contents($file, $c);
echo "Patched message quote box.\n";
