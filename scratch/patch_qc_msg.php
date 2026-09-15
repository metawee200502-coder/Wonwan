<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

$searchMsg = <<<HTML
            \$msg_stmt = \$pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message, msg_type, metadata, created_at) VALUES (?, 0, ?, 'admin_qc_report', ?, NOW())");
            \$meta = json_encode([
                'side' => \$side,
                'item_owner_id' => \$item_owner_id,
                'is_match' => \$is_match,
                'photos' => \$photo_urls
            ], JSON_UNESCAPED_UNICODE);
            \$msg_stmt->execute([\$conversation_id, \$msgContent, \$meta]);
HTML;

$replaceMsg = <<<HTML
            \$admin_id = \$_SESSION['user_id'];
            \$msg_stmt = \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, metadata, created_at) VALUES (?, ?, ?, 'admin_qc_report', ?, NOW())");
            \$meta = json_encode([
                'side' => \$side,
                'item_owner_id' => \$item_owner_id,
                'is_match' => \$is_match,
                'photos' => \$photo_urls,
                'request_id' => \$request_id
            ], JSON_UNESCAPED_UNICODE);
            \$msg_stmt->execute([\$conversation_id, \$admin_id, \$msgContent, \$meta]);
HTML;

$content = str_replace($searchMsg, $replaceMsg, $content);
file_put_contents($file, $content);
echo "Fixed insert query.\n";
