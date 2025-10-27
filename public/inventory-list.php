<?php
/**
 * Inventory List Page
 * Professional data table with search and filtering
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Inventory;
use App\Helper\CurrencyHelper;
use App\Helper\NotificationHelper;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();
$inventoryModel = new Inventory();

// Get notification summary for inventory alerts
$userId = $user['id'] ?? 'admin';
$notificationSummary = NotificationHelper::getSummary($userId);
$inventoryAlerts = $notificationSummary['by_type']['inventory'] ?? 0;

// Get filter and pagination parameters
$searchQuery = $_GET['search'] ?? '';
$filterType = $_GET['filter'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'asc';
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = (int)($_GET['per_page'] ?? 6);
$viewMode = $_GET['view'] ?? 'table'; // table or grid

// Get ALL inventory items first (for total count)
try {
    if (!empty($searchQuery)) {
        $allItems = $inventoryModel->search($searchQuery);
    } elseif ($filterType === 'low_stock') {
        $allItems = $inventoryModel->getLowStock(5);
    } elseif ($filterType === 'out_of_stock') {
        $allItems = $inventoryModel->getLowStock(0);
    } else {
        $allItems = $inventoryModel->getAll();
    }
    
    // Apply filters
    if ($categoryFilter !== 'all') {
        $allItems = array_filter($allItems, fn($item) => ($item['type'] ?? '') === $categoryFilter);
    }
    
    if ($statusFilter !== 'all') {
        $allItems = array_filter($allItems, function($item) use ($statusFilter) {
            $qty = $item['quantity'] ?? 0;
            if ($statusFilter === 'in_stock') return $qty > 5;
            if ($statusFilter === 'low_stock') return $qty > 0 && $qty <= 5;
            if ($statusFilter === 'out_of_stock') return $qty == 0;
            return true;
        });
    }
    
    // Sorting with proper field mapping
    usort($allItems, function($a, $b) use ($sortBy, $sortOrder) {
        // Map sort field to actual data
        switch($sortBy) {
            case 'barcode':
            case 'sku':
                $aVal = $a['barcode'] ?? '';
                $bVal = $b['barcode'] ?? '';
                break;
            case 'name':
                $aVal = $a['name'] ?? '';
                $bVal = $b['name'] ?? '';
                break;
            case 'category':
            case 'type':
                $aVal = $a['type'] ?? '';
                $bVal = $b['type'] ?? '';
                break;
            case 'price':
                $aVal = (float)($a['price'] ?? 0);
                $bVal = (float)($b['price'] ?? 0);
                break;
            case 'quantity':
            case 'stock':
                $aVal = (int)($a['quantity'] ?? 0);
                $bVal = (int)($b['quantity'] ?? 0);
                break;
            case 'status':
                // Sort by stock status (out->low->in)
                $aQty = (int)($a['quantity'] ?? 0);
                $bQty = (int)($b['quantity'] ?? 0);
                $aStatus = $aQty == 0 ? 0 : ($aQty <= 5 ? 1 : 2);
                $bStatus = $bQty == 0 ? 0 : ($bQty <= 5 ? 1 : 2);
                $aVal = $aStatus;
                $bVal = $bStatus;
                break;
            case 'value':
                $aVal = ((int)($a['quantity'] ?? 0)) * ((float)($a['price'] ?? 0));
                $bVal = ((int)($b['quantity'] ?? 0)) * ((float)($b['price'] ?? 0));
                break;
            case 'date':
            case 'updated':
                $aVal = isset($a['date_added']) ? $a['date_added']->toDateTime()->getTimestamp() : 0;
                $bVal = isset($b['date_added']) ? $b['date_added']->toDateTime()->getTimestamp() : 0;
                break;
            default:
                $aVal = $a[$sortBy] ?? '';
                $bVal = $b[$sortBy] ?? '';
        }
        
        $result = $aVal <=> $bVal;
        return $sortOrder === 'desc' ? -$result : $result;
    });
    
    // Calculate totals
    $totalValue = 0;
    $totalQuantity = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;
    foreach ($allItems as $item) {
        $qty = $item['quantity'] ?? 0;
        $price = (float)($item['price'] ?? 0);
        $totalValue += $qty * $price;
        $totalQuantity += $qty;
        if ($qty == 0) $outOfStockCount++;
        elseif ($qty <= 5) $lowStockCount++;
    }
    
    // Calculate pagination
    $totalItems = count($allItems);
    $totalPages = max(1, ceil($totalItems / $itemsPerPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Get items for current page
    $items = array_slice($allItems, $offset, $itemsPerPage);
} catch (Exception $e) {
    $items = [];
    $allItems = [];
    $totalItems = 0;
    $totalPages = 1;
    $totalValue = 0;
    $totalQuantity = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $categories = [];
    $error = $e->getMessage();
}

// Set page variables
$pageTitle = 'Inventory List';

// Get unique categories from all items
$categories = array_unique(array_column($allItems, 'type'));
sort($categories);

// Start output buffering for content
ob_start();
?>

<style>
/* Inventory Color Theme - Blue-Gray #7194A5 */
:root {
  --inventory-primary: #7194A5;
  --inventory-primary-hover: #5d7a8a;
  --inventory-primary-light: #e8eef1;
  --inventory-border: #d1dce2;
  --inventory-accent: #4a90e2;
}

/* Shadcn Table Styles */
.inventory-table-container {
  width: 100%;
  max-width: 1600px;
  margin: 0 auto;
  background: var(--bg-primary);
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.inventory-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.875rem;
}

.inventory-table thead tr {
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-secondary);
}

.inventory-table th {
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  font-size: 0.8125rem;
  letter-spacing: 0.01em;
}

.inventory-table tbody tr {
  border-bottom: 1px solid var(--border-color);
  transition: background-color 0.15s ease;
}

.inventory-table tbody tr:hover {
  background: var(--bg-hover, hsl(0 0% 0% / 0.02));
}

.inventory-table tbody tr:last-child {
  border-bottom: none;
}

.inventory-table td {
  padding: 0.875rem 1rem;
  white-space: nowrap;
  color: var(--text-primary);
}

/* Shadcn Badge Styles */
.inventory-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid transparent;
  padding: 0.25rem 0.625rem;
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.inventory-badge-success {
  background: hsl(143 85% 96%);
  color: hsl(140 61% 13%);
  border-color: hsl(143 85% 90%);
}

.inventory-badge-warning {
  background: hsl(48 96% 89%);
  color: hsl(25 95% 16%);
  border-color: hsl(48 96% 80%);
}

.inventory-badge-danger {
  background: hsl(0 86% 97%);
  color: hsl(0 74% 24%);
  border-color: hsl(0 86% 90%);
}

.inventory-badge-primary {
  background: var(--inventory-primary-light);
  color: var(--inventory-primary);
  border-color: var(--inventory-border);
}

/* Stats Cards */
.inventory-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.inventory-stat-card {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 1.25rem;
  transition: all 0.2s ease;
}

.inventory-stat-card:hover {
  border-color: var(--inventory-primary);
  box-shadow: 0 4px 12px rgba(113, 148, 165, 0.1);
}

.stat-label {
  font-size: 0.8125rem;
  color: var(--text-secondary);
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--inventory-primary);
  line-height: 1.2;
}

.stat-change {
  font-size: 0.75rem;
  margin-top: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

/* Filter Bar */
.inventory-filter-bar {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 1rem;
  margin-bottom: 1.5rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.filter-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-secondary);
  white-space: nowrap;
}

/* Bulk Actions Bar */
.bulk-actions-bar {
  background: var(--inventory-primary-light);
  border: 1px solid var(--inventory-border);
  border-radius: var(--radius-md);
  padding: 0.875rem 1rem;
  margin-bottom: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* Quick Actions */
.quick-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--inventory-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.quick-action-btn:hover {
  background: var(--inventory-primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(113, 148, 165, 0.2);
}

/* Stock Level Indicators */
.stock-level-bar {
  width: 60px;
  height: 6px;
  background: var(--bg-secondary);
  border-radius: 3px;
  overflow: hidden;
  position: relative;
}

.stock-level-fill {
  height: 100%;
  transition: width 0.3s ease;
}

.stock-level-fill.high {
  background: hsl(143 85% 50%);
}

.stock-level-fill.medium {
  background: hsl(48 96% 50%);
}

.stock-level-fill.low {
  background: hsl(0 86% 60%);
}

/* Sortable Column Headers */
.sortable-header {
  cursor: pointer;
  user-select: none;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.sortable-header:hover {
  color: var(--inventory-primary);
}

.sortable-header.active {
  color: var(--inventory-primary);
  font-weight: 700;
}

.sort-icon {
  display: inline-flex;
  flex-direction: column;
  width: 12px;
  height: 16px;
  opacity: 0.3;
  transition: opacity 0.2s ease;
}

.sortable-header:hover .sort-icon {
  opacity: 0.6;
}

.sortable-header.active .sort-icon {
  opacity: 1;
}

.sort-arrow {
  width: 0;
  height: 0;
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
}

.sort-arrow.up {
  border-bottom: 6px solid currentColor;
  margin-bottom: 1px;
}

.sort-arrow.down {
  border-top: 6px solid currentColor;
  margin-top: 1px;
}

.sortable-header.active.asc .sort-arrow.up {
  opacity: 1;
}

.sortable-header.active.asc .sort-arrow.down {
  opacity: 0.2;
}

.sortable-header.active.desc .sort-arrow.up {
  opacity: 0.2;
}

.sortable-header.active.desc .sort-arrow.down {
  opacity: 1;
}

/* Loading State */
.inventory-loading {
  position: relative;
  pointer-events: none;
  opacity: 0.6;
}

.inventory-loading::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 24px;
  height: 24px;
  margin: -12px 0 0 -12px;
  border: 3px solid var(--inventory-primary-light);
  border-top-color: var(--inventory-primary);
  border-radius: 50%;
  animation: inventory-spin 0.8s linear infinite;
}

@keyframes inventory-spin {
  to { transform: rotate(360deg); }
}

.filter-loading {
  opacity: 0.6;
  pointer-events: none;
}

/* Smooth transitions */
.inventory-table tbody {
  transition: opacity 0.2s ease;
}

.inventory-table.updating tbody {
  opacity: 0.4;
}

/* Responsive */
@media (max-width: 1400px) {
  .inventory-table-container {
    max-width: 100%;
  }
}

@media (max-width: 768px) {
  .inventory-stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .inventory-filter-bar {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>

<!-- Compact Header with Breadcrumb -->
<div class="content-header" style="margin-bottom: 1.5rem;">
  <div>
    <nav class="breadcrumb">
      <a href="dashboard.php" class="breadcrumb-link">Dashboard</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current">Inventory</span>
    </nav>
    <h1 class="content-title" style="color: var(--inventory-primary);">📦 Inventory Management</h1>
  </div>
  <div class="content-actions">
    <button class="btn btn-ghost btn-sm" onclick="toggleBulkMode()" id="bulk-mode-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
      </svg>
      Bulk Select
    </button>
    <button class="btn btn-secondary btn-sm" onclick="window.print()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M6 9V2H18V9M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18M6 14H18V22H6V14Z" stroke="currentColor" stroke-width="2"/>
      </svg>
      Print
    </button>
    <button class="btn btn-secondary btn-sm" onclick="exportInventory()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Export
    </button>
    <a href="add_item.php" class="btn btn-sm" style="background: var(--inventory-primary); color: white; border: none;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Add Item
    </a>
  </div>
</div>

<!-- Stats Overview (Xero/QuickBooks Style) -->
<div class="inventory-stats-grid">
  <div class="inventory-stat-card">
    <div class="stat-label">Total Items</div>
    <div class="stat-value"><?php echo number_format($totalItems); ?></div>
    <div class="stat-change" style="color: var(--text-secondary);">
      <span><?php echo count($items); ?> on this page</span>
    </div>
  </div>
  
  <div class="inventory-stat-card">
    <div class="stat-label">Total Quantity</div>
    <div class="stat-value"><?php echo number_format($totalQuantity); ?></div>
    <div class="stat-change" style="color: var(--text-secondary);">
      <span>units in stock</span>
    </div>
  </div>
  
  <div class="inventory-stat-card">
    <div class="stat-label">Inventory Value</div>
    <div class="stat-value"><?php echo CurrencyHelper::format($totalValue); ?></div>
    <div class="stat-change" style="color: var(--text-secondary);">
      <span><?php echo CurrencyHelper::symbol(); ?> total worth</span>
    </div>
  </div>
  
  <div class="inventory-stat-card">
    <div class="stat-label">Stock Alerts</div>
    <div class="stat-value" style="color: <?php echo ($lowStockCount + $outOfStockCount) > 0 ? 'hsl(25 95% 16%)' : 'hsl(143 85% 30%)'; ?>;">
      <?php echo $lowStockCount + $outOfStockCount; ?>
    </div>
    <div class="stat-change" style="color: var(--text-secondary);">
      <span><?php echo $lowStockCount; ?> low, <?php echo $outOfStockCount; ?> out</span>
    </div>
  </div>
</div>

<!-- Advanced Filter Bar (Xero/QuickBooks/LedgerSMB Style) -->
<div class="inventory-filter-bar">
  <!-- Search -->
  <div class="filter-group" style="flex: 1; min-width: 300px;">
    <div class="search-wrapper" style="width: 100%;">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input 
        type="search" 
        class="search-input" 
        placeholder="Search items... (name, barcode, SKU)"
        id="inventory-search"
        value="<?php echo htmlspecialchars($searchQuery); ?>"
        style="color: var(--text-primary); background-color: var(--bg-secondary);"
      >
    </div>
  </div>
  
  <!-- Status Filter -->
  <div class="filter-group">
    <span class="filter-label">Status:</span>
    <select class="form-select" id="status-filter" style="width: auto; min-width: 140px;">
      <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
      <option value="in_stock" <?php echo $statusFilter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
      <option value="low_stock" <?php echo $statusFilter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
      <option value="out_of_stock" <?php echo $statusFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
    </select>
  </div>
  
  <!-- Category Filter -->
  <?php if (!empty($categories)): ?>
  <div class="filter-group">
    <span class="filter-label">Category:</span>
    <select class="form-select" id="category-filter" style="width: auto; min-width: 140px;">
      <option value="all" <?php echo $categoryFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($cat); ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  
  <!-- Sort By -->
  <div class="filter-group">
    <span class="filter-label">Sort:</span>
    <select class="form-select" id="sort-by" style="width: auto; min-width: 150px;">
      <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Item Name</option>
      <option value="sku" <?php echo $sortBy === 'sku' ? 'selected' : ''; ?>>SKU/Barcode</option>
      <option value="category" <?php echo $sortBy === 'category' ? 'selected' : ''; ?>>Category</option>
      <option value="price" <?php echo $sortBy === 'price' ? 'selected' : ''; ?>>Price</option>
      <option value="stock" <?php echo $sortBy === 'stock' ? 'selected' : ''; ?>>Stock Level</option>
      <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
      <option value="value" <?php echo $sortBy === 'value' ? 'selected' : ''; ?>>Value</option>
      <option value="updated" <?php echo $sortBy === 'updated' ? 'selected' : ''; ?>>Last Updated</option>
    </select>
  </div>
  
  <!-- Sort Order -->
  <div class="filter-group">
    <select class="form-select" id="sort-order" style="width: auto; min-width: 100px;">
      <option value="asc" <?php echo $sortOrder === 'asc' ? 'selected' : ''; ?>>↑ Ascending</option>
      <option value="desc" <?php echo $sortOrder === 'desc' ? 'selected' : ''; ?>>↓ Descending</option>
    </select>
  </div>
  
  <!-- Per Page -->
  <div class="filter-group">
    <span class="filter-label">Show:</span>
    <select class="form-select" id="per-page" style="width: auto; min-width: 80px;">
      <option value="6" <?php echo $itemsPerPage === 6 ? 'selected' : ''; ?>>6</option>
      <option value="10" <?php echo $itemsPerPage === 10 ? 'selected' : ''; ?>>10</option>
      <option value="25" <?php echo $itemsPerPage === 25 ? 'selected' : ''; ?>>25</option>
      <option value="50" <?php echo $itemsPerPage === 50 ? 'selected' : ''; ?>>50</option>
      <option value="100" <?php echo $itemsPerPage === 100 ? 'selected' : ''; ?>>100</option>
    </select>
  </div>
  
  <!-- Clear Filters -->
  <?php if ($statusFilter !== 'all' || $categoryFilter !== 'all' || !empty($searchQuery)): ?>
  <a href="inventory-list.php" class="btn btn-ghost btn-sm" style="color: var(--inventory-primary);">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
      <path d="M6 18L18 6M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Clear
  </a>
  <?php endif; ?>
</div>

<!-- Bulk Actions Bar (Hidden by default) -->
<div class="bulk-actions-bar" id="bulk-actions-bar" style="display: none;">
  <div style="display: flex; align-items: center; gap: 1rem;">
    <input type="checkbox" id="select-all-checkbox" onclick="toggleSelectAll()" style="width: 18px; height: 18px; cursor: pointer;">
    <span style="font-weight: 600; color: var(--inventory-primary);" id="selected-count">0 items selected</span>
  </div>
  <div style="display: flex; gap: 0.5rem;">
    <button class="btn btn-sm" style="background: var(--inventory-primary); color: white; border: none;" onclick="bulkEdit()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2"/>
        <path d="M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/>
      </svg>
      Bulk Edit
    </button>
    <button class="btn btn-secondary btn-sm" onclick="bulkDelete()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2"/>
      </svg>
      Delete
    </button>
    <button class="btn btn-ghost btn-sm" onclick="toggleBulkMode()">
      Cancel
    </button>
  </div>
</div>

<!-- Inventory Table -->
<?php if (isset($error)): ?>
<div class="alert alert-danger" style="margin-bottom: 1.5rem;">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
    <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
  </svg>
  <span><?php echo htmlspecialchars($error); ?></span>
</div>
<?php endif; ?>

<?php if (empty($items)): ?>
<div class="card">
  <div class="card-content">
    <div class="empty-state">
      <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none">
        <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 5C9 6.10457 9.89543 7 11 7H13C14.1046 7 15 6.10457 15 5M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5M9 12H15M9 16H12" stroke="currentColor" stroke-width="2"/>
      </svg>
      <p class="empty-state-title">
        <?php 
        if (!empty($searchQuery)) {
          echo "No items found for \"" . htmlspecialchars($searchQuery) . "\"";
        } elseif ($filterType === 'low_stock') {
          echo "No low stock items";
        } elseif ($filterType === 'out_of_stock') {
          echo "No out of stock items";
        } else {
          echo "No inventory items found";
        }
        ?>
      </p>
      <p class="empty-state-description">
        <?php if (empty($searchQuery) && $filterType === 'all'): ?>
          Start by adding your first inventory item
        <?php else: ?>
          Try adjusting your search or filters
        <?php endif; ?>
      </p>
      <?php if (empty($searchQuery) && $filterType === 'all'): ?>
        <a href="add_item.php" class="btn btn-primary">Add First Item</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>
<div class="inventory-table-container">
  <table class="inventory-table">
    <thead>
      <tr>
        <th style="width: 40px;">
          <input type="checkbox" class="bulk-checkbox" id="header-checkbox" style="display: none; width: 18px; height: 18px; cursor: pointer;" onclick="toggleSelectAll()">
        </th>
        <th style="width: 60px;">#</th>
        <th style="width: 140px;">
          <span class="sortable-header <?php echo $sortBy === 'sku' || $sortBy === 'barcode' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('sku')">
            SKU/Barcode
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="min-width: 200px;">
          <span class="sortable-header <?php echo $sortBy === 'name' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('name')">
            Item Name
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 120px;">
          <span class="sortable-header <?php echo $sortBy === 'category' || $sortBy === 'type' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('category')">
            Category
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 100px;">
          <span class="sortable-header <?php echo $sortBy === 'price' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('price')">
            Price
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 140px;">
          <span class="sortable-header <?php echo $sortBy === 'stock' || $sortBy === 'quantity' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('stock')">
            Stock Level
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 100px;">
          <span class="sortable-header <?php echo $sortBy === 'status' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('status')">
            Status
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 120px;">
          <span class="sortable-header <?php echo $sortBy === 'value' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('value')">
            Value
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 120px;">
          <span class="sortable-header <?php echo $sortBy === 'updated' || $sortBy === 'date' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('updated')">
            Last Updated
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 140px; text-align: center;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $counter = $offset + 1;
      foreach ($items as $item): 
        $itemId = (string)$item['_id'];
        $quantity = $item['quantity'] ?? 0;
        $price = (float)($item['price'] ?? 0);
        $itemValue = $quantity * $price;
        $isLowStock = $quantity > 0 && $quantity <= 5;
        $isOutOfStock = $quantity == 0;
        $dateAdded = isset($item['date_added']) ? $item['date_added']->toDateTime()->format('M d, Y') : 'N/A';
        
        // Stock level percentage (assume max stock is 50 for visual indicator)
        $maxStock = 50;
        $stockPercent = min(100, ($quantity / $maxStock) * 100);
        $stockLevel = $quantity > 5 ? 'high' : ($quantity > 0 ? 'medium' : 'low');
      ?>
      <tr data-item-id="<?php echo $itemId; ?>">
        <td>
          <input type="checkbox" class="bulk-checkbox item-checkbox" data-item-id="<?php echo $itemId; ?>" style="display: none; width: 18px; height: 18px; cursor: pointer;" onclick="updateSelectedCount()">
        </td>
        <td style="color: var(--text-secondary); font-weight: 500;"><?php echo $counter++; ?></td>
        <td>
          <span class="font-mono" style="font-size: 0.8125rem; color: var(--text-secondary);">
            <?php echo htmlspecialchars($item['barcode'] ?? 'SKU-' . $counter); ?>
          </span>
        </td>
        <td>
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 40px; height: 40px; background: var(--inventory-primary-light); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--inventory-primary); font-size: 0.875rem;">
              <?php echo strtoupper(substr($item['name'] ?? 'I', 0, 1)); ?>
            </div>
            <div>
              <div style="font-weight: 600; color: var(--text-primary);">
                <?php echo htmlspecialchars($item['name'] ?? 'Unnamed Item'); ?>
              </div>
              <?php if (!empty($item['lifespan'])): ?>
              <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.125rem;">
                Lifespan: <?php echo htmlspecialchars($item['lifespan']); ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td>
          <span class="inventory-badge inventory-badge-primary">
            <?php echo htmlspecialchars($item['type'] ?? 'General'); ?>
          </span>
        </td>
        <td>
          <span style="font-weight: 600; color: var(--inventory-primary);">
            <?php echo CurrencyHelper::format($price); ?>
          </span>
        </td>
        <td>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-weight: 700; color: var(--text-primary); min-width: 35px;">
              <?php echo $quantity; ?>
            </span>
            <div class="stock-level-bar">
              <div class="stock-level-fill <?php echo $stockLevel; ?>" style="width: <?php echo $stockPercent; ?>%;"></div>
            </div>
          </div>
        </td>
        <td>
          <?php if ($isOutOfStock): ?>
            <span class="inventory-badge inventory-badge-danger">Out of Stock</span>
          <?php elseif ($isLowStock): ?>
            <span class="inventory-badge inventory-badge-warning">Low Stock</span>
          <?php else: ?>
            <span class="inventory-badge inventory-badge-success">In Stock</span>
          <?php endif; ?>
        </td>
        <td>
          <span style="font-weight: 600; color: var(--text-primary);">
            <?php echo CurrencyHelper::format($itemValue); ?>
          </span>
        </td>
        <td style="color: var(--text-secondary); font-size: 0.8125rem;">
          <?php echo $dateAdded; ?>
        </td>
        <td>
          <div style="display: flex; gap: 0.375rem; justify-content: center;">
            <a href="edit_item.php?id=<?php echo $itemId; ?>" 
               class="btn btn-ghost btn-sm" 
               style="padding: 0.375rem 0.5rem;"
               title="Edit Item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2"/>
                <path d="M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </a>
            <button 
              onclick="quickView('<?php echo $itemId; ?>')" 
              class="btn btn-ghost btn-sm" 
              style="padding: 0.375rem 0.5rem; color: var(--inventory-primary);"
              title="Quick View">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button 
              onclick="deleteItem('<?php echo $itemId; ?>', '<?php echo htmlspecialchars($item['name'] ?? 'this item'); ?>')" 
              class="btn btn-ghost btn-sm" 
              style="padding: 0.375rem 0.5rem; color: hsl(0 74% 24%);"
              title="Delete Item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<div class="mt-6" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
  <!-- Summary -->
  <div class="text-sm text-secondary">
    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $itemsPerPage, $totalItems); ?></strong> of <strong><?php echo $totalItems; ?></strong> 
    <?php echo $totalItems === 1 ? 'item' : 'items'; ?>
  </div>
  
  <!-- Pagination Controls (AJAX) -->
  <?php if ($totalPages > 1): ?>
  <div id="pagination-controls" style="display: flex; gap: 0.25rem; align-items: center;">
    <!-- Previous Button -->
    <?php if ($currentPage > 1): ?>
      <button onclick="goToPage(<?php echo $currentPage - 1; ?>)" 
         class="btn btn-ghost btn-sm" 
         style="padding: 0.5rem 0.75rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Previous
      </button>
    <?php else: ?>
      <button class="btn btn-ghost btn-sm" disabled style="padding: 0.5rem 0.75rem; opacity: 0.5;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Previous
      </button>
    <?php endif; ?>
    
    <!-- Page Numbers -->
    <div style="display: flex; gap: 0.25rem;">
      <?php
      $startPage = max(1, $currentPage - 2);
      $endPage = min($totalPages, $currentPage + 2);
      
      // Show first page if not in range
      if ($startPage > 1):
      ?>
        <button onclick="goToPage(1)" 
           class="btn btn-ghost btn-sm" 
           style="min-width: 2.5rem; padding: 0.5rem;">
          1
        </button>
        <?php if ($startPage > 2): ?>
        <span style="padding: 0.5rem; color: var(--text-muted);">...</span>
        <?php endif; ?>
      <?php endif; ?>
      
      <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
        <?php if ($page === $currentPage): ?>
          <button class="btn btn-primary btn-sm" 
                  style="min-width: 2.5rem; padding: 0.5rem; font-weight: 600;">
            <?php echo $page; ?>
          </button>
        <?php else: ?>
          <button onclick="goToPage(<?php echo $page; ?>)" 
             class="btn btn-ghost btn-sm" 
             style="min-width: 2.5rem; padding: 0.5rem;">
            <?php echo $page; ?>
          </button>
        <?php endif; ?>
      <?php endfor; ?>
      
      <!-- Show last page if not in range -->
      <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
        <span style="padding: 0.5rem; color: var(--text-muted);">...</span>
        <?php endif; ?>
        <button onclick="goToPage(<?php echo $totalPages; ?>)" 
           class="btn btn-ghost btn-sm" 
           style="min-width: 2.5rem; padding: 0.5rem;">
          <?php echo $totalPages; ?>
        </button>
      <?php endif; ?>
    </div>
    
    <!-- Next Button -->
    <?php if ($currentPage < $totalPages): ?>
      <button onclick="goToPage(<?php echo $currentPage + 1; ?>)" 
         class="btn btn-ghost btn-sm" 
         style="padding: 0.5rem 0.75rem;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    <?php else: ?>
      <button class="btn btn-ghost btn-sm" disabled style="padding: 0.5rem 0.75rem; opacity: 0.5;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  
  <!-- Clear Filters -->
  <?php if ($filterType !== 'all' || !empty($searchQuery)): ?>
  <div>
    <a href="inventory-list.php" class="text-primary text-sm">Clear filters</a>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
// ============================================
// BULK SELECTION MODE
// ============================================
let bulkModeActive = false;
const selectedItems = new Set();

function toggleBulkMode() {
  bulkModeActive = !bulkModeActive;
  const bulkCheckboxes = document.querySelectorAll('.bulk-checkbox');
  const bulkActionsBar = document.getElementById('bulk-actions-bar');
  const bulkModeBtn = document.getElementById('bulk-mode-btn');
  
  bulkCheckboxes.forEach(cb => {
    cb.style.display = bulkModeActive ? 'inline-block' : 'none';
    if (!bulkModeActive) cb.checked = false;
  });
  
  bulkActionsBar.style.display = bulkModeActive ? 'flex' : 'none';
  bulkModeBtn.textContent = bulkModeActive ? 'Exit Bulk Mode' : 'Bulk Select';
  
  if (!bulkModeActive) {
    selectedItems.clear();
    updateSelectedCount();
  }
}

function toggleSelectAll() {
  const headerCheckbox = document.getElementById('header-checkbox');
  const itemCheckboxes = document.querySelectorAll('.item-checkbox');
  const isChecked = headerCheckbox.checked;
  
  itemCheckboxes.forEach(cb => {
    cb.checked = isChecked;
    const itemId = cb.dataset.itemId;
    if (isChecked) {
      selectedItems.add(itemId);
    } else {
      selectedItems.delete(itemId);
    }
  });
  
  updateSelectedCount();
}

function updateSelectedCount() {
  const itemCheckboxes = document.querySelectorAll('.item-checkbox');
  selectedItems.clear();
  
  itemCheckboxes.forEach(cb => {
    if (cb.checked) selectedItems.add(cb.dataset.itemId);
  });
  
  const count = selectedItems.size;
  const countEl = document.getElementById('selected-count');
  countEl.textContent = `${count} item${count !== 1 ? 's' : ''} selected`;
  
  // Update header checkbox state
  const headerCheckbox = document.getElementById('header-checkbox');
  if (count === 0) {
    headerCheckbox.checked = false;
    headerCheckbox.indeterminate = false;
  } else if (count === itemCheckboxes.length) {
    headerCheckbox.checked = true;
    headerCheckbox.indeterminate = false;
  } else {
    headerCheckbox.checked = false;
    headerCheckbox.indeterminate = true;
  }
}

function bulkEdit() {
  if (selectedItems.size === 0) {
    alert('Please select items to edit');
    return;
  }
  alert(`Bulk editing ${selectedItems.size} items (feature coming soon)`);
}

function bulkDelete() {
  if (selectedItems.size === 0) {
    alert('Please select items to delete');
    return;
  }
  
  if (confirm(`Are you sure you want to delete ${selectedItems.size} item${selectedItems.size !== 1 ? 's' : ''}?`)) {
    alert('Deleting items... (feature coming soon)');
  }
}

// ============================================
// AJAX INVENTORY LOADING (NO PAGE REFRESH)
// ============================================
function loadInventory(params = {}) {
  const url = new URL('/api/inventory.php', window.location.origin);
  const currentParams = new URLSearchParams(window.location.search);
  
  // Merge current params with new ones
  for (const [key, value] of currentParams.entries()) {
    if (!params.hasOwnProperty(key)) {
      params[key] = value;
    }
  }
  
  // Set action
  url.searchParams.set('action', 'list');
  
  // Apply all params
  Object.keys(params).forEach(key => {
    const value = params[key];
    if (value && value !== 'all' && value !== null) {
      url.searchParams.set(key, value);
    } else {
      url.searchParams.delete(key);
    }
  });
  
  // Show loading state
  const tableContainer = document.querySelector('.inventory-table-container');
  const statsGrid = document.querySelector('.inventory-stats-grid');
  if (tableContainer) tableContainer.classList.add('inventory-loading');
  if (statsGrid) statsGrid.classList.add('filter-loading');
  
  // Fetch data
  fetch(url.toString())
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update stats
        updateStats(data.stats);
        
        // Update table
        updateTable(data.items);
        
        // Update pagination
        updatePagination(data.pagination);
        
        // Update URL without reload
        const newUrl = new URL(window.location.href);
        Object.keys(params).forEach(key => {
          if (params[key] && params[key] !== 'all') {
            newUrl.searchParams.set(key, params[key]);
          } else {
            newUrl.searchParams.delete(key);
          }
        });
        window.history.pushState({}, '', newUrl);
      }
    })
    .catch(error => console.error('Error loading inventory:', error))
    .finally(() => {
      // Remove loading state
      if (tableContainer) tableContainer.classList.remove('inventory-loading');
      if (statsGrid) statsGrid.classList.remove('filter-loading');
    });
}

function updateStats(stats) {
  const statCards = document.querySelectorAll('.inventory-stat-card');
  if (statCards[0]) {
    statCards[0].querySelector('.stat-value').textContent = stats.total_items.toLocaleString();
  }
  if (statCards[1]) {
    statCards[1].querySelector('.stat-value').textContent = stats.total_quantity.toLocaleString();
  }
  if (statCards[2]) {
    statCards[2].querySelector('.stat-value').textContent = stats.total_value_formatted;
  }
  if (statCards[3]) {
    const alertCount = stats.low_stock_count + stats.out_of_stock_count;
    statCards[3].querySelector('.stat-value').textContent = alertCount;
    statCards[3].querySelector('.stat-value').style.color = alertCount > 0 ? 'hsl(25 95% 16%)' : 'hsl(143 85% 30%)';
    statCards[3].querySelector('.stat-change span').textContent = `${stats.low_stock_count} low, ${stats.out_of_stock_count} out`;
  }
}

function updateTable(items) {
  const tbody = document.querySelector('.inventory-table tbody');
  if (!tbody) return;
  
  if (items.length === 0) {
    tbody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No items found</td></tr>';
    return;
  }
  
  tbody.innerHTML = items.map(item => `
    <tr data-item-id="${item.id}">
      <td>
        <input type="checkbox" class="bulk-checkbox item-checkbox" data-item-id="${item.id}" style="display: none; width: 18px; height: 18px; cursor: pointer;" onclick="updateSelectedCount()">
      </td>
      <td style="color: var(--text-secondary); font-weight: 500;">${item.counter}</td>
      <td>
        <span class="font-mono" style="font-size: 0.8125rem; color: var(--text-secondary);">${item.barcode}</span>
      </td>
      <td>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <div style="width: 40px; height: 40px; background: var(--inventory-primary-light); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--inventory-primary); font-size: 0.875rem;">
            ${item.avatar_letter}
          </div>
          <div>
            <div style="font-weight: 600; color: var(--text-primary);">${item.name}</div>
            ${item.lifespan ? `<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.125rem;">Lifespan: ${item.lifespan}</div>` : ''}
          </div>
        </div>
      </td>
      <td>
        <span class="inventory-badge inventory-badge-primary">${item.type}</span>
      </td>
      <td>
        <span style="font-weight: 600; color: var(--inventory-primary);">${item.price_formatted}</span>
      </td>
      <td>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <span style="font-weight: 700; color: var(--text-primary); min-width: 35px;">${item.quantity}</span>
          <div class="stock-level-bar">
            <div class="stock-level-fill ${item.stock_level}" style="width: ${item.stock_percent}%;"></div>
          </div>
        </div>
      </td>
      <td>
        ${item.is_out_of_stock ? '<span class="inventory-badge inventory-badge-danger">Out of Stock</span>' : 
          item.is_low_stock ? '<span class="inventory-badge inventory-badge-warning">Low Stock</span>' : 
          '<span class="inventory-badge inventory-badge-success">In Stock</span>'}
      </td>
      <td>
        <span style="font-weight: 600; color: var(--text-primary);">${item.value_formatted}</span>
      </td>
      <td style="color: var(--text-secondary); font-size: 0.8125rem;">${item.date_added}</td>
      <td>
        <div style="display: flex; gap: 0.375rem; justify-content: center;">
          <a href="edit_item.php?id=${item.id}" class="btn btn-ghost btn-sm" style="padding: 0.375rem 0.5rem;" title="Edit Item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2"/><path d="M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/></svg>
          </a>
          <button onclick="quickView('${item.id}')" class="btn btn-ghost btn-sm" style="padding: 0.375rem 0.5rem; color: var(--inventory-primary);" title="Quick View">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
          </button>
          <button onclick="deleteItem('${item.id}', '${item.name}')" class="btn btn-ghost btn-sm" style="padding: 0.375rem 0.5rem; color: hsl(0 74% 24%);" title="Delete Item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function updatePagination(pagination) {
  const paginationContainer = document.querySelector('.mt-6');
  if (!paginationContainer) return;
  
  // Update summary
  const summary = paginationContainer.querySelector('.text-sm');
  if (summary) {
    summary.innerHTML = `Showing <strong>${pagination.showing_from}</strong> to <strong>${pagination.showing_to}</strong> of <strong>${pagination.total_items}</strong> ${pagination.total_items === 1 ? 'item' : 'items'}`;
  }
  
  // Rebuild pagination controls
  const controls = document.getElementById('pagination-controls');
  if (!controls || pagination.total_pages <= 1) {
    if (controls) controls.style.display = 'none';
    return;
  }
  
  controls.style.display = 'flex';
  
  const currentPage = pagination.current_page;
  const totalPages = pagination.total_pages;
  const startPage = Math.max(1, currentPage - 2);
  const endPage = Math.min(totalPages, currentPage + 2);
  
  let html = '';
  
  // Previous button
  if (currentPage > 1) {
    html += `
      <button onclick="goToPage(${currentPage - 1})" class="btn btn-ghost btn-sm" style="padding: 0.5rem 0.75rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Previous
      </button>
    `;
  } else {
    html += `
      <button class="btn btn-ghost btn-sm" disabled style="padding: 0.5rem 0.75rem; opacity: 0.5;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Previous
      </button>
    `;
  }
  
  // Page numbers container
  html += '<div style="display: flex; gap: 0.25rem;">';
  
  // First page if not in range
  if (startPage > 1) {
    html += `<button onclick="goToPage(1)" class="btn btn-ghost btn-sm" style="min-width: 2.5rem; padding: 0.5rem;">1</button>`;
    if (startPage > 2) {
      html += '<span style="padding: 0.5rem; color: var(--text-muted);">...</span>';
    }
  }
  
  // Page number buttons
  for (let page = startPage; page <= endPage; page++) {
    if (page === currentPage) {
      html += `<button class="btn btn-primary btn-sm" style="min-width: 2.5rem; padding: 0.5rem; font-weight: 600;">${page}</button>`;
    } else {
      html += `<button onclick="goToPage(${page})" class="btn btn-ghost btn-sm" style="min-width: 2.5rem; padding: 0.5rem;">${page}</button>`;
    }
  }
  
  // Last page if not in range
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      html += '<span style="padding: 0.5rem; color: var(--text-muted);">...</span>';
    }
    html += `<button onclick="goToPage(${totalPages})" class="btn btn-ghost btn-sm" style="min-width: 2.5rem; padding: 0.5rem;">${totalPages}</button>`;
  }
  
  html += '</div>';
  
  // Next button
  if (currentPage < totalPages) {
    html += `
      <button onclick="goToPage(${currentPage + 1})" class="btn btn-ghost btn-sm" style="padding: 0.5rem 0.75rem;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    `;
  } else {
    html += `
      <button class="btn btn-ghost btn-sm" disabled style="padding: 0.5rem 0.75rem; opacity: 0.5;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    `;
  }
  
  controls.innerHTML = html;
}

// ============================================
// SEARCH & FILTERS (NO TOAST)
// ============================================
const searchInput = document.getElementById('inventory-search');
if (searchInput) {
  searchInput.addEventListener('input', debounce(function(e) {
    const query = e.target.value.trim();
    loadInventory({ search: query || null, page: 1 });
  }, 600));
}

const statusFilter = document.getElementById('status-filter');
if (statusFilter) {
  statusFilter.addEventListener('change', function(e) {
    loadInventory({ status: e.target.value, page: 1 });
  });
}

const categoryFilter = document.getElementById('category-filter');
if (categoryFilter) {
  categoryFilter.addEventListener('change', function(e) {
    loadInventory({ category: e.target.value, page: 1 });
  });
}

const sortBy = document.getElementById('sort-by');
if (sortBy) {
  sortBy.addEventListener('change', function(e) {
    loadInventory({ sort: e.target.value, page: 1 });
  });
}

const sortOrder = document.getElementById('sort-order');
if (sortOrder) {
  sortOrder.addEventListener('change', function(e) {
    loadInventory({ order: e.target.value, page: 1 });
  });
}

const perPage = document.getElementById('per-page');
if (perPage) {
  perPage.addEventListener('change', function(e) {
    loadInventory({ per_page: e.target.value, page: 1 });
  });
}

// ============================================
// PAGINATION (AJAX)
// ============================================
function goToPage(page) {
  loadInventory({ page: page });
}

// ============================================
// TABLE SORTING (NO TOAST, NO REFRESH)
// ============================================
function sortTable(column) {
  const url = new URL(window.location.href);
  const currentSort = url.searchParams.get('sort') || 'name';
  const currentOrder = url.searchParams.get('order') || 'asc';
  
  // Toggle order if clicking same column, otherwise default to asc
  let newOrder = 'asc';
  if (currentSort === column) {
    newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
  }
  
  // Load with new sort order (no page refresh)
  loadInventory({ sort: column, order: newOrder, page: 1 });
}

// ============================================
// QUICK VIEW MODAL (NO TOAST)
// ============================================
function quickView(itemId) {
  console.log('Quick view for item:', itemId);
  // Future: Show modal with item details
}

// ============================================
// DELETE ITEM (NO TOAST)
// ============================================
function deleteItem(id, name) {
  if (confirm(`Delete "${name}"?\n\nThis action cannot be undone.`)) {
    window.location.href = `delete_item.php?id=${id}`;
  }
}

// ============================================
// EXPORT INVENTORY (NO TOAST)
// ============================================
function exportInventory() {
  console.log('Export inventory');
  // Future: Generate CSV/Excel export
}

// ============================================
// UTILITIES
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

// No toast notifications - loading state handled by CSS
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
