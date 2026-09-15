<?php
$file = 'c:/laragon/www/wanawan_project/pages/trade_condition_form.php';
$content = file_get_contents($file);

$replacements = [
    // 1. Container
    <<<'CSS'
        .container {
            max-width: 900px;
            margin: <?= $isIframe ? '20px auto' : '40px auto' ?>;
            padding: <?= $isIframe ? '20px' : '40px' ?>;
CSS => <<<'CSS'
        .container {
            max-width: 900px;
            margin: <?= $isIframe ? '0 auto' : '40px auto' ?>;
            padding: <?= $isIframe ? '15px' : '40px' ?>;
CSS,

    // 2. Header title
    <<<'CSS'
        .header-title { text-align: center; margin-bottom: <?= $isIframe ? '15px' : '40px' ?>; }
        .header-title h1 {
            font-size: <?= $isIframe ? '24px' : '32px' ?>;
            font-weight: 700;
            background: linear-gradient(135deg, #175564, #2F6E63);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 10px 0;
        }
        .header-title p { color: var(--text-muted); font-size: 16px; margin: 0; }
CSS => <<<'CSS'
        .header-title { text-align: center; margin-bottom: <?= $isIframe ? '10px' : '40px' ?>; }
        .header-title h1 {
            font-size: <?= $isIframe ? '20px' : '32px' ?>;
            font-weight: 700;
            background: linear-gradient(135deg, #175564, #2F6E63);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 <?= $isIframe ? '4px' : '10px' ?> 0;
        }
        .header-title p { color: var(--text-muted); font-size: <?= $isIframe ? '13px' : '16px' ?>; margin: 0; }
CSS,

    // 3. Status badge
    <<<'CSS'
        .status-badge {
            display: inline-block;
            background: #e0f2f1;
            color: #175564;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 15px;
        }
CSS => <<<'CSS'
        .status-badge {
            display: inline-block;
            background: #e0f2f1;
            color: #175564;
            padding: <?= $isIframe ? '4px 12px' : '6px 16px' ?>;
            border-radius: 20px;
            font-weight: 600;
            font-size: <?= $isIframe ? '12px' : '14px' ?>;
            margin-top: <?= $isIframe ? '8px' : '15px' ?>;
        }
CSS,

    // 4. Section title
    <<<'CSS'
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title span {
            display: flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; background: #175564; color: #fff; border-radius: 50%;
            font-size: 16px; font-weight: bold;
        }
CSS => <<<'CSS'
        .section-title {
            font-size: <?= $isIframe ? '16px' : '20px' ?>;
            font-weight: 600;
            margin: 0 0 <?= $isIframe ? '10px' : '20px' ?> 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title span {
            display: flex; align-items: center; justify-content: center;
            width: <?= $isIframe ? '24px' : '30px' ?>; height: <?= $isIframe ? '24px' : '30px' ?>; background: #175564; color: #fff; border-radius: 50%;
            font-size: <?= $isIframe ? '13px' : '16px' ?>; font-weight: bold;
        }
CSS,

    // 5. Button
    <<<'CSS'
        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #FF7B00, #FF5500);
            color: #fff;
            box-shadow: 0 4px 15px rgba(255, 123, 0, 0.3);
        }
        .btn-primary:hover { box-shadow: 0 6px 20px rgba(255, 123, 0, 0.4); transform: translateY(-1px); }
CSS => <<<'CSS'
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

        .btn-primary {
            background: linear-gradient(135deg, #175564, #2F6E63);
            color: #fff;
            box-shadow: 0 4px 15px rgba(23, 85, 100, 0.3);
        }
        .btn-primary:hover { box-shadow: 0 6px 20px rgba(23, 85, 100, 0.4); transform: translateY(-1px); }
CSS,

    // 6. Alert
    <<<'CSS'
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex; align-items: center; gap: 15px;
            animation: slideIn 0.3s ease-out;
        }
CSS => <<<'CSS'
        .alert {
            padding: <?= $isIframe ? '10px 15px' : '16px 20px' ?>;
            border-radius: 12px;
            margin-bottom: <?= $isIframe ? '15px' : '25px' ?>;
            font-size: <?= $isIframe ? '13px' : '16px' ?>;
            display: flex; align-items: center; gap: 15px;
            animation: slideIn 0.3s ease-out;
        }
CSS
];

foreach ($replacements as $old => $new) {
    $content = str_replace($old, $new, $content);
}

file_put_contents($file, $content);
echo "Applied shrink fixes successfully.\n";
