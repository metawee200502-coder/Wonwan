<?php
$file = 'c:/laragon/www/wanawan_project/pages/trade_upload_slip.php';
$content = file_get_contents($file);

// 1. Add iframe logic at the top
$content = str_replace(
    "\$requestId = (int)(\$_GET['request_id'] ?? 0);",
    "\$requestId = (int)(\$_GET['request_id'] ?? 0);\n\$isIframe = !empty(\$_GET['iframe']);",
    $content
);

// 2. Add JS auto-close on success
$content = preg_replace(
    '/(<div class="alert alert-success">.*?<\/div>)/is',
    "$1\n        <?php if(\$isIframe): ?><script>setTimeout(() => window.parent.postMessage({action: 'close_condition_modal'}, '*'), 1500);</script><?php endif; ?>",
    $content
);

// 3. Update CSS styling
$oldCss = <<<'CSS'
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #fdf5f7; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(209,138,167,0.15); width: 100%; max-width: 400px; text-align: center; }
        .card h2 { color: #8b5266; margin-top: 0; }
        .amount { font-size: 32px; font-weight: bold; color: #c53030; margin: 15px 0; }
        .qr-placeholder { background: #f7fafc; border: 2px dashed #cbd5e0; padding: 40px; border-radius: 8px; margin-bottom: 20px; color: #a0aec0; }
        .btn { display: inline-block; width: 100%; padding: 12px; background: linear-gradient(135deg, #d18aa7, #b66f68); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; text-decoration: none; transition: 0.2s; box-sizing: border-box; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(209,138,167,0.3); }
        .btn-outline { background: #fff; color: #8b5266; border: 1px solid #d18aa7; margin-top: 10px; }
        .btn-outline:hover { background: #fdf5f7; }
CSS;

$newCss = <<<'CSS'
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body {
            <?php if ($isIframe): ?>
            scrollbar-width: none;
            -ms-overflow-style: none;
            <?php endif; ?>
        }
        <?php if ($isIframe): ?>
        html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar {
            display: none;
        }
        * { scrollbar-width: none; }
        <?php endif; ?>

        body { 
            font-family: 'Prompt', sans-serif; 
            background: <?= $isIframe ? 'transparent' : '#fdf5f7' ?>; 
            margin: 0; 
            padding: <?= $isIframe ? '15px' : '20px' ?>; 
            display: flex; justify-content: center; align-items: <?= $isIframe ? 'flex-start' : 'center' ?>; 
            min-height: 100vh; 
        }
        .card { 
            background: <?= $isIframe ? 'transparent' : '#fff' ?>; 
            padding: <?= $isIframe ? '0' : '30px' ?>; 
            border-radius: 12px; 
            box-shadow: <?= $isIframe ? 'none' : '0 4px 15px rgba(0,0,0,0.05)' ?>; 
            width: 100%; max-width: 400px; text-align: center; 
        }
        .card h2 { color: #175564; margin-top: 0; display: <?= $isIframe ? 'none' : 'block' ?>; }
        .amount { font-size: <?= $isIframe ? '28px' : '32px' ?>; font-weight: bold; color: #175564; margin: 10px 0; }
        .qr-placeholder { background: #f0fdfa; border: 2px dashed #175564; padding: <?= $isIframe ? '20px' : '40px' ?>; border-radius: 8px; margin-bottom: 20px; color: #175564; }
        .btn { display: inline-block; width: 100%; padding: <?= $isIframe ? '10px' : '12px' ?>; background: linear-gradient(135deg, #175564, #2F6E63); color: #fff; border: none; border-radius: 8px; font-size: <?= $isIframe ? '14px' : '16px' ?>; font-weight: 600; cursor: pointer; text-decoration: none; transition: 0.2s; box-sizing: border-box; font-family: inherit; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(23,85,100,0.3); }
        .btn-outline { background: #fff; color: #175564; border: 1px solid #175564; margin-top: 10px; }
        .btn-outline:hover { background: #f0fdfa; }
CSS;

$content = str_replace($oldCss, $newCss, $content);

// 4. Hide "Back to chat" button in iframe
$oldBackToChat = '<a href="/pages/chat.php?conversation=<?= urlencode($_GET[\'conversation_id\'] ?? \'\') ?>" class="btn btn-outline">กลับไปที่แชท</a>';
$newBackToChat = '<?php if(!$isIframe): ?><a href="/pages/chat.php?conversation=<?= urlencode($_GET[\'conversation_id\'] ?? \'\') ?>" class="btn btn-outline">กลับไปที่แชท</a><?php endif; ?>';
$content = str_replace($oldBackToChat, $newBackToChat, $content);

$oldCancel = '<a href="/pages/chat.php?conversation=<?= urlencode($_GET[\'conversation_id\'] ?? \'\') ?>" class="btn btn-outline" style="display:inline-block; text-align:center;">ยกเลิก</a>';
$newCancel = '<?php if(!$isIframe): ?><a href="/pages/chat.php?conversation=<?= urlencode($_GET[\'conversation_id\'] ?? \'\') ?>" class="btn btn-outline" style="display:inline-block; text-align:center;">ยกเลิก</a><?php endif; ?>';
$content = str_replace($oldCancel, $newCancel, $content);

file_put_contents($file, $content);
echo "Updated trade_upload_slip.php successfully.\n";
