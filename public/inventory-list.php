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
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = 10;

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
    
    // Calculate pagination
    $totalItems = count($allItems);
    $totalPages = max(1, ceil($totalItems / $itemsPerPage));
    $currentPage = min($currentPage, $totalPages); // Ensure current page doesn't exceed total
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Get items for current page
    $items = array_slice($allItems, $offset, $itemsPerPage);
} catch (Exception $e) {
    $items = [];
    $allItems = [];
    $totalItems = 0;
    $totalPages = 1;
    $error = $e->getMessage();
}

// Set page variables
$pageTitle = 'Inventory List';

// Start output buffering for content
ob_start();
?>

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Inventory Sheet</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>Status:</strong>
          <span class="status-indicator">
            <span class="status-dot"></span>
            Online
          </span>
        </div>
        <div class="page-banner-meta-item">
          <strong>User:</strong>
          <?php echo htmlspecialchars($user['username'] ?? 'Unknown'); ?>
        </div>
        <div class="page-banner-meta-item">
          <strong>Access Level:</strong>
          <span class="access-badge"><?php echo ucfirst($user['role'] ?? 'User'); ?></span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Currency:</strong>
          <span class="font-medium"><?php echo CurrencyHelper::symbol() . ' ' . CurrencyHelper::getCurrentCurrency(); ?></span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <a href="add_item.php" class="btn btn-success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Add Item
      </a>
      <a href="logout.php" class="btn btn-danger">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9M16 17L21 12M21 12L16 7M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Logout
      </a>
    </div>
  </div>
</div>

<!-- Toolbar with Search and Filters -->
<div class="toolbar">
  <div class="toolbar-left">
    <div class="search-wrapper">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input 
        type="search" 
        class="search-input" 
        placeholder="Search by name, barcode, or type..."
        id="inventory-search"
        value="<?php echo htmlspecialchars($searchQuery); ?>"
        style="color: var(--text-primary); background-color: var(--bg-secondary);"
      >
    </div>
  </div>
  
  <div class="toolbar-right">
    <select class="form-select" id="filter-select" style="width: auto;">
      <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>All Items</option>
      <option value="low_stock" <?php echo $filterType === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
      <option value="out_of_stock" <?php echo $filterType === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
    </select>
    
    <button class="btn btn-ghost btn-icon" onclick="window.print()" title="Print">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M6 9V2H18V9M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18M6 14H18V22H6V14Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
    
    <button class="btn btn-ghost btn-icon" onclick="showToast('Export feature coming soon', 'info')" title="Export">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
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
<div class="table-container">
  <table class="data-table">
    <thead>
      <tr>
        <th class="sortable" data-sort="number">#</th>
        <th class="sortable" data-sort="barcode">Barcode</th>
        <th class="sortable" data-sort="name">Item Name</th>
        <th class="sortable" data-sort="type">Type</th>
        <th class="sortable" data-sort="lifespan">Lifespan</th>
        <th class="sortable" data-sort="quantity">Quantity</th>
        <th class="sortable" data-sort="date">Date Added</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $counter = $offset + 1;
      foreach ($items as $item): 
        $quantity = $item['quantity'] ?? 0;
        $isLowStock = $quantity > 0 && $quantity <= 5;
        $isOutOfStock = $quantity == 0;
        $rowClass = $isOutOfStock ? 'low-stock' : ($isLowStock ? 'low-stock' : '');
        $dateAdded = isset($item['date_added']) ? $item['date_added']->toDateTime()->format('Y-m-d') : 'N/A';
      ?>
      <tr class="<?php echo $rowClass; ?>">
        <td><?php echo $counter++; ?></td>
        <td class="font-mono"><?php echo htmlspecialchars($item['barcode'] ?? 'N/A'); ?></td>
        <td class="font-medium"><?php echo htmlspecialchars($item['name'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($item['type'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($item['lifespan'] ?? 'N/A'); ?></td>
        <td>
          <div class="flex items-center gap-2">
            <span class="font-medium"><?php echo htmlspecialchars($quantity); ?></span>
            <?php if ($isOutOfStock): ?>
              <span class="badge badge-danger">Out</span>
            <?php elseif ($isLowStock): ?>
              <span class="badge badge-warning">Low</span>
            <?php endif; ?>
          </div>
        </td>
        <td class="text-secondary"><?php echo htmlspecialchars($dateAdded); ?></td>
        <td>
          <div class="flex gap-2">
            <a href="edit_item.php?id=<?php echo (string)$item['_id']; ?>" 
               class="btn btn-ghost btn-sm" 
               title="Edit">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </a>
            <button 
              onclick="deleteItem('<?php echo (string)$item['_id']; ?>', '<?php echo htmlspecialchars($item['name']); ?>')" 
              class="btn btn-ghost btn-sm text-danger" 
              title="Delete">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
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
  
  <!-- Pagination Controls -->
  <?php if ($totalPages > 1): ?>
  <div style="display: flex; gap: 0.25rem; align-items: center;">
    <!-- Previous Button -->
    <?php if ($currentPage > 1): ?>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>" 
         class="btn btn-ghost btn-sm" 
         style="padding: 0.5rem 0.75rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Previous
      </a>
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
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
           class="btn btn-ghost btn-sm" 
           style="min-width: 2.5rem; padding: 0.5rem;">
          1
        </a>
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
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page])); ?>" 
             class="btn btn-ghost btn-sm" 
             style="min-width: 2.5rem; padding: 0.5rem;">
            <?php echo $page; ?>
          </a>
        <?php endif; ?>
      <?php endfor; ?>
      
      <!-- Show last page if not in range -->
      <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
        <span style="padding: 0.5rem; color: var(--text-muted);">...</span>
        <?php endif; ?>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" 
           class="btn btn-ghost btn-sm" 
           style="min-width: 2.5rem; padding: 0.5rem;">
          <?php echo $totalPages; ?>
        </a>
      <?php endif; ?>
    </div>
    
    <!-- Next Button -->
    <?php if ($currentPage < $totalPages): ?>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>" 
         class="btn btn-ghost btn-sm" 
         style="padding: 0.5rem 0.75rem;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
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
// Search functionality
const searchInput = document.getElementById('inventory-search');
if (searchInput) {
  searchInput.addEventListener('input', debounce(function(e) {
    const query = e.target.value.trim();
    const url = new URL(window.location.href);
    
    if (query) {
      url.searchParams.set('search', query);
    } else {
      url.searchParams.delete('search');
    }
    
    window.location.href = url.toString();
  }, 800));
}

// Filter functionality
const filterSelect = document.getElementById('filter-select');
if (filterSelect) {
  filterSelect.addEventListener('change', function(e) {
    const filter = e.target.value;
    const url = new URL(window.location.href);
    
    if (filter && filter !== 'all') {
      url.searchParams.set('filter', filter);
    } else {
      url.searchParams.delete('filter');
    }
    url.searchParams.delete('search');
    
    window.location.href = url.toString();
  });
}

// Delete item function
function deleteItem(id, name) {
  if (confirm(`Are you sure you want to delete "${name}"?`)) {
    window.location.href = `delete_item.php?id=${id}`;
  }
}

// Debounce helper
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

// Table sorting (basic implementation)
const sortableThs = document.querySelectorAll('.sortable');
sortableThs.forEach(th => {
  th.style.cursor = 'pointer';
  th.addEventListener('click', function() {
    showToast('Sorting feature coming soon', 'info');
  });
});
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
