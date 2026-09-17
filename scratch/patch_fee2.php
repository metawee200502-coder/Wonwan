<?php
$file = 'c:\\laragon\\www\\wanawan_project\\pages\\trade_upload_slip.php';
$content = file_get_contents($file);

// We need to fetch trade_option if it's not fetched yet.
// In trade_upload_slip.php, line ~30: $reqStmt = $pdo->prepare("SELECT * FROM exchange_requests WHERE request_id = ?"); 
// So $exchangeRequest['trade_option'] is available!

$find1 = "\$orderNum = 'EXC-FEE-' . \$requestId . '-' . \$userId;\n                    \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method)\n                                    VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A')\")\n                        ->execute([\$userId, \$orderNum, \$requiredDeposit, \$requiredDeposit, \$requiredDeposit]);";

$replace1 = "\$orderNum = 'EXC-FEE-' . \$requestId . '-' . \$userId;\n                    \$feeAmount = (\$exchangeRequest['trade_option'] === 'admin_qc') ? \$requiredDeposit : 5;\n                    \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method)\n                                    VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A')\")\n                        ->execute([\$userId, \$orderNum, \$requiredDeposit, \$requiredDeposit, \$feeAmount]);";

if (strpos(str_replace(' ', '', $content), str_replace(' ', '', $find1)) === false) {
    // try a more generic replace
    $content = preg_replace('/(\$orderNum = \'EXC-FEE-\' \. \$requestId \. \'-\' \. \$userId;).*?(\$pdo->prepare\("INSERT IGNORE INTO orders[^\n]*\n[^\n]*VALUES \(\?, \?, \?, \?, \?, \'paid\', \'pending\', \'ระบบกลาง\', \'N\/A\', \'N\/A\', \'N\/A\'\)"\)\s*->execute\(\[\$userId, \$orderNum, \$requiredDeposit, \$requiredDeposit, \$requiredDeposit\]\);)/s', "$1\n                    \$feeAmount = (\$exchangeRequest['trade_option'] === 'admin_qc') ? \$requiredDeposit : 5;\n                    \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A')\")\n                        ->execute([\$userId, \$orderNum, \$requiredDeposit, \$requiredDeposit, \$feeAmount]);", $content);
} else {
    $content = str_replace($find1, $replace1, $content);
}

// Do the same for slip upload section
$content = preg_replace('/(\$orderNum = \'EXC-FEE-\' \. \$requestId \. \'-\' \. \$userId;).*?(\$pdo->prepare\("INSERT IGNORE INTO orders[^\n]*\n[^\n]*VALUES \(\?, \?, \?, \?, \?, \'paid\', \'pending\', \'ระบบกลาง\', \'N\/A\', \'N\/A\', \'N\/A\', \?\)"\)\s*->execute\(\[\$userId, \$orderNum, \$requiredDeposit, \$requiredDeposit, \$requiredDeposit, \$slipUrl\]\);)/s', "$1\n                        \$feeAmount = (\$exchangeRequest['trade_option'] === 'admin_qc') ? \$requiredDeposit : 5;\n                        \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method, slip_image_url) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A', ?)\")\n                            ->execute([\$userId, \$orderNum, \$requiredDeposit, \$requiredDeposit, \$feeAmount, \$slipUrl]);", $content);

file_put_contents($file, $content);
echo "Patched trade_upload_slip.php\n";

// Now admin/escrow.php
$file2 = 'c:\\laragon\\www\\wanawan_project\\admin\\escrow.php';
$content2 = file_get_contents($file2);
$content2 = preg_replace('/(\$form = \$pdo->prepare\("SELECT request_id, owner_id, required_deposit_amount, deposit_slip_url FROM trade_condition_forms WHERE form_id = \?"\);\n\s*\$form->execute\(\[\$formId\]\);\n\s*\$f = \$form->fetch\(\);\n\s*if \(\$f && \$f\[\'required_deposit_amount\'\] > 0\) \{)/', "\$form = \$pdo->prepare(\"SELECT t.request_id, t.owner_id, t.required_deposit_amount, t.deposit_slip_url, r.trade_option FROM trade_condition_forms t JOIN exchange_requests r ON r.request_id = t.request_id WHERE t.form_id = ?\");\n            \$form->execute([\$formId]);\n            \$f = \$form->fetch();\n            if (\$f && \$f['required_deposit_amount'] > 0) {", $content2);
$content2 = preg_replace('/(\$pdo->prepare\("INSERT IGNORE INTO orders[^;]+;\n)/', "\$feeAmount = (\$f['trade_option'] === 'admin_qc') ? \$f['required_deposit_amount'] : 5;\n                \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method, slip_image_url) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A', ?)\")->execute([\$f['owner_id'], \$orderNum, \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$feeAmount, \$f['deposit_slip_url']]);\n", $content2);
file_put_contents($file2, $content2);
echo "Patched admin/escrow.php\n";

// admin/qc_exchanges.php
$file3 = 'c:\\laragon\\www\\wanawan_project\\admin\\qc_exchanges.php';
$content3 = file_get_contents($file3);
$content3 = preg_replace('/(\$form = \$pdo->prepare\("SELECT request_id, owner_id, required_deposit_amount, deposit_slip_url FROM trade_condition_forms WHERE form_id = \?"\);\n\s*\$form->execute\(\[\$formId\]\);\n\s*\$f = \$form->fetch\(\);\n\s*if \(\$f && \$f\[\'required_deposit_amount\'\] > 0\) \{)/', "\$form = \$pdo->prepare(\"SELECT t.request_id, t.owner_id, t.required_deposit_amount, t.deposit_slip_url, r.trade_option FROM trade_condition_forms t JOIN exchange_requests r ON r.request_id = t.request_id WHERE t.form_id = ?\");\n                    \$form->execute([\$formId]);\n                    \$f = \$form->fetch();\n                    if (\$f && \$f['required_deposit_amount'] > 0) {", $content3);
$content3 = preg_replace('/(\$pdo->prepare\("INSERT IGNORE INTO orders[^;]+;\n)/', "\$feeAmount = (\$f['trade_option'] === 'admin_qc') ? \$f['required_deposit_amount'] : 5;\n                        \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method, slip_image_url) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A', ?)\")->execute([\$f['owner_id'], \$orderNum, \$f['required_deposit_amount'], \$f['required_deposit_amount'], \$feeAmount, \$f['deposit_slip_url']]);\n", $content3);
file_put_contents($file3, $content3);
echo "Patched admin/qc_exchanges.php\n";
