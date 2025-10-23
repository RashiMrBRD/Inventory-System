<?php
/**
 * Dashboard Page
 * Overview of inventory statistics and recent activity
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database service
require_once __DIR__ . '/../vendor/autoload.php';
use App\Model\Inventory;
use App\Model\Invoice;
use App\Model\Quotation;
use App\Model\Order;
use App\Model\Project;
use App\Model\Shipment;
use App\Model\BirForm;
use App\Model\FdaProduct;

// Get current user
use App\Controller\AuthController;
use App\Helper\NotificationHelper;
use App\Helper\CurrencyHelper;
$authController = new AuthController();
$user = $authController->getCurrentUser();

// Initialize user ID
$userId = $user['id'] ?? 'admin';

// ============================================
// INVENTORY MODULE
// ============================================
$inventoryModel = new Inventory();
try {
    $totalItems = $inventoryModel->count();
    $lowStockItems = $inventoryModel->getLowStockCount();
    $outOfStockItems = $inventoryModel->getOutOfStockCount();
    $recentItems = $inventoryModel->getRecentItems(5);
    $addedToday = $inventoryModel->countAddedSince(new \DateTimeImmutable('-24 hours'));
} catch (Exception $e) {
    $totalItems = $lowStockItems = $outOfStockItems = $addedToday = 0;
    $recentItems = [];
}

// ============================================
// SALES & OPERATIONS
// ============================================
$invoiceModel = new Invoice();
$quotationModel = new Quotation();
$orderModel = new Order();
$projectModel = new Project();
$shipmentModel = new Shipment();

try {
    // Quotations
    $quotations = $quotationModel->getAll();
    $totalQuotations = count($quotations);
    $pendingQuotations = count(array_filter($quotations, fn($q) => ($q['status'] ?? '') === 'pending'));
    
    // Invoices
    $invoices = $invoiceModel->getAll();
    $invoiceTotals = $invoiceModel->totals();
    $totalRevenue = $invoiceTotals['total'];
    $paidRevenue = $invoiceTotals['paid'];
    $outstandingRevenue = $invoiceTotals['outstanding'];
    $totalInvoices = count($invoices);
    
    // Orders
    $orders = $orderModel->getAll();
    $totalOrders = count($orders);
    $processingOrders = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'processing'));
    
    // Projects
    $projects = $projectModel->getAll();
    $totalProjects = count($projects);
    $activeProjects = count(array_filter($projects, fn($p) => ($p['status'] ?? '') === 'active'));
    
    // Shipments
    $shipments = $shipmentModel->getAll();
    $totalShipments = count($shipments);
    $inTransitShipments = count(array_filter($shipments, fn($s) => ($s['status'] ?? '') === 'in_transit'));
} catch (Exception $e) {
    $totalQuotations = $pendingQuotations = 0;
    $totalRevenue = $paidRevenue = $outstandingRevenue = $totalInvoices = 0;
    $totalOrders = $processingOrders = 0;
    $totalProjects = $activeProjects = 0;
    $totalShipments = $inTransitShipments = 0;
}

// ============================================
// COMPLIANCE TRACKING
// ============================================
$birModel = new BirForm();
$fdaModel = new FdaProduct();

try {
    $birForms = $birModel->getRecentForms(50);
    $pendingBirForms = count(array_filter($birForms, fn($f) => ($f['status'] ?? '') === 'pending'));
    
    $expiringFdaProducts = $fdaModel->getExpiringProducts(30);
    $totalExpiringProducts = count($expiringFdaProducts);
    $activeProducts = $fdaModel->countActive();
} catch (Exception $e) {
    $pendingBirForms = $totalExpiringProducts = $activeProducts = 0;
}

// ============================================
// NOTIFICATIONS
// ============================================
$notificationSummary = NotificationHelper::getSummary($userId);
$recentNotifications = NotificationHelper::getRecent($userId, 5);

// Set page variables
$pageTitle = 'Dashboard';

// Start output buffering for content
ob_start();
?>

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Dashboard</h1>
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

<!-- ========================================
     SYSTEM OVERVIEW - ALL MODULES
     ======================================== -->

<!-- Inventory Module Stats -->
<div class="section">
  <h2 class="section-title">Inventory Management</h2>
  <div class="grid grid-cols-4 mb-4">
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Total Items</p>
        <p class="stat-value"><?php echo number_format($totalItems); ?></p>
        <a href="inventory-list.php" class="text-sm text-primary">View All →</a>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Low Stock</p>
        <p class="stat-value text-warning"><?php echo number_format($lowStockItems); ?></p>
        <?php if ($lowStockItems > 0): ?>
        <span class="badge badge-warning">Attention needed</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Out of Stock</p>
        <p class="stat-value text-danger"><?php echo number_format($outOfStockItems); ?></p>
        <?php if ($outOfStockItems > 0): ?>
        <span class="badge badge-danger">Critical</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Added Today</p>
        <p class="stat-value text-success"><?php echo number_format($addedToday); ?></p>
        <span class="badge badge-default">Last 24h</span>
      </div>
    </div>
  </div>
</div>

<!-- Sales & Operations Module Stats -->
<div class="section">
  <h2 class="section-title">Sales & Operations</h2>
  <div class="grid grid-cols-4 mb-4">
    <!-- Quotations -->
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Quotations</p>
        <p class="stat-value"><?php echo number_format($totalQuotations); ?></p>
        <?php if ($pendingQuotations > 0): ?>
        <span class="badge badge-warning"><?php echo $pendingQuotations; ?> pending</span>
        <?php endif; ?>
        <a href="quotations.php" class="text-sm text-primary mt-2">View →</a>
      </div>
    </div>
    <!-- Orders -->
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Orders</p>
        <p class="stat-value"><?php echo number_format($totalOrders); ?></p>
        <?php if ($processingOrders > 0): ?>
        <span class="badge badge-warning"><?php echo $processingOrders; ?> processing</span>
        <?php endif; ?>
        <a href="orders.php" class="text-sm text-primary mt-2">View →</a>
      </div>
    </div>
    <!-- Projects -->
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Projects</p>
        <p class="stat-value"><?php echo number_format($totalProjects); ?></p>
        <?php if ($activeProjects > 0): ?>
        <span class="badge badge-success"><?php echo $activeProjects; ?> active</span>
        <?php endif; ?>
        <a href="projects.php" class="text-sm text-primary mt-2">View →</a>
      </div>
    </div>
    <!-- Shipments -->
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Shipments</p>
        <p class="stat-value"><?php echo number_format($totalShipments); ?></p>
        <?php if ($inTransitShipments > 0): ?>
        <span class="badge badge-warning"><?php echo $inTransitShipments; ?> in transit</span>
        <?php endif; ?>
        <a href="shipping.php" class="text-sm text-primary mt-2">View →</a>
      </div>
    </div>
  </div>
</div>

<!-- Financial Overview -->
<div class="section">
  <h2 class="section-title">Financial Overview</h2>
  <div class="grid grid-cols-4 mb-4">
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Total Revenue</p>
        <p class="stat-value"><?php echo CurrencyHelper::format($totalRevenue); ?></p>
        <span class="badge badge-default"><?php echo $totalInvoices; ?> invoices</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Paid</p>
        <p class="stat-value text-success"><?php echo CurrencyHelper::format($paidRevenue); ?></p>
        <span class="text-sm text-secondary"><?php echo $totalRevenue > 0 ? round(($paidRevenue/$totalRevenue)*100) : 0; ?>% collected</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Outstanding</p>
        <p class="stat-value text-warning"><?php echo CurrencyHelper::format($outstandingRevenue); ?></p>
        <?php if ($outstandingRevenue > 0): ?>
        <span class="badge badge-warning">Pending</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Quick Access</p>
        <div class="flex flex-col gap-2 mt-2">
          <a href="invoicing.php" class="btn btn-sm btn-secondary">Invoices</a>
          <a href="financial-reports.php" class="btn btn-sm btn-secondary">Reports</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Compliance Tracking -->
<div class="section">
  <h2 class="section-title">Compliance & Regulatory</h2>
  <div class="grid grid-cols-4 mb-4">
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">BIR Forms</p>
        <p class="stat-value"><?php echo number_format($pendingBirForms); ?></p>
        <?php if ($pendingBirForms > 0): ?>
        <span class="badge badge-warning">Pending filing</span>
        <?php else: ?>
        <span class="badge badge-success">Up to date</span>
        <?php endif; ?>
        <a href="bir-compliance.php" class="text-sm text-primary mt-2">View →</a>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">FDA Products</p>
        <p class="stat-value"><?php echo number_format($activeProducts); ?></p>
        <span class="badge badge-success">Active</span>
        <a href="fda-compliance.php" class="text-sm text-primary mt-2">View →</a>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Expiring Soon</p>
        <p class="stat-value text-warning"><?php echo number_format($totalExpiringProducts); ?></p>
        <?php if ($totalExpiringProducts > 0): ?>
        <span class="badge badge-danger">Next 30 days</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Accounting</p>
        <div class="flex flex-col gap-2 mt-2">
          <a href="chart-of-accounts.php" class="btn btn-sm btn-secondary">Accounts</a>
          <a href="journal-entries.php" class="btn btn-sm btn-secondary">Journals</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="card mb-6">
  <div class="card-header">
    <h3 class="card-title">Quick Actions</h3>
  </div>
  <div class="card-content">
    <div class="flex gap-3">
      <a href="add_item.php" class="btn btn-secondary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Add New Item
      </a>
      <a href="inventory-list.php" class="btn btn-secondary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M4 6H20M4 12H20M4 18H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        View All Items
      </a>
      <a href="inventory-list.php?filter=low_stock" class="btn btn-secondary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 9V13M12 17H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Check Low Stock
      </a>
      <button class="btn btn-ghost ml-auto" onclick="showToast('Export feature coming soon!', 'info')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Export Data
      </button>
    </div>
  </div>
</div>

<!-- Recent Activity & Low Stock Items -->
<div class="grid grid-cols-2 gap-6">
  <!-- Recent Activity -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Recent Activity</h3>
      <p class="card-description">Latest updates to inventory</p>
    </div>
    <div class="card-content">
      <?php if (empty($recentItems)): ?>
        <div class="empty-state">
          <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none">
            <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 5C9 6.10457 9.89543 7 11 7H13C14.1046 7 15 6.10457 15 5M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5M9 12H15M9 16H12" stroke="currentColor" stroke-width="2"/>
          </svg>
          <p class="empty-state-title">No recent activity</p>
          <p class="empty-state-description">Start by adding your first inventory item</p>
          <a href="add_item.php" class="btn btn-primary">Add First Item</a>
        </div>
      <?php else: ?>
        <div>
          <?php foreach ($recentItems as $item): ?>
          <div class="flex items-center gap-3 p-3 mb-2 rounded-md hover-bg transition-all">
            <div class="flex-1">
              <p class="font-medium text-sm"><?php echo htmlspecialchars($item['name']); ?></p>
              <p class="text-xs text-secondary">
                Quantity: <?php echo $item['quantity']; ?> • 
                <?php echo htmlspecialchars($item['type']); ?>
              </p>
            </div>
            <span class="badge <?php echo $item['quantity'] <= 5 ? 'badge-warning' : 'badge-success'; ?>">
              <?php echo $item['quantity'] <= 5 ? 'Low Stock' : 'In Stock'; ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-4">
          <a href="inventory-list.php" class="btn btn-ghost btn-sm w-full">View All Items →</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Low Stock Alerts -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Low Stock Alerts</h3>
      <p class="card-description">Items requiring attention</p>
    </div>
    <div class="card-content">
      <?php if ($lowStockItems === 0): ?>
        <div class="empty-state">
          <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none">
            <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <p class="empty-state-title">All stock levels good</p>
          <p class="empty-state-description">No items require immediate attention</p>
        </div>
      <?php else: ?>
        <div class="alert alert-warning mb-4">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M12 9V13M12 17H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <div>
            <strong><?php echo $lowStockItems; ?> item<?php echo $lowStockItems > 1 ? 's' : ''; ?></strong> running low on stock
          </div>
        </div>
        <a href="inventory-list.php?filter=low_stock" class="btn btn-secondary w-full">
          View Low Stock Items
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- System Notifications & Alerts -->
<div class="section">
  <h2 class="section-title">System Notifications & Alerts</h2>
  <div class="grid grid-cols-4 mb-4">
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Unread</p>
        <p class="stat-value"><?php echo number_format($notificationSummary['unread']); ?></p>
        <span class="badge badge-default">Notifications</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">High Priority</p>
        <p class="stat-value text-danger"><?php echo number_format($notificationSummary['high_priority']); ?></p>
        <?php if ($notificationSummary['high_priority'] > 0): ?>
        <span class="badge badge-danger">Urgent</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Medium Priority</p>
        <p class="stat-value text-warning"><?php echo number_format($notificationSummary['medium_priority']); ?></p>
        <?php if ($notificationSummary['medium_priority'] > 0): ?>
        <span class="badge badge-warning">Action needed</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Today</p>
        <p class="stat-value"><?php echo number_format($notificationSummary['today']); ?></p>
        <a href="notifications.php" class="text-sm text-primary mt-2">View All →</a>
      </div>
    </div>
  </div>
  <?php if (!empty($recentNotifications)): ?>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Recent Alerts</h3>
    </div>
    <div class="card-content">
      <?php foreach (array_slice($recentNotifications, 0, 5) as $notif): ?>
      <div class="alert-item" style="display: grid; grid-template-columns: auto 1fr auto; gap: 0.75rem; padding: 0.75rem 1rem; margin-bottom: 0.5rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); align-items: start;">
        <!-- Icon -->
        <div style="padding-top: 0.125rem;">
          <?php if ($notif['priority'] === 'high'): ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="color: var(--color-danger);">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <path d="M12 8V12M12 16H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <?php elseif ($notif['priority'] === 'medium'): ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="color: var(--color-warning);">
            <path d="M12 9V13M12 17H12.01M10.29 3.86L1.82 18C1.64537 18.3024 1.55296 18.6453 1.55199 18.9945C1.55101 19.3437 1.64149 19.6871 1.81442 19.9905C1.98736 20.2939 2.23672 20.5467 2.53771 20.7239C2.8387 20.901 3.18082 20.9962 3.53 21H20.47C20.8192 20.9962 21.1613 20.901 21.4623 20.7239C21.7633 20.5467 22.0126 20.2939 22.1856 19.9905C22.3585 19.6871 22.449 19.3437 22.448 18.9945C22.447 18.6453 22.3546 18.3024 22.18 18L13.71 3.86C13.5317 3.56611 13.2807 3.32312 12.9812 3.15448C12.6817 2.98585 12.3437 2.89725 12 2.89725C11.6563 2.89725 11.3183 2.98585 11.0188 3.15448C10.7193 3.32312 10.4683 3.56611 10.29 3.86Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <?php else: ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="color: var(--color-primary);">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <path d="M12 16V12M12 8H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <?php endif; ?>
        </div>
        
        <!-- Content -->
        <div style="min-width: 0;">
          <div class="font-medium text-sm" style="margin-bottom: 0.25rem; line-height: 1.4;"><?php echo htmlspecialchars($notif['title']); ?></div>
          <div class="text-xs text-secondary" style="line-height: 1.5;"><?php echo htmlspecialchars($notif['message']); ?></div>
        </div>
        
        <!-- Badge -->
        <span class="badge badge-<?php echo $notif['priority'] === 'high' ? 'danger' : ($notif['priority'] === 'medium' ? 'warning' : 'default'); ?> badge-sm" style="flex-shrink: 0;">
          <?php echo ucfirst($notif['priority']); ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
