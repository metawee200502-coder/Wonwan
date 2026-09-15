<?php
// 1. Patch chat_messages_poll.php
$filePoll = 'C:/laragon/www/wanawan_project/pages/handlers/chat_messages_poll.php';
$contentPoll = file_get_contents($filePoll);

$searchPoll = "onclick=\"window.open(this.src)\"";
$replacePoll = "onclick=\"if(window.openConditionModal) { window.openConditionModal('\" . htmlspecialchars(\$exchangeRequestId) . \"'); } else { window.open(this.src); }\"";

$contentPoll = str_replace($searchPoll, $replacePoll, $contentPoll);
file_put_contents($filePoll, $contentPoll);


// 2. Patch chat.php
$fileChat = 'C:/laragon/www/wanawan_project/pages/chat.php';
$contentChat = file_get_contents($fileChat);

// Define window.openConditionModal
$searchChat = "function closeConditionModal() {";
$replaceChat = "window.openConditionModal = function(reqId) {
    document.getElementById('conditionIframe').src = '/pages/trade_condition_form.php?request_id=' + reqId + '&iframe=1&conversation_id=<?= \$conversationId ?>';
    document.getElementById('conditionModal').style.display = 'flex';
};
function closeConditionModal() {";

if (strpos($contentChat, 'window.openConditionModal = function') === false) {
    $contentChat = str_replace($searchChat, $replaceChat, $contentChat);
    file_put_contents($fileChat, $contentChat);
}

echo "Successfully updated to open condition modal instead of new tab.";
