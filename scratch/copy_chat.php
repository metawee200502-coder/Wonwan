<?php
$content = file_get_contents('C:\laragon\www\wanawan_project\pages\chat.php');
$md = "```php\n" . $content . "\n```";
file_put_contents('C:\Users\metha\.gemini\antigravity-ide\brain\e7a78e82-d925-47ea-9abc-2113097870d4\chat_source.md', $md);
echo "Done";
