<?php
/**
 * PDF Export Endpoint for Financial Reports
 * Generates PDF file directly without print dialog
 */

session_start();

// Initialize timezone
require_once __DIR__ . '/../../init_timezone.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
use App\Controller\AccountingController;

// Check if mPDF or Dompdf is available
$useMpdf = class_exists('Mpdf\Mpdf');
$useDompdf = !$useMpdf && class_exists('Dompdf\Dompdf');

if (!$useMpdf && !$useDompdf) {
    die('PDF library not installed. Please install mPDF or Dompdf via Composer.');
}

$accountingController = new AccountingController();

// Get branding
$branding = $_SESSION['branding'] ?? [
    'company_name' => 'Inventory System',
    'company_address' => '',
    'company_phone' => '',
    'header_image' => '',
    'footer_image' => ''
];

// Get report parameters
$reportType = $_GET['report'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');

if (!$reportType) {
    die('No report type specified');
}

// Generate report data
$reportData = [];
$reportTitle = '';
$reportSubtitle = '';

switch ($reportType) {
    case 'balance_sheet':
        $result = $accountingController->getBalanceSheet($asOfDate);
        $reportData = $result['success'] ? $result['data'] : [];
        $reportTitle = 'Balance Sheet';
        $reportSubtitle = 'As of ' . date('F j, Y', strtotime($asOfDate));
        break;
        
    case 'income_statement':
        $result = $accountingController->getIncomeStatement($startDate, $endDate);
        $reportData = $result['success'] ? $result['data'] : [];
        $reportTitle = 'Income Statement';
        $reportSubtitle = 'For the period ' . date('M j, Y', strtotime($startDate)) . ' to ' . date('M j, Y', strtotime($endDate));
        break;
        
    case 'trial_balance':
        $result = $accountingController->getTrialBalance($asOfDate);
        $reportData = $result['success'] ? $result['data'] : [];
        $reportTitle = 'Trial Balance';
        $reportSubtitle = 'As of ' . date('F j, Y', strtotime($asOfDate));
        break;
        
    default:
        die('Report type not supported for PDF export');
}

// Start building HTML content
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #000; }
        .header h1 { font-size: 18pt; margin: 0 0 5px 0; }
        .header p { font-size: 10pt; margin: 0; color: #666; }
        .biodata { margin-bottom: 20px; }
        .biodata table { width: 100%; border-collapse: collapse; border: 1px solid #ddd; }
        .biodata th { background: #f5f5f5; padding: 8px; text-align: left; font-weight: 600; border: 1px solid #ddd; width: 25%; }
        .biodata td { padding: 8px; border: 1px solid #ddd; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .report-table th { background: #f5f5f5; padding: 8px; text-align: left; font-weight: 600; border-bottom: 2px solid #000; }
        .report-table td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
        .report-table .total-row td { font-weight: 600; border-top: 2px solid #000; background: #f5f5f5; padding: 10px 8px; }
        .report-table .grand-total td { font-weight: 700; border-top: 3px double #000; background: #e8e8e8; padding: 12px 8px; }
        .text-right { text-align: right; }
        .font-mono { font-family: 'Courier New', monospace; }
        .two-column { display: table; width: 100%; }
        .column { display: table-cell; width: 48%; vertical-align: top; }
        .column + .column { padding-left: 4%; }
        .section-title { font-size: 12pt; font-weight: 600; margin: 15px 0 10px 0; padding-bottom: 5px; border-bottom: 2px solid #333; }
    </style>
</head>
<body>
    <?php if (!empty($branding['header_image']) && file_exists(__DIR__ . '/' . $branding['header_image'])): ?>
    <div style="text-align: center; margin-bottom: 15px;">
        <img src="<?php echo __DIR__ . '/' . $branding['header_image']; ?>" style="max-height: 50px;" />
    </div>
    <?php endif; ?>
    
    <div class="header">
        <h1><?php echo htmlspecialchars($reportTitle); ?></h1>
        <p><?php echo htmlspecialchars($reportSubtitle); ?></p>
        <p style="font-size: 8pt; margin-top: 5px;">Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
    </div>
    
    <div class="biodata">
        <table>
            <tr>
                <th>Company</th>
                <td><?php echo htmlspecialchars($branding['company_name'] ?: '—'); ?></td>
            </tr>
            <tr>
                <th>Prepared By</th>
                <td><?php echo htmlspecialchars($_SESSION['user_name'] ?? '—'); ?></td>
            </tr>
            <?php if (!empty($branding['company_address'])): ?>
            <tr>
                <th>Address</th>
                <td><?php echo htmlspecialchars($branding['company_address']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($branding['company_phone'])): ?>
            <tr>
                <th>Phone</th>
                <td><?php echo htmlspecialchars($branding['company_phone']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <?php if ($reportType === 'balance_sheet' && !empty($reportData)): ?>
        <div class="two-column">
            <div class="column">
                <div class="section-title">Assets</div>
                <table style="width: 100%; font-size: 9pt;">
                    <?php foreach ($reportData['assets']['accounts'] ?? [] as $account): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                        <td class="text-right font-mono"><?php echo number_format($account['balance'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: 600; border-top: 2px solid #000; background: #f5f5f5;">
                        <td style="padding-top: 8px;">Total Assets</td>
                        <td class="text-right font-mono" style="padding-top: 8px;"><?php echo number_format($reportData['assets']['total'], 2); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="column">
                <div class="section-title">Liabilities</div>
                <table style="width: 100%; font-size: 9pt; margin-bottom: 15px;">
                    <?php foreach ($reportData['liabilities']['accounts'] ?? [] as $account): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                        <td class="text-right font-mono"><?php echo number_format($account['balance'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: 600; border-top: 2px solid #000; background: #f5f5f5;">
                        <td style="padding-top: 8px;">Total Liabilities</td>
                        <td class="text-right font-mono" style="padding-top: 8px;"><?php echo number_format($reportData['liabilities']['total'], 2); ?></td>
                    </tr>
                </table>
                
                <div class="section-title">Equity</div>
                <table style="width: 100%; font-size: 9pt;">
                    <?php foreach ($reportData['equity']['accounts'] ?? [] as $account): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                        <td class="text-right font-mono"><?php echo number_format($account['balance'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: 600; border-top: 2px solid #000; background: #f5f5f5;">
                        <td style="padding-top: 8px;">Total Equity</td>
                        <td class="text-right font-mono" style="padding-top: 8px;"><?php echo number_format($reportData['equity']['total'], 2); ?></td>
                    </tr>
                    <tr style="font-weight: 700; border-top: 3px double #000; background: #e8e8e8;">
                        <td style="padding-top: 10px;">Total Liabilities & Equity</td>
                        <td class="text-right font-mono" style="padding-top: 10px;"><?php echo number_format($reportData['total_liabilities_equity'], 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    
    <?php elseif ($reportType === 'income_statement' && !empty($reportData)): ?>
        <table class="report-table">
            <tr>
                <th>Income</th>
                <th class="text-right">Amount</th>
            </tr>
            <?php foreach ($reportData['income']['accounts'] ?? [] as $account): ?>
            <tr>
                <td style="padding-left: 20px;"><?php echo htmlspecialchars($account['account_name']); ?></td>
                <td class="text-right font-mono"><?php echo number_format($account['period_activity'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td style="padding-left: 20px;">Total Income</td>
                <td class="text-right font-mono"><?php echo number_format($reportData['income']['total'], 2); ?></td>
            </tr>
            
            <tr>
                <th style="padding-top: 15px;">Expenses</th>
                <th class="text-right"></th>
            </tr>
            <?php foreach ($reportData['expenses']['accounts'] ?? [] as $account): ?>
            <tr>
                <td style="padding-left: 20px;"><?php echo htmlspecialchars($account['account_name']); ?></td>
                <td class="text-right font-mono"><?php echo number_format($account['period_activity'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td style="padding-left: 20px;">Total Expenses</td>
                <td class="text-right font-mono"><?php echo number_format($reportData['expenses']['total'], 2); ?></td>
            </tr>
            
            <tr class="grand-total">
                <td>Net Income</td>
                <td class="text-right font-mono" style="color: <?php echo $reportData['net_income'] >= 0 ? '#22c55e' : '#ef4444'; ?>;">
                    <?php echo number_format($reportData['net_income'], 2); ?>
                </td>
            </tr>
        </table>
    
    <?php elseif ($reportType === 'trial_balance' && !empty($reportData)): ?>
        <table class="report-table">
            <tr>
                <th>Account Code</th>
                <th>Account Name</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
            </tr>
            <?php foreach ($reportData['accounts'] ?? [] as $account): ?>
            <tr>
                <td><?php echo htmlspecialchars($account['account_code']); ?></td>
                <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                <td class="text-right font-mono"><?php echo number_format($account['debit'], 2); ?></td>
                <td class="text-right font-mono"><?php echo number_format($account['credit'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="grand-total">
                <td colspan="2">Totals</td>
                <td class="text-right font-mono"><?php echo number_format($reportData['total_debit'], 2); ?></td>
                <td class="text-right font-mono"><?php echo number_format($reportData['total_credit'], 2); ?></td>
            </tr>
        </table>
    <?php endif; ?>
    
    <?php if (!empty($branding['footer_image']) && file_exists(__DIR__ . '/' . $branding['footer_image'])): ?>
    <div style="text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd;">
        <img src="<?php echo __DIR__ . '/' . $branding['footer_image']; ?>" style="max-height: 40px;" />
    </div>
    <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();

// Generate PDF
$filename = preg_replace('/[^a-z0-9]/i', '_', $reportTitle) . '_' . date('Y-m-d') . '.pdf';

try {
    if ($useMpdf) {
        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'margin_right' => 15
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename, 'D'); // D = Download
    } elseif ($useDompdf) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => 1]);
    }
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}

