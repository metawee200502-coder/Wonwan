<?php
/**
 * pages/dashboard_export.php
 * ส่งออกข้อมูล dashboard เป็นไฟล์ Excel (.xlsx) สวยงามระดับมืออาชีพ
 * ตกแต่งสีหัวตาราง, สีสถานะ, แถวสลับสี, ปรับความกว้างคอลัมน์อัตโนมัติ และป้องกันเลขพัสดุกลายเป็นรหัสวิทยาศาสตร์
 */
ob_start();
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = $_SESSION['role'] ?? '';
if (!$userId) {
    ob_end_clean();
    http_response_code(403);
    exit('Forbidden');
}

// ตรวจสอบประเภทผู้ขาย
if ($role === 'publisher_admin') {
    $sellerType = 'publisher';
    $sellerStmt = $pdo->prepare('SELECT p.publisher_id id, p.company_name name FROM publisher_staff ps JOIN publishers p ON p.publisher_id=ps.publisher_id WHERE ps.user_id=? LIMIT 1');
    $sellerStmt->execute([$userId]);
    $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);
} elseif ($role === 'shop_admin') {
    $sellerType = 'shop';
    $sellerStmt = $pdo->prepare('SELECT s.shop_id id, s.shop_name name FROM shop_staff ss JOIN shops s ON s.shop_id=ss.shop_id WHERE ss.user_id=? LIMIT 1');
    $sellerStmt->execute([$userId]);
    $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);
} else {
    // ผู้ขายทั่วไปในตลาด (seller_market.php)
    $sellerType = 'user';
    $sellerStmt = $pdo->prepare('SELECT user_id id, COALESCE(NULLIF(display_name,""), email) name FROM users WHERE user_id=? LIMIT 1');
    $sellerStmt->execute([$userId]);
    $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);
}

if (!$seller) {
    ob_end_clean();
    http_response_code(403);
    exit('Forbidden');
}

$sellerId    = (int)$seller['id'];
$sellerName  = (string)$seller['name'];
$sellerLabel = ($sellerType === 'user') ? 'ผู้ขาย' : 'ร้านค้า/สำนักพิมพ์';
$type        = $_GET['type'] ?? 'orders';
$dateLabel   = date('Ymd_His');

$gpPercent   = ($sellerType === 'user') ? 15 : 10;
$gpRate      = $gpPercent / 100;
$netRate     = 1 - $gpRate;

/**
 * จัดรูปแบบ Worksheet ให้สวยงามระดับมืออาชีพ
 */
function buildStyledWorksheet(
    Worksheet $sheet,
    string $title,
    string $subtitle,
    array $rows,
    array $sumCols = []
): void {
    $sheet->setShowGridLines(true);

    if (empty($rows)) {
        // กรณีไม่มีข้อมูล
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->setCellValue('A3', '(ไม่พบข้อมูลในช่วงเวลาที่เลือก)');
        return;
    }

    $headers = array_keys($rows[0]);
    $totalCols = count($headers);
    $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

    // ── Row 1: Title Banner ──────────────────────────────────────────────────
    $sheet->mergeCells("A1:{$lastColLetter}1");
    $sheet->setCellValue('A1', '🌿  ' . $title);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
    $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
    $sheet->getRowDimension(1)->setRowHeight(36);

    // ── Row 2: Subtitle / Metadata ──────────────────────────────────────────
    $sheet->mergeCells("A2:{$lastColLetter}2");
    $sheet->setCellValue('A2', $subtitle . '  ·  จำนวน ' . number_format(count($rows)) . ' รายการ');
    $subFont = $sheet->getStyle('A2')->getFont();
    $subFont->setSize(9)->setItalic(true);
    $subFont->getColor()->setRGB('64748B');
    $sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
    $sheet->getStyle('A2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // Row 3 เว้นว่าง
    $sheet->getRowDimension(3)->setRowHeight(10);

    // ── Row 4: Table Headers ────────────────────────────────────────────────
    $headerRow = 4;
    $sheet->getRowDimension($headerRow)->setRowHeight(28);
    foreach ($headers as $idx => $hName) {
        $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
        $cellCoord = "{$colLetter}{$headerRow}";
        $sheet->setCellValue($cellCoord, $hName);

        $style = $sheet->getStyle($cellCoord);
        $style->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2F3E36'); // Wanawan Forest Slate
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false);
    }

    // ── Data Rows (Row 5 เป็นต้นไป) ─────────────────────────────────────────
    $currentRow = 5;
    $borderThin = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E2E8F0'],
            ],
        ],
    ];

    foreach ($rows as $row) {
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
        $isOdd = ($currentRow % 2 !== 0);
        $rowBgColor = $isOdd ? 'F8FAFC' : 'FFFFFF';

        $colIdx = 1;
        foreach ($row as $colName => $val) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $cellCoord = "{$colLetter}{$currentRow}";
            $cellStyle = $sheet->getStyle($cellCoord);

            // Default background for row striping
            $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rowBgColor);
            $cellStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            // วิเคราะห์ประเภทข้อมูลเพื่อจัด Alignment, NumberFormat และสีสถานะ
            $lowerCol = mb_strtolower($colName);
            $valStr = (string)$val;

            // 1) สถานะ (Status Badges)
            if (str_contains($lowerCol, 'สถานะ')) {
                $sheet->setCellValueExplicit($cellCoord, $valStr, DataType::TYPE_STRING);
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // สีสถานะ: สำเร็จ / ชำระแล้ว / ส่งถึงแล้ว / วางขาย -> เขียวอ่อน
                if (in_array($valStr, ['ชำระแล้ว', 'จัดส่งแล้ว', 'ส่งถึงแล้ว', 'วางขาย', 'paid', 'delivered', 'available'], true)) {
                    $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
                    $cellStyle->getFont()->setBold(true)->getColor()->setRGB('15803D');
                }
                // สีสถานะ: ยกเลิก / คืนสินค้า / หมด -> แดงอ่อน
                elseif (in_array($valStr, ['ยกเลิก', 'คืนเงินแล้ว', 'หมด', 'cancelled', 'sold_out', 'returned'], true)) {
                    $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
                    $cellStyle->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
                }
                // สีสถานะ: รอชำระ / รอเตรียม / กำลังเตรียม -> ส้ม/เหลืองอ่อน
                elseif (in_array($valStr, ['รอชำระ', 'รอเตรียม', 'กำลังเตรียม', 'pending', 'packing'], true)) {
                    $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
                    $cellStyle->getFont()->setBold(true)->getColor()->setRGB('B45309');
                }
                // ซ่อน / อื่นๆ
                else {
                    $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                    $cellStyle->getFont()->getColor()->setRGB('475569');
                }
            }
            // 2) ตัวเลขเงิน / ทศนิยม
            elseif (str_contains($lowerCol, 'บาท') || str_contains($lowerCol, 'ราคา') || str_contains($lowerCol, 'ยอด') || str_contains($lowerCol, 'รายได้') || str_contains($lowerCol, 'gp') || str_contains($lowerCol, 'มูลค่า')) {
                $numVal = is_numeric($val) ? (float)$val : 0.0;
                $sheet->setCellValueExplicit($cellCoord, $numVal, DataType::TYPE_NUMERIC);
                $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            // 3) ตัวเลขจำนวนนับ (ออเดอร์, เล่ม, ครั้ง, สต็อก)
            elseif (str_contains($lowerCol, 'จำนวน') || str_contains($lowerCol, 'สต็อก') || str_contains($lowerCol, 'ครั้ง')) {
                $intVal = is_numeric($val) ? (int)$val : 0;
                $sheet->setCellValueExplicit($cellCoord, $intVal, DataType::TYPE_NUMERIC);
                $cellStyle->getNumberFormat()->setFormatCode('#,##0');
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            // 4) เลขคำสั่งซื้อ / เลขพัสดุ / รหัสหนังสือ (ป้องกันเลขกลายเป็น 1.23E+10)
            elseif (str_contains($lowerCol, 'เลข') || str_contains($lowerCol, 'รหัส') || str_contains($lowerCol, 'พัสดุ')) {
                $sheet->setCellValueExplicit($cellCoord, $valStr, DataType::TYPE_STRING);
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            // 5) วันที่ / ปี / เดือน
            elseif (str_contains($lowerCol, 'วัน') || str_contains($lowerCol, 'ปี') || str_contains($lowerCol, 'เดือน')) {
                $sheet->setCellValueExplicit($cellCoord, $valStr, DataType::TYPE_STRING);
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            // 6) ข้อความทั่วไป (ชื่อหนังสือ, ผู้แต่ง, อีเมล, ลูกค้า)
            else {
                $sheet->setCellValueExplicit($cellCoord, $valStr, DataType::TYPE_STRING);
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }

            $colIdx++;
        }
        $currentRow++;
    }

    // ใส่กริดเส้นขอบตารางข้อมูลทั้งหมด
    $sheet->getStyle("A4:{$lastColLetter}" . ($currentRow - 1))->applyFromArray($borderThin);

    // ── แถวรวมยอด (Summary Footer Row) ───────────────────────────────────────
    if (!empty($sumCols)) {
        $sheet->getRowDimension($currentRow)->setRowHeight(25);
        $sheet->setCellValue("A{$currentRow}", 'รวมทั้งหมด');
        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->getColor()->setRGB('1E293B');
        $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        foreach ($headers as $idx => $hName) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $cellCoord = "{$colLetter}{$currentRow}";
            $cellStyle = $sheet->getStyle($cellCoord);

            $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
            $cellStyle->getFont()->setBold(true)->getColor()->setRGB('1E293B');
            $cellStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            if (in_array($hName, $sumCols, true)) {
                $startData = 5;
                $endData = $currentRow - 1;
                $sheet->setCellValue($cellCoord, "=SUM({$colLetter}{$startData}:{$colLetter}{$endData})");
                $cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if (str_contains(mb_strtolower($hName), 'บาท') || str_contains(mb_strtolower($hName), 'ยอด') || str_contains(mb_strtolower($hName), 'รายได้') || str_contains(mb_strtolower($hName), 'มูลค่า')) {
                    $cellStyle->getNumberFormat()->setFormatCode('#,##0.00');
                } else {
                    $cellStyle->getNumberFormat()->setFormatCode('#,##0');
                }
            }
        }

        // เส้นขอบพิเศษสำหรับแถวสรุปยอด (บนเส้นเดี่ยว, ล่างเส้นคู่)
        $summaryBorders = [
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '475569']],
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
            ],
        ];
        $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray($summaryBorders);
    }

    // Freeze Pane: ตรึงหัวตารางไว้แถวที่ 4 เพื่อให้เลื่อนดูข้อมูลได้สะดวก
    $sheet->freezePane('A5');

    // Auto-fit Column Widths + เผื่อระยะขอบให้อ่านง่าย
    foreach (range(1, $totalCols) as $cIdx) {
        $colLetter = Coordinate::stringFromColumnIndex($cIdx);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }
}

/**
 * ส่งออกไฟล์ Excel (.xlsx) ให้ผู้ใช้ดาวน์โหลด
 */
function sendXlsx(Spreadsheet $spreadsheet, string $filename): void
{
    ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. ส่งออกประวัติคำสั่งซื้อ (Orders Export)
// ─────────────────────────────────────────────────────────────────────────────
if ($type === 'orders') {
    $stmt = $pdo->prepare("
        SELECT
            o.order_number                          AS 'เลขคำสั่งซื้อ',
            DATE_FORMAT(o.created_at,'%d/%m/%Y %H:%i') AS 'วันที่สั่งซื้อ',
            COALESCE(NULLIF(u.display_name,''), u.email) AS 'ลูกค้า',
            u.email                                 AS 'อีเมล',
            b.title                                 AS 'ชื่อหนังสือ',
            oi.quantity                             AS 'จำนวน',
            oi.price_at_purchase                    AS 'ราคา/เล่ม (บาท)',
            (oi.quantity * oi.price_at_purchase)    AS 'รวม (บาท)',
            CASE o.payment_status WHEN 'paid' THEN 'ชำระแล้ว' WHEN 'pending' THEN 'รอชำระ' WHEN 'cancelled' THEN 'ยกเลิก' ELSE o.payment_status END AS 'สถานะชำระ',
            CASE o.shipping_status WHEN 'pending' THEN 'รอเตรียม' WHEN 'packing' THEN 'กำลังเตรียม' WHEN 'shipped' THEN 'จัดส่งแล้ว' WHEN 'delivered' THEN 'ส่งถึงแล้ว' ELSE o.shipping_status END AS 'สถานะจัดส่ง',
            COALESCE(o.tracking_number, '-')        AS 'เลขพัสดุ'
        FROM order_items oi
        JOIN orders o ON o.order_id = oi.order_id
        JOIN books  b ON b.book_id  = oi.book_id
        JOIN users  u ON u.user_id  = o.user_id
        WHERE b.seller_type = ? AND b.seller_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5000
    ");
    $stmt->execute([$sellerType, $sellerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ประวัติคำสั่งซื้อ');

    buildStyledWorksheet(
        $sheet,
        'รายงานประวัติคำสั่งซื้อ (Orders History)',
        "{$sellerLabel}: {$sellerName}  ·  วันที่ออกรายงาน: " . date('d/m/Y H:i'),
        $rows,
        ['จำนวน', 'รวม (บาท)']
    );

    sendXlsx($spreadsheet, "orders_{$dateLabel}.xlsx");
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. ส่งออกรายการสต็อกสินค้า (Stock Inventory Export)
// ─────────────────────────────────────────────────────────────────────────────
if ($type === 'stock') {
    $stmt = $pdo->prepare("
        SELECT
            b.book_id                               AS 'รหัสหนังสือ',
            b.title                                 AS 'ชื่อหนังสือ',
            b.author                                AS 'ผู้แต่ง',
            COALESCE(NULLIF(b.category,''),'-')     AS 'หมวดหมู่',
            b.price                                 AS 'ราคา (บาท)',
            b.stock_quantity                        AS 'สต็อก (เล่ม)',
            CASE b.status WHEN 'available' THEN 'วางขาย' WHEN 'hidden' THEN 'ซ่อน' WHEN 'sold_out' THEN 'หมด' ELSE b.status END AS 'สถานะ',
            DATE_FORMAT(b.created_at,'%d/%m/%Y')    AS 'วันที่เพิ่ม'
        FROM books b
        WHERE b.seller_type = ? AND b.seller_id = ?
        ORDER BY b.stock_quantity ASC, b.title ASC
        LIMIT 5000
    ");
    $stmt->execute([$sellerType, $sellerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('สต็อกสินค้า');

    buildStyledWorksheet(
        $sheet,
        'รายงานสต็อกสินค้าคงคลัง (Inventory Stock)',
        "{$sellerLabel}: {$sellerName}  ·  วันที่ออกรายงาน: " . date('d/m/Y H:i'),
        $rows,
        ['สต็อก (เล่ม)']
    );

    sendXlsx($spreadsheet, "stock_{$dateLabel}.xlsx");
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ส่งออกสรุปยอดขายรายเดือน (Revenue Summary Export - Multi Sheets)
// ─────────────────────────────────────────────────────────────────────────────
if ($type === 'summary') {
    $year = (int)($_GET['year'] ?? date('Y'));

    // ส่วนที่ 1: สรุปรายเดือน
    $stmtMonthly = $pdo->prepare("
        SELECT
            yr                AS 'ปี',
            ELT(mo,'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                   'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม') AS 'เดือน',
            order_count         AS 'จำนวนออเดอร์',
            book_count          AS 'จำนวนเล่มที่ขาย',
            gross_sales         AS 'ยอดขายรวม (บาท)',
            gp_total            AS 'ค่า GP หักออก {$gpPercent}% (บาท)',
            net_sales           AS 'รายได้สุทธิ (บาท)',
            return_count        AS 'ครั้งที่ถูกคืนสินค้า',
            return_amount       AS 'มูลค่าคืนเงิน (บาท)'
        FROM (
            SELECT
                YEAR(o.created_at)                                      yr,
                MONTH(o.created_at)                                     mo,
                COUNT(DISTINCT o.order_id)                              order_count,
                SUM(oi.quantity)                                        book_count,
                ROUND(SUM(oi.quantity * oi.price_at_purchase), 2)      gross_sales,
                ROUND(SUM(oi.quantity * oi.price_at_purchase) * {$gpRate}, 2) gp_total,
                ROUND(SUM(oi.quantity * oi.price_at_purchase) * {$netRate}, 2) net_sales,
                COALESCE(MAX(r.cnt), 0)                                 return_count,
                COALESCE(MAX(r.amt), 0)                                 return_amount
            FROM order_items oi
            JOIN orders o ON o.order_id = oi.order_id
            JOIN books  b ON b.book_id  = oi.book_id
            LEFT JOIN (
                SELECT
                    MONTH(created_at) mo2,
                    YEAR(created_at)  yr2,
                    COUNT(*)          cnt,
                    ROUND(SUM(refund_amount), 2) amt
                FROM return_requests
                WHERE seller_type = ? AND seller_id = ?
                  AND status IN ('shop_received','refunded_to_wallet')
                  AND YEAR(created_at) = ?
                GROUP BY YEAR(created_at), MONTH(created_at)
            ) r ON r.mo2 = MONTH(o.created_at) AND r.yr2 = YEAR(o.created_at)
            WHERE b.seller_type = ? AND b.seller_id = ?
              AND o.payment_status = 'paid'
              AND YEAR(o.created_at) = ?
            GROUP BY YEAR(o.created_at), MONTH(o.created_at)
        ) sub
        ORDER BY mo
    ");
    $stmtMonthly->execute([$sellerType, $sellerId, $year,
                           $sellerType, $sellerId, $year]);
    $monthlyRows = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

    // ส่วนที่ 2: รายละเอียดหนังสือแต่ละเล่ม
    $stmtBooks = $pdo->prepare("
        SELECT
            yr                AS 'ปี',
            ELT(mo,'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                   'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม') AS 'เดือน',
            title             AS 'ชื่อหนังสือ',
            author            AS 'ผู้แต่ง',
            qty               AS 'จำนวนเล่มที่ขาย',
            unit_price        AS 'ราคา/เล่ม (บาท)',
            line_gross        AS 'รายได้รวม (บาท)',
            line_gp           AS 'ค่า GP {$gpPercent}% (บาท)',
            line_net          AS 'รายได้สุทธิ (บาท)'
        FROM (
            SELECT
                YEAR(o.created_at)                                      yr,
                MONTH(o.created_at)                                     mo,
                b.title                                                 title,
                b.author                                                author,
                SUM(oi.quantity)                                        qty,
                oi.price_at_purchase                                    unit_price,
                ROUND(SUM(oi.quantity * oi.price_at_purchase), 2)       line_gross,
                ROUND(SUM(oi.quantity * oi.price_at_purchase) * {$gpRate}, 2) line_gp,
                ROUND(SUM(oi.quantity * oi.price_at_purchase) * {$netRate}, 2) line_net
            FROM order_items oi
            JOIN orders o ON o.order_id = oi.order_id
            JOIN books  b ON b.book_id  = oi.book_id
            WHERE b.seller_type = ? AND b.seller_id = ?
              AND o.payment_status = 'paid'
              AND YEAR(o.created_at) = ?
            GROUP BY YEAR(o.created_at), MONTH(o.created_at), b.book_id, b.title, b.author, oi.price_at_purchase
        ) sub
        ORDER BY mo, line_gross DESC
    ");
    $stmtBooks->execute([$sellerType, $sellerId, $year]);
    $bookRows = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();

    // ── Sheet 1: สรุปรายเดือน ───────────────────────────────────────────────
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('สรุปยอดขายรายเดือน');
    buildStyledWorksheet(
        $sheet1,
        "รายงานสรุปยอดขายรายเดือน ปี {$year}",
        "{$sellerLabel}: {$sellerName}  ·  วันที่ออกรายงาน: " . date('d/m/Y H:i'),
        $monthlyRows,
        ['จำนวนออเดอร์', 'จำนวนเล่มที่ขาย', 'ยอดขายรวม (บาท)', "ค่า GP หักออก {$gpPercent}% (บาท)", 'รายได้สุทธิ (บาท)', 'ครั้งที่ถูกคืนสินค้า', 'มูลค่าคืนเงิน (บาท)']
    );

    // ── Sheet 2: รายละเอียดหนังสือ ──────────────────────────────────────────
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('รายละเอียดหนังสือที่ขาย');
    buildStyledWorksheet(
        $sheet2,
        "รายละเอียดหนังสือที่ขายรายเดือน ปี {$year} (หักค่า GP {$gpPercent}%)",
        "{$sellerLabel}: {$sellerName}  ·  วันที่ออกรายงาน: " . date('d/m/Y H:i'),
        $bookRows,
        ['จำนวนเล่มที่ขาย', 'รายได้รวม (บาท)', "ค่า GP {$gpPercent}% (บาท)", 'รายได้สุทธิ (บาท)']
    );

    // เลือก sheet 1 เป็น active sheet เมื่อเปิดไฟล์
    $spreadsheet->setActiveSheetIndex(0);

    sendXlsx($spreadsheet, "summary_{$year}_{$dateLabel}.xlsx");
}

http_response_code(400);
echo 'ประเภทข้อมูลไม่ถูกต้อง';
