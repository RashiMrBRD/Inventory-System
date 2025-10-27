<?php
/**
 * Export Journal Entries API Endpoint
 * Exports journal entries to XLSX format
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

// Get filter parameters from query string
$typeFilter = $_GET['type'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Get all journal entries
$result = $accountingController->getAllJournalEntries();

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch journal entries']);
    exit;
}

$entries = $result['data'];

// Apply filters (same logic as the main page)
$filteredEntries = array_filter($entries, function($entry) use ($typeFilter, $statusFilter, $dateFrom, $dateTo, $searchQuery) {
    // Type filter
    if ($typeFilter !== 'all' && ($entry['type'] ?? '') !== $typeFilter) {
        return false;
    }
    
    // Status filter
    if ($statusFilter !== 'all') {
        $entryStatus = $entry['status'] ?? 'draft';
        if ($statusFilter !== $entryStatus) return false;
    }
    
    // Date range filter
    if ($dateFrom && isset($entry['date'])) {
        $entryDate = date('Y-m-d', strtotime($entry['date']));
        if ($entryDate < $dateFrom) return false;
    }
    if ($dateTo && isset($entry['date'])) {
        $entryDate = date('Y-m-d', strtotime($entry['date']));
        if ($entryDate > $dateTo) return false;
    }
    
    // Search filter
    if ($searchQuery) {
        $searchLower = strtolower($searchQuery);
        $refLower = strtolower($entry['reference'] ?? '');
        $descLower = strtolower($entry['description'] ?? '');
        if (strpos($refLower, $searchLower) === false && strpos($descLower, $searchLower) === false) {
            return false;
        }
    }
    
    return true;
});

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Journal Entries');

// Set document properties
$spreadsheet->getProperties()
    ->setCreator($_SESSION['username'] ?? 'System')
    ->setTitle('Journal Entries')
    ->setSubject('Accounting Data Export')
    ->setDescription('Journal Entries exported from Inventory Management System')
    ->setCreated(time());

// Define headers
$headers = ['Date', 'Reference', 'Type', 'Description', 'Total Debit', 'Total Credit', 'Status', 'Posted By', 'Posted Date'];
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
$sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(12); // Date
$sheet->getColumnDimension('B')->setWidth(28); // Reference (increased to fit 25+ chars)
$sheet->getColumnDimension('C')->setWidth(15); // Type
$sheet->getColumnDimension('D')->setWidth(35); // Description
$sheet->getColumnDimension('E')->setWidth(18); // Total Debit (increased for currency display)
$sheet->getColumnDimension('F')->setWidth(18); // Total Credit (increased for currency display)
$sheet->getColumnDimension('G')->setWidth(12); // Status
$sheet->getColumnDimension('H')->setWidth(20); // Posted By
$sheet->getColumnDimension('I')->setWidth(18); // Posted Date

// Add data rows
$row = 2;
foreach ($filteredEntries as $entry) {
    $status = $entry['status'] ?? 'draft';
    $statusText = ucfirst($status);
    
    // Calculate totals
    $totalDebit = 0;
    $totalCredit = 0;
    if (isset($entry['items']) && is_array($entry['items'])) {
        foreach ($entry['items'] as $item) {
            $totalDebit += $item['debit'] ?? 0;
            $totalCredit += $item['credit'] ?? 0;
        }
    }
    
    // Format entry type
    $type = ucfirst($entry['type'] ?? 'general');
    
    // Format date
    $date = isset($entry['date']) ? date('Y-m-d', strtotime($entry['date'])) : '';
    
    // Posted info
    $postedBy = $entry['posted_by'] ?? '-';
    $postedDate = isset($entry['posted_date']) ? date('Y-m-d H:i', strtotime($entry['posted_date'])) : '-';
    
    $sheet->setCellValue('A' . $row, $date);
    $sheet->setCellValue('B' . $row, $entry['reference'] ?? '');
    $sheet->setCellValue('C' . $row, $type);
    $sheet->setCellValue('D' . $row, $entry['description'] ?? '');
    $sheet->setCellValue('E' . $row, $totalDebit);
    $sheet->setCellValue('F' . $row, $totalCredit);
    $sheet->setCellValue('G' . $row, $statusText);
    $sheet->setCellValue('H' . $row, $postedBy);
    $sheet->setCellValue('I' . $row, $postedDate);
    
    // Format currency columns
    $sheet->getStyle('E' . $row)->getNumberFormat()
        ->setFormatCode($currencySymbol . '#,##0.00');
    $sheet->getStyle('F' . $row)->getNumberFormat()
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
    $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($dataStyle);
    
    // Alternate row colors
    if ($row % 2 == 0) {
        $sheet->getStyle('A' . $row . ':I' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F9FAFB');
    }
    
    // Right align amounts
    $sheet->getStyle('E' . $row)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('F' . $row)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Center align status
    $sheet->getStyle('G' . $row)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $row++;
}

// Freeze header row
$sheet->freezePane('A2');

// Auto-filter
$sheet->setAutoFilter('A1:I' . ($row - 1));

// Generate filename with timestamp
$timestamp = date('Y-m-d_His');
$filename = 'Journal_Entries_' . $timestamp . '.xlsx';

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
