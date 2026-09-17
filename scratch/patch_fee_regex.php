<?php
$file = 'c:\\laragon\\www\\wanawan_project\\pages\\trade_upload_slip.php';
$content = file_get_contents($file);
$content = preg_replace('/(\$pdo->prepare\("UPDATE trade_condition_forms SET deposit_status = \'approved\', deposit_approved_at = NOW\(\) WHERE form_id = \?"\)\s*->execute\(\[\$myCondition\[\'form_id\'\]\]\);)/', "$1\n\n                    \$orderNum = 'EXC-FEE-' . \$requestId . '-' . \$userId;\n                    \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status) VALUES (?, ?, ?, ?, ?, 'paid', 'pending')\")\n                        ->execute([\$userId, \$orderNum, \$requiredDeposit, \$requiredDeposit, \$requiredDeposit]);\n", $content);
file_put_contents($file, $content);

$file2 = 'c:\\laragon\\www\\wanawan_project\\admin\\escrow.php';
if(file_exists($file2)) {
    $content2 = file_get_contents($file2);
    $content2 = preg_replace('/(\$stmt = \$pdo->prepare\("UPDATE trade_condition_forms SET deposit_status = \'approved\', deposit_approved_at = NOW\(\) WHERE form_id = \?"\);\s*\$stmt->execute\(\[\$formId\]\);)/', "$1\n\n            \$form = \$pdo->prepare(\"SELECT request_id, owner_id, required_deposit_amount, deposit_slip_url FROM trade_condition_forms WHERE form_id = ?\");\n            \$form->execute([\$formId]);\n            \$f = \$form->fetch();\n            if (\$f && \$f['required_deposit_amount'] > 0) {\n                \$orderNum = 'EXC-FEE-' . \$f['request_id'] . '-' . \$f['owner_id'];\n                \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, slip_image_url) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', ?)\")->execute([\$f['owner_id'], \$orderNum, \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$f['deposit_slip_url']]);\n            }\n", $content2);
    file_put_contents($file2, $content2);
}

$file3 = 'c:\\laragon\\www\\wanawan_project\\admin\\qc_exchanges.php';
if(file_exists($file3)) {
    $content3 = file_get_contents($file3);
    $content3 = preg_replace('/(\$stmt = \$pdo->prepare\("UPDATE trade_condition_forms SET deposit_status = \?, deposit_approved_at = NOW\(\) WHERE form_id = \?"\);\s*\$stmt->execute\(\[\$status, \$formId\]\);)/', "$1\n\n                if (\$status === 'approved') {\n                    \$form = \$pdo->prepare(\"SELECT request_id, owner_id, required_deposit_amount, deposit_slip_url FROM trade_condition_forms WHERE form_id = ?\");\n                    \$form->execute([\$formId]);\n                    \$f = \$form->fetch();\n                    if (\$f && \$f['required_deposit_amount'] > 0) {\n                        \$orderNum = 'EXC-FEE-' . \$f['request_id'] . '-' . \$f['owner_id'];\n                        \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, slip_image_url) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', ?)\")->execute([\$f['owner_id'], \$orderNum, \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$f['deposit_slip_url']]);\n                    }\n                }\n", $content3);
    file_put_contents($file3, $content3);
}

echo "Done regex patch\n";
