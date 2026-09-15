<?php
$file = 'c:/laragon/www/wanawan_project/pages/trade_condition_form.php';
$content = file_get_contents($file);

// Add scrollbar hiding logic to body
$oldBody = <<<'CSS'
        body {
            font-family: 'Prompt', sans-serif;
            background: <?= $isIframe ? 'transparent' : 'linear-gradient(135deg, #F0F4F8 0%, #DFE9F3 100%)' ?>;
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
        }
CSS;

$newBody = <<<'CSS'
        body {
            font-family: 'Prompt', sans-serif;
            background: <?= $isIframe ? 'transparent' : 'linear-gradient(135deg, #F0F4F8 0%, #DFE9F3 100%)' ?>;
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
            <?php if ($isIframe): ?>
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            <?php endif; ?>
        }
        <?php if ($isIframe): ?>
        body::-webkit-scrollbar {
            display: none; /* Chrome, Safari and Opera */
        }
        <?php endif; ?>
CSS;

$content = str_replace($oldBody, $newBody, $content);

// Add font-family: inherit to .btn
$oldBtn = <<<'CSS'
        .btn {
            padding: <?= $isIframe ? '12px 20px' : '16px 32px' ?>;
            border: none;
            border-radius: 12px;
            font-size: <?= $isIframe ? '14px' : '16px' ?>;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
CSS;

$newBtn = <<<'CSS'
        .btn {
            padding: <?= $isIframe ? '12px 20px' : '16px 32px' ?>;
            border: none;
            border-radius: 12px;
            font-size: <?= $isIframe ? '14px' : '16px' ?>;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
CSS;

$content = str_replace($oldBtn, $newBtn, $content);

file_put_contents($file, $content);
echo "Applied scrollbar and font fixes successfully.\n";
