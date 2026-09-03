const fs = require('fs');
const readline = require('readline');

const rl = readline.createInterface({
    input: fs.createReadStream('C:\\Users\\metha\\.gemini\\antigravity-ide\\brain\\c4b88514-f313-4a78-b8d3-f3635fa76652\\.system_generated\\logs\\transcript_full.jsonl')
});

rl.on('line', (line) => {
    try {
        const data = JSON.parse(line);
        if (data.tool_calls) {
            data.tool_calls.forEach(call => {
                if (call.name === 'default_api:multi_replace_file_content') {
                    const args = JSON.parse(call.arguments);
                    if (args.TargetFile && args.TargetFile.toLowerCase().includes('header.php')) {
                        args.ReplacementChunks.forEach(chunk => {
                            if (chunk.ReplacementContent && chunk.ReplacementContent.includes('mobile-sidebar-overlay')) {
                                fs.writeFileSync('c:\\laragon\\www\\wanawan_project\\scratch\\recover_menu.txt', chunk.ReplacementContent);
                                console.log('RECOVERED');
                            }
                        });
                    }
                }
            });
        }
    } catch (e) {}
});
