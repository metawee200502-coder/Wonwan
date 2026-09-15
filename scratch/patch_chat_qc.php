<?php
$file = __DIR__ . '/../pages/chat.php';
$content = file_get_contents($file);

$searchMsgLoop = <<<HTML
<div class="messages"><?php foreach(\$messages as \$m):?><div class="bubble <?=\$m['sender_user_id']==\$userId?'mine':''?>"><?=nl2br(htmlspecialchars(\$m['message_text']))?><time><?=date('H:i',strtotime(\$m['created_at']))?></time></div><?php endforeach;?></div>
HTML;

$replaceMsgLoop = <<<HTML
<div class="messages">
<?php foreach(\$messages as \$m):?>
    <?php if ((\$m['msg_type'] ?? '') === 'admin_qc_report'): 
        \$meta = json_decode(\$m['metadata'], true);
        \$isForMe = (\$meta['item_owner_id'] != \$userId); 
        \$notes = explode("\\n", \$m['message_text']);
        \$adminNotes = str_replace('หมายเหตุ: ', '', \$notes[2] ?? '');
    ?>
        <div class="bubble admin-report" style="max-width:100%; width:100%; background:#f8fafc; border:1px solid #cbd5e1; border-radius:12px; margin-bottom:15px; padding:15px;">
            <strong style="color:#0f172a; font-size:15px; display:block; margin-bottom:10px;"><i class="fas fa-clipboard-check"></i> รายงานตรวจสอบสภาพสินค้าจากส่วนกลาง</strong>
            
            <div style="background:#fff; padding:12px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:12px;">
                <p style="margin:0 0 8px 0; font-size:14px;"><strong>ผลการตรวจสอบ:</strong> <span style="color:<?= \$meta['is_match'] ? '#16a34a' : '#dc2626' ?>; font-weight:bold;"><?= \$meta['is_match'] ? 'ตรงปก / ผ่าน' : 'ไม่ตรงปก / มีปัญหา' ?></span></p>
                <p style="margin:0; font-size:14px; color:#475569;"><strong>หมายเหตุจากแอดมิน:</strong><br><?= nl2br(htmlspecialchars(\$adminNotes)) ?></p>
            </div>
            
            <?php if (!empty(\$meta['photos'])): ?>
            <div style="display:flex; gap:8px; margin-bottom:15px; overflow-x:auto;">
                <?php foreach (\$meta['photos'] as \$p): ?>
                    <img src="<?= htmlspecialchars(\$p) ?>" style="height:100px; border-radius:6px; border:1px solid #cbd5e1; cursor:pointer;" onclick="window.open(this.src, '_blank')">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (\$isForMe): ?>
                <div style="background:#fff7ed; padding:12px; border-radius:8px; border:1px solid #fed7aa;">
                    <p style="margin:0 0 10px 0; font-size:13px; color:#9a3412; font-weight:bold;">กรุณายืนยันการรับสินค้าในสภาพนี้</p>
                    <form method="POST" action="/pages/trade_qc_logistics_form.php" style="display:flex; gap:10px;">
                        <input type="hidden" name="action" value="user_qc_decision">
                        <input type="hidden" name="request_id" value="<?= \$meta['request_id'] ?>">
                        <input type="hidden" name="side" value="<?= \$meta['side'] ?>">
                        <input type="hidden" name="conversation_id" value="<?= \$conversationId ?>">
                        <button type="submit" name="decision" value="accepted" class="action-btn" style="flex:1; background:#22c55e; color:#fff; border:none; padding:8px; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="return confirm('ยืนยันยอมรับสภาพสินค้านี้ใช่หรือไม่?');">ยอมรับสภาพสินค้า</button>
                        <button type="submit" name="decision" value="rejected" class="action-btn" style="flex:1; background:#ef4444; color:#fff; border:none; padding:8px; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="return confirm('คุณต้องการปฏิเสธและยกเลิกการแลกเปลี่ยนนี้ใช่หรือไม่?');">ปฏิเสธ (ยกเลิกแลกเปลี่ยน)</button>
                    </form>
                </div>
            <?php else: ?>
                <div style="background:#f1f5f9; padding:10px; border-radius:8px; text-align:center; font-size:13px; color:#64748b;">
                    รอการตัดสินใจจากอีกฝ่าย
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="bubble <?=\$m['sender_user_id']==\$userId?'mine':''?>">
            <?=nl2br(htmlspecialchars(\$m['message_text']))?>
            <time><?=date('H:i',strtotime(\$m['created_at']))?></time>
        </div>
    <?php endif; ?>
<?php endforeach;?>
</div>
HTML;

$content = str_replace($searchMsgLoop, $replaceMsgLoop, $content);
file_put_contents($file, $content);
echo "Chat messages rendering patched.\n";
