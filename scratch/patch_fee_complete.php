<?php
$file = 'c:\\laragon\\www\\wanawan_project\\pages\\exchange_requests.php';
$content = file_get_contents($file);

$find = "\$feeOrders = \$pdo->prepare(\"SELECT order_number, gp_amount, user_id FROM orders WHERE order_number LIKE ?\");\n                \$feeOrders->execute(['EXC-FEE-'.\$requestId.'-%']);\n                foreach (\$feeOrders->fetchAll() as \$fee) {\n                    \$pdo->prepare(\"UPDATE orders SET shipping_status = 'delivered', delivered_at = NOW() WHERE order_number = ?\")->execute([\$fee['order_number']]);\n                    \$pdo->prepare(\"UPDATE vendor_wallets SET escrow_balance = escrow_balance - ? WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$fee['gp_amount'], \$fee['user_id']]);\n                }";

$replace = "\$feeOrders = \$pdo->prepare(\"SELECT order_number, subtotal, gp_amount, user_id FROM orders WHERE order_number LIKE ?\");\n                \$feeOrders->execute(['EXC-FEE-'.\$requestId.'-%']);\n                foreach (\$feeOrders->fetchAll() as \$fee) {\n                    \$pdo->prepare(\"UPDATE orders SET shipping_status = 'delivered', delivered_at = NOW() WHERE order_number = ?\")->execute([\$fee['order_number']]);\n                    \n                    // Deduct full deposit from escrow\n                    \$pdo->prepare(\"UPDATE vendor_wallets SET escrow_balance = escrow_balance - ? WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$fee['subtotal'], \$fee['user_id']]);\n                    \n                    // Refund the remainder (subtotal - gp_amount)\n                    \$refund = \$fee['subtotal'] - \$fee['gp_amount'];\n                    if (\$refund > 0) {\n                        \$pdo->prepare(\"UPDATE vendor_wallets SET available_balance = available_balance + ? WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$refund, \$fee['user_id']]);\n                        // Record refund transaction\n                        \$pdo->prepare(\"INSERT INTO wallet_transactions (wallet_id, type, amount, status, created_at) SELECT wallet_id, 'EARNING', ?, 'SUCCESS', NOW() FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$refund, \$fee['user_id']]);\n                    }\n                }";

// We also need to do this for admin_qc?
// Wait, the user said "ถ้าลูกค้าแลกกันผ่านระบบ admin qc เงินค่าธรรมเนียมที่ลูกค้าโอนไว้ คนละ 100 จะเข้าทั้งคู่ค่ะ"
// So admin_qc doesn't refund anything, it just deducts the escrow_balance.
// I will change the if condition to run for both direct_escrow and admin_qc!
$content = str_replace("if (\$request['trade_option'] === 'direct_escrow') {", "if (\$request['trade_option'] === 'direct_escrow' || \$request['trade_option'] === 'admin_qc') {", $content);
$content = str_replace($find, $replace, $content);

file_put_contents($file, $content);
echo "Patched exchange_requests.php for completion refund.\n";
