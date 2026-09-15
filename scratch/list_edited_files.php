<?php
$lines = file('C:/Users/metha/.gemini/antigravity-ide/brain/33dbb834-52f0-44af-8044-9c4e5a137c81/.system_generated/logs/transcript_full.jsonl');
$editedFiles = [];

foreach ($lines as $line) {
    $data = json_decode($line, true);
    if ($data['type'] === 'PLANNER_RESPONSE' && isset($data['tool_calls'])) {
        foreach ($data['tool_calls'] as $tc) {
            if (in_array($tc['name'], ['replace_file_content', 'multi_replace_file_content', 'write_to_file', 'default_api:replace_file_content'])) {
                $args = $tc['args'] ?? $tc['arguments'];
                if (is_string($args)) $args = json_decode($args, true);
                if (isset($args['TargetFile'])) {
                    $editedFiles[$args['TargetFile']] = true;
                }
            }
        }
    }
}

foreach ($editedFiles as $file => $v) {
    echo $file . "\n";
}
