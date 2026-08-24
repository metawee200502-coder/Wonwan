const fs = require('fs');
let c = fs.readFileSync('c:/laragon/www/wanawan_project/pages/book_detail.php', 'utf8');
c = c.replace(/onclick="addToCart\((<\?= \$id \?>)\)">ซื้อเลย<\/button>/, 'onclick="buyNow($1)">ซื้อเลย</button>');
fs.writeFileSync('c:/laragon/www/wanawan_project/pages/book_detail.php', c);
console.log('Replaced buyNow in book_detail.php');
