const fs = require('fs');
const text = fs.readFileSync('c:\\laragon\\www\\wanawan_project\\scratch\\recover_menu_json.txt', 'utf-8');
const match = text.match(/\{.*\}/);
if (match) {
    const data = JSON.parse(match[0]);
    if (data.tool_calls) {
        data.tool_calls.forEach(call => {
            if (call.name === 'default_api:multi_replace_file_content' || call.name === 'default_api:replace_file_content') {
                const args = JSON.parse(call.arguments);
                const chunks = args.ReplacementChunks || [args];
                chunks.forEach(chunk => {
                    if (chunk.ReplacementContent && chunk.ReplacementContent.includes('mobile-sidebar-overlay')) {
                        fs.writeFileSync('c:\\laragon\\www\\wanawan_project\\scratch\\recover_menu.txt', chunk.ReplacementContent);
                        console.log('RECOVERED');
                    }
                });
            }
        });
    }
}
