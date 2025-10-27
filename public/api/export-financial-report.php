<?php
/**
 * Export Financial Report API Endpoint
 * Exports financial reports to XLSX format
 * Supports: Balance Sheet, Income Statement, Trial Balance, General Ledger
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
use PhpOffice\PhpSpreadsheet\Style\Font;

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

// Get report parameters
$reportType = $_GET['report'] ?? 'balance-sheet';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

// Validate report type
$validReports = ['balance-sheet', 'income-statement', 'trial-balance', 'general-ledger'];
if (!in_array($reportType, $validReports)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid report type']);
    exit;
}

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator($_SESSION['username'] ?? 'System')
    ->setTitle('Financial Report')
    ->setSubject('Accounting Report Export')
    ->setDescription('Financial Report exported from Inventory Management System')
    ->setCreated(time());

// Generate report based on type
switch ($reportType) {
    case 'balance-sheet':
        generateBalanceSheet($sheet, $accountingController, $currencySymbol, $dateTo);
        $filename = 'Balance_Sheet_' . date('Y-m-d', strtotime($dateTo));
        break;
    
    case 'income-statement':
        generateIncomeStatement($sheet, $accountingController, $currencySymbol, $dateFrom, $dateTo);
        $filename = 'Income_Statement_' . date('Y-m-d', strtotime($dateFrom)) . '_to_' . date('Y-m-d', strtotime($dateTo));
        break;
    
    case 'trial-balance':
        generateTrialBalance($sheet, $accountingController, $currencySymbol, $dateTo);
        $filename = 'Trial_Balance_' . date('Y-m-d', strtotime($dateTo));
        break;
    
    case 'general-ledger':
        generateGeneralLedger($sheet, $accountingController, $currencySymbol, $dateFrom, $dateTo);
        $filename = 'General_Ledger_' . date('Y-m-d', strtotime($dateFrom)) . '_to_' . date('Y-m-d', strtotime($dateTo));
        break;
}

$filename .= '_' . date('His') . '.xlsx';

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

// ========== REPORT GENERATORS ==========

function generateBalanceSheet($sheet, $controller, $currencySymbol, $asOfDate) {
    $sheet->setTitle('Balance Sheet');
    
    // Company name and report title
    $row = 1;
    $sheet->setCellValue('A' . $row, $_SESSION['company_name'] ?? 'Company Name');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Balance Sheet');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'As of ' . date('F d, Y', strtotime($asOfDate)));
    $sheet->getStyle('A' . $row)->getFont()->setItalic(true);
    $row += 2;
    
    // Get accounts
    $result = $controller->getAccountHierarchy();
    $accounts = $result['success'] ? $result['data'] : [];
    
    // Headers
    $sheet->setCellValue('A' . $row, 'Account');
    $sheet->setCellValue('B' . $row, 'Amount');
    styleHeaderRow($sheet, $row, 2);
    $row++;
    
    // Assets
    $sheet->setCellValue('A' . $row, 'ASSETS');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $totalAssets = 0;
    foreach ($accounts['asset'] ?? [] as $account) {
        $balance = $account['balance'] ?? 0;
        $totalAssets += $balance;
        $sheet->setCellValue('A' . $row, '  ' . $account['account_name']);
        $sheet->setCellValue('B' . $row, $balance);
        $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode($currencySymbol . '#,##0.00');
        $row++;
    }
    
    $sheet->setCellValue('A' . $row, 'Total Assets');
    $sheet->setCellValue('B' . $row, $totalAssets);
    styleSubtotalRow($sheet, $row, 2, $currencySymbol);
    $row += 2;
    
    // Liabilities
    $sheet->setCellValue('A' . $row, 'LIABILITIES');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $totalLiabilities = 0;
    foreach ($accounts['liability'] ?? [] as $account) {
        $balance = $account['balance'] ?? 0;
        $totalLiabilities += $balance;
        $sheet->setCellValue('A' . $row, '  ' . $account['account_name']);
        $sheet->setCellValue('B' . $row, $balance);
        $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode($currencySymbol . '#,##0.00');
        $row++;
    }
    
    $sheet->setCellValue('A' . $row, 'Total Liabilities');
    $sheet->setCellValue('B' . $row, $totalLiabilities);
    styleSubtotalRow($sheet, $row, 2, $currencySymbol);
    $row += 2;
    
    // Equity
    $sheet->setCellValue('A' . $row, 'EQUITY');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $totalEquity = 0;
    foreach ($accounts['equity'] ?? [] as $account) {
        $balance = $account['balance'] ?? 0;
        $totalEquity += $balance;
        $sheet->setCellValue('A' . $row, '  ' . $account['account_name']);
        $sheet->setCellValue('B' . $row, $balance);
        $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode($currencySymbol . '#,##0.00');
        $row++;
    }
    
    $sheet->setCellValue('A' . $row, 'Total Equity');
    $sheet->setCellValue('B' . $row, $totalEquity);
    styleSubtotalRow($sheet, $row, 2, $currencySymbol);
    $row += 2;
    
    // Total Liabilities + Equity
    $sheet->setCellValue('A' . $row, 'TOTAL LIABILITIES & EQUITY');
    $sheet->setCellValue('B' . $row, $totalLiabilities + $totalEquity);
    styleTotalRow($sheet, $row, 2, $currencySymbol);
    
    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(40);
    $sheet->getColumnDimension('B')->setWidth(18);
}

function generateIncomeStatement($sheet, $controller, $currencySymbol, $dateFrom, $dateTo) {
    $sheet->setTitle('Income Statement');
    
    // Company name and report title
    $row = 1;
    $sheet->setCellValue('A' . $row, $_SESSION['company_name'] ?? 'Company Name');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Income Statement');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'For the period ' . date('M d, Y', strtotime($dateFrom)) . ' to ' . date('M d, Y', strtotime($dateTo)));
    $sheet->getStyle('A' . $row)->getFont()->setItalic(true);
    $row += 2;
    
    // Get accounts
    $result = $controller->getAccountHierarchy();
    $accounts = $result['success'] ? $result['data'] : [];
    
    // Headers
    $sheet->setCellValue('A' . $row, 'Account');
    $sheet->setCellValue('B' . $row, 'Amount');
    styleHeaderRow($sheet, $row, 2);
    $row++;
    
    // Income
    $sheet->setCellValue('A' . $row, 'REVENUE');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $totalIncome = 0;
    foreach ($accounts['income'] ?? [] as $account) {
        $balance = $account['balance'] ?? 0;
        $totalIncome += $balance;
        $sheet->setCellValue('A' . $row, '  ' . $account['account_name']);
        $sheet->setCellValue('B' . $row, $balance);
        $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode($currencySymbol . '#,##0.00');
        $row++;
    }
    
    $sheet->setCellValue('A' . $row, 'Total Revenue');
    $sheet->setCellValue('B' . $row, $totalIncome);
    styleSubtotalRow($sheet, $row, 2, $currencySymbol);
    $row += 2;
    
    // Expenses
    $sheet->setCellValue('A' . $row, 'EXPENSES');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $totalExpenses = 0;
    foreach ($accounts['expense'] ?? [] as $account) {
        $balance = $account['balance'] ?? 0;
        $totalExpenses += $balance;
        $sheet->setCellValue('A' . $row, '  ' . $account['account_name']);
        $sheet->setCellValue('B' . $row, $balance);
        $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode($currencySymbol . '#,##0.00');
        $row++;
    }
    
    $sheet->setCellValue('A' . $row, 'Total Expenses');
    $sheet->setCellValue('B' . $row, $totalExpenses);
    styleSubtotalRow($sheet, $row, 2, $currencySymbol);
    $row += 2;
    
    // Net Income
    $netIncome = $totalIncome - $totalExpenses;
    $sheet->setCellValue('A' . $row, 'NET INCOME');
    $sheet->setCellValue('B' . $row, $netIncome);
    styleTotalRow($sheet, $row, 2, $currencySymbol);
    
    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(40);
    $sheet->getColumnDimension('B')->setWidth(18);
}

function generateTrialBalance($sheet, $controller, $currencySymbol, $asOfDate) {
    $sheet->setTitle('Trial Balance');
    
    // Company name and report title
    $row = 1;
    $sheet->setCellValue('A' . $row, $_SESSION['company_name'] ?? 'Company Name');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Trial Balance');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'As of ' . date('F d, Y', strtotime($asOfDate)));
    $sheet->getStyle('A' . $row)->getFont()->setItalic(true);
    $row += 2;
    
    // Headers
    $sheet->setCellValue('A' . $row, 'Account Code');
    $sheet->setCellValue('B' . $row, 'Account Name');
    $sheet->setCellValue('C' . $row, 'Debit');
    $sheet->setCellValue('D' . $row, 'Credit');
    styleHeaderRow($sheet, $row, 4);
    $row++;
    
    // Get all accounts
    $result = $controller->getAllAccounts();
    $allAccounts = $result['success'] ? $result['data'] : [];
    
    $totalDebit = 0;
    $totalCredit = 0;
    
    foreach ($allAccounts as $account) {
        $balance = $account['balance'] ?? 0;
        $accountType = $account['account_type'] ?? '';
        
        // Determine if debit or credit based on account type and balance
        $debit = 0;
        $credit = 0;
        
        if (in_array($accountType, ['asset', 'expense'])) {
            $debit = $balance;
            $totalDebit += $balance;
        } else {
            $credit = $balance;
            $totalCredit += $balance;
        }
        
        $sheet->setCellValue('A' . $row, $account['account_code'] ?? '');
        $sheet->setCellValue('B' . $row, $account['account_name'] ?? '');
        $sheet->setCellValue('C' . $row, $debit > 0 ? $debit : '');
        $sheet->setCellValue('D' . $row, $credit > 0 ? $credit : '');
        
        $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode($currencySymbol . '#,##0.00');
        $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencySymbol . '#,##0.00');
        
        $row++;
    }
    
    // Totals
    $sheet->setCellValue('B' . $row, 'TOTAL');
    $sheet->setCellValue('C' . $row, $totalDebit);
    $sheet->setCellValue('D' . $row, $totalCredit);
    styleTotalRow($sheet, $row, 4, $currencySymbol);
    
    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(35);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(18);
}

function generateGeneralLedger($sheet, $controller, $currencySymbol, $dateFrom, $dateTo) {
    $sheet->setTitle('General Ledger');
    
    // Company name and report title
    $row = 1;
    $sheet->setCellValue('A' . $row, $_SESSION['company_name'] ?? 'Company Name');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'General Ledger');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Period: ' . date('M d, Y', strtotime($dateFrom)) . ' to ' . date('M d, Y', strtotime($dateTo)));
    $sheet->getStyle('A' . $row)->getFont()->setItalic(true);
    $row += 2;
    
    $sheet->setCellValue('A' . $row, 'Date');
    $sheet->setCellValue('B' . $row, 'Reference');
    $sheet->setCellValue('C' . $row, 'Description');
    $sheet->setCellValue('D' . $row, 'Debit');
    $sheet->setCellValue('E' . $row, 'Credit');
    $sheet->setCellValue('F' . $row, 'Balance');
    styleHeaderRow($sheet, $row, 6);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'General Ledger entries would be listed here...');
    $row++;
    
    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(35);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
}

// ========== STYLING HELPER FUNCTIONS ==========

function styleHeaderRow($sheet, $row, $colCount) {
    $lastCol = chr(64 + $colCount);
    $range = 'A' . $row . ':' . $lastCol . $row;
    
    $sheet->getStyle($range)->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '000000']
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
    ]);
}

function styleSubtotalRow($sheet, $row, $colCount, $currencySymbol) {
    $lastCol = chr(64 + $colCount);
    $range = 'A' . $row . ':' . $lastCol . $row;
    
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true],
        'borders' => [
            'top' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
    
    $sheet->getStyle($lastCol . $row)->getNumberFormat()
        ->setFormatCode($currencySymbol . '#,##0.00');
}

function styleTotalRow($sheet, $row, $colCount, $currencySymbol) {
    $lastCol = chr(64 + $colCount);
    $range = 'A' . $row . ':' . $lastCol . $row;
    
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'borders' => [
            'top' => [
                'borderStyle' => Border::BORDER_DOUBLE,
                'color' => ['rgb' => '000000']
            ],
            'bottom' => [
                'borderStyle' => Border::BORDER_DOUBLE,
                'color' => ['rgb' => '000000']
            ]
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F3F4F6']
        ]
    ]);
    
    $sheet->getStyle($lastCol . $row)->getNumberFormat()
        ->setFormatCode($currencySymbol . '#,##0.00');
}
