const fs = require('fs');
const lines = fs.readFileSync('C:/Users/metha/.gemini/antigravity-ide/brain/c4b88514-f313-4a78-b8d3-f3635fa76652/.system_generated/logs/transcript_full.jsonl', 'utf8').split('\n');
let maxLen = 0; let bestContent = '';
for(const line of lines) {
  if(line.includes('mobile-sidebar-overlay')) {
    try { const call = JSON.parse(line);
    if(call.tool_calls) {
      call.tool_calls.forEach(tc => {
        if(tc.name && tc.name.includes('replace')) {
          const chunks = tc.args.ReplacementChunks || [tc.args];
          chunks.forEach(chunk => {
            if(chunk.ReplacementContent && chunk.ReplacementContent.includes('mobile-sidebar-overlay')) {
              if(chunk.ReplacementContent.length > maxLen) { maxLen = chunk.ReplacementContent.length; bestContent = chunk.ReplacementContent; }
            }
          });
        }
      });
    } }catch(e){}
  }
}
fs.writeFileSync('scratch/recover5.txt', bestContent);
