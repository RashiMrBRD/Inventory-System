<?php
/**
 * Orders Module - LedgerSMB Feature
 * Manage sales and purchase orders
 */

require_once __DIR__ . '/../vendor/autoload.php';
use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Model\Order;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Real orders from database
$orderModel = new Order();
try {
    $orders = $orderModel->getAll();
} catch (\Exception $e) {
    $orders = [];
}

// Derived metrics
$salesCount = count(array_filter($orders, fn($o) => ($o['type'] ?? '') === 'Sales'));
$purchaseCount = count(array_filter($orders, fn($o) => ($o['type'] ?? '') === 'Purchase'));
$processingCount = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'processing'));
$totalValue = array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $orders));

$pageTitle = 'Orders';
ob_start();
?>

<!-- Page Banner -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Order Management</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>Status:</strong>
          <span class="status-indicator"><span class="status-dot"></span>Online</span>
        </div>
        <div class="page-banner-meta-item">
          <strong>User:</strong>
          <?php echo htmlspecialchars($user['username'] ?? 'Unknown'); ?>
        </div>
        <div class="page-banner-meta-item">
          <strong>Total Orders:</strong>
          <span class="font-semibold"><?php echo count($orders); ?></span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Currency:</strong>
          <span class="font-medium"><?php echo CurrencyHelper::symbol() . ' ' . CurrencyHelper::getCurrentCurrency(); ?></span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <button class="btn btn-success" onclick="showToast('New Order form', 'info')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        New Order
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

<!-- Stats -->
<div class="grid grid-cols-4 mb-6">
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Sales Orders</p>
      <p class="stat-value"><?php echo number_format($salesCount); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Purchase Orders</p>
      <p class="stat-value"><?php echo number_format($purchaseCount); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Processing</p>
      <p class="stat-value text-warning"><?php echo number_format($processingCount); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Total Value</p>
      <p class="stat-value"><?php echo CurrencyHelper::format($totalValue); ?></p>
    </div>
  </div>
</div>

<!-- Table -->
<div class="table-container">
  <table class="data-table">
    <thead>
      <tr>
        <th>Order #</th>
        <th>Type</th>
        <th>Customer/Vendor</th>
        <th>Date</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $order): ?>
      <tr>
        <td class="font-mono font-medium"><?php echo htmlspecialchars($order['id']); ?></td>
        <td><span class="badge <?php echo $order['type'] === 'Sales' ? 'badge-success' : 'badge-default'; ?>"><?php echo $order['type']; ?></span></td>
        <td class="font-medium"><?php echo htmlspecialchars($order['customer']); ?></td>
        <td><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
        <td class="font-semibold"><?php echo CurrencyHelper::format($order['total']); ?></td>
        <td>
          <?php
          $badges = ['pending' => 'badge-warning', 'processing' => 'badge-default', 'shipped' => 'badge-success', 'received' => 'badge-success'];
          ?>
          <span class="badge <?php echo $badges[$order['status']] ?? 'badge-default'; ?>"><?php echo ucfirst($order['status']); ?></span>
        </td>
        <td>
          <div class="flex gap-2">
            <button class="btn btn-ghost btn-sm" onclick="showToast('View order', 'info')" title="View">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
