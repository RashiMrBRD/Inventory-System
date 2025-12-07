<?php
/**
 * Analytics Dashboard - Phase 6
 * Interactive charts and real-time visualizations
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Inventory;
use App\Model\Invoice;
use App\Model\Quotation;
use App\Model\Order;
use App\Model\Project;
use App\Model\Shipment;
use App\Model\BirForm;
use App\Model\FdaProduct;
use App\Helper\CurrencyHelper;
use App\Helper\NotificationHelper;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

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

// BIR widgets visibility based on sidebar settings
$showBirWidgets = !in_array('bir-compliance', $sidebarHiddenItems, true);

// FDA widgets visibility based on sidebar settings
$showFdaWidgets = !in_array('fda-compliance', $sidebarHiddenItems, true);
$hasComplianceCharts = $showBirWidgets || $showFdaWidgets;

// Notifications widgets visibility based on sidebar settings
$showNotificationsWidgets = !in_array('notifications', $sidebarHiddenItems, true);

$userId = $user['id'] ?? 'admin';

// ============================================
// TIME RANGE FILTER
// ============================================
$timeRange = $_GET['range'] ?? '30d';
$validRanges = ['7d', '30d', '90d', '1y'];
if (!in_array($timeRange, $validRanges)) {
    $timeRange = '30d';
}

// Calculate date ranges
$rangeMap = [
    '7d' => 7,
    '30d' => 30,
    '90d' => 90,
    '1y' => 365
];
$daysBack = $rangeMap[$timeRange];
$startDate = new \DateTimeImmutable("-$daysBack days");
$endDate = new \DateTimeImmutable();

// Previous period for comparison
$prevStartDate = new \DateTimeImmutable("-" . ($daysBack * 2) . " days");
$prevEndDate = $startDate;

// Helper function to filter data by date range
function isInDateRange($dateValue, $start, $end) {
    if (!$dateValue) return false;
    $ts = is_string($dateValue) ? strtotime($dateValue) : 
          (is_object($dateValue) && method_exists($dateValue, 'toDateTime') ? 
           $dateValue->toDateTime()->getTimestamp() : null);
    if (!$ts) return false;
    return $ts >= $start->getTimestamp() && $ts <= $end->getTimestamp();
}

// ============================================
// INVENTORY ANALYTICS (Time-Range Filtered)
// ============================================
$inventoryModel = new Inventory();
try {
    $allItems = $inventoryModel->getAll();
    
    // Current period inventory stats (ALL items, not filtered by date)
    $totalItems = count($allItems);
    $lowStockItems = 0;
    $outOfStockItems = 0;
    $inventoryValue = 0;
    
    // Items added in current period (for trend calculation)
    $itemsAddedThisPeriod = 0;
    $itemsAddedPrevPeriod = 0;
    
    foreach ($allItems as $item) {
        $createdDate = $item['created_at'] ?? $item['date_added'] ?? $item['date'] ?? null;
        $quantity = $item['quantity'] ?? 0;
        $price = (float)($item['sell_price'] ?? $item['price'] ?? 0);
        
        // Calculate current inventory value from ALL items
        $inventoryValue += $quantity * $price;
        
        // Stock level classification
        if ($quantity == 0) $outOfStockItems++;
        elseif ($quantity <= 5) $lowStockItems++;
        
        // Count items added in time periods (for trend)
        if (isInDateRange($createdDate, $startDate, $endDate)) {
            $itemsAddedThisPeriod++;
        }
        if (isInDateRange($createdDate, $prevStartDate, $prevEndDate)) {
            $itemsAddedPrevPeriod++;
        }
    }
    
    // Calculate trend based on items ADDED (not total count)
    $inventoryTrend = $itemsAddedPrevPeriod > 0 ? 
        round((($itemsAddedThisPeriod - $itemsAddedPrevPeriod) / $itemsAddedPrevPeriod) * 100, 1) : 
        ($itemsAddedThisPeriod > 0 ? 100 : 0);
    
    // Inventory trend data points
    $dailyCounts = $inventoryModel->getDailyAddedCounts($daysBack);
    $inventoryTrendLabels = [];
    $inventoryTrendData = [];
    
    $dataPoints = min($daysBack, 30); // Max 30 points for readability
    $interval = max(1, floor($daysBack / $dataPoints));
    
    for ($i = $daysBack - 1; $i >= 0; $i -= $interval) {
        $d = new \DateTimeImmutable("-$i days");
        $key = $d->format('Y-m-d');
        $inventoryTrendLabels[] = $d->format($timeRange === '1y' ? 'M' : 'M j');
        $inventoryTrendData[] = (int)($dailyCounts[$key] ?? 0);
    }
    
    // Inventory distribution
    $typeData = [];
    $stockLevelData = ['In Stock' => 0, 'Low Stock' => 0, 'Out of Stock' => 0];
    foreach ($allItems as $item) {
        $type = $item['type'] ?? 'Unknown';
        $quantity = $item['quantity'] ?? 0;
        if (!isset($typeData[$type])) $typeData[$type] = 0;
        $typeData[$type]++;
        
        if ($quantity == 0) $stockLevelData['Out of Stock']++;
        elseif ($quantity <= 5) $stockLevelData['Low Stock']++;
        else $stockLevelData['In Stock']++;
    }
} catch (Exception $e) {
    $totalItems = $lowStockItems = $outOfStockItems = 0;
    $inventoryTrendLabels = $inventoryTrendData = [];
    $typeData = $stockLevelData = [];
}

// ============================================
// SALES & REVENUE ANALYTICS
// ============================================
$invoiceModel = new Invoice();
$quotationModel = new Quotation();
$orderModel = new Order();

try {
    $invoices = $invoiceModel->getAll();
    
    // ALL invoices (not time-filtered)
    $totalRevenue = 0;
    $paidRevenue = 0;
    $outstandingRevenue = 0;
    $invoiceCount = count($invoices);
    
    // Time-filtered for trend comparison
    $revenueInPeriod = 0;
    $prevTotalRevenue = 0;
    $prevPaidRevenue = 0;
    
    // Revenue trend data
    $revenueByPeriod = [];
    $dataPoints = $timeRange === '1y' ? 12 : ($timeRange === '90d' ? 12 : min($daysBack, 30));
    $periodType = $timeRange === '1y' ? 'month' : 'day';
    
    foreach ($invoices as $inv) {
        $date = $inv['date'] ?? null;
        $total = (float)($inv['total'] ?? 0);
        $status = $inv['status'] ?? '';
        
        // Calculate all-time totals
        $totalRevenue += $total;
        if ($status === 'paid') {
            $paidRevenue += $total;
        } else {
            $outstandingRevenue += $total;
        }
        
        // Track revenue in current period for trends
        if (isInDateRange($date, $startDate, $endDate)) {
            $revenueInPeriod += $total;
        }
        
        // Previous period for comparison
        if (isInDateRange($date, $prevStartDate, $prevEndDate)) {
            $prevTotalRevenue += $total;
            if ($status === 'paid') {
                $prevPaidRevenue += $total;
            }
        }
    }
    
    // Calculate trends
    $revenueTrend = $prevTotalRevenue > 0 ? 
        round((($totalRevenue - $prevTotalRevenue) / $prevTotalRevenue) * 100, 1) : 0;
    $collectionRateTrend = $prevPaidRevenue > 0 && $prevTotalRevenue > 0 ? 
        round(((($paidRevenue/$totalRevenue) - ($prevPaidRevenue/$prevTotalRevenue)) / ($prevPaidRevenue/$prevTotalRevenue)) * 100, 1) : 0;
    
    // Build revenue chart data
    $monthLabels = [];
    $revenueData = [];
    
    if ($timeRange === '1y') {
        // Monthly grouping for 1 year
        for ($i = 11; $i >= 0; $i--) {
            $month = new \DateTimeImmutable("-$i months");
            $monthKey = $month->format('Y-m');
            $monthLabels[] = $month->format('M y');
            $revenueByPeriod[$monthKey] = 0;
        }
        foreach ($invoices as $inv) {
            $date = $inv['date'] ?? null;
            if (isInDateRange($date, $startDate, $endDate)) {
                $ts = is_string($date) ? strtotime($date) : 
                      (is_object($date) && method_exists($date, 'toDateTime') ? $date->toDateTime()->getTimestamp() : null);
                if ($ts) {
                    $monthKey = date('Y-m', $ts);
                    if (isset($revenueByPeriod[$monthKey])) {
                        $revenueByPeriod[$monthKey] += (float)($inv['total'] ?? 0);
                    }
                }
            }
        }
        $revenueData = array_values($revenueByPeriod);
    } else {
        // Daily/weekly grouping for shorter periods
        $interval = $timeRange === '90d' ? 7 : 1; // Weekly for 90d, daily for others
        for ($i = $daysBack - 1; $i >= 0; $i -= $interval) {
            $d = new \DateTimeImmutable("-$i days");
            $key = $d->format('Y-m-d');
            $monthLabels[] = $d->format('M j');
            $revenueByPeriod[$key] = 0;
        }
        foreach ($invoices as $inv) {
            $date = $inv['date'] ?? null;
            if (isInDateRange($date, $startDate, $endDate)) {
                $ts = is_string($date) ? strtotime($date) : 
                      (is_object($date) && method_exists($date, 'toDateTime') ? $date->toDateTime()->getTimestamp() : null);
                if ($ts) {
                    $dayKey = date('Y-m-d', $ts);
                    if (isset($revenueByPeriod[$dayKey])) {
                        $revenueByPeriod[$dayKey] += (float)($inv['total'] ?? 0);
                    }
                }
            }
        }
        $revenueData = array_values($revenueByPeriod);
    }
    
    // Orders by type
    $orders = $orderModel->getAll();
    $ordersByType = ['Sales' => 0, 'Purchase' => 0];
    foreach ($orders as $order) {
        $type = $order['type'] ?? 'Sales';
        if (!isset($ordersByType[$type])) $ordersByType[$type] = 0;
        $ordersByType[$type]++;
    }
    
    // Quotation analytics (show ALL quotations)
    $quotations = $quotationModel->getAll();
    $quotationCount = count($quotations);
    $approvedCount = 0;
    $pendingCount = 0;
    $convertedCount = 0;
    
    // Time-filtered counts for trends
    $quotationsInPeriod = 0;
    $approvedInPeriod = 0;
    $prevQuotationCount = 0;
    $prevApprovedCount = 0;
    
    foreach ($quotations as $q) {
        $date = $q['created_at'] ?? $q['date'] ?? null;
        $status = $q['status'] ?? '';
        
        // All-time status counts
        if ($status === 'approved') $approvedCount++;
        if ($status === 'pending') $pendingCount++;
        if ($status === 'converted') $convertedCount++;
        
        // Current period counts for trends
        if (isInDateRange($date, $startDate, $endDate)) {
            $quotationsInPeriod++;
            if ($status === 'approved') $approvedInPeriod++;
        }
        
        // Previous period for comparison
        if (isInDateRange($date, $prevStartDate, $prevEndDate)) {
            $prevQuotationCount++;
            if ($status === 'approved') $prevApprovedCount++;
        }
    }
    
    // Calculate conversion rate from all-time data
    $conversionRate = $quotationCount > 0 ? round(($approvedCount / $quotationCount) * 100, 1) : 0;
    $prevConversionRate = $prevQuotationCount > 0 ? round(($prevApprovedCount / $prevQuotationCount) * 100, 1) : 0;
    $conversionTrend = $prevConversionRate > 0 ? round((($conversionRate - $prevConversionRate) / $prevConversionRate) * 100, 1) : 0;
    
} catch (Exception $e) {
    $totalRevenue = $paidRevenue = $outstandingRevenue = $revenueInPeriod = 0;
    $monthLabels = $revenueData = $ordersByType = [];
    $conversionRate = $revenueTrend = $collectionRateTrend = $conversionTrend = 0;
    $invoiceCount = $quotationCount = $approvedCount = $pendingCount = $convertedCount = 0;
    $quotationsInPeriod = $approvedInPeriod = 0;
}

// ============================================
// OPERATIONS ANALYTICS
// ============================================
$projectModel = new Project();
$shipmentModel = new Shipment();

// Initialize operations analytics defaults to avoid undefined variables
$projects = [];
$projectsByStatus = ['active' => 0, 'completed' => 0, 'on_hold' => 0];
$totalBudget = $totalSpent = 0;
$totalProjects = 0;
$shipmentsByStatus = [];

try {
    $projects = $projectModel->getAll();
    $projectsByStatus = ['active' => 0, 'completed' => 0, 'on_hold' => 0];
    $totalBudget = $totalSpent = 0;
    $totalProjects = count($projects);
    foreach ($projects as $proj) {
        $status = $proj['status'] ?? 'active';
        if (!isset($projectsByStatus[$status])) $projectsByStatus[$status] = 0;
        $projectsByStatus[$status]++;
        $totalBudget += (float)($proj['budget'] ?? 0);
        $totalSpent += (float)($proj['spent'] ?? 0);
    }
    $budgetUtilization = $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0;
    
    $shipments = $shipmentModel->getAll();
    $shipmentsByStatus = [];
    foreach ($shipments as $ship) {
        $status = $ship['status'] ?? 'pending';
        if (!isset($shipmentsByStatus[$status])) $shipmentsByStatus[$status] = 0;
        $shipmentsByStatus[$status]++;
    }
} catch (Exception $e) {
    $projects = [];
    $projectsByStatus = ['active' => 0, 'completed' => 0, 'on_hold' => 0];
    $totalBudget = $totalSpent = 0;
    $totalProjects = 0;
    $shipmentsByStatus = [];
    $budgetUtilization = 0;
}

// ============================================
// COMPLIANCE ANALYTICS
// ============================================
$birModel = new BirForm();
$fdaModel = new FdaProduct();

try {
    $birForms = $birModel->getRecentForms(100);
    $birByStatus = ['filed' => 0, 'pending' => 0];
    foreach ($birForms as $form) {
        $status = $form['status'] ?? 'pending';
        if (!isset($birByStatus[$status])) $birByStatus[$status] = 0;
        $birByStatus[$status]++;
    }
    $complianceRate = count($birForms) > 0 ? round(($birByStatus['filed'] / count($birForms)) * 100, 1) : 0;
    
    $expiringProducts = $fdaModel->getExpiringProducts(90);
    $expiryDistribution = ['0-30 days' => 0, '31-60 days' => 0, '61-90 days' => 0];
    foreach ($expiringProducts as $prod) {
        $daysLeft = $prod['days_left'] ?? 0;
        if ($daysLeft <= 30) $expiryDistribution['0-30 days']++;
        elseif ($daysLeft <= 60) $expiryDistribution['31-60 days']++;
        else $expiryDistribution['61-90 days']++;
    }
} catch (Exception $e) {
    $birByStatus = $expiryDistribution = [];
    $complianceRate = 0;
}

// ============================================
// NOTIFICATIONS ANALYTICS
// ============================================
$notificationSummary = NotificationHelper::getSummary($userId);

$pageTitle = 'Analytics Dashboard';
ob_start();
?>

<style>
/* Ultra-Compact Analytics Dashboard - Shadcn Inspired */
.analytics-section {
  margin-bottom: 1rem;
}

.analytics-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.625rem;
}

.analytics-section-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
}

/* Compact KPI Grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.625rem;
  margin-bottom: 1rem;
}

.kpi-card {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 0.75rem;
  transition: all 0.2s ease;
}

.kpi-card:hover {
  border-color: var(--color-primary);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.kpi-label {
  font-size: 0.6875rem;
  color: var(--text-secondary);
  font-weight: 500;
  margin-bottom: 0.375rem;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.kpi-value {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 0.25rem;
}

.kpi-meta {
  font-size: 0.6875rem;
  color: var(--text-secondary);
  margin-top: 0.25rem;
}

/* Compact Chart Container */
.chart-card {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 0.875rem;
  margin-bottom: 0.75rem;
}

.chart-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.625rem;
}

.chart-card-title {
  font-size: 0.875rem;
  font-weight: 600;
  margin-bottom: 0.125rem;
}

.chart-card-description {
  font-size: 0.6875rem;
  color: var(--text-secondary);
}

.chart-canvas-compact {
  height: 180px !important;
  max-height: 180px !important;
}

.chart-canvas-full {
  height: 220px !important;
  max-height: 220px !important;
}

/* Compact Grid System */
.grid-analytics-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
}

.grid-analytics-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.625rem;
}

/* Time Range Filters */
.time-filters {
  display: flex;
  gap: 0.375rem;
  align-items: center;
}

.time-filter-btn {
  font-size: 0.6875rem;
  padding: 0.25rem 0.5rem;
  border: 1px solid var(--border-color);
  background: var(--bg-primary);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.2s;
}

.time-filter-btn.active {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.time-filter-btn:hover:not(.active) {
  background: var(--bg-secondary);
}

/* Trend Indicators */
.trend-indicator {
  display: inline-flex;
  align-items: center;
  gap: 0.125rem;
  font-weight: 600;
  border-radius: var(--radius-sm);
}

.trend-indicator.up {
  background: hsl(143 85% 96%);
  color: hsl(140 61% 13%);
}

.trend-indicator.down {
  background: hsl(0 86% 97%);
  color: hsl(0 74% 24%);
}

.trend-arrow {
  font-size: 0.875rem;
  line-height: 1;
}

/* KPI Card Hover Effect */
.kpi-card:hover {
  transform: translateY(-2px);
}

/* KPI Sparkline Charts */
.kpi-sparkline {
  width: 100% !important;
  height: 32px !important;
  margin: 0.5rem 0 0.25rem 0;
  display: block;
}

/* Chart Loading State */
.chart-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 180px;
  color: var(--text-secondary);
  font-size: 0.875rem;
}

/* Data Point Labels */
.data-label {
  font-size: 0.6875rem;
  color: var(--text-secondary);
  margin-top: 0.25rem;
}

/* Color Variables for Charts */
:root {
  --color-purple: #a855f7;
  --color-pink: #ec4899;
  --color-teal: #14b8a6;
  --color-orange: #f97316;
}

/* Shadcn-style Range Indicator Badge */
.range-indicator-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  background: hsl(214 95% 93%);
  color: hsl(222 47% 17%);
  border: 1px solid hsl(214 95% 80%);
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
  transition: all 0.2s ease;
}

.range-indicator-badge:hover {
  background: hsl(214 95% 88%);
  border-color: hsl(214 95% 70%);
  box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.range-icon {
  font-size: 1rem;
  line-height: 1;
}

.range-label {
  line-height: 1;
  white-space: nowrap;
}

/* Responsive */
@media (max-width: 1200px) {
  .grid-analytics-3 { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .grid-analytics-2, .grid-analytics-3 { grid-template-columns: 1fr; }
  .chart-canvas-compact { height: 160px !important; }
  .chart-canvas-full { height: 200px !important; }
}

@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Compact Header -->
<div class="content-header" style="margin-bottom: 1rem;">
  <div>
    <nav class="breadcrumb">
      <a href="dashboard.php" class="breadcrumb-link">Dashboard</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current">Analytics</span>
    </nav>
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <h1 class="content-title" style="font-size: 1.5rem;">Analytics & Insights</h1>
      <?php
        $rangeDisplayMap = [
          '7d' => 'Last 7 Days',
          '30d' => 'Last 30 Days',
          '90d' => 'Last 90 Days',
          '1y' => 'Last Year'
        ];
        $rangeDisplay = $rangeDisplayMap[$timeRange] ?? 'Last 30 Days';
      ?>
      <div class="range-indicator-badge" title="Current analytics period">
        <span class="range-icon">📊</span>
        <span class="range-label"><?php echo $rangeDisplay; ?></span>
      </div>
    </div>
  </div>
  <div class="content-actions">
    <div class="time-filters">
      <button onclick="setAnalyticsRange('7d')" class="time-filter-btn <?php echo $timeRange === '7d' ? 'active' : ''; ?>" title="Last 7 days">7D</button>
      <button onclick="setAnalyticsRange('30d')" class="time-filter-btn <?php echo $timeRange === '30d' ? 'active' : ''; ?>" title="Last 30 days">30D</button>
      <button onclick="setAnalyticsRange('90d')" class="time-filter-btn <?php echo $timeRange === '90d' ? 'active' : ''; ?>" title="Last 90 days">90D</button>
      <button onclick="setAnalyticsRange('1y')" class="time-filter-btn <?php echo $timeRange === '1y' ? 'active' : ''; ?>" title="Last 12 months">1Y</button>
    </div>
    <button class="btn btn-secondary btn-sm" onclick="refreshAnalytics()" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
        <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C13.8214 3 15.5291 3.57138 16.9497 4.55313M21 3V8M21 8H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Refresh
    </button>
    <a href="dashboard.php" class="btn btn-primary btn-sm" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
        <path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="currentColor" stroke-width="2"/>
      </svg>
      Home
    </a>
  </div>
</div>

<!-- ========================================
     KEY PERFORMANCE INDICATORS (KPIs)
     ======================================== -->

<!-- Financial KPIs (Ultra Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">💰 Financial Performance</h2>
  </div>
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">Total Revenue</div>
      <div class="kpi-value" style="color: var(--color-success);"><?php echo CurrencyHelper::format($totalRevenue); ?></div>
      <canvas id="sparkRevenue" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">
        <?php if ($revenueTrend != 0): ?>
          <span class="trend-indicator <?php echo $revenueTrend > 0 ? 'up' : 'down'; ?>" style="font-size: 0.6875rem; padding: 0.125rem 0.375rem;">
            <span class="trend-arrow"><?php echo $revenueTrend > 0 ? '↑' : '↓'; ?></span>
            <span><?php echo abs($revenueTrend); ?>%</span>
          </span>
        <?php endif; ?>
        <span style="margin-left: 0.25rem;"><?php echo $invoiceCount; ?> invoices</span>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Collection Rate</div>
      <div class="kpi-value" style="color: var(--color-success);"><?php echo $totalRevenue > 0 ? round(($paidRevenue/$totalRevenue)*100, 1) : 0; ?>%</div>
      <canvas id="sparkCollection" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">
        <?php if ($collectionRateTrend != 0): ?>
          <span class="trend-indicator <?php echo $collectionRateTrend > 0 ? 'up' : 'down'; ?>" style="font-size: 0.6875rem; padding: 0.125rem 0.375rem;">
            <span class="trend-arrow"><?php echo $collectionRateTrend > 0 ? '↑' : '↓'; ?></span>
            <span><?php echo abs($collectionRateTrend); ?>%</span>
          </span>
        <?php endif; ?>
        <span style="margin-left: 0.25rem;"><?php echo CurrencyHelper::format($paidRevenue); ?></span>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Outstanding</div>
      <div class="kpi-value" style="color: var(--color-warning);"><?php echo CurrencyHelper::format($outstandingRevenue); ?></div>
      <canvas id="sparkOutstanding" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">
        <span class="badge badge-warning" style="font-size: 0.625rem; padding: 0.125rem 0.375rem;">Pending</span>
        <span style="margin-left: 0.25rem;"><?php echo $totalRevenue > 0 ? round(($outstandingRevenue/$totalRevenue)*100, 1) : 0; ?>%</span>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Conversion</div>
      <div class="kpi-value" style="color: var(--color-primary);"><?php echo $conversionRate; ?>%</div>
      <canvas id="sparkConversion" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">
        <?php if ($conversionTrend != 0): ?>
          <span class="trend-indicator <?php echo $conversionTrend > 0 ? 'up' : 'down'; ?>" style="font-size: 0.6875rem; padding: 0.125rem 0.375rem;">
            <span class="trend-arrow"><?php echo $conversionTrend > 0 ? '↑' : '↓'; ?></span>
            <span><?php echo abs($conversionTrend); ?>%</span>
          </span>
        <?php endif; ?>
        <span style="margin-left: 0.25rem;"><?php echo $quotationCount; ?> quotes</span>
      </div>
    </div>
    
    <!-- NEW: Average Invoice Value (Xero Feature) -->
    <div class="kpi-card">
      <div class="kpi-label">Avg Invoice</div>
      <div class="kpi-value" style="color: var(--color-info);"><?php echo $invoiceCount > 0 ? CurrencyHelper::format($totalRevenue / $invoiceCount) : CurrencyHelper::format(0); ?></div>
      <canvas id="sparkAvgInvoice" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">Per transaction</div>
    </div>
    
    <!-- NEW: Inventory Value (QuickBooks Feature) -->
    <div class="kpi-card">
      <div class="kpi-label">Inventory Value</div>
      <div class="kpi-value" style="color: var(--color-purple);"><?php echo CurrencyHelper::format($inventoryValue); ?></div>
      <canvas id="sparkInventoryValue" class="kpi-sparkline"></canvas>
      <div class="kpi-meta"><?php echo $totalItems; ?> items</div>
    </div>
  </div>
</div>

<!-- Operations KPIs (Ultra Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">📊 Operations Performance</h2>
  </div>
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">Inventory Items</div>
      <div class="kpi-value"><?php echo number_format($totalItems); ?></div>
      <canvas id="sparkInventory" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">
        <?php if ($inventoryTrend != 0): ?>
          <span class="trend-indicator <?php echo $inventoryTrend > 0 ? 'up' : 'down'; ?>" style="font-size: 0.6875rem; padding: 0.125rem 0.375rem;">
            <span class="trend-arrow"><?php echo $inventoryTrend > 0 ? '↑' : '↓'; ?></span>
            <span><?php echo abs($inventoryTrend); ?>%</span>
          </span>
        <?php endif; ?>
        <span style="margin-left: 0.25rem;">added</span>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Stock Health</div>
      <div class="kpi-value" style="color: <?php echo $lowStockItems > 0 ? 'var(--color-warning)' : 'var(--color-success)'; ?>;"><?php echo $totalItems > 0 ? round((($totalItems - $lowStockItems - $outOfStockItems) / $totalItems) * 100, 1) : 0; ?>%</div>
      <canvas id="sparkStockHealth" class="kpi-sparkline"></canvas>
      <div class="kpi-meta"><?php echo number_format($lowStockItems); ?> low, <?php echo number_format($outOfStockItems); ?> out</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Budget Use</div>
      <div class="kpi-value" style="color: var(--color-primary);"><?php echo $budgetUtilization; ?>%</div>
      <canvas id="sparkBudgetUse" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">Project spend</div>
    </div>
    <?php if ($showBirWidgets): ?>
    <div class="kpi-card">
      <div class="kpi-label">Compliance</div>
      <div class="kpi-value" style="color: <?php echo $complianceRate >= 90 ? 'var(--color-success)' : 'var(--color-warning)'; ?>;"><?php echo $complianceRate; ?>%</div>
      <canvas id="sparkCompliance" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">BIR forms</div>
    </div>
    <?php endif; ?>
    
    <!-- NEW: Stock Turnover Rate (Xero Feature) -->
    <div class="kpi-card">
      <div class="kpi-label">Turnover</div>
      <div class="kpi-value" style="color: var(--color-info);"><?php echo $totalItems > 0 ? round(($invoiceCount / $totalItems) * 100, 1) : 0; ?>%</div>
      <canvas id="sparkTurnover" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">Stock movement</div>
    </div>
    
    <!-- NEW: Active Orders (QuickBooks Feature) -->
    <div class="kpi-card">
      <div class="kpi-label">Orders</div>
      <div class="kpi-value" style="color: var(--color-success);"><?php echo number_format(array_sum($ordersByType)); ?></div>
      <canvas id="sparkOrders" class="kpi-sparkline"></canvas>
      <div class="kpi-meta"><?php echo $ordersByType['Sales'] ?? 0; ?> sales / <?php echo $ordersByType['Purchase'] ?? 0; ?> purchase</div>
    </div>
  </div>
</div>

<!-- Projects Overview KPIs (New Section) -->
<?php if ($showProjectsWidgets): ?>
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">🚀 Projects Overview</h2>
  </div>
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">Total Projects</div>
      <div class="kpi-value" style="color: var(--color-primary);"><?php 
        $totalProjects = count($projects ?? []);
        echo number_format($totalProjects); 
      ?></div>
      <canvas id="sparkProjects" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">All time</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Active Projects</div>
      <div class="kpi-value" style="color: var(--color-success);"><?php echo number_format($projectsByStatus['active'] ?? 0); ?></div>
      <canvas id="sparkActiveProjects" class="kpi-sparkline"></canvas>
      <div class="kpi-meta"><?php echo $totalProjects > 0 ? round((($projectsByStatus['active'] ?? 0) / $totalProjects) * 100, 1) : 0; ?>% of total</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Total Budget</div>
      <div class="kpi-value" style="color: var(--color-info);"><?php echo CurrencyHelper::format($totalBudget); ?></div>
      <canvas id="sparkBudget" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">Allocated funds</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Budget Spent</div>
      <div class="kpi-value" style="color: <?php echo $budgetUtilization > 90 ? 'var(--color-warning)' : 'var(--color-success)'; ?>;"><?php echo CurrencyHelper::format($totalSpent); ?></div>
      <canvas id="sparkBudgetSpent" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">
        <span class="trend-indicator <?php echo $budgetUtilization > 90 ? 'warning' : 'up'; ?>" style="font-size: 0.6875rem; padding: 0.125rem 0.375rem;">
          <span><?php echo $budgetUtilization; ?>%</span>
        </span>
        <span style="margin-left: 0.25rem;">utilization</span>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Completed</div>
      <div class="kpi-value" style="color: var(--color-success);"><?php echo number_format($projectsByStatus['completed'] ?? 0); ?></div>
      <canvas id="sparkCompleted" class="kpi-sparkline"></canvas>
      <div class="kpi-meta"><?php echo $totalProjects > 0 ? round((($projectsByStatus['completed'] ?? 0) / $totalProjects) * 100, 1) : 0; ?>% completion</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">On Hold</div>
      <div class="kpi-value" style="color: var(--color-warning);"><?php echo number_format($projectsByStatus['on_hold'] ?? 0); ?></div>
      <canvas id="sparkOnHold" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">Paused</div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- System Health KPIs (Ultra Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">🔔 System & Alerts</h2>
  </div>
  <div class="kpi-grid">
    <?php if ($showNotificationsWidgets): ?>
    <div class="kpi-card">
      <div class="kpi-label">Active Alerts</div>
      <div class="kpi-value" style="color: var(--color-warning);"><?php echo number_format($notificationSummary['unread']); ?></div>
      <canvas id="sparkAlerts" class="kpi-sparkline"></canvas>
      <div class="kpi-meta"><span class="badge badge-warning" style="font-size: 0.625rem; padding: 0.125rem 0.375rem;"><?php echo $notificationSummary['high_priority']; ?> urgent</span></div>
    </div>
    <?php endif; ?>
    <?php if ($showFdaWidgets): ?>
    <div class="kpi-card">
      <div class="kpi-label">FDA Expiring</div>
      <div class="kpi-value" style="color: var(--color-danger);"><?php echo number_format($expiryDistribution['0-30 days'] ?? 0); ?></div>
      <canvas id="sparkFDAExpiring" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">Next 30 days</div>
    </div>
    <?php endif; ?>
    <div class="kpi-card">
      <div class="kpi-label">Avg Project Value</div>
      <div class="kpi-value" style="color: var(--color-info);"><?php echo $totalProjects > 0 ? CurrencyHelper::format($totalBudget / $totalProjects) : CurrencyHelper::format(0); ?></div>
      <canvas id="sparkAvgProjectValue" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">Per project</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">In Transit</div>
      <div class="kpi-value" style="color: var(--color-primary);"><?php echo number_format($shipmentsByStatus['in_transit'] ?? 0); ?></div>
      <canvas id="sparkInTransit" class="kpi-sparkline"></canvas>
      <div class="kpi-meta">Shipments</div>
    </div>
  </div>
</div>

<!-- ========================================
     DATA VISUALIZATIONS & CHARTS
     ======================================== -->

<!-- Revenue Trend (Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">📈 Revenue Trends</h2>
  </div>
  <div class="chart-card">
    <div class="chart-card-header">
      <div>
        <h3 class="chart-card-title">Revenue by Month (6 Months)</h3>
        <p class="chart-card-description">Growth & patterns</p>
      </div>
    </div>
    <canvas id="revenueChart" class="chart-canvas-full"></canvas>
  </div>
</div>

<!-- Distribution Charts (Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">🎯 Distribution Analysis</h2>
  </div>
  <div class="grid-analytics-2">
    <!-- Orders by Type -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3 class="chart-card-title">Orders by Type</h3>
          <p class="chart-card-description">Sales vs Purchase</p>
        </div>
      </div>
      <canvas id="ordersChart" class="chart-canvas-compact"></canvas>
    </div>
    
    <!-- Stock Level Distribution -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3 class="chart-card-title">Stock Levels</h3>
          <p class="chart-card-description">Health status</p>
        </div>
      </div>
      <canvas id="stockLevelChart" class="chart-canvas-compact"></canvas>
    </div>
  </div>
</div>

<?php if ($hasComplianceCharts): ?>
<!-- Compliance Charts (Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">🔒 Compliance & Regulatory</h2>
  </div>
  <div class="grid-analytics-2">
    <!-- BIR Compliance Status -->
    <?php if ($showBirWidgets): ?>
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3 class="chart-card-title">BIR Compliance</h3>
          <p class="chart-card-description">Filed vs Pending</p>
        </div>
      </div>
      <canvas id="birChart" class="chart-canvas-compact"></canvas>
    </div>
    <?php endif; ?>
    
    <!-- FDA Product Expiry Timeline -->
    <?php if ($showFdaWidgets): ?>
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3 class="chart-card-title">FDA Expiry</h3>
          <p class="chart-card-description">Next 90 days</p>
        </div>
      </div>
      <canvas id="expiryChart" class="chart-canvas-compact"></canvas>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Inventory Trend (Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">📦 Inventory Growth</h2>
  </div>
  <div class="chart-card">
    <div class="chart-card-header">
      <div>
        <h3 class="chart-card-title">Items Added (30 Days)</h3>
        <p class="chart-card-description">Daily additions</p>
      </div>
    </div>
    <canvas id="inventoryTrendChart" class="chart-canvas-full"></canvas>
  </div>
</div>

<!-- Projects & Logistics (Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">
      <?php echo $showProjectsWidgets ? '🚀 Projects & Logistics' : '🚚 Logistics'; ?>
    </h2>
  </div>
  <div class="grid-analytics-2">
    <?php if ($showProjectsWidgets): ?>
    <!-- Projects by Status -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3 class="chart-card-title">Projects Status</h3>
          <p class="chart-card-description">Active, Done, Hold</p>
        </div>
      </div>
      <canvas id="projectsChart" class="chart-canvas-compact"></canvas>
    </div>
    <?php endif; ?>
    
    <!-- Shipments by Status -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3 class="chart-card-title">Shipments</h3>
          <p class="chart-card-description">Logistics tracking</p>
        </div>
      </div>
      <canvas id="shipmentsChart" class="chart-canvas-compact"></canvas>
    </div>
  </div>
</div>

<!-- Chart.js Library (Local - Offline) -->
<script src="assets/js/chart.min.js"></script>

<script>
// Chart colors
const colors = {
  primary: '#2563eb',
  success: '#22c55e',
  warning: '#f59e0b',
  danger: '#ef4444',
  info: '#3b82f6',
  purple: '#a855f7',
  pink: '#ec4899',
  teal: '#14b8a6'
};

// ==============================================
// CHART INITIALIZATION
// ==============================================

// Revenue Chart (Line Chart) - Shadcn Styled
const revenueLabels = <?php echo json_encode($monthLabels); ?>;
const revenueData = <?php echo json_encode($revenueData); ?>;

// Progressive Line Chart with Easing (Shadcn Style)
const revenueCanvas = document.getElementById('revenueChart');
const revenueCtx = revenueCanvas.getContext('2d');

// Create gradient for line and fill
const revenueGradient = revenueCtx.createLinearGradient(0, 0, 0, revenueCanvas.height);
revenueGradient.addColorStop(0, 'hsl(142 71% 45% / 0.3)');
revenueGradient.addColorStop(1, 'hsl(142 71% 45% / 0.05)');

new Chart(revenueCtx, {
  type: 'line',
  data: {
    labels: revenueLabels,
    datasets: [{
      label: 'Revenue',
      data: revenueData,
      borderColor: 'hsl(142 71% 45%)',
      backgroundColor: revenueGradient,
      fill: true,
      tension: 0.4,
      pointRadius: 5,
      pointBackgroundColor: 'hsl(142 71% 45%)',
      pointBorderColor: 'white',
      pointBorderWidth: 2,
      pointHoverRadius: 7,
      pointHoverBorderWidth: 3,
      borderWidth: 3
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
      x: {
        type: 'number',
        easing: 'easeInOutQuart',
        duration: 200,
        from: NaN,
        delay(ctx) {
          if (ctx.type !== 'data' || ctx.xStarted) {
            return 0;
          }
          ctx.xStarted = true;
          return ctx.index * 15;
        }
      },
      y: {
        type: 'number',
        easing: 'easeInOutQuart',
        duration: 200,
        from: (ctx) => ctx.index === 0 ? ctx.chart.scales.y.getPixelForValue(100) : ctx.chart.getDatasetMeta(ctx.datasetIndex).data[ctx.index - 1].getProps(['y'], true).y,
        delay(ctx) {
          if (ctx.type !== 'data' || ctx.yStarted) {
            return 0;
          }
          ctx.yStarted = true;
          return ctx.index * 15;
        }
      }
    },
    interaction: {
      intersect: false,
      mode: 'index'
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'hsl(0 0% 9%)',
        titleColor: 'white',
        bodyColor: 'white',
        borderColor: 'hsl(0 0% 20%)',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 6,
        displayColors: false,
        titleFont: { size: 13, weight: 'bold' },
        bodyFont: { size: 12 },
        callbacks: {
          label: (ctx) => 'Revenue: ₱' + ctx.parsed.y.toLocaleString()
        }
      }
    },
    scales: {
      x: {
        grid: { 
          display: false,
          drawBorder: false
        },
        ticks: {
          color: 'hsl(0 0% 45%)',
          font: { size: 11, weight: '500' },
          padding: 8
        }
      },
      y: {
        beginAtZero: true,
        grid: {
          color: 'hsl(0 0% 93%)',
          drawBorder: false,
          lineWidth: 1
        },
        ticks: {
          color: 'hsl(0 0% 45%)',
          font: { size: 11, weight: '500' },
          padding: 8,
          callback: function(value) {
            return '₱' + (value / 1000) + 'k';
          }
        }
      }
    }
  }
});

// Orders Chart (Doughnut)
const ordersData = <?php echo json_encode($ordersByType); ?>;
new Chart(document.getElementById('ordersChart'), {
  type: 'doughnut',
  data: {
    labels: Object.keys(ordersData),
    datasets: [{
      data: Object.values(ordersData),
      backgroundColor: [colors.primary, colors.warning],
      borderWidth: 2,
      borderColor: '#ffffff'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom' }
    }
  }
});

// Stock Level Chart (Bar)
const stockLevelData = <?php echo json_encode($stockLevelData); ?>;
new Chart(document.getElementById('stockLevelChart'), {
  type: 'bar',
  data: {
    labels: Object.keys(stockLevelData),
    datasets: [{
      label: 'Items',
      data: Object.values(stockLevelData),
      backgroundColor: [colors.success, colors.warning, colors.danger],
      borderRadius: 0,
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: { beginAtZero: true }
    }
  }
});

<?php if ($showBirWidgets): ?>
// BIR Compliance Chart (Doughnut)
const birData = <?php echo json_encode($birByStatus); ?>;
new Chart(document.getElementById('birChart'), {
  type: 'doughnut',
  data: {
    labels: Object.keys(birData),
    datasets: [{
      data: Object.values(birData),
      backgroundColor: [colors.success, colors.warning],
      borderWidth: 2,
      borderColor: '#ffffff'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom' }
    }
  }
});
<?php endif; ?>

// FDA Expiry Chart (Bar)
<?php if ($showFdaWidgets): ?>
const expiryData = <?php echo json_encode($expiryDistribution); ?>;
new Chart(document.getElementById('expiryChart'), {
  type: 'bar',
  data: {
    labels: Object.keys(expiryData),
    datasets: [{
      label: 'Products',
      data: Object.values(expiryData),
      backgroundColor: [colors.danger, colors.warning, colors.info],
      borderRadius: 0,
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: { beginAtZero: true }
    }
  }
});
<?php endif; ?>

// Inventory Trend Chart (Line)
const inventoryTrendLabels = <?php echo json_encode($inventoryTrendLabels); ?>;
const inventoryTrendData = <?php echo json_encode($inventoryTrendData); ?>;

// Progressive Line Chart with Fast Easing (Shadcn Style)
const inventoryCanvas = document.getElementById('inventoryTrendChart');
const inventoryCtx = inventoryCanvas.getContext('2d');

// Create gradient for line and fill
const inventoryGradient = inventoryCtx.createLinearGradient(0, 0, 0, inventoryCanvas.height);
inventoryGradient.addColorStop(0, 'hsl(217 91% 60% / 0.3)');
inventoryGradient.addColorStop(1, 'hsl(217 91% 60% / 0.05)');

new Chart(inventoryCtx, {
  type: 'line',
  data: {
    labels: inventoryTrendLabels,
    datasets: [{
      label: 'Items Added',
      data: inventoryTrendData,
      borderColor: 'hsl(217 91% 60%)',
      backgroundColor: inventoryGradient,
      fill: true,
      tension: 0.4,
      pointRadius: 5,
      pointBackgroundColor: 'hsl(217 91% 60%)',
      pointBorderColor: 'white',
      pointBorderWidth: 2,
      pointHoverRadius: 7,
      pointHoverBorderWidth: 3,
      borderWidth: 3
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
      x: {
        type: 'number',
        easing: 'easeInOutQuart',
        duration: 200,
        from: NaN,
        delay(ctx) {
          if (ctx.type !== 'data' || ctx.xStarted) {
            return 0;
          }
          ctx.xStarted = true;
          return ctx.index * 15;
        }
      },
      y: {
        type: 'number',
        easing: 'easeInOutQuart',
        duration: 200,
        from: (ctx) => ctx.index === 0 ? ctx.chart.scales.y.getPixelForValue(100) : ctx.chart.getDatasetMeta(ctx.datasetIndex).data[ctx.index - 1].getProps(['y'], true).y,
        delay(ctx) {
          if (ctx.type !== 'data' || ctx.yStarted) {
            return 0;
          }
          ctx.yStarted = true;
          return ctx.index * 15;
        }
      }
    },
    interaction: {
      intersect: false,
      mode: 'index'
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'hsl(0 0% 9%)',
        titleColor: 'white',
        bodyColor: 'white',
        borderColor: 'hsl(0 0% 20%)',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 6,
        displayColors: false,
        titleFont: { size: 13, weight: 'bold' },
        bodyFont: { size: 12 },
        callbacks: {
          label: (ctx) => 'Items: ' + ctx.parsed.y.toLocaleString()
        }
      }
    },
    scales: {
      x: {
        grid: { 
          display: false,
          drawBorder: false
        },
        ticks: {
          color: 'hsl(0 0% 45%)',
          font: { size: 11, weight: '500' },
          padding: 8,
          maxTicksLimit: 10
        }
      },
      y: {
        beginAtZero: true,
        grid: {
          color: 'hsl(0 0% 93%)',
          drawBorder: false,
          lineWidth: 1
        },
        ticks: {
          color: 'hsl(0 0% 45%)',
          font: { size: 11, weight: '500' },
          padding: 8
        }
      }
    }
  }
});

// Projects Chart (Doughnut) - Shadcn Styled
<?php if ($showProjectsWidgets): ?>
const projectsData = <?php echo json_encode($projectsByStatus); ?>;
new Chart(document.getElementById('projectsChart'), {
  type: 'doughnut',
  data: {
    labels: ['Active', 'Completed', 'On Hold'],
    datasets: [{
      data: [
        projectsData.active || 0,
        projectsData.completed || 0,
        projectsData.on_hold || 0
      ],
      backgroundColor: [
        'hsl(142 71% 45%)',  // Green - Active
        'hsl(217 91% 60%)',  // Blue - Completed
        'hsl(45 93% 47%)'    // Yellow - On Hold
      ],
      borderColor: 'white',
      borderWidth: 2
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          color: 'hsl(0 0% 45%)',
          padding: 10,
          font: {
            size: 11,
            weight: '500',
            family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
          },
          usePointStyle: true,
          pointStyle: 'circle',
          boxWidth: 10,
          boxHeight: 10
        }
      },
      tooltip: {
        backgroundColor: 'hsl(0 0% 9%)',
        titleColor: 'white',
        bodyColor: 'white',
        borderColor: 'hsl(0 0% 20%)',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 6,
        displayColors: true,
        titleFont: { size: 12 },
        bodyFont: { size: 11 },
        callbacks: {
          label: function(context) {
            const label = context.label || '';
            const value = context.parsed || 0;
            const total = context.dataset.data.reduce((a, b) => a + b, 0);
            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
            return label + ': ' + value + ' (' + percentage + '%)';
          }
        }
      }
    }
  }
});
<?php endif; ?>

// Shipments Chart (Bar)
const shipmentsData = <?php echo json_encode($shipmentsByStatus); ?>;
new Chart(document.getElementById('shipmentsChart'), {
  type: 'bar',
  data: {
    labels: Object.keys(shipmentsData),
    datasets: [{
      label: 'Shipments',
      data: Object.values(shipmentsData),
      backgroundColor: colors.info,
      borderRadius: 0,
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: { beginAtZero: true }
    }
  }
});

// ============================================
// SPARKLINE CHARTS FOR KPI CARDS
// ============================================
function createSparkline(canvasId, data, color, isPositive = true) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  
  // Extract HSL values and create gradient with proper alpha
  const hslMatch = color.match(/hsl\((\d+)\s+(\d+)%\s+(\d+)%\)/);
  if (!hslMatch) return;
  
  const [, h, s, l] = hslMatch;
  const ctx = canvas.getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, canvas.width, 0);
  gradient.addColorStop(0, `hsl(${h} ${s}% ${l}% / 1)`);      // Full color (darkest)
  gradient.addColorStop(0.5, `hsl(${h} ${s}% ${l}% / 0.85)`); // High opacity (85%)
  gradient.addColorStop(1, `hsl(${h} ${s}% ${l}% / 0.6)`);    // Medium (60% opacity - lightest)
  
  new Chart(canvas, {
    type: 'line',
    data: {
      labels: data.map((_, i) => ''),
      datasets: [{
        data: data,
        borderColor: gradient,
        backgroundColor: `hsl(${h} ${s}% ${l}% / 0.2)`,
        borderWidth: 2.5,
        fill: true,
        tension: 0.4,
        pointRadius: 0,
        pointHoverRadius: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: false }
      },
      scales: {
        x: {
          display: false
        },
        y: {
          display: false,
          beginAtZero: true
        }
      },
      elements: {
        line: {
          borderJoinStyle: 'round'
        }
      }
    }
  });
}

// Helper: build a flat series from a single metric value
function makeFlatSeries(value, points = 10) {
  const v = isNaN(value) ? 0 : Number(value);
  const data = [];
  for (let i = 0; i < points; i++) {
    data.push(v);
  }
  return data;
}

// Color mapping - matches CSS variables to HSL values
const colorMap = {
  'var(--color-success)': 'hsl(142 71% 45%)',
  'var(--color-warning)': 'hsl(45 93% 47%)',
  'var(--color-danger)': 'hsl(0 74% 50%)',
  'var(--color-primary)': 'hsl(217 91% 60%)',
  'var(--color-info)': 'hsl(200 90% 50%)',
  'var(--color-purple)': 'hsl(280 65% 60%)'
};

// Initialize sparklines using real metrics (no hard-coded sample trend data)
// Revenue sparkline - GREEN (success)
const revenueTrendData = <?php echo json_encode(array_slice($revenueData, -10)); ?>;
createSparkline(
  'sparkRevenue',
  Array.isArray(revenueTrendData) && revenueTrendData.length > 0
    ? revenueTrendData
    : makeFlatSeries(0),
  colorMap['var(--color-success)']
);

// Inventory sparkline - BLUE (primary)
const inventorySparkData = <?php echo json_encode(array_slice($inventoryTrendData, -10)); ?>;
createSparkline(
  'sparkInventory',
  Array.isArray(inventorySparkData) && inventorySparkData.length > 0
    ? inventorySparkData
    : makeFlatSeries(0),
  colorMap['var(--color-primary)']
);

// Projects sparkline - BLUE (primary)
createSparkline(
  'sparkProjects',
  makeFlatSeries(<?php echo $totalProjects; ?>),
  colorMap['var(--color-primary)']
);

// Budget sparkline - INFO (light blue)
const budgetValue = <?php echo $totalBudget; ?>;
createSparkline('sparkBudget', makeFlatSeries(budgetValue), colorMap['var(--color-info)']);

// Collection Rate sparkline - GREEN (success)
const collectionRateValue = <?php echo $totalRevenue > 0 ? round(($paidRevenue/$totalRevenue)*100, 1) : 0; ?>;
createSparkline('sparkCollection', makeFlatSeries(collectionRateValue), colorMap['var(--color-success)']);

// Outstanding sparkline - YELLOW (warning)
const outstanding = <?php echo $outstandingRevenue; ?>;
createSparkline('sparkOutstanding', makeFlatSeries(outstanding), colorMap['var(--color-warning)']);

// Conversion sparkline - BLUE (primary)
const conversionValue = <?php echo $conversionRate; ?>;
createSparkline('sparkConversion', makeFlatSeries(conversionValue), colorMap['var(--color-primary)']);

// Average Invoice sparkline - INFO (light blue)
const avgInvoice = <?php echo $invoiceCount > 0 ? ($totalRevenue / $invoiceCount) : 0; ?>;
createSparkline('sparkAvgInvoice', makeFlatSeries(avgInvoice), colorMap['var(--color-info)']);

// Inventory Value sparkline - PURPLE
const invValue = <?php echo $inventoryValue; ?>;
createSparkline('sparkInventoryValue', makeFlatSeries(invValue), colorMap['var(--color-purple)']);

// Stock Health sparkline - DYNAMIC (warning/success based on stock level)
const stockHealth = <?php echo $totalItems > 0 ? round((($totalItems - $lowStockItems - $outOfStockItems) / $totalItems) * 100, 1) : 0; ?>;
createSparkline(
  'sparkStockHealth',
  makeFlatSeries(stockHealth),
  colorMap['<?php echo $lowStockItems > 0 ? "var(--color-warning)" : "var(--color-success)"; ?>']
);

// Budget Use sparkline - BLUE (primary)
const budgetUse = <?php echo $budgetUtilization; ?>;
createSparkline('sparkBudgetUse', makeFlatSeries(budgetUse), colorMap['var(--color-primary)']);

// Compliance sparkline - DYNAMIC (success/warning based on compliance rate)
const compliance = <?php echo $complianceRate; ?>;
createSparkline(
  'sparkCompliance',
  makeFlatSeries(compliance),
  colorMap['<?php echo $complianceRate >= 90 ? "var(--color-success)" : "var(--color-warning)"; ?>']
);

// Turnover sparkline - INFO (light blue)
const turnover = <?php echo $totalItems > 0 ? round(($invoiceCount / $totalItems) * 100, 1) : 0; ?>;
createSparkline('sparkTurnover', makeFlatSeries(turnover), colorMap['var(--color-info)']);

// Orders sparkline - GREEN (success)
const totalOrders = <?php echo array_sum($ordersByType); ?>;
createSparkline('sparkOrders', makeFlatSeries(totalOrders), colorMap['var(--color-success)']);

// Active Projects sparkline - GREEN (success)
const activeProjects = <?php echo $projectsByStatus['active'] ?? 0; ?>;
createSparkline('sparkActiveProjects', makeFlatSeries(activeProjects), colorMap['var(--color-success)']);

// Budget Spent sparkline - DYNAMIC (success/warning based on utilization)
const spentValue = <?php echo $totalSpent; ?>;
createSparkline(
  'sparkBudgetSpent',
  makeFlatSeries(spentValue),
  colorMap['<?php echo $budgetUtilization > 90 ? "var(--color-warning)" : "var(--color-success)"; ?>']
);

// Completed Projects sparkline - GREEN (success)
const completedProjects = <?php echo $projectsByStatus['completed'] ?? 0; ?>;
createSparkline('sparkCompleted', makeFlatSeries(completedProjects), colorMap['var(--color-success)']);

// On Hold Projects sparkline - YELLOW (warning)
const onHold = <?php echo $projectsByStatus['on_hold'] ?? 0; ?>;
createSparkline('sparkOnHold', makeFlatSeries(onHold), colorMap['var(--color-warning)']);

// Active Alerts sparkline - YELLOW (warning)
const alerts = <?php echo $notificationSummary['unread']; ?>;
createSparkline('sparkAlerts', makeFlatSeries(alerts), colorMap['var(--color-warning)']);

// FDA Expiring sparkline - RED (danger)
const fdaExpiring = <?php echo $expiryDistribution['0-30 days'] ?? 0; ?>;
createSparkline('sparkFDAExpiring', makeFlatSeries(fdaExpiring), colorMap['var(--color-danger)']);

// Avg Project Value sparkline - INFO (light blue)
const avgProjValue = <?php echo $totalProjects > 0 ? ($totalBudget / $totalProjects) : 0; ?>;
createSparkline('sparkAvgProjectValue', makeFlatSeries(avgProjValue), colorMap['var(--color-info)']);

// In Transit sparkline - BLUE (primary)
const inTransit = <?php echo $shipmentsByStatus['in_transit'] ?? 0; ?>;
createSparkline('sparkInTransit', makeFlatSeries(inTransit), colorMap['var(--color-primary)']);

// ============================================
// ANALYTICS AJAX (NO PAGE REFRESH)
// ============================================
function setAnalyticsRange(range) {
  const currentRange = new URLSearchParams(window.location.search).get('range') || '30d';
  if (range === currentRange) return; // Already selected
  
  loadAnalyticsData(range);
}

function refreshAnalytics() {
  const currentRange = new URLSearchParams(window.location.search).get('range') || '30d';
  loadAnalyticsData(currentRange);
}

function loadAnalyticsData(range) {
  // Show loading state
  const kpiCards = document.querySelector('.analytics-kpi-grid');
  if (kpiCards) {
    kpiCards.style.opacity = '0.6';
    kpiCards.style.pointerEvents = 'none';
  }
  
  // Fetch new data
  fetch(`/api/analytics.php?action=get_analytics&range=${range}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update URL without reload
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('range', range);
        window.history.pushState({}, '', newUrl);
        
        // Reload page to update charts (simpler for now)
        // In future, can update charts individually
        location.reload();
      }
    })
    .catch(error => console.error('Error loading analytics:', error))
    .finally(() => {
      if (kpiCards) {
        kpiCards.style.opacity = '1';
        kpiCards.style.pointerEvents = '';
      }
    });
}

// Auto-refresh every 5 minutes (no toast notification)
setInterval(() => {
  console.log('Auto-refreshing analytics data...');
  refreshAnalytics();
}, 300000);

// ============================================
// APPLY NUMBER FORMAT API TO CURRENCY VALUES
// ============================================
window.addEventListener('load', function() {
  const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';
  
  // Auto-apply formatting to KPI values (will skip percentages and non-currency)
  NumberFormat.autoApply(currencySymbol, {
    customSelectors: [
      { selector: '.kpi-value', maxWidth: 1 },  // All KPI values (API filters non-currency)
      { selector: '.kpi-meta span:not(.badge):not(.trend-indicator)', maxWidth: 80 }
    ]
  });
});
</script>

<style>
/* Chart-specific styles */
.stat-change {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  margin-top: 0.5rem;
  color: var(--text-secondary);
}

.stat-change.positive {
  color: var(--color-success);
}

.stat-change.negative {
  color: var(--color-danger);
}

.stat-change svg {
  flex-shrink: 0;
}
</style>  

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
