<?php
$file = 'c:\\laragon\\www\\wanawan_project\\pages\\exchange_requests.php';
$content = file_get_contents($file);

// Also need to make sure the SELECT fetches order_id for the execute parameter!
$find1 = "\$feeOrders = \$pdo->prepare(\"SELECT order_number, subtotal, user_id FROM orders WHERE order_number LIKE ? AND shipping_status = 'pending'\");";
$replace1 = "\$feeOrders = \$pdo->prepare(\"SELECT order_id, order_number, subtotal, user_id FROM orders WHERE order_number LIKE ? AND shipping_status = 'pending'\");";
$content = str_replace($find1, $replace1, $content);

$find2 = "->execute([\$fee['subtotal'], \$fee['user_id']]);";
$replace2 = "->execute([\$fee['subtotal'], \$fee['order_id'], \$fee['user_id']]);";
$content = str_replace($find2, $replace2, $content);

$find3 = "->execute([\$refund, \$fee['user_id']]);";
$replace3 = "->execute([\$refund, \$fee['order_id'], \$fee['user_id']]);";
$content = str_replace($find3, $replace3, $content);

// For the completion logic:
$find4 = "\$feeOrders = \$pdo->prepare(\"SELECT order_number, subtotal, gp_amount, user_id FROM orders WHERE order_number LIKE ? AND shipping_status = 'pending'\");";
$replace4 = "\$feeOrders = \$pdo->prepare(\"SELECT order_id, order_number, subtotal, gp_amount, user_id FROM orders WHERE order_number LIKE ? AND shipping_status = 'pending'\");";
$content = str_replace($find4, $replace4, $content);

file_put_contents($file, $content);
echo "Patched exchange_requests.php\n";
