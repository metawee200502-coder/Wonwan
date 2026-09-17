<?php
$file = 'c:\\laragon\\www\\wanawan_project\\pages\\exchange_requests.php';
$content = file_get_contents($file);

// Replace the reject/cancel block to include refund logic
$find = "elseif(in_array(\$action,['reject','cancel'],true)&&\$request['status']==='accepted'){\n\t\t\t\t\t\t\t\$pdo->prepare(\"UPDATE exchanges SET status='open' WHERE exchange_id IN (?,?) AND status='in_progress'\")->execute([(int)\$request['exchange_id'],(int)(\$request['offered_exchange_id']??0)]);\n\t\t\t\t\t\t}";
// the exact spacing might be different. I will use regex.

$content = preg_replace('/(elseif\(in_array\(\$action,\[\'reject\',\'cancel\'\],true\)&&\(\$request\[\'status\'\]===\'accepted\'\|\|\$request\[\'status\'\]===\'pending\'\)\){.*?})(\s*elseif)/s', 
    "$1\n            // Cancel fees and refund escrow_balance\n            if (in_array(\$request['trade_option'], ['direct_escrow', 'admin_qc'])) {\n                \$feeOrders = \$pdo->prepare(\"SELECT order_number, subtotal, user_id FROM orders WHERE order_number LIKE ? AND shipping_status = 'pending'\");\n                \$feeOrders->execute(['EXC-FEE-'.\$requestId.'-%']);\n                foreach (\$feeOrders->fetchAll() as \$fee) {\n                    \$pdo->prepare(\"UPDATE orders SET shipping_status = 'cancelled' WHERE order_number = ?\")->execute([\$fee['order_number']]);\n                    // Refund full deposit\n                    \$pdo->prepare(\"UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, available_balance = available_balance + ? WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$fee['subtotal'], \$fee['subtotal'], \$fee['user_id']]);\n                    \$pdo->prepare(\"INSERT INTO wallet_transactions (wallet_id, type, amount, status, created_at) SELECT wallet_id, 'EARNING', ?, 'SUCCESS', NOW() FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$fee['subtotal'], \$fee['user_id']]);\n                }\n            }$2", $content);

// Also need to handle just the accepted condition in the original code:
// `elseif(in_array($action,['reject','cancel'],true)&&$request['status']==='accepted')`
$content = preg_replace('/(elseif\(in_array\(\$action,\[\'reject\',\'cancel\'\],true\)&&\$request\[\'status\'\]===\'accepted\'\){[^}]+})(\s*elseif)/s', 
    "$1\n            // Cancel fees and refund escrow_balance\n            if (in_array(\$request['trade_option'], ['direct_escrow', 'admin_qc'])) {\n                \$feeOrders = \$pdo->prepare(\"SELECT order_number, subtotal, user_id FROM orders WHERE order_number LIKE ? AND shipping_status = 'pending'\");\n                \$feeOrders->execute(['EXC-FEE-'.\$requestId.'-%']);\n                foreach (\$feeOrders->fetchAll() as \$fee) {\n                    \$pdo->prepare(\"UPDATE orders SET shipping_status = 'cancelled' WHERE order_number = ?\")->execute([\$fee['order_number']]);\n                    // Refund full deposit\n                    \$pdo->prepare(\"UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, available_balance = available_balance + ? WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$fee['subtotal'], \$fee['subtotal'], \$fee['user_id']]);\n                    \$pdo->prepare(\"INSERT INTO wallet_transactions (wallet_id, type, amount, status, created_at) SELECT wallet_id, 'EARNING', ?, 'SUCCESS', NOW() FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$fee['subtotal'], \$fee['user_id']]);\n                }\n            }$2", $content);

// Also handle the pending reject
$content = preg_replace('/(\$newStatus=\[\'accept\'=>\'accepted\',\'reject\'=>\'rejected\',\'complete\'=>\'completed\',\'cancel\'=>\'cancelled\'\]\[\$action\];\n\s*if\(\$newStatus\)\$pdo->prepare\(\'UPDATE exchange_requests SET status=\? WHERE request_id=\?\'\)->execute\(\[\$newStatus,\$requestId\]\);\n\s*if\(\$action===\'accept\'\).*?elseif\(\$action===\'complete\'\){.*?}})(.*?)/s', "$1$2", $content); // No change here, just to test regex

// But what if status is pending and they reject?
// In line 33-34:
/*
         $newStatus=['accept'=>'accepted','reject'=>'rejected','complete'=>'completed','cancel'=>'cancelled'][$action];
         if($newStatus)$pdo->prepare('UPDATE exchange_requests SET status=? WHERE request_id=?')->execute([$newStatus,$requestId]);
*/
// If they cancel when pending, we should also refund!
$content = preg_replace('/(if\(\$newStatus\)\$pdo->prepare\(\'UPDATE exchange_requests SET status=\? WHERE request_id=\?\'\)->execute\(\[\$newStatus,\$requestId\]\);)/', "$1\n         if (in_array(\$action, ['reject','cancel'], true) && in_array(\$request['trade_option'], ['direct_escrow', 'admin_qc'])) {\n                \$feeOrders = \$pdo->prepare(\"SELECT order_number, subtotal, user_id FROM orders WHERE order_number LIKE ? AND shipping_status = 'pending'\");\n                \$feeOrders->execute(['EXC-FEE-'.\$requestId.'-%']);\n                foreach (\$feeOrders->fetchAll() as \$fee) {\n                    \$pdo->prepare(\"UPDATE orders SET shipping_status = 'cancelled' WHERE order_number = ?\")->execute([\$fee['order_number']]);\n                    // Refund full deposit\n                    \$pdo->prepare(\"UPDATE vendor_wallets SET escrow_balance = escrow_balance - ?, available_balance = available_balance + ? WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$fee['subtotal'], \$fee['subtotal'], \$fee['user_id']]);\n                    \$pdo->prepare(\"INSERT INTO wallet_transactions (wallet_id, type, amount, status, created_at) SELECT wallet_id, 'EARNING', ?, 'SUCCESS', NOW() FROM vendor_wallets WHERE seller_type = 'user' AND seller_id = ?\")->execute([\$fee['subtotal'], \$fee['user_id']]);\n                }\n            }\n", $content);

file_put_contents($file, $content);
echo "Patched exchange_requests.php for cancel refund.\n";
