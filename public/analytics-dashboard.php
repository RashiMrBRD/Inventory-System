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
// INVENTORY ANALYTICS
// ============================================
$inventoryModel = new Inventory();
try {
    $totalItems = $inventoryModel->count();
    $lowStockItems = $inventoryModel->getLowStockCount();
    $outOfStockItems = $inventoryModel->getOutOfStockCount();
    $allItems = $inventoryModel->getAll();
    
    // Inventory trend (last 30 days)
    $dailyCounts = $inventoryModel->getDailyAddedCounts(30);
    $inventoryTrendLabels = [];
    $inventoryTrendData = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = new \DateTimeImmutable("-$i days");
        $key = $d->format('Y-m-d');
        $inventoryTrendLabels[] = $d->format('M j');
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
    $invoiceTotals = $invoiceModel->totals();
    $totalRevenue = $invoiceTotals['total'];
    $paidRevenue = $invoiceTotals['paid'];
    $outstandingRevenue = $invoiceTotals['outstanding'];
    
    // Revenue by month (last 6 months)
    $revenueByMonth = [];
    $monthLabels = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = new \DateTimeImmutable("-$i months");
        $monthKey = $month->format('Y-m');
        $monthLabels[] = $month->format('M Y');
        $revenueByMonth[$monthKey] = 0;
    }
    foreach ($invoices as $inv) {
        $date = $inv['date'] ?? null;
        if ($date) {
            $ts = is_string($date) ? strtotime($date) : (is_object($date) && method_exists($date, 'toDateTime') ? $date->toDateTime()->getTimestamp() : null);
            if ($ts) {
                $monthKey = date('Y-m', $ts);
                if (isset($revenueByMonth[$monthKey])) {
                    $revenueByMonth[$monthKey] += (float)($inv['total'] ?? 0);
                }
            }
        }
    }
    $revenueData = array_values($revenueByMonth);
    
    // Orders by type
    $orders = $orderModel->getAll();
    $ordersByType = ['Sales' => 0, 'Purchase' => 0];
    foreach ($orders as $order) {
        $type = $order['type'] ?? 'Sales';
        if (!isset($ordersByType[$type])) $ordersByType[$type] = 0;
        $ordersByType[$type]++;
    }
    
    // Quotation conversion rate
    $quotations = $quotationModel->getAll();
    $approvedQuotations = count(array_filter($quotations, fn($q) => ($q['status'] ?? '') === 'approved'));
    $conversionRate = count($quotations) > 0 ? round(($approvedQuotations / count($quotations)) * 100, 1) : 0;
    
} catch (Exception $e) {
    $totalRevenue = $paidRevenue = $outstandingRevenue = 0;
    $monthLabels = $revenueData = $ordersByType = [];
    $conversionRate = 0;
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

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Analytics Dashboard</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>Status:</strong>
          <span class="status-indicator">
            <span class="status-dot"></span>
            Real-Time
          </span>
        </div>
        <div class="page-banner-meta-item">
          <strong>User:</strong>
          <?php echo htmlspecialchars($user['username'] ?? 'Unknown'); ?>
        </div>
        <div class="page-banner-meta-item">
          <strong>Currency:</strong>
          <span class="font-medium"><?php echo CurrencyHelper::symbol() . ' ' . CurrencyHelper::getCurrentCurrency(); ?></span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <button class="btn btn-secondary" onclick="refreshCharts()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M1 4V10H7M23 20V14H17M20.49 9C19.9828 7.56678 19.1209 6.28536 17.9845 5.27539C16.8482 4.26541 15.4745 3.55976 13.9917 3.22426C12.5089 2.88875 10.9652 2.93434 9.50481 3.35677C8.04437 3.77921 6.71475 4.56471 5.64 5.64L1 10M23 14L18.36 18.36C17.2853 19.4353 15.9556 20.2208 14.4952 20.6432C13.0348 21.0657 11.4911 21.1112 10.0083 20.7757C8.52547 20.4402 7.1518 19.7346 6.01547 18.7246C4.87913 17.7146 4.01717 16.4332 3.51 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Refresh
      </button>
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
     KEY PERFORMANCE INDICATORS (KPIs)
     ======================================== -->

<!-- Financial KPIs -->
<div class="section">
  <h2 class="section-title">Financial Performance Metrics</h2>
  <div class="grid grid-cols-4 mb-4">
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Total Revenue</p>
        <p class="stat-value"><?php echo CurrencyHelper::format($totalRevenue); ?></p>
        <span class="badge badge-default">All-time</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Collection Rate</p>
        <p class="stat-value text-success"><?php echo $totalRevenue > 0 ? round(($paidRevenue/$totalRevenue)*100, 1) : 0; ?>%</p>
        <span class="text-sm text-secondary"><?php echo CurrencyHelper::format($paidRevenue); ?> collected</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Outstanding</p>
        <p class="stat-value text-warning"><?php echo CurrencyHelper::format($outstandingRevenue); ?></p>
        <span class="badge badge-warning">Pending payment</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Conversion Rate</p>
        <p class="stat-value text-primary"><?php echo $conversionRate; ?>%</p>
        <span class="text-sm text-secondary">Quotation → Invoice</span>
      </div>
    </div>
  </div>
</div>

<!-- Operations KPIs -->
<div class="section">
  <h2 class="section-title">Operations Performance Metrics</h2>
  <div class="grid grid-cols-4 mb-4">
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Inventory Items</p>
        <p class="stat-value"><?php echo number_format($totalItems); ?></p>
        <span class="badge badge-success"><?php echo number_format($totalItems - $lowStockItems - $outOfStockItems); ?> in stock</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Stock Health</p>
        <p class="stat-value <?php echo $lowStockItems > 0 ? 'text-warning' : 'text-success'; ?>"><?php echo $totalItems > 0 ? round((($totalItems - $lowStockItems - $outOfStockItems) / $totalItems) * 100, 1) : 0; ?>%</p>
        <span class="text-sm text-secondary"><?php echo number_format($lowStockItems); ?> low, <?php echo number_format($outOfStockItems); ?> out</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Budget Utilization</p>
        <p class="stat-value text-primary"><?php echo $budgetUtilization; ?>%</p>
        <span class="text-sm text-secondary">Project spending</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Compliance Rate</p>
        <p class="stat-value <?php echo $complianceRate >= 90 ? 'text-success' : 'text-warning'; ?>"><?php echo $complianceRate; ?>%</p>
        <span class="text-sm text-secondary">BIR forms filed</span>
      </div>
    </div>
  </div>
</div>

<!-- Alerts & Notifications KPIs -->
<div class="section">
  <h2 class="section-title">System Health & Alerts</h2>
  <div class="grid grid-cols-4 mb-4">
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Active Alerts</p>
        <p class="stat-value text-warning"><?php echo number_format($notificationSummary['unread']); ?></p>
        <span class="badge badge-warning"><?php echo $notificationSummary['high_priority']; ?> high priority</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">FDA Expiring</p>
        <p class="stat-value text-danger"><?php echo number_format($expiryDistribution['0-30 days'] ?? 0); ?></p>
        <span class="text-sm text-secondary">Next 30 days</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">Active Projects</p>
        <p class="stat-value text-success"><?php echo number_format($projectsByStatus['active'] ?? 0); ?></p>
        <span class="text-sm text-secondary"><?php echo number_format($projectsByStatus['completed'] ?? 0); ?> completed</span>
      </div>
    </div>
    <div class="card stat-card">
      <div class="card-content">
        <p class="stat-label">In Transit</p>
        <p class="stat-value text-primary"><?php echo number_format($shipmentsByStatus['in_transit'] ?? 0); ?></p>
        <span class="text-sm text-secondary">Shipments</span>
      </div>
    </div>
  </div>
</div>

<!-- ========================================
     DATA VISUALIZATIONS & CHARTS
     ======================================== -->

<!-- Revenue Trend (Full Width) -->
<div class="section">
  <h2 class="section-title">Revenue Trends & Analysis</h2>
  <div class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">Revenue by Month (Last 6 Months)</h3>
      <p class="card-description">Track revenue growth and patterns</p>
    </div>
    <div class="card-content">
      <canvas id="revenueChart" style="height: 300px; max-height: 300px;"></canvas>
    </div>
  </div>
</div>

<!-- Distribution Charts -->
<div class="section">
  <h2 class="section-title">Distribution & Composition Analysis</h2>
  <div class="grid grid-cols-2 mb-4" style="gap: 1.5rem;">
    <!-- Orders by Type -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Orders by Type</h3>
        <p class="card-description">Sales vs Purchase orders</p>
      </div>
      <div class="card-content">
        <canvas id="ordersChart" style="height: 300px; max-height: 300px;"></canvas>
      </div>
    </div>
    
    <!-- Stock Level Distribution -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Stock Level Distribution</h3>
        <p class="card-description">Inventory health status</p>
      </div>
      <div class="card-content">
        <canvas id="stockLevelChart" style="height: 300px; max-height: 300px;"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Compliance & Operations Charts -->
<div class="section">
  <h2 class="section-title">Compliance & Operations Overview</h2>
  <div class="grid grid-cols-2 mb-4" style="gap: 1.5rem;">
    <!-- BIR Compliance Status -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">BIR Compliance Status</h3>
        <p class="card-description">Filed vs Pending forms</p>
      </div>
      <div class="card-content">
        <canvas id="birChart" style="height: 300px; max-height: 300px;"></canvas>
      </div>
    </div>
    
    <!-- FDA Product Expiry Timeline -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">FDA Product Expiry Timeline</h3>
        <p class="card-description">Products expiring in next 90 days</p>
      </div>
      <div class="card-content">
        <canvas id="expiryChart" style="height: 300px; max-height: 300px;"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Inventory Trend -->
<div class="section">
  <h2 class="section-title">Inventory Growth Trend</h2>
  <div class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">Items Added (Last 30 Days)</h3>
      <p class="card-description">Daily inventory additions</p>
    </div>
    <div class="card-content">
      <canvas id="inventoryTrendChart" style="height: 250px; max-height: 250px;"></canvas>
    </div>
  </div>
</div>

<!-- Project & Shipment Status -->
<div class="section">
  <h2 class="section-title">Project & Logistics Status</h2>
  <div class="grid grid-cols-2 mb-4" style="gap: 1.5rem;">
    <!-- Projects by Status -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Projects by Status</h3>
        <p class="card-description">Active, Completed, On Hold</p>
      </div>
      <div class="card-content">
        <canvas id="projectsChart" style="height: 300px; max-height: 300px;"></canvas>
      </div>
    </div>
    
    <!-- Shipments by Status -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Shipments by Status</h3>
        <p class="card-description">Logistics tracking overview</p>
      </div>
      <div class="card-content">
        <canvas id="shipmentsChart" style="height: 300px; max-height: 300px;"></canvas>
      </div>
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

// Refresh charts function
function refreshCharts() {
  showToast('Refreshing analytics...', 'info');
  setTimeout(() => {
    location.reload();
  }, 500);
}

// Auto-refresh every 5 minutes
setInterval(() => {
  console.log('Auto-refreshing analytics data...');
  // In production, use AJAX to update data without full reload
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
