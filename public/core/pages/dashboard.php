<?php
/**
 * Dashboard Page
 * Overview of inventory statistics and recent activity
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Include database service
require_once __DIR__ . '/../../../vendor/autoload.php';
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

// Sidebar visibility preferences (match sidebar.php/settings.php)
$defaultHiddenSidebarItems = [
    'projects',
    'bir-compliance',
    'fda-compliance',
    'notifications',
    'chart-of-accounts',
    'journal-entries',
    'financial-reports',
    'conversations',
    'system-alerts',
];

$sidebarHiddenItems = $defaultHiddenSidebarItems;
if ($user && isset($user['sidebar_hidden_items']) && is_array($user['sidebar_hidden_items'])) {
    $sidebarHiddenItems = $user['sidebar_hidden_items'];
}
$showProjectsWidgets = !in_array('projects', $sidebarHiddenItems, true);

// Initialize user ID - use MongoDB _id
$userId = isset($user['_id']) ? (string)$user['_id'] : null;
if (!$userId) {
    // Fallback to session user_id
    $userId = $_SESSION['user_id'] ?? null;
}

if (!$userId) {
    die("User not authenticated");
}
 
// BIR widgets visibility based on sidebar settings
$showBirWidgets = !in_array('bir-compliance', $sidebarHiddenItems, true);

// FDA widgets visibility based on sidebar settings
$showFdaWidgets = !in_array('fda-compliance', $sidebarHiddenItems, true);

// Notifications widgets visibility based on sidebar settings
$showNotificationsWidgets = !in_array('notifications', $sidebarHiddenItems, true);
$hasComplianceWidgets = $showBirWidgets || $showFdaWidgets || $showNotificationsWidgets;

// ============================================
// TIME RANGE FILTER (Dashboard)
// ============================================
$timeRange = $_GET['range'] ?? '30d';
$validRanges = ['7d', '30d', '90d', 'month', 'quarter', 'year'];
if (!in_array($timeRange, $validRanges)) {
    $timeRange = '30d';
}

// Calculate date ranges
$rangeMap = [
    '7d' => 7,
    '30d' => 30,
    '90d' => 90,
    'month' => 30,
    'quarter' => 90,
    'year' => 365
];
$daysBack = $rangeMap[$timeRange];
$startDate = new \DateTimeImmutable("-$daysBack days");
$endDate = new \DateTimeImmutable();

// Helper function to filter data by date range
function isDashboardInRange($dateValue, $start, $end) {
    if (!$dateValue) return false;
    $ts = is_string($dateValue) ? strtotime($dateValue) : 
          (is_object($dateValue) && method_exists($dateValue, 'toDateTime') ? 
           $dateValue->toDateTime()->getTimestamp() : null);
    if (!$ts) return false;
    return $ts >= $start->getTimestamp() && $ts <= $end->getTimestamp();
}

// ============================================
// INVENTORY MODULE - Use aggregation
// ============================================
$inventoryModel = new Inventory();
$inventoryStats = $inventoryModel->getDashboardAnalytics($daysBack);

$totalItems = $inventoryStats['total_items'];
$lowStockItems = $inventoryStats['low_stock_count'];
$outOfStockItems = $inventoryStats['out_of_stock_count'];
$inventoryValue = $inventoryStats['total_value'];
$recentItems = $inventoryModel->getRecentItems(5);
$addedToday = $inventoryStats['items_added_this_period'];

// ============================================
// SALES & OPERATIONS - Use aggregation
// ============================================
$invoiceModel = new Invoice();
$quotationModel = new Quotation();
$orderModel = new Order();
$projectModel = new Project();
$shipmentModel = new Shipment();

// Use aggregation methods instead of loading all records
$invoiceStats = $invoiceModel->getDashboardAnalytics($daysBack);
$quotationStats = $quotationModel->getDashboardAnalytics($daysBack);
$orderStats = $orderModel->getDashboardAnalytics($daysBack);
$projectStats = $projectModel->getDashboardAnalytics($daysBack);
$shipmentStats = $shipmentModel->getDashboardAnalytics($daysBack);

$totalQuotations = $quotationStats['total_count'];
$pendingQuotations = $quotationStats['pending_count'];
$quotationsInPeriod = $quotationStats['quotations_in_period'];

$totalRevenue = $invoiceStats['total_revenue'];
$paidRevenue = $invoiceStats['paid_revenue'];
$outstandingRevenue = $invoiceStats['outstanding_revenue'];
$totalInvoices = $invoiceStats['invoice_count'];
$revenueInPeriod = $invoiceStats['revenue_in_period'];
$paidRevenuePrevPeriod = $invoiceStats['paid_revenue_prev_period'] ?? 0;

$totalOrders = $orderStats['total_count'];
$processingOrders = $orderStats['pending_count'];

$totalProjects = $projectStats['total_count'];
$activeProjects = $projectStats['active_count'];

$totalShipments = $shipmentStats['total_count'];
$inTransitShipments = $shipmentStats['in_transit_count'];

// Calculate trends from aggregation results
$revenueChange = $invoiceStats['revenue_trend'];
$collectionRateChange = $invoiceStats['collection_trend'];
$currentCollectionRate = $invoiceStats['collection_rate'];

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

// Calculate financial summary stats
$financialSummary = [
    'total_revenue' => $totalRevenue,
    'paid_revenue' => $paidRevenue,
    'outstanding_revenue' => $outstandingRevenue,
    'total_invoices' => $totalInvoices,
    'pending_quotations' => $pendingQuotations,
    'active_projects' => $activeProjects,
];

// Set page variables
$pageTitle = 'Dashboard';
$loadDashboardJS = true;
$dashboardScriptConfig = [
    'currencySymbol' => CurrencyHelper::symbol()
];

// Start output buffering for content
ob_start();

// ========== WELCOME MODAL ==========
// To remove this welcome system, delete the following 2 lines and the features/experimental-warning folder
require_once __DIR__ . '/../../features/experimental-warning/experimental-warning.php';
renderExperimentalWarning('Dashboard - Welcome');
// System auto-detects page and shows welcome modal with app info and team members
// ===================================================
?>

<!-- Dashboard styles are now managed in assets/css/dashboard.css -->

<!-- Page Header with Breadcrumb (following chart-of-accounts.php style) -->
<div class="content-header">
  <div>
    <nav class="breadcrumb">
      <span class="breadcrumb-current">Dashboard</span>
    </nav>
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <h1 class="content-title">Business Overview</h1>
      <?php
        $rangeDisplayMap = [
          '7d' => 'Last 7 Days',
          '30d' => 'Last 30 Days',
          '90d' => 'Last 90 Days',
          'month' => 'This Month',
          'quarter' => 'This Quarter',
          'year' => 'This Year'
        ];
        $rangeDisplay = $rangeDisplayMap[$timeRange] ?? 'Last 30 Days';
        $rangeIcon = '📅';
      ?>
      <div class="range-indicator-badge" title="Current time range">
        <span class="range-icon"><?php echo $rangeIcon; ?></span>
        <span class="range-label"><?php echo $rangeDisplay; ?></span>
      </div>
    </div>
  </div>
  <div class="content-actions">
    <button class="btn btn-secondary" onclick="refreshDashboard()" id="refresh-dashboard-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Refresh
    </button>
    <a href="financial-reports.php" class="btn btn-secondary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M9 17V11M15 17V7M3 21H21M5 7L12 3L19 7V21H5V7Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Reports
    </a>
    <a href="journal-entries.php" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      New Entry
    </a>
  </div>
</div>

<!-- ========================================
     FINANCIAL SNAPSHOT - Xero/QuickBooks Inspired
     ======================================== -->

<!-- Key Financial Metrics (Ultra Compact) -->
<div class="dashboard-section">
  <div class="dashboard-section-header">
    <h2 class="dashboard-section-title" style="font-size: 1.125rem;">
      <span class="dashboard-section-icon" style="font-size: 1.25rem;">💰</span>
      Financial Snapshot
    </h2>
    <a href="financial-reports.php" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">Reports →</a>
  </div>
  
  <div class="financial-cards-grid">
    <!-- Total Revenue Card -->
    <div class="financial-card">
      <div class="financial-card-header">
        <div>
          <div class="financial-card-title">Total Revenue</div>
          <div class="financial-card-badge"><?php echo $totalInvoices; ?> invoices</div>
        </div>
        <div style="width: 48px; height: 48px; background: hsl(143 85% 96%); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
          📊
        </div>
      </div>
      <div class="financial-card-value" style="color: var(--color-success);">
        <?php echo CurrencyHelper::format($totalRevenue); ?>
      </div>
      <div class="financial-card-subtitle">
        <span style="color: var(--color-success); font-weight: 600;">
          <?php echo CurrencyHelper::format($paidRevenue); ?> paid
        </span>
        <span style="color: var(--text-secondary);"> • 
          <?php echo $totalRevenue > 0 ? round(($paidRevenue/$totalRevenue)*100) : 0; ?>% collected
        </span>
      </div>
      <!-- Compact Progress Bar -->
      <?php $collectionRate = $totalRevenue > 0 ? round(($paidRevenue/$totalRevenue)*100) : 0; ?>
      <div style="margin-top: 0.625rem;">
        <div class="progress-bar" style="height: 6px;">
          <div class="progress-bar-fill success" style="width: <?php echo $collectionRate; ?>%;"></div>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 0.25rem; font-size: 0.6875rem; color: var(--text-secondary);">
          <span>Collection</span>
          <span style="font-weight: 600;"><?php echo $collectionRate; ?>%</span>
        </div>
      </div>
      <!-- Compact Trend -->
      <div style="margin-top: 0.5rem;">
        <?php if ($collectionRateChange != 0): ?>
        <span class="trend-indicator <?php echo $collectionRateChange > 0 ? 'up' : 'down'; ?>" style="font-size: 0.6875rem; padding: 0.125rem 0.375rem;">
          <span class="trend-arrow" style="font-size: 0.875rem;"><?php echo $collectionRateChange > 0 ? '↑' : '↓'; ?></span>
          <span><?php echo $collectionRateChange > 0 ? '+' : ''; ?><?php echo $collectionRateChange; ?>%</span>
        </span>
        <span style="font-size: 0.6875rem; color: var(--text-secondary); margin-left: 0.375rem;">vs last period</span>
        <?php else: ?>
        <span style="font-size: 0.6875rem; color: var(--text-secondary);">No change vs last period</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Outstanding Amount Card -->
    <div class="financial-card">
      <div class="financial-card-header">
        <div>
          <div class="financial-card-title">Accounts Receivable</div>
          <span class="badge badge-warning">Outstanding</span>
        </div>
        <div style="width: 48px; height: 48px; background: hsl(48 96% 89%); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
          ⏳
        </div>
      </div>
      <div class="financial-card-value" style="color: var(--color-warning);">
        <?php echo CurrencyHelper::format($outstandingRevenue); ?>
      </div>
      <div class="financial-card-subtitle">
        Pending customer payments
        <?php if ($outstandingRevenue > 0 && $totalRevenue > 0): ?>
          <span style="color: var(--text-secondary);"> • 
            <?php echo round(($outstandingRevenue/$totalRevenue)*100); ?>% of total
          </span>
        <?php endif; ?>
      </div>
      <!-- Compact Mini Chart -->
      <div style="margin-top: 0.625rem;">
        <div style="font-size: 0.6875rem; color: var(--text-secondary); margin-bottom: 0.375rem;">7-day trend</div>
        <div class="mini-chart" style="height: 32px;">
          <div class="mini-chart-bar" style="height: 60%;"></div>
          <div class="mini-chart-bar" style="height: 75%;"></div>
          <div class="mini-chart-bar" style="height: 50%;"></div>
          <div class="mini-chart-bar" style="height: 85%;"></div>
          <div class="mini-chart-bar" style="height: 70%;"></div>
          <div class="mini-chart-bar" style="height: 90%;"></div>
          <div class="mini-chart-bar" style="height: 95%;"></div>
        </div>
      </div>
      <!-- Compact Button -->
      <div style="margin-top: 0.625rem;">
        <a href="invoicing.php?status=unpaid" class="btn btn-sm btn-warning" style="width: 100%; font-size: 0.6875rem; padding: 0.375rem 0.5rem;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" style="margin-right: 0.25rem;">
            <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 12H15M9 16H12" stroke="currentColor" stroke-width="2"/>
          </svg>
          Follow Up
        </a>
      </div>
    </div>

    <!-- Active Projects Card -->
    <?php if ($showProjectsWidgets): ?>
    <div class="financial-card">
      <div class="financial-card-header">
        <div>
          <div class="financial-card-title">Active Projects</div>
          <span class="badge badge-success"><?php echo $activeProjects; ?> ongoing</span>
        </div>
        <div style="width: 48px; height: 48px; background: hsl(214 95% 93%); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
          🎯
        </div>
      </div>
      <div class="financial-card-value">
        <?php echo number_format($totalProjects); ?>
      </div>
      <div class="financial-card-subtitle">
        Total projects • <?php echo $pendingQuotations; ?> pending quotations
      </div>
      <!-- Compact Stats Grid -->
      <div class="stat-grid-3" style="margin-top: 0.75rem;">
        <div class="stat-item">
          <div class="stat-item-value" style="color: var(--color-success);"><?php echo $activeProjects; ?></div>
          <div class="stat-item-label">Active</div>
        </div>
        <div class="stat-item">
          <div class="stat-item-value" style="color: var(--color-warning);"><?php echo $pendingQuotations; ?></div>
          <div class="stat-item-label">Pending</div>
        </div>
        <div class="stat-item">
          <div class="stat-item-value" style="color: var(--color-primary);"><?php echo max(0, $totalProjects - $activeProjects); ?></div>
          <div class="stat-item-label">Done</div>
        </div>
      </div>
      <!-- Compact Donut -->
      <?php 
        $completionRate = $totalProjects > 0 ? round(($activeProjects / $totalProjects) * 100) : 0;
      ?>
      <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.75rem;">
        <div>
          <div style="font-size: 0.6875rem; color: var(--text-secondary); margin-bottom: 0.125rem;">Completion</div>
          <div style="font-size: 1rem; font-weight: 700;"><?php echo $completionRate; ?>%</div>
          <div class="progress-bar" style="width: 90px; margin-top: 0.375rem; height: 6px;">
            <div class="progress-bar-fill primary" style="width: <?php echo $completionRate; ?>%;"></div>
          </div>
        </div>
        <div class="donut-chart" style="--percentage: <?php echo $completionRate; ?>; width: 80px; height: 80px;">
          <div class="donut-chart-value" style="font-size: 1.125rem;"><?php echo $completionRate; ?>%</div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Business Operations (Compact Grid) -->
<div class="dashboard-section">
  <div class="dashboard-section-header" style="margin-bottom: 0.625rem;">
    <h2 class="dashboard-section-title" style="font-size: 1.125rem;">
      <span class="dashboard-section-icon" style="font-size: 1.25rem;">📦</span>
      Operations
    </h2>
  </div>
  
  <div class="dashboard-stats-grid">
    <!-- Inventory Card -->
    <div class="dashboard-stat-card">
      <div class="dashboard-stat-header">
        <div class="dashboard-stat-icon" style="background: hsl(143 85% 96%); color: hsl(140 61% 13%);">📦</div>
      </div>
      <div class="dashboard-stat-label">Total Inventory</div>
      <div class="dashboard-stat-value"><?php echo number_format($totalItems); ?></div>
      <div style="font-size: 0.6875rem; color: var(--text-secondary); line-height: 1.3;">
        <?php if ($lowStockItems > 0): ?>
          <span style="color: var(--color-warning); font-weight: 600;"><?php echo $lowStockItems; ?> low</span>
        <?php else: ?>
          <span style="color: var(--color-success);">✓ Good</span>
        <?php endif; ?>
        <?php if ($outOfStockItems > 0): ?>
          <span style="color: var(--color-danger); font-weight: 600; margin-left: 0.375rem;"><?php echo $outOfStockItems; ?> out</span>
        <?php endif; ?>
      </div>
      <a href="inventory-list.php" style="font-size: 0.6875rem; color: var(--color-primary); text-decoration: none; margin-top: 0.375rem; display: inline-block;">View →</a>
    </div>

    <!-- Quotations Card -->
    <div class="dashboard-stat-card">
      <div class="dashboard-stat-header">
        <div class="dashboard-stat-icon" style="background: hsl(48 96% 89%); color: hsl(25 95% 16%);">📝</div>
      </div>
      <div class="dashboard-stat-label">Quotations</div>
      <div class="dashboard-stat-value"><?php echo number_format($totalQuotations); ?></div>
      <div style="font-size: 0.6875rem; color: var(--text-secondary);">
        <?php if ($pendingQuotations > 0): ?>
          <span style="color: var(--color-warning); font-weight: 600;"><?php echo $pendingQuotations; ?> pending</span>
        <?php else: ?>
          <span style="color: var(--color-success);">✓ Done</span>
        <?php endif; ?>
      </div>
      <a href="quotations.php" style="font-size: 0.6875rem; color: var(--color-primary); text-decoration: none; margin-top: 0.375rem; display: inline-block;">View →</a>
    </div>

    <!-- Orders Card -->
    <div class="dashboard-stat-card">
      <div class="dashboard-stat-header">
        <div class="dashboard-stat-icon" style="background: hsl(214 95% 93%); color: hsl(222 47% 17%);">🛒</div>
      </div>
      <div class="dashboard-stat-label">Orders</div>
      <div class="dashboard-stat-value"><?php echo number_format($totalOrders); ?></div>
      <div style="font-size: 0.6875rem; color: var(--text-secondary);">
        <?php if ($processingOrders > 0): ?>
          <span style="color: var(--color-warning); font-weight: 600;"><?php echo $processingOrders; ?> processing</span>
        <?php else: ?>
          <span>No active</span>
        <?php endif; ?>
      </div>
      <a href="orders.php" style="font-size: 0.6875rem; color: var(--color-primary); text-decoration: none; margin-top: 0.375rem; display: inline-block;">View →</a>
    </div>

    <!-- Shipments Card -->
    <div class="dashboard-stat-card">
      <div class="dashboard-stat-header">
        <div class="dashboard-stat-icon" style="background: hsl(48 96% 89%); color: hsl(25 95% 16%);">🚚</div>
      </div>
      <div class="dashboard-stat-label">Shipments</div>
      <div class="dashboard-stat-value"><?php echo number_format($totalShipments); ?></div>
      <div style="font-size: 0.6875rem; color: var(--text-secondary);">
        <?php if ($inTransitShipments > 0): ?>
          <span style="color: var(--color-primary); font-weight: 600;"><?php echo $inTransitShipments; ?> transit</span>
        <?php else: ?>
          <span>None active</span>
        <?php endif; ?>
      </div>
      <a href="shipping.php" style="font-size: 0.6875rem; color: var(--color-primary); text-decoration: none; margin-top: 0.375rem; display: inline-block;">View →</a>
    </div>
  </div>
</div>

<!-- Quick Actions (Ultra Compact) -->
<div class="dashboard-section">
  <div class="dashboard-section-header" style="margin-bottom: 0.625rem;">
    <h2 class="dashboard-section-title" style="font-size: 1.125rem;">
      <span class="dashboard-section-icon" style="font-size: 1.25rem;">⚡</span>
      Quick Actions
    </h2>
  </div>
  
  <div class="quick-actions-grid">
    <a href="journal-entries.php" class="quick-action-card">
      <div class="quick-action-icon" style="background: hsl(143 85% 96%); color: hsl(140 61% 13%);">📓</div>
      <div class="quick-action-label">New Journal Entry</div>
    </a>
    
    <a href="invoicing.php" class="quick-action-card">
      <div class="quick-action-icon" style="background: hsl(214 95% 93%); color: hsl(222 47% 17%);">🧱</div>
      <div class="quick-action-label">Create Invoice</div>
    </a>
    
    <a href="add_item.php" class="quick-action-card">
      <div class="quick-action-icon" style="background: hsl(48 96% 89%); color: hsl(25 95% 16%);">📦</div>
      <div class="quick-action-label">Add Inventory Item</div>
    </a>
    
    <a href="chart-of-accounts.php" class="quick-action-card">
      <div class="quick-action-icon" style="background: hsl(214 95% 93%); color: hsl(222 47% 17%);">📈</div>
      <div class="quick-action-label">Chart of Accounts</div>
    </a>
    
    <a href="financial-reports.php" class="quick-action-card">
      <div class="quick-action-icon" style="background: hsl(143 85% 96%); color: hsl(140 61% 13%);">📊</div>
      <div class="quick-action-label">Financial Reports</div>
    </a>
    
    <a href="quotations.php" class="quick-action-card">
      <div class="quick-action-icon" style="background: hsl(48 96% 89%); color: hsl(25 95% 16%);">📝</div>
      <div class="quick-action-label">View Quotations</div>
    </a>
  </div>
</div>

<!-- ========================================
     ADVANCED VISUALIZATIONS - Xero/QuickBooks Inspired
     ======================================== -->

<!-- Cash Flow (Ultra Compact) -->
<div class="dashboard-section">
  <div class="dashboard-section-header" style="margin-bottom: 0.625rem;">
    <h2 class="dashboard-section-title" style="font-size: 1.125rem;">
      <span class="dashboard-section-icon" style="font-size: 1.25rem;">💸</span>
      Cash Flow
    </h2>
    <div class="chart-filters" style="display: flex; gap: 0.25rem;">
      <button onclick="setTimeRange('month')" class="btn btn-sm <?php echo $timeRange === 'month' ? 'btn-primary' : 'btn-secondary'; ?>" style="<?php echo $timeRange === 'month' ? '' : 'opacity: 0.6;'; ?> font-size: 0.6875rem; padding: 0.25rem 0.5rem;" title="View this month's data">
        This Month
      </button>
      <button onclick="setTimeRange('30d')" class="btn btn-sm <?php echo $timeRange === '30d' ? 'btn-primary' : 'btn-secondary'; ?>" style="<?php echo $timeRange === '30d' ? '' : 'opacity: 0.6;'; ?> font-size: 0.6875rem; padding: 0.25rem 0.5rem;" title="View last 30 days">
        30 Days
      </button>
    </div>
  </div>
  
  <div class="dashboard-widgets-grid">
    <!-- Cash Flow Visual Bar -->
    <div class="metric-card">
      <div class="metric-header">
        <div>
          <div class="metric-label">Cash In vs Cash Out</div>
          <div class="metric-value" style="color: var(--color-success);">
            <?php echo CurrencyHelper::format($paidRevenue); ?>
          </div>
        </div>
        <?php 
          $cashFlowChange = 0;
          if ($paidRevenuePrevPeriod > 0) {
            $cashFlowChange = round((($paidRevenue - $paidRevenuePrevPeriod) / $paidRevenuePrevPeriod) * 100, 1);
          }
          $cashFlowDirection = $cashFlowChange >= 0 ? 'up' : 'down';
          $cashFlowArrow = $cashFlowChange >= 0 ? '↑' : '↓';
        ?>
        <div class="trend-indicator <?php echo $cashFlowDirection; ?>">
          <span class="trend-arrow"><?php echo $cashFlowArrow; ?></span>
          <span><?php echo $cashFlowChange >= 0 ? '+' : ''; ?><?php echo number_format(abs($cashFlowChange), 1); ?>%</span>
        </div>
      </div>
      
      <?php 
        $cashIn = $paidRevenue;
        $cashOut = $paidRevenue * 0.65; // Sample: 65% expenses
        $netCashFlow = $cashIn - $cashOut;
        $totalCash = $cashIn + $cashOut;
        $cashInPercent = $totalCash > 0 ? ($cashIn / $totalCash) * 100 : 50;
        $cashOutPercent = 100 - $cashInPercent;
      ?>
      
      <div class="cash-flow-bar">
        <div class="cash-flow-in" style="width: <?php echo $cashInPercent; ?>%;">
          <div style="text-align: center;">
            <div style="font-size: 0.75rem; opacity: 0.9;">Cash In</div>
            <div style="font-weight: 700;"><?php echo CurrencyHelper::format($cashIn); ?></div>
          </div>
        </div>
        <div class="cash-flow-out" style="width: <?php echo $cashOutPercent; ?>%;">
          <div style="text-align: center;">
            <div style="font-size: 0.75rem; opacity: 0.9;">Cash Out</div>
            <div style="font-weight: 700;"><?php echo CurrencyHelper::format($cashOut); ?></div>
          </div>
        </div>
      </div>
      
      <div class="metric-comparison">
        <div style="flex: 1;">
          <div style="font-size: 0.75rem; color: var(--text-secondary);">Net Cash Flow</div>
          <div style="font-size: 1.5rem; font-weight: 700; color: <?php echo $netCashFlow >= 0 ? 'var(--color-success)' : 'var(--color-danger)'; ?>;">
            <?php echo CurrencyHelper::format($netCashFlow); ?>
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 0.75rem; color: var(--text-secondary);">vs Last Period</div>
          <span class="trend-indicator <?php echo $netCashFlow >= 0 ? 'up' : 'down'; ?>">
            <span class="trend-arrow"><?php echo $netCashFlow >= 0 ? '↑' : '↓'; ?></span>
            <span><?php echo abs(round(($netCashFlow / max($cashIn, 1)) * 100, 1)); ?>%</span>
          </span>
        </div>
      </div>
    </div>
    
    <!-- Account Balances Widget (QuickBooks Feature) -->
    <div class="metric-card">
      <div class="metric-header">
        <div>
          <div class="metric-label">Account Balances</div>
          <div class="metric-value"><?php echo CurrencyHelper::format($paidRevenue + ($outstandingRevenue * 0.3)); ?></div>
        </div>
        <a href="chart-of-accounts.php" style="font-size: 0.875rem; color: var(--color-primary); text-decoration: none;">View All →</a>
      </div>
      
      <div style="margin-top: 1rem;">
        <!-- Cash Account -->
        <div class="account-balance-item">
          <div>
            <div class="account-name">💵 Cash on Hand</div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Account 1001</div>
          </div>
          <div class="account-balance" style="color: var(--color-success);">
            <?php echo CurrencyHelper::format($paidRevenue * 0.3); ?>
          </div>
        </div>
        
        <!-- Bank Account -->
        <div class="account-balance-item">
          <div>
            <div class="account-name">🏦 Bank Account</div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Account 1002</div>
          </div>
          <div class="account-balance" style="color: var(--color-success);">
            <?php echo CurrencyHelper::format($paidRevenue * 0.7); ?>
          </div>
        </div>
        
        <!-- Accounts Receivable -->
        <div class="account-balance-item">
          <div>
            <div class="account-name">📄 Accounts Receivable</div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Account 1200</div>
          </div>
          <div class="account-balance" style="color: var(--color-warning);">
            <?php echo CurrencyHelper::format($outstandingRevenue); ?>
          </div>
        </div>
        
        <!-- Total Progress -->
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid var(--border-color);">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span style="font-weight: 600;">Total Assets</span>
            <span style="font-weight: 700; font-family: monospace;"><?php echo CurrencyHelper::format($paidRevenue + $outstandingRevenue); ?></span>
          </div>
          <div class="progress-bar">
            <div class="progress-bar-fill success" style="width: 75%;"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Aging Receivables (Compact) -->
<div class="dashboard-section">
  <div class="dashboard-section-header" style="margin-bottom: 0.625rem;">
    <h2 class="dashboard-section-title" style="font-size: 1.125rem;">
      <span class="dashboard-section-icon" style="font-size: 1.25rem;">📅</span>
      Aging Receivables
    </h2>
    <a href="financial-reports.php?report=aging" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">Report →</a>
  </div>
  
  <div class="metric-card">
    <div class="grid-compact-4" style="margin-bottom: 0.75rem;">
      <div class="stat-item">
        <div class="stat-item-value" style="color: var(--color-success);"><?php echo CurrencyHelper::format($outstandingRevenue * 0.4); ?></div>
        <div class="stat-item-label">Current</div>
      </div>
      <div class="stat-item">
        <div class="stat-item-value" style="color: var(--color-primary);"><?php echo CurrencyHelper::format($outstandingRevenue * 0.3); ?></div>
        <div class="stat-item-label">1-30 Days</div>
      </div>
      <div class="stat-item">
        <div class="stat-item-value" style="color: var(--color-warning);"><?php echo CurrencyHelper::format($outstandingRevenue * 0.2); ?></div>
        <div class="stat-item-label">31-60 Days</div>
      </div>
      <div class="stat-item">
        <div class="stat-item-value" style="color: var(--color-danger);"><?php echo CurrencyHelper::format($outstandingRevenue * 0.1); ?></div>
        <div class="stat-item-label">60+ Days</div>
      </div>
    </div>
    
    <div class="aging-bars">
      <?php
        $agingData = [
          ['label' => 'Current', 'amount' => $outstandingRevenue * 0.4, 'percent' => 40],
          ['label' => '1-30 Days', 'amount' => $outstandingRevenue * 0.3, 'percent' => 30],
          ['label' => '31-60 Days', 'amount' => $outstandingRevenue * 0.2, 'percent' => 20],
          ['label' => '60+ Days', 'amount' => $outstandingRevenue * 0.1, 'percent' => 10],
        ];
        foreach ($agingData as $aging):
      ?>
      <div class="aging-bar-item">
        <div class="aging-label"><?php echo $aging['label']; ?></div>
        <div class="aging-bar-container">
          <div class="aging-bar-fill" style="width: <?php echo $aging['percent']; ?>%;">
            <?php if ($aging['percent'] > 15): ?>
              <?php echo $aging['percent']; ?>%
            <?php endif; ?>
          </div>
        </div>
        <div class="aging-amount"><?php echo CurrencyHelper::format($aging['amount']); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    
    <div style="margin-top: 0.75rem; padding: 0.625rem 0.75rem; background: hsl(48 96% 89%); border-radius: var(--radius-sm); border: 1px solid hsl(25 95% 16% / 0.2);">
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="color: hsl(25 95% 16%); flex-shrink: 0;">
          <path d="M12 9V13M12 17H12.01M10.29 3.86L1.82 18C1.64537 18.3024 1.55296 18.6453 1.55199 18.9945C1.55101 19.3437 1.64149 19.6871 1.81442 19.9905C1.98736 20.2939 2.23672 20.5467 2.53771 20.7239C2.8387 20.901 3.18082 20.9962 3.53 21H20.47C20.8192 20.9962 21.1613 20.901 21.4623 20.7239C21.7633 20.5467 22.0126 20.2939 22.1856 19.9905C22.3585 19.6871 22.449 19.3437 22.448 18.9945C22.447 18.6453 22.3546 18.3024 22.18 18L13.71 3.86C13.5317 3.56611 13.2807 3.32312 12.9812 3.15448C12.6817 2.98585 12.3437 2.89725 12 2.89725C11.6563 2.89725 11.3183 2.98585 11.0188 3.15448C10.7193 3.32312 10.4683 3.56611 10.29 3.86Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div style="flex: 1;">
          <div style="font-weight: 600; color: hsl(25 95% 16%); margin-bottom: 0.25rem;">Action Required</div>
          <div style="font-size: 0.6875rem; color: hsl(25 95% 16% / 0.8); margin-top: 0.125rem;">
            <?php echo CurrencyHelper::format($outstandingRevenue * 0.1); ?> overdue 60+ days
          </div>
        </div>
        <a href="invoicing.php?filter=overdue" class="btn btn-sm btn-warning" style="font-size: 0.6875rem; padding: 0.375rem 0.625rem;">Review</a>
      </div>
    </div>
  </div>
</div>

<?php $hasComplianceWidgets = $showBirWidgets || $showFdaWidgets || $showNotificationsWidgets; ?>
<?php if ($hasComplianceWidgets): ?>
<!-- Compliance (Compact) -->
<div class="dashboard-section">
  <div class="dashboard-section-header" style="margin-bottom: 0.625rem;">
    <h2 class="dashboard-section-title" style="font-size: 1.125rem;">
      <span class="dashboard-section-icon" style="font-size: 1.25rem;">🔒</span>
      Compliance
    </h2>
    <a href="bir-compliance.php" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">All →</a>
  </div>
  
  <div class="dashboard-stats-grid">
    <?php if ($showBirWidgets): ?>
    <!-- BIR Forms Card -->
    <div class="dashboard-stat-card">
      <div class="dashboard-stat-header">
        <div class="dashboard-stat-icon" style="background: <?php echo $pendingBirForms > 0 ? 'hsl(48 96% 89%)' : 'hsl(143 85% 96%)'; ?>; color: <?php echo $pendingBirForms > 0 ? 'hsl(25 95% 16%)' : 'hsl(140 61% 13%)'; ?>;">📋</div>
      </div>
      <div class="dashboard-stat-label">BIR Forms</div>
      <div class="dashboard-stat-value"><?php echo number_format($pendingBirForms); ?></div>
      <div style="font-size: 0.6875rem;">
        <?php if ($pendingBirForms > 0): ?>
          <span style="color: var(--color-warning); font-weight: 600;">Pending</span>
        <?php else: ?>
          <span style="color: var(--color-success); font-weight: 600;">✓ Current</span>
        <?php endif; ?>
      </div>
      <a href="bir-compliance.php" style="font-size: 0.6875rem; color: var(--color-primary); text-decoration: none; margin-top: 0.375rem; display: inline-block;">Manage →</a>
    </div>
    <?php endif; ?>

    <?php if ($showFdaWidgets): ?>
    <!-- FDA Products Card -->
    <div class="dashboard-stat-card">
      <div class="dashboard-stat-header">
        <div class="dashboard-stat-icon" style="background: hsl(143 85% 96%); color: hsl(140 61% 13%);">✅</div>
      </div>
      <div class="dashboard-stat-label">FDA Products</div>
      <div class="dashboard-stat-value"><?php echo number_format($activeProducts); ?></div>
      <div style="font-size: 0.6875rem;">
        <span style="color: var(--color-success); font-weight: 600;">Active</span>
      </div>
      <a href="fda-compliance.php" style="font-size: 0.6875rem; color: var(--color-primary); text-decoration: none; margin-top: 0.375rem; display: inline-block;">View →</a>
    </div>

    <!-- Expiring Products Card -->
    <div class="dashboard-stat-card">
      <div class="dashboard-stat-header">
        <div class="dashboard-stat-icon" style="background: <?php echo $totalExpiringProducts > 0 ? 'hsl(0 86% 97%)' : 'hsl(143 85% 96%)'; ?>; color: <?php echo $totalExpiringProducts > 0 ? 'hsl(0 74% 24%)' : 'hsl(140 61% 13%)'; ?>;">⏰</div>
      </div>
      <div class="dashboard-stat-label">Expiring Soon</div>
      <div class="dashboard-stat-value" style="color: <?php echo $totalExpiringProducts > 0 ? 'var(--color-danger)' : 'inherit'; ?>; "><?php echo number_format($totalExpiringProducts); ?></div>
      <div style="font-size: 0.6875rem;">
        <?php if ($totalExpiringProducts > 0): ?>
          <span style="color: var(--color-danger); font-weight: 600;">30 days</span>
        <?php else: ?>
          <span style="color: var(--color-success);">✓ None</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($showNotificationsWidgets): ?>
    <!-- System Notifications Card -->
    <div class="dashboard-stat-card">
      <div class="dashboard-stat-header">
        <div class="dashboard-stat-icon" style="background: hsl(214 95% 93%); color: hsl(222 47% 17%);">🔔</div>
      </div>
      <div class="dashboard-stat-label">Notifications</div>
      <div class="dashboard-stat-value"><?php echo number_format($notificationSummary['unread']); ?></div>
      <div style="font-size: 0.6875rem;">
        <?php if ($notificationSummary['high_priority'] > 0): ?>
          <span style="color: var(--color-danger); font-weight: 600; "><?php echo $notificationSummary['high_priority']; ?> urgent</span>
        <?php else: ?>
          <span style="color: var(--text-secondary);">None urgent</span>
        <?php endif; ?>
      </div>
      <a href="notifications.php" style="font-size: 0.6875rem; color: var(--color-primary); text-decoration: none; margin-top: 0.375rem; display: inline-block;">View →</a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>


<!-- Performance Metrics (Compact) -->
<div class="dashboard-section">
  <div class="dashboard-section-header" style="margin-bottom: 0.625rem;">
    <h2 class="dashboard-section-title" style="font-size: 1.125rem;">
      <span class="dashboard-section-icon" style="font-size: 1.25rem;">📈</span>
      Performance
    </h2>
    <div class="chart-filters" style="display: flex; gap: 0.25rem;">
      <button onclick="setTimeRange('quarter')" class="btn btn-sm <?php echo $timeRange === 'quarter' ? 'btn-primary' : 'btn-secondary'; ?>" style="<?php echo $timeRange === 'quarter' ? '' : 'opacity: 0.6;'; ?> font-size: 0.6875rem; padding: 0.25rem 0.5rem;" title="View this quarter's data">
        This Quarter
      </button>
      <button onclick="setTimeRange('month')" class="btn btn-sm <?php echo $timeRange === 'month' ? 'btn-primary' : 'btn-secondary'; ?>" style="<?php echo $timeRange === 'month' ? '' : 'opacity: 0.6;'; ?> font-size: 0.6875rem; padding: 0.25rem 0.5rem;" title="View this month's data">
        This Month
      </button>
    </div>
  </div>
  
  <div class="dashboard-widgets-grid">
    <!-- Revenue Performance Widget -->
    <div class="metric-card">
      <div class="metric-header">
        <div>
          <div class="metric-label">Revenue Performance</div>
          <div class="metric-value" style="color: var(--color-success);">
            <?php echo CurrencyHelper::format($totalRevenue); ?>
          </div>
        </div>
        <?php if ($revenueChange != 0): ?>
        <div class="trend-indicator <?php echo $revenueChange > 0 ? 'up' : 'down'; ?>">
          <span class="trend-arrow"><?php echo $revenueChange > 0 ? '↑' : '↓'; ?></span>
          <span><?php echo $revenueChange > 0 ? '+' : ''; ?><?php echo $revenueChange; ?>%</span>
        </div>
        <?php else: ?>
        <div style="font-size: 0.75rem; color: var(--text-secondary);">—</div>
        <?php endif; ?>
      </div>
      
      <!-- Compact Revenue Breakdown -->
      <div style="margin-top: 0.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.375rem;">
          <span style="font-size: 0.75rem; font-weight: 500;">Paid Revenue</span>
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span style="font-family: monospace; font-weight: 700; color: var(--color-success); font-size: 0.75rem;">
              <?php echo CurrencyHelper::format($paidRevenue); ?>
            </span>
            <span style="font-size: 0.6875rem; color: var(--text-secondary);">
              <?php echo $totalRevenue > 0 ? round(($paidRevenue/$totalRevenue)*100) : 0; ?>%
            </span>
          </div>
        </div>
        <div style="font-size: 0.6875rem; color: var(--text-secondary); margin-bottom: 0.375rem;">7-Day Trend</div>
        <div class="mini-chart" style="height: 32px;">
          <div class="mini-chart-bar" style="height: 45%; background: var(--color-success);"></div>
          <div class="mini-chart-bar" style="height: 60%; background: var(--color-success);"></div>
          <div class="mini-chart-bar" style="height: 55%; background: var(--color-success);"></div>
          <div class="mini-chart-bar" style="height: 75%; background: var(--color-success);"></div>
          <div class="mini-chart-bar" style="height: 65%; background: var(--color-success);"></div>
          <div class="mini-chart-bar" style="height: 85%; background: var(--color-success);"></div>
          <div class="mini-chart-bar" style="height: 95%; background: var(--color-success);"></div>
        </div>
      </div>
    </div>
    
    <!-- Expense Breakdown Widget -->
    <div class="metric-card">
      <div class="metric-header">
        <div>
          <div class="metric-label">Top Expenses</div>
          <div class="metric-value" style="color: var(--color-danger);">
            <?php echo CurrencyHelper::format($cashOut); ?>
          </div>
        </div>
        <a href="financial-reports.php?report=expenses" style="font-size: 0.875rem; color: var(--color-primary); text-decoration: none;">View All →</a>
      </div>
      
      <div style="margin-top: 1rem;">
        <?php
          $expenses = [
            ['category' => 'Operating Costs', 'amount' => $cashOut * 0.4, 'icon' => '🏢', 'color' => 'hsl(221 83% 53%)'],
            ['category' => 'Inventory Purchases', 'amount' => $cashOut * 0.3, 'icon' => '📦', 'color' => 'hsl(143 70% 50%)'],
            ['category' => 'Salaries & Wages', 'amount' => $cashOut * 0.2, 'icon' => '👥', 'color' => 'hsl(48 90% 60%)'],
            ['category' => 'Marketing', 'amount' => $cashOut * 0.1, 'icon' => '📊', 'color' => 'hsl(0 80% 60%)'],
          ];
          $maxExpense = max(array_column($expenses, 'amount'));
          
          foreach ($expenses as $expense):
            $percent = $maxExpense > 0 ? ($expense['amount'] / $maxExpense) * 100 : 0;
        ?>
        <div class="expense-item">
          <div class="expense-icon" style="background: <?php echo $expense['color']; ?>20;">
            <span style="font-size: 1.25rem;"><?php echo $expense['icon']; ?></span>
          </div>
          <div class="expense-details">
            <div class="expense-category"><?php echo $expense['category']; ?></div>
            <div class="expense-bar">
              <div class="expense-bar-fill" style="width: <?php echo $percent; ?>%; background: <?php echo $expense['color']; ?>;"></div>
            </div>
          </div>
          <div class="expense-amount"><?php echo CurrencyHelper::format($expense['amount']); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Expense Summary -->
      <div style="margin-top: 0.75rem; padding-top: 0.625rem; border-top: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 0.6875rem; color: var(--text-secondary);">Total</div>
            <div style="font-size: 1rem; font-weight: 700; color: var(--color-danger);">
              <?php echo CurrencyHelper::format($cashOut); ?>
            </div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 0.6875rem; color: var(--text-secondary);">vs Budget</div>
            <?php 
              $budgetExpense = $cashOut * 1.05;
              $budgetVariance = 0;
              if ($budgetExpense > 0) {
                $budgetVariance = round((($cashOut - $budgetExpense) / $budgetExpense) * 100, 1);
              }
              $budgetDirection = $budgetVariance >= 0 ? 'up' : 'down';
              $budgetArrow = $budgetVariance >= 0 ? '↑' : '↓';
            ?>
            <?php if ($cashOut > 0): ?>
            <span class="trend-indicator <?php echo $budgetDirection; ?>" style="font-size: 0.6875rem; padding: 0.125rem 0.375rem;">
              <span class="trend-arrow" style="font-size: 0.875rem;"><?php echo $budgetArrow; ?></span>
              <span><?php echo $budgetVariance >= 0 ? '+' : ''; ?><?php echo number_format(abs($budgetVariance), 1); ?>%</span>
            </span>
            <?php else: ?>
            <span style="font-size: 0.6875rem; color: var(--text-secondary); padding: 0.125rem 0.375rem;">N/A</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Activity (Compact) -->
<div class="dashboard-section">
  <div class="dashboard-section-header" style="margin-bottom: 0.625rem;">
    <h2 class="dashboard-section-title" style="font-size: 1.125rem;">
      <span class="dashboard-section-icon" style="font-size: 1.25rem;">🕒</span>
      Recent Activity
    </h2>
  </div>
  
  <div class="dashboard-widgets-grid">
    <!-- Recent Inventory -->
    <div class="chart-container">
      <div class="chart-header" style="margin-bottom: 0.75rem;">
        <h3 class="chart-title" style="font-size: 1rem;">Inventory Updates</h3>
        <a href="inventory-list.php" style="font-size: 0.6875rem; color: var(--color-primary); text-decoration: none;">All →</a>
      </div>
      <div>
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
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
          <?php foreach ($recentItems as $item): ?>
          <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); transition: all 0.2s ease;" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--border-color)'">
            <div style="width: 40px; height: 40px; background: <?php echo $item['quantity'] <= 5 ? 'hsl(48 96% 89%)' : 'hsl(143 85% 96%)'; ?>; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
              📦
            </div>
            <div style="flex: 1; min-width: 0;">
              <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($item['name']); ?></div>
              <div style="font-size: 0.75rem; color: var(--text-secondary);">
                Qty: <span style="font-weight: 600; <?php echo $item['quantity'] <= 5 ? 'color: var(--color-warning);' : ''; ?>"><?php echo $item['quantity']; ?></span> • 
                <?php echo htmlspecialchars($item['type']); ?>
              </div>
            </div>
            <span class="badge badge-<?php echo $item['quantity'] <= 5 ? 'warning' : 'success'; ?>">
              <?php echo $item['quantity'] <= 5 ? 'Low' : 'Good'; ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      </div>
    </div>

    <!-- Stock Alerts -->
    <div class="chart-container">
      <div class="chart-header" style="margin-bottom: 0.75rem;">
        <h3 class="chart-title" style="font-size: 1rem;">Stock Alerts</h3>
        <a href="inventory-list.php?filter=low_stock" style="font-size: 0.6875rem; color: var(--color-primary); text-decoration: none;">All →</a>
      </div>
      <div>
      <?php if ($lowStockItems === 0): ?>
        <div class="empty-state">
          <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none">
            <path d="M12 9L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <p class="empty-state-title">All stock levels good</p>
          <p class="empty-state-description">No items require immediate attention</p>
        </div>
      <?php else: ?>
        <div style="display: flex; align-items: center; gap: 1rem; padding: 1.25rem; background: hsl(48 96% 89%); border: 1px solid hsl(25 95% 16% / 0.2); border-radius: var(--radius-md); margin-bottom: 1rem;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="color: hsl(25 95% 16%); flex-shrink: 0;">
            <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <div style="flex: 1;">
            <div style="font-weight: 600; color: hsl(25 95% 16%); margin-bottom: 0.25rem;"><?php echo $lowStockItems; ?> Item<?php echo $lowStockItems > 1 ? 's' : ''; ?> Need Attention</div>
            <div style="font-size: 0.875rem; color: hsl(25 95% 16% / 0.8);">Running low on stock - reorder soon</div>
          </div>
          <a href="inventory-list.php?filter=low_stock" class="btn btn-secondary" style="font-size: 0.6875rem; padding: 0.375rem 0.5rem;">View Low Stock Items</a>
        </div>
      <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Recent Notifications Widget -->
<?php if ($showNotificationsWidgets && !empty($recentNotifications)): ?>
<div class="dashboard-section">
  <div class="dashboard-section-header">
    <h2 class="dashboard-section-title">
      <span class="dashboard-section-icon">🔔</span>
      Recent Notifications
    </h2>
    <a href="notifications.php" class="btn btn-secondary btn-sm">View All Notifications →</a>
  </div>
  
  <div class="chart-container">
    <div>
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
</div>
<?php endif; ?>


<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/../../components/layout.php';
?>

