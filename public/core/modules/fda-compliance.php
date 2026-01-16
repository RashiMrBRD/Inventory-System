<?php
/**
 * FDA Compliance Module
 * Philippine Food and Drug Administration compliance tracking
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Service\FDAService;
use App\Model\FdaProduct;
use App\Helper\NotificationHelper;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Get notification summary for FDA/expiry alerts
$userId = $user['id'] ?? 'admin';
$notificationSummary = NotificationHelper::getSummary($userId);
$fdaAlerts = $notificationSummary['by_type']['fda'] ?? 0;
$expiryAlerts = $notificationSummary['by_type']['expiry'] ?? 0;

// Real expiring products from database
$fdaModel = new FdaProduct();
try {
    $expiring30 = $fdaModel->getExpiringProducts(30);
    $expiring60 = $fdaModel->getExpiringProducts(60);
    $expiring90 = $fdaModel->getExpiringProducts(90);
    $activeProducts = $fdaModel->countActive();
} catch (\Exception $e) {
    $expiring30 = $expiring60 = $expiring90 = [];
    $activeProducts = 0;
}
// Default table shows 30-day window
$expiringProducts = $expiring30;

$pageTitle = 'FDA Compliance';
ob_start();
?>

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">FDA Compliance</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>LTO Status:</strong>
          <span class="status-indicator">
            <span class="status-dot"></span>
            Valid until Dec 31, 2025
          </span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Products:</strong>
          <span class="font-medium"><?php echo number_format($activeProducts); ?> Active</span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Expiring Soon:</strong>
          <span class="text-warning"><?php echo number_format(count($expiring30)); ?> items</span>
        </div>
        <?php if ($expiryAlerts > 0): ?>
        <div class="page-banner-meta-item">
          <strong>Expiry Alerts:</strong>
          <span class="badge badge-danger"><?php echo number_format($expiryAlerts); ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="page-banner-actions">
      <button class="btn btn-secondary" onclick="showToast('Viewing FDA guide...', 'info')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
        </svg>
        FDA Guide
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

<!-- FDA Stats Cards -->
<div class="section">
  <div class="grid grid-cols-4 mb-6" style="gap: 1.5rem;">
    
    <!-- Expiring in 30 Days -->
    <div class="card">
      <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
          <span class="badge badge-warning">Urgent</span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b">
            <path d="M12 8V12L15 15" stroke-width="2" stroke-linecap="round"/>
            <circle cx="12" cy="12" r="10" stroke-width="2"/>
          </svg>
        </div>
        <h3 class="text-3xl font-bold" style="margin-bottom: 0.25rem;"><?php echo number_format(count($expiring30)); ?></h3>
        <p class="text-sm text-secondary">Expiring in 30 Days</p>
      </div>
    </div>

    <!-- CPR Expiring -->
    <div class="card">
      <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
          <span class="badge badge-default">Monitor</span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6b7280">
            <path d="M9 12H15M12 9V15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <h3 class="text-3xl font-bold" style="margin-bottom: 0.25rem;"><?php echo number_format(max(0, count($expiring60) - count($expiring30))); ?></h3>
        <p class="text-sm text-secondary">CPR Renewals Due</p>
      </div>
    </div>

    <!-- Total Batches -->
    <div class="card">
      <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
          <span class="badge badge-success">Active</span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e">
            <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke-width="2"/>
            <path d="M16 21V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V21" stroke-width="2"/>
          </svg>
        </div>
        <h3 class="text-3xl font-bold" style="margin-bottom: 0.25rem;"><?php echo number_format($activeProducts); ?></h3>
        <p class="text-sm text-secondary">Active Batches</p>
      </div>
    </div>

    <!-- LTO Status -->
    <div class="card">
      <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
          <span class="badge badge-success">Valid</span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e">
            <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h3 class="text-3xl font-bold" style="margin-bottom: 0.25rem;">✓</h3>
        <p class="text-sm text-secondary">LTO Current</p>
      </div>
    </div>
  </div>
</div>

<!-- Expiry Monitoring Tabs -->
<div class="section">
  <h2 class="section-title">Expiry Monitoring (FEFO)</h2>
  
  <!-- Tab Buttons -->
  <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0;">
    <button class="btn btn-ghost" style="border-bottom: 2px solid var(--color-primary); border-radius: 0; margin-bottom: -2px;">
      30 Days (<?php echo number_format(count($expiring30)); ?>)
    </button>
    <button class="btn btn-ghost" onclick="showToast('Showing 60-day expiry items', 'info')" style="border-radius: 0;">
      60 Days (<?php echo number_format(count($expiring60)); ?>)
    </button>
    <button class="btn btn-ghost" onclick="showToast('Showing 90-day expiry items', 'info')" style="border-radius: 0;">
      90 Days (<?php echo number_format(count($expiring90)); ?>)
    </button>
    <button class="btn btn-ghost" onclick="showToast('Showing all items', 'info')" style="border-radius: 0;">
      All Items (<?php echo number_format($activeProducts); ?>)
    </button>
  </div>

  <!-- Expiring Products Table -->
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>Product Name</th>
          <th>Batch/Lot Number</th>
          <th>Expiry Date</th>
          <th>Days Left</th>
          <th>Quantity</th>
          <th>Priority</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($expiringProducts as $product): ?>
        <tr>
          <td class="font-medium"><?php echo htmlspecialchars($product['name']); ?></td>
          <td class="font-mono text-sm"><?php echo htmlspecialchars($product['batch']); ?></td>
          <td><?php echo date('M d, Y', strtotime($product['expiry'])); ?></td>
          <td>
            <?php
            $daysLeft = $product['days_left'];
            $badgeClass = $daysLeft <= 30 ? 'badge-danger' : ($daysLeft <= 60 ? 'badge-warning' : 'badge-default');
            ?>
            <span class="badge <?php echo $badgeClass; ?>"><?php echo $daysLeft; ?> days</span>
          </td>
          <td class="font-semibold"><?php echo number_format($product['quantity']); ?> units</td>
          <td>
            <?php
            $priority = $daysLeft <= 30 ? 'HIGH' : ($daysLeft <= 60 ? 'MEDIUM' : 'LOW');
            $priorityClass = $daysLeft <= 30 ? 'text-danger' : ($daysLeft <= 60 ? 'text-warning' : 'text-secondary');
            ?>
            <span class="font-semibold <?php echo $priorityClass; ?>"><?php echo $priority; ?></span>
          </td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-sm" onclick="showToast('Viewing batch details...', 'info')" title="View Details">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                </svg>
              </button>
              <button class="btn btn-ghost btn-sm" onclick="showToast('Marking for priority sale...', 'success')" title="Prioritize">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" fill="none"/>
                </svg>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- FEFO Recommendation -->
  <div class="card" style="margin-top: 2rem; background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%); border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 8px 32px rgba(0,0,0,0.4); color: white;">
    <div class="card-content" style="padding: 1.5rem 1.5rem;">
      <div style="display: flex; align-items: start; gap: 1.5rem;">
        <div style="padding: 0.75rem; background: rgba(255,255,255,0.1); border-radius: var(--radius-md); backdrop-filter: blur(10px); flex-shrink: 0;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="10" stroke-width="2"/>
            <path d="M12 16V12M12 8H12.01" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <h4 class="font-semibold" style="margin-bottom: 0.75rem; color: white; font-size: 1.1rem;">FEFO Recommendation</h4>
          <p class="text-sm" style="color: rgba(255,255,255,0.8); line-height: 1.6;">
            <strong style="color: rgba(255,255,255,0.95);">First Expire First Out:</strong> Prioritize selling <strong style="color: rgba(255,255,255,0.95);">Mushrooms (Canned)</strong> - expires in 22 days. 
            Move to front of warehouse and promote to customers immediately.
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- License & Registration Tracking -->
<div class="section" style="margin-top: 3rem;">
  <h2 class="section-title">License & Registration Status</h2>
  <div class="grid grid-cols-2" style="gap: 1.5rem;">
    
    <!-- LTO Card -->
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; align-items: start; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="padding: 0.75rem; background: rgba(34, 197, 94, 0.1); border-radius: var(--radius-md);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e">
              <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke-width="2" stroke-linecap="round"/>
              <path d="M22 4L12 14.01L9 11.01" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div style="flex: 1;">
            <h3 class="card-title" style="margin-bottom: 0.25rem;">LTO (License to Operate)</h3>
            <p class="text-sm text-secondary">Food Establishment License</p>
          </div>
        </div>
        <div style="margin-bottom: 1.5rem;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-sm text-secondary">License No:</span>
            <span class="text-sm font-medium">LTO-2025-MAD-00456</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-sm text-secondary">Issue Date:</span>
            <span class="text-sm font-medium">Jan 15, 2025</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-sm text-secondary">Expiry Date:</span>
            <span class="text-sm font-medium text-warning">Dec 31, 2025</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span class="text-sm text-secondary">Days Remaining:</span>
            <span class="text-sm font-semibold text-success">73 days</span>
          </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
          <button class="btn btn-secondary btn-sm flex-1" onclick="showToast('Opening LTO document...', 'info')">View License</button>
          <button class="btn btn-primary btn-sm flex-1" onclick="showToast('Initiating renewal process...', 'info')">Renew</button>
        </div>
      </div>
    </div>

    <!-- CPR Tracking Card -->
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; align-items: start; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="padding: 0.75rem; background: rgba(37, 99, 235, 0.1); border-radius: var(--radius-md);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb">
              <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke-width="2"/>
              <polyline points="14 2 14 8 20 8" stroke-width="2"/>
              <path d="M12 18V12M9 15H15" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div style="flex: 1;">
            <h3 class="card-title" style="margin-bottom: 0.25rem;">CPR Tracking</h3>
            <p class="text-sm text-secondary">Certificate of Product Registration</p>
          </div>
        </div>
        <div style="margin-bottom: 1.5rem;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-sm text-secondary">Total Products:</span>
            <span class="text-sm font-medium">142 items</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-sm text-secondary">Registered:</span>
            <span class="text-sm font-medium text-success">140 items</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span class="text-sm text-secondary">Pending:</span>
            <span class="text-sm font-medium text-warning">2 items</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span class="text-sm text-secondary">Expiring Soon:</span>
            <span class="text-sm font-semibold text-warning">2 renewals due</span>
          </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
          <button class="btn btn-secondary btn-sm flex-1" onclick="showToast('Viewing CPR list...', 'info')">View All</button>
          <button class="btn btn-primary btn-sm flex-1" onclick="showToast('New CPR application...', 'info')">New CPR</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Batch/Lot Tracking -->
<div class="section" style="margin-top: 3rem;">
  <h2 class="section-title">Recent Batch/Lot Activity</h2>
  <div class="card">
    <div class="card-content" style="padding: 1.5rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <input type="text" class="search-input" placeholder="Search by batch number..." style="max-width: 400px;">
        <button class="btn btn-primary" onclick="showToast('Generating new batch number...', 'success')">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Generate Batch
        </button>
      </div>
      <div class="grid grid-cols-3" style="gap: 1.5rem;">
        <div style="padding: 1.25rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <div class="font-mono text-sm font-semibold" style="margin-bottom: 0.75rem;">LOT-20251020-B8A4</div>
          <div class="text-sm text-secondary" style="margin-bottom: 0.5rem;">Olive Oil Premium</div>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="text-xs text-secondary">Exp: Jan 10, 2026</span>
            <span class="badge badge-success badge-sm">Active</span>
          </div>
        </div>
        <div style="padding: 1.25rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <div class="font-mono text-sm font-semibold" style="margin-bottom: 0.75rem;">LOT-20251018-F7C1</div>
          <div class="text-sm text-secondary" style="margin-bottom: 0.5rem;">Pasta Premium</div>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="text-xs text-secondary">Exp: Dec 15, 2025</span>
            <span class="badge badge-warning badge-sm">Expiring</span>
          </div>
        </div>
        <div style="padding: 1.25rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <div class="font-mono text-sm font-semibold" style="margin-bottom: 0.75rem;">LOT-20251015-A3B2</div>
          <div class="text-sm text-secondary" style="margin-bottom: 0.5rem;">Mushrooms (Canned)</div>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="text-xs text-secondary">Exp: Nov 10, 2025</span>
            <span class="badge badge-danger badge-sm">Urgent</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../../components/layout.php';
?>
