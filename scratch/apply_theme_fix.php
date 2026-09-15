<?php
$file = 'c:/laragon/www/wanawan_project/pages/trade_condition_form.php';
$content = file_get_contents($file);

// 1. Font
$content = str_replace(
    '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">',
    '<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">',
    $content
);
$content = str_replace("font-family: 'Inter', 'Sarabun', sans-serif;", "font-family: 'Prompt', sans-serif;", $content);

// 2. Colors
$content = str_replace('--primary: #FF7B00;', '--primary: #175564;', $content);
$content = str_replace('--primary-dark: #E66F00;', '--primary-dark: #2F6E63;', $content);
$content = str_replace('background: linear-gradient(135deg, #FF7B00, #FF4500);', 'background: linear-gradient(135deg, #175564, #2F6E63);', $content);
$content = str_replace('background: #FFF0E6;', 'background: #e0f2f1;', $content);
$content = str_replace('color: #FF7B00;', 'color: #175564;', $content);
$content = str_replace('border-color: #FF7B00;', 'border-color: #175564;', $content);
$content = str_replace('background: #FFF9F5;', 'background: #f0fdfa;', $content);
$content = str_replace('background: #FF7B00;', 'background: #175564;', $content);

// 3. Emojis to SVG
$cameraIcon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:5px; color:var(--primary);"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>';
$bookIcon = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:5px; color:var(--primary);"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>';
$content = str_replace('📸', $cameraIcon, $content);
$content = str_replace('📷', $cameraIcon, $content);
$content = str_replace('📚', $bookIcon, $content);

// 4. Header title size
$oldH1 = <<<'CSS'
        .header-title { text-align: center; margin-bottom: 40px; }
        .header-title h1 {
            font-size: 32px;
CSS;
$newH1 = <<<'CSS'
        .header-title { text-align: center; margin-bottom: <?= $isIframe ? '15px' : '40px' ?>; }
        .header-title h1 {
            font-size: <?= $isIframe ? '24px' : '32px' ?>;
CSS;
$content = str_replace($oldH1, $newH1, $content);

file_put_contents($file, $content);
echo "Theme applied successfully.\n";
