<?php
require_once __DIR__ . '/../config/config.php';

$stmt = $pdo->query("SELECT * FROM admin_action_logs WHERE action_type IN ('APPROVE_SLIP', 'REJECT_SLIP')");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($logs as $log) {
    if (is_numeric($log['target_id'])) {
        $order_id = (int)$log['target_id'];
        $order_number = $pdo->query("SELECT order_number FROM orders WHERE order_id = $order_id")->fetchColumn();
        
        if ($order_number) {
            $new_details = str_replace("#{$order_id}", $order_number, $log['details']);
            $pdo->prepare("UPDATE admin_action_logs SET target_id = ?, details = ? WHERE log_id = ?")->execute([
                $order_number, $new_details, $log['log_id']
            ]);
            echo "Updated Log ID {$log['log_id']} to $order_number\n";
        }
    }
}
echo "Done.";
