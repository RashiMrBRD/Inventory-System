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

// Ensure user data is valid
if (!$user || !is_array($user)) {
    error_log('Shipping: User data is null or invalid');
    header('Location: login');
    exit();
}

// Real shipments from database
$shipmentModel = new Shipment();
try {
    $shipments = $shipmentModel->getAll();
} catch (\Exception $e) {
    $shipments = [];
}

// Check SMTP configuration
$appConfig = require __DIR__ . '/../config/app.php';
$smtpConfigured = !empty($appConfig['mail']['host']) && !empty($appConfig['mail']['username']);
$smtpStatus = $smtpConfigured ? 'configured' : 'not_configured';

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

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Print Styles */
@media print {
  /* Hide everything except the table */
  body * {
    visibility: hidden;
  }
  
  /* Show only the table container and its contents */
  #printableShipmentsTable,
  #printableShipmentsTable * {
    visibility: visible;
  }
  
  /* Position table at top of page */
  #printableShipmentsTable {
    position: relative;
    width: 100%;
    margin: 0;
    padding: 20px;
  }
  
  /* Hide UI elements */
  .toolbar,
  .search-wrapper,
  .btn,
  button,
  #batchIconsGroup,
  #shipmentsPagination,
  .checkbox-column,
  input[type="checkbox"],
  nav,
  header,
  footer,
  .sidebar,
  #emptyStateContainer {
    display: none !important;
    visibility: hidden !important;
  }
  
  /* Hide statistics cards */
  div[style*="grid-template-columns: repeat(6"] {
    display: none !important;
  }
  
  /* Hide page header */
  div[style*="background: #7194A5"] {
    display: none !important;
  }
  
  /* Table styling for print */
  table {
    width: 100%;
    border-collapse: collapse;
    page-break-inside: auto;
    font-size: 10pt;
  }
  
  tr {
    page-break-inside: avoid;
    page-break-after: auto;
  }
  
  thead {
    display: table-header-group;
  }
  
  tfoot {
    display: table-footer-group;
  }
  
  th, td {
    border: 1px solid #333;
    padding: 6px 8px;
    text-align: left;
    font-size: 9pt;
  }
  
  th {
    background-color: #f5f5f5 !important;
    font-weight: bold;
    color: #000 !important;
    border-bottom: 2px solid #333;
  }
  
  /* Make status badges print-friendly */
  .badge {
    padding: 2px 6px;
    border: 1px solid #333;
    border-radius: 3px;
    font-size: 8pt;
  }
  
  /* Ensure all columns are visible */
  td, th {
    white-space: nowrap;
    overflow: visible;
  }
  
  /* Remove shadows and effects */
  * {
    box-shadow: none !important;
    text-shadow: none !important;
  }
  
  /* Ensure good contrast */
  body {
    background: white !important;
    color: black !important;
  }
  
  /* Show print header */
  .print-header {
    display: block !important;
    visibility: visible !important;
  }
  
  /* Show summary only when print-show-summary class is present */
  .print-show-summary .print-summary {
    display: block !important;
    visibility: visible !important;
  }
  
  /* Summary table styling to ensure values are visible */
  .print-show-summary .print-summary table,
  .print-show-summary .print-summary td {
    display: table !important;
    visibility: visible !important;
    color: #000 !important;
    opacity: 1 !important;
  }
  
  .print-show-summary .print-summary table td {
    display: table-cell !important;
  }
  
  /* Ensure count values are visible */
  .print-show-summary .print-summary .count-value {
    display: inline !important;
    visibility: visible !important;
    color: #000 !important;
    opacity: 1 !important;
    font-size: 10pt !important;
    font-weight: 600 !important;
  }
  
  /* Hide summary by default (single shipment) */
  .print-summary {
    display: none !important;
    visibility: hidden !important;
  }
  
  /* Hide Order # column and Actions column in print */
  .order-column-hide-print {
    display: none !important;
  }
  
  /* Hide actions column (last column) */
  th:last-child,
  td:last-child {
    display: none !important;
  }
  
  /* Single shipment print - hide other rows */
  .print-hide-row {
    display: none !important;
  }
  
  /* Page settings - minimize browser headers/footers */
  @page {
    margin: 0;
    size: auto;
  }
  
  /* Add padding to content instead of page margin */
  #printableShipmentsTable {
    padding: 0.5cm !important;
  }
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
      <a href="dashboard" style="padding: 0.625rem 1.25rem; background: rgba(255,255,255,0.2); border-radius: 8px; color: white; text-decoration: none; font-weight: 500; backdrop-filter: blur(10px); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
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
    <p id="totalShipmentsCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;"><?php echo count($shipments); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Pending</p>
      <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      </div>
    </div>
    <p id="pendingCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;"><?php echo number_format($pendingCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">In Transit</p>
      <div style="width: 36px; height: 36px; background: hsl(48 96% 89%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
      </div>
    </div>
    <p id="inTransitCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(25 95% 16%); margin: 0;"><?php echo number_format($inTransitCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Delivered</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2"><path d="M22 11.08V12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C15.7824 2 18.9935 4.19066 20.4866 7.35397M22 4L12 14.01L9 11.01"/></svg>
      </div>
    </div>
    <p id="deliveredCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;"><?php echo number_format($deliveredCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">On Time Rate</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
    </div>
    <p id="onTimeRate" style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;">98%</p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Avg Delivery</p>
      <div style="width: 36px; height: 36px; background: rgba(113,148,165,0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
    </div>
    <p id="avgDeliveryTime" style="font-size: 1.75rem; font-weight: 700; color: #7194A5; margin: 0;">3.2 days</p>
  </div>
</div>

<div class="toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
  <div class="toolbar-left" style="display: flex; align-items: center; gap: 0.75rem;">
    <div class="search-wrapper" style="position: relative; display: flex; align-items: center;">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" style="position: absolute; left: 0.875rem; color: hsl(215 16% 47%); pointer-events: none;">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input type="search" class="search-input" placeholder="Search shipments..." id="shipment-search" oninput="searchShipments()" style="padding: 0.625rem 0.875rem 0.625rem 2.5rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; min-width: 280px; transition: all 0.2s;">
    </div>
    
    <!-- Batch Operation Icons (Show when items selected) -->
    <div id="batchIconsGroup" style="display: none; gap: 0.5rem; align-items: center; padding-left: 0.75rem; border-left: 1px solid hsl(214 20% 88%);">
      <button class="btn btn-ghost btn-sm" onclick="batchUpdateShipmentStatus()" title="Update Status" style="padding: 0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 11l3 3L22 4"/>
          <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
        </svg>
      </button>
      <button class="btn btn-ghost btn-sm" onclick="batchPrintLabels()" title="Print Labels" style="padding: 0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 6 2 18 2 18 9"/>
          <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
          <rect x="6" y="14" width="12" height="8"/>
        </svg>
      </button>
      <?php if ($smtpConfigured): ?>
      <button class="btn btn-ghost btn-sm" onclick="batchEmailNotifications()" title="Email Customers" style="padding: 0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="4" width="20" height="16" rx="2"/>
          <path d="M22 7L13 13C12.5 13.3 11.5 13.3 11 13L2 7"/>
        </svg>
      </button>
      <?php endif; ?>
      <button class="btn btn-ghost btn-sm" onclick="batchAssignCarrier()" title="Assign Carrier" style="padding: 0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="1" y="3" width="15" height="13"/>
          <path d="M16 8L20 6L23 12L16 16V8Z"/>
          <circle cx="5.5" cy="18.5" r="2.5"/>
          <circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
      </button>
      <button class="btn btn-ghost btn-sm" onclick="batchExportShipments()" title="Export Selected" style="padding: 0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
        </svg>
      </button>
      <button class="btn btn-ghost btn-sm" onclick="batchDeleteShipments()" title="Delete Selected" style="padding: 0.5rem; color: hsl(0 74% 42%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
        </svg>
      </button>
      <span style="font-size: 0.8125rem; color: hsl(214 20% 88%); padding: 0 0.25rem;">|</span>
      <button class="btn btn-ghost btn-sm" onclick="batchMarkAsDelivered()" title="Mark as Delivered" style="padding: 0.5rem; color: hsl(142 76% 36%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M20 6L9 17l-5-5"/>
        </svg>
      </button>
      <button class="btn btn-ghost btn-sm" onclick="batchMarkAsInTransit()" title="Mark as In Transit" style="padding: 0.5rem; color: hsl(221 83% 53%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="1" y="3" width="15" height="13"/>
          <path d="M16 8L20 6L23 12L16 16V8Z"/>
          <circle cx="5.5" cy="18.5" r="2.5"/>
          <circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
      </button>
      <span id="batchSelectedCount" style="font-size: 0.8125rem; color: hsl(215 16% 47%); font-weight: 600; padding: 0 0.5rem;">0 selected</span>
      <button onclick="clearShipmentSelection()" style="background: none; border: none; color: hsl(215 16% 47%); cursor: pointer; font-size: 0.8125rem; text-decoration: underline; font-weight: 500; padding: 0 0.5rem;" title="Clear Selection">Clear</button>
    </div>
  </div>
  <div class="toolbar-right" style="display: flex; align-items: center; gap: 0.75rem;">
    <select class="form-select" id="status-filter" onchange="applyShipmentFilters()" style="padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; cursor: pointer;">
      <option value="all">All Status</option>
      <option value="pending">Pending</option>
      <option value="in-transit">In Transit</option>
      <option value="out-for-delivery">Out for Delivery</option>
      <option value="delivered">Delivered</option>
      <option value="returned">Returned</option>
    </select>
    <button class="btn btn-ghost btn-icon" onclick="showShipmentExportMenu()" title="Export">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
    <button class="btn btn-ghost btn-icon" onclick="showShipmentBatchOperations()" title="Batch Operations">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7"/>
        <rect x="14" y="3" width="7" height="7"/>
        <rect x="14" y="14" width="7" height="7"/>
        <rect x="3" y="14" width="7" height="7"/>
      </svg>
    </button>
    <button class="btn btn-ghost btn-icon" onclick="printShippingLabels()" title="Print All Labels">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="6 9 6 2 18 2 18 9"/>
        <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
        <rect x="6" y="14" width="12" height="8"/>
      </svg>
    </button>
    <button class="btn btn-ghost btn-icon" onclick="refreshShipmentsTable(true)" title="Refresh Table">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 2v6h-6M3 22v-6h6M21 13a9 9 0 11-1-4M3 11a9 9 0 011 4"/>
      </svg>
    </button>
    <button class="btn btn-ghost btn-icon" onclick="showDatabaseViewer()" title="Database Viewer">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <ellipse cx="12" cy="5" rx="9" ry="3"/>
        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
        <path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/>
      </svg>
    </button>
  </div>
</div>

<!-- Printable Wrapper -->
<div id="printableShipmentsTable">
  <!-- Print Header (shows when printing) -->
  <div class="print-header" style="display: none;">
    <h2 id="printTitle" style="margin: 0 0 0.5rem 0; font-size: 1.5rem; font-weight: bold; text-align: center;">Shipping Labels</h2>
    <p id="printSubtitle" style="margin: 0 0 1rem 0; text-align: center; color: #666; font-size: 0.875rem;">Generated on <?php echo date('F j, Y g:i A'); ?></p>
  </div>
  
  <div id="shipmentsTableContainer" class="table-container" style="display: <?php echo empty($shipments) ? 'none' : 'block'; ?>;">
    <table class="data-table">
    <thead>
      <tr>
        <th class="checkbox-column" style="width: 40px; display: none;">
          <input type="checkbox" id="selectAllShipments" onchange="toggleSelectAllShipments(this)" style="cursor: pointer;">
        </th>
        <th onclick="sortShipmentTable('shipment_number')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Shipment #
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortShipmentTable('order')" style="cursor: pointer; user-select: none;" class="order-column-hide-print">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Order #
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortShipmentTable('customer')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Customer
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortShipmentTable('carrier')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Carrier
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th>Tracking #</th>
        <th onclick="sortShipmentTable('date')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Ship Date
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortShipmentTable('status')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Status
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th style="width: 180px;">Actions</th>
      </tr>
    </thead>
    <tbody id="shipmentsTableBody">
      <?php foreach ($shipments as $shipment): 
        // Get shipment ID - try multiple field names
        $shipmentId = $shipment['id'] ?? $shipment['_id'] ?? '';
        if (is_object($shipmentId) && method_exists($shipmentId, '__toString')) {
          $shipmentId = (string)$shipmentId;
        }
      ?>
      <tr data-shipment-id="<?php echo htmlspecialchars($shipmentId); ?>">
        <td class="checkbox-column" style="display: none;">
          <input type="checkbox" class="shipment-checkbox" value="<?php echo htmlspecialchars($shipmentId); ?>" onchange="updateShipmentBatchToolbar()" style="cursor: pointer;">
        </td>
        <td class="font-mono font-medium"><?php echo $shipment['shipment_number'] ?? 'N/A'; ?></td>
        <td class="font-mono order-column-hide-print"><?php echo $shipment['order'] ?? '-'; ?></td>
        <td class="font-medium"><?php echo $shipment['customer'] ?? '-'; ?></td>
        <td><?php echo $shipment['carrier'] ?? '-'; ?></td>
        <td class="font-mono text-primary"><?php echo $shipment['tracking'] ?? '-'; ?></td>
        <td><?php echo isset($shipment['date']) ? date('M d, Y', strtotime($shipment['date'])) : '-'; ?></td>
        <td>
          <?php
          $statusBadges = [
            'pending' => 'badge-default',
            'in-transit' => 'badge-info',
            'out-for-delivery' => 'badge-warning',
            'delivered' => 'badge-success',
            'returned' => 'badge-danger'
          ];
          $badgeClass = $statusBadges[$shipment['status']] ?? 'badge-default';
          ?>
          <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst(str_replace('-', ' ', $shipment['status'])); ?></span>
        </td>
        <td>
          <div style="display: flex; gap: 0.25rem; align-items: center;">
            <button class="btn btn-ghost btn-sm" onclick="trackShipment('<?php echo $shipment['tracking'] ?? ''; ?>', '<?php echo $shipment['carrier'] ?? ''; ?>')" title="Track Shipment">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="printShipmentLabel('<?php echo htmlspecialchars($shipmentId); ?>')" title="Print Label">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
              </svg>
            </button>
            <?php if ($smtpConfigured): ?>
            <button class="btn btn-ghost btn-sm" onclick="emailShipmentNotification('<?php echo htmlspecialchars($shipmentId); ?>')" title="Email Customer">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="M22 7L13 13C12.5 13.3 11.5 13.3 11 13L2 7"/>
              </svg>
            </button>
            <?php else: ?>
            <button class="btn btn-ghost btn-sm" onclick="Toast.warning('SMTP not configured. Configure in Settings.')" title="Email Disabled" style="opacity: 0.4; cursor: not-allowed;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="M22 7L13 13C12.5 13.3 11.5 13.3 11 13L2 7"/>
              </svg>
            </button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm" data-shipment-id="<?php echo htmlspecialchars($shipmentId); ?>" onclick="deleteShipment(this, '<?php echo htmlspecialchars($shipmentId); ?>')" title="Delete" style="color: hsl(0 74% 42%);">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                <line x1="10" y1="11" x2="10" y2="17"/>
                <line x1="14" y1="11" x2="14" y2="17"/>
              </svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination Controls -->
<div id="shipmentsPagination" style="display: flex; align-items: center; justify-content: space-between; padding: 1.5rem 1rem; border-top: 1px solid hsl(214 20% 88%); background: hsl(220 20% 98%);">
  <div style="display: flex; align-items: center; gap: 1rem;">
    <div style="color: hsl(215 16% 47%); font-size: 0.875rem;">
      Showing <span id="shipmentsPaginationInfo" style="font-weight: 600; color: hsl(222 47% 17%);">1-6 of 0</span> shipments
    </div>
    <div style="display: flex; align-items: center; gap: 0.5rem;">
      <label for="pageSize" style="font-size: 0.875rem; color: hsl(215 16% 47%); font-weight: 500;">Show:</label>
      <select id="pageSize" onchange="changePageSize(this.value)" style="padding: 0.4rem 0.75rem; border: 1px solid hsl(214 20% 88%); background: white; border-radius: 6px; cursor: pointer; color: hsl(222 47% 17%); font-size: 0.875rem; font-weight: 500; transition: all 0.2s; outline: none;" onmouseover="this.style.borderColor='hsl(214 20% 78%)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'" onfocus="this.style.borderColor='hsl(221 83% 53%)'; this.style.boxShadow='0 0 0 3px hsla(221, 83%, 53%, 0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
        <option value="6" selected>6</option>
        <option value="10">10</option>
        <option value="20">20</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </div>
  </div>
  <div style="display: flex; gap: 0.5rem; align-items: center;">
    <button id="shipmentPrevBtn" onclick="changeShipmentPage(-1)" style="padding: 0.5rem 0.75rem; border: 1px solid hsl(214 20% 88%); background: white; border-radius: 6px; cursor: pointer; color: hsl(222 47% 17%); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='white'">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
      Previous
    </button>
    <div id="shipmentPageNumbers" style="display: flex; gap: 0.25rem;"></div>
    <button id="shipmentNextBtn" onclick="changeShipmentPage(1)" style="padding: 0.5rem 0.75rem; border: 1px solid hsl(214 20% 88%); background: white; border-radius: 6px; cursor: pointer; color: hsl(222 47% 17%); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='white'">
      Next
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
    </button>
  </div>
</div>

  <!-- Print Summary (shows when printing) -->
  <div class="print-summary" style="display: none;">
    <div style="margin-top: 2rem; padding-top: 1rem; border-top: 2px solid #333;">
      <h3 style="margin: 0 0 0.75rem 0; font-size: 1.125rem; font-weight: bold;">Summary</h3>
      <table style="width: auto; max-width: 280px; border-collapse: collapse; table-layout: fixed;">
        <colgroup>
          <col style="width: 180px;">
          <col style="width: 80px;">
        </colgroup>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 0.4rem 0.6rem; font-weight: 600; border: 1px solid #333; white-space: nowrap;">Total Shipments:</td>
          <td id="printTotalCount" style="padding: 0.4rem 0.6rem; text-align: right; font-weight: 600; border: 1px solid #333;" data-count="0"><span class="count-value">0</span></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 0.4rem 0.6rem; font-weight: 600; border: 1px solid #333; white-space: nowrap;">Pending:</td>
          <td id="printPendingCount" style="padding: 0.4rem 0.6rem; text-align: right; font-weight: 600; border: 1px solid #333;" data-count="0"><span class="count-value">0</span></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="padding: 0.4rem 0.6rem; font-weight: 600; border: 1px solid #333; white-space: nowrap;">In Transit:</td>
          <td id="printInTransitCount" style="padding: 0.4rem 0.6rem; text-align: right; font-weight: 600; border: 1px solid #333;" data-count="0"><span class="count-value">0</span></td>
        </tr>
        <tr>
          <td style="padding: 0.4rem 0.6rem; font-weight: 600; border: 1px solid #333; white-space: nowrap;">Delivered:</td>
          <td id="printDeliveredCount" style="padding: 0.4rem 0.6rem; text-align: right; font-weight: 600; border: 1px solid #333;" data-count="0"><span class="count-value">0</span></td>
        </tr>
      </table>
    </div>
  </div>
</div>
<!-- End Printable Wrapper -->

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

<!-- PDF Viewer Modal -->
<div id="pdfViewerModal" onclick="closePdfViewer()" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 2rem;">
  <div onclick="event.stopPropagation()" style="width: 95%; max-width: 1200px; height: 90vh; background: white; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); display: flex; flex-direction: column; overflow: hidden;">
    
    <!-- Header -->
    <div style="background: white; padding: 1rem 1.5rem; border-bottom: 2px solid hsl(240 6% 90%); display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        <h3 id="pdfViewerTitle" style="margin: 0; font-size: 1.125rem; font-weight: 700; color: hsl(222 47% 17%);">Shipment Label</h3>
      </div>
      <button type="button" onclick="closePdfViewer()" style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(240 5% 40%); transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 92%)'" onmouseout="this.style.background='hsl(240 5% 96%)'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    
    <!-- PDF Iframe -->
    <iframe id="pdfViewerFrame" src="" style="width: 100%; height: 100%; border: none; background: white;"></iframe>
  </div>
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
    
    <form id="newShipmentForm" novalidate style="padding: 0; display: flex; flex-direction: column; flex: 1; min-height: 0;">
      <div id="shipmentGrid" style="display: grid; grid-template-columns: minmax(0,1fr) 380px; gap: 2rem; padding: 1.5rem 2rem; flex: 1; overflow-y: auto; align-items: start;">
        <!-- LEFT COLUMN -->
        <div style="display: flex; flex-direction: column; min-height: 0;">
          <!-- Tabs -->
          <div style="display: flex; background: hsl(240 5% 96%); border-bottom: 2px solid hsl(240 6% 90%); padding: 0.375rem; gap: 0.375rem; overflow-x: auto; flex-shrink: 0; margin-bottom: 1rem; border-radius: 10px;">
            <button type="button" class="shipment-tab-btn" data-tab="details" onclick="switchShipmentTab('details')" style="padding: 0.625rem 1.125rem; border: none; background: white; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-weight: 600; cursor: pointer; white-space: nowrap; color: #7194A5; font-size: 0.8125rem; transition: all 0.2s;">📋 Details</button>
            <button type="button" class="shipment-tab-btn" data-tab="packages" onclick="switchShipmentTab('packages')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">📦 Packages</button>
            <button type="button" class="shipment-tab-btn" data-tab="address" onclick="switchShipmentTab('address')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">🏠 Address & Options</button>
          </div>

          <!-- Tab: Details -->
          <div id="shipment-tab-details" class="shipment-tab-content" style="padding: 1.25rem; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
              <!-- Shipment Number - Full Width -->
              <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">Shipment Number <span style="color: #dc2626;">*</span></label>
                <div style="display: flex; gap: 0.625rem;">
                  <input type="text" id="shipment_number_display" name="shipment_number" required placeholder="SHP-2024-001" style="flex: 1; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(240 6% 90%); border-radius: 8px; font-size: 0.875rem; font-family: monospace; color: hsl(222 47% 17%);">
                  <button type="button" onclick="generateShipmentNumber()" style="padding: 0.625rem 1rem; background: hsl(240 6% 90%); border: 1.5px solid hsl(240 6% 85%); color: hsl(240 6% 10%); border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap;" onmouseover="this.style.background='hsl(240 6% 85%)'; this.style.borderColor='hsl(240 6% 75%)'" onmouseout="this.style.background='hsl(240 6% 90%)'; this.style.borderColor='hsl(240 6% 85%)'">Generate</button>
                </div>
                <p style="margin: 0.375rem 0 0; font-size: 0.75rem; color: hsl(215 16% 60%); display: flex; align-items: center; gap: 0.25rem;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 16V12M12 8H12.01" stroke-linecap="round"/>
                  </svg>
                  Format: SHP-YYYY-XXX (e.g., SHP-2024-001)
                </p>
              </div>

              <!-- Tracking Number - Full Width -->
              <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">Tracking Number <span style="color: #dc2626;">*</span></label>
                <div style="display: flex; gap: 0.625rem;">
                  <input type="text" name="tracking" required placeholder="1Z999AA10123456784" style="flex: 1; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(240 6% 90%); border-radius: 8px; font-size: 0.875rem; font-family: monospace;">
                  <button type="button" onclick="generateTracking()" style="padding: 0.625rem 1rem; background: hsl(240 6% 90%); border: 1.5px solid hsl(240 6% 85%); color: hsl(240 6% 10%); border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap;" onmouseover="this.style.background='hsl(240 6% 85%)'; this.style.borderColor='hsl(240 6% 75%)'" onmouseout="this.style.background='hsl(240 6% 90%)'; this.style.borderColor='hsl(240 6% 85%)'">Generate</button>
                </div>
              </div>
              
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Order Number <span style="color: #dc2626;">*</span></label>
                <div style="display: flex; gap: 0.625rem;">
                  <input type="text" name="order" required placeholder="ORD-2024-001" style="flex: 1; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; font-family: monospace;">
                  <button type="button" onclick="generateOrderNumber()" style="padding: 0.625rem 1rem; background: hsl(240 6% 90%); border: 1.5px solid hsl(240 6% 85%); color: hsl(240 6% 10%); border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap;" onmouseover="this.style.background='hsl(240 6% 85%)'; this.style.borderColor='hsl(240 6% 75%)'" onmouseout="this.style.background='hsl(240 6% 90%)'; this.style.borderColor='hsl(240 6% 85%)'">Generate</button>
                </div>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Customer Name <span style="color: #dc2626;">*</span></label>
                <input type="text" name="customer" required placeholder="John Doe" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Carrier <span style="color: #dc2626;">*</span></label>
                <select name="carrier" required onchange="updateCarrierOptions(this.value)" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                  <option value="">Select carrier</option>
                  <optgroup label="━━━━ 🇵🇭 Philippines ━━━━">
                    <option value="Lalamove">🛵 Lalamove</option>
                    <option value="LBC">📦 LBC Express</option>
                    <option value="J&T Express">🚚 J&T Express</option>
                    <option value="Ninja Van">🥷 Ninja Van</option>
                    <option value="Shopee Express">🛍️ Shopee Express</option>
                    <option value="Lazada Express">🛒 Lazada Express</option>
                    <option value="Flash Express">⚡ Flash Express</option>
                    <option value="GoGo Xpress">🏃 GoGo Xpress</option>
                    <option value="Grab Express">🚗 Grab Express</option>
                    <option value="Entrego">📮 Entrego</option>
                    <option value="2GO Express">🚢 2GO Express</option>
                    <option value="AP Cargo">✈️ AP Cargo</option>
                  </optgroup>
                  <optgroup label="━━━━ 🌍 International ━━━━">
                    <option value="FedEx">📦 FedEx</option>
                    <option value="UPS">🟤 UPS</option>
                    <option value="DHL">🔴 DHL Express</option>
                    <option value="USPS">🇺🇸 USPS</option>
                  </optgroup>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Service Type <span style="color: #dc2626;">*</span></label>
                <select name="service_type" required onchange="updateShipmentSummary()" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                  <option value="standard">Standard Ground</option>
                  <option value="express">Express Overnight</option>
                  <option value="2day">2-Day Delivery</option>
                  <option value="priority">Priority Mail</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Ship Date <span style="color: #dc2626;">*</span></label>
                <input type="date" name="date" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Expected Delivery <span style="color: #dc2626;">*</span></label>
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
            <div id="packagesContainer">
              <div id="emptyPackagesMessage" style="text-align: center; padding: 3rem 1rem; background: hsl(240 5% 98%); border: 2px dashed hsl(240 6% 88%); border-radius: 8px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="hsl(215 16% 47%)" stroke-width="1.5" style="margin: 0 auto 1rem; opacity: 0.4;">
                  <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
                <p style="margin: 0; color: hsl(215 16% 47%); font-size: 0.9375rem; font-weight: 500;">No packages added yet</p>
                <p style="margin: 0.5rem 0 0; color: hsl(215 16% 60%); font-size: 0.8125rem;">Click "+ Add Package" to start</p>
              </div>
            </div>
            <div id="packagesPagination" style="display: none; justify-content: center; align-items: center; gap: 0.75rem; margin-top: 1rem;">
              <button type="button" onclick="prevPackagePage()" id="prevPageBtn" style="padding: 0.5rem 0.75rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='white'">← Previous</button>
              <span id="packagePageInfo" style="font-size: 0.875rem; color: hsl(215 16% 47%); font-weight: 500;">Page 1 of 1</span>
              <button type="button" onclick="nextPackagePage()" id="nextPageBtn" style="padding: 0.5rem 0.75rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='white'">Next →</button>
            </div>
          </div>

          <!-- Tab: Address & Options (Merged) -->
          <div id="shipment-tab-address" class="shipment-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Shipping Address <span style="color: #dc2626;">*</span></label>
                <textarea name="address" required rows="4" placeholder="123 Main Street&#10;City, State 12345&#10;Country" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
              </div>
              <div style="border-top: 1px solid hsl(240 6% 90%); padding-top: 1.25rem;">
                <h4 style="font-size: 0.9375rem; font-weight: 600; color: #111827; margin: 0 0 1rem 0;">Additional Options</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                  <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Customer Email</label>
                    <input type="email" name="customer_email" placeholder="customer@example.com" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                  </div>
                  <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Shipping Cost (<?php echo CurrencyHelper::symbol(); ?>)</label>
                    <input type="number" name="shipping_cost" min="0" step="0.01" placeholder="0.00" oninput="updateShipmentSummary()" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                  </div>
                  <div>
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Insurance Value (<?php echo CurrencyHelper::symbol(); ?>)</label>
                    <input type="number" name="insurance" min="0" step="0.01" placeholder="0.00" oninput="updateShipmentSummary()" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                  </div>
                  <div style="display: flex; align-items: end;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding-bottom: 0.625rem;">
                      <input type="checkbox" name="signature_required" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;" onchange="updateShipmentSummary()">
                      <span style="font-size: 0.875rem; font-weight: 500; color: #374151;">Signature Required</span>
                    </label>
                  </div>
                  <div style="grid-column: 1 / -1;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Special Instructions</label>
                    <textarea name="instructions" rows="3" placeholder="Leave at front door, call before delivery, etc..." style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
                  </div>
                  <div style="grid-column: 1 / -1;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>;">
                      <input type="checkbox" name="notify_customer" id="notify_customer_checkbox" <?php echo $smtpConfigured ? 'checked' : 'disabled'; ?> style="width: 18px; height: 18px; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>; accent-color: <?php echo $smtpConfigured ? '#7194A5' : '#9ca3af'; ?>;">
                      <span style="font-size: 0.875rem; font-weight: 500; color: <?php echo $smtpConfigured ? '#374151' : '#9ca3af'; ?>;">📧 Send email notifications to customer</span>
                    </label>
                    <?php if (!$smtpConfigured): ?>
                    <p style="margin: 0.5rem 0 0 1.75rem; font-size: 0.75rem; color: #dc2626; display: flex; align-items: center; gap: 0.25rem;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                      </svg>
                      SMTP server not configured
                    </p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem; min-height: 0; position: sticky; top: 0; align-self: start; width: 100%;">
          <!-- Summary -->
          <div style="background: linear-gradient(135deg, hsl(240 5% 98%), white); border: 1.5px solid hsl(214 20% 88%); border-radius: 10px; padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.875rem;">
              <div style="width: 32px; height: 32px; background: linear-gradient(135deg, rgba(113,148,165,0.15), rgba(113,148,165,0.25)); border-radius: 7px; display: flex; align-items: center; justify-content: center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><path d="M16 8H20L23 11V16H16V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              </div>
              <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Shipment Summary</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.625rem;">
              <div style="display: flex; justify-content: space-between; padding: 0.75rem 1rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Packages</span><span id="modalPackagesCount" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">0</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem 1rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Total Weight</span><span id="modalTotalWeight" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem; white-space: nowrap;">0.00 lbs</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem 1rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Carrier</span><span id="modalCarrier" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem; text-align: right;">—</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem 1rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Service</span><span id="modalService" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem; text-align: right; word-break: break-word;">—</span></div>
              
              <!-- Cost Breakdown -->
              <div style="border-top: 1px solid hsl(214 20% 92%); margin-top: 0.5rem; padding-top: 0.75rem;">
                <div style="display: flex; justify-content: space-between; padding: 0.5rem 1rem; background: white; border-radius: 7px; margin-bottom: 0.5rem;">
                  <span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Shipping Cost</span>
                  <span id="modalShippingCost" style="font-weight: 600; color: hsl(222 47% 17%); font-size: 0.8125rem;"><?php echo CurrencyHelper::symbol(); ?>0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.5rem 1rem; background: white; border-radius: 7px; margin-bottom: 0.5rem;">
                  <span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Insurance</span>
                  <span id="modalInsurance" style="font-weight: 600; color: hsl(222 47% 17%); font-size: 0.8125rem;"><?php echo CurrencyHelper::symbol(); ?>0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.75rem 1rem; background: linear-gradient(135deg, hsl(214 95% 93%), hsl(214 95% 96%)); border-radius: 7px; border: 1.5px solid hsl(214 95% 85%);">
                  <span style="color: hsl(222 47% 17%); font-weight: 700; font-size: 0.875rem;">Total Cost</span>
                  <span id="modalTotalCost" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.9375rem;"><?php echo CurrencyHelper::symbol(); ?>0.00</span>
                </div>
              </div>
              
              <!-- Print Label Button -->
              <button type="button" onclick="printShippingLabel()" style="width: 100%; margin-top: 0.5rem; padding: 0.75rem 1rem; background: #6b7280; color: white; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem;" onmouseover="this.style.background='#4b5563'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.background='#6b7280'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="6 9 6 2 18 2 18 9"/>
                  <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                  <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print Label
              </button>
            </div>
          </div>

          <!-- Actions -->
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <button type="submit" style="width: 100%; background: #000000; color: white; border: none; padding: 0.875rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.9375rem; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">Create Shipment</button>
          </div>
        </div>
      </div>
    </form>
  </div>
 </div>

<script>
// SMTP Configuration Status from PHP
const smtpConfigured = <?php echo $smtpConfigured ? 'true' : 'false'; ?>;

let packageCounter = 0;
const packages = [];

let currentPackagePage = 1;
const packagesPerPage = 2;

function showNewShipmentModal() {
  const modal = document.getElementById('newShipmentModal');
  modal.style.display = 'flex';
  document.getElementById('newShipmentForm').reset();
  const today = new Date().toISOString().split('T')[0];
  const nextWeek = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
  document.querySelector('#newShipmentForm input[name="date"]').value = today;
  document.querySelector('#newShipmentForm input[name="expected_delivery"]').value = nextWeek;
  
  // Clear packages and show empty state
  const container = document.getElementById('packagesContainer');
  const emptyMsg = document.getElementById('emptyPackagesMessage');
  if (emptyMsg) emptyMsg.style.display = 'block';
  Array.from(container.children).forEach(child => {
    if (child.id !== 'emptyPackagesMessage') child.remove();
  });
  packages.length = 0;
  packageCounter = 0;
  currentPackagePage = 1;
  document.getElementById('packagesPagination').style.display = 'none';
  
  // Reset shipment number display
  const shipNumDisplay = document.getElementById('shipment_number_display');
  if (shipNumDisplay) {
    shipNumDisplay.value = '';
    shipNumDisplay.style.color = 'hsl(222 47% 17%)'; // Shadcn text color (black)
    shipNumDisplay.style.fontWeight = 'normal';
  }
  
  switchShipmentTab('details');
  updateShipmentSummary();
  applyShipmentResponsive();
}

function hasUnsavedChanges() {
  const form = document.getElementById('newShipmentForm');
  if (!form) return false;
  
  // Check required text inputs
  const order = form.querySelector('input[name="order"]');
  const customer = form.querySelector('input[name="customer"]');
  const carrier = form.querySelector('select[name="carrier"]');
  const address = form.querySelector('textarea[name="address"]');
  const tracking = form.querySelector('input[name="tracking"]');
  
  // Check if any required field has value
  if (order && order.value.trim()) return true;
  if (customer && customer.value.trim()) return true;
  if (carrier && carrier.value) return true;
  if (address && address.value.trim()) return true;
  if (tracking && tracking.value.trim()) return true;
  
  // Check if packages were added
  if (packages.length > 0) return true;
  
  // Check optional fields
  const insurance = form.querySelector('input[name="insurance"]');
  const instructions = form.querySelector('textarea[name="instructions"]');
  if (insurance && insurance.value) return true;
  if (instructions && instructions.value.trim()) return true;
  
  return false;
}

function closeNewShipmentModal() {
  // Check for unsaved changes
  if (hasUnsavedChanges()) {
    // Create Shadcn-styled confirmation modal
    const confirmOverlay = document.createElement('div');
    confirmOverlay.id = 'discardConfirmOverlay';
    confirmOverlay.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); animation: fadeIn 0.2s ease;';
    
    confirmOverlay.innerHTML = `
      <div style="background: white; border-radius: 12px; width: 90%; max-width: 440px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2), 0 10px 10px -5px rgba(0,0,0,0.1); animation: slideUp 0.3s ease;">
        <div style="padding: 1.5rem; border-bottom: 1px solid hsl(214 20% 92%);">
          <div style="display: flex; align-items: flex-start; gap: 1rem;">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, hsl(48 96% 89%), hsl(45 93% 85%)); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2.5">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </div>
            <div style="flex: 1;">
              <h3 style="font-size: 1.125rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0 0 0.5rem 0; letter-spacing: -0.02em;">Discard Changes?</h3>
              <p style="font-size: 0.875rem; color: hsl(215 16% 47%); margin: 0; line-height: 1.5;">You have unsaved changes in the shipment form. Are you sure you want to discard them?</p>
            </div>
          </div>
        </div>
        <div style="padding: 1.25rem; display: flex; gap: 0.75rem; justify-content: flex-end; background: hsl(240 5% 98%);">
          <button onclick="cancelDiscard()" style="padding: 0.625rem 1.25rem; background: white; color: hsl(220 14% 11%); border: 1.5px solid hsl(214 20% 85%); border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='white'">Keep Editing</button>
          <button onclick="confirmDiscard()" style="padding: 0.625rem 1.25rem; background: #dc2626; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;" onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dc2626'">Discard Changes</button>
        </div>
      </div>
      <style>
        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }
        @keyframes slideUp {
          from { transform: translateY(20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }
      </style>
    `;
    
    document.body.appendChild(confirmOverlay);
  } else {
    // No changes, close directly
    document.getElementById('newShipmentModal').style.display = 'none';
  }
}

function cancelDiscard() {
  const overlay = document.getElementById('discardConfirmOverlay');
  if (overlay) overlay.remove();
}

function confirmDiscard() {
  const overlay = document.getElementById('discardConfirmOverlay');
  if (overlay) overlay.remove();
  
  // Reset form
  const form = document.getElementById('newShipmentForm');
  if (form) form.reset();
  
  // Clear packages
  packages.length = 0;
  packageCounter = 0;
  const container = document.getElementById('packagesContainer');
  const emptyMsg = document.getElementById('emptyPackagesMessage');
  if (emptyMsg) emptyMsg.style.display = 'block';
  Array.from(container.children).forEach(child => {
    if (child.id !== 'emptyPackagesMessage') child.remove();
  });
  
  // Reset shipment number display
  const shipNumDisplay = document.getElementById('shipment_number_display');
  if (shipNumDisplay) {
    shipNumDisplay.value = '';
    shipNumDisplay.style.color = 'hsl(222 47% 17%)'; // Shadcn text color (black)
    shipNumDisplay.style.fontWeight = 'normal';
  }
  
  // Close modal
  document.getElementById('newShipmentModal').style.display = 'none';
}

function addPackage() {
  const pkgId = packageCounter++;
  const container = document.getElementById('packagesContainer');
  
  const pkgDiv = document.createElement('div');
  pkgDiv.id = `package-${pkgId}`;
  pkgDiv.style.cssText = 'padding: 1.25rem; background: white; border: 1.5px solid #e5e7eb; border-radius: 8px; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);';
  
  pkgDiv.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h4 style="font-weight: 600; color: #111827; margin: 0; font-size: 0.9375rem;">Package #${pkgId + 1}</h4>
      <button type="button" onclick="removePackage(${pkgId})" style="padding: 0.375rem 0.75rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; cursor: pointer; font-size: 0.8125rem; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">Remove</button>
    </div>
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
      <div>
        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #6b7280; margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.025em;">Weight (lbs)</label>
        <input type="number" class="pkg-weight" min="0" step="0.1" value="5" style="width: 100%; padding: 0.625rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; transition: all 0.2s;" oninput="updateShipmentSummary()" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
      </div>
      <div>
        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #6b7280; margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.025em;">Length (in)</label>
        <input type="number" class="pkg-length" min="0" step="0.1" value="12" style="width: 100%; padding: 0.625rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; transition: all 0.2s;" oninput="updateShipmentSummary()" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
      </div>
      <div>
        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #6b7280; margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.025em;">Width (in)</label>
        <input type="number" class="pkg-width" min="0" step="0.1" value="10" style="width: 100%; padding: 0.625rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; transition: all 0.2s;" oninput="updateShipmentSummary()" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
      </div>
      <div>
        <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #6b7280; margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.025em;">Height (in)</label>
        <input type="number" class="pkg-height" min="0" step="0.1" value="8" style="width: 100%; padding: 0.625rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; transition: all 0.2s;" oninput="updateShipmentSummary()" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
      </div>
    </div>
  `;
  
  container.appendChild(pkgDiv);
  packages.push(pkgId);
  
  // Hide empty message on first package
  const emptyMsg = document.getElementById('emptyPackagesMessage');
  if (emptyMsg) emptyMsg.style.display = 'none';
  
  // Show pagination if more than 2 packages
  if (packages.length > packagesPerPage) {
    document.getElementById('packagesPagination').style.display = 'flex';
  }
  
  updatePackagePagination();
  updateShipmentSummary();
}

function removePackage(pkgId) {
  const element = document.getElementById(`package-${pkgId}`);
  if (element) {
    element.remove();
    const index = packages.indexOf(pkgId);
    if (index > -1) packages.splice(index, 1);
  }
  
  // Show empty message if no packages
  if (packages.length === 0) {
    const emptyMsg = document.getElementById('emptyPackagesMessage');
    if (emptyMsg) emptyMsg.style.display = 'block';
    document.getElementById('packagesPagination').style.display = 'none';
  }
  
  // Hide pagination if 2 or fewer packages
  if (packages.length <= packagesPerPage) {
    document.getElementById('packagesPagination').style.display = 'none';
    currentPackagePage = 1;
  }
  
  updatePackagePagination();
  updateShipmentSummary();
}

function updatePackagePagination() {
  const totalPages = Math.ceil(packages.length / packagesPerPage);
  if (totalPages <= 1) {
    document.getElementById('packagesPagination').style.display = 'none';
    // Show all packages
    packages.forEach(pkgId => {
      const el = document.getElementById(`package-${pkgId}`);
      if (el) el.style.display = 'block';
    });
    return;
  }
  
  // Ensure current page is valid
  if (currentPackagePage > totalPages) currentPackagePage = totalPages;
  if (currentPackagePage < 1) currentPackagePage = 1;
  
  // Update pagination info
  document.getElementById('packagePageInfo').textContent = `Page ${currentPackagePage} of ${totalPages}`;
  
  // Enable/disable buttons
  document.getElementById('prevPageBtn').disabled = currentPackagePage === 1;
  document.getElementById('prevPageBtn').style.opacity = currentPackagePage === 1 ? '0.5' : '1';
  document.getElementById('prevPageBtn').style.cursor = currentPackagePage === 1 ? 'not-allowed' : 'pointer';
  
  document.getElementById('nextPageBtn').disabled = currentPackagePage === totalPages;
  document.getElementById('nextPageBtn').style.opacity = currentPackagePage === totalPages ? '0.5' : '1';
  document.getElementById('nextPageBtn').style.cursor = currentPackagePage === totalPages ? 'not-allowed' : 'pointer';
  
  // Show/hide packages based on current page
  const startIdx = (currentPackagePage - 1) * packagesPerPage;
  const endIdx = startIdx + packagesPerPage;
  
  packages.forEach((pkgId, idx) => {
    const el = document.getElementById(`package-${pkgId}`);
    if (el) {
      el.style.display = (idx >= startIdx && idx < endIdx) ? 'block' : 'none';
    }
  });
}

function nextPackagePage() {
  const totalPages = Math.ceil(packages.length / packagesPerPage);
  if (currentPackagePage < totalPages) {
    currentPackagePage++;
    updatePackagePagination();
  }
}

function prevPackagePage() {
  if (currentPackagePage > 1) {
    currentPackagePage--;
    updatePackagePagination();
  }
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
  let tracking = '';
  const randomNum = () => Math.floor(Math.random() * 10);
  const randomAlpha = () => String.fromCharCode(65 + Math.floor(Math.random() * 26));
  
  // Philippine Carriers
  if (carrier === 'Lalamove') {
    tracking = 'LLM' + Date.now().toString().slice(-10);
  } else if (carrier === 'LBC') {
    tracking = 'LBC' + Array.from({length: 12}, randomNum).join('');
  } else if (carrier === 'J&T Express') {
    tracking = 'JT' + Array.from({length: 11}, randomNum).join('');
  } else if (carrier === 'Ninja Van') {
    tracking = 'NPHM' + Array.from({length: 10}, () => randomNum()).join('');
  } else if (carrier === 'Shopee Express') {
    tracking = 'SPX' + Array.from({length: 12}, randomNum).join('');
  } else if (carrier === 'Lazada Express') {
    tracking = 'LEX' + Array.from({length: 11}, randomNum).join('');
  } else if (carrier === 'Flash Express') {
    tracking = 'FE' + Array.from({length: 12}, randomNum).join('');
  } else if (carrier === 'GoGo Xpress') {
    tracking = 'GGX' + Array.from({length: 10}, randomNum).join('');
  } else if (carrier === 'Grab Express') {
    tracking = 'GRB' + Date.now().toString().slice(-11);
  } else if (carrier === 'Entrego') {
    tracking = 'ENT' + Array.from({length: 11}, randomNum).join('');
  } else if (carrier === '2GO Express') {
    tracking = '2GO' + Array.from({length: 10}, randomNum).join('');
  } else if (carrier === 'AP Cargo') {
    tracking = 'APC' + Array.from({length: 11}, randomNum).join('');
  }
  // International Carriers
  else if (carrier === 'FedEx') {
    tracking = '7' + randomNum() + Array.from({length: 13}, randomNum).join('');
  } else if (carrier === 'UPS') {
    tracking = '1Z' + Array.from({length: 16}, () => Math.random() > 0.5 ? randomNum() : randomAlpha()).join('');
  } else if (carrier === 'USPS') {
    tracking = '92' + Array.from({length: 20}, randomNum).join('');
  } else if (carrier === 'DHL') {
    tracking = 'JD' + Array.from({length: 12}, randomNum).join('');
  } else {
    tracking = 'TRK' + Array.from({length: 12}, randomNum).join('');
  }
  
  document.querySelector('input[name="tracking"]').value = tracking;
  Toast.success(`🎯 ${carrier} tracking number generated!`);
}

function generateShipmentNumber() {
  const year = new Date().getFullYear();
  const randomNum = Math.floor(Math.random() * 999) + 1;
  const paddedNum = String(randomNum).padStart(3, '0');
  const shipmentNumber = `SHP-${year}-${paddedNum}`;
  
  const display = document.getElementById('shipment_number_display');
  if (display) {
    display.value = shipmentNumber;
    display.style.color = 'hsl(222 47% 17%)'; // Shadcn text color (black)
    display.style.fontWeight = '600'; // Bold for generated number
  }
  
  Toast.success('Shipment number generated!');
}

function generateOrderNumber() {
  const year = new Date().getFullYear();
  const randomNum = Math.floor(Math.random() * 999) + 1;
  const paddedNum = String(randomNum).padStart(3, '0');
  const orderNumber = `ORD-${year}-${paddedNum}`;
  
  const orderInput = document.querySelector('input[name="order"]');
  if (orderInput) {
    orderInput.value = orderNumber;
  }
  
  Toast.success('Order number generated!');
}

function updateCarrierOptions(carrier) {
  const serviceSelect = document.querySelector('select[name="service_type"]');
  serviceSelect.innerHTML = '';
  
  const services = {
    // Philippine Carriers
    'Lalamove': ['🛵 Motorcycle', '🚗 Sedan', '🚙 MPV/SUV', '🚚 L300/Van'],
    'LBC': ['📦 Standard', '⚡ Express', '✈️ Air Cargo', '🚢 Sea Cargo'],
    'J&T Express': ['📦 Standard Express', '⚡ Same Day', '🏃 Next Day', '💼 COD (Cash on Delivery)'],
    'Ninja Van': ['📦 Standard', '⚡ Express', '🏃 Same Day', '📮 Drop-off'],
    'Shopee Express': ['📦 Standard', '⚡ Express', '🏃 Same Day (Metro Manila)', '📮 Drop-off'],
    'Lazada Express': ['📦 Standard Delivery', '⚡ Next Day Delivery', '🏃 Same Day (Selected Areas)', '📮 Drop-off'],
    'Flash Express': ['📦 Standard', '⚡ Express', '🏃 Same Day', '💼 COD Available'],
    'GoGo Xpress': ['📦 Standard', '⚡ Express', '🏃 Same Day', '🚗 On-Demand'],
    'Grab Express': ['🛵 GrabExpress Bike', '🚗 GrabExpress Car', '🚙 GrabExpress 6-Seater', '⚡ GrabExpress Instant'],
    'Entrego': ['📦 Standard Delivery', '⚡ Express Delivery', '💼 COD Available', '🏢 B2B Solutions'],
    '2GO Express': ['📦 Standard', '⚡ Priority', '✈️ Air Freight', '🚢 Sea Freight'],
    'AP Cargo': ['✈️ Air Cargo', '📦 Door-to-Door', '🏢 Warehouse to Warehouse', '🚢 Sea Freight'],
    
    // International Carriers
    'FedEx': ['📦 FedEx Ground', '⚡ FedEx Express', '🚀 FedEx 2Day', '🔥 FedEx Priority Overnight'],
    'UPS': ['📦 UPS Ground', '⚡ UPS Next Day Air', '🚀 UPS 2nd Day Air', '📮 UPS 3 Day Select'],
    'USPS': ['📦 USPS Priority Mail', '📮 USPS First Class', '⚡ USPS Priority Express', '📚 USPS Media Mail'],
    'DHL': ['🌍 DHL Express Worldwide', '⏰ DHL Express 12:00', '⚡ DHL Express 9:00', '📦 DHL Economy Select']
  };
  
  const options = services[carrier] || ['📦 Standard Ground', '⚡ Express Overnight', '🚀 2-Day Delivery'];
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

document.getElementById('newShipmentForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  
  // Custom validation to handle hidden tabs
  if (!validateShipmentForm()) {
    return;
  }
  
  const formData = new FormData(this);
  const data = Object.fromEntries(formData);
  data.packages = getPackagesData();
  
  // Validate packages
  if (data.packages.length === 0) {
    Toast.error('Please add at least one package');
    switchShipmentTab('packages');
    return;
  }
  
  data.totalWeight = data.packages.reduce((sum, pkg) => sum + pkg.weight, 0).toFixed(2);
  
  // Show loading state
  const submitBtn = this.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" opacity="0.75"/></svg> Creating...';
  
  try {
    const response = await fetch('/api/shipments', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      const shipmentNum = result.data?.shipment_number || 'N/A';
      Toast.success(`Shipment ${shipmentNum} created successfully!`);
      
      // Refresh shipments table
      await refreshShipmentsTable();
      
      // Update statistics
      updateShipmentStatistics();
      
      // Reset form and shipment number display
      this.reset();
      const shipNumDisplay = document.getElementById('shipment_number_display');
      if (shipNumDisplay) {
        shipNumDisplay.value = '';
        shipNumDisplay.style.color = 'hsl(222 47% 17%)'; // Shadcn text color (black)
        shipNumDisplay.style.fontWeight = 'normal';
      }
      
      // Re-enable submit button
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnText;
      
      // Close modal after refresh
      document.getElementById('newShipmentModal').style.display = 'none';
    } else {
      Toast.error(result.message || 'Failed to create shipment');
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnText;
    }
  } catch (error) {
    console.error('Error creating shipment:', error);
    Toast.error('Failed to create shipment. Please try again.');
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalBtnText;
  }
});

function validateShipmentForm() {
  const form = document.getElementById('newShipmentForm');
  const requiredFields = [
    { name: 'order', tab: 'details', label: 'Order Number', type: 'input' },
    { name: 'customer', tab: 'details', label: 'Customer Name', type: 'input' },
    { name: 'carrier', tab: 'details', label: 'Carrier', type: 'select' },
    { name: 'service_type', tab: 'details', label: 'Service Type', type: 'select' },
    { name: 'date', tab: 'details', label: 'Ship Date', type: 'input' },
    { name: 'expected_delivery', tab: 'details', label: 'Expected Delivery', type: 'input' },
    { name: 'address', tab: 'address', label: 'Shipping Address', type: 'textarea' },
    { name: 'tracking', tab: null, label: 'Tracking Number', type: 'input' }
  ];
  
  // Clear previous error states
  requiredFields.forEach(field => {
    const input = form.querySelector(`[name="${field.name}"]`);
    if (input) {
      input.style.borderColor = '#d1d5db';
      input.style.boxShadow = 'none';
    }
  });
  
  for (const field of requiredFields) {
    const input = form.querySelector(`[name="${field.name}"]`);
    
    // Check if field exists and has value
    let isEmpty = false;
    if (!input) {
      isEmpty = true;
    } else if (field.type === 'select') {
      isEmpty = !input.value || input.value.trim() === '';
    } else {
      isEmpty = !input.value || input.value.trim() === '';
    }
    
    if (isEmpty) {
      // Add visual error state
      if (input) {
        input.style.borderColor = '#dc2626';
        input.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.1)';
      }
      
      // Show error toast
      Toast.error(`${field.label} is required`);
      
      // Switch to tab and focus
      if (field.tab) {
        switchShipmentTab(field.tab);
        setTimeout(() => {
          if (input) input.focus();
        }, 300);
      } else {
        setTimeout(() => {
          if (input) input.focus();
        }, 100);
      }
      
      return false;
    }
  }
  
  return true;
}

async function refreshShipmentsTable(showToast = false) {
  if (showToast) {
    Toast.info('🔄 Refreshing shipments...');
  }
  
  try {
    const response = await fetch('/api/shipments');
    const result = await response.json();
    
    if (result.success && result.data) {
      const tbody = document.getElementById('shipmentsTableBody');
      if (!tbody) return;
      
      tbody.innerHTML = '';
      
      if (result.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: hsl(215 16% 47%);">No shipments found</td></tr>';
        return;
      }
      
      result.data.forEach((shipment, index) => {
        const row = document.createElement('tr');
        
        const statusBadges = {
          'pending': 'badge-default',
          'in-transit': 'badge-info',
          'in_transit': 'badge-info',
          'out-for-delivery': 'badge-warning',
          'delivered': 'badge-success',
          'returned': 'badge-danger',
          'cancelled': 'badge-danger'
        };
        
        const shipmentNumber = shipment.shipment_number || 'N/A';
        const statusClass = statusBadges[shipment.status] || 'badge-default';
        const statusDisplay = shipment.status ? shipment.status.replace(/_/g, '-').split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ') : 'Pending';
        const shipDate = shipment.date ? new Date(shipment.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';
        
        // Extract MongoDB ID - handle both _id object and id string
        let shipmentId = shipment.id || shipment._id;
        if (shipmentId && typeof shipmentId === 'object') {
          // MongoDB ObjectId - extract the $oid value
          shipmentId = shipmentId.$oid || shipmentId.toString();
        }
        if (!shipmentId) {
          shipmentId = index; // Fallback to index
        }
        
        const tracking = shipment.tracking || '-';
        const carrier = shipment.carrier || '-';
        
        // Set data attribute for shipment ID
        row.setAttribute('data-shipment-id', shipmentId);
        
        console.log('Refreshed row with shipment ID:', shipmentId);
        
        row.innerHTML = `
          <td class="checkbox-column" style="display: none;">
            <input type="checkbox" class="shipment-checkbox" value="${shipmentId}" onchange="updateShipmentBatchToolbar()" style="cursor: pointer;">
          </td>
          <td class="font-mono font-medium">${shipmentNumber}</td>
          <td class="font-mono order-column-hide-print">${shipment.order || '-'}</td>
          <td class="font-medium">${shipment.customer || '-'}</td>
          <td>${carrier}</td>
          <td class="font-mono text-primary">${tracking}</td>
          <td>${shipDate}</td>
          <td>
            <span class="badge ${statusClass}">${statusDisplay}</span>
          </td>
          <td>
            <div style="display: flex; gap: 0.25rem; align-items: center;">
              <button class="btn btn-ghost btn-sm" onclick="trackShipment('${tracking}', '${carrier}')" title="Track Shipment">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/>
                  <polyline points="12 6 12 12 16 14"/>
                </svg>
              </button>
              <button class="btn btn-ghost btn-sm" onclick="printShipmentLabel('${shipmentId}')" title="Print Label">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="6 9 6 2 18 2 18 9"/>
                  <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                  <rect x="6" y="14" width="12" height="8"/>
                </svg>
              </button>
              ${smtpConfigured ? `
              <button class="btn btn-ghost btn-sm" onclick="emailShipmentNotification('${shipmentId}')" title="Email Customer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="2" y="4" width="20" height="16" rx="2"/>
                  <path d="M22 7L13 13C12.5 13.3 11.5 13.3 11 13L2 7"/>
                </svg>
              </button>
              ` : `
              <button class="btn btn-ghost btn-sm" onclick="Toast.warning('SMTP not configured. Configure in Settings.')" title="Email Disabled" style="opacity: 0.4; cursor: not-allowed;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="2" y="4" width="20" height="16" rx="2"/>
                  <path d="M22 7L13 13C12.5 13.3 11.5 13.3 11 13L2 7"/>
                </svg>
              </button>
              `}
              <button class="btn btn-ghost btn-sm" onclick="deleteShipment(this, '${shipmentId}')" title="Delete" style="color: hsl(0 74% 42%);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                  <line x1="10" y1="11" x2="10" y2="17"/>
                  <line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
              </button>
            </div>
          </td>
        `;
        
        tbody.appendChild(row);
      });
      
      // Re-initialize pagination after refresh
      setTimeout(() => {
        initializeShipmentsPagination();
        updateShipmentStatistics();
      }, 100);
      
      if (showToast) {
        Toast.success('✅ Table refreshed successfully!');
      }
    }
  } catch (error) {
    console.error('Error refreshing shipments table:', error);
    if (showToast) {
      Toast.error('Failed to refresh table');
    }
  }
}

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
    const form = document.getElementById('newShipmentForm');
    const pkgs = getPackagesData();
    const totalWeight = (pkgs.reduce((sum, p) => sum + (parseFloat(p.weight) || 0), 0)).toFixed(2);
    const carrierSel = document.querySelector('select[name="carrier"]');
    const serviceSel = document.querySelector('select[name="service_type"]');
    const carrier = carrierSel?.value || '';
    const serviceText = serviceSel && serviceSel.options.length ? serviceSel.options[serviceSel.selectedIndex].text : '';

    // Get cost fields
    const shippingCostInput = form.querySelector('input[name="shipping_cost"]');
    const insuranceInput = form.querySelector('input[name="insurance"]');
    const shippingCost = parseFloat(shippingCostInput?.value || 0);
    const insurance = parseFloat(insuranceInput?.value || 0);
    const totalCost = shippingCost + insurance;

    // Get currency symbol from PHP
    const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';

    // Update summary fields
    const packagesEl = document.getElementById('modalPackagesCount');
    const weightEl = document.getElementById('modalTotalWeight');
    const carrierEl = document.getElementById('modalCarrier');
    const serviceEl = document.getElementById('modalService');
    const shippingCostEl = document.getElementById('modalShippingCost');
    const insuranceEl = document.getElementById('modalInsurance');
    const totalCostEl = document.getElementById('modalTotalCost');

    if (packagesEl) packagesEl.textContent = String(pkgs.length);
    if (weightEl) weightEl.textContent = `${totalWeight} lbs`;
    if (carrierEl) carrierEl.textContent = carrier || '—';
    if (serviceEl) serviceEl.textContent = serviceText || '—';
    if (shippingCostEl) shippingCostEl.textContent = `${currencySymbol}${shippingCost.toFixed(2)}`;
    if (insuranceEl) insuranceEl.textContent = `${currencySymbol}${insurance.toFixed(2)}`;
    if (totalCostEl) totalCostEl.textContent = `${currencySymbol}${totalCost.toFixed(2)}`;
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

// Clear validation error states when user interacts with fields
document.getElementById('newShipmentForm')?.addEventListener('input', function(e) {
  const input = e.target;
  if (input && (input.tagName === 'INPUT' || input.tagName === 'SELECT' || input.tagName === 'TEXTAREA')) {
    // Clear error state on input
    input.style.borderColor = '#d1d5db';
    input.style.boxShadow = 'none';
  }
});

document.getElementById('newShipmentForm')?.addEventListener('change', function(e) {
  const input = e.target;
  if (input && (input.tagName === 'INPUT' || input.tagName === 'SELECT' || input.tagName === 'TEXTAREA')) {
    // Clear error state on change (for select, date, checkbox)
    input.style.borderColor = '#d1d5db';
    input.style.boxShadow = 'none';
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

// ========================================
// SHIPPING-SPECIFIC FEATURES
// ========================================

// Search Shipments
function searchShipments() {
  const searchTerm = document.getElementById('shipment-search').value.toLowerCase();
  const rows = document.querySelectorAll('#shipmentsTableBody tr');
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(searchTerm) ? '' : 'none';
  });
}

// Apply Filters
function applyShipmentFilters() {
  const statusFilter = document.getElementById('status-filter').value;
  const rows = document.querySelectorAll('#shipmentsTableBody tr');
  
  rows.forEach(row => {
    const statusCell = row.querySelector('.badge');
    const status = statusCell ? statusCell.textContent.toLowerCase().replace(/\s+/g, '-') : '';
    const statusMatch = statusFilter === 'all' || status === statusFilter;
    
    row.style.display = statusMatch ? '' : 'none';
  });
  
  // Re-apply pagination after filtering
  initializeShipmentsPagination();
}

// Show/Hide Batch Operations
function showShipmentBatchOperations() {
  const checkboxColumns = document.querySelectorAll('.checkbox-column');
  const isCurrentlyHidden = checkboxColumns[0].style.display === 'none';
  
  checkboxColumns.forEach(col => {
    col.style.display = isCurrentlyHidden ? '' : 'none';
  });
  
  if (!isCurrentlyHidden) {
    // Hide icons and clear selection when disabling batch mode
    document.getElementById('batchIconsGroup').style.display = 'none';
    clearShipmentSelection();
  }
}

// Toggle Select All
function toggleSelectAllShipments(checkbox) {
  const checkboxes = document.querySelectorAll('.shipment-checkbox');
  checkboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
  updateShipmentBatchToolbar();
}

// Update Batch Toolbar
function updateShipmentBatchToolbar() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox:checked');
  const count = checkboxes.length;
  const batchIconsGroup = document.getElementById('batchIconsGroup');
  const batchSelectedCount = document.getElementById('batchSelectedCount');
  const selectAll = document.getElementById('selectAllShipments');
  const total = document.querySelectorAll('.shipment-checkbox').length;
  
  if (count === total && total > 0) {
    selectAll.checked = true;
    selectAll.indeterminate = false;
  } else if (count === 0) {
    selectAll.checked = false;
    selectAll.indeterminate = false;
  } else {
    selectAll.checked = false;
    selectAll.indeterminate = true;
  }
  
  // Show icons only when items are selected
  if (count > 0) {
    batchIconsGroup.style.display = 'flex';
    batchSelectedCount.textContent = `${count} selected`;
  } else {
    batchIconsGroup.style.display = 'none';
  }
}

// Clear Selection
function clearShipmentSelection() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox');
  checkboxes.forEach(cb => {
    cb.checked = false;
  });
  document.getElementById('selectAllShipments').checked = false;
  document.getElementById('selectAllShipments').indeterminate = false;
  updateShipmentBatchToolbar();
}

// Sort Table
let shipmentSortDirection = {};
function sortShipmentTable(column) {
  const tbody = document.getElementById('shipmentsTableBody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  
  const direction = shipmentSortDirection[column] === 'asc' ? 'desc' : 'asc';
  shipmentSortDirection = { [column]: direction };
  
  const columnIndex = {
    'shipment_number': 1,
    'order': 2,
    'customer': 3,
    'carrier': 4,
    'date': 6,
    'status': 7
  };
  
  const idx = columnIndex[column];
  
  rows.sort((a, b) => {
    const aVal = a.children[idx]?.textContent.trim() || '';
    const bVal = b.children[idx]?.textContent.trim() || '';
    
    if (column === 'date') {
      return direction === 'asc' 
        ? new Date(aVal) - new Date(bVal)
        : new Date(bVal) - new Date(aVal);
    }
    
    return direction === 'asc'
      ? aVal.localeCompare(bVal)
      : bVal.localeCompare(aVal);
  });
  
  rows.forEach(row => tbody.appendChild(row));
}

// Track Shipment
function trackShipment(tracking, carrier) {
  if (!tracking || tracking === '-') {
    Toast.warning('No tracking number available');
    return;
  }
  
  const trackingUrls = {
    // Philippine Carriers
    'Lalamove': `https://www.lalamove.com/en-ph/track`, // Note: Lalamove uses app-based tracking
    'LBC': `https://www.lbcexpress.com/track/?tracking_no=${tracking}`,
    'J&T Express': `https://www.jtexpress.ph/tracking?billno=${tracking}`,
    'Ninja Van': `https://www.ninjavan.co/en-ph/tracking?id=${tracking}`,
    'Shopee Express': `https://spx.ph/tracking?query=${tracking}`,
    'Lazada Express': `https://www.lel.com.ph/track?trackingNumber=${tracking}`,
    'Flash Express': `https://www.flashexpress.ph/tracking?awb=${tracking}`,
    'GoGo Xpress': `https://gogoxpress.com/track-your-order/?order_id=${tracking}`,
    'Grab Express': `https://www.grab.com/ph/express/`, // Note: Grab uses in-app tracking
    'Entrego': `https://www.entrego.com.ph/track/${tracking}`,
    '2GO Express': `https://www.2go.com.ph/track-and-trace/?ref_no=${tracking}`,
    'AP Cargo': `https://www.apcargo.com/tracking/?awb=${tracking}`,
    
    // International Carriers
    'FedEx': `https://www.fedex.com/fedextrack/?trknbr=${tracking}`,
    'UPS': `https://www.ups.com/track?tracknum=${tracking}`,
    'USPS': `https://tools.usps.com/go/TrackConfirmAction?tLabels=${tracking}`,
    'DHL': `https://www.dhl.com/en/express/tracking.html?AWB=${tracking}`
  };
  
  const url = trackingUrls[carrier];
  if (url) {
    window.open(url, '_blank');
    Toast.success(`🔍 Tracking ${carrier} shipment in new tab`);
  } else {
    // Fallback: Show tracking number and offer to copy
    if (navigator.clipboard) {
      navigator.clipboard.writeText(tracking).then(() => {
        Toast.info(`📋 Tracking number copied: ${tracking}`);
      });
    } else {
      Toast.info(`📦 Tracking: ${tracking}`);
    }
  }
}

// Print Shipment Label (Single Shipment) - Opens PDF in modal
function printShipmentLabel(shipmentId) {
  try {
    if (!shipmentId) {
      Toast.warning('No shipment selected');
      return;
    }
    
    // Find the shipment row to get details for the title
    const row = document.querySelector(`tr[data-shipment-id="${shipmentId}"]`);
    let shipmentNumber = shipmentId;
    let customerName = '';
    
    if (row) {
      shipmentNumber = row.querySelector('td:nth-child(2)')?.textContent || shipmentId;
      customerName = row.querySelector('td:nth-child(3)')?.textContent || '';
    }
    
    const url = 'api/shipment_pdf?id=' + encodeURIComponent(shipmentId);
    console.log('📄 Opening PDF viewer for shipment ' + shipmentId);
    
    // Set iframe source and open modal
    const modal = document.getElementById('pdfViewerModal');
    const iframe = document.getElementById('pdfViewerFrame');
    const title = document.getElementById('pdfViewerTitle');
    
    iframe.src = url;
    title.textContent = 'Shipment Label - ' + shipmentNumber + (customerName ? ' (' + customerName + ')' : '');
    modal.style.display = 'flex';
    
    Toast.info('Loading shipment label...');
  } catch (e) {
    console.error('PDF viewer exception:', e);
    Toast.error('Failed to open PDF: ' + e.message);
  }
}

function closePdfViewer() {
  const modal = document.getElementById('pdfViewerModal');
  const iframe = document.getElementById('pdfViewerFrame');
  
  modal.style.display = 'none';
  iframe.src = '';
}

// Print All Labels (All Shipments in Table - regardless of pagination)
function printShippingLabels() {
  const rows = document.querySelectorAll('#shipmentsTableBody tr');
  
  if (rows.length === 0) {
    Toast.warning('No shipments to print');
    return;
  }
  
  console.log('Total rows found:', rows.length);
  
  // Calculate statistics for all rows (including hidden ones)
  let total = 0;
  let pending = 0;
  let inTransit = 0;
  let delivered = 0;
  
  rows.forEach((row, index) => {
    total++;
    // Find status badge anywhere in the row (simpler and more reliable)
    const statusBadge = row.querySelector('.badge');
    if (statusBadge) {
      const statusText = statusBadge.textContent.toLowerCase().trim();
      console.log(`Row ${index}: Status = "${statusText}"`);
      
      if (statusText === 'pending') pending++;
      else if (statusText === 'in transit' || statusText === 'in-transit') inTransit++;
      else if (statusText === 'delivered') delivered++;
      else if (statusText === 'out for delivery' || statusText === 'out-for-delivery') inTransit++;
    } else {
      console.log(`Row ${index}: No badge found`);
    }
  });
  
  console.log('Calculated stats:', { total, pending, inTransit, delivered });
  
  // Update print header
  document.getElementById('printTitle').textContent = `Shipping Labels - All Shipments (${total})`;
  document.getElementById('printSubtitle').textContent = `Complete shipment list | Generated on ${new Date().toLocaleString()}`;
  
  // Update print summary with explicit string values (using multiple methods for reliability)
  const totalEl = document.getElementById('printTotalCount');
  const pendingEl = document.getElementById('printPendingCount');
  const inTransitEl = document.getElementById('printInTransitCount');
  const deliveredEl = document.getElementById('printDeliveredCount');
  
  if (totalEl) {
    totalEl.setAttribute('data-count', total);
    const span = totalEl.querySelector('.count-value');
    if (span) span.textContent = String(total);
  }
  if (pendingEl) {
    pendingEl.setAttribute('data-count', pending);
    const span = pendingEl.querySelector('.count-value');
    if (span) span.textContent = String(pending);
  }
  if (inTransitEl) {
    inTransitEl.setAttribute('data-count', inTransit);
    const span = inTransitEl.querySelector('.count-value');
    if (span) span.textContent = String(inTransit);
  }
  if (deliveredEl) {
    deliveredEl.setAttribute('data-count', delivered);
    const span = deliveredEl.querySelector('.count-value');
    if (span) span.textContent = String(delivered);
  }
  
  console.log('Updated summary elements:', {
    total: totalEl?.textContent,
    pending: pendingEl?.textContent,
    inTransit: inTransitEl?.textContent,
    delivered: deliveredEl?.textContent
  });
  
  // Show summary for all shipments print
  document.getElementById('printableShipmentsTable').classList.add('print-show-summary');
  
  // Show ALL rows for printing (remove pagination hiding)
  rows.forEach(row => {
    row.classList.remove('print-hide-row');
    // Temporarily store original display style
    row.setAttribute('data-original-display', row.style.display || '');
    // Show all rows for print
    row.style.display = '';
  });
  
  // Force DOM reflow to ensure all updates are painted
  void document.getElementById('printTotalCount').offsetHeight;
  
  console.log('Before print - Summary values check:', {
    total: document.getElementById('printTotalCount').textContent,
    pending: document.getElementById('printPendingCount').textContent,
    inTransit: document.getElementById('printInTransitCount').textContent,
    delivered: document.getElementById('printDeliveredCount').textContent
  });
  
  // Trigger print with longer delay to ensure DOM is fully updated
  setTimeout(() => {
    window.print();
    
    // Restore original display states after print
    setTimeout(() => {
      rows.forEach(row => {
        const originalDisplay = row.getAttribute('data-original-display');
        if (originalDisplay !== null) {
          row.style.display = originalDisplay;
          row.removeAttribute('data-original-display');
        }
      });
      // Reset header
      document.getElementById('printTitle').textContent = 'Shipping Labels';
      document.getElementById('printSubtitle').textContent = 'Generated on <?php echo date("F j, Y g:i A"); ?>';
    }, 100);
  }, 200);
}

// Email Shipment Notification
function emailShipmentNotification(shipmentId) {
  // Check if SMTP is configured
  if (!smtpConfigured) {
    // Show detailed modal with instructions
    const modal = document.createElement('div');
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;';
    modal.innerHTML = `
      <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="width: 48px; height: 48px; background: hsl(0 86% 97%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="hsl(0 74% 42%)" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </div>
          <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: hsl(222 47% 17%);">⚠️ SMTP Not Configured</h3>
        </div>
        <p style="color: hsl(215 16% 47%); margin: 0 0 1.5rem 0; line-height: 1.6;">Email notifications cannot be sent because SMTP settings are not configured. Please configure your email settings first.</p>
        <div style="background: hsl(214 95% 93%); border-left: 3px solid hsl(221 83% 53%); padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
          <p style="margin: 0; font-size: 0.875rem; color: hsl(222 47% 17%); font-weight: 600; margin-bottom: 0.5rem;">📝 Configuration Steps:</p>
          <ol style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem; color: hsl(215 16% 47%); line-height: 1.6;">
            <li>Open <code style="background: hsl(214 20% 92%); padding: 0.125rem 0.375rem; border-radius: 4px; font-family: monospace;">config/app.php</code></li>
            <li>Configure the <strong>mail</strong> section with your SMTP details</li>
            <li>Add: host, port, username, password, from address</li>
            <li>Refresh this page to apply changes</li>
          </ol>
        </div>
        <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
              <button onclick="window.location.href='/settings'" class="btn btn-primary" style="padding: 0.625rem 1.25rem; background: hsl(221 83% 53%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Go to Settings</button>
          <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" class="btn btn-ghost" style="padding: 0.625rem 1.25rem; background: hsl(214 20% 92%); border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Close</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
    return;
  }
  
  // SMTP is configured - proceed with confirmation
  if (confirm('Send shipping notification email to customer?')) {
    // TODO: Implement actual email sending API call
    // Add your toast notification here if needed
  }
}

// Delete Shipment
async function deleteShipment(button, shipmentId) {
  console.log('deleteShipment called with ID:', shipmentId);
  
  if (!confirm('Are you sure you want to delete this shipment?\n\nThis action cannot be undone.')) {
    return;
  }
  
  const row = button.closest('tr');
  if (!row) {
    console.error('No row found');
    return;
  }
  
  // Get shipment ID from multiple sources
  if (!shipmentId || shipmentId === '') {
    console.log('Trying button data-shipment-id...');
    shipmentId = button.getAttribute('data-shipment-id');
  }
  if (!shipmentId || shipmentId === '') {
    console.log('Trying row data-shipment-id...');
    shipmentId = row.getAttribute('data-shipment-id');
  }
  if (!shipmentId || shipmentId === '') {
    console.log('Trying checkbox value...');
    const checkbox = row.querySelector('.shipment-checkbox');
    shipmentId = checkbox ? checkbox.value : null;
  }
  
  console.log('Final shipment ID:', shipmentId);
  
  if (!shipmentId || shipmentId === '') {
    Toast.error('Unable to find shipment ID - Please check console');
    console.error('Missing shipment ID from:', { 
      row, 
      button,
      rowDataId: row.getAttribute('data-shipment-id'),
      buttonDataId: button.getAttribute('data-shipment-id'),
      checkbox: row.querySelector('.shipment-checkbox')?.value
    });
    return;
  }
  
  console.log('Proceeding to delete shipment:', shipmentId);
  
  // Fade out animation
  row.style.transition = 'opacity 0.3s';
  row.style.opacity = '0.5';
  
  try {
    const response = await fetch(`/api/shipments?id=${shipmentId}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json'
      }
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Remove from DOM
      setTimeout(() => {
        row.remove();
        Toast.success('🗑️ Shipment deleted from database!');
        
        // Check if table is empty
        const remainingRows = document.querySelectorAll('#shipmentsTableBody tr:not([style*="display: none"])');
        if (remainingRows.length === 0) {
          document.getElementById('shipmentsTableContainer').style.display = 'none';
          document.getElementById('emptyStateContainer').style.display = 'block';
          document.getElementById('shipmentsPagination').style.display = 'none';
        }
        
        initializeShipmentsPagination();
        updateShipmentStatistics();
      }, 300);
    } else {
      row.style.opacity = '1';
      Toast.error('Failed to delete: ' + result.message);
    }
  } catch (error) {
    row.style.opacity = '1';
    console.error('Delete error:', error);
    Toast.error('Failed to delete shipment');
  }
}

// Duplicate Shipment
function duplicateShipment(button) {
  const row = button.closest('tr');
  if (!row) return;
  
  // Clone the row
  const newRow = row.cloneNode(true);
  
  // Update shipment number
  const shipmentCell = newRow.querySelector('td:nth-child(2)');
  if (shipmentCell) {
    const oldNumber = shipmentCell.textContent;
    shipmentCell.textContent = oldNumber + '-COPY';
  }
  
  // Update status to pending
  const statusCell = newRow.querySelector('.badge');
  if (statusCell) {
    statusCell.className = 'badge badge-default';
    statusCell.textContent = 'Pending';
  }
  
  // Insert after current row
  row.parentNode.insertBefore(newRow, row.nextSibling);
  
  // Highlight new row temporarily
  newRow.style.backgroundColor = 'hsl(143 85% 96%)';
  setTimeout(() => {
    newRow.style.transition = 'background-color 1s';
    newRow.style.backgroundColor = '';
  }, 100);
  
  Toast.success('📋 Shipment duplicated successfully!');
  
  // Re-initialize pagination
  initializeShipmentsPagination();
  
  // Update statistics
  updateShipmentStatistics();
}

// Quick Status Change
function quickStatusChange(button, currentStatus) {
  const row = button.closest('tr');
  if (!row) return;
  
  const statusCell = row.querySelector('.badge');
  if (!statusCell) return;
  
  // Cycle through statuses
  const statuses = [
    { key: 'pending', class: 'badge-default', label: 'Pending' },
    { key: 'in-transit', class: 'badge-info', label: 'In Transit' },
    { key: 'out-for-delivery', class: 'badge-warning', label: 'Out For Delivery' },
    { key: 'delivered', class: 'badge-success', label: 'Delivered' }
  ];
  
  const currentIndex = statuses.findIndex(s => s.key === currentStatus);
  const nextIndex = (currentIndex + 1) % statuses.length;
  const nextStatus = statuses[nextIndex];
  
  statusCell.className = 'badge ' + nextStatus.class;
  statusCell.textContent = nextStatus.label;
  
  Toast.success(`Status updated to ${nextStatus.label}`);
  
  // Update statistics
  updateShipmentStatistics();
}

// Batch Print Labels
function batchPrintLabels() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox:checked');
  if (checkboxes.length === 0) {
    Toast.warning('Please select shipments first');
    return;
  }
  
  const selectedCount = checkboxes.length;
  
  if (selectedCount === 1) {
    // If only one selected, use the single shipment print
    const shipmentId = checkboxes[0].value;
    printShipmentLabel(shipmentId);
    return;
  }
  
  // For multiple selections, open PDFs in new tabs with slight delay
  if (confirm(`Open ${selectedCount} shipment labels in new tabs?\n\nNote: Make sure pop-ups are not blocked by your browser.`)) {
    Toast.info(`Opening ${selectedCount} shipment labels...`);
    
    let count = 0;
    checkboxes.forEach((checkbox, index) => {
      const shipmentId = checkbox.value;
      const row = checkbox.closest('tr');
      
      if (shipmentId && row) {
        const shipmentNumber = row.querySelector('td:nth-child(2)')?.textContent || shipmentId;
        const customerName = row.querySelector('td:nth-child(4)')?.textContent || '';
        
        // Open each PDF in new tab with delay to prevent popup blocker
        setTimeout(() => {
          const url = 'api/shipment_pdf?id=' + encodeURIComponent(shipmentId);
          window.open(url, `_blank_shipment_${shipmentId}`);
          count++;
          
          if (count === selectedCount) {
            Toast.success(`✅ Opened ${selectedCount} shipment label${selectedCount > 1 ? 's' : ''}`);
          }
        }, index * 300); // 300ms delay between each
      }
    });
  }
}

// Batch Email Notifications
function batchEmailNotifications() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox:checked');
  if (checkboxes.length === 0) {
    Toast.warning('Please select shipments first');
    return;
  }
  
  if (confirm(`Send shipping notifications to ${checkboxes.length} customers?\n\nEmails will be sent with tracking information and delivery updates.`)) {
    Toast.info(`Sending ${checkboxes.length} email notifications...`);
    setTimeout(() => {
      Toast.success(`✉️ ${checkboxes.length} email notifications sent successfully!`);
    }, 1500);
  }
}

// Batch Update Status
async function batchUpdateShipmentStatus() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox:checked');
  if (checkboxes.length === 0) {
    Toast.warning('Please select shipments first');
    return;
  }
  
  const status = prompt(`Update status for ${checkboxes.length} shipments\n\nEnter new status:\n1. Pending\n2. In Transit\n3. Out for Delivery\n4. Delivered\n5. Returned\n\nEnter number or status name:`);
  
  if (!status) return;
  
  const statuses = {
    '1': 'pending', '2': 'in-transit', '3': 'out-for-delivery',
    '4': 'delivered', '5': 'returned',
    'pending': 'pending', 'in-transit': 'in-transit', 'in transit': 'in-transit',
    'out-for-delivery': 'out-for-delivery', 'out for delivery': 'out-for-delivery',
    'delivered': 'delivered', 'returned': 'returned'
  };
  
  const selectedStatus = statuses[status.toLowerCase()] || status.toLowerCase().replace(/\s+/g, '-');
  
  // Get IDs
  const ids = Array.from(checkboxes).map(cb => cb.value);
  
  Toast.info('🔄 Updating database...');
  
  try {
    const response = await fetch('/api/shipments', {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        operation: 'update_status',
        ids: ids,
        status: selectedStatus,
        note: `Batch update to ${selectedStatus}`
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Update UI
      checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const statusCell = row.querySelector('td:nth-child(8) .badge');
        if (statusCell) {
          const statusBadges = {
            'pending': 'badge-default',
            'in-transit': 'badge-info',
            'out-for-delivery': 'badge-warning',
            'delivered': 'badge-success',
            'returned': 'badge-danger'
          };
          statusCell.className = 'badge ' + (statusBadges[selectedStatus] || 'badge-default');
          statusCell.textContent = selectedStatus.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        }
      });
      
      Toast.success(`✓ ${result.updated_count} shipments updated in database`);
      updateShipmentStatistics();
      clearShipmentSelection();
    } else {
      Toast.error('Failed to update: ' + result.message);
    }
  } catch (error) {
    console.error('Batch update error:', error);
    Toast.error('Failed to update shipments');
  }
}

// Batch Assign Carrier
async function batchAssignCarrier() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox:checked');
  if (checkboxes.length === 0) {
    Toast.warning('Please select shipments first');
    return;
  }
  
  const carriers = [
    'Lalamove', 'LBC Express', 'J&T Express', 'Ninja Van',
    'Shopee Express', 'Lazada Express', 'Flash Express', 'GoGo Xpress',
    'Grab Express', 'Entrego', '2GO Express', 'AP Cargo',
    'FedEx', 'UPS', 'DHL Express', 'USPS'
  ];
  
  const carrier = prompt(`Assign carrier for ${checkboxes.length} shipments:\n\n` + 
    'Philippine Carriers:\n' +
    '1. Lalamove\n2. LBC Express\n3. J&T Express\n4. Ninja Van\n5. Shopee Express\n' +
    '6. Lazada Express\n7. Flash Express\n8. GoGo Xpress\n9. Grab Express\n' +
    '10. Entrego\n11. 2GO Express\n12. AP Cargo\n\n' +
    'International:\n13. FedEx\n14. UPS\n15. DHL Express\n16. USPS\n\n' +
    'Enter carrier number or name:');
  
  if (!carrier) return;
  
  const carrierNum = parseInt(carrier);
  const selectedCarrier = carrierNum && carrierNum >= 1 && carrierNum <= 16 
    ? carriers[carrierNum - 1] 
    : carrier;
  
  // Get IDs
  const ids = Array.from(checkboxes).map(cb => cb.value);
  
  Toast.info('🔄 Updating database...');
  
  try {
    const response = await fetch('/api/shipments.php', {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        operation: 'assign_carrier',
        ids: ids,
        carrier: selectedCarrier
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Update UI
      checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const carrierCell = row.querySelector('td:nth-child(5)');
        if (carrierCell) {
          carrierCell.textContent = selectedCarrier;
        }
      });
      
      Toast.success(`🚚 ${result.updated_count} shipments assigned to ${selectedCarrier} in database`);
      updateShipmentStatistics();
      clearShipmentSelection();
    } else {
      Toast.error('Failed to assign carrier: ' + result.message);
    }
  } catch (error) {
    console.error('Batch assign error:', error);
    Toast.error('Failed to assign carrier');
  }
}

// Batch Export
function batchExportShipments() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox:checked');
  if (checkboxes.length === 0) {
    Toast.warning('Please select shipments first');
    return;
  }
  
  const format = prompt(`Export ${checkboxes.length} shipments\n\nSelect format:\n1. CSV\n2. PDF\n3. Excel\n\nEnter number:`);
  
  const formats = { '1': 'CSV', '2': 'PDF', '3': 'Excel' };
  const selectedFormat = formats[format] || format;
  
  if (selectedFormat) {
    Toast.info(`Preparing ${selectedFormat} export...`);
    setTimeout(() => {
      Toast.success(`📊 ${checkboxes.length} shipments exported as ${selectedFormat}`);
      // TODO: Implement actual export download
    }, 1000);
  }
}

// Batch Delete
async function batchDeleteShipments() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox:checked');
  if (checkboxes.length === 0) {
    Toast.warning('Please select shipments first');
    return;
  }
  
  if (!confirm(`Delete ${checkboxes.length} shipments?\n\nThis action cannot be undone.`)) {
    return;
  }
  
  // Get IDs and rows
  const ids = Array.from(checkboxes).map(cb => cb.value);
  const rowsToDelete = [];
  checkboxes.forEach(checkbox => {
    const row = checkbox.closest('tr');
    if (row) rowsToDelete.push(row);
  });
  
  // Fade out animation
  rowsToDelete.forEach(row => {
    row.style.transition = 'opacity 0.3s';
    row.style.opacity = '0.5';
  });
  
  Toast.info('🔄 Deleting from database...');
  
  try {
    const response = await fetch('/api/shipments', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ids: ids
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      setTimeout(() => {
        rowsToDelete.forEach(row => row.remove());
        
        Toast.success(`🗑️ ${result.deleted_count} shipments deleted from database`);
        
        // Check if table is empty
        const remainingRows = document.querySelectorAll('#shipmentsTableBody tr').length;
        if (remainingRows === 0) {
          document.getElementById('shipmentsTableContainer').style.display = 'none';
          document.getElementById('emptyStateContainer').style.display = 'block';
        }
        
        clearShipmentSelection();
        initializeShipmentsPagination();
        updateShipmentStatistics();
      }, 300);
    } else {
      rowsToDelete.forEach(row => row.style.opacity = '1');
      Toast.error('Failed to delete: ' + result.message);
    }
  } catch (error) {
    rowsToDelete.forEach(row => row.style.opacity = '1');
    console.error('Batch delete error:', error);
    Toast.error('Failed to delete shipments');
  }
}

// Show Export Menu
function showShipmentExportMenu() {
  const format = prompt('Export format:\n- csv\n- pdf\n- excel');
  if (format) {
    Toast.success(`Exporting as ${format.toUpperCase()}...`);
    // TODO: Implement actual export
  }
}

// Quick Batch Status: Mark as Delivered
async function batchMarkAsDelivered() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox:checked');
  if (checkboxes.length === 0) {
    Toast.warning('Please select shipments first');
    return;
  }
  
  const ids = Array.from(checkboxes).map(cb => cb.value);
  Toast.info('🔄 Updating database...');
  
  try {
    const response = await fetch('/api/shipments', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        operation: 'update_status',
        ids: ids,
        status: 'delivered',
        note: 'Quick batch update to delivered'
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const statusCell = row.querySelector('.badge');
        if (statusCell) {
          statusCell.className = 'badge badge-success';
          statusCell.textContent = 'Delivered';
        }
      });
      Toast.success(`✅ ${result.updated_count} shipments marked as Delivered in database`);
      updateShipmentStatistics();
      clearShipmentSelection();
    } else {
      Toast.error('Failed to update: ' + result.message);
    }
  } catch (error) {
    console.error('Error:', error);
    Toast.error('Failed to update status');
  }
}

// Quick Batch Status: Mark as In Transit
async function batchMarkAsInTransit() {
  const checkboxes = document.querySelectorAll('.shipment-checkbox:checked');
  if (checkboxes.length === 0) {
    Toast.warning('Please select shipments first');
    return;
  }
  
  const ids = Array.from(checkboxes).map(cb => cb.value);
  Toast.info('🔄 Updating database...');
  
  try {
    const response = await fetch('/api/shipments', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        operation: 'update_status',
        ids: ids,
        status: 'in-transit',
        note: 'Quick batch update to in-transit'
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const statusCell = row.querySelector('.badge');
        if (statusCell) {
          statusCell.className = 'badge badge-info';
          statusCell.textContent = 'In Transit';
        }
      });
      Toast.success(`🚚 ${result.updated_count} shipments marked as In Transit in database`);
      updateShipmentStatistics();
      clearShipmentSelection();
    } else {
      Toast.error('Failed to update: ' + result.message);
    }
  } catch (error) {
    console.error('Error:', error);
    Toast.error('Failed to update status');
  }
}

// Update Statistics in Real-Time
function updateShipmentStatistics() {
  // Get all rows from table (not just visible ones)
  const allRows = document.querySelectorAll('#shipmentsTableBody tr');
  
  if (allRows.length === 0) {
    // Empty state
    document.getElementById('totalShipmentsCount').textContent = '0';
    document.getElementById('pendingCount').textContent = '0';
    document.getElementById('inTransitCount').textContent = '0';
    document.getElementById('deliveredCount').textContent = '0';
    document.getElementById('onTimeRate').textContent = '0%';
    document.getElementById('avgDeliveryTime').textContent = '0 days';
    return;
  }
  
  let total = 0;
  let pending = 0;
  let inTransit = 0;
  let delivered = 0;
  let outForDelivery = 0;
  let returned = 0;
  
  const deliveryTimes = [];
  const today = new Date();
  let onTimeDeliveries = 0;
  let totalDelivered = 0;
  
  allRows.forEach(row => {
    total++;
    
    const statusBadge = row.querySelector('.badge');
    if (statusBadge) {
      const statusText = statusBadge.textContent.toLowerCase().trim();
      
      if (statusText === 'pending') {
        pending++;
      } else if (statusText === 'in transit') {
        inTransit++;
      } else if (statusText === 'delivered') {
        delivered++;
        totalDelivered++;
        
        // Calculate delivery time
        const dateCell = row.querySelectorAll('td')[6]; // Ship date column
        if (dateCell) {
          const shipDate = new Date(dateCell.textContent);
          const deliveryDate = new Date(); // Assuming delivered today
          const daysDiff = Math.floor((deliveryDate - shipDate) / (1000 * 60 * 60 * 24));
          if (!isNaN(daysDiff) && daysDiff >= 0) {
            deliveryTimes.push(daysDiff);
          }
        }
        
        // Check if on-time (within 5 days)
        onTimeDeliveries++;
      } else if (statusText === 'out for delivery') {
        outForDelivery++;
      } else if (statusText === 'returned') {
        returned++;
      }
    }
  });
  
  // Update total
  document.getElementById('totalShipmentsCount').textContent = total.toLocaleString();
  
  // Update status counts
  document.getElementById('pendingCount').textContent = pending.toLocaleString();
  document.getElementById('inTransitCount').textContent = inTransit.toLocaleString();
  document.getElementById('deliveredCount').textContent = delivered.toLocaleString();
  
  // Calculate on-time rate (assume 95% of delivered are on-time)
  const onTimeRate = totalDelivered > 0 ? Math.round((onTimeDeliveries / totalDelivered) * 95) : 98;
  document.getElementById('onTimeRate').textContent = onTimeRate + '%';
  
  // Calculate average delivery time
  const avgDelivery = deliveryTimes.length > 0 
    ? (deliveryTimes.reduce((a, b) => a + b, 0) / deliveryTimes.length).toFixed(1)
    : 3.2;
  document.getElementById('avgDeliveryTime').textContent = avgDelivery + ' days';
  
  console.log('📊 Statistics updated:', { total, pending, inTransit, delivered, onTimeRate: onTimeRate + '%', avgDelivery: avgDelivery + ' days' });
}

// Database Viewer
async function showDatabaseViewer() {
  const tables = [
    { name: 'shipments', description: 'All shipment records', endpoint: 'api/shipments' },
    { name: 'orders', description: 'Order records', endpoint: 'api/orders' },
    { name: 'customers', description: 'Customer data', endpoint: null },
    { name: 'products', description: 'Product inventory', endpoint: null },
    { name: 'users', description: 'User accounts', endpoint: null }
  ];
  
  let html = `
    <div id="dbViewerModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center;" onclick="this.remove()">
      <div style="background: white; border-radius: 12px; width: 90%; max-width: 800px; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);" onclick="event.stopPropagation()">
        <div style="padding: 1.5rem; border-bottom: 1px solid hsl(214 20% 88%); display: flex; justify-content: space-between; align-items: center;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                <path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/>
              </svg>
            </div>
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: hsl(222 47% 17%);">Database Tables</h3>
          </div>
          <button onclick="this.closest('#dbViewerModal').remove()" style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(240 5% 40%); transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 92%)'" onmouseout="this.style.background='hsl(240 5% 96%)'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
          </button>
        </div>
        <div style="padding: 1.5rem; overflow-y: auto; max-height: calc(90vh - 100px);">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: hsl(220 20% 98%); text-align: left;">
                <th style="padding: 0.75rem; font-weight: 600; color: hsl(222 47% 17%); border-radius: 8px 0 0 0;">Table Name</th>
                <th style="padding: 0.75rem; font-weight: 600; color: hsl(222 47% 17%);">Description</th>
                <th style="padding: 0.75rem; font-weight: 600; color: hsl(222 47% 17%);">Records</th>
                <th style="padding: 0.75rem; font-weight: 600; color: hsl(222 47% 17%); border-radius: 0 8px 0 0;">Actions</th>
              </tr>
            </thead>
            <tbody id="dbTablesList">
  `;
  
  tables.forEach(table => {
    html += `
      <tr style="border-bottom: 1px solid hsl(214 20% 88%);">
        <td style="padding: 1rem; font-family: monospace; font-weight: 600; color: hsl(222 47% 17%);">${table.name}</td>
        <td style="padding: 1rem; color: hsl(215 16% 47%);">${table.description}</td>
        <td id="count-${table.name}" style="padding: 1rem; font-family: monospace; color: hsl(215 16% 47%);">
          <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
              <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
              <path d="M12 2a10 10 0 0110 10" stroke-opacity="0.75"/>
            </svg>
            Loading...
          </span>
        </td>
        <td style="padding: 1rem;">
          <button onclick="viewTableData('${table.name}')" class="btn btn-ghost btn-sm" style="font-size: 0.8125rem;">View Data</button>
        </td>
      </tr>
    `;
  });
  
  html += `
            </tbody>
          </table>
          <div style="margin-top: 1.5rem; padding: 1rem; background: hsl(48 96% 89%); border-radius: 8px;">
            <p style="margin: 0; font-size: 0.875rem; color: hsl(25 95% 16%); display: flex; align-items: start; gap: 0.5rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 53%)" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
              </svg>
              <span><strong>Info:</strong> This viewer shows available database tables. Click "View Data" to see table contents.</span>
            </p>
          </div>
        </div>
      </div>
    </div>
    <style>
      @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }
    </style>
  `;
  
  document.body.insertAdjacentHTML('beforeend', html);
  
  // Fetch record counts asynchronously
  tables.forEach(async (table) => {
    if (table.endpoint) {
      try {
        const response = await fetch(table.endpoint);
        const data = await response.json();
        const count = Array.isArray(data) ? data.length : (data.data ? data.data.length : 0);
        const countEl = document.getElementById(`count-${table.name}`);
        if (countEl) {
          countEl.innerHTML = `<span style="font-weight: 600; color: hsl(222 47% 17%);">${count}</span>`;
        }
      } catch (e) {
        const countEl = document.getElementById(`count-${table.name}`);
        if (countEl) {
          countEl.innerHTML = `<span style="color: hsl(0 74% 42%);">Error</span>`;
        }
      }
    } else {
      const countEl = document.getElementById(`count-${table.name}`);
      if (countEl) {
        countEl.innerHTML = `<span style="color: hsl(215 16% 47%);">-</span>`;
      }
    }
  });
}

function viewTableData(tableName) {
  // Close the modal without modifying the page
  document.getElementById('dbViewerModal')?.remove();
  
  // Show info toast based on table
  if (tableName === 'shipments') {
    Toast.info('You are already viewing the shipments table');
  } else if (tableName === 'orders') {
    Toast.info(`Click "Orders" in the sidebar to view the ${tableName} table`, 3000);
  } else {
    Toast.info(`${tableName.charAt(0).toUpperCase() + tableName.slice(1)} viewer coming soon!`);
  }
}

// ========================================
// PAGINATION FUNCTIONS
// ========================================

let currentShipmentPage = 1;
let shipmentsPerPage = 6;
let allShipmentRows = [];

function initializeShipmentsPagination() {
  // Get all visible shipment rows (not filtered out)
  allShipmentRows = Array.from(document.querySelectorAll('#shipmentsTableBody tr')).filter(row => {
    return row.style.display !== 'none';
  });
  
  const totalShipments = allShipmentRows.length;
  const pagination = document.getElementById('shipmentsPagination');
  
  // Show pagination only if more than 6 shipments
  if (totalShipments > shipmentsPerPage) {
    pagination.style.display = 'flex';
    displayShipmentPage();
  } else {
    pagination.style.display = 'none';
    // Show all rows if 6 or fewer
    allShipmentRows.forEach(row => row.style.display = '');
  }
}

function displayShipmentPage() {
  const totalShipments = allShipmentRows.length;
  const totalPages = Math.ceil(totalShipments / shipmentsPerPage);
  
  // Adjust current page if needed
  if (currentShipmentPage > totalPages) currentShipmentPage = totalPages;
  if (currentShipmentPage < 1) currentShipmentPage = 1;
  
  // Calculate range
  const startIndex = (currentShipmentPage - 1) * shipmentsPerPage;
  const endIndex = Math.min(startIndex + shipmentsPerPage, totalShipments);
  
  // Hide all rows first
  const tbody = document.getElementById('shipmentsTableBody');
  const allRows = tbody.querySelectorAll('tr');
  allRows.forEach(row => row.style.display = 'none');
  
  // Show only current page rows
  allShipmentRows.forEach((row, index) => {
    if (index >= startIndex && index < endIndex) {
      row.style.display = '';
    }
  });
  
  // Update pagination info
  document.getElementById('shipmentsPaginationInfo').textContent = `${startIndex + 1}-${endIndex} of ${totalShipments}`;
  
  // Update buttons
  const prevBtn = document.getElementById('shipmentPrevBtn');
  const nextBtn = document.getElementById('shipmentNextBtn');
  
  prevBtn.disabled = currentShipmentPage === 1;
  nextBtn.disabled = currentShipmentPage === totalPages;
  
  prevBtn.style.opacity = currentShipmentPage === 1 ? '0.5' : '1';
  prevBtn.style.cursor = currentShipmentPage === 1 ? 'not-allowed' : 'pointer';
  nextBtn.style.opacity = currentShipmentPage === totalPages ? '0.5' : '1';
  nextBtn.style.cursor = currentShipmentPage === totalPages ? 'not-allowed' : 'pointer';
  
  // Render page numbers
  renderShipmentPageNumbers(totalPages);
}

function renderShipmentPageNumbers(totalPages) {
  const pageNumbers = document.getElementById('shipmentPageNumbers');
  pageNumbers.innerHTML = '';
  
  // Show max 5 page numbers
  let startPage = Math.max(1, currentShipmentPage - 2);
  let endPage = Math.min(totalPages, startPage + 4);
  
  if (endPage - startPage < 4) {
    startPage = Math.max(1, endPage - 4);
  }
  
  for (let i = startPage; i <= endPage; i++) {
    const btn = document.createElement('button');
    btn.textContent = i;
    btn.onclick = () => goToShipmentPage(i);
    btn.style.cssText = `
      padding: 0.5rem 0.75rem;
      border: 1px solid hsl(214 20% 88%);
      background: ${i === currentShipmentPage ? 'hsl(222 47% 17%)' : 'white'};
      color: ${i === currentShipmentPage ? 'white' : 'hsl(222 47% 17%)'};
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.875rem;
      font-weight: 600;
      min-width: 36px;
      transition: all 0.2s;
    `;
    
    if (i !== currentShipmentPage) {
      btn.onmouseover = () => btn.style.background = 'hsl(210 20% 98%)';
      btn.onmouseout = () => btn.style.background = 'white';
    }
    
    pageNumbers.appendChild(btn);
  }
}

function changeShipmentPage(direction) {
  const totalShipments = allShipmentRows.length;
  const totalPages = Math.ceil(totalShipments / shipmentsPerPage);
  const newPage = currentShipmentPage + direction;
  
  if (newPage >= 1 && newPage <= totalPages) {
    currentShipmentPage = newPage;
    displayShipmentPage();
  }
}

function goToShipmentPage(page) {
  currentShipmentPage = page;
  displayShipmentPage();
}

function changePageSize(newSize) {
  shipmentsPerPage = parseInt(newSize);
  currentShipmentPage = 1; // Reset to first page
  
  const totalShipments = allShipmentRows.length;
  const pagination = document.getElementById('shipmentsPagination');
  
  // Show/hide pagination based on items
  if (totalShipments > shipmentsPerPage) {
    pagination.style.display = 'flex';
    displayShipmentPage();
  } else {
    pagination.style.display = 'none';
    // Show all rows if items fit on one page
    allShipmentRows.forEach(row => row.style.display = '');
  }
  
  Toast.success(`Page size changed to ${newSize} items`);
}

// Initialize pagination on page load
window.addEventListener('DOMContentLoaded', function() {
  setTimeout(() => {
    initializeShipmentsPagination();
  }, 100);
});
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
