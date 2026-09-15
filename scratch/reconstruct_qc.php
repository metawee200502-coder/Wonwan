<?php
$lines = file('C:/Users/metha/.gemini/antigravity-ide/brain/33dbb834-52f0-44af-8044-9c4e5a137c81/.system_generated/logs/transcript_full.jsonl');

// 1. Get the base file from git
exec('git show HEAD:admin/qc_exchanges.php', $gitLines);
$content = implode("\n", $gitLines);

foreach ($lines as $line) {
    $data = json_decode($line, true);
    
    if ($data['type'] === 'PLANNER_RESPONSE' && isset($data['tool_calls'])) {
        foreach ($data['tool_calls'] as $tc) {
            $name = str_replace('default_api:', '', $tc['name']);
            if (in_array($name, ['replace_file_content', 'multi_replace_file_content'])) {
                $args = $tc['args'] ?? $tc['arguments'];
                if (is_string($args)) $args = json_decode($args, true);
                
                if (isset($args['TargetFile']) && strpos($args['TargetFile'], 'qc_exchanges.php') !== false) {
                    if ($name === 'replace_file_content') {
                        if (isset($args['TargetContent']) && isset($args['ReplacementContent'])) {
                            $target = $args['TargetContent'];
                            $replacement = $args['ReplacementContent'];
                            $content = str_replace($target, $replacement, $content);
                        }
                    } elseif ($name === 'multi_replace_file_content') {
                        if (isset($args['ReplacementChunks'])) {
                            foreach ($args['ReplacementChunks'] as $chunk) {
                                $target = $chunk['TargetContent'];
                                $replacement = $chunk['ReplacementContent'];
                                $content = str_replace($target, $replacement, $content);
                            }
                        }
                    }
                }
            }
            
            if ($name === 'run_command') {
                $args = $tc['args'] ?? $tc['arguments'];
                if (is_string($args)) $args = json_decode($args, true);
                if (isset($args['CommandLine']) && strpos($args['CommandLine'], 'git checkout') !== false && strpos($args['CommandLine'], 'qc_exchanges.php') !== false) {
                    file_put_contents('scratch/recovered_qc_exchanges.php', $content);
                    echo "Successfully reconstructed file up to git checkout!\n";
                    echo "Length: " . strlen($content) . "\n";
                    exit;
                }
            }
        }
    }
}
