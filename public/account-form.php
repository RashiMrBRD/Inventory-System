<?php
/**
 * Account Form Page
 * Add/Edit accounting account with modern features
 * Inspired by Xero, QuickBooks, and LedgerSMB
 */

session_start();

// Initialize timezone
require_once __DIR__ . '/init_timezone.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
use App\Controller\AccountingController;

$accountingController = new AccountingController();
$error = '';
$account = null;
$isEdit = false;

// Get account ID if editing
$accountId = $_GET['id'] ?? '';
if ($accountId) {
    $result = $accountingController->getAllAccounts();
    if ($result['success']) {
        foreach ($result['data'] as $acc) {
            if ((string)$acc['_id'] === $accountId) {
                $account = $acc;
                $isEdit = true;
                break;
            }
        }
    }
}

// Get all accounts for code suggestion
$allAccountsResult = $accountingController->getAllAccounts();
$allAccounts = $allAccountsResult['success'] ? $allAccountsResult['data'] : [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountData = [
        'account_code' => $_POST['account_code'] ?? '',
        'account_name' => $_POST['account_name'] ?? '',
        'account_type' => $_POST['account_type'] ?? '',
        'account_subtype' => $_POST['account_subtype'] ?? '',
        'description' => $_POST['description'] ?? '',
        'is_active' => isset($_POST['is_active'])
    ];

    if ($isEdit) {
        $result = $accountingController->updateAccount($accountId, $accountData);
    } else {
        $result = $accountingController->createAccount($accountData);
    }

    if ($result['success']) {
        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = 'success';
        header('Location: chart-of-accounts.php');
        exit;
    } else {
        $error = $result['message'];
    }
}

// Set page variables
$pageTitle = $isEdit ? 'Edit Account' : 'Add Account';

// Start output buffering for content
ob_start();
?>

<style>
/* Enhanced Form Styles */
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.form-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1.5rem; }
.form-card-header { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); }
.form-card-icon { width: 20px; height: 20px; }
.form-hint-box { background: var(--bg-secondary); border-left: 3px solid var(--color-info); padding: 0.75rem 1rem; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-secondary); }
.code-suggestions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
.code-suggestion { background: var(--bg-secondary); border: 1px solid var(--border-color); padding: 0.25rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.75rem; cursor: pointer; transition: all 0.2s; font-family: monospace; }
.code-suggestion:hover { background: var(--color-primary); color: white; border-color: var(--color-primary); }
.validation-message { font-size: 0.75rem; margin-top: 0.25rem; display: none; }
.validation-message.show { display: block; }
.validation-message.error { color: var(--color-danger); }
.validation-message.success { color: var(--color-success); }
.validation-message.warning { color: var(--color-warning); }
.input-with-icon { position: relative; }
.input-icon { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; }
.account-preview { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
.account-preview-label { font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
.account-preview-value { font-size: 1rem; font-weight: 600; font-family: monospace; color: var(--text-primary); }
.subtype-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; margin-top: 0.75rem; }
.subtype-card { background: var(--bg-primary); border: 2px solid var(--border-color); padding: 0.75rem; border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s; }
.subtype-card:hover { border-color: var(--color-primary); background: var(--bg-secondary); }
.subtype-card.selected { border-color: var(--color-primary); background: var(--color-primary); color: white; }
.subtype-card-title { font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem; }
.subtype-card-desc { font-size: 0.75rem; color: var(--text-secondary); }
.subtype-card.selected .subtype-card-desc { color: rgba(255,255,255,0.9); }

@media (max-width: 768px) {
  .form-grid-2 { grid-template-columns: 1fr; }
}
</style>

<!-- Page Header -->
<div class="content-header">
  <div>
    <nav class="breadcrumb">
      <a href="dashboard.php" class="breadcrumb-link">Dashboard</a>
      <span class="breadcrumb-separator">/</span>
      <a href="#" class="breadcrumb-link">Accounting</a>
      <span class="breadcrumb-separator">/</span>
      <a href="chart-of-accounts.php" class="breadcrumb-link">Chart of Accounts</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current"><?php echo $isEdit ? 'Edit' : 'Add'; ?> Account</span>
    </nav>
    <h1 class="content-title"><?php echo $isEdit ? 'Edit' : 'Add New'; ?> Account</h1>
  </div>
</div>

<div class="form-container" style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; max-width: 1400px;">
  <!-- LEFT COLUMN: Main Form -->
  <div class="form-left">
  <!-- Main Form Card -->
  <div class="form-card">
    <div class="form-card-header">
      <svg class="form-card-icon" viewBox="0 0 24 24" fill="none">
        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20Z" fill="currentColor"/>
        <path d="M13 7H11V13H17V11H13V7Z" fill="currentColor"/>
      </svg>
      Account Information
    </div>
      <?php if ($error): ?>
      <div class="alert alert-danger mb-4">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" id="account-form">
        <div class="form-grid-2">
          <!-- Account Code -->
          <div class="form-group">
            <label for="account_code" class="form-label">
              Account Code <span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <input 
                type="text" 
                id="account_code" 
                name="account_code" 
                class="form-input" 
                value="<?php echo htmlspecialchars($account['account_code'] ?? ''); ?>"
                placeholder="e.g., 1000"
                required
                <?php echo $isEdit ? 'readonly' : ''; ?>
                pattern="[0-9]{3,5}"
                maxlength="5"
                autofocus
              >
              <svg class="input-icon" id="code-validation-icon" style="display: none;" viewBox="0 0 24 24" fill="none">
                <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </div>
            <div class="validation-message" id="code-validation"></div>
            <?php if (!$isEdit): ?>
            <div class="code-suggestions" id="code-suggestions">
              <!-- Dynamic suggestions will appear here -->
            </div>
            <?php endif; ?>
            <div class="form-hint-box">
              <strong>Standard Ranges:</strong><br>
              1000-1999: Assets | 2000-2999: Liabilities | 3000-3999: Equity<br>
              4000-4999: Income | 5000-6999: Expenses
            </div>
          </div>

          <!-- Account Type -->
          <div class="form-group">
            <label for="account_type" class="form-label">
              Account Type <span class="required">*</span>
            </label>
            <select id="account_type" name="account_type" class="form-select" required>
              <option value="">Select Type</option>
              <option value="asset" <?php echo ($account['account_type'] ?? '') === 'asset' ? 'selected' : ''; ?>>💰 Asset</option>
              <option value="liability" <?php echo ($account['account_type'] ?? '') === 'liability' ? 'selected' : ''; ?>>📋 Liability</option>
              <option value="equity" <?php echo ($account['account_type'] ?? '') === 'equity' ? 'selected' : ''; ?>>🏦 Equity</option>
              <option value="income" <?php echo ($account['account_type'] ?? '') === 'income' ? 'selected' : ''; ?>>📈 Income</option>
              <option value="expense" <?php echo ($account['account_type'] ?? '') === 'expense' ? 'selected' : ''; ?>>📉 Expense</option>
            </select>
            <div class="form-hint-box">
              The account type determines where this account appears in financial statements.
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="account_name" class="form-label">
            Account Name <span class="required">*</span>
          </label>
          <input 
            type="text" 
            id="account_name" 
            name="account_name" 
            class="form-input" 
            value="<?php echo htmlspecialchars($account['account_name'] ?? ''); ?>"
            placeholder="e.g., Cash, Bank Account, Sales Revenue"
            required
          >
        </div>

        <!-- Account Subtype (Dynamic) -->
        <div class="form-group" id="subtype-section" style="display: none;">
          <label class="form-label">
            Account Subtype
          </label>
          <input type="hidden" id="account_subtype" name="account_subtype" value="<?php echo htmlspecialchars($account['account_subtype'] ?? ''); ?>">
          <div class="subtype-grid" id="subtype-options">
            <!-- Dynamic subtype cards will appear here -->
          </div>
        </div>

        <div class="form-group">
          <label for="description" class="form-label">
            Description
          </label>
          <textarea 
            id="description" 
            name="description" 
            class="form-input" 
            rows="3"
            placeholder="Optional description or notes about this account"
          ><?php echo htmlspecialchars($account['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
          <label class="flex items-center gap-2">
            <input 
              type="checkbox" 
              name="is_active" 
              <?php echo ($account['is_active'] ?? true) ? 'checked' : ''; ?>
            >
            <span class="font-medium">Active Account</span>
          </label>
          <span class="form-helper">Inactive accounts won't appear in dropdown lists</span>
        </div>

        <div class="flex gap-3 mt-6">
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <?php echo $isEdit ? 'Update' : 'Create'; ?> Account
          </button>
          <a href="chart-of-accounts.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
  </div>

  <!-- Account Preview Card -->
  <div class="form-card" id="preview-card" style="display: none;">
    <div class="form-card-header">
      <svg class="form-card-icon" viewBox="0 0 24 24" fill="none">
        <path d="M15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z" stroke="currentColor" stroke-width="2"/>
        <path d="M2 12C2 12 5 5 12 5C19 5 22 12 22 12C22 12 19 19 12 19C5 19 2 12 2 12Z" stroke="currentColor" stroke-width="2"/>
      </svg>
      Account Preview
    </div>
    <div class="account-preview">
      <div class="account-preview-label">Full Account Display</div>
      <div class="account-preview-value" id="preview-display">Select type and enter details...</div>
    </div>
    <div class="form-hint-box" style="margin-top: 1rem;">
      This is how your account will appear in lists and dropdowns.
    </div>
  </div>

  </div>
  <!-- LEFT COLUMN END -->

  <!-- RIGHT COLUMN: Advanced Settings & Helpers -->
  <div class="form-right">
    
    <!-- Tax Settings Card -->
    <div class="form-card" id="tax-settings-card" style="display: none;">
      <div class="form-card-header">
        <svg class="form-card-icon" viewBox="0 0 24 24" fill="none">
          <path d="M9 7H6C5.46957 7 4.96086 7.21071 4.58579 7.58579C4.21071 7.96086 4 8.46957 4 9V18C4 18.5304 4.21071 19.0391 4.58579 19.4142C4.96086 19.7893 5.46957 20 6 20H15C15.5304 20 16.0391 19.7893 16.4142 19.4142C16.7893 19.0391 17 18.5304 17 18V15M11 13L21 3M21 3H15M21 3V9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Tax Configuration
      </div>
      <div class="form-group">
        <label class="flex items-center gap-2">
          <input type="checkbox" id="enable_tax" name="enable_tax">
          <span class="font-medium">Enable Tax Tracking</span>
        </label>
        <span class="form-helper">Track sales tax for this account</span>
      </div>
      <div class="form-group" id="tax-code-group" style="display: none;">
        <label for="default_tax_code" class="form-label">Default Tax Code</label>
        <select id="default_tax_code" name="default_tax_code" class="form-select">
          <option value="">None</option>
          <option value="VAT12">VAT 12%</option>
          <option value="VAT0">VAT 0% (Zero-rated)</option>
          <option value="EXEMPT">Tax Exempt</option>
        </select>
      </div>
    </div>

    <!-- Opening Balance Card -->
    <div class="form-card" id="opening-balance-card" style="<?php echo $isEdit ? 'display: none;' : ''; ?>">
      <div class="form-card-header">
        <svg class="form-card-icon" viewBox="0 0 24 24" fill="none">
          <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="currentColor"/>
        </svg>
        Opening Balance
      </div>
      <div class="form-group">
        <label for="opening_balance" class="form-label">Starting Balance</label>
        <div style="position: relative;">
          <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);">₱</span>
          <input 
            type="number" 
            id="opening_balance" 
            name="opening_balance" 
            class="form-input" 
            style="padding-left: 2rem;"
            value="0.00"
            step="0.01"
            placeholder="0.00"
          >
        </div>
        <span class="form-helper">Initial balance for this account (optional)</span>
      </div>
      <div class="form-group">
        <label for="balance_date" class="form-label">As of Date</label>
        <input 
          type="date" 
          id="balance_date" 
          name="balance_date" 
          class="form-input" 
          value="<?php echo date('Y-m-d'); ?>"
        >
      </div>
    </div>

    <!-- Bank Account Details Card -->
    <div class="form-card" id="bank-details-card" style="display: none;">
      <div class="form-card-header">
        <svg class="form-card-icon" viewBox="0 0 24 24" fill="none">
          <path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="currentColor" stroke-width="2"/>
          <path d="M9 22V12H15V22" stroke="currentColor" stroke-width="2"/>
        </svg>
        Bank Account Details
      </div>
      <div class="form-group">
        <label for="bank_name" class="form-label">Bank Name</label>
        <input 
          type="text" 
          id="bank_name" 
          name="bank_name" 
          class="form-input" 
          placeholder="e.g., BDO, BPI, Metrobank"
        >
      </div>
      <div class="form-group">
        <label for="account_number" class="form-label">Account Number</label>
        <input 
          type="text" 
          id="account_number" 
          name="account_number" 
          class="form-input" 
          placeholder="e.g., 1234567890"
        >
      </div>
      <div class="form-group">
        <label class="flex items-center gap-2">
          <input type="checkbox" id="enable_reconciliation" name="enable_reconciliation">
          <span class="font-medium">Enable Bank Reconciliation</span>
        </label>
        <span class="form-helper">Track and reconcile bank statements</span>
      </div>
    </div>

    <!-- Account Usage Tips -->
    <div class="form-card" id="usage-tips-card">
      <div class="form-card-header">
        <svg class="form-card-icon" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
          <path d="M12 16V12M12 8H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Tips & Best Practices
      </div>
      <div id="tips-content" style="font-size: 0.875rem; line-height: 1.6; color: var(--text-secondary);">
        <p>Select an account type to see specific tips and best practices.</p>
      </div>
    </div>

    <!-- Similar Accounts Card -->
    <div class="form-card" id="similar-accounts-card" style="display: none;">
      <div class="form-card-header">
        <svg class="form-card-icon" viewBox="0 0 24 24" fill="none">
          <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21M23 21V19C22.9993 18.1137 22.7044 17.2528 22.1614 16.5523C21.6184 15.8519 20.8581 15.3516 20 15.13M16 3.13C16.8604 3.3503 17.623 3.8507 18.1676 4.55231C18.7122 5.25392 19.0078 6.11683 19.0078 7.005C19.0078 7.89317 18.7122 8.75608 18.1676 9.45769C17.623 10.1593 16.8604 10.6597 16 10.88M13 7C13 9.20914 11.2091 11 9 11C6.79086 11 5 9.20914 5 7C5 4.79086 6.79086 3 9 3C11.2091 3 13 4.79086 13 7Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Similar Accounts
      </div>
      <div id="similar-accounts-list" style="font-size: 0.875rem;">
        <!-- Dynamic content -->
      </div>
    </div>

    <!-- Quick Reference Guide -->
    <div class="form-card">
      <div class="form-card-header">
        <svg class="form-card-icon" viewBox="0 0 24 24" fill="none">
          <path d="M12 6.25278V19.2528M12 6.25278C10.8321 5.47686 9.24649 5 7.5 5C5.75351 5 4.16789 5.47686 3 6.25278V19.2528C4.16789 18.4769 5.75351 18 7.5 18C9.24649 18 10.8321 18.4769 12 19.2528M12 6.25278C13.1679 5.47686 14.7535 5 16.5 5C18.2465 5 19.8321 5.47686 21 6.25278V19.2528C19.8321 18.4769 18.2465 18 16.5 18C14.7535 18 13.1679 18.4769 12 19.2528" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Quick Reference
      </div>
      <div style="font-size: 0.875rem; line-height: 1.6; color: var(--text-secondary);">
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
          <div>
            <strong>💰 1000-1999:</strong> Assets<br>
            <span style="font-size: 0.75rem;">Cash, Bank, AR, Inventory</span>
          </div>
          <div>
            <strong>📋 2000-2999:</strong> Liabilities<br>
            <span style="font-size: 0.75rem;">AP, Loans, Credit Cards</span>
          </div>
          <div>
            <strong>🏦 3000-3999:</strong> Equity<br>
            <span style="font-size: 0.75rem;">Owner's Equity, Retained Earnings</span>
          </div>
          <div>
            <strong>📈 4000-4999:</strong> Income<br>
            <span style="font-size: 0.75rem;">Sales, Service Revenue</span>
          </div>
          <div>
            <strong>📉 5000-6999:</strong> Expenses<br>
            <span style="font-size: 0.75rem;">COGS, Salaries, Rent</span>
          </div>
        </div>
      </div>
    </div>

  </div>
  <!-- RIGHT COLUMN END -->
</div>

<!-- Responsive CSS -->
<style>
@media (max-width: 1024px) {
  .form-container {
    grid-template-columns: 1fr !important;
  }
  .form-right {
    margin-top: 1rem;
  }
}
</style>

<script>
// All existing accounts for validation
const existingAccounts = <?php echo json_encode($allAccounts); ?>;

// Subtype definitions
const subtypes = {
  asset: [
    { value: 'current_asset', title: 'Current Asset', desc: 'Cash, AR, inventory - converted within 1 year' },
    { value: 'fixed_asset', title: 'Fixed Asset', desc: 'Equipment, buildings, vehicles' },
    { value: 'other_asset', title: 'Other Asset', desc: 'Long-term investments, intangibles' }
  ],
  liability: [
    { value: 'current_liability', title: 'Current Liability', desc: 'AP, short-term loans - due within 1 year' },
    { value: 'long_term_liability', title: 'Long-term Liability', desc: 'Mortgages, bonds payable' }
  ],
  equity: [
    { value: 'owner_equity', title: "Owner's Equity", desc: 'Capital contributions, drawings' },
    { value: 'retained_earnings', title: 'Retained Earnings', desc: 'Accumulated profits/losses' }
  ],
  income: [
    { value: 'operating_income', title: 'Operating Income', desc: 'Primary business revenue' },
    { value: 'other_income', title: 'Other Income', desc: 'Interest, gains on sale' }
  ],
  expense: [
    { value: 'cost_of_goods_sold', title: 'Cost of Goods Sold', desc: 'Direct costs of producing goods' },
    { value: 'operating_expense', title: 'Operating Expense', desc: 'Rent, salaries, utilities' },
    { value: 'other_expense', title: 'Other Expense', desc: 'Interest, losses, depreciation' }
  ]
};

// Account type change handler
document.getElementById('account_type').addEventListener('change', function() {
  const type = this.value;
  updateCodeSuggestions(type);
  updateSubtypeOptions(type);
  updatePreview();
  updateRightSidebar(type);
});

// Account code validation
const codeInput = document.getElementById('account_code');
const codeValidation = document.getElementById('code-validation');
const codeIcon = document.getElementById('code-validation-icon');

if (codeInput && !codeInput.readOnly) {
  codeInput.addEventListener('input', function() {
    validateAccountCode(this.value);
    updatePreview();
  });
}

// Account name change
document.getElementById('account_name').addEventListener('input', updatePreview);

function validateAccountCode(code) {
  if (!code) {
    codeValidation.className = 'validation-message';
    codeIcon.style.display = 'none';
    return;
  }

  // Check format
  if (!/^[0-9]{3,5}$/.test(code)) {
    codeValidation.textContent = 'Code must be 3-5 digits';
    codeValidation.className = 'validation-message show error';
    codeIcon.style.display = 'none';
    return;
  }

  // Check if already exists
  const exists = existingAccounts.some(acc => acc.account_code === code);
  if (exists) {
    codeValidation.textContent = '❌ Code already exists';
    codeValidation.className = 'validation-message show error';
    codeIcon.style.display = 'none';
    return;
  }

  // Success
  codeValidation.textContent = '✓ Code available';
  codeValidation.className = 'validation-message show success';
  codeIcon.style.display = 'block';
  codeIcon.style.color = 'var(--color-success)';
}

function updateCodeSuggestions(type) {
  const suggestionsDiv = document.getElementById('code-suggestions');
  if (!suggestionsDiv) return;

  suggestionsDiv.innerHTML = '';

  // Get range for type
  const ranges = {
    asset: { start: 1000, end: 1999 },
    liability: { start: 2000, end: 2999 },
    equity: { start: 3000, end: 3999 },
    income: { start: 4000, end: 4999 },
    expense: { start: 5000, end: 6999 }
  };

  if (!type || !ranges[type]) return;

  const range = ranges[type];
  const usedCodes = existingAccounts
    .filter(acc => acc.account_code >= range.start && acc.account_code <= range.end)
    .map(acc => parseInt(acc.account_code));

  // Find next available codes
  const suggestions = [];
  for (let i = 0; i < 5; i++) {
    let code = range.start + (i * 10);
    while (usedCodes.includes(code) && code <= range.end) {
      code += 10;
    }
    if (code <= range.end) {
      suggestions.push(code);
    }
  }

  suggestions.forEach(code => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'code-suggestion';
    btn.textContent = code;
    btn.onclick = () => {
      document.getElementById('account_code').value = code;
      validateAccountCode(code.toString());
      updatePreview();
    };
    suggestionsDiv.appendChild(btn);
  });

  if (suggestions.length > 0) {
    const label = document.createElement('span');
    label.style.fontSize = '0.75rem';
    label.style.color = 'var(--text-secondary)';
    label.style.width = '100%';
    label.textContent = 'Suggested codes:';
    suggestionsDiv.insertBefore(label, suggestionsDiv.firstChild);
  }
}

function updateSubtypeOptions(type) {
  const section = document.getElementById('subtype-section');
  const optionsDiv = document.getElementById('subtype-options');
  const hiddenInput = document.getElementById('account_subtype');

  if (!type || !subtypes[type]) {
    section.style.display = 'none';
    return;
  }

  section.style.display = 'block';
  optionsDiv.innerHTML = '';

  subtypes[type].forEach(subtype => {
    const card = document.createElement('div');
    card.className = 'subtype-card';
    if (hiddenInput.value === subtype.value) {
      card.classList.add('selected');
    }

    card.innerHTML = `
      <div class="subtype-card-title">${subtype.title}</div>
      <div class="subtype-card-desc">${subtype.desc}</div>
    `;

    card.onclick = () => {
      // Remove selected from all
      optionsDiv.querySelectorAll('.subtype-card').forEach(c => c.classList.remove('selected'));
      // Add to clicked
      card.classList.add('selected');
      hiddenInput.value = subtype.value;
      updatePreview();
    };

    optionsDiv.appendChild(card);
  });
}

function updatePreview() {
  const code = document.getElementById('account_code').value;
  const name = document.getElementById('account_name').value;
  const type = document.getElementById('account_type').value;
  const previewCard = document.getElementById('preview-card');
  const previewDisplay = document.getElementById('preview-display');

  if (!code && !name && !type) {
    previewCard.style.display = 'none';
    return;
  }

  previewCard.style.display = 'block';

  const typeEmojis = {
    asset: '💰',
    liability: '📋',
    equity: '🏦',
    income: '📈',
    expense: '📉'
  };

  const parts = [];
  if (code) parts.push(code);
  if (name) parts.push(name);
  if (type) parts.push(`(${typeEmojis[type] || ''} ${type.charAt(0).toUpperCase() + type.slice(1)})`);

  previewDisplay.textContent = parts.join(' - ') || 'Select type and enter details...';
}

// Tax tracking toggle
const enableTaxCheckbox = document.getElementById('enable_tax');
if (enableTaxCheckbox) {
  enableTaxCheckbox.addEventListener('change', function() {
    document.getElementById('tax-code-group').style.display = this.checked ? 'block' : 'none';
  });
}

// Update right sidebar based on account type
function updateRightSidebar(type) {
  const taxCard = document.getElementById('tax-settings-card');
  const bankCard = document.getElementById('bank-details-card');
  const tipsCard = document.getElementById('usage-tips-card');
  const similarCard = document.getElementById('similar-accounts-card');
  const tipsContent = document.getElementById('tips-content');
  const similarList = document.getElementById('similar-accounts-list');
  
  // Show/hide tax settings for income and expense accounts
  if (type === 'income' || type === 'expense') {
    taxCard.style.display = 'block';
  } else {
    taxCard.style.display = 'none';
  }
  
  // Show bank details for cash/bank accounts
  const accountName = document.getElementById('account_name').value.toLowerCase();
  if ((type === 'asset') && (accountName.includes('bank') || accountName.includes('cash'))) {
    bankCard.style.display = 'block';
  } else {
    bankCard.style.display = 'none';
  }
  
  // Update tips content
  const tips = {
    asset: `
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <div>
          <strong>💡 When to Use:</strong>
          <p>Use asset accounts for resources your business owns that have monetary value.</p>
        </div>
        <div>
          <strong>✅ Best Practices:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Separate current assets (cash, AR) from fixed assets (equipment)</li>
            <li>Create sub-accounts for different bank accounts</li>
            <li>Track inventory in separate accounts by category</li>
            <li>Review asset values annually</li>
          </ul>
        </div>
        <div>
          <strong>⚠️ Common Mistakes:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Mixing personal and business assets</li>
            <li>Not depreciating fixed assets</li>
            <li>Forgetting to reconcile bank accounts</li>
          </ul>
        </div>
      </div>
    `,
    liability: `
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <div>
          <strong>💡 When to Use:</strong>
          <p>Use liability accounts for debts and obligations your business owes to others.</p>
        </div>
        <div>
          <strong>✅ Best Practices:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Separate short-term and long-term liabilities</li>
            <li>Track each loan in a separate account</li>
            <li>Monitor payment due dates</li>
            <li>Reconcile credit card statements monthly</li>
          </ul>
        </div>
        <div>
          <strong>⚠️ Common Mistakes:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Not tracking interest separately</li>
            <li>Forgetting to record accrued expenses</li>
            <li>Mixing different creditors in one account</li>
          </ul>
        </div>
      </div>
    `,
    equity: `
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <div>
          <strong>💡 When to Use:</strong>
          <p>Use equity accounts to track owner investments and retained earnings.</p>
        </div>
        <div>
          <strong>✅ Best Practices:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Separate owner contributions from retained earnings</li>
            <li>Track each partner's equity separately</li>
            <li>Record distributions in separate accounts</li>
            <li>Close net income to retained earnings annually</li>
          </ul>
        </div>
        <div>
          <strong>⚠️ Common Mistakes:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Using owner's equity for daily transactions</li>
            <li>Not tracking owner drawings</li>
            <li>Mixing capital and retained earnings</li>
          </ul>
        </div>
      </div>
    `,
    income: `
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <div>
          <strong>💡 When to Use:</strong>
          <p>Use income accounts to track all revenue sources for your business.</p>
        </div>
        <div>
          <strong>✅ Best Practices:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Create separate accounts for each revenue stream</li>
            <li>Track sales by product category or service type</li>
            <li>Enable tax tracking for taxable income</li>
            <li>Use descriptive names for easy reporting</li>
          </ul>
        </div>
        <div>
          <strong>⚠️ Common Mistakes:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Not tracking sales tax separately</li>
            <li>Mixing different income types</li>
            <li>Recording deposits as income (use AR instead)</li>
          </ul>
        </div>
        <div>
          <strong>💰 Tax Tip:</strong>
          <p>Enable tax tracking to automatically calculate VAT/sales tax on transactions.</p>
        </div>
      </div>
    `,
    expense: `
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <div>
          <strong>💡 When to Use:</strong>
          <p>Use expense accounts to track all costs of running your business.</p>
        </div>
        <div>
          <strong>✅ Best Practices:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Separate COGS from operating expenses</li>
            <li>Create specific accounts for major expense categories</li>
            <li>Track deductible expenses separately</li>
            <li>Review expenses monthly for budgeting</li>
          </ul>
        </div>
        <div>
          <strong>⚠️ Common Mistakes:</strong>
          <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
            <li>Using generic "Office Expense" for everything</li>
            <li>Not tracking mileage/travel separately</li>
            <li>Mixing capital expenditures with expenses</li>
          </ul>
        </div>
        <div>
          <strong>💰 Tax Tip:</strong>
          <p>Keep detailed records of deductible expenses for tax purposes.</p>
        </div>
      </div>
    `
  };
  
  tipsContent.innerHTML = tips[type] || '<p>Select an account type to see specific tips and best practices.</p>';
  
  // Show similar accounts
  if (type) {
    const similarAccounts = existingAccounts
      .filter(acc => acc.account_type === type)
      .slice(0, 5);
    
    if (similarAccounts.length > 0) {
      similarCard.style.display = 'block';
      similarList.innerHTML = `
        <p style="margin-bottom: 0.75rem; color: var(--text-secondary);">Other ${type} accounts you've created:</p>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
          ${similarAccounts.map(acc => `
            <div style="padding: 0.5rem; background: var(--bg-secondary); border-radius: var(--radius-sm); font-size: 0.813rem;">
              <div style="font-weight: 600; font-family: monospace;">${acc.account_code}</div>
              <div style="color: var(--text-secondary);">${acc.account_name}</div>
            </div>
          `).join('')}
        </div>
      `;
    } else {
      similarCard.style.display = 'none';
    }
  } else {
    similarCard.style.display = 'none';
  }
}

// Check account name for bank keywords
document.getElementById('account_name').addEventListener('input', function() {
  const type = document.getElementById('account_type').value;
  const name = this.value.toLowerCase();
  const bankCard = document.getElementById('bank-details-card');
  
  if (type === 'asset' && (name.includes('bank') || name.includes('cash') || name.includes('checking') || name.includes('savings'))) {
    bankCard.style.display = 'block';
  } else if (type !== 'asset') {
    bankCard.style.display = 'none';
  }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  const typeSelect = document.getElementById('account_type');
  if (typeSelect.value) {
    updateCodeSuggestions(typeSelect.value);
    updateSubtypeOptions(typeSelect.value);
    updatePreview();
    updateRightSidebar(typeSelect.value);
  }
});
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
