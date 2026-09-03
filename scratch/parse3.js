const fs = require('fs');
const text = fs.readFileSync('scratch/raw.txt', 'utf8');
const start = text.indexOf('{');
const data = JSON.parse(text.substring(start));
data.tool_calls.forEach(call => {
  if(call.name === 'multi_replace_file_content' || call.name === 'replace_file_content') {
    const chunks = call.args.ReplacementChunks || [call.args];
    chunks.forEach(chunk => {
      if(chunk.ReplacementContent && chunk.ReplacementContent.includes('mobile-sidebar-overlay')) {
        fs.writeFileSync('scratch/recover3.txt', chunk.ReplacementContent);
        console.log('Success');
      }
    })
  }
})
