UPDATE admin_action_logs 
SET details = REPLACE(details, '#33', (SELECT order_number FROM orders WHERE order_id = 33 LIMIT 1)),
    target_id = (SELECT order_number FROM orders WHERE order_id = 33 LIMIT 1)
WHERE target_id = '33' AND action_type = 'APPROVE_SLIP';
