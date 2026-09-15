<?php
$file = 'C:/laragon/www/wanawan_project/pages/handlers/chat_messages_poll.php';
$content = file_get_contents($file);

$targetPhp = "    if (preg_match('/\[PROPOSE_FULL_TRADE:([^\]]+)\]/i', \$text, \$matches)) {
        \$isSystem = true;
        \$parts = explode(':', trim(\$matches[1]));
        \$opt = \$parts[0] ?? '';
        \$days = \$parts[1] ?? '';
        
        \$alreadySet = (\$er && \$er['trade_option'] === \$opt && (\$days === '' || \$er['shipping_deadline_days'] == \$days));
        \$optNamesList = ['direct_escrow'=>'ส่งตรงให้กัน (P2P Escrow)', 'meetup'=>'นัดรับ (Meet-up)', 'admin_qc'=>'ส่งผ่านส่วนกลาง (Admin QC)'];
        \$displayName = \$optNamesList[\$opt] ?? \$opt;
        
        \$senderStmt=\$pdo->prepare(\"SELECT COALESCE(NULLIF(display_name,''),email,'ผู้ใช้'), profile_image FROM users WHERE user_id=?\");
        \$senderStmt->execute([\$message['sender_user_id']]);
        \$senderInfo = \$senderStmt->fetch(PDO::FETCH_NUM);
        \$senderName = \$senderInfo[0] ?? 'ผู้ใช้';
        \$senderImg = \$senderInfo[1] ?? '/assets/image/default-avatar.png';
        
        \$sysContent = '<div style=\"background:linear-gradient(135deg, #fdf5f7 0%, #f7e6eb 100%);border:1px solid #f2dbe4;padding:14px 16px;border-radius:14px;color:#8b5266;font-size:13px;max-width:300px;box-shadow:0 4px 12px rgba(169,85,117,0.06);font-family:inherit;\">' .
            '<div style=\"display:flex;align-items:center;gap:8px;margin-bottom:10px;\">' .
            '<img src=\"'.htmlspecialchars((string)\$senderImg).'\" style=\"width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 4px rgba(169,85,117,0.1)\">' .
            '<div style=\"line-height:1.2\"><strong style=\"font-size:14px;color:#a95575;font-weight:600;\">'.htmlspecialchars((string)\$senderName).'</strong><br><small style=\"color:#b87d90;font-size:11px;\">เสนอเงื่อนไขการแลกเปลี่ยน</small></div>' .
            '</div>' .
            '<div style=\"background:rgba(255,255,255,0.85);padding:10px;border-radius:8px;border:1px solid #f2dbe4;margin-bottom:12px;font-size:13px;color:#8b5266;\">' .
            '<div style=\"display:flex;justify-content:space-between;margin-bottom:4px;\"><span>รูปแบบ:</span> <strong style=\"color:#a95575;\">'.\$displayName.'</strong></div>' .
            (\$days ? '<div style=\"display:flex;justify-content:space-between;\"><span>ระยะเวลา:</span> <strong style=\"color:#a95575;\">ภายใน '.\$days.' วัน</strong></div>' : '') .
            '</div>' .
            (\$isMine ? '<div style=\"text-align:center;font-size:12px;color:#a95575;background:rgba(169,85,117,0.1);padding:6px;border-radius:6px;font-family:inherit;\">⏳ รอการตอบรับ...</div>' : 
                (\$alreadySet ? '<div style=\"text-align:center;font-size:13px;color:#059669;background:#ecfdf5;padding:8px;border-radius:6px;font-weight:600;border:1px solid #a7f3d0;font-family:inherit;\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"3\" stroke-linecap=\"round\" stroke-linejoin=\"round\" style=\"vertical-align:text-bottom;margin-right:2px;\"><polyline points=\"20 6 9 17 4 12\"></polyline></svg> คุณยอมรับแล้ว</div>' : 
                '<form method=\"post\" style=\"display:flex;gap:6px;\">'.
                '<input type=\"hidden\" name=\"csrf_token\" value=\"'.htmlspecialchars(\$_SESSION['chat_csrf'] ?? '').'\">'.
                '<input type=\"hidden\" name=\"trade_option\" value=\"'.htmlspecialchars(\$opt).'\">'.
                '<input type=\"hidden\" name=\"deadline_days\" value=\"'.htmlspecialchars(\$days).'\">'.
                '<input type=\"hidden\" name=\"request_id\" value=\"'.htmlspecialchars(\$exchangeRequestId).'\">'.
                '<button style=\"flex:1;font-size:13px;background:#fff;border:1px solid #d3948b;color:#d3948b;padding:8px;border-radius:6px;font-weight:600;cursor:pointer;transition:transform 0.2s;font-family:inherit;\" name=\"reject_full_trade\" onmouseover=\"this.style.transform=\'scale(1.02)\'\" onmouseout=\"this.style.transform=\'scale(1)\'\">ปฏิเสธ</button>'.
                '<button style=\"flex:2;font-size:13px;background:linear-gradient(135deg, #d18aa7, #b66f68);border:0;color:#fff;padding:8px;border-radius:6px;font-weight:600;cursor:pointer;box-shadow:0 3px 8px rgba(209,138,167,0.3);transition:transform 0.2s;font-family:inherit;\" name=\"accept_full_trade\" onmouseover=\"this.style.transform=\'scale(1.02)\'\" onmouseout=\"this.style.transform=\'scale(1)\'\">ตกลง</button>'.
                '</form>')
            ).
            '</div>';
    } elseif (preg_match('/\[ACCEPTED_FULL_TRADE:([^\]]+)\]/i', \$text, \$matches)) {
        \$isSystem = true;
        \$parts = explode(':', trim(\$matches[1]));
        \$opt = \$parts[0] ?? '';
        \$days = \$parts[1] ?? '';
        \$optNamesList = ['direct_escrow'=>'ส่งตรงให้กัน (P2P Escrow)', 'meetup'=>'นัดรับ (Meet-up)', 'admin_qc'=>'ส่งผ่านส่วนกลาง (Admin QC)'];
        \$displayName = \$optNamesList[\$opt] ?? \$opt;
        \$sysContent = '<div style=\"background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;\">'.
            '<span style=\"display:inline-flex;align-items:center;vertical-align:middle;margin-right:4px;\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"3\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"20 6 9 17 4 12\"></polyline></svg></span> <strong>ตกลงเงื่อนไขแล้ว:</strong> <span style=\"background:#fff;padding:3px 6px;border-radius:6px;margin-left:4px;color:#15803d;font-size:12px;\">'.\$displayName.(\$days?' (ภายใน '.\$days.' วัน)':'').'</span>'.
            '</div>';";

$replacementPhp = "    if (preg_match('/\[PROPOSE_FULL_TRADE:([^\]]+)\]/i', \$text, \$matches)) {
        \$isSystem = true;
        \$parts = explode(':', trim(\$matches[1]));
        \$opt = \$parts[0] ?? '';
        \$days = \$parts[1] ?? '';
        \$mDate = \$parts[1] ?? '';
        \$mTime = \$parts[2] ?? '';
        \$mLoc = \$parts[3] ?? '';
        
        \$alreadySet = false;
        if (\$opt === 'meetup') {
            \$alreadySet = (\$er && \$er['trade_option'] === \$opt && \$er['meetup_date'] === \$mDate && \$er['meetup_time'] === \$mTime && \$er['meetup_location'] === \$mLoc);
        } else {
            \$alreadySet = (\$er && \$er['trade_option'] === \$opt && (\$days === '' || \$er['shipping_deadline_days'] == \$days));
        }
        
        \$optNamesList = ['direct_escrow'=>'ส่งตรงให้กัน (P2P Escrow)', 'meetup'=>'นัดรับ (Meet-up)', 'admin_qc'=>'ส่งผ่านส่วนกลาง (Admin QC)'];
        \$displayName = \$optNamesList[\$opt] ?? \$opt;
        
        \$senderStmt=\$pdo->prepare(\"SELECT COALESCE(NULLIF(display_name,''),email,'ผู้ใช้'), profile_image FROM users WHERE user_id=?\");
        \$senderStmt->execute([\$message['sender_user_id']]);
        \$senderInfo = \$senderStmt->fetch(PDO::FETCH_NUM);
        \$senderName = \$senderInfo[0] ?? 'ผู้ใช้';
        \$senderImg = \$senderInfo[1] ?? '/assets/image/default-avatar.png';
        
        \$sysContent = '<div style=\"background:linear-gradient(135deg, #fdf5f7 0%, #f7e6eb 100%);border:1px solid #f2dbe4;padding:14px 16px;border-radius:14px;color:#8b5266;font-size:13px;max-width:300px;box-shadow:0 4px 12px rgba(169,85,117,0.06);font-family:inherit;\">' .
            '<div style=\"display:flex;align-items:center;gap:8px;margin-bottom:10px;\">' .
            '<img src=\"'.htmlspecialchars((string)\$senderImg).'\" style=\"width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 4px rgba(169,85,117,0.1)\">' .
            '<div style=\"line-height:1.2\"><strong style=\"font-size:14px;color:#a95575;font-weight:600;\">'.htmlspecialchars((string)\$senderName).'</strong><br><small style=\"color:#b87d90;font-size:11px;\">เสนอเงื่อนไขการแลกเปลี่ยน</small></div>' .
            '</div>' .
            '<div style=\"background:rgba(255,255,255,0.85);padding:10px;border-radius:8px;border:1px solid #f2dbe4;margin-bottom:12px;font-size:13px;color:#8b5266;\">' .
            '<div style=\"display:flex;justify-content:space-between;margin-bottom:4px;\"><span>รูปแบบ:</span> <strong style=\"color:#a95575;\">'.\$displayName.'</strong></div>' .
            (\$opt === 'meetup' ? 
                '<div style=\"display:flex;justify-content:space-between;margin-bottom:4px;\"><span>วันที่:</span> <strong style=\"color:#a95575;\">'.htmlspecialchars(\$mDate).'</strong></div>' .
                '<div style=\"display:flex;justify-content:space-between;margin-bottom:4px;\"><span>เวลา:</span> <strong style=\"color:#a95575;\">'.htmlspecialchars(\$mTime).' น.</strong></div>' .
                '<div style=\"display:flex;justify-content:space-between;\"><span>สถานที่:</span> <strong style=\"color:#a95575;\">'.htmlspecialchars(\$mLoc).'</strong></div>'
            : 
                (\$days ? '<div style=\"display:flex;justify-content:space-between;\"><span>ระยะเวลา:</span> <strong style=\"color:#a95575;\">ภายใน '.\$days.' วัน</strong></div>' : '') 
            ) .
            '</div>' .
            (\$isMine ? '<div style=\"text-align:center;font-size:12px;color:#a95575;background:rgba(169,85,117,0.1);padding:6px;border-radius:6px;font-family:inherit;\">⏳ รอการตอบรับ...</div>' : 
                (\$alreadySet ? '<div style=\"text-align:center;font-size:13px;color:#059669;background:#ecfdf5;padding:8px;border-radius:6px;font-weight:600;border:1px solid #a7f3d0;font-family:inherit;\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"3\" stroke-linecap=\"round\" stroke-linejoin=\"round\" style=\"vertical-align:text-bottom;margin-right:2px;\"><polyline points=\"20 6 9 17 4 12\"></polyline></svg> คุณยอมรับแล้ว</div>' : 
                '<form method=\"post\" style=\"display:flex;gap:6px;\">'.
                '<input type=\"hidden\" name=\"csrf_token\" value=\"'.htmlspecialchars(\$_SESSION['chat_csrf'] ?? '').'\">'.
                '<input type=\"hidden\" name=\"trade_option\" value=\"'.htmlspecialchars(\$opt).'\">'.
                '<input type=\"hidden\" name=\"deadline_days\" value=\"'.htmlspecialchars(\$days).'\">'.
                '<input type=\"hidden\" name=\"meetup_date\" value=\"'.htmlspecialchars(\$mDate).'\">'.
                '<input type=\"hidden\" name=\"meetup_time\" value=\"'.htmlspecialchars(\$mTime).'\">'.
                '<input type=\"hidden\" name=\"meetup_location\" value=\"'.htmlspecialchars(\$mLoc).'\">'.
                '<input type=\"hidden\" name=\"request_id\" value=\"'.htmlspecialchars(\$exchangeRequestId).'\">'.
                '<button style=\"flex:1;font-size:13px;background:#fff;border:1px solid #d3948b;color:#d3948b;padding:8px;border-radius:6px;font-weight:600;cursor:pointer;transition:transform 0.2s;font-family:inherit;\" name=\"reject_full_trade\" onmouseover=\"this.style.transform=\'scale(1.02)\'\" onmouseout=\"this.style.transform=\'scale(1)\'\">ปฏิเสธ</button>'.
                '<button style=\"flex:2;font-size:13px;background:linear-gradient(135deg, #d18aa7, #b66f68);border:0;color:#fff;padding:8px;border-radius:6px;font-weight:600;cursor:pointer;box-shadow:0 3px 8px rgba(209,138,167,0.3);transition:transform 0.2s;font-family:inherit;\" name=\"accept_full_trade\" onmouseover=\"this.style.transform=\'scale(1.02)\'\" onmouseout=\"this.style.transform=\'scale(1)\'\">ตกลง</button>'.
                '</form>')
            ).
            '</div>';
    } elseif (preg_match('/\[ACCEPTED_FULL_TRADE:([^\]]+)\]/i', \$text, \$matches)) {
        \$isSystem = true;
        \$parts = explode(':', trim(\$matches[1]));
        \$opt = \$parts[0] ?? '';
        \$days = \$parts[1] ?? '';
        \$mDate = \$parts[1] ?? '';
        \$mTime = \$parts[2] ?? '';
        \$mLoc = \$parts[3] ?? '';
        \$optNamesList = ['direct_escrow'=>'ส่งตรงให้กัน (P2P Escrow)', 'meetup'=>'นัดรับ (Meet-up)', 'admin_qc'=>'ส่งผ่านส่วนกลาง (Admin QC)'];
        \$displayName = \$optNamesList[\$opt] ?? \$opt;
        
        \$detailStr = '';
        if (\$opt === 'meetup') {
            \$detailStr = \"(\$mDate \$mTime น. @ \$mLoc)\";
        } else if (\$days) {
            \$detailStr = \"(ภายใน \$days วัน)\";
        }
        
        \$sysContent = '<div style=\"background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;\">'.
            '<span style=\"display:inline-flex;align-items:center;vertical-align:middle;margin-right:4px;\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"3\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"20 6 9 17 4 12\"></polyline></svg></span> <strong>ตกลงเงื่อนไขแล้ว:</strong> <span style=\"background:#fff;padding:3px 6px;border-radius:6px;margin-left:4px;color:#15803d;font-size:12px;\">'.\$displayName.' '.\$detailStr.'</span>'.
            '</div>';";

$content = str_replace($targetPhp, $replacementPhp, $content);
file_put_contents($file, $content);
echo "Updated chat_messages_poll.php successfully.";
