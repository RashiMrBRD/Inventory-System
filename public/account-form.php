<?php
/**
 * Account Form Page
 * Add/Edit accounting account
 */

session_start();

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

<div class="grid grid-cols-1" style="max-width: 700px;">
  <div class="card">
    <div class="card-content">
      <?php if ($error): ?>
      <div class="alert alert-danger mb-4">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php endif; ?>

      <form method="POST">
        <div class="grid grid-cols-2 gap-4">
          <div class="form-group">
            <label for="account_code" class="form-label">
              Account Code <span class="required">*</span>
            </label>
            <input 
              type="text" 
              id="account_code" 
              name="account_code" 
              class="form-input" 
              value="<?php echo htmlspecialchars($account['account_code'] ?? ''); ?>"
              placeholder="e.g., 1000"
              required
              <?php echo $isEdit ? 'readonly' : ''; ?>
              autofocus
            >
            <span class="form-helper">Unique numeric code (e.g., 1000-1999 for Assets)</span>
          </div>

          <div class="form-group">
            <label for="account_type" class="form-label">
              Account Type <span class="required">*</span>
            </label>
            <select id="account_type" name="account_type" class="form-select" required>
              <option value="">Select Type</option>
              <option value="asset" <?php echo ($account['account_type'] ?? '') === 'asset' ? 'selected' : ''; ?>>Asset</option>
              <option value="liability" <?php echo ($account['account_type'] ?? '') === 'liability' ? 'selected' : ''; ?>>Liability</option>
              <option value="equity" <?php echo ($account['account_type'] ?? '') === 'equity' ? 'selected' : ''; ?>>Equity</option>
              <option value="income" <?php echo ($account['account_type'] ?? '') === 'income' ? 'selected' : ''; ?>>Income</option>
              <option value="expense" <?php echo ($account['account_type'] ?? '') === 'expense' ? 'selected' : ''; ?>>Expense</option>
            </select>
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

        <div class="form-group">
          <label for="account_subtype" class="form-label">
            Account Subtype
          </label>
          <select id="account_subtype" name="account_subtype" class="form-select">
            <option value="">Select Subtype (Optional)</option>
            <optgroup label="Asset Subtypes">
              <option value="current_asset" <?php echo ($account['account_subtype'] ?? '') === 'current_asset' ? 'selected' : ''; ?>>Current Asset</option>
              <option value="fixed_asset" <?php echo ($account['account_subtype'] ?? '') === 'fixed_asset' ? 'selected' : ''; ?>>Fixed Asset</option>
            </optgroup>
            <optgroup label="Liability Subtypes">
              <option value="current_liability" <?php echo ($account['account_subtype'] ?? '') === 'current_liability' ? 'selected' : ''; ?>>Current Liability</option>
              <option value="long_term_liability" <?php echo ($account['account_subtype'] ?? '') === 'long_term_liability' ? 'selected' : ''; ?>>Long-term Liability</option>
            </optgroup>
            <optgroup label="Income Subtypes">
              <option value="operating_income" <?php echo ($account['account_subtype'] ?? '') === 'operating_income' ? 'selected' : ''; ?>>Operating Income</option>
              <option value="other_income" <?php echo ($account['account_subtype'] ?? '') === 'other_income' ? 'selected' : ''; ?>>Other Income</option>
            </optgroup>
            <optgroup label="Expense Subtypes">
              <option value="cost_of_goods_sold" <?php echo ($account['account_subtype'] ?? '') === 'cost_of_goods_sold' ? 'selected' : ''; ?>>Cost of Goods Sold</option>
              <option value="operating_expense" <?php echo ($account['account_subtype'] ?? '') === 'operating_expense' ? 'selected' : ''; ?>>Operating Expense</option>
            </optgroup>
          </select>
          <span class="form-helper">Optional: Further categorize the account</span>
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
  </div>

  <!-- Help Card -->
  <div class="card mt-4">
    <div class="card-header">
      <h3 class="card-title">Account Numbering Guide</h3>
    </div>
    <div class="card-content">
      <div class="text-sm text-secondary" style="line-height: 1.6;">
        <p><strong>Standard Account Ranges:</strong></p>
        <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.5rem;">
          <li><strong>1000-1999:</strong> Assets (Cash, Bank, AR, Inventory, Fixed Assets)</li>
          <li><strong>2000-2999:</strong> Liabilities (AP, Loans, Credit Cards)</li>
          <li><strong>3000-3999:</strong> Equity (Owner's Equity, Retained Earnings)</li>
          <li><strong>4000-4999:</strong> Income (Sales, Service Revenue, Other Income)</li>
          <li><strong>5000-6999:</strong> Expenses (COGS, Operating Expenses)</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
