import fs from "node:fs/promises";
import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";

const inputPath = "C:/Users/metha/Downloads/JAMSAI_book_template.csv.xlsx";
const outputDir = "C:/laragon/www/wanawan_project/.codex/workbook_check";

await fs.mkdir(outputDir, { recursive: true });
const input = await FileBlob.load(inputPath);
const workbook = await SpreadsheetFile.importXlsx(input);

const overview = await workbook.inspect({
  kind: "workbook,sheet,table",
  maxChars: 12000,
  tableMaxRows: 10,
  tableMaxCols: 30,
  tableMaxCellChars: 180,
});
console.log(overview.ndjson);

const sheet = workbook.worksheets.getItemAt(0);
const used = sheet.getUsedRange(true);
console.log(JSON.stringify({ sheet: sheet.name, values: used.values }));

const preview = await workbook.render({
  sheetName: sheet.name,
  autoCrop: "all",
  scale: 1.5,
  format: "png",
});
await fs.writeFile(
  `${outputDir}/preview.png`,
  new Uint8Array(await preview.arrayBuffer()),
);
