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
    
    // Current period inventory stats
    $totalItems = 0;
    $lowStockItems = 0;
    $outOfStockItems = 0;
    $inventoryValue = 0;
    
    // Previous period for comparison
    $prevTotalItems = 0;
    
    foreach ($allItems as $item) {
        $createdDate = $item['created_at'] ?? $item['date'] ?? null;
        
        // Current period
        if (isInDateRange($createdDate, $startDate, $endDate)) {
            $totalItems++;
            $quantity = $item['quantity'] ?? 0;
            $price = (float)($item['price'] ?? 0);
            $inventoryValue += $quantity * $price;
            
            if ($quantity == 0) $outOfStockItems++;
            elseif ($quantity <= 5) $lowStockItems++;
        }
        
        // Previous period for comparison
        if (isInDateRange($createdDate, $prevStartDate, $prevEndDate)) {
            $prevTotalItems++;
        }
    }
    
    // Calculate trend
    $inventoryTrend = $prevTotalItems > 0 ? 
        round((($totalItems - $prevTotalItems) / $prevTotalItems) * 100, 1) : 0;
    
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
    
    // Current period revenue
    $totalRevenue = 0;
    $paidRevenue = 0;
    $outstandingRevenue = 0;
    $invoiceCount = 0;
    
    // Previous period for comparison
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
        
        // Current period
        if (isInDateRange($date, $startDate, $endDate)) {
            $totalRevenue += $total;
            $invoiceCount++;
            if ($status === 'paid') {
                $paidRevenue += $total;
            } else {
                $outstandingRevenue += $total;
            }
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
    
    // Quotation conversion rate (time-filtered)
    $quotations = $quotationModel->getAll();
    $quotationCount = 0;
    $approvedCount = 0;
    $prevQuotationCount = 0;
    $prevApprovedCount = 0;
    
    foreach ($quotations as $q) {
        $date = $q['created_at'] ?? $q['date'] ?? null;
        $status = $q['status'] ?? '';
        
        if (isInDateRange($date, $startDate, $endDate)) {
            $quotationCount++;
            if ($status === 'approved') $approvedCount++;
        }
        
        if (isInDateRange($date, $prevStartDate, $prevEndDate)) {
            $prevQuotationCount++;
            if ($status === 'approved') $prevApprovedCount++;
        }
    }
    
    $conversionRate = $quotationCount > 0 ? round(($approvedCount / $quotationCount) * 100, 1) : 0;
    $prevConversionRate = $prevQuotationCount > 0 ? round(($prevApprovedCount / $prevQuotationCount) * 100, 1) : 0;
    $conversionTrend = $prevConversionRate > 0 ? round((($conversionRate - $prevConversionRate) / $prevConversionRate) * 100, 1) : 0;
    
} catch (Exception $e) {
    $totalRevenue = $paidRevenue = $outstandingRevenue = 0;
    $monthLabels = $revenueData = $ordersByType = [];
    $conversionRate = $revenueTrend = $collectionRateTrend = $conversionTrend = 0;
    $invoiceCount = 0;
}

// ============================================
// OPERATIONS ANALYTICS
// ============================================
$projectModel = new Project();
$shipmentModel = new Shipment();

try {
    $projects = $projectModel->getAll();
    $projectsByStatus = ['active' => 0, 'completed' => 0, 'on_hold' => 0];
    $totalBudget = $totalSpent = 0;
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
    $projectsByStatus = $shipmentsByStatus = [];
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
      <div class="kpi-meta">
        <span class="badge badge-warning" style="font-size: 0.625rem; padding: 0.125rem 0.375rem;">Pending</span>
        <span style="margin-left: 0.25rem;"><?php echo $totalRevenue > 0 ? round(($outstandingRevenue/$totalRevenue)*100, 1) : 0; ?>%</span>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Conversion</div>
      <div class="kpi-value" style="color: var(--color-primary);"><?php echo $conversionRate; ?>%</div>
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
      <div class="kpi-meta">Per transaction</div>
    </div>
    
    <!-- NEW: Inventory Value (QuickBooks Feature) -->
    <div class="kpi-card">
      <div class="kpi-label">Inventory Value</div>
      <div class="kpi-value" style="color: var(--color-purple);"><?php echo CurrencyHelper::format($inventoryValue); ?></div>
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
      <div class="kpi-meta"><?php echo number_format($lowStockItems); ?> low, <?php echo number_format($outOfStockItems); ?> out</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Budget Use</div>
      <div class="kpi-value" style="color: var(--color-primary);"><?php echo $budgetUtilization; ?>%</div>
      <div class="kpi-meta">Project spend</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Compliance</div>
      <div class="kpi-value" style="color: <?php echo $complianceRate >= 90 ? 'var(--color-success)' : 'var(--color-warning)'; ?>;"><?php echo $complianceRate; ?>%</div>
      <div class="kpi-meta">BIR forms</div>
    </div>
    
    <!-- NEW: Stock Turnover Rate (Xero Feature) -->
    <div class="kpi-card">
      <div class="kpi-label">Turnover</div>
      <div class="kpi-value" style="color: var(--color-info);"><?php echo $totalItems > 0 ? round(($invoiceCount / $totalItems) * 100, 1) : 0; ?>%</div>
      <div class="kpi-meta">Stock movement</div>
    </div>
    
    <!-- NEW: Active Orders (QuickBooks Feature) -->
    <div class="kpi-card">
      <div class="kpi-label">Orders</div>
      <div class="kpi-value" style="color: var(--color-success);"><?php echo number_format(array_sum($ordersByType)); ?></div>
      <div class="kpi-meta"><?php echo $ordersByType['Sales'] ?? 0; ?> sales / <?php echo $ordersByType['Purchase'] ?? 0; ?> purchase</div>
    </div>
  </div>
</div>

<!-- System Health KPIs (Ultra Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">🔔 System & Alerts</h2>
  </div>
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">Active Alerts</div>
      <div class="kpi-value" style="color: var(--color-warning);"><?php echo number_format($notificationSummary['unread']); ?></div>
      <div class="kpi-meta"><span class="badge badge-warning" style="font-size: 0.625rem; padding: 0.125rem 0.375rem;"><?php echo $notificationSummary['high_priority']; ?> urgent</span></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">FDA Expiring</div>
      <div class="kpi-value" style="color: var(--color-danger);"><?php echo number_format($expiryDistribution['0-30 days'] ?? 0); ?></div>
      <div class="kpi-meta">Next 30 days</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Active Projects</div>
      <div class="kpi-value" style="color: var(--color-success);"><?php echo number_format($projectsByStatus['active'] ?? 0); ?></div>
      <div class="kpi-meta"><?php echo number_format($projectsByStatus['completed'] ?? 0); ?> done</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">In Transit</div>
      <div class="kpi-value" style="color: var(--color-primary);"><?php echo number_format($shipmentsByStatus['in_transit'] ?? 0); ?></div>
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

<!-- Compliance Charts (Compact) -->
<div class="analytics-section">
  <div class="analytics-section-header">
    <h2 class="analytics-section-title">🔒 Compliance & Regulatory</h2>
  </div>
  <div class="grid-analytics-2">
    <!-- BIR Compliance Status -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3 class="chart-card-title">BIR Compliance</h3>
          <p class="chart-card-description">Filed vs Pending</p>
        </div>
      </div>
      <canvas id="birChart" class="chart-canvas-compact"></canvas>
    </div>
    
    <!-- FDA Product Expiry Timeline -->
    <div class="chart-card">
      <div class="chart-card-header">
        <div>
          <h3 class="chart-card-title">FDA Expiry</h3>
          <p class="chart-card-description">Next 90 days</p>
        </div>
      </div>
      <canvas id="expiryChart" class="chart-canvas-compact"></canvas>
    </div>
  </div>
</div>

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
    <h2 class="analytics-section-title">🚀 Projects & Logistics</h2>
  </div>
  <div class="grid-analytics-2">
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

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

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

// Revenue Chart (Line Chart)
const revenueLabels = <?php echo json_encode($monthLabels); ?>;
const revenueData = <?php echo json_encode($revenueData); ?>;

new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: revenueLabels,
    datasets: [{
      label: 'Revenue',
      data: revenueData,
      borderColor: colors.success,
      backgroundColor: colors.success + '20',
      fill: true,
      tension: 0.4,
      pointRadius: 4,
      pointHoverRadius: 7
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (ctx) => 'Revenue: ' + ctx.parsed.y.toLocaleString()
        }
      }
    },
    scales: {
      y: { beginAtZero: true }
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

// FDA Expiry Chart (Bar)
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

// Inventory Trend Chart (Line)
const inventoryTrendLabels = <?php echo json_encode($inventoryTrendLabels); ?>;
const inventoryTrendData = <?php echo json_encode($inventoryTrendData); ?>;

new Chart(document.getElementById('inventoryTrendChart'), {
  type: 'line',
  data: {
    labels: inventoryTrendLabels,
    datasets: [{
      label: 'Items Added',
      data: inventoryTrendData,
      borderColor: colors.primary,
      backgroundColor: colors.primary + '20',
      fill: true,
      tension: 0.4,
      pointRadius: 3,
      pointHoverRadius: 6
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: { beginAtZero: true },
      x: {
        ticks: { maxTicksLimit: 10 }
      }
    }
  }
});

// Projects Chart (Doughnut)
const projectsData = <?php echo json_encode($projectsByStatus); ?>;
new Chart(document.getElementById('projectsChart'), {
  type: 'doughnut',
  data: {
    labels: Object.keys(projectsData),
    datasets: [{
      data: Object.values(projectsData),
      backgroundColor: [colors.success, colors.primary, colors.warning],
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
