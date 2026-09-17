<?php
$file = 'c:\\laragon\\www\\wanawan_project\\pages\\handlers\\trade_submit_logistics.php';
$content = file_get_contents($file);

$findWallet = "\$pdo->prepare(\"UPDATE vendor_wallets SET available_balance = available_balance - ? WHERE seller_type='user' AND seller_id=?\")->execute([\$feeAmount, \$userId]);";
$replaceWallet = "\$pdo->prepare(\"UPDATE vendor_wallets SET available_balance = available_balance - ?, escrow_balance = escrow_balance + ? WHERE seller_type='user' AND seller_id=?\")->execute([\$feeAmount, \$feeAmount, \$userId]);\n            \n            \$orderNum = 'EXC-FEE-' . \$requestId . '-' . \$userId;\n            \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A')\")->execute([\$userId, \$orderNum, \$feeAmount, \$feeAmount, \$feeAmount]);";

$content = str_replace($findWallet, $replaceWallet, $content);

// Also need to handle slip upload in admin/qc_exchanges.php
file_put_contents($file, $content);
echo "Patched trade_submit_logistics.php\n";

$file2 = 'c:\\laragon\\www\\wanawan_project\\admin\\qc_exchanges.php';
$content2 = file_get_contents($file2);

// Find slip approval
$findSlip = "\$stmt = \$pdo->prepare(\"UPDATE exchange_requests SET requester_slip_status = ? WHERE request_id = ?\");\n            \$stmt->execute([\$status, \$requestId]);";
$replaceSlip = "\$stmt = \$pdo->prepare(\"UPDATE exchange_requests SET requester_slip_status = ? WHERE request_id = ?\");\n            \$stmt->execute([\$status, \$requestId]);\n\n            if (\$status === 'approved') {\n                \$reqStmt = \$pdo->prepare(\"SELECT requester_id, requester_slip_url FROM exchange_requests WHERE request_id = ?\");\n                \$reqStmt->execute([\$requestId]);\n                \$rData = \$reqStmt->fetch();\n                if (\$rData) {\n                    \$orderNum = 'EXC-FEE-' . \$requestId . '-' . \$rData['requester_id'];\n                    \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method, slip_image_url) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A', ?)\")->execute([\$rData['requester_id'], \$orderNum, 100, 100, 100, \$rData['requester_slip_url']]);\n                    \$pdo->prepare(\"UPDATE vendor_wallets SET escrow_balance = escrow_balance + 100 WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$rData['requester_id']]);\n                }\n            }";

$content2 = str_replace($findSlip, $replaceSlip, $content2);

// And for offer_slip_status
$findSlip2 = "\$stmt = \$pdo->prepare(\"UPDATE exchange_requests SET offer_slip_status = ? WHERE request_id = ?\");\n            \$stmt->execute([\$status, \$requestId]);";
$replaceSlip2 = "\$stmt = \$pdo->prepare(\"UPDATE exchange_requests SET offer_slip_status = ? WHERE request_id = ?\");\n            \$stmt->execute([\$status, \$requestId]);\n\n            if (\$status === 'approved') {\n                \$reqStmt = \$pdo->prepare(\"SELECT e.owner_id, r.offer_slip_url FROM exchange_requests r JOIN exchanges e ON e.exchange_id = r.exchange_id WHERE r.request_id = ?\");\n                \$reqStmt->execute([\$requestId]);\n                \$rData = \$reqStmt->fetch();\n                if (\$rData) {\n                    \$orderNum = 'EXC-FEE-' . \$requestId . '-' . \$rData['owner_id'];\n                    \$pdo->prepare(\"INSERT IGNORE INTO orders (user_id, order_number, subtotal, total_amount, gp_amount, payment_status, shipping_status, shipping_name, shipping_address, shipping_phone, shipping_method, slip_image_url) VALUES (?, ?, ?, ?, ?, 'paid', 'pending', 'ระบบกลาง', 'N/A', 'N/A', 'N/A', ?)\")->execute([\$rData['owner_id'], \$orderNum, 100, 100, 100, \$rData['offer_slip_url']]);\n                    \$pdo->prepare(\"UPDATE vendor_wallets SET escrow_balance = escrow_balance + 100 WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$rData['owner_id']]);\n                }\n            }";

$content2 = str_replace($findSlip2, $replaceSlip2, $content2);
file_put_contents($file2, $content2);
echo "Patched admin/qc_exchanges.php\n";
