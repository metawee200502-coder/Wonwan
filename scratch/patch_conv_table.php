<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

$searchStr = <<<HTML
        \$conv_stmt = \$pdo->prepare("SELECT conversation_id FROM conversations WHERE request_id = ?");
        \$conv_stmt->execute([\$request_id]);
HTML;

$replaceStr = <<<HTML
        // Get exchange_id and requester_id from request_id to find conversation
        \$reqInfo = \$pdo->query("SELECT r.exchange_id, r.requester_id, e.owner_id FROM exchange_requests r JOIN exchanges e ON r.exchange_id = e.exchange_id WHERE r.request_id = \$request_id")->fetch();
        \$conversation_id = 0;
        if (\$reqInfo) {
            \$conv_stmt = \$pdo->prepare("SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id = ? AND ((customer_user_id = ? AND seller_id = ?) OR (customer_user_id = ? AND seller_id = ?))");
            \$conv_stmt->execute([\$reqInfo['exchange_id'], \$reqInfo['requester_id'], \$reqInfo['owner_id'], \$reqInfo['owner_id'], \$reqInfo['requester_id']]);
            \$conv = \$conv_stmt->fetch();
            if (\$conv) {
                \$conversation_id = \$conv['conversation_id'];
            }
        }
HTML;

// In qc_exchanges.php we had:
// $conv = $conv_stmt->fetch();
// if ($conv) { ... }
// We need to slightly adjust the replacement to integrate properly
$searchMsg = <<<HTML
        \$conv_stmt = \$pdo->prepare("SELECT conversation_id FROM conversations WHERE request_id = ?");
        \$conv_stmt->execute([\$request_id]);
        \$conv = \$conv_stmt->fetch();
        if (\$conv) {
            \$conversation_id = \$conv['conversation_id'];
HTML;

$replaceMsg = <<<HTML
        \$reqInfo = \$pdo->query("SELECT r.exchange_id, r.requester_id, e.owner_id FROM exchange_requests r JOIN exchanges e ON r.exchange_id = e.exchange_id WHERE r.request_id = \$request_id")->fetch();
        \$conversation_id = 0;
        if (\$reqInfo) {
            \$conv_stmt = \$pdo->prepare("SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id = ? AND ((customer_user_id = ? AND seller_id = ?) OR (customer_user_id = ? AND seller_id = ?))");
            \$conv_stmt->execute([\$reqInfo['exchange_id'], \$reqInfo['requester_id'], \$reqInfo['owner_id'], \$reqInfo['owner_id'], \$reqInfo['requester_id']]);
            \$conv = \$conv_stmt->fetch();
            if (\$conv) {
                \$conversation_id = \$conv['conversation_id'];
            }
        }
        
        if (\$conversation_id) {
HTML;

$content = str_replace($searchMsg, $replaceMsg, $content);
file_put_contents($file, $content);
echo "Fixed table name conversations to chat_conversations.\n";
