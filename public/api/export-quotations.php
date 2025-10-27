<?php
/**
 * Export Quotations API Endpoint
 * Exports quotations to XLSX format with professional formatting
 * Features: Color coding, formulas, summaries, and proper alignment
 */

session_start();

// Initialize timezone
require_once __DIR__ . '/../init_timezone.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

// Get POST data
$input = json_decode($_POST['data'] ?? '{}', true);

if (empty($input['quotations'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No data provided']);
    exit;
}

$quotations = $input['quotations'];
$stats = $input['stats'];
$user = $input['user'] ?? 'System';
$currency = $input['currency'] ?? '$';
$currencyCode = $input['currencyCode'] ?? 'USD';
$timezone = $input['timezone'] ?? 'UTC';
$timestamp = date('Y-m-d H:i:s', strtotime($input['timestamp']));

// Create new Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Quotations Export');

// Set document properties
$spreadsheet->getProperties()
    ->setCreator($user)
    ->setTitle('Sales Quotations Export')
    ->setSubject('Quotations Report')
    ->setDescription('Exported quotations with summary statistics and financial breakdown')
    ->setCreated(time());

// ============================================
// TITLE SECTION
// ============================================
$currentRow = 1;

// Main Title
$sheet->setCellValue('A' . $currentRow, 'SALES QUOTATIONS EXPORT REPORT');
$sheet->mergeCells('A' . $currentRow . ':J' . $currentRow);
$sheet->getStyle('A' . $currentRow)->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 18,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '7194A5']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);
$sheet->getRowDimension($currentRow)->setRowHeight(35);
$currentRow++;

// Report Info
$sheet->setCellValue('A' . $currentRow, 'Generated:');
$sheet->setCellValue('B' . $currentRow, $timestamp);
$sheet->setCellValue('D' . $currentRow, 'Timezone:');
$sheet->setCellValue('E' . $currentRow, $timezone);
$sheet->setCellValue('G' . $currentRow, 'Currency:');
$sheet->setCellValue('H' . $currentRow, $currency . ' (' . $currencyCode . ')');
$sheet->setCellValue('I' . $currentRow, 'Exported by:');
$sheet->setCellValue('J' . $currentRow, $user);
$sheet->getStyle('A' . $currentRow . ':H' . $currentRow)->applyFromArray([
    'font' => ['bold' => true, 'size' => 10],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F0F0F0']
    ]
]);
$currentRow += 2;

// ============================================
// SUMMARY STATISTICS SECTION
// ============================================
$summaryStartRow = $currentRow;

// Section Header
$sheet->setCellValue('A' . $currentRow, 'SUMMARY STATISTICS');
$sheet->mergeCells('A' . $currentRow . ':D' . $currentRow);
$sheet->getStyle('A' . $currentRow)->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4A90A4']
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$currentRow++;

// Statistics Table
$summaryData = [
    ['Total Quotations:', $stats['total'], '', ''],
    ['Pending:', $stats['pending'], $stats['pendingPercent'] . '%', 'FFF9C4'],
    ['Approved:', $stats['approved'], $stats['approvedPercent'] . '%', 'C8E6C9'],
    ['Rejected:', $stats['rejected'], $stats['rejectedPercent'] . '%', 'FFCDD2'],
    ['Converted:', $stats['converted'], $stats['convertedPercent'] . '%', 'E1F5FE']
];

foreach ($summaryData as $row) {
    $sheet->setCellValue('A' . $currentRow, $row[0]);
    $sheet->setCellValue('B' . $currentRow, $row[1]);
    $sheet->setCellValue('C' . $currentRow, $row[2]);
    
    $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true);
    $sheet->getStyle('B' . $currentRow . ':C' . $currentRow)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    if (isset($row[3])) {
        $sheet->getStyle('A' . $currentRow . ':C' . $currentRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color($row[3]));
    }
    
    $currentRow++;
}

$currentRow++;

// ============================================
// FINANCIAL SUMMARY SECTION
// ============================================
$sheet->setCellValue('F' . $summaryStartRow, 'FINANCIAL SUMMARY');
$sheet->mergeCells('F' . $summaryStartRow . ':J' . $summaryStartRow);
$sheet->getStyle('F' . $summaryStartRow)->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '388E3C']
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

$financialRow = $summaryStartRow + 1;
$financialData = [
    ['Total Value:', $stats['totalValue'], 'E3F2FD'],
    ['Approved Value:', $stats['approvedValue'], 'C8E6C9'],
    ['Pending Value:', $stats['pendingValue'], 'FFF9C4'],
    ['Rejected Loss:', $stats['rejectedValue'], 'FFCDD2'],
    ['Net Realizable:', $stats['netValue'], 'A5D6A7']
];

foreach ($financialData as $row) {
    $sheet->setCellValue('F' . $financialRow, $row[0]);
    $sheet->setCellValue('G' . $financialRow, $row[1]);
    
    $sheet->getStyle('F' . $financialRow)->getFont()->setBold(true);
    $sheet->getStyle('G' . $financialRow)->getNumberFormat()
        ->setFormatCode('#,##0.00');
    $sheet->getStyle('G' . $financialRow)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    if (isset($row[2])) {
        $sheet->getStyle('F' . $financialRow . ':G' . $financialRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color($row[2]));
    }
    
    $financialRow++;
}

$currentRow = max($currentRow, $financialRow) + 1;

// ============================================
// DATA TABLE SECTION
// ============================================

// Column Headers
$headers = [
    'A' => 'Quote #',
    'B' => 'Customer',
    'C' => 'Company',
    'D' => 'Email',
    'E' => 'Phone',
    'F' => 'Date',
    'G' => 'Validity',
    'H' => 'Status',
    'I' => 'Amount',
    'J' => 'Currency'
];

foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . $currentRow, $header);
}

$sheet->getStyle('A' . $currentRow . ':J' . $currentRow)->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2C5F6F']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
]);
$sheet->getRowDimension($currentRow)->setRowHeight(25);
$currentRow++;

// Data Rows
$dataStartRow = $currentRow;
foreach ($quotations as $q) {
    // Safe date handling
    $formattedDate = 'N/A';
    if (isset($q['date']) && is_string($q['date']) && !empty($q['date'])) {
        $timestamp = strtotime($q['date']);
        if ($timestamp !== false) {
            $formattedDate = date('M d, Y', $timestamp);
        } else {
            $formattedDate = $q['date']; // Use original if parsing fails
        }
    }
    
    $sheet->setCellValue('A' . $currentRow, $q['quote_number'] ?? 'N/A');
    $sheet->setCellValue('B' . $currentRow, $q['customer'] ?? 'N/A');
    $sheet->setCellValue('C' . $currentRow, $q['customer_company'] ?? '');
    $sheet->setCellValue('D' . $currentRow, $q['customer_email'] ?? '');
    $sheet->setCellValue('E' . $currentRow, $q['customer_phone'] ?? '');
    $sheet->setCellValue('F' . $currentRow, $formattedDate);
    $sheet->setCellValue('G' . $currentRow, $q['validity_days'] ?? 30);
    $sheet->setCellValue('H' . $currentRow, strtoupper($q['status'] ?? 'PENDING'));
    $sheet->setCellValue('I' . $currentRow, floatval($q['total'] ?? 0));
    $sheet->setCellValue('J' . $currentRow, $currency);
    
    // Status color coding
    $status = strtolower($q['status'] ?? 'pending');
    $statusColors = [
        'pending' => 'FFF9C4',
        'approved' => 'C8E6C9',
        'rejected' => 'FFCDD2',
        'converted' => 'E1F5FE'
    ];
    
    if (isset($statusColors[$status])) {
        $sheet->getStyle('H' . $currentRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color($statusColors[$status]));
    }
    
    // Amount formatting
    $sheet->getStyle('I' . $currentRow)->getNumberFormat()
        ->setFormatCode('#,##0.00');
    
    // Borders
    $sheet->getStyle('A' . $currentRow . ':J' . $currentRow)
        ->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)
        ->setColor(new Color('CCCCCC'));
    
    // Alternate row colors
    if (($currentRow - $dataStartRow) % 2 == 0) {
        $sheet->getStyle('A' . $currentRow . ':J' . $currentRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color('F9F9F9'));
    }
    
    $currentRow++;
}

$dataEndRow = $currentRow - 1;

// ============================================
// TOTALS ROW WITH FORMULAS
// ============================================
$currentRow++;
$sheet->setCellValue('A' . $currentRow, 'TOTAL');
$sheet->mergeCells('A' . $currentRow . ':H' . $currentRow);
$sheet->setCellValue('I' . $currentRow, '=SUM(I' . $dataStartRow . ':I' . $dataEndRow . ')');

$sheet->getStyle('A' . $currentRow . ':J' . $currentRow)->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 12,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '388E3C']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_RIGHT,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_MEDIUM,
            'color' => ['rgb' => '2E7D32']
        ]
    ]
]);
$sheet->getStyle('I' . $currentRow)->getNumberFormat()
    ->setFormatCode('#,##0.00');
$sheet->getRowDimension($currentRow)->setRowHeight(30);

// ============================================
// COLUMN WIDTHS
// ============================================
$sheet->getColumnDimension('A')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(25);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(30);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(18);
$sheet->getColumnDimension('H')->setWidth(12);
$sheet->getColumnDimension('I')->setWidth(15);
$sheet->getColumnDimension('J')->setWidth(10);

// ============================================
// FOOTER
// ============================================
$currentRow += 2;
$sheet->setCellValue('A' . $currentRow, 'Report generated by Inventory Management System');
$sheet->mergeCells('A' . $currentRow . ':J' . $currentRow);
$sheet->getStyle('A' . $currentRow)->applyFromArray([
    'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '666666']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// ============================================
// FREEZE PANES & PRINT SETTINGS
// ============================================
$sheet->freezePane('A' . $dataStartRow);

// Set print area
$sheet->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
    ->setFitToWidth(1)
    ->setFitToHeight(0);

// Set margins
$sheet->getPageMargins()
    ->setTop(0.5)
    ->setRight(0.5)
    ->setLeft(0.5)
    ->setBottom(0.5);

// ============================================
// OUTPUT FILE
// ============================================
$filename = 'Quotations_Export_' . date('Y-m-d_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
exit;
