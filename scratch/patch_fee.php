<?php
$file = 'c:\\laragon\\www\\wanawan_project\\pages\\trade_upload_slip.php';
$content = file_get_contents($file);
$find1 = "\$pdo->prepare(\"UPDATE trade_condition_forms SET deposit_status = 'approved', deposit_approved_at = NOW() WHERE form_id = ?\")\n                        ->execute([\$myCondition['form_id']]);";
$replace1 = "\$pdo->prepare(\"UPDATE trade_condition_forms SET deposit_status = 'approved', deposit_approved_at = NOW() WHERE form_id = ?\")\n                        ->execute([\$myCondition['form_id']]);\n                        \n                    // Create pending fee record in orders\n                    \$orderNum = 'EXC-FEE-' . \$requestId . '-' . \$userId;\n                    \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status) VALUES (?, ?, ?, ?, ?, 'paid', 'pending')\")\n                        ->execute([\$userId, \$orderNum, \$requiredDeposit, \$requiredDeposit, \$requiredDeposit]);";
if (strpos($content, $find1) !== false) {
    file_put_contents($file, str_replace($find1, $replace1, $content));
    echo "Patched trade_upload_slip.php\n";
}

$file2 = 'c:\\laragon\\www\\wanawan_project\\admin\\escrow.php';
if (file_exists($file2)) {
    $content2 = file_get_contents($file2);
    $find2 = "\$stmt = \$pdo->prepare(\"UPDATE trade_condition_forms SET deposit_status = 'approved', deposit_approved_at = NOW() WHERE form_id = ?\");\n            \$stmt->execute([\$formId]);";
    $replace2 = "\$stmt = \$pdo->prepare(\"UPDATE trade_condition_forms SET deposit_status = 'approved', deposit_approved_at = NOW() WHERE form_id = ?\");\n            \$stmt->execute([\$formId]);\n            \n            // Create pending fee record\n            \$form = \$pdo->prepare(\"SELECT request_id, owner_id, required_deposit_amount, deposit_slip_url FROM trade_condition_forms WHERE form_id = ?\");\n            \$form->execute([\$formId]);\n            \$f = \$form->fetch();\n            if (\$f && \$f['required_deposit_amount'] > 0) {\n                \$orderNum = 'EXC-FEE-' . \$f['request_id'] . '-' . \$f['owner_id'];\n                \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, slip_image_url) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', ?)\")->execute([\$f['owner_id'], \$orderNum, \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$f['deposit_slip_url']]);\n            }";
    if (strpos($content2, $find2) !== false) {
        file_put_contents($file2, str_replace($find2, $replace2, $content2));
        echo "Patched admin/escrow.php\n";
    }
}

$file4 = 'c:\\laragon\\www\\wanawan_project\\admin\\qc_exchanges.php';
if (file_exists($file4)) {
    $content4 = file_get_contents($file4);
    $find4 = "\$stmt = \$pdo->prepare(\"UPDATE trade_condition_forms SET deposit_status = ?, deposit_approved_at = NOW() WHERE form_id = ?\");\n                \$stmt->execute([\$status, \$formId]);";
    $replace4 = "\$stmt = \$pdo->prepare(\"UPDATE trade_condition_forms SET deposit_status = ?, deposit_approved_at = NOW() WHERE form_id = ?\");\n                \$stmt->execute([\$status, \$formId]);\n                \n                if (\$status === 'approved') {\n                    \$form = \$pdo->prepare(\"SELECT request_id, owner_id, required_deposit_amount, deposit_slip_url FROM trade_condition_forms WHERE form_id = ?\");\n                    \$form->execute([\$formId]);\n                    \$f = \$form->fetch();\n                    if (\$f && \$f['required_deposit_amount'] > 0) {\n                        \$orderNum = 'EXC-FEE-' . \$f['request_id'] . '-' . \$f['owner_id'];\n                        \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, slip_image_url) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', ?)\")->execute([\$f['owner_id'], \$orderNum, \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$f['deposit_slip_url']]);\n                    }\n                }";
    if (strpos($content4, $find4) !== false) {
        file_put_contents($file4, str_replace($find4, $replace4, $content4));
        echo "Patched admin/qc_exchanges.php\n";
    }
}

$file3 = 'c:\\laragon\\www\\wanawan_project\\pages\\exchange_requests.php';
$content3 = file_get_contents($file3);
$find3 = "\$pdo->prepare(\"UPDATE exchanges SET status='completed' WHERE exchange_id IN (?,?)\")->execute([(int)\$request['exchange_id'],(int)(\$request['offered_exchange_id']??0)]);";
$replace3 = "\$pdo->prepare(\"UPDATE exchanges SET status='completed' WHERE exchange_id IN (?,?)\")->execute([(int)\$request['exchange_id'],(int)(\$request['offered_exchange_id']??0)]);\n\n            // Mark fees as delivered and deduct escrow_balance\n            if (\$request['trade_option'] === 'direct_escrow') {\n                \$feeOrders = \$pdo->prepare(\"SELECT order_number, gp_amount, user_id FROM orders WHERE order_number LIKE ?\");\n                \$feeOrders->execute(['EXC-FEE-'.\$requestId.'-%']);\n                foreach (\$feeOrders->fetchAll() as \$fee) {\n                    \$pdo->prepare(\"UPDATE orders SET shipping_status = 'delivered', delivered_at = NOW() WHERE order_number = ?\")->execute([\$fee['order_number']]);\n                    \$pdo->prepare(\"UPDATE vendor_wallets SET escrow_balance = escrow_balance - ? WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$fee['gp_amount'], \$fee['user_id']]);\n                }\n            }";
if (strpos($content3, $find3) !== false) {
    file_put_contents($file3, str_replace($find3, $replace3, $content3));
    echo "Patched exchange_requests.php\n";
}
echo "Done";
