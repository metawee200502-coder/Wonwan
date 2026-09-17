<?php
$file = 'c:\\laragon\\www\\wanawan_project\\admin\\revenue.php';
$content = file_get_contents($file);

$findQuery = "        SELECT o.*,\n               (SELECT GROUP_CONCAT(DISTINCT COALESCE(s.shop_name, p.company_name, u.display_name) SEPARATOR ', ')\n                FROM order_items oi\n                JOIN books b ON b.book_id = oi.book_id\n                LEFT JOIN shops s ON b.seller_type = 'shop' AND b.seller_id = s.shop_id\n                LEFT JOIN publishers p ON b.seller_type = 'publisher' AND b.seller_id = p.publisher_id\n                LEFT JOIN users u ON b.seller_type = 'user' AND b.seller_id = u.user_id\n                WHERE oi.order_id = o.order_id) as seller_names,\n               (SELECT MAX(b.seller_type) FROM order_items oi JOIN books b ON oi.book_id = b.book_id WHERE oi.order_id = o.order_id) as seller_type\n        FROM orders o\n        WHERE o.shipping_status = 'delivered' AND o.gp_amount > 0\n        ORDER BY COALESCE(o.delivered_at, o.created_at) DESC";

$replaceQuery = "        SELECT o.*,
               IF(o.order_number LIKE 'EXC-FEE-%', 
                  (SELECT display_name FROM users WHERE user_id = o.user_id),
                  (SELECT GROUP_CONCAT(DISTINCT COALESCE(s.shop_name, p.company_name, u.display_name) SEPARATOR ', ')
                   FROM order_items oi
                   JOIN books b ON b.book_id = oi.book_id
                   LEFT JOIN shops s ON b.seller_type = 'shop' AND b.seller_id = s.shop_id
                   LEFT JOIN publishers p ON b.seller_type = 'publisher' AND b.seller_id = p.publisher_id
                   LEFT JOIN users u ON b.seller_type = 'user' AND b.seller_id = u.user_id
                   WHERE oi.order_id = o.order_id)
               ) as seller_names,
               IF(o.order_number LIKE 'EXC-FEE-%',
                  'user',
                  (SELECT MAX(b.seller_type) FROM order_items oi JOIN books b ON oi.book_id = b.book_id WHERE oi.order_id = o.order_id)
               ) as seller_type
        FROM orders o
        WHERE o.shipping_status = 'delivered' AND o.gp_amount > 0
        ORDER BY COALESCE(o.delivered_at, o.created_at) DESC";

$content = str_replace($findQuery, $replaceQuery, $content);
file_put_contents($file, $content);
echo "Patched admin/revenue.php for seller_names\n";
