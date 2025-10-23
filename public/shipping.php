<?php
/**
 * Shipping Module - LedgerSMB Feature
 * Track shipments and deliveries
 */

require_once __DIR__ . '/../vendor/autoload.php';
use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Model\Shipment;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Real shipments from database
$shipmentModel = new Shipment();
try {
    $shipments = $shipmentModel->getAll();
} catch (\Exception $e) {
    $shipments = [];
}

// Derived metrics
$statusOf = function($s) {
  $st = strtolower(str_replace('_', '-', (string)($s['status'] ?? '')));
  return $st;
};
$inTransitCount = count(array_filter($shipments, fn($s) => in_array($statusOf($s), ['in-transit','in transit'])));
$deliveredCount = count(array_filter($shipments, fn($s) => $statusOf($s) === 'delivered'));
$pendingCount = count(array_filter($shipments, fn($s) => $statusOf($s) === 'pending'));

$pageTitle = 'Shipping';
ob_start();
?>

<!-- Page Banner -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Shipping & Fulfillment</h1>
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
          <strong>In Transit:</strong>
          <span class="font-semibold"><?php echo number_format($inTransitCount); ?></span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Currency:</strong>
          <span class="font-medium"><?php echo CurrencyHelper::symbol() . ' ' . CurrencyHelper::getCurrentCurrency(); ?></span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <button class="btn btn-success" onclick="showToast('Create Shipment', 'info')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Create Shipment
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
      <p class="stat-label">Total Shipments</p>
      <p class="stat-value"><?php echo count($shipments); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">In Transit</p>
      <p class="stat-value text-warning"><?php echo number_format($inTransitCount); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Delivered</p>
      <p class="stat-value text-success"><?php echo number_format($deliveredCount); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Pending</p>
      <p class="stat-value"><?php echo number_format($pendingCount); ?></p>
    </div>
  </div>
</div>

<!-- Shipments Table -->
<div class="table-container">
  <table class="data-table">
    <thead>
      <tr>
        <th>Shipment #</th>
        <th>Order #</th>
        <th>Customer</th>
        <th>Carrier</th>
        <th>Tracking Number</th>
        <th>Ship Date</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($shipments as $shipment): ?>
      <tr>
        <td class="font-mono font-medium"><?php echo $shipment['id']; ?></td>
        <td class="font-mono"><?php echo $shipment['order']; ?></td>
        <td class="font-medium"><?php echo $shipment['customer']; ?></td>
        <td><?php echo $shipment['carrier']; ?></td>
        <td class="font-mono text-primary"><?php echo $shipment['tracking']; ?></td>
        <td><?php echo date('M d, Y', strtotime($shipment['date'])); ?></td>
        <td>
          <?php
          $badges = ['pending' => 'badge-default', 'in-transit' => 'badge-warning', 'delivered' => 'badge-success'];
          ?>
          <span class="badge <?php echo $badges[$shipment['status']]; ?>"><?php echo ucfirst(str_replace('-', ' ', $shipment['status'])); ?></span>
        </td>
        <td>
          <div class="flex gap-2">
            <button class="btn btn-ghost btn-sm" onclick="trackShipment('<?php echo $shipment['tracking']; ?>')" title="Track">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <polyline points="12 6 12 12 16 14" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="printLabel('<?php echo $shipment['id']; ?>')" title="Print Label">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M6 9V2H18V9M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18M6 14H18V22H6V14Z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function trackShipment(tracking) {
  showToast('Tracking shipment: ' + tracking, 'info');
}

function printLabel(id) {
  showToast('Printing label for ' + id, 'info');
}
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
