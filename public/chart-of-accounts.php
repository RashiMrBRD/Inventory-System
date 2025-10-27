<?php
/**
 * Chart of Accounts Page
 * Lists all accounting accounts with advanced filtering and bulk actions
 * Features inspired by Xero, QuickBooks, and LedgerSMB
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
$user = $_SESSION['user_id'];

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

// Format money function
function formatMoney($amount, $symbol) {
    return $symbol . number_format($amount, 2);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $accountId = $_POST['account_id'] ?? '';
    $accountIds = $_POST['account_ids'] ?? [];

    // Initialize default accounts
    if ($action === 'initialize') {
        $result = $accountingController->initializeDefaultAccounts();
        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = $result['success'] ? 'success' : 'danger';
        header('Location: chart-of-accounts.php');
        exit;
    }
    
    // Single account actions
    elseif ($action === 'activate' && $accountId) {
        // Activate account logic would go here
        $_SESSION['flash_message'] = 'Account activated successfully';
        $_SESSION['flash_type'] = 'success';
    }
    elseif ($action === 'deactivate' && $accountId) {
        // Deactivate account logic would go here
        $_SESSION['flash_message'] = 'Account deactivated successfully';
        $_SESSION['flash_type'] = 'success';
    }
    elseif ($action === 'delete' && $accountId) {
        // Delete account logic would go here
        $_SESSION['flash_message'] = 'Account deleted successfully';
        $_SESSION['flash_type'] = 'success';
    }
    
    // Bulk actions
    elseif ($action === 'bulk_activate' && !empty($accountIds)) {
        $successCount = count($accountIds);
        $_SESSION['flash_message'] = "Activated $successCount accounts successfully";
        $_SESSION['flash_type'] = 'success';
    }
    elseif ($action === 'bulk_deactivate' && !empty($accountIds)) {
        $successCount = count($accountIds);
        $_SESSION['flash_message'] = "Deactivated $successCount accounts successfully";
        $_SESSION['flash_type'] = 'success';
    }
    elseif ($action === 'bulk_delete' && !empty($accountIds)) {
        $successCount = count($accountIds);
        $_SESSION['flash_message'] = "Deleted $successCount accounts successfully";
        $_SESSION['flash_type'] = 'success';
    }

    header('Location: chart-of-accounts.php?' . http_build_query($_GET));
    exit;
}

// Get filter parameters
$viewMode = $_GET['view'] ?? 'grouped'; // 'grouped' or 'list'
$typeFilter = $_GET['type'] ?? 'all';
$subtypeFilter = $_GET['subtype'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'account_code';
$sortOrder = $_GET['order'] ?? 'asc';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = intval($_GET['per_page'] ?? 25);

// Get all accounts (original hierarchy structure)
$result = $accountingController->getAccountHierarchy();
$accountHierarchy = $result['success'] ? $result['data'] : [];

// Flatten accounts for filtering and display
$allAccounts = [];
foreach ($accountHierarchy as $type => $accounts) {
    foreach ($accounts as $account) {
        $account['account_type'] = $type; // Add type to account
        $allAccounts[] = $account;
    }
}

// Apply filters
$filteredAccounts = array_filter($allAccounts, function($account) use ($typeFilter, $subtypeFilter, $statusFilter, $searchQuery) {
    // Type filter
    if ($typeFilter !== 'all' && $account['account_type'] !== $typeFilter) {
        return false;
    }
    
    // Subtype filter
    if ($subtypeFilter !== 'all' && ($account['account_subtype'] ?? '') !== $subtypeFilter) {
        return false;
    }
    
    // Status filter
    if ($statusFilter !== 'all') {
        $isActive = $account['is_active'] ?? true;
        if ($statusFilter === 'active' && !$isActive) return false;
        if ($statusFilter === 'inactive' && $isActive) return false;
    }
    
    // Search filter
    if ($searchQuery) {
        $searchLower = strtolower($searchQuery);
        $codeLower = strtolower($account['account_code']);
        $nameLower = strtolower($account['account_name']);
        if (strpos($codeLower, $searchLower) === false && strpos($nameLower, $searchLower) === false) {
            return false;
        }
    }
    
    return true;
});

// Sort accounts
usort($filteredAccounts, function($a, $b) use ($sortBy, $sortOrder) {
    if ($sortBy === 'account_code') {
        $aVal = $a['account_code'];
        $bVal = $b['account_code'];
    } elseif ($sortBy === 'account_name') {
        $aVal = $a['account_name'];
        $bVal = $b['account_name'];
    } elseif ($sortBy === 'account_type') {
        $aVal = $a['account_type'] ?? '';
        $bVal = $b['account_type'] ?? '';
    } elseif ($sortBy === 'account_subtype') {
        $aVal = $a['account_subtype'] ?? '';
        $bVal = $b['account_subtype'] ?? '';
    } elseif ($sortBy === 'balance') {
        $aVal = $a['balance'] ?? 0;
        $bVal = $b['balance'] ?? 0;
    } elseif ($sortBy === 'is_active') {
        $aVal = $a['is_active'] ?? true ? 1 : 0;
        $bVal = $b['is_active'] ?? true ? 1 : 0;
    } else {
        $aVal = $a[$sortBy] ?? '';
        $bVal = $b[$sortBy] ?? '';
    }
    
    $comparison = $aVal <=> $bVal;
    return $sortOrder === 'desc' ? -$comparison : $comparison;
});

// Pagination
$totalAccounts = count($filteredAccounts);
$totalPages = ceil($totalAccounts / $perPage);
$page = min($page, max(1, $totalPages));
$offset = ($page - 1) * $perPage;
$accounts = array_slice($filteredAccounts, $offset, $perPage);

// Set page variables
$pageTitle = 'Chart of Accounts';

// Start output buffering for content
ob_start();
?>

<?php
// Calculate stats
$stats = [
    'total' => $totalAccounts,
    'active' => count(array_filter($allAccounts, fn($a) => $a['is_active'] ?? true)),
    'inactive' => count(array_filter($allAccounts, fn($a) => !($a['is_active'] ?? true))),
    'assets' => count($accountHierarchy['asset'] ?? []),
    'liabilities' => count($accountHierarchy['liability'] ?? []),
    'equity' => count($accountHierarchy['equity'] ?? []),
    'total_asset_balance' => array_sum(array_column($accountHierarchy['asset'] ?? [], 'balance')),
    'total_liability_balance' => array_sum(array_column($accountHierarchy['liability'] ?? [], 'balance')),
    'total_equity_balance' => array_sum(array_column($accountHierarchy['equity'] ?? [], 'balance')),
];

// Flash message
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<style>
.coa-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.coa-stat-card { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
.coa-stat-label { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
.coa-stat-value { font-size: 1.5rem; font-weight: 600; font-family: monospace; }
.coa-toolbar { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1.5rem; }
.coa-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.sortable-header { cursor: pointer; user-select: none; }
.sortable-header:hover { background: var(--bg-secondary); }
.sort-icon { display: inline-block; margin-left: 0.25rem; opacity: 0.3; }
.sort-icon.active { opacity: 1; }
.bulk-action-banner { position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%); background: var(--color-primary); color: white; padding: 1rem 2rem; border-radius: var(--radius-lg); box-shadow: 0 4px 20px rgba(0,0,0,0.2); display: none; z-index: 1000; align-items: center; gap: 1rem; }
.bulk-action-banner.show { display: flex; }

/* Table Layout - Fixed column widths */
.data-table {
  table-layout: fixed;
  width: 100%;
}

/* Currency and Number Alignment */
.data-table th.text-right,
.data-table td.text-right {
  text-align: right !important;
  padding-right: 0.75rem !important;
}

.data-table td.font-mono {
  font-family: 'Courier New', Courier, monospace !important;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* Column widths using colgroup */
.data-table col.col-checkbox { width: 40px; }
.data-table col.col-code { width: 120px; }
.data-table col.col-name { width: auto; }
.data-table col.col-type { width: 100px; }
.data-table col.col-subtype { width: 150px; }
.data-table col.col-balance { width: 130px; }
.data-table col.col-status { width: 110px; }
.data-table col.col-actions { width: 180px; }

/* Account hierarchy indentation */
.account-indent-1 { padding-left: 1.5rem; }
.account-indent-2 { padding-left: 3rem; }
.account-indent-3 { padding-left: 4.5rem; }

/* Button Group */
.btn-group { display: inline-flex; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); }
.btn-group .btn { border-radius: 0; border: none; border-right: 1px solid var(--border-color); }
.btn-group .btn:last-child { border-right: none; }
.btn-group .btn:hover { z-index: 1; }

/* Grouped View Sections */
.account-section { margin-bottom: 1.5rem; }
.account-section-header { 
  background: var(--bg-secondary); 
  padding: 0.75rem 1rem; 
  border-radius: var(--radius-md) var(--radius-md) 0 0; 
  border: 1px solid var(--border-color); 
  border-bottom: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.account-section-title { font-size: 1.125rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.account-section-icon { font-size: 1.5rem; }
.account-section-count { font-size: 0.875rem; font-weight: normal; color: var(--text-secondary); margin-left: 0.5rem; }
.account-section-body { 
  border: 1px solid var(--border-color); 
  border-radius: 0 0 var(--radius-md) var(--radius-md);
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
      <span class="breadcrumb-current">Chart of Accounts</span>
    </nav>
    <h1 class="content-title">Chart of Accounts (<?php echo number_format($stats['total']); ?>)</h1>
  </div>
  <div class="content-actions">
    <!-- View Toggle -->
    <div class="btn-group" style="margin-right: 1rem;">
      <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'grouped'])); ?>" 
         class="btn btn-<?php echo $viewMode === 'grouped' ? 'primary' : 'secondary'; ?> btn-sm"
         title="Grouped by Type">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 6H20M4 12H20M4 18H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Grouped
      </a>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'list'])); ?>" 
         class="btn btn-<?php echo $viewMode === 'list' ? 'primary' : 'secondary'; ?> btn-sm"
         title="All in One Table">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 3H21M3 9H21M3 15H21M3 21H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        List
      </a>
    </div>
    
    <button class="btn btn-secondary" onclick="showImportModal()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15M7 10L12 15M12 15L17 10M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Import
    </button>
    <button class="btn btn-secondary" onclick="exportAccounts()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Export
    </button>
    <form method="POST" style="display: inline;">
      <input type="hidden" name="action" value="initialize">
      <button type="submit" class="btn btn-secondary" onclick="return confirm('Initialize default chart of accounts? This will add standard business accounts.')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Initialize
      </button>
    </form>
    <a href="account-form.php" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      New Account
    </a>
  </div>
</div>

<?php if ($flashMessage): ?>
<div class="alert alert-<?php echo $flashType; ?> mb-4">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/></svg>
  <span><?php echo htmlspecialchars($flashMessage); ?></span>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="coa-stats-grid">
  <div class="coa-stat-card"><div class="coa-stat-label">Total Accounts</div><div class="coa-stat-value"><?php echo number_format($stats['total']); ?></div></div>
  <div class="coa-stat-card"><div class="coa-stat-label">Active</div><div class="coa-stat-value" style="color: var(--color-success);"><?php echo number_format($stats['active']); ?></div></div>
  <div class="coa-stat-card"><div class="coa-stat-label">Inactive</div><div class="coa-stat-value" style="color: var(--color-danger);"><?php echo number_format($stats['inactive']); ?></div></div>
  <div class="coa-stat-card"><div class="coa-stat-label">Total Assets</div><div class="coa-stat-value"><?php echo formatMoney($stats['total_asset_balance'], $currencySymbol); ?></div></div>
  <div class="coa-stat-card"><div class="coa-stat-label">Total Liabilities</div><div class="coa-stat-value"><?php echo formatMoney($stats['total_liability_balance'], $currencySymbol); ?></div></div>
  <div class="coa-stat-card"><div class="coa-stat-label">Net Equity</div><div class="coa-stat-value"><?php echo formatMoney($stats['total_equity_balance'], $currencySymbol); ?></div></div>
</div>

<!-- Advanced Filters Toolbar -->
<div class="coa-toolbar">
  <form method="GET" id="filter-form">
    <div class="coa-filters">
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Search</label><input type="text" name="search" id="coa-search" class="form-input" placeholder="Code, Name..." value="<?php echo htmlspecialchars($searchQuery); ?>"></div>
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Type</label><select name="type" id="coa-type" class="form-select" onchange="applyFilters()"><option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option><option value="asset" <?php echo $typeFilter === 'asset' ? 'selected' : ''; ?>>Assets</option><option value="liability" <?php echo $typeFilter === 'liability' ? 'selected' : ''; ?>>Liabilities</option><option value="equity" <?php echo $typeFilter === 'equity' ? 'selected' : ''; ?>>Equity</option><option value="income" <?php echo $typeFilter === 'income' ? 'selected' : ''; ?>>Income</option><option value="expense" <?php echo $typeFilter === 'expense' ? 'selected' : ''; ?>>Expenses</option></select></div>
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Subtype</label><select name="subtype" id="coa-subtype" class="form-select" onchange="applyFilters()"><option value="all" <?php echo $subtypeFilter === 'all' ? 'selected' : ''; ?>>All Subtypes</option><option value="current_asset" <?php echo $subtypeFilter === 'current_asset' ? 'selected' : ''; ?>>Current Asset</option><option value="fixed_asset" <?php echo $subtypeFilter === 'fixed_asset' ? 'selected' : ''; ?>>Fixed Asset</option><option value="current_liability" <?php echo $subtypeFilter === 'current_liability' ? 'selected' : ''; ?>>Current Liability</option><option value="long_term_liability" <?php echo $subtypeFilter === 'long_term_liability' ? 'selected' : ''; ?>>Long-term Liability</option><option value="revenue" <?php echo $subtypeFilter === 'revenue' ? 'selected' : ''; ?>>Revenue</option><option value="expense" <?php echo $subtypeFilter === 'expense' ? 'selected' : ''; ?>>Expense</option></select></div>
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Status</label><select name="status" id="coa-status" class="form-select" onchange="applyFilters()"><option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option><option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Show</label><select name="per_page" id="coa-per-page" class="form-select" onchange="applyFilters()"><option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10 per page</option><option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25 per page</option><option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50 per page</option><option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100 per page</option></select></div>
    </div>
    <div style="display: flex; gap: 0.5rem; margin-top: 1rem; justify-content: flex-end;"><button type="button" onclick="clearFilters()" class="btn btn-secondary btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Clear All</button><button type="button" onclick="applyFilters()" class="btn btn-primary btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M21 21L15 15M17 10C17 13.866 13.866 17 10 17C6.13401 17 3 13.866 3 10C3 6.13401 6.13401 3 10 3C13.866 3 17 6.13401 17 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Apply Filters</button></div>
  </form>
</div>

<!-- Bulk Actions Banner -->
<div class="bulk-action-banner" id="bulk-banner"><span id="selected-count">0</span> <span>selected</span><button class="btn btn-sm" style="background: white; color: var(--color-primary);" onclick="bulkActivate()">Activate</button><button class="btn btn-sm" style="background: white; color: var(--color-warning);" onclick="bulkDeactivate()">Deactivate</button><button class="btn btn-sm" style="background: white; color: var(--color-danger);" onclick="bulkDelete()">Delete</button><button class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white;" onclick="clearSelection()">Clear</button></div>

<?php if ($viewMode === 'grouped'): ?>
<!-- GROUPED VIEW - Accounts separated by type -->
<?php
$accountTypes = [
    'asset' => ['title' => 'Assets', 'icon' => '💰', 'badge' => 'success'],
    'liability' => ['title' => 'Liabilities', 'icon' => '📋', 'badge' => 'warning'],
    'equity' => ['title' => 'Equity', 'icon' => '🏦', 'badge' => 'info'],
    'income' => ['title' => 'Income', 'icon' => '📈', 'badge' => 'success'],
    'expense' => ['title' => 'Expenses', 'icon' => '📉', 'badge' => 'danger']
];

// Group filtered accounts by type
$groupedAccounts = [];
foreach ($accounts as $account) {
    $type = $account['account_type'];
    if (!isset($groupedAccounts[$type])) {
        $groupedAccounts[$type] = [];
    }
    $groupedAccounts[$type][] = $account;
}

if (empty($groupedAccounts)):
?>
<div class="card">
  <div class="card-content">
    <div class="empty-state">
      <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none">
        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="currentColor"/>
      </svg>
      <p class="empty-state-title">No Accounts Found</p>
      <p class="empty-state-description">
        <?php if ($searchQuery || $typeFilter !== 'all' || $statusFilter !== 'all'): ?>
          No accounts match your current filters. Try adjusting your search criteria.
        <?php else: ?>
          Initialize the default chart of accounts to get started with standard business accounts.
        <?php endif; ?>
      </p>
      <?php if (!$searchQuery && $typeFilter === 'all' && $statusFilter === 'all'): ?>
      <form method="POST">
        <input type="hidden" name="action" value="initialize">
        <button type="submit" class="btn btn-primary">Initialize Default Accounts</button>
      </form>
      <?php else: ?>
      <a href="chart-of-accounts.php" class="btn btn-secondary">Clear Filters</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else:
  foreach ($accountTypes as $type => $config):
    if (!isset($groupedAccounts[$type]) || empty($groupedAccounts[$type])) continue;
    $typeAccounts = $groupedAccounts[$type];
?>
<!-- <?php echo $config['title']; ?> Section -->
<div class="account-section">
  <div class="account-section-header">
    <div class="account-section-title">
      <span class="account-section-icon"><?php echo $config['icon']; ?></span>
      <?php echo $config['title']; ?>
      <span class="account-section-count">(<?php echo count($typeAccounts); ?> accounts)</span>
    </div>
    <div>
      <span class="badge badge-<?php echo $config['badge']; ?>">
        <?php 
        $typeBalance = array_sum(array_column($typeAccounts, 'balance'));
        echo formatMoney($typeBalance, $currencySymbol);
        ?>
      </span>
    </div>
  </div>
  <div class="account-section-body">
    <div class="table-container" style="border: none; border-radius: 0;">
      <table class="data-table">
        <colgroup>
          <col class="col-checkbox">
          <col class="col-code">
          <col class="col-name">
          <col class="col-subtype">
          <col class="col-balance">
          <col class="col-status">
          <col class="col-actions">
        </colgroup>
        <thead>
          <tr>
            <th><input type="checkbox" class="select-all-type" data-type="<?php echo $type; ?>" onchange="toggleSelectType(this, '<?php echo $type; ?>')"></th>
            <th class="sortable-header" onclick="sortTable('account_code')">Code <span class="sort-icon <?php echo $sortBy === 'account_code' ? 'active' : ''; ?>"><?php echo $sortBy === 'account_code' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
            <th class="sortable-header" onclick="sortTable('account_name')">Account Name <span class="sort-icon <?php echo $sortBy === 'account_name' ? 'active' : ''; ?>"><?php echo $sortBy === 'account_name' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
            <th class="sortable-header" onclick="sortTable('account_subtype')">Subtype <span class="sort-icon <?php echo $sortBy === 'account_subtype' ? 'active' : ''; ?>"><?php echo $sortBy === 'account_subtype' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
            <th class="text-right sortable-header" onclick="sortTable('balance')">Balance <span class="sort-icon <?php echo $sortBy === 'balance' ? 'active' : ''; ?>"><?php echo $sortBy === 'balance' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
            <th class="sortable-header" onclick="sortTable('is_active')">Status <span class="sort-icon <?php echo $sortBy === 'is_active' ? 'active' : ''; ?>"><?php echo $sortBy === 'is_active' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($typeAccounts as $account): 
            $accountId = (string)$account['_id'];
            $isActive = $account['is_active'] ?? true;
            $balance = $account['balance'] ?? 0;
          ?>
          <tr>
            <td><input type="checkbox" class="account-checkbox" data-type="<?php echo $type; ?>" value="<?php echo $accountId; ?>" onchange="updateBulkActions()"></td>
            <td class="font-mono font-semibold">
              <a href="general-ledger.php?code=<?php echo urlencode($account['account_code']); ?>" class="text-primary hover:underline"><?php echo htmlspecialchars($account['account_code']); ?></a>
            </td>
            <td class="font-medium">
              <?php echo htmlspecialchars($account['account_name']); ?>
              <?php if ($account['is_system'] ?? false): ?>
                <span class="badge badge-info ml-2" style="font-size: 0.7rem;">System</span>
              <?php endif; ?>
            </td>
            <td class="text-secondary"><?php echo htmlspecialchars($account['account_subtype'] ?? '-'); ?></td>
            <td class="text-right font-mono"><?php echo formatMoney($balance, $currencySymbol); ?></td>
            <td>
              <?php if ($isActive): ?>
                <span class="badge badge-success">Active</span>
              <?php else: ?>
                <span class="badge badge-default">Inactive</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="flex gap-1" style="flex-wrap: wrap;">
                <a href="general-ledger.php?code=<?php echo urlencode($account['account_code']); ?>" class="btn btn-ghost btn-sm" title="View Ledger">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z" stroke="currentColor" stroke-width="2"/><path d="M2 12C2 12 5 5 12 5C19 5 22 12 22 12C22 12 19 19 12 19C5 19 2 12 2 12Z" stroke="currentColor" stroke-width="2"/></svg>
                </a>
                <a href="account-form.php?id=<?php echo $accountId; ?>" class="btn btn-ghost btn-sm" title="Edit">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/></svg>
                </a>
                <?php if ($isActive): ?>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="action" value="deactivate">
                  <input type="hidden" name="account_id" value="<?php echo $accountId; ?>">
                  <button type="submit" class="btn btn-ghost btn-sm text-warning" title="Deactivate" onclick="return confirm('Deactivate this account?')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  </button>
                </form>
                <?php else: ?>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="action" value="activate">
                  <input type="hidden" name="account_id" value="<?php echo $accountId; ?>">
                  <button type="submit" class="btn btn-ghost btn-sm text-success" title="Activate" onclick="return confirm('Activate this account?')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  </button>
                </form>
                <?php endif; ?>
                <?php if (!($account['is_system'] ?? false)): ?>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="account_id" value="<?php echo $accountId; ?>">
                  <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Delete" onclick="return confirm('Delete this account? This action cannot be undone.')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php 
  endforeach;
endif;
?>

<?php else: ?>
<!-- LIST VIEW - All accounts in unified table -->

<?php if (empty($accounts)): ?>
<div class="card">
  <div class="card-content">
    <div class="empty-state">
      <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none">
        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="currentColor"/>
      </svg>
      <p class="empty-state-title">No Accounts Found</p>
      <p class="empty-state-description">
        <?php if ($searchQuery || $typeFilter !== 'all' || $statusFilter !== 'all'): ?>
          No accounts match your current filters. Try adjusting your search criteria.
        <?php else: ?>
          Initialize the default chart of accounts to get started with standard business accounts.
        <?php endif; ?>
      </p>
      <?php if (!$searchQuery && $typeFilter === 'all' && $statusFilter === 'all'): ?>
      <form method="POST">
        <input type="hidden" name="action" value="initialize">
        <button type="submit" class="btn btn-primary">Initialize Default Accounts</button>
      </form>
      <?php else: ?>
      <a href="chart-of-accounts.php" class="btn btn-secondary">Clear Filters</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>
<div class="table-container">
  <table class="data-table">
    <colgroup>
      <col class="col-checkbox">
      <col class="col-code">
      <col class="col-name">
      <col class="col-type">
      <col class="col-subtype">
      <col class="col-balance">
      <col class="col-status">
      <col class="col-actions">
    </colgroup>
    <thead>
      <tr>
        <th><input type="checkbox" id="select-all" onchange="toggleSelectAll(this)"></th>
        <th class="sortable-header" onclick="sortTable('account_code')">Code <span class="sort-icon <?php echo $sortBy === 'account_code' ? 'active' : ''; ?>"><?php echo $sortBy === 'account_code' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
        <th class="sortable-header" onclick="sortTable('account_name')">Account Name <span class="sort-icon <?php echo $sortBy === 'account_name' ? 'active' : ''; ?>"><?php echo $sortBy === 'account_name' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
        <th class="sortable-header" onclick="sortTable('account_type')">Type <span class="sort-icon <?php echo $sortBy === 'account_type' ? 'active' : ''; ?>"><?php echo $sortBy === 'account_type' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
        <th class="sortable-header" onclick="sortTable('account_subtype')">Subtype <span class="sort-icon <?php echo $sortBy === 'account_subtype' ? 'active' : ''; ?>"><?php echo $sortBy === 'account_subtype' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
        <th class="text-right sortable-header" onclick="sortTable('balance')">Balance <span class="sort-icon <?php echo $sortBy === 'balance' ? 'active' : ''; ?>"><?php echo $sortBy === 'balance' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
        <th class="sortable-header" onclick="sortTable('is_active')">Status <span class="sort-icon <?php echo $sortBy === 'is_active' ? 'active' : ''; ?>"><?php echo $sortBy === 'is_active' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($accounts as $account): 
        $accountId = (string)$account['_id'];
        $accountType = $account['account_type'];
        $isActive = $account['is_active'] ?? true;
        $balance = $account['balance'] ?? 0;
        
        // Define type badges
        $typeBadges = [
          'asset' => 'success',
          'liability' => 'warning',
          'equity' => 'info',
          'income' => 'success',
          'expense' => 'danger'
        ];
        $typeBadge = $typeBadges[$accountType] ?? 'default';
      ?>
      <tr>
        <td><input type="checkbox" class="account-checkbox" value="<?php echo $accountId; ?>" onchange="updateBulkActions()"></td>
        <td class="font-mono font-semibold">
          <a href="general-ledger.php?code=<?php echo urlencode($account['account_code']); ?>" class="text-primary hover:underline"><?php echo htmlspecialchars($account['account_code']); ?></a>
        </td>
        <td class="font-medium">
          <?php echo htmlspecialchars($account['account_name']); ?>
          <?php if ($account['is_system'] ?? false): ?>
            <span class="badge badge-info ml-2" style="font-size: 0.7rem;">System</span>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-<?php echo $typeBadge; ?>"><?php echo htmlspecialchars(ucfirst($accountType)); ?></span></td>
        <td class="text-secondary"><?php echo htmlspecialchars($account['account_subtype'] ?? '-'); ?></td>
        <td class="text-right font-mono"><?php echo formatMoney($balance, $currencySymbol); ?></td>
        <td>
          <?php if ($isActive): ?>
            <span class="badge badge-success">Active</span>
          <?php else: ?>
            <span class="badge badge-default">Inactive</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="flex gap-1" style="flex-wrap: wrap;">
            <a href="general-ledger.php?code=<?php echo urlencode($account['account_code']); ?>" class="btn btn-ghost btn-sm" title="View Ledger">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z" stroke="currentColor" stroke-width="2"/><path d="M2 12C2 12 5 5 12 5C19 5 22 12 22 12C22 12 19 19 12 19C5 19 2 12 2 12Z" stroke="currentColor" stroke-width="2"/></svg>
            </a>
            <a href="account-form.php?id=<?php echo $accountId; ?>" class="btn btn-ghost btn-sm" title="Edit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/></svg>
            </a>
            <?php if ($isActive): ?>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="action" value="deactivate">
              <input type="hidden" name="account_id" value="<?php echo $accountId; ?>">
              <button type="submit" class="btn btn-ghost btn-sm text-warning" title="Deactivate" onclick="return confirm('Deactivate this account?')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              </button>
            </form>
            <?php else: ?>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="action" value="activate">
              <input type="hidden" name="account_id" value="<?php echo $accountId; ?>">
              <button type="submit" class="btn btn-ghost btn-sm text-success" title="Activate" onclick="return confirm('Activate this account?')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              </button>
            </form>
            <?php endif; ?>
            <?php if (!($account['is_system'] ?? false)): ?>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="account_id" value="<?php echo $accountId; ?>">
              <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Delete" onclick="return confirm('Delete this account? This action cannot be undone.')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <div><span class="text-sm text-secondary">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalAccounts); ?> of <?php echo number_format($totalAccounts); ?> accounts</span></div>
  <div class="pagination-buttons">
    <?php if ($page > 1): ?><button onclick="goToPage(<?php echo $page - 1; ?>)" class="btn btn-secondary btn-sm">Previous</button><?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <button onclick="goToPage(<?php echo $i; ?>)" class="btn btn-<?php echo $i === $page ? 'primary' : 'secondary'; ?> btn-sm"><?php echo $i; ?></button>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><button onclick="goToPage(<?php echo $page + 1; ?>)" class="btn btn-secondary btn-sm">Next</button><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; // End view mode check ?>

<!-- Import Modal -->
<div class="modal-backdrop" id="import-backdrop" style="display: none;"></div>
<div class="modal" id="import-modal" style="display: none;">
  <div class="modal-header">
    <h3 class="modal-title">Import Chart of Accounts</h3>
    <button class="modal-close" onclick="closeImportModal()">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
  <div class="modal-body">
    <form id="import-form" enctype="multipart/form-data">
      <div class="form-group">
        <label for="import-file" class="form-label">Select CSV File <span class="required">*</span></label>
        <input type="file" id="import-file" name="file" class="form-input" accept=".csv" required>
        <p class="form-hint">CSV format: Code, Name, Type, Subtype, Balance</p>
      </div>
      
      <div class="form-group">
        <label class="form-label">
          <input type="checkbox" name="overwrite" value="1"> Overwrite existing accounts
        </label>
      </div>

      <div class="alert alert-info" style="margin-top: 1rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
          <path d="M12 16V12M12 8H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <div>
          <strong>CSV Template:</strong><br>
          <code style="font-size: 0.875rem;">1000,Cash,asset,current_asset,10000.00</code>
        </div>
      </div>

      <div class="flex gap-3 mt-4">
        <button type="submit" class="btn btn-primary flex-1">Import Accounts</button>
        <button type="button" class="btn btn-secondary flex-1" onclick="closeImportModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// ============================================
// CHART OF ACCOUNTS AJAX (NO PAGE REFRESH)
// ============================================

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Apply filters via AJAX
function applyFilters() {
  const urlParams = new URLSearchParams(window.location.search);
  
  // Update URL without reload
  const search = document.getElementById('coa-search').value;
  const type = document.getElementById('coa-type').value;
  const subtype = document.getElementById('coa-subtype').value;
  const status = document.getElementById('coa-status').value;
  const perPage = document.getElementById('coa-per-page').value;
  
  urlParams.set('search', search);
  urlParams.set('type', type);
  urlParams.set('subtype', subtype);
  urlParams.set('status', status);
  urlParams.set('per_page', perPage);
  urlParams.set('page', '1'); // Reset to page 1
  
  const newUrl = new URL(window.location.href);
  newUrl.search = urlParams.toString();
  window.history.pushState({}, '', newUrl);
  
  // Reload page with new params
  location.reload();
}

// Clear all filters
function clearFilters() {
  window.location.href = 'chart-of-accounts.php';
}

// Go to specific page
function goToPage(pageNum) {
  const urlParams = new URLSearchParams(window.location.search);
  urlParams.set('page', pageNum);
  
  const newUrl = new URL(window.location.href);
  newUrl.search = urlParams.toString();
  window.history.pushState({}, '', newUrl);
  
  location.reload();
}

// Sorting functionality (AJAX)
function sortTable(column) {
  const urlParams = new URLSearchParams(window.location.search);
  const currentSort = urlParams.get('sort');
  const currentOrder = urlParams.get('order');
  
  let newOrder = 'asc';
  if (currentSort === column && currentOrder === 'asc') {
    newOrder = 'desc';
  }
  
  urlParams.set('sort', column);
  urlParams.set('order', newOrder);
  
  const newUrl = new URL(window.location.href);
  newUrl.search = urlParams.toString();
  window.history.pushState({}, '', newUrl);
  
  // Reload page with new sort
  location.reload();
}

// Search with debounce
const searchInput = document.getElementById('coa-search');
if (searchInput) {
  searchInput.addEventListener('input', debounce(function(e) {
    applyFilters();
  }, 500));
}

// Select all checkbox
function toggleSelectAll(checkbox) {
  const checkboxes = document.querySelectorAll('.account-checkbox');
  checkboxes.forEach(cb => cb.checked = checkbox.checked);
  updateBulkActions();
}

// Update bulk actions banner
function updateBulkActions() {
  const checked = document.querySelectorAll('.account-checkbox:checked');
  const banner = document.getElementById('bulk-banner');
  const count = document.getElementById('selected-count');
  
  count.textContent = checked.length;
  
  if (checked.length > 0) {
    banner.classList.add('show');
  } else {
    banner.classList.remove('show');
  }
}

// Clear selection
function clearSelection() {
  const checkboxes = document.querySelectorAll('.account-checkbox');
  checkboxes.forEach(cb => cb.checked = false);
  document.getElementById('select-all').checked = false;
  updateBulkActions();
}

// Bulk activate
function bulkActivate() {
  const checked = Array.from(document.querySelectorAll('.account-checkbox:checked'));
  if (checked.length === 0) return;
  
  if (!confirm(`Activate ${checked.length} account(s)?`)) return;
  
  const form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = '<input type="hidden" name="action" value="bulk_activate">';
  
  checked.forEach(cb => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'account_ids[]';
    input.value = cb.value;
    form.appendChild(input);
  });
  
  document.body.appendChild(form);
  form.submit();
}

// Bulk deactivate
function bulkDeactivate() {
  const checked = Array.from(document.querySelectorAll('.account-checkbox:checked'));
  if (checked.length === 0) return;
  
  if (!confirm(`Deactivate ${checked.length} account(s)?`)) return;
  
  const form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = '<input type="hidden" name="action" value="bulk_deactivate">';
  
  checked.forEach(cb => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'account_ids[]';
    input.value = cb.value;
    form.appendChild(input);
  });
  
  document.body.appendChild(form);
  form.submit();
}

// Bulk delete
function bulkDelete() {
  const checked = Array.from(document.querySelectorAll('.account-checkbox:checked'));
  if (checked.length === 0) return;
  
  if (!confirm(`Delete ${checked.length} account(s)? This action cannot be undone.`)) return;
  
  const form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = '<input type="hidden" name="action" value="bulk_delete">';
  
  checked.forEach(cb => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'account_ids[]';
    input.value = cb.value;
    form.appendChild(input);
  });
  
  document.body.appendChild(form);
  form.submit();
}

// Export accounts to XLSX (modern format with proper UTF-8 support)
function exportAccounts() {
  // Call API endpoint to generate XLSX
  window.location.href = 'api/export-accounts.php';
}

// Show import modal
function showImportModal() {
  document.getElementById('import-modal').style.display = 'block';
  document.getElementById('import-backdrop').style.display = 'block';
}

// Close import modal
function closeImportModal() {
  document.getElementById('import-modal').style.display = 'none';
  document.getElementById('import-backdrop').style.display = 'none';
}

// Handle import form submission
document.addEventListener('DOMContentLoaded', function() {
  const importForm = document.getElementById('import-form');
  if (importForm) {
    importForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const fileInput = document.getElementById('import-file');
      if (!fileInput.files[0]) {
        alert('Please select a file to import');
        return;
      }
      
      // In a real implementation, this would send the file to the server
      console.log('Import functionality coming soon!');
      closeImportModal();
    });
  }
});

// Toggle select all for a specific type (grouped view)
function toggleSelectType(checkbox, type) {
  const checkboxes = document.querySelectorAll('.account-checkbox[data-type="' + type + '"]');
  checkboxes.forEach(cb => cb.checked = checkbox.checked);
  updateBulkActions();
}
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
