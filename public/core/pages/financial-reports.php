<?php
/**
 * Financial Reports Page
 * Comprehensive financial statements and reports
 * Integrated design from LedgerSMB and QuickBooks
 */

session_start();

// Initialize timezone
require_once __DIR__ . '/../utils/init_timezone.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
use App\Controller\AccountingController;

$accountingController = new AccountingController();

// Branding state (company info and header/footer images)
$branding = $_SESSION['branding'] ?? [
    'company_name' => 'Inventory System',
    'company_address' => '',
    'company_phone' => '',
    'header_image' => '',
    'footer_image' => ''
];

// Handle branding save (supports common image formats: png, jpg, jpeg, gif, svg, webp, bmp)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['branding_action']) && $_POST['branding_action'] === 'save') {
    $branding['company_name'] = trim($_POST['company_name'] ?? $branding['company_name']);
    $branding['company_address'] = trim($_POST['company_address'] ?? $branding['company_address']);
    $branding['company_phone'] = trim($_POST['company_phone'] ?? $branding['company_phone']);

    $uploadDir = __DIR__ . '/../../uploads/branding';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    $allowedExt = ['png','jpg','jpeg','gif','svg','webp','bmp'];
    $maxBytes = 5 * 1024 * 1024; // 5MB
    $userId = $_SESSION['user_id'] ?? 'user';

    foreach (['header_image' => 'header', 'footer_image' => 'footer'] as $field => $prefix) {
        if (!empty($_FILES[$field]['name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
            if ($_FILES[$field]['size'] > $maxBytes) {
                // skip oversized files silently
                continue;
            }

            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $map = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/gif' => 'gif',
                'image/svg+xml' => 'svg',
                'image/webp' => 'webp',
                'image/bmp' => 'bmp',
            ];
            $mimeExt = $ext;
            if (function_exists('finfo_open')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES[$field]['tmp_name']);
                $mimeExt = $map[$mime] ?? $ext;
            }

            if (!in_array($mimeExt, $allowedExt, true)) {
                continue;
            }

            $filename = $prefix . '_' . $userId . '_' . time() . '.' . $mimeExt;
            $dest = $uploadDir . '/' . $filename;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
                $branding[$field] = 'uploads/branding/' . $filename; // web path
            }
        }
    }

    $_SESSION['branding'] = $branding;
    // Prevent form resubmission
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
    exit;
}

// Handle branding reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['branding_action']) && $_POST['branding_action'] === 'reset') {
    // Delete uploaded images
    $uploadDir = __DIR__ . '/../../uploads/branding';
    if (!empty($branding['header_image']) && file_exists(__DIR__ . '/../../' . $branding['header_image'])) {
        @unlink(__DIR__ . '/../../' . $branding['header_image']);
    }
    if (!empty($branding['footer_image']) && file_exists(__DIR__ . '/../../' . $branding['footer_image'])) {
        @unlink(__DIR__ . '/../../' . $branding['footer_image']);
    }
    
    // Reset to defaults
    $_SESSION['branding'] = [
        'company_name' => 'Inventory System',
        'company_address' => '',
        'company_phone' => '',
        'header_image' => '',
        'footer_image' => ''
    ];
    
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
    exit;
}

// Only generate report when explicitly requested
$generateReport = isset($_GET['generate']) && $_GET['generate'] === 'true';

// Get report type and parameters
$reportType = $_GET['report'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');
$compareWith = $_GET['compare_with'] ?? 'none'; // none, previous_period, previous_year
$format = $_GET['format'] ?? 'detailed'; // detailed, summary

// Initialize variables
$reportData = [];
$reportTitle = '';
$reportSubtitle = '';

// Define available report types with categories
$reportCategories = [
    'Financial Statements' => [
        'balance_sheet' => 'Balance Sheet',
        'income_statement' => 'Income Statement (P&L)',
        'cash_flow' => 'Statement of Cash Flows',
        'statement_of_equity' => 'Statement of Changes in Equity'
    ],
    'Accounting Reports' => [
        'trial_balance' => 'Trial Balance',
        'general_ledger' => 'General Ledger',
        'chart_of_accounts' => 'Chart of Accounts',
        'journal_entries' => 'Journal Entries'
    ],
    'Receivables & Payables' => [
        'ar_aging' => 'Accounts Receivable Aging',
        'ap_aging' => 'Accounts Payable Aging',
        'customer_balances' => 'Customer Balances',
        'vendor_balances' => 'Vendor Balances'
    ],
    'Analysis & Planning' => [
        'budget_actual' => 'Budget vs Actual',
        'comparative_bs' => 'Comparative Balance Sheet',
        'comparative_is' => 'Comparative Income Statement',
        'financial_ratios' => 'Financial Ratios'
    ]
];

// Generate report only if requested
if ($generateReport && $reportType) {
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
            
        // Add more report types as they are implemented
        default:
            $reportTitle = 'Report Not Yet Implemented';
            $reportSubtitle = 'This report type is coming soon';
    }
}

// Set page variables
$pageTitle = 'Financial Reports';

// Start output buffering for content
ob_start();
?>

<!-- Page Header -->
<div class="content-header print:hidden">
  <div>
    <nav class="breadcrumb">
      <a href=\"/dashboard" class="breadcrumb-link">Dashboard</a>
      <span class="breadcrumb-separator">/</span>
      <a href="#" class="breadcrumb-link">Accounting</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current">Financial Reports</span>
    </nav>
    <h1 class="content-title">Financial Reports</h1>
    <p class="text-sm" style="color: var(--text-secondary); margin-top: 0.25rem;">Generate and view comprehensive financial statements</p>
  </div>
  <?php if ($generateReport && $reportType): ?>
  <div class="content-actions" style="display: flex; gap: 0.5rem;">
    <button class="btn btn-ghost btn-sm" onclick="exportReport('pdf')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <path d="M12 18v-6M9 15l3 3 3-3"/>
      </svg>
      Export PDF
    </button>
    <button class="btn btn-ghost btn-sm" onclick="exportReport('xlsx')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
        <polyline points="10 9 9 9 8 9"/>
      </svg>
      Export Excel
    </button>
    <button class="btn btn-secondary btn-sm" onclick="window.print()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="6 9 6 2 18 2 18 9"/>
        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
        <rect x="6" y="14" width="12" height="8"/>
      </svg>
      Print
    </button>
  </div>
  <?php endif; ?>
</div>

<!-- Report Configuration -->
<div class="card print:hidden" style="margin-bottom: 1.5rem;">
  <div class="card-content" style="padding: 1.5rem;">
    <h3 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-primary);">Report Configuration</h3>
    
    <div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 1rem;">
      <!-- Report Type Dropdown -->
      <div style="grid-column: span 4;">
        <label class="form-label" style="display: block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">Report Type</label>
        <select id="report-type" class="form-select" style="width: 100%;">
          <option value="" disabled <?php echo !$reportType ? 'selected' : ''; ?>>Select a report...</option>
          <?php foreach ($reportCategories as $category => $reports): ?>
            <optgroup label="<?php echo htmlspecialchars($category); ?>">
              <?php foreach ($reports as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $reportType === $key ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($label); ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Date Range Fields -->
      <div style="grid-column: span 2;" id="as-of-date-field" class="date-field">
        <label class="form-label" style="display: block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">As of Date</label>
        <input type="date" id="as-of-date" class="form-input" value="<?php echo $asOfDate; ?>" style="width: 100%;">
      </div>

      <div style="grid-column: span 2;" id="start-date-field" class="date-field" style="display: none;">
        <label class="form-label" style="display: block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">Start Date</label>
        <input type="date" id="start-date" class="form-input" value="<?php echo $startDate; ?>" style="width: 100%;">
      </div>

      <div style="grid-column: span 2;" id="end-date-field" class="date-field" style="display: none;">
        <label class="form-label" style="display: block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">End Date</label>
        <input type="date" id="end-date" class="form-input" value="<?php echo $endDate; ?>" style="width: 100%;">
      </div>

      <!-- Comparison Option -->
      <div style="grid-column: span 2;">
        <label class="form-label" style="display: block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">Compare With</label>
        <select id="compare-with" class="form-select" style="width: 100%;">
          <option value="none" <?php echo $compareWith === 'none' ? 'selected' : ''; ?>>None</option>
          <option value="previous_period" <?php echo $compareWith === 'previous_period' ? 'selected' : ''; ?>>Previous Period</option>
          <option value="previous_year" <?php echo $compareWith === 'previous_year' ? 'selected' : ''; ?>>Previous Year</option>
        </select>
      </div>

      <!-- Format Option -->
      <div style="grid-column: span 2;">
        <label class="form-label" style="display: block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">Format</label>
        <select id="format" class="form-select" style="width: 100%;">
          <option value="detailed" <?php echo $format === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
          <option value="summary" <?php echo $format === 'summary' ? 'selected' : ''; ?>>Summary</option>
        </select>
      </div>
    </div>

    <!-- Generate Button -->
    <div style="margin-top: 1.25rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
      <button class="btn btn-ghost" onclick="resetForm()" type="button">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
          <path d="M3 3v5h5"/>
        </svg>
        Reset
      </button>
      <button class="btn btn-primary" onclick="generateReport()" type="button">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
        Generate Report
      </button>
    </div>
  </div>
</div>

<!-- Branding (Header/Footer Images and Company Info) -->
<div class="card print:hidden" style="margin-bottom: 2.5rem;">
  <div class="card-content" style="padding: 1.5rem;">
    <h3 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-primary);">Branding</h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="branding_action" value="save" />
      
      <!-- First Row: Company Info -->
      <div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 1rem; margin-bottom: 1rem;">
        <div style="grid-column: span 4;">
          <label class="form-label" style="display:block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">Company Name</label>
          <input type="text" name="company_name" class="form-input" value="<?php echo htmlspecialchars($branding['company_name']); ?>" style="width: 100%;" />
        </div>
        <div style="grid-column: span 4;">
          <label class="form-label" style="display:block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">Company Address</label>
          <input type="text" name="company_address" class="form-input" value="<?php echo htmlspecialchars($branding['company_address']); ?>" style="width: 100%;" />
        </div>
        <div style="grid-column: span 4;">
          <label class="form-label" style="display:block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">Company Phone</label>
          <input type="text" name="company_phone" class="form-input" value="<?php echo htmlspecialchars($branding['company_phone']); ?>" style="width: 100%;" />
        </div>
      </div>

      <!-- Second Row: Images -->
      <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem;">
        <div>
          <label class="form-label" style="display:block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">Header Image</label>
          <input type="file" name="header_image" accept=".png,.jpg,.jpeg,.gif,.svg,.webp,.bmp,image/*" class="form-input" style="width: 100%;" />
          <?php if (!empty($branding['header_image'])): ?>
            <div style="margin-top: 0.5rem; display:flex; align-items:center; gap:0.5rem;">
              <img src="<?php echo htmlspecialchars($branding['header_image']); ?>" alt="Header" style="max-height:40px; border:1px solid var(--border-color); border-radius: 4px; padding:2px; background:#fff;" />
              <span style="font-size: 0.75rem; color: var(--text-secondary);">Preview</span>
            </div>
          <?php endif; ?>
        </div>
        <div>
          <label class="form-label" style="display:block; margin-bottom: 0.375rem; font-size: 0.875rem; font-weight: 500;">Footer Image</label>
          <input type="file" name="footer_image" accept=".png,.jpg,.jpeg,.gif,.svg,.webp,.bmp,image/*" class="form-input" style="width: 100%;" />
          <?php if (!empty($branding['footer_image'])): ?>
            <div style="margin-top: 0.5rem; display:flex; align-items:center; gap:0.5rem;">
              <img src="<?php echo htmlspecialchars($branding['footer_image']); ?>" alt="Footer" style="max-height:40px; border:1px solid var(--border-color); border-radius: 4px; padding:2px; background:#fff;" />
              <span style="font-size: 0.75rem; color: var(--text-secondary);">Preview</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Buttons Row -->
      <div style="display: flex; justify-content: flex-end; align-items: center; gap: 0.5rem;">
        <?php if (!empty($branding['header_image']) || !empty($branding['footer_image']) || $branding['company_name'] !== 'Inventory System' || !empty($branding['company_address']) || !empty($branding['company_phone'])): ?>
        <button type="button" onclick="if(confirm('Are you sure you want to reset all branding? This will remove all images and reset company information.')) { document.getElementById('reset-branding-form').submit(); }" class="btn btn-ghost">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
            <path d="M3 3v5h5"/>
          </svg>
          Reset
        </button>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
            <polyline points="17 21 17 13 7 13 7 21"/>
            <polyline points="7 3 7 8 15 8"/>
          </svg>
          Save Branding
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden Reset Form -->
<form id="reset-branding-form" method="post" style="display:none;">
  <input type="hidden" name="branding_action" value="reset" />
</form>

<!-- Report Display -->
<?php if (!$generateReport || !$reportType): ?>
  <!-- Welcome State -->
  <div class="card" style="text-align: center; padding: 4rem 2rem; margin-top: 1rem;">
    <div style="max-width: 32rem; margin: 0 auto;">
      <div style="width: 4rem; height: 4rem; margin: 0 auto 1.5rem; background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
          <line x1="10" y1="9" x2="8" y2="9"/>
        </svg>
      </div>
      <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">Select a Report to Get Started</h3>
      <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.875rem; line-height: 1.5;">Choose a report type from the dropdown above, configure the date range and options, then click "Generate Report" to view your financial data.</p>
      
      <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 2rem; text-align: left;">
        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.375rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2"/>
              <line x1="9" y1="3" x2="9" y2="21"/>
            </svg>
            Financial Statements
          </h4>
          <p style="font-size: 0.75rem; color: var(--text-secondary);">Balance Sheet, Income Statement, Cash Flow, and Equity statements</p>
        </div>
        
        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.375rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2">
              <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
            Receivables & Payables
          </h4>
          <p style="font-size: 0.75rem; color: var(--text-secondary);">AR/AP Aging, Customer and Vendor balances</p>
        </div>
        
        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.375rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-warning)" stroke-width="2">
              <path d="M3 3v18h18"/>
              <path d="m19 9-5 5-4-4-3 3"/>
            </svg>
            Analysis & Planning
          </h4>
          <p style="font-size: 0.75rem; color: var(--text-secondary);">Budget vs Actual, Comparative statements, Financial ratios</p>
        </div>
        
        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.375rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-info)" stroke-width="2">
              <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
              <rect x="8" y="2" width="8" height="4" rx="1"/>
            </svg>
            Accounting Reports
          </h4>
          <p style="font-size: 0.75rem; color: var(--text-secondary);">Trial Balance, General Ledger, Chart of Accounts, Journal entries</p>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <!-- Report Display Card -->
  <div class="card report-card" style="margin-top: 1rem;">
    <?php if (!empty($branding['header_image'])): ?>
    <div class="print-brand-header print:block" style="display:none; text-align:center;">
      <img src="<?php echo htmlspecialchars($branding['header_image']); ?>" alt="Header" />
    </div>
    <?php endif; ?>
    <?php if (empty($branding['header_image'])): ?>
    <div class="custom-print-header print:block" style="display: none;">
      <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.5rem; margin-bottom: 0.75rem; border-bottom: 1px solid #ddd; font-size: 0.75rem; color: var(--text-muted);">
        <span><?php echo date('n/j/y, g:i A'); ?></span>
        <span>Financial Reports - Inventory System</span>
      </div>
    </div>
    <?php endif; ?>
    <!-- Report Header for Print -->
    <div class="report-header" style="border-bottom: 2px solid var(--border-color); padding: 2rem 2rem 1.5rem 2rem; margin-bottom: 2rem;">
      <div style="text-align: center;">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; color: var(--text-primary);"><?php echo htmlspecialchars($reportTitle); ?></h2>
        <?php if ($reportSubtitle): ?>
          <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($reportSubtitle); ?></p>
        <?php endif; ?>
        <p class="print:block" style="display: none; color: var(--text-muted); font-size: 0.75rem; margin-top: 0.5rem;">
          Generated on <?php echo date('F j, Y \a\t g:i A'); ?>
        </p>
      </div>
    </div>
    
    <div class="card-content report-content" style="padding: 0 2rem 2rem 2rem;">
    <div class="biodata" style="margin-bottom: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
      <table class="bio-table" style="width:100%; border-collapse: collapse;">
        <tbody>
          <tr style="border-bottom: 1px solid var(--border-color);">
            <th style="text-align:left; font-weight:600; padding:10px 16px; width: 25%; background: var(--bg-secondary); color: var(--text-primary);">Company</th>
            <td style="padding:10px 16px; background: white;"><?php echo htmlspecialchars($branding['company_name'] ?: '—'); ?></td>
          </tr>
          <tr style="border-bottom: 1px solid var(--border-color);">
            <th style="text-align:left; font-weight:600; padding:10px 16px; background: var(--bg-secondary); color: var(--text-primary);">Prepared By</th>
            <td style="padding:10px 16px; background: white;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? '—'); ?></td>
          </tr>
          <?php if (!empty($branding['company_address'])): ?>
          <tr style="border-bottom: 1px solid var(--border-color);">
            <th style="text-align:left; font-weight:600; padding:10px 16px; background: var(--bg-secondary); color: var(--text-primary);">Address</th>
            <td style="padding:10px 16px; background: white;"><?php echo htmlspecialchars($branding['company_address']); ?></td>
          </tr>
          <?php endif; ?>
          <?php if (!empty($branding['company_phone'])): ?>
          <tr style="border-bottom: 1px solid var(--border-color);">
            <th style="text-align:left; font-weight:600; padding:10px 16px; background: var(--bg-secondary); color: var(--text-primary);">Phone</th>
            <td style="padding:10px 16px; background: white;"><?php echo htmlspecialchars($branding['company_phone']); ?></td>
          </tr>
          <?php endif; ?>
          <tr style="border-bottom: 1px solid var(--border-color);">
            <th style="text-align:left; font-weight:600; padding:10px 16px; background: var(--bg-secondary); color: var(--text-primary);">Report</th>
            <td style="padding:10px 16px; background: white;"><?php echo htmlspecialchars($reportTitle ?: '—'); ?></td>
          </tr>
          <tr style="border-bottom: 1px solid var(--border-color);">
            <th style="text-align:left; font-weight:600; padding:10px 16px; background: var(--bg-secondary); color: var(--text-primary);">Period</th>
            <td style="padding:10px 16px; background: white;">
              <?php if ($reportType === 'balance_sheet' || $reportType === 'trial_balance'): ?>
                As of <?php echo date('F j, Y', strtotime($asOfDate)); ?>
              <?php else: ?>
                <?php echo date('F j, Y', strtotime($startDate)); ?> — <?php echo date('F j, Y', strtotime($endDate)); ?>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th style="text-align:left; font-weight:600; padding:10px 16px; background: var(--bg-secondary); color: var(--text-primary);">Generated</th>
            <td style="padding:10px 16px; background: white;"><?php echo date('F j, Y \\a\\t g:i A'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php if ($reportType === 'balance_sheet' && !empty($reportData)): ?>
      <!-- Balance Sheet -->
      <div class="grid grid-cols-2 gap-8" style="margin-top: 1.5rem;">
        <!-- Assets -->
        <div>
          <h3 style="font-weight: 600; font-size: 1.125rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-color); color: var(--text-primary);">Assets</h3>
          <table style="width: 100%; font-size: 0.875rem;">
            <?php foreach ($reportData['assets']['accounts'] ?? [] as $account): ?>
            <tr style="border-bottom: 1px solid var(--border-color);">
              <td style="padding: 0.75rem 0.5rem;"><?php echo htmlspecialchars($account['account_name']); ?></td>
              <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($account['balance'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="font-weight: 600; border-top: 2px solid var(--border-color); background: var(--bg-secondary);">
              <td style="padding: 1rem 0.5rem;">Total Assets</td>
              <td style="padding: 1rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($reportData['assets']['total'], 2); ?></td>
            </tr>
          </table>
        </div>

        <!-- Liabilities & Equity -->
        <div>
          <h3 style="font-weight: 600; font-size: 1.125rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-color); color: var(--text-primary);">Liabilities</h3>
          <table style="width: 100%; font-size: 0.875rem; margin-bottom: 2rem;">
            <?php foreach ($reportData['liabilities']['accounts'] ?? [] as $account): ?>
            <tr style="border-bottom: 1px solid var(--border-color);">
              <td style="padding: 0.75rem 0.5rem;"><?php echo htmlspecialchars($account['account_name']); ?></td>
              <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($account['balance'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="font-weight: 600; border-top: 2px solid var(--border-color); background: var(--bg-secondary);">
              <td style="padding: 1rem 0.5rem;">Total Liabilities</td>
              <td style="padding: 1rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($reportData['liabilities']['total'], 2); ?></td>
            </tr>
          </table>

          <h3 style="font-weight: 600; font-size: 1.125rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-color); color: var(--text-primary);">Equity</h3>
          <table style="width: 100%; font-size: 0.875rem;">
            <?php foreach ($reportData['equity']['accounts'] ?? [] as $account): ?>
            <tr style="border-bottom: 1px solid var(--border-color);">
              <td style="padding: 0.75rem 0.5rem;"><?php echo htmlspecialchars($account['account_name']); ?></td>
              <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($account['balance'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="font-weight: 600; border-top: 2px solid var(--border-color); background: var(--bg-secondary);">
              <td style="padding: 1rem 0.5rem;">Total Equity</td>
              <td style="padding: 1rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($reportData['equity']['total'], 2); ?></td>
            </tr>
            <tr style="font-weight: 700; border-top: 3px double var(--border-color); background: var(--bg-secondary);">
              <td style="padding: 1rem 0.5rem;">Total Liabilities & Equity</td>
              <td style="padding: 1rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($reportData['total_liabilities_equity'], 2); ?></td>
            </tr>
          </table>
        </div>
      </div>


    <?php elseif ($reportType === 'income_statement' && !empty($reportData)): ?>
      <!-- Income Statement -->
      <table style="width: 100%; margin-top: 1.5rem; font-size: 0.875rem;">
        <thead>
          <tr style="border-bottom: 2px solid var(--border-color);">
            <th style="text-align: left; font-weight: 600; padding: 0.75rem 0.5rem; color: var(--text-primary);">Income</th>
            <th style="text-align: right; padding: 0.75rem 0.5rem;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reportData['income']['accounts'] ?? [] as $account): ?>
          <tr style="border-bottom: 1px solid var(--border-color);">
            <td style="padding: 0.75rem 0.5rem 0.75rem 1.5rem;"><?php echo htmlspecialchars($account['account_name']); ?></td>
            <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($account['period_activity'], 2); ?></td>
          </tr>
          <?php endforeach; ?>
          <tr style="font-weight: 600; border-top: 2px solid var(--border-color); background: var(--bg-secondary);">
            <td style="padding: 1rem 0.5rem 1rem 1.5rem;">Total Income</td>
            <td style="padding: 1rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($reportData['income']['total'], 2); ?></td>
          </tr>

          <tr style="border-top: 2px solid var(--border-color); border-bottom: 2px solid var(--border-color);">
            <th style="text-align: left; font-weight: 600; padding: 1.5rem 0.5rem 0.75rem 0.5rem; color: var(--text-primary);">Expenses</th>
            <th style="text-align: right; padding: 1.5rem 0.5rem 0.75rem 0.5rem;"></th>
          </tr>
          <?php foreach ($reportData['expenses']['accounts'] ?? [] as $account): ?>
          <tr style="border-bottom: 1px solid var(--border-color);">
            <td style="padding: 0.75rem 0.5rem 0.75rem 1.5rem;"><?php echo htmlspecialchars($account['account_name']); ?></td>
            <td style="padding: 0.75rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($account['period_activity'], 2); ?></td>
          </tr>
          <?php endforeach; ?>
          <tr style="font-weight: 600; border-top: 2px solid var(--border-color); background: var(--bg-secondary);">
            <td style="padding: 1rem 0.5rem 1rem 1.5rem;">Total Expenses</td>
            <td style="padding: 1rem 0.5rem; text-align: right; font-family: monospace;"><?php echo number_format($reportData['expenses']['total'], 2); ?></td>
          </tr>

          <tr style="font-weight: 700; font-size: 1rem; border-top: 3px double var(--border-color); background: var(--bg-secondary);">
            <td style="padding: 1.25rem 0.5rem;">Net Income</td>
            <td style="padding: 1.25rem 0.5rem; text-align: right; font-family: monospace; color: <?php echo $reportData['net_income'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)'; ?>;">
              <?php echo number_format($reportData['net_income'], 2); ?>
            </td>
          </tr>
        </tbody>
      </table>

    <?php elseif ($reportType === 'trial_balance' && !empty($reportData)): ?>
      <!-- Trial Balance -->
      <table style="width: 100%; margin-top: 1.5rem; font-size: 0.875rem; border: 1px solid var(--border-color);">
        <thead>
          <tr style="background: var(--bg-secondary); border-bottom: 2px solid var(--border-color);">
            <th style="text-align: left; font-weight: 600; padding: 0.75rem; color: var(--text-primary);">Account Code</th>
            <th style="text-align: left; font-weight: 600; padding: 0.75rem; color: var(--text-primary);">Account Name</th>
            <th style="text-align: right; font-weight: 600; padding: 0.75rem; color: var(--text-primary);">Debit</th>
            <th style="text-align: right; font-weight: 600; padding: 0.75rem; color: var(--text-primary);">Credit</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reportData['accounts'] ?? [] as $account): ?>
          <tr style="border-bottom: 1px solid var(--border-color);">
            <td style="padding: 0.75rem;"><?php echo htmlspecialchars($account['account_code']); ?></td>
            <td style="padding: 0.75rem;"><?php echo htmlspecialchars($account['account_name']); ?></td>
            <td style="padding: 0.75rem; text-align: right; font-family: monospace;"><?php echo number_format($account['debit'], 2); ?></td>
            <td style="padding: 0.75rem; text-align: right; font-family: monospace;"><?php echo number_format($account['credit'], 2); ?></td>
          </tr>
          <?php endforeach; ?>
          <tr style="font-weight: 700; border-top: 3px double var(--border-color); background: var(--bg-secondary);">
            <td colspan="2" style="padding: 1rem 0.75rem;">Totals</td>
            <td style="padding: 1rem 0.75rem; text-align: right; font-family: monospace;"><?php echo number_format($reportData['total_debit'], 2); ?></td>
            <td style="padding: 1rem 0.75rem; text-align: right; font-family: monospace;"><?php echo number_format($reportData['total_credit'], 2); ?></td>
          </tr>
        </tbody>
      </table>


    <?php else: ?>
      <!-- No Data Available State -->
      <div style="padding: 3rem 2rem; text-align: center;">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="margin: 0 auto 1.5rem; opacity: 0.5;">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 5C9 6.10457 9.89543 7 11 7H13C14.1046 7 15 6.10457 15 5M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5"/>
        </svg>
        <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">No Data Available</h3>
        <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1.5rem;">There is no data to display for this report. Please post some journal entries or transactions to see results.</p>
        <a href=\"/journal-entry" class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14"/>
          </svg>
          Create Journal Entry
        </a>
      </div>
    <?php endif; ?>
    </div>
    
    <?php if (!empty($branding['footer_image'])): ?>
    <div class="print-brand-footer print:block" style="display:none; text-align:center;">
      <img src="<?php echo htmlspecialchars($branding['footer_image']); ?>" alt="Footer" />
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<script>
// DOM Elements
const reportTypeSelect = document.getElementById('report-type');
const asOfDateField = document.getElementById('as-of-date-field');
const startDateField = document.getElementById('start-date-field');
const endDateField = document.getElementById('end-date-field');

// Reports that use date ranges
const dateRangeReports = [
  'income_statement', 'cash_flow', 'comparative_is', 
  'budget_actual', 'journal_entries', 'general_ledger'
];

// Reports that use as-of date
const asOfDateReports = [
  'balance_sheet', 'trial_balance', 'comparative_bs',
  'statement_of_equity', 'chart_of_accounts', 'ar_aging',
  'ap_aging', 'customer_balances', 'vendor_balances', 'financial_ratios'
];

// Update date fields based on report type
function updateDateFields() {
  const selectedReport = reportTypeSelect.value;
  
  if (dateRangeReports.includes(selectedReport)) {
    asOfDateField.style.display = 'none';
    startDateField.style.display = 'block';
    endDateField.style.display = 'block';
  } else if (asOfDateReports.includes(selectedReport)) {
    asOfDateField.style.display = 'block';
    startDateField.style.display = 'none';
    endDateField.style.display = 'none';
  } else {
    // Default to as-of date for new reports
    asOfDateField.style.display = 'block';
    startDateField.style.display = 'none';
    endDateField.style.display = 'none';
  }
}

// Listen for report type changes
reportTypeSelect.addEventListener('change', updateDateFields);

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateDateFields);

// Generate report function
function generateReport() {
  const report = reportTypeSelect.value;
  
  if (!report) {
    alert('Please select a report type');
    return;
  }
  
  const url = new URL(window.location.href);
  url.searchParams.set('report', report);
  url.searchParams.set('generate', 'true');
  
  // Add date parameters based on report type
  if (dateRangeReports.includes(report)) {
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    
    if (!startDate || !endDate) {
      alert('Please select both start and end dates');
      return;
    }
    
    url.searchParams.set('start_date', startDate);
    url.searchParams.set('end_date', endDate);
    url.searchParams.delete('as_of_date');
  } else {
    const asOfDate = document.getElementById('as-of-date').value;
    
    if (!asOfDate) {
      alert('Please select an as-of date');
      return;
    }
    
    url.searchParams.set('as_of_date', asOfDate);
    url.searchParams.delete('start_date');
    url.searchParams.delete('end_date');
  }
  
  // Add comparison and format options
  url.searchParams.set('compare_with', document.getElementById('compare-with').value);
  url.searchParams.set('format', document.getElementById('format').value);
  
  window.location.href = url.toString();
}

// Reset form
function resetForm() {
  const url = new URL(window.location.origin + window.location.pathname);
  window.location.href = url.toString();
}

// Export report functions (XLSX - Modern format with proper UTF-8 support)
function exportReport(format) {
  if (format === 'pdf') {
    // Generate PDF by redirecting to export endpoint
    const params = new URLSearchParams(window.location.search);
    const exportUrl = 'export-report-pdf.php?' + params.toString();
    
    // Open in new window to trigger download
    window.open(exportUrl, '_blank');
    
  } else if (format === 'xlsx' || format === 'excel') {
    // Export to XLSX format (modern, proper encoding)
    exportToXLSX();
  }
}

// XLSX Export Function (replaces CSV)
function exportToXLSX() {
  // Show loading toast
  if (typeof Toast !== 'undefined') {
    Toast.info('Preparing export...');
  }
  
  // Build URL with current parameters
  const params = new URLSearchParams(window.location.search);
  window.location.href = 'api/export-financial-report.php?' + params.toString();
  
  // Show success after a delay (file download will start)
  setTimeout(() => {
    if (typeof Toast !== 'undefined') {
      Toast.success('Financial report exported successfully to XLSX!');
    }
  }, 1000);
}

const __originalTitle = document.title;
window.addEventListener('beforeprint', function () {
  document.title = '';
});
window.addEventListener('afterprint', function () {
  document.title = __originalTitle;
});

// Print styles are handled via CSS
</script>

<style>
/* Print Styles for Financial Reports */
@media print {
  /* Hide non-printable elements */
  .print\:hidden,
  .content-header,
  .breadcrumb,
  .btn,
  button,
  .card:not(.report-card) {
    display: none !important;
  }
  
  /* Show print-specific elements */
  .print\:block {
    display: block !important;
  }
  
  /* Branded header/footer images - compact print layout */
  .print-brand-header {
    display: block !important;
    text-align: center;
    padding: 0.3cm 0 0.3cm 0;
    margin: 0 0 0.5cm 0;
    border-bottom: 1px solid #e5e5e5;
    page-break-after: avoid;
  }
  
  .print-brand-footer {
    display: block !important;
    text-align: center;
    padding: 0.5cm 0 0.3cm 0;
    margin: 1cm 0 0 0;
    border-top: 1px solid #e5e5e5;
    page-break-before: avoid;
  }
  
  .print-brand-header img {
    max-height: 50px;
    width: auto;
    max-width: 90%;
    display: inline-block;
  }
  
  .print-brand-footer img {
    max-height: 40px;
    width: auto;
    max-width: 90%;
    display: inline-block;
  }
  
  /* Report content with proper spacing */
  .report-content {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    page-break-inside: auto;
  }
  
  /* Biodata table print styling */
  .biodata {
    page-break-inside: avoid;
    margin-bottom: 1.5rem !important;
  }
  
  .bio-table th {
    background: #f5f5f5 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  
  .bio-table td {
    background: white !important;
  }
  
  /* Page setup with balanced margins */
  @page {
    size: A4;
    margin: 1.5cm 2cm;
  }
  
  body {
    background: white !important;
    color: black !important;
    font-size: 10pt;
    line-height: 1.4;
  }
  
  /* Report card styling */
  .report-card {
    box-shadow: none !important;
    border: none !important;
    background: white !important;
    padding: 0 !important;
    margin: 0 !important;
    position: relative !important;
  }
  
  .report-header {
    border-bottom: 2px solid #000 !important;
    padding: 0.5rem 1rem 0.75rem 1rem !important;
    margin-bottom: 1rem !important;
    margin-top: 0 !important;
  }
  
  .custom-print-header {
    display: block !important;
    margin-bottom: 1rem;
  }
  
  .report-header h2 {
    font-size: 18pt !important;
    font-weight: bold !important;
    margin-bottom: 0.25rem !important;
  }
  
  .report-header p {
    font-size: 10pt !important;
  }
  
  /* Table styling */
  table {
    border-collapse: collapse;
    width: 100%;
    page-break-inside: avoid;
  }
  
  table th {
    background: #f5f5f5 !important;
    border-bottom: 2px solid #000 !important;
    padding: 6px 8px !important;
    font-weight: bold !important;
    text-align: left !important;
    font-size: 9pt !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  
  table td {
    padding: 5px 8px !important;
    border-bottom: 1px solid #ddd !important;
    font-size: 9pt !important;
  }
  
  table tr.font-bold td,
  table tr.font-semibold td {
    font-weight: bold !important;
  }
  
  table tr.border-t td {
    border-top: 1px solid #000 !important;
  }
  
  table tr.border-t-2 td {
    border-top: 2px solid #000 !important;
  }
  
  table tr.border-double td {
    border-top: 3px double #000 !important;
  }
  
  /* Grid layout for Balance Sheet */
  .grid-cols-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
  }
  
  /* Typography */
  h3 {
    font-size: 12pt !important;
    font-weight: bold !important;
    margin-bottom: 0.5rem !important;
  }
  
  /* Alerts */
  .alert {
    padding: 8px 12px !important;
    border: 1px solid #ddd !important;
    border-radius: 4px !important;
    margin-top: 1rem !important;
    page-break-inside: avoid;
  }
  
  .alert-success {
    border-color: #22c55e !important;
    background: #f0fdf4 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  
  .alert-danger {
    border-color: #ef4444 !important;
    background: #fef2f2 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  
  /* Number formatting */
  .text-right {
    text-align: right !important;
  }
  
  .font-mono {
    font-family: 'Courier New', monospace !important;
  }
  
  /* Page breaks */
  .card-content {
    page-break-inside: avoid;
  }
  
  h2, h3 {
    page-break-after: avoid;
  }
  
  /* Footer for each page */
  @page {
    @bottom-center {
      content: "Page " counter(page) " of " counter(pages);
    }
  }
}

/* Screen-only enhancements */
@media screen {
  .report-card table {
    font-variant-numeric: tabular-nums;
  }
  
  .report-card tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
  }
}
</style>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/../../components/layout.php';
?>

