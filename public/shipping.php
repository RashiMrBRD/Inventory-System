<?php
/**
 * Shipping Module
 * Track shipments, manage carriers, and monitor deliveries
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

<style>
@keyframes slideUp {
  from { 
    opacity: 0; 
    transform: translateY(20px) scale(0.98);
  }
  to { 
    opacity: 1; 
    transform: translateY(0) scale(1);
  }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
</style>

<div style="background: #7194A5; color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
  <div class="container">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 32px; backdrop-filter: blur(10px);">
        🚚
      </div>
      <div style="flex: 1;">
        <h1 style="font-size: 1.875rem; font-weight: 700; margin: 0 0 0.25rem 0; color: white;">Shipping & Fulfillment</h1>
        <p style="font-size: 0.875rem; margin: 0; opacity: 0.9;">Track shipments, manage carriers, and monitor deliveries</p>
      </div>
      <button onclick="showNewShipmentModal()" style="padding: 0.625rem 1.5rem; background: rgba(255,255,255,0.95); border: none; border-radius: 8px; color: #7194A5; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='white'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.95)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
        Create Shipment
      </button>
      <a href="dashboard.php" style="padding: 0.625rem 1.25rem; background: rgba(255,255,255,0.2); border-radius: 8px; color: white; text-decoration: none; font-weight: 500; backdrop-filter: blur(10px); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        ← Dashboard
      </a>
    </div>
  </div>
</div>

<div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.875rem; margin-bottom: 1.5rem;">
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Shipments</p>
      <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><path d="M16 8H20L23 11V16H16V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;"><?php echo count($shipments); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Pending</p>
      <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;"><?php echo number_format($pendingCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">In Transit</p>
      <div style="width: 36px; height: 36px; background: hsl(48 96% 89%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(25 95% 16%); margin: 0;"><?php echo number_format($inTransitCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Delivered</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2"><path d="M22 11.08V12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C15.7824 2 18.9935 4.19066 20.4866 7.35397M22 4L12 14.01L9 11.01"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;"><?php echo number_format($deliveredCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">On Time Rate</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;">98%</p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Avg Delivery</p>
      <div style="width: 36px; height: 36px; background: rgba(113,148,165,0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: #7194A5; margin: 0;">3.2 days</p>
  </div>
</div>

<div class="toolbar">
  <div class="toolbar-left">
    <div class="search-wrapper">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input type="search" class="search-input" placeholder="Search shipments..." id="shipment-search">
    </div>
  </div>
  <div class="toolbar-right">
    <select class="form-select" id="carrier-filter">
      <option value="all">All Carriers</option>
      <option value="FedEx">FedEx</option>
      <option value="UPS">UPS</option>
      <option value="USPS">USPS</option>
      <option value="DHL">DHL</option>
    </select>
    <select class="form-select" id="status-filter">
      <option value="all">All Status</option>
      <option value="pending">Pending</option>
      <option value="in-transit">In Transit</option>
      <option value="delivered">Delivered</option>
    </select>
    <button class="btn btn-ghost btn-icon" onclick="alert('Export')" title="Export">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
</div>

<div id="shipmentsTableContainer" class="table-container" style="display: <?php echo empty($shipments) ? 'none' : 'block'; ?>;">
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
          <div class="flex gap-1">
            <button class="btn btn-ghost btn-sm" onclick="alert('Track shipment')" title="Track">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <polyline points="12 6 12 12 16 14" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="alert('Print label')" title="Print Label">
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

<div id="emptyStateContainer" style="display: <?php echo empty($shipments) ? 'block' : 'none'; ?>; padding: 4rem 2rem; text-align: center; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
  <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#6b7280" style="opacity: 0.15; margin: 0 auto 1.5rem; stroke-width: 1.5;">
    <rect x="1" y="3" width="15" height="13" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M16 8H20L23 11V16H16V8Z" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="5.5" cy="18.5" r="2.5"/>
    <circle cx="18.5" cy="18.5" r="2.5"/>
  </svg>
  <h3 style="font-size: 1.25rem; font-weight: 600; color: #111827; margin: 0 0 0.75rem 0;">No shipments yet</h3>
  <p style="font-size: 0.9375rem; color: #6b7280; margin: 0 auto 1.5rem; max-width: 28rem; line-height: 1.6;">
    Get started by creating your first shipment. Click the "Create Shipment" button above to begin.
  </p>
  <button onclick="showNewShipmentModal()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
    Create Your First Shipment
  </button>
</div>

<div id="newShipmentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
  <div style="background: white; border-radius: 12px; width: 92%; max-width: 1140px; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); animation: slideUp 0.3s ease; display: flex; flex-direction: column;">
    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid hsl(214 20% 92%); display: flex; align-items: center; justify-content: space-between; background: white; z-index: 10; flex-shrink: 0;">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(113,148,165,0.25);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"/><path d="M16 8L20 6L23 12L16 16V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </div>
        <div>
          <h2 style="font-size: 1.25rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0; letter-spacing: -0.02em;">Create New Shipment</h2>
          <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0; font-weight: 500;">Configure packages, set delivery options, and manage tracking</p>
        </div>
      </div>
      <button onclick="closeNewShipmentModal()" style="padding: 0.625rem; background: hsl(0 0% 98%); border: 1px solid hsl(214 20% 92%); cursor: pointer; border-radius: 8px; transition: all 0.2s; color: hsl(215 16% 47%);" onmouseover="this.style.background='hsl(0 0% 95%)'; this.style.borderColor='hsl(214 20% 85%)'" onmouseout="this.style.background='hsl(0 0% 98%)'; this.style.borderColor='hsl(214 20% 92%)'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M18 6L6 18M6 6L18 18" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
    
    <form id="newShipmentForm" style="padding: 0; display: flex; flex-direction: column; flex: 1; min-height: 0;">
      <div id="shipmentGrid" style="display: grid; grid-template-columns: minmax(0,1fr) 340px; gap: 1.5rem; padding: 1.25rem 1.5rem; flex: 1; overflow-y: auto; align-items: start;">
        <!-- LEFT COLUMN -->
        <div style="display: flex; flex-direction: column; min-height: 0;">
          <!-- Tabs -->
          <div style="display: flex; background: hsl(240 5% 96%); border-bottom: 2px solid hsl(240 6% 90%); padding: 0.375rem; gap: 0.375rem; overflow-x: auto; flex-shrink: 0; margin-bottom: 1rem; border-radius: 10px;">
            <button type="button" class="shipment-tab-btn" data-tab="details" onclick="switchShipmentTab('details')" style="padding: 0.625rem 1.125rem; border: none; background: white; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-weight: 600; cursor: pointer; white-space: nowrap; color: #7194A5; font-size: 0.8125rem; transition: all 0.2s;">📋 Details</button>
            <button type="button" class="shipment-tab-btn" data-tab="packages" onclick="switchShipmentTab('packages')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">📦 Packages</button>
            <button type="button" class="shipment-tab-btn" data-tab="address" onclick="switchShipmentTab('address')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">🏠 Address</button>
            <button type="button" class="shipment-tab-btn" data-tab="options" onclick="switchShipmentTab('options')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">⚙️ Options</button>
          </div>

          <!-- Tab: Details -->
          <div id="shipment-tab-details" class="shipment-tab-content" style="padding: 1.25rem; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Order Number *</label>
                <input type="text" name="order" required placeholder="ORD-2024-001" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; font-family: monospace;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Customer Name *</label>
                <input type="text" name="customer" required placeholder="John Doe" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Carrier *</label>
                <select name="carrier" required onchange="updateCarrierOptions(this.value)" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                  <option value="">Select carrier</option>
                  <option value="FedEx">FedEx</option>
                  <option value="UPS">UPS</option>
                  <option value="USPS">USPS</option>
                  <option value="DHL">DHL Express</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Service Type *</label>
                <select name="service_type" required onchange="updateShipmentSummary()" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                  <option value="standard">Standard Ground</option>
                  <option value="express">Express Overnight</option>
                  <option value="2day">2-Day Delivery</option>
                  <option value="priority">Priority Mail</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Ship Date *</label>
                <input type="date" name="date" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Expected Delivery *</label>
                <input type="date" name="expected_delivery" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
              </div>
            </div>
          </div>

          <!-- Tab: Packages -->
          <div id="shipment-tab-packages" class="shipment-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin: 0;">Package Details</h3>
              <button type="button" onclick="addPackage()" style="padding: 0.375rem 0.875rem; background: #000000; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer;">+ Add Package</button>
            </div>
            <div id="packagesContainer"></div>
          </div>

          <!-- Tab: Address -->
          <div id="shipment-tab-address" class="shipment-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div>
              <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Shipping Address *</label>
              <textarea name="address" required rows="4" placeholder="123 Main Street&#10;City, State 12345&#10;Country" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
            </div>
          </div>

          <!-- Tab: Options -->
          <div id="shipment-tab-options" class="shipment-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Insurance Value (<?php echo CurrencyHelper::symbol(); ?>)</label>
                <input type="number" name="insurance" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
              </div>
              <div>
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                  <input type="checkbox" name="signature_required" style="width: 18px; height: 18px; cursor: pointer;" onchange="updateShipmentSummary()">
                  <span style="font-size: 0.875rem; font-weight: 500; color: #374151;">Signature Required on Delivery</span>
                </label>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Special Instructions</label>
                <textarea name="instructions" rows="3" placeholder="Leave at front door, call before delivery, etc..." style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem; min-height: 0; position: sticky; top: 0; align-self: start; max-width: 340px;">
          <!-- Summary -->
          <div style="background: linear-gradient(135deg, hsl(240 5% 98%), white); border: 1.5px solid hsl(214 20% 88%); border-radius: 10px; padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.875rem;">
              <div style="width: 32px; height: 32px; background: linear-gradient(135deg, rgba(113,148,165,0.15), rgba(113,148,165,0.25)); border-radius: 7px; display: flex; align-items: center; justify-content: center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><path d="M16 8H20L23 11V16H16V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              </div>
              <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Shipment Summary</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.625rem;">
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Packages</span><span id="modalPackagesCount" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">0</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Total Weight</span><span id="modalTotalWeight" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">0.00 lbs</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Carrier</span><span id="modalCarrier" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">—</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Service</span><span id="modalService" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">—</span></div>
            </div>
          </div>

          <!-- Tracking -->
          <div style="background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 10px; padding: 1rem;">
            <label style="display: block; font-size: 0.8125rem; font-weight: 700; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">Tracking Number *</label>
            <div style="display: flex; gap: 0.5rem;">
              <input type="text" name="tracking" required placeholder="1Z999AA10123456784" style="flex: 1; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; font-family: monospace;">
              <button type="button" onclick="generateTracking()" style="padding: 0.625rem 1rem; background: #f3f4f6; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer;">Generate</button>
            </div>
          </div>

          <!-- Actions -->
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <button type="button" onclick="closeNewShipmentModal()" style="width: 100%; background: white; color: hsl(220 14% 11%); border: 1.5px solid hsl(214 20% 85%); padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.8125rem;">Cancel</button>
            <button type="button" onclick="printShippingLabel()" style="width: 100%; background: #6b7280; color: white; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.8125rem;">Print Label</button>
            <button type="submit" style="width: 100%; background: #000000; color: white; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.875rem;">Create Shipment</button>
          </div>
        </div>
      </div>
    </form>
  </div>
 </div>

<script>
let packageCounter = 0;
const packages = [];

function showNewShipmentModal() {
  const modal = document.getElementById('newShipmentModal');
  modal.style.display = 'flex';
  document.getElementById('newShipmentForm').reset();
  const today = new Date().toISOString().split('T')[0];
  const nextWeek = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
  document.querySelector('#newShipmentForm input[name="date"]').value = today;
  document.querySelector('#newShipmentForm input[name="expected_delivery"]').value = nextWeek;
  document.getElementById('packagesContainer').innerHTML = '';
  packages.length = 0;
  packageCounter = 0;
  addPackage();
  switchShipmentTab('details');
  updateShipmentSummary();
  applyShipmentResponsive();
}

function closeNewShipmentModal() {
  document.getElementById('newShipmentModal').style.display = 'none';
}

function addPackage() {
  const pkgId = packageCounter++;
  const container = document.getElementById('packagesContainer');
  
  const pkgDiv = document.createElement('div');
  pkgDiv.id = `package-${pkgId}`;
  pkgDiv.style.cssText = 'padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 0.75rem;';
  
  pkgDiv.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <h4 style="font-weight: 600; color: #111827; margin: 0;">Package #${pkgId + 1}</h4>
      <button type="button" onclick="removePackage(${pkgId})" style="padding: 0.25rem 0.5rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 4px; cursor: pointer; font-size: 0.875rem;">Remove</button>
    </div>
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem;">
      <input type="number" placeholder="Weight (lbs)" class="pkg-weight" min="0" step="0.1" value="5" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateShipmentSummary()">
      <input type="number" placeholder="Length (in)" class="pkg-length" min="0" step="0.1" value="12" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateShipmentSummary()">
      <input type="number" placeholder="Width (in)" class="pkg-width" min="0" step="0.1" value="10" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateShipmentSummary()">
      <input type="number" placeholder="Height (in)" class="pkg-height" min="0" step="0.1" value="8" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateShipmentSummary()">
    </div>
  `;
  
  container.appendChild(pkgDiv);
  packages.push(pkgId);
  updateShipmentSummary();
}

function removePackage(pkgId) {
  const element = document.getElementById(`package-${pkgId}`);
  if (element) {
    element.remove();
    const index = packages.indexOf(pkgId);
    if (index > -1) packages.splice(index, 1);
  }
  if (packages.length === 0) addPackage();
  updateShipmentSummary();
}

function getPackagesData() {
  const pkgs = [];
  packages.forEach(pkgId => {
    const pkgEl = document.getElementById(`package-${pkgId}`);
    if (pkgEl) {
      pkgs.push({
        weight: parseFloat(pkgEl.querySelector('.pkg-weight').value) || 0,
        length: parseFloat(pkgEl.querySelector('.pkg-length').value) || 0,
        width: parseFloat(pkgEl.querySelector('.pkg-width').value) || 0,
        height: parseFloat(pkgEl.querySelector('.pkg-height').value) || 0
      });
    }
  });
  return pkgs;
}

function generateTracking() {
  const carrier = document.querySelector('select[name="carrier"]').value;
  let prefix = '1Z';
  if (carrier === 'FedEx') prefix = '7' + Math.floor(Math.random() * 9);
  else if (carrier === 'UPS') prefix = '1Z';
  else if (carrier === 'USPS') prefix = '92';
  else if (carrier === 'DHL') prefix = 'JD';
  
  const tracking = prefix + Math.random().toString(36).substr(2, 12).toUpperCase() + Math.floor(Math.random() * 10000);
  document.querySelector('input[name="tracking"]').value = tracking;
  Toast.success('Tracking number generated!');
}

function updateCarrierOptions(carrier) {
  const serviceSelect = document.querySelector('select[name="service_type"]');
  serviceSelect.innerHTML = '';
  
  const services = {
    'FedEx': ['FedEx Ground', 'FedEx Express', 'FedEx 2Day', 'FedEx Priority Overnight'],
    'UPS': ['UPS Ground', 'UPS Next Day Air', 'UPS 2nd Day Air', 'UPS 3 Day Select'],
    'USPS': ['USPS Priority Mail', 'USPS First Class', 'USPS Priority Express', 'USPS Media Mail'],
    'DHL': ['DHL Express Worldwide', 'DHL Express 12:00', 'DHL Express 9:00', 'DHL Economy Select']
  };
  
  const options = services[carrier] || ['Standard Ground', 'Express Overnight', '2-Day Delivery'];
  options.forEach(opt => {
    const option = document.createElement('option');
    option.value = opt;
    option.textContent = opt;
    serviceSelect.appendChild(option);
  });
  updateShipmentSummary();
}

function printShippingLabel() {
  Toast.info('Generating shipping label...');
  setTimeout(() => {
    Toast.success('Shipping label ready to print!');
    console.log('Printing label with form data');
  }, 1000);
}

document.getElementById('newShipmentForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const data = Object.fromEntries(formData);
  data.packages = getPackagesData();
  data.totalWeight = data.packages.reduce((sum, pkg) => sum + pkg.weight, 0).toFixed(2);
  
  console.log('Creating new shipment:', data);
  Toast.success('Shipment created successfully! Tracking: ' + data.tracking);
  closeNewShipmentModal();
});

function switchShipmentTab(name) {
  const tabs = document.querySelectorAll('.shipment-tab-content');
  const btns = document.querySelectorAll('.shipment-tab-btn');
  tabs.forEach(t => { t.style.display = t.id === `shipment-tab-${name}` ? 'block' : 'none'; });
  btns.forEach(b => {
    if (b.getAttribute('data-tab') === name) {
      b.style.background = 'white';
      b.style.borderBottom = '3px solid #7194A5';
      b.style.color = '#7194A5';
      b.style.fontWeight = '600';
    } else {
      b.style.background = 'transparent';
      b.style.borderBottom = 'none';
      b.style.color = 'hsl(215 16% 47%)';
      b.style.fontWeight = '500';
    }
  });
}

function updateShipmentSummary() {
  try {
    const pkgs = getPackagesData();
    const totalWeight = (pkgs.reduce((sum, p) => sum + (parseFloat(p.weight) || 0), 0)).toFixed(2);
    const carrierSel = document.querySelector('select[name="carrier"]');
    const serviceSel = document.querySelector('select[name="service_type"]');
    const carrier = carrierSel?.value || '';
    const serviceText = serviceSel && serviceSel.options.length ? serviceSel.options[serviceSel.selectedIndex].text : '';

    const packagesEl = document.getElementById('modalPackagesCount');
    const weightEl = document.getElementById('modalTotalWeight');
    const carrierEl = document.getElementById('modalCarrier');
    const serviceEl = document.getElementById('modalService');
    if (packagesEl) packagesEl.textContent = String(pkgs.length);
    if (weightEl) weightEl.textContent = `${totalWeight} lbs`;
    if (carrierEl) carrierEl.textContent = carrier || '—';
    if (serviceEl) serviceEl.textContent = serviceText || '—';
  } catch (e) {
    console.warn('Failed to update shipment summary', e);
  }
}

function applyShipmentResponsive() {
  try {
    const grid = document.getElementById('shipmentGrid');
    if (!grid) return;
    if (window.innerWidth < 1024) {
      grid.style.gridTemplateColumns = '1fr';
    } else {
      grid.style.gridTemplateColumns = '1fr 340px';
    }
  } catch {}
}

document.getElementById('newShipmentModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeNewShipmentModal();
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('newShipmentModal');
    if (modal && modal.style.display === 'flex') {
      closeNewShipmentModal();
    }
  }
});
window.addEventListener('resize', applyShipmentResponsive);

function trackShipment(tracking) {
  Toast.info('Opening tracking for: ' + tracking);
  window.open(`https://www.google.com/search?q=track+${tracking}`, '_blank');
}

function printLabel(id) {
  Toast.success('Label printed for shipment: ' + id);
}
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
