<?php
/**
 * Journal Entries Page
 * Lists all journal entries
 */

session_start();

// Initialize timezone
require_once __DIR__ . '/init_timezone.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
use App\Controller\AccountingController;
use App\Model\JournalEntry;

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
    $entryId = $_POST['entry_id'] ?? '';
    $entryIds = $_POST['entry_ids'] ?? [];

    // Single entry actions
    if ($action === 'post' && $entryId) {
        $result = $accountingController->postJournalEntry($entryId);
        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = $result['success'] ? 'success' : 'danger';
    } elseif ($action === 'void' && $entryId) {
        $reason = $_POST['void_reason'] ?? 'Voided by user';
        $result = $accountingController->voidJournalEntry($entryId, $reason);
        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = $result['success'] ? 'success' : 'danger';
    } elseif ($action === 'delete' && $entryId) {
        $result = $accountingController->deleteJournalEntry($entryId);
        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = $result['success'] ? 'success' : 'danger';
    }
    
    // Bulk actions
    elseif ($action === 'bulk_post' && !empty($entryIds)) {
        $successCount = 0;
        foreach ($entryIds as $id) {
            $result = $accountingController->postJournalEntry($id);
            if ($result['success']) $successCount++;
        }
        $_SESSION['flash_message'] = "Posted $successCount entries successfully";
        $_SESSION['flash_type'] = 'success';
    } elseif ($action === 'bulk_void' && !empty($entryIds)) {
        $reason = $_POST['bulk_void_reason'] ?? 'Bulk void';
        $successCount = 0;
        foreach ($entryIds as $id) {
            $result = $accountingController->voidJournalEntry($id, $reason);
            if ($result['success']) $successCount++;
        }
        $_SESSION['flash_message'] = "Voided $successCount entries successfully";
        $_SESSION['flash_type'] = 'success';
    } elseif ($action === 'bulk_delete' && !empty($entryIds)) {
        $successCount = 0;
        foreach ($entryIds as $id) {
            $result = $accountingController->deleteJournalEntry($id);
            if ($result['success']) $successCount++;
        }
        $_SESSION['flash_message'] = "Deleted $successCount entries successfully";
        $_SESSION['flash_type'] = 'success';
    }

    header('Location: journal-entries?' . http_build_query($_GET));
    exit;
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'entry_date';
$sortOrder = $_GET['order'] ?? 'desc';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = intval($_GET['per_page'] ?? 25);

// Build filter
$filter = [];
if ($statusFilter !== 'all') {
    $filter['status'] = $statusFilter;
}
if ($typeFilter !== 'all') {
    $filter['entry_type'] = $typeFilter;
}
if ($startDate) {
    $filter['start_date'] = $startDate;
}
if ($endDate) {
    $filter['end_date'] = $endDate;
}
if ($searchQuery) {
    $filter['search'] = $searchQuery;
}

// Get all entries
$result = $accountingController->getAllJournalEntries($filter);
$allEntries = $result['success'] ? $result['data'] : [];

// Sort entries
usort($allEntries, function($a, $b) use ($sortBy, $sortOrder) {
    if ($sortBy === 'entry_date') {
        $aVal = $a['entry_date']->toDateTime()->getTimestamp();
        $bVal = $b['entry_date']->toDateTime()->getTimestamp();
    } elseif ($sortBy === 'entry_number') {
        $aVal = $a['entry_number'];
        $bVal = $b['entry_number'];
    } elseif ($sortBy === 'total_debit') {
        $aVal = $a['total_debit'];
        $bVal = $b['total_debit'];
    } else {
        $aVal = $a[$sortBy] ?? '';
        $bVal = $b[$sortBy] ?? '';
    }
    
    $comparison = $aVal <=> $bVal;
    return $sortOrder === 'desc' ? -$comparison : $comparison;
});

// Pagination
$totalEntries = count($allEntries);
$totalPages = ceil($totalEntries / $perPage);
$page = min($page, max(1, $totalPages));
$offset = ($page - 1) * $perPage;
$entries = array_slice($allEntries, $offset, $perPage);

// Get accounts for filtering
$accountsResult = $accountingController->getAllAccounts();
$accounts = $accountsResult['success'] ? $accountsResult['data'] : [];

// Set page variables
$pageTitle = 'Journal Entries';

// Start output buffering for content
ob_start();
?>

<?php
// Calculate stats
$stats = [
    'total' => $totalEntries,
    'draft' => count(array_filter($allEntries, fn($e) => $e['status'] === JournalEntry::STATUS_DRAFT)),
    'posted' => count(array_filter($allEntries, fn($e) => $e['status'] === JournalEntry::STATUS_POSTED)),
    'void' => count(array_filter($allEntries, fn($e) => $e['status'] === JournalEntry::STATUS_VOID)),
    'total_debits' => array_sum(array_column($allEntries, 'total_debit')),
    'total_credits' => array_sum(array_column($allEntries, 'total_credit'))
];

// Flash message
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<style>
.je-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.je-stat-card { background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
.je-stat-label { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem; }
.je-stat-value { font-size: 1.5rem; font-weight: 600; font-family: monospace; }
.je-toolbar { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1.5rem; }
.je-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
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
.data-table col.col-entry-number { width: 120px; }
.data-table col.col-date { width: 110px; }
.data-table col.col-type { width: 85px; }
.data-table col.col-description { width: 90px; } /* Reduced to bring Debit closer */
.data-table col.col-debit { width: 90px; } /* Same as Date */
.data-table col.col-credit { width: 110px; } /* Same as Date */
.data-table col.col-status { width: 90px; }
.data-table col.col-actions { width: 220px; }

/* Truncate long descriptions */
.data-table td.col-description {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

<!-- Page Header -->
<div class="content-header">
  <div>
    <nav class="breadcrumb">
      <a href="dashboard" class="breadcrumb-link">Dashboard</a>
      <span class="breadcrumb-separator">/</span>
      <a href="#" class="breadcrumb-link">Accounting</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current">Journal Entries</span>
    </nav>
    <h1 class="content-title">Journal Entries (<?php echo number_format($stats['total']); ?>)</h1>
  </div>
  <div class="content-actions">
    <button class="btn btn-secondary" onclick="showImportModal()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15M7 10L12 15M12 15L17 10M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Import
    </button>
    <button class="btn btn-secondary" onclick="exportData()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Export
    </button>
    <a href="journal-entry-form?new=1" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      New Entry
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
<div class="je-stats-grid">
  <div class="je-stat-card"><div class="je-stat-label">Total Entries</div><div class="je-stat-value"><?php echo number_format($stats['total']); ?></div></div>
  <div class="je-stat-card"><div class="je-stat-label">Posted</div><div class="je-stat-value" style="color: var(--color-success);"><?php echo number_format($stats['posted']); ?></div></div>
  <div class="je-stat-card"><div class="je-stat-label">Draft</div><div class="je-stat-value" style="color: var(--color-warning);"><?php echo number_format($stats['draft']); ?></div></div>
  <div class="je-stat-card"><div class="je-stat-label">Voided</div><div class="je-stat-value" style="color: var(--color-danger);"><?php echo number_format($stats['void']); ?></div></div>
  <div class="je-stat-card"><div class="je-stat-label">Total Debits</div><div class="je-stat-value"><?php echo formatMoney($stats['total_debits'], $currencySymbol); ?></div></div>
  <div class="je-stat-card"><div class="je-stat-label">Total Credits</div><div class="je-stat-value"><?php echo formatMoney($stats['total_credits'], $currencySymbol); ?></div></div>
</div>

<!-- Advanced Filters Toolbar -->
<div class="je-toolbar">
  <form method="GET" id="filter-form">
    <div class="je-filters">
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Search</label><input type="text" name="search" id="je-search" class="form-input" placeholder="Entry #, Description..." value="<?php echo htmlspecialchars($searchQuery); ?>"></div>
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Status</label><select name="status" id="je-status" class="form-select" onchange="applyFilters()"><option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option><option value="<?php echo JournalEntry::STATUS_DRAFT; ?>" <?php echo $statusFilter === JournalEntry::STATUS_DRAFT ? 'selected' : ''; ?>>Draft</option><option value="<?php echo JournalEntry::STATUS_POSTED; ?>" <?php echo $statusFilter === JournalEntry::STATUS_POSTED ? 'selected' : ''; ?>>Posted</option><option value="<?php echo JournalEntry::STATUS_VOID; ?>" <?php echo $statusFilter === JournalEntry::STATUS_VOID ? 'selected' : ''; ?>>Voided</option></select></div>
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Type</label><select name="type" id="je-type" class="form-select" onchange="applyFilters()"><option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option><option value="general" <?php echo $typeFilter === 'general' ? 'selected' : ''; ?>>General</option><option value="sales" <?php echo $typeFilter === 'sales' ? 'selected' : ''; ?>>Sales</option><option value="purchase" <?php echo $typeFilter === 'purchase' ? 'selected' : ''; ?>>Purchase</option><option value="payment" <?php echo $typeFilter === 'payment' ? 'selected' : ''; ?>>Payment</option><option value="receipt" <?php echo $typeFilter === 'receipt' ? 'selected' : ''; ?>>Receipt</option><option value="adjustment" <?php echo $typeFilter === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option></select></div>
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Start Date</label><input type="date" name="start_date" id="je-start-date" class="form-input" value="<?php echo htmlspecialchars($startDate); ?>" onchange="applyFilters()"></div>
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">End Date</label><input type="date" name="end_date" id="je-end-date" class="form-input" value="<?php echo htmlspecialchars($endDate); ?>" onchange="applyFilters()"></div>
      <div><label class="form-label" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Show</label><select name="per_page" id="je-per-page" class="form-select" onchange="applyFilters()"><option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10 per page</option><option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25 per page</option><option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50 per page</option><option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100 per page</option></select></div>
    </div>
    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;"><button type="button" onclick="applyFilters()" class="btn btn-primary btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M21 21L15 15M17 10C17 13.866 13.866 17 10 17C6.13401 17 3 13.866 3 10C3 6.13401 6.13401 3 10 3C13.866 3 17 6.13401 17 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Apply Filters</button><button type="button" onclick="clearFilters()" class="btn btn-secondary btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Clear All</button></div>
  </form>
</div>

<!-- Bulk Actions Banner -->
<div class="bulk-action-banner" id="bulk-banner"><span id="selected-count">0</span> <span>selected</span><button class="btn btn-sm" style="background: white; color: var(--color-primary);" onclick="bulkPost()">Post</button><button class="btn btn-sm" style="background: white; color: var(--color-danger);" onclick="bulkVoid()">Void</button><button class="btn btn-sm" style="background: white; color: var(--text-primary);" onclick="bulkDelete()">Delete</button><button class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white;" onclick="clearSelection()">Clear</button></div>

<?php if (empty($entries)): ?>
<div class="card">
  <div class="card-content">
    <div class="empty-state">
      <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none">
        <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 5C9 6.10457 9.89543 7 11 7H13C14.1046 7 15 6.10457 15 5M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5M9 12H15M9 16H12" stroke="currentColor" stroke-width="2"/>
      </svg>
      <p class="empty-state-title">No Journal Entries Found</p>
      <p class="empty-state-description">Create your first journal entry to record transactions.</p>
      <a href="journal-entry-form?new=1" class="btn btn-primary">Create First Entry</a>
    </div>
  </div>
</div>
<?php else: ?>
<div class="table-container">
  <table class="data-table">
    <colgroup>
      <col class="col-checkbox">
      <col class="col-entry-number">
      <col class="col-date">
      <col class="col-type">
      <col class="col-description">
      <col class="col-debit">
      <col class="col-credit">
      <col class="col-status">
      <col class="col-actions">
    </colgroup>
    <thead>
      <tr>
        <th><input type="checkbox" id="select-all" onchange="toggleSelectAll(this)"></th>
        <th class="sortable-header" onclick="sortTable('entry_number')">Entry # <span class="sort-icon <?php echo $sortBy === 'entry_number' ? 'active' : ''; ?>"><?php echo $sortBy === 'entry_number' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
        <th class="sortable-header" onclick="sortTable('entry_date')">Date <span class="sort-icon <?php echo $sortBy === 'entry_date' ? 'active' : ''; ?>"><?php echo $sortBy === 'entry_date' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
        <th>Type</th>
        <th>Description</th>
        <th class="text-right sortable-header" onclick="sortTable('total_debit')">Debit <span class="sort-icon <?php echo $sortBy === 'total_debit' ? 'active' : ''; ?>"><?php echo $sortBy === 'total_debit' ? ($sortOrder === 'asc' ? '↑' : '↓') : '↕'; ?></span></th>
        <th class="text-right">Credit</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($entries as $entry): 
        $entryDate = $entry['entry_date']->toDateTime()->format('Y-m-d');
        $status = $entry['status'];
        $entryId = (string)$entry['_id'];
      ?>
      <tr>
        <td><input type="checkbox" class="entry-checkbox" value="<?php echo $entryId; ?>" onchange="updateBulkActions()"></td>
        <td class="font-mono font-medium"><a href="journal-entry-detail?id=<?php echo $entryId; ?>" class="text-primary hover:underline"><?php echo htmlspecialchars($entry['entry_number']); ?></a></td>
        <td><?php echo $entryDate; ?></td>
        <td><span class="badge badge-default"><?php echo htmlspecialchars(ucfirst($entry['entry_type'])); ?></span></td>
        <td class="col-description" title="<?php echo htmlspecialchars($entry['description']); ?>"><?php echo htmlspecialchars($entry['description']); ?></td>
        <td class="text-right font-mono"><?php echo formatMoney($entry['total_debit'], $currencySymbol); ?></td>
        <td class="text-right font-mono"><?php echo formatMoney($entry['total_credit'], $currencySymbol); ?></td>
        <td><?php if ($status === JournalEntry::STATUS_DRAFT): ?><span class="badge badge-warning">Draft</span><?php elseif ($status === JournalEntry::STATUS_POSTED): ?><span class="badge badge-success">Posted</span><?php elseif ($status === JournalEntry::STATUS_VOID): ?><span class="badge badge-danger">Void</span><?php endif; ?></td>
        <td><div class="flex gap-1" style="flex-wrap: wrap;"><a href="journal-entry-detail?id=<?php echo $entryId; ?>" class="btn btn-ghost btn-sm" title="View"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z" stroke="currentColor" stroke-width="2"/><path d="M2 12C2 12 5 5 12 5C19 5 22 12 22 12C22 12 19 19 12 19C5 19 2 12 2 12Z" stroke="currentColor" stroke-width="2"/></svg></a><?php if ($status === JournalEntry::STATUS_DRAFT): ?><form method="POST" style="display: inline;"><input type="hidden" name="action" value="post"><input type="hidden" name="entry_id" value="<?php echo $entryId; ?>"><button type="submit" class="btn btn-ghost btn-sm text-success" title="Post" onclick="return confirm('Post this entry?')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button></form><a href="journal-entry-form?copy=<?php echo $entryId; ?>" class="btn btn-ghost btn-sm" title="Copy"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M8 16H6C4.89543 16 4 15.1046 4 14V6C4 4.89543 4.89543 4 6 4H14C15.1046 4 16 4.89543 16 6V8M10 12H18C19.1046 12 20 12.8954 20 14V18C20 19.1046 19.1046 20 18 20H10C8.89543 20 8 19.1046 8 18V14C8 12.8954 8.89543 12 10 12Z" stroke="currentColor" stroke-width="2"/></svg></a><?php endif; ?><?php if ($status === JournalEntry::STATUS_POSTED): ?><button class="btn btn-ghost btn-sm text-danger" title="Void" onclick="voidEntry('<?php echo $entryId; ?>')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button><button class="btn btn-ghost btn-sm" title="Reverse" onclick="reverseEntry('<?php echo $entryId; ?>')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 7V17C3 18.1046 3.89543 19 5 19H19C20.1046 19 21 18.1046 21 17V7M3 7L12 13L21 7M3 7L12 3L21 7" stroke="currentColor" stroke-width="2"/></svg></button><?php endif; ?><button class="btn btn-ghost btn-sm" title="More" onclick="showEntryMenu('<?php echo $entryId; ?>', event)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 13C12.5523 13 13 12.5523 13 12C13 11.4477 12.5523 11 12 11C11.4477 11 11 11.4477 11 12C11 12.5523 11.4477 13 12 13Z" stroke="currentColor" stroke-width="2"/><path d="M12 6C12.5523 6 13 5.55228 13 5C13 4.44772 12.5523 4 12 4C11.4477 4 11 4.44772 11 5C11 5.55228 11.4477 6 12 6Z" stroke="currentColor" stroke-width="2"/><path d="M12 20C12.5523 20 13 19.5523 13 19C13 18.4477 12.5523 18 12 18C11.4477 18 11 18.4477 11 19C11 19.5523 11.4477 20 12 20Z" stroke="currentColor" stroke-width="2"/></svg></button></div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <div><span class="text-sm text-secondary">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalEntries); ?> of <?php echo number_format($totalEntries); ?> entries</span></div>
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

<!-- Void Modal -->
<div class="modal-backdrop" id="void-backdrop" style="display: none;"></div>
<div class="modal" id="void-modal" style="display: none;">
  <div class="modal-header">
    <h3 class="modal-title">Void Journal Entry</h3>
    <button class="modal-close" onclick="closeVoidModal()">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
  <div class="modal-body">
    <form method="POST" id="void-form">
      <input type="hidden" name="action" value="void">
      <input type="hidden" name="entry_id" id="void-entry-id">
      
      <div class="form-group">
        <label for="void_reason" class="form-label">Void Reason <span class="required">*</span></label>
        <textarea 
          id="void_reason" 
          name="void_reason" 
          class="form-input" 
          rows="3"
          placeholder="Enter reason for voiding this entry..."
          required
        ></textarea>
      </div>

      <div class="flex gap-3 mt-4">
        <button type="submit" class="btn btn-danger flex-1">Void Entry</button>
        <button type="button" class="btn btn-secondary flex-1" onclick="closeVoidModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Audit Trail Modal -->
<div class="modal-backdrop" id="audit-backdrop" style="display: none;"></div>
<div class="modal" id="audit-modal" style="display: none; max-width: 700px;">
  <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
    <h3 class="modal-title" style="display: flex; align-items: center; gap: 0.5rem;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
      </svg>
      Audit Trail
    </h3>
    <button class="modal-close" onclick="closeAuditModal()">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
  <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
    <div id="audit-content">
      <!-- Audit trail content will be inserted here -->
    </div>
  </div>
  <div style="padding: 1rem; border-top: 1px solid var(--border-color); text-align: right;">
    <button class="btn btn-primary" onclick="closeAuditModal()">OK</button>
  </div>
</div>

<!-- Print Container (Hidden) -->
<div id="print-container" style="display: none;"></div>

<!-- Duplicate Confirmation Modal -->
<div class="modal-backdrop" id="duplicate-backdrop" style="display: none;"></div>
<div class="modal" id="duplicate-modal" style="display: none; max-width: 500px;">
  <div class="modal-header">
    <h3 class="modal-title" style="display: flex; align-items: center; gap: 0.5rem;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M8 16H6C4.89543 16 4 15.1046 4 14V6C4 4.89543 4.89543 4 6 4H14C15.1046 4 16 4.89543 16 6V8M10 12H18C19.1046 12 20 12.8954 20 14V18C20 19.1046 19.1046 20 18 20H10C8.89543 20 8 19.1046 8 18V14C8 12.8954 8.89543 12 10 12Z" stroke="currentColor" stroke-width="2"/>
      </svg>
      Duplicate Journal Entry
    </h3>
    <button class="modal-close" onclick="closeDuplicateModal()">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
  <div class="modal-body">
    <div id="duplicate-content">
      <div style="padding: 1rem 0;">
        <p style="color: var(--text-primary); margin-bottom: 1rem;">
          This will create a new draft entry with the same transaction lines and details.
        </p>
        <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color);">
          <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="color: var(--color-primary);">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
              <path d="M12 16v-4M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span style="font-weight: 600; font-size: 0.875rem;">What will be duplicated:</span>
          </div>
          <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem; color: var(--text-secondary);">
            <li>Entry type and date</li>
            <li>Reference and description</li>
            <li>All transaction lines (accounts, debits, credits)</li>
            <li>Tags and notes</li>
          </ul>
        </div>
        <div style="background: #fffbeb; padding: 1rem; border-radius: 8px; border: 1px solid #fbbf24; margin-top: 1rem;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="color: #f59e0b;">
              <path d="M12 9V13M12 17H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span style="font-size: 0.875rem; color: #92400e; font-weight: 500;">
              The new entry will be created as a <strong>Draft</strong> with a new entry number.
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="modal-footer" style="display: flex; gap: 0.75rem; padding: 1rem; border-top: 1px solid var(--border-color);">
    <button class="btn btn-secondary flex-1" onclick="closeDuplicateModal()">Cancel</button>
    <button class="btn btn-primary flex-1" id="confirm-duplicate-btn" onclick="confirmDuplicate()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-right: 0.5rem;">
        <path d="M8 16H6C4.89543 16 4 15.1046 4 14V6C4 4.89543 4.89543 4 6 4H14C15.1046 4 16 4.89543 16 6V8M10 12H18C19.1046 12 20 12.8954 20 14V18C20 19.1046 19.1046 20 18 20H10C8.89543 20 8 19.1046 8 18V14C8 12.8954 8.89543 12 10 12Z" stroke="currentColor" stroke-width="2"/>
      </svg>
      Duplicate Entry
    </button>
  </div>
</div>

<style>
.audit-section {
  margin-bottom: 1.5rem;
  padding: 1rem;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
}

.audit-section-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.audit-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-color);
}

.audit-row:last-child {
  border-bottom: none;
}

.audit-label {
  font-size: 0.8125rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.audit-value {
  font-size: 0.8125rem;
  color: var(--text-primary);
  font-weight: 500;
  text-align: right;
}

.audit-timeline {
  position: relative;
  padding-left: 2rem;
}

.audit-timeline-item {
  position: relative;
  padding-bottom: 1.5rem;
}

.audit-timeline-item:last-child {
  padding-bottom: 0;
}

.audit-timeline-item::before {
  content: '';
  position: absolute;
  left: -1.5rem;
  top: 0.5rem;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--color-primary);
  border: 2px solid white;
  box-shadow: 0 0 0 2px var(--color-primary);
}

.audit-timeline-item::after {
  content: '';
  position: absolute;
  left: -1.45rem;
  top: 1.25rem;
  width: 2px;
  height: calc(100% - 0.5rem);
  background: var(--border-color);
}

.audit-timeline-item:last-child::after {
  display: none;
}

.audit-timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.25rem;
}

.audit-timeline-action {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary);
}

.audit-timeline-time {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.audit-timeline-user {
  font-size: 0.8125rem;
  color: var(--text-secondary);
  margin-bottom: 0.25rem;
}

.audit-timeline-details {
  font-size: 0.8125rem;
  color: var(--text-secondary);
  font-style: italic;
}

.audit-info-banner {
  padding: 0.75rem 1rem;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  font-size: 0.8125rem;
  color: var(--text-secondary);
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin-top: 1rem;
}

.audit-info-banner svg {
  flex-shrink: 0;
  opacity: 0.7;
}

/* Modal Footer Styles */
.modal-footer {
  display: flex;
  gap: 0.75rem;
  padding: 1rem;
  border-top: 1px solid var(--border-color);
}

/* Spinning Animation for Loading States */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* Print Styles */
@media print {
  /* Minimize browser headers and footers */
  @page {
    margin: 0.3cm 0.5cm;
    size: auto;
  }
  
  html, body {
    margin: 0 !important;
    padding: 0 !important;
  }
  
  body * {
    visibility: hidden;
  }
  
  #print-container,
  #print-container * {
    visibility: visible;
  }
  
  #print-container {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    display: block !important;
    margin: 0 !important;
    padding: 0.5cm 0 0 0 !important;
  }
  
  .print-header {
    border-bottom: 3px solid #000;
    padding: 1.5rem 0 1rem 0;
    margin-bottom: 2rem;
    page-break-after: avoid;
    margin-top: 0 !important;
  }
  
  .print-title {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.5px;
    margin-bottom: 0.5rem;
  }
  
  .print-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
    margin-bottom: 1rem;
  }
  
  .print-info-item {
    display: flex;
    justify-content: space-between;
  }
  
  .print-label {
    font-weight: 600;
  }
  
  .print-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
  }
  
  .print-table th,
  .print-table td {
    border: 1px solid #000;
    padding: 0.5rem;
    text-align: left;
  }
  
  .print-table th {
    background: #f5f5f5;
    font-weight: 600;
  }
  
  .print-table td.text-right {
    text-align: right;
  }
  
  .print-footer {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #000;
  }
  
  .print-totals {
    display: flex;
    justify-content: flex-end;
    gap: 2rem;
    margin-top: 1rem;
    font-weight: 700;
    font-size: 14px;
  }
}
</style>

<script>
// ============================================
// JOURNAL ENTRIES AJAX (NO PAGE REFRESH)
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

function applyFilters() {
  const urlParams = new URLSearchParams(window.location.search);
  
  urlParams.set('search', document.getElementById('je-search').value);
  urlParams.set('status', document.getElementById('je-status').value);
  urlParams.set('type', document.getElementById('je-type').value);
  urlParams.set('start_date', document.getElementById('je-start-date').value);
  urlParams.set('end_date', document.getElementById('je-end-date').value);
  urlParams.set('per_page', document.getElementById('je-per-page').value);
  urlParams.set('page', '1');
  
  const newUrl = new URL(window.location.href);
  newUrl.search = urlParams.toString();
  window.history.pushState({}, '', newUrl);
  
  location.reload();
}

function clearFilters() {
  window.location.href = 'journal-entries';
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
  
  location.reload();
}

// Search with debounce
const jeSearchInput = document.getElementById('je-search');
if (jeSearchInput) {
  jeSearchInput.addEventListener('input', debounce(function(e) {
    applyFilters();
  }, 500));
}

// ========== BULK SELECTION ========== 
function toggleSelectAll(checkbox) {
  document.querySelectorAll('.entry-checkbox').forEach(cb => cb.checked = checkbox.checked);
  updateBulkActions();
}

function updateBulkActions() {
  const checked = document.querySelectorAll('.entry-checkbox:checked');
  const banner = document.getElementById('bulk-banner');
  const count = document.getElementById('selected-count');
  
  count.textContent = checked.length;
  banner.classList.toggle('show', checked.length > 0);
}

function clearSelection() {
  document.querySelectorAll('.entry-checkbox').forEach(cb => cb.checked = false);
  document.getElementById('select-all').checked = false;
  updateBulkActions();
}

function getSelectedIds() {
  return Array.from(document.querySelectorAll('.entry-checkbox:checked')).map(cb => cb.value);
}

// ========== BULK ACTIONS ==========
function bulkPost() {
  const ids = getSelectedIds();
  if (!ids.length) return;
  
  if (confirm(`Post ${ids.length} selected entries? This will update account balances.`)) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="action" value="bulk_post">`;
    ids.forEach(id => form.innerHTML += `<input type="hidden" name="entry_ids[]" value="${id}">`);
    document.body.appendChild(form);
    form.submit();
  }
}

function bulkVoid() {
  const ids = getSelectedIds();
  if (!ids.length) return;
  
  const reason = prompt(`Void ${ids.length} entries. Enter reason:`);
  if (!reason) return;
  
  const form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = `
    <input type="hidden" name="action" value="bulk_void">
    <input type="hidden" name="bulk_void_reason" value="${reason}">
  `;
  ids.forEach(id => form.innerHTML += `<input type="hidden" name="entry_ids[]" value="${id}">`);
  document.body.appendChild(form);
  form.submit();
}

function bulkDelete() {
  const ids = getSelectedIds();
  if (!ids.length) return;
  
  if (confirm(`DELETE ${ids.length} selected entries? This action cannot be undone!`)) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="action" value="bulk_delete">`;
    ids.forEach(id => form.innerHTML += `<input type="hidden" name="entry_ids[]" value="${id}">`);
    document.body.appendChild(form);
    form.submit();
  }
}

// ========== SORTING ==========
function sortTable(column) {
  const url = new URL(window.location.href);
  const currentSort = url.searchParams.get('sort');
  const currentOrder = url.searchParams.get('order');
  
  if (currentSort === column) {
    url.searchParams.set('order', currentOrder === 'asc' ? 'desc' : 'asc');
  } else {
    url.searchParams.set('sort', column);
    url.searchParams.set('order', 'desc');
  }
  
  window.location.href = url.toString();
}

// ========== VOID MODAL ==========
function voidEntry(entryId) {
  document.getElementById('void-entry-id').value = entryId;
  document.getElementById('void-backdrop').style.display = 'block';
  document.getElementById('void-modal').style.display = 'block';
  document.getElementById('void-modal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeVoidModal() {
  document.getElementById('void-backdrop').style.display = 'none';
  document.getElementById('void-modal').style.display = 'none';
  document.getElementById('void-modal').classList.remove('show');
  document.body.style.overflow = '';
  document.getElementById('void_reason').value = '';
}

document.getElementById('void-backdrop').addEventListener('click', closeVoidModal);

// ========== REVERSE ENTRY ==========
function reverseEntry(entryId) {
  if (confirm('Create a reversing entry? This will create a new entry with opposite debits/credits.')) {
    window.location.href = `journal-entry-form?reverse=${entryId}`;
  }
}

// ========== EXPORT ==========
function exportData() {
  // Show loading toast
  Toast.info('Preparing export...');
  
  // Build URL with current filters
  const params = new URLSearchParams(<?php echo json_encode($_GET); ?>);
  window.location.href = 'api/export-journal-entries?' + params.toString();
  
  // Show success after a delay (file download will start)
  setTimeout(() => {
    Toast.success('Journal entries exported successfully to XLSX!');
  }, 1000);
}

// ========== IMPORT MODAL ==========
function showImportModal() {
  alert('Import CSV Feature:\n\n1. Prepare CSV with columns: date, type, description, account_code, debit, credit\n2. Upload file via import modal\n3. System will validate and create entries\n\nComing soon in full implementation!');
}

// ========== ENTRY MENU ==========
function showEntryMenu(entryId, event) {
  event.stopPropagation();
  
  const menu = document.createElement('div');
  menu.style.cssText = 'position:absolute;background:white;border:1px solid #ddd;border-radius:8px;padding:0.5rem;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:1000;min-width:180px;';
  menu.innerHTML = `
    <button onclick="window.location.href='journal-entry-form?edit=${entryId}'" style="display:block;width:100%;text-align:left;padding:0.5rem;border:none;background:none;cursor:pointer;border-radius:4px;" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='none'">
      ✏️ Edit Entry
    </button>
    <button onclick="duplicateEntry('${entryId}')" style="display:block;width:100%;text-align:left;padding:0.5rem;border:none;background:none;cursor:pointer;border-radius:4px;" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='none'">
      📋 Duplicate
    </button>
    <button onclick="printEntry('${entryId}')" style="display:block;width:100%;text-align:left;padding:0.5rem;border:none;background:none;cursor:pointer;border-radius:4px;" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='none'">
      🖨️ Print
    </button>
    <button onclick="viewAuditTrail('${entryId}')" style="display:block;width:100%;text-align:left;padding:0.5rem;border:none;background:none;cursor:pointer;border-radius:4px;" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='none'">
      📜 Audit Trail
    </button>
    <hr style="margin:0.5rem 0;border:none;border-top:1px solid #eee;">
    <button onclick="deleteEntry('${entryId}')" style="display:block;width:100%;text-align:left;padding:0.5rem;border:none;background:none;cursor:pointer;color:#dc2626;border-radius:4px;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='none'">
      🗑️ Delete
    </button>
  `;
  
  const rect = event.target.getBoundingClientRect();
  const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
  const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
  
  // Append to body first to measure dimensions
  document.body.appendChild(menu);
  
  // Get menu dimensions
  menu.style.visibility = 'hidden';
  const menuRect = menu.getBoundingClientRect();
  menu.style.visibility = 'visible';
  
  // Calculate viewport dimensions
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;
  
  // Calculate positions
  let top = rect.bottom + scrollTop + 5;
  let left = rect.left + scrollLeft - 150;
  
  // Check if menu goes below viewport (bottom of screen)
  if (rect.bottom + menuRect.height + 10 > viewportHeight) {
    // Position above the button instead
    top = rect.top + scrollTop - menuRect.height - 5;
  }
  
  // Check if menu goes beyond right edge
  if (left + menuRect.width > viewportWidth + scrollLeft) {
    left = viewportWidth + scrollLeft - menuRect.width - 20;
  }
  
  // Check if menu goes beyond left edge
  if (left < scrollLeft) {
    left = scrollLeft + 10;
  }
  
  // Position menu
  menu.style.top = top + 'px';
  menu.style.left = left + 'px';
  
  setTimeout(() => {
    document.addEventListener('click', function closeMenu() {
      menu.remove();
      document.removeEventListener('click', closeMenu);
    });
  }, 100);
}

function viewAuditTrail(entryId) {
  // Show the modal
  document.getElementById('audit-backdrop').style.display = 'block';
  document.getElementById('audit-modal').style.display = 'block';
  document.getElementById('audit-modal').classList.add('show');
  document.body.style.overflow = 'hidden';
  
  // Show loading state
  const auditContent = document.getElementById('audit-content');
  auditContent.innerHTML = `
    <div style="text-align: center; padding: 3rem;">
      <div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--color-primary); border-radius: 50%; animation: spin 1s linear infinite;"></div>
      <p style="margin-top: 1rem; color: var(--text-secondary);">Loading audit trail...</p>
    </div>
    <style>
      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
    </style>
  `;
  
  // Fetch actual audit data from database
  fetch(`api/get-audit-trail?entry_id=${entryId}`)
    .then(response => response.json())
    .then(result => {
      if (!result.success) {
        throw new Error(result.message || 'Failed to load audit trail');
      }
      
      const auditData = result.data;
  
  auditContent.innerHTML = `
    <!-- Entry Information -->
    <div class="audit-section">
      <div class="audit-section-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 5C9 6.10457 9.89543 7 11 7H13C14.1046 7 15 6.10457 15 5M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5" stroke="currentColor" stroke-width="2"/>
        </svg>
        Entry Information
      </div>
      <div class="audit-row">
        <span class="audit-label">Entry Number</span>
        <span class="audit-value" style="font-family: monospace; font-weight: 600;">${auditData.entryNumber}</span>
      </div>
      <div class="audit-row">
        <span class="audit-label">Entry ID</span>
        <span class="audit-value" style="font-family: monospace; font-size: 0.75rem;">${auditData.fullEntryId}</span>
      </div>
    </div>
    
    <!-- Creation Details -->
    <div class="audit-section">
      <div class="audit-section-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
        </svg>
        Creation Details
      </div>
      <div class="audit-row">
        <span class="audit-label">Created By</span>
        <span class="audit-value">${auditData.created.user}</span>
      </div>
      <div class="audit-row">
        <span class="audit-label">Created At</span>
        <span class="audit-value">${auditData.created.timestamp}</span>
      </div>
      <div class="audit-row">
        <span class="audit-label">IP Address</span>
        <span class="audit-value" style="font-family: monospace;">${auditData.created.ipAddress}</span>
      </div>
    </div>
    
    <!-- Last Modification Details -->
    <div class="audit-section">
      <div class="audit-section-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Last Modification
      </div>
      <div class="audit-row">
        <span class="audit-label">Modified By</span>
        <span class="audit-value">${auditData.modified.user}</span>
      </div>
      <div class="audit-row">
        <span class="audit-label">Modified At</span>
        <span class="audit-value">${auditData.modified.timestamp}</span>
      </div>
      <div class="audit-row">
        <span class="audit-label">IP Address</span>
        <span class="audit-value" style="font-family: monospace;">${auditData.modified.ipAddress}</span>
      </div>
    </div>
    
    <!-- Modification History Timeline -->
    <div class="audit-section">
      <div class="audit-section-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
        </svg>
        Modification History
      </div>
      <div class="audit-timeline">
        ${auditData.history.map(item => `
          <div class="audit-timeline-item">
            <div class="audit-timeline-header">
              <span class="audit-timeline-action">${item.action}</span>
              <span class="audit-timeline-time">${item.timestamp}</span>
            </div>
            <div class="audit-timeline-user">By ${item.user}</div>
            <div class="audit-timeline-details">${item.details}</div>
          </div>
        `).join('')}
      </div>
    </div>
    
    <!-- Info Banner -->
    <div class="audit-info-banner">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
        <path d="M12 16v-4M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <div>
        <strong>Connected to audit log database</strong><br>
        This audit trail displays real data from the database including actual usernames, timestamps, and all tracked changes for complete transparency and compliance.
      </div>
    </div>
  `;
    })
    .catch(error => {
      console.error('Error loading audit trail:', error);
      auditContent.innerHTML = `
        <div style="text-align: center; padding: 3rem;">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="color: var(--color-danger); margin-bottom: 1rem;">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <p style="color: var(--text-primary); font-weight: 600; margin-bottom: 0.5rem;">Failed to Load Audit Trail</p>
          <p style="color: var(--text-secondary); font-size: 0.875rem;">${error.message}</p>
          <button class="btn btn-secondary" style="margin-top: 1rem;" onclick="viewAuditTrail('${entryId}')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-right: 0.5rem;">
              <path d="M1 4v6h6M23 20v-6h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Retry
          </button>
        </div>
      `;
    });
}

function closeAuditModal() {
  document.getElementById('audit-backdrop').style.display = 'none';
  document.getElementById('audit-modal').style.display = 'none';
  document.getElementById('audit-modal').classList.remove('show');
  document.body.style.overflow = '';
}

// Close audit modal on backdrop click
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('audit-backdrop')?.addEventListener('click', closeAuditModal);
});

function deleteEntry(entryId) {
  if (confirm('Delete this journal entry? This action cannot be undone!')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="entry_id" value="${entryId}">
    `;
    document.body.appendChild(form);
    form.submit();
  }
}

// ========== DUPLICATE ENTRY ==========
let currentDuplicateEntryId = null;

function duplicateEntry(entryId) {
  currentDuplicateEntryId = entryId;
  showDuplicateModal();
}

function showDuplicateModal() {
  document.getElementById('duplicate-backdrop').style.display = 'block';
  document.getElementById('duplicate-modal').style.display = 'block';
  document.getElementById('duplicate-modal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeDuplicateModal() {
  document.getElementById('duplicate-backdrop').style.display = 'none';
  document.getElementById('duplicate-modal').style.display = 'none';
  document.getElementById('duplicate-modal').classList.remove('show');
  document.body.style.overflow = '';
  currentDuplicateEntryId = null;
}

function confirmDuplicate() {
  if (!currentDuplicateEntryId) return;
  
  // Show loading state on button
  const btn = document.getElementById('confirm-duplicate-btn');
  const originalText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = `
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-right: 0.5rem; animation: spin 1s linear infinite;">
      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-opacity="0.25"/>
      <path d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    Duplicating...
  `;
  
  // Redirect to form with copy parameter
  setTimeout(() => {
    window.location.href = `journal-entry-form.php?copy=${currentDuplicateEntryId}`;
  }, 300);
}

// Close duplicate modal on backdrop click
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('duplicate-backdrop')?.addEventListener('click', closeDuplicateModal);
});

// ========== PRINT ENTRY ==========
function printEntry(entryId) {
  const printContainer = document.getElementById('print-container');
  
  // Show loading message
  printContainer.innerHTML = '<p style="padding: 2rem; text-align: center;">Loading entry for print...</p>';
  printContainer.style.display = 'block';
  
  // Fetch entry data for printing
  fetch(`api/get-journal-entry-print?entry_id=${entryId}`)
    .then(response => response.json())
    .then(result => {
      if (!result.success) {
        throw new Error(result.message || 'Failed to load entry');
      }
      
      const entry = result.data;
      const currencySymbol = '<?php echo $currencySymbol; ?>';
      
      // Format money
      function formatMoney(amount) {
        return currencySymbol + parseFloat(amount).toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }
      
      // Build print HTML
      printContainer.innerHTML = `
        <div style="padding: 2rem; max-width: 900px; margin: 0 auto; font-family: system-ui, -apple-system, sans-serif;">
          <!-- Comprehensive Header -->
          <div class="print-header" style="padding: 1.5rem 0 1rem 0; margin-bottom: 2rem; border-bottom: 3px solid #000;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
              <div>
                <div style="font-size: 28px; font-weight: 700; letter-spacing: -0.5px; margin-bottom: 0.25rem;">JOURNAL ENTRY</div>
                <div style="font-size: 14px; color: #666; font-weight: 500;">Inventory Management System</div>
              </div>
              <div style="text-align: right;">
                <div style="font-size: 20px; font-weight: 700; font-family: monospace; color: #000;">${entry.entry_number}</div>
                <div style="font-size: 12px; color: #666; margin-top: 0.25rem;">${entry.status.toUpperCase()}</div>
              </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; padding-top: 0.75rem; border-top: 1px solid #e5e5e5; font-size: 13px;">
              <div><span style="font-weight: 600; color: #666;">Date:</span> <span style="font-weight: 600;">${entry.entry_date}</span></div>
              <div style="text-align: right;"><span style="font-weight: 600; color: #666;">Type:</span> <span style="font-weight: 600;">${entry.entry_type}</span></div>
            </div>
          </div>
          
          <!-- Additional Information -->
          ${entry.reference ? `
            <div style="margin-bottom: 1rem; padding: 0.75rem; background: #f9f9f9; border-left: 3px solid #666;">
              <span style="font-weight: 600; color: #666; font-size: 13px;">Reference:</span>
              <span style="font-weight: 600; font-size: 14px; margin-left: 0.5rem;">${entry.reference}</span>
            </div>
          ` : ''}
          
          ${entry.description ? `
            <div style="margin: 1rem 0;">
              <div class="print-label">Description:</div>
              <div style="padding: 0.5rem; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 4px;">
                ${entry.description}
              </div>
            </div>
          ` : ''}
          
          <!-- Transaction Lines -->
          <table class="print-table">
            <thead>
              <tr>
                <th style="width: 15%;">Account Code</th>
                <th style="width: 30%;">Account Name</th>
                <th style="width: 30%;">Description</th>
                <th style="width: 12.5%; text-align: right;">Debit</th>
                <th style="width: 12.5%; text-align: right;">Credit</th>
              </tr>
            </thead>
            <tbody>
              ${entry.lines.map(line => `
                <tr>
                  <td style="font-family: monospace;">${line.account_code}</td>
                  <td>${line.account_name}</td>
                  <td style="font-size: 0.9em; color: #666;">${line.description || '-'}</td>
                  <td class="text-right" style="font-family: monospace;">
                    ${line.debit > 0 ? formatMoney(line.debit) : '-'}
                  </td>
                  <td class="text-right" style="font-family: monospace;">
                    ${line.credit > 0 ? formatMoney(line.credit) : '-'}
                  </td>
                </tr>
              `).join('')}
              <tr style="font-weight: 700; background: #f5f5f5;">
                <td colspan="3" style="text-align: right; padding-right: 1rem;">TOTALS:</td>
                <td class="text-right" style="font-family: monospace; border-top: 2px solid #000;">
                  ${formatMoney(entry.total_debit)}
                </td>
                <td class="text-right" style="font-family: monospace; border-top: 2px solid #000;">
                  ${formatMoney(entry.total_credit)}
                </td>
              </tr>
            </tbody>
          </table>
          
          ${entry.tags && entry.tags.length > 0 ? `
            <div style="margin: 1rem 0;">
              <span class="print-label">Tags:</span>
              ${entry.tags.map(tag => `<span style="display: inline-block; padding: 0.25rem 0.5rem; margin: 0.25rem; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; font-size: 0.875rem;">${tag}</span>`).join('')}
            </div>
          ` : ''}
          
          ${entry.notes ? `
            <div style="margin: 1rem 0;">
              <div class="print-label">Notes:</div>
              <div style="padding: 0.5rem; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 4px; font-size: 0.9em;">
                ${entry.notes}
              </div>
            </div>
          ` : ''}
          
          <!-- Footer -->
          <div class="print-footer">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; font-size: 0.875rem;">
              <div>
                <div class="print-label">Created By:</div>
                <div>${entry.created_by}</div>
                <div style="color: #666;">${entry.created_at}</div>
              </div>
              <div style="text-align: right;">
                <div class="print-label">Printed:</div>
                <div>${new Date().toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'})}</div>
                <div style="color: #666;">${new Date().toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}</div>
              </div>
            </div>
          </div>
          
          <!-- Balance Check -->
          ${entry.total_debit !== entry.total_credit ? `
            <div style="margin-top: 1rem; padding: 0.75rem; background: #fef2f2; border: 1px solid #dc2626; border-radius: 4px; color: #dc2626; font-weight: 600;">
              ⚠️ Warning: Entry is not balanced! Debit and Credit totals do not match.
            </div>
          ` : ''}
        </div>
      `;
      
      // Update document title for print header (empty to minimize browser header)
      const originalTitle = document.title;
      document.title = ' ';
      
      // Trigger print dialog
      setTimeout(() => {
        // Note: Browser headers can only be hidden via browser print settings
        // Users should disable "Headers and footers" in print dialog
        window.print();
        // Restore original title and hide print container
        document.title = originalTitle;
        printContainer.style.display = 'none';
      }, 100);
    })
    .catch(error => {
      console.error('Error loading entry for print:', error);
      alert('Failed to load entry for printing: ' + error.message);
      printContainer.style.display = 'none';
    });
}

// ========== KEYBOARD SHORTCUTS ==========
document.addEventListener('keydown', function(e) {
  // Ctrl/Cmd + N = New Entry
  if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
    e.preventDefault();
    window.location.href = 'journal-entry-form?new=1';
  }
  
  // Ctrl/Cmd + E = Export
  if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
    e.preventDefault();
    exportData();
  }
  
  // Esc = Clear selection
  if (e.key === 'Escape') {
    clearSelection();
  }
});

console.log('%c✅ Journal Entries - Comprehensive System', 'color: #10b981; font-size: 16px; font-weight: bold;');
console.log('%cFeatures: Advanced Filtering • Bulk Actions • Export/Import • Sorting • Pagination • Audit Trail', 'color: #6b7280; font-size: 12px;');
console.log('%cKeyboard Shortcuts: Ctrl+N (New) • Ctrl+E (Export) • Esc (Clear Selection)', 'color: #6b7280; font-size: 12px;');
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
