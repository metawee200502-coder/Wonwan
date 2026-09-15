<?php
$file = 'c:/laragon/www/wanawan_project/pages/trade_qc_logistics_form.php';
$content = file_get_contents($file);

// 1. Update CSS variables
$content = str_replace('--primary: #FF7B00;', '--primary: #175564;', $content);
$content = str_replace('--primary-dark: #E66F00;', '--primary-dark: #2F6E63;', $content);

// 2. Update button gradient and font-family
$content = str_replace(
    'background: linear-gradient(135deg, #FF7B00, #E66F00);',
    'background: linear-gradient(135deg, #175564, #2F6E63); font-family: \'Prompt\', sans-serif;',
    $content
);

// 3. Update step title color
$content = str_replace('color: #b45309;', 'color: #175564;', $content);
$content = str_replace('color: #059669;', 'color: #2F6E63;', $content); // completed color

// 4. Update info box colors to match theme
$content = str_replace('background: #fff7ed;', 'background: #f0fdfa;', $content);
$content = str_replace('border: 1px solid #fed7aa;', 'border: 1px solid #ccfbf1;', $content);
$content = str_replace('color: #d97706;', 'color: #175564;', $content); // bank account number

// 5. Replace emojis with SVG icons
// ✅ 
$checkIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
$content = str_replace('✅', $checkIcon, $content);

// ❌
$xIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
$content = str_replace('❌', $xIcon, $content);

// 🎉
$partyIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M5.8 11.3 2 22l10.7-3.79"></path><path d="M4 3h.01"></path><path d="M22 8h.01"></path><path d="M15 2h.01"></path><path d="M22 20h.01"></path><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"></path><path d="m22 13-.82-.33c-.86-.39-1.67.14-1.92 1.03v0c-.2.74-.93 1.18-1.7 1.11h-.14c-.86-.07-1.63.5-1.78 1.35L15 18"></path><path d="m11 15 8-8"></path></svg>';
$content = str_replace('🎉', $partyIcon, $content);

// 📸
$cameraIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>';
$content = str_replace('📸', $cameraIcon, $content);

// ⏳
$waitIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M5 22h14"></path><path d="M5 2h14"></path><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path></svg>';
$content = str_replace('⏳', $waitIcon, $content);

// 📦
$boxIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>';
$content = str_replace('📦', $boxIcon, $content);


file_put_contents($file, $content);
echo "Updated trade_qc_logistics_form.php UI successfully.\n";
