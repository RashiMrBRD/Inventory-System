<?php
/**
 * Export Accounts API Endpoint
 * Exports chart of accounts to XLSX format
 * Properly handles currency symbols and UTF-8 encoding
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

use App\Controller\AccountingController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$accountingController = new AccountingController();

// Get currency settings
$currency = $_SESSION['currency'] ?? 'PHP';
$currencySymbols = [
    'PHP' => '₱',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'JPY' => '¥',
    'CNY' => '¥',
    'KRW' => '₩',
    'MYR' => 'RM',
    'SGD' => 'S$',
    'THB' => '฿',
    'IDR' => 'Rp',
    'VND' => '₫',
    'INR' => '₹',
    'AUD' => 'A$',
    'CAD' => 'C$'
];
$currencySymbol = $currencySymbols[$currency] ?? $currency . ' ';

// Get all accounts
$result = $accountingController->getAllAccounts();

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch accounts']);
    exit;
}

$accounts = $result['data'];

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Chart of Accounts');

// Set document properties
$spreadsheet->getProperties()
    ->setCreator($_SESSION['username'] ?? 'System')
    ->setTitle('Chart of Accounts')
    ->setSubject('Accounting Data Export')
    ->setDescription('Chart of Accounts exported from Inventory Management System')
    ->setCreated(time());

// Define headers
$headers = ['Code', 'Account Name', 'Type', 'Subtype', 'Balance', 'Status', 'Description'];
$sheet->fromArray($headers, null, 'A1');

// Style header row
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '000000'] // Black color
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(12); // Code
$sheet->getColumnDimension('B')->setWidth(30); // Account Name
$sheet->getColumnDimension('C')->setWidth(15); // Type
$sheet->getColumnDimension('D')->setWidth(20); // Subtype
$sheet->getColumnDimension('E')->setWidth(18); // Balance
$sheet->getColumnDimension('F')->setWidth(12); // Status
$sheet->getColumnDimension('G')->setWidth(40); // Description

// Add data rows
$row = 2;
foreach ($accounts as $account) {
    $status = ($account['is_active'] ?? true) ? 'Active' : 'Inactive';
    $balance = $account['balance'] ?? 0;
    
    // Format account type
    $type = ucfirst($account['account_type'] ?? '');
    
    // Format subtype (convert underscores to spaces and title case)
    $subtype = '';
    if (!empty($account['account_subtype'])) {
        $subtype = ucwords(str_replace('_', ' ', $account['account_subtype']));
    }
    
    $sheet->setCellValue('A' . $row, $account['account_code'] ?? '');
    $sheet->setCellValue('B' . $row, $account['account_name'] ?? '');
    $sheet->setCellValue('C' . $row, $type);
    $sheet->setCellValue('D' . $row, $subtype);
    $sheet->setCellValue('E' . $row, $balance);
    $sheet->setCellValue('F' . $row, $status);
    $sheet->setCellValue('G' . $row, $account['description'] ?? '');
    
    // Format balance column as currency
    $sheet->getStyle('E' . $row)->getNumberFormat()
        ->setFormatCode($currencySymbol . '#,##0.00');
    
    // Style data rows
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E5E7EB']
            ]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($dataStyle);
    
    // Alternate row colors
    if ($row % 2 == 0) {
        $sheet->getStyle('A' . $row . ':G' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F9FAFB');
    }
    
    // Right align balance
    $sheet->getStyle('E' . $row)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Center align status
    $sheet->getStyle('F' . $row)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $row++;
}

// Freeze header row
$sheet->freezePane('A2');

// Auto-filter
$sheet->setAutoFilter('A1:G' . ($row - 1));

// Generate filename with timestamp
$timestamp = date('Y-m-d_His');
$filename = 'Chart_of_Accounts_' . $timestamp . '.xlsx';

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

// Write file to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// Clean up
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
exit;
