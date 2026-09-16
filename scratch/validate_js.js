const fs = require('fs');
const content = fs.readFileSync('pages/chat.php', 'utf8');
const scriptRegex = /<script>([\s\S]*?)<\/script>/g;
let match;
let count = 0;
while ((match = scriptRegex.exec(content)) !== null) {
    fs.writeFileSync(`scratch/test_${count}.js`, match[1]);
    const { execSync } = require('child_process');
    try {
        execSync(`node -c scratch/test_${count}.js`);
        console.log(`Script ${count} OK`);
    } catch (e) {
        console.log(`Script ${count} FAIL`);
        console.log(e.message);
    }
    count++;
}
