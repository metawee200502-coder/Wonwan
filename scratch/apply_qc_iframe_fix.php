<?php
$file = 'c:/laragon/www/wanawan_project/pages/trade_qc_logistics_form.php';
$content = file_get_contents($file);

// 1. Add iframe logic
$content = str_replace(
    "\$requestId = (int)(\$_GET['request_id'] ?? \$_POST['request_id'] ?? 0);",
    "\$requestId = (int)(\$_GET['request_id'] ?? \$_POST['request_id'] ?? 0);\n\$isIframe = !empty(\$_GET['iframe']) || !empty(\$_POST['iframe']);",
    $content
);

// 2. Add hidden iframe field in forms
$content = str_replace(
    '<input type="hidden" name="action_type" value="upload_slip">',
    '<input type="hidden" name="action_type" value="upload_slip">' . "\n" . '<?php if($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>',
    $content
);
$content = str_replace(
    '<input type="hidden" name="action_type" value="submit_tracking">',
    '<input type="hidden" name="action_type" value="submit_tracking">' . "\n" . '<?php if($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>',
    $content
);

// 3. Close modal script for success messages
$content = str_replace(
    "echo \"<script>alert('\$success_msg'); window.location.href = '/pages/trade_qc_logistics_form.php?request_id=\$requestId';</script>\";",
    "if(\$isIframe){ echo \"<script>alert('\$success_msg'); window.parent.postMessage({action: 'close_condition_modal'}, '*');</script>\"; } else { echo \"<script>alert('\$success_msg'); window.location.href = '/pages/trade_qc_logistics_form.php?request_id=\$requestId';</script>\"; }",
    $content
);

// 4. Update CSS for iframe mode
$oldCss = <<<'CSS'
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            margin: 0; padding: 20px;
            color: var(--text-main);
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        h2 { margin-top: 0; color: #8b5266; text-align: center; margin-bottom: 24px; }
CSS;

$newCss = <<<'CSS'
        html, body {
            <?php if ($isIframe): ?>
            scrollbar-width: none;
            -ms-overflow-style: none;
            <?php endif; ?>
        }
        <?php if ($isIframe): ?>
        html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar { display: none; }
        * { scrollbar-width: none; }
        <?php endif; ?>

        body {
            font-family: 'Prompt', 'Inter', sans-serif;
            background: <?= $isIframe ? 'transparent' : 'var(--bg-color)' ?>;
            margin: 0; padding: <?= $isIframe ? '15px' : '20px' ?>;
            color: var(--text-main);
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: <?= $isIframe ? 'transparent' : '#fff' ?>;
            padding: <?= $isIframe ? '0' : '30px' ?>;
            border-radius: 16px;
            box-shadow: <?= $isIframe ? 'none' : '0 10px 30px rgba(0,0,0,0.05)' ?>;
        }
        h2 { margin-top: 0; color: #175564; text-align: center; margin-bottom: 24px; display: <?= $isIframe ? 'none' : 'block' ?>; }
CSS;
$content = str_replace($oldCss, $newCss, $content);

// Change font-family Inter to Prompt globally
$content = str_replace(
    '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">',
    '<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">',
    $content
);

file_put_contents($file, $content);
echo "Updated trade_qc_logistics_form.php for iframe display successfully.\n";
