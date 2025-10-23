<?php
/**
 * Chart of Accounts Page
 * Lists all accounting accounts
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

// Handle initialize default accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'initialize') {
    $result = $accountingController->initializeDefaultAccounts();
    $_SESSION['flash_message'] = $result['message'];
    $_SESSION['flash_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: chart-of-accounts.php');
    exit;
}

// Get all accounts
$result = $accountingController->getAccountHierarchy();
$accountHierarchy = $result['success'] ? $result['data'] : [];

// Set page variables
$pageTitle = 'Chart of Accounts';

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
      <span class="breadcrumb-current">Chart of Accounts</span>
    </nav>
    <h1 class="content-title">Chart of Accounts</h1>
  </div>
  <div class="content-actions">
    <form method="POST" style="display: inline;">
      <input type="hidden" name="action" value="initialize">
      <button type="submit" class="btn btn-secondary" onclick="return confirm('Initialize default chart of accounts? This will add standard business accounts.')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Initialize Defaults
      </button>
    </form>
    <a href="account-form.php" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Add Account
    </a>
  </div>
</div>

<!-- Account Summary Cards -->
<div class="grid grid-cols-5 mb-6">
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Assets</p>
      <p class="stat-value"><?php echo count($accountHierarchy['asset'] ?? []); ?></p>
    </div>
  </div>
  
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Liabilities</p>
      <p class="stat-value"><?php echo count($accountHierarchy['liability'] ?? []); ?></p>
    </div>
  </div>
  
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Equity</p>
      <p class="stat-value"><?php echo count($accountHierarchy['equity'] ?? []); ?></p>
    </div>
  </div>
  
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Income</p>
      <p class="stat-value"><?php echo count($accountHierarchy['income'] ?? []); ?></p>
    </div>
  </div>
  
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Expenses</p>
      <p class="stat-value"><?php echo count($accountHierarchy['expense'] ?? []); ?></p>
    </div>
  </div>
</div>

<?php
$accountTypes = [
    'asset' => ['title' => 'Assets', 'icon' => '💰', 'badge' => 'success'],
    'liability' => ['title' => 'Liabilities', 'icon' => '📋', 'badge' => 'warning'],
    'equity' => ['title' => 'Equity', 'icon' => '🏦', 'badge' => 'info'],
    'income' => ['title' => 'Income', 'icon' => '📈', 'badge' => 'success'],
    'expense' => ['title' => 'Expenses', 'icon' => '📉', 'badge' => 'danger']
];

foreach ($accountTypes as $type => $config):
    $accounts = $accountHierarchy[$type] ?? [];
    if (empty($accounts)) continue;
?>

<!-- <?php echo $config['title']; ?> Section -->
<div class="card mb-6">
  <div class="card-header">
    <h3 class="card-title">
      <span style="font-size: 1.5rem; margin-right: 0.5rem;"><?php echo $config['icon']; ?></span>
      <?php echo $config['title']; ?>
      <span class="badge badge-<?php echo $config['badge']; ?> ml-2"><?php echo count($accounts); ?> accounts</span>
    </h3>
  </div>
  <div class="card-content p-0">
    <div class="table-container" style="border: none; border-radius: 0;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Account Name</th>
            <th>Subtype</th>
            <th class="text-right">Balance</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accounts as $account): ?>
          <tr>
            <td class="font-mono font-semibold"><?php echo htmlspecialchars($account['account_code']); ?></td>
            <td class="font-medium">
              <?php echo htmlspecialchars($account['account_name']); ?>
              <?php if ($account['is_system'] ?? false): ?>
                <span class="badge badge-info ml-2">System</span>
              <?php endif; ?>
            </td>
            <td class="text-secondary">
              <?php echo htmlspecialchars($account['account_subtype'] ?? '-'); ?>
            </td>
            <td class="text-right font-mono">
              <?php 
              $balance = $account['balance'] ?? 0;
              $formatted = number_format(abs($balance), 2);
              echo $balance < 0 ? "($formatted)" : $formatted;
              ?>
            </td>
            <td>
              <?php if ($account['is_active'] ?? true): ?>
                <span class="badge badge-success">Active</span>
              <?php else: ?>
                <span class="badge badge-default">Inactive</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="flex gap-2">
                <a href="account-form.php?id=<?php echo (string)$account['_id']; ?>" 
                   class="btn btn-ghost btn-sm" 
                   title="Edit">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/>
                  </svg>
                </a>
                <a href="general-ledger.php?code=<?php echo urlencode($account['account_code']); ?>" 
                   class="btn btn-ghost btn-sm" 
                   title="View Ledger">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M4 6H20M4 12H20M4 18H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  </svg>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php endforeach; ?>

<?php if (empty($accountHierarchy) || array_sum(array_map('count', $accountHierarchy)) === 0): ?>
<div class="card">
  <div class="card-content">
    <div class="empty-state">
      <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none">
        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="currentColor"/>
      </svg>
      <p class="empty-state-title">No Chart of Accounts Found</p>
      <p class="empty-state-description">
        Initialize the default chart of accounts to get started with standard business accounts.
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="initialize">
        <button type="submit" class="btn btn-primary">Initialize Default Accounts</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
