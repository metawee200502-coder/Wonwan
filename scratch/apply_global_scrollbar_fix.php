<?php
$file = 'c:/laragon/www/wanawan_project/pages/trade_condition_form.php';
$content = file_get_contents($file);

$oldStyle = <<<'CSS'
        * { box-sizing: border-box; }

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

$newStyle = <<<'CSS'
        * { box-sizing: border-box; }

        html, body {
            <?php if ($isIframe): ?>
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            <?php endif; ?>
        }
        <?php if ($isIframe): ?>
        html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar {
            display: none; /* Chrome, Safari and Opera */
        }
        * {
            scrollbar-width: none;
        }
        <?php endif; ?>

        body {
            font-family: 'Prompt', sans-serif;
            background: <?= $isIframe ? 'transparent' : 'linear-gradient(135deg, #F0F4F8 0%, #DFE9F3 100%)' ?>;
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
        }
CSS;

$content = str_replace($oldStyle, $newStyle, $content);

file_put_contents($file, $content);
echo "Updated scrollbar CSS globally in form successfully.\n";
