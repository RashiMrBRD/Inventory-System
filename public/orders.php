<?php
/**
 * Orders Module
 * Manages sales and purchase orders
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Model\Order;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Load orders from database
$orderModel = new Order();
try {
    $orders = $orderModel->getAll();
    foreach ($orders as &$order) {
        if (isset($order['_id'])) {
            $order['id'] = (string)$order['_id'];
        }
    }
    unset($order);
} catch (\Exception $e) {
    $orders = [];
}

// Calculate comprehensive statistics
$salesOrders = array_filter($orders, fn($o) => ($o['type'] ?? '') === 'Sales');
$purchaseOrders = array_filter($orders, fn($o) => ($o['type'] ?? '') === 'Purchase');
$salesCount = count($salesOrders);
$purchaseCount = count($purchaseOrders);

$pendingCount = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'pending'));
$processingCount = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'processing'));
$shippedCount = count(array_filter($orders, fn($o) => in_array(($o['status'] ?? ''), ['shipped', 'delivered', 'received'])));
$cancelledCount = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'cancelled'));

$salesValue = array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $salesOrders));
$purchaseValue = array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $purchaseOrders));
$totalValue = array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $orders));

$pageTitle = 'Orders';
ob_start();
?>

<style>
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

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

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(-20px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* Enhanced Input Focus States - Shadcn Style */
input[type="text"]:focus,
input[type="email"]:focus,
input[type="tel"]:focus,
input[type="date"]:focus,
input[type="number"]:focus,
select:focus,
textarea:focus {
  outline: none !important;
  border-color: #7194A5 !important;
  box-shadow: 0 0 0 3px rgba(113, 148, 165, 0.1) !important;
}

/* Button Hover Effects */
button[type="submit"]:hover:not(:disabled) {
  background: #1a1a1a !important;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

button[type="submit"]:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Line Item Card Styling */
.line-item-card {
  transition: all 0.2s ease;
}

.line-item-card:hover {
  background: hsl(214 95% 98%) !important;
  border-color: hsl(214 20% 85%) !important;
}

.line-item-card:last-child {
  border-bottom: none !important;
}

/* Line Item Input Focus */
.item-description:focus,
.item-quantity:focus,
.item-price:focus {
  border-color: #7194A5 !important;
  box-shadow: 0 0 0 3px rgba(113, 148, 165, 0.1) !important;
  outline: none;
}

/* Discount Input Styling */
#discountInput:focus {
  border-color: #7194A5 !important;
  box-shadow: 0 0 0 3px rgba(113, 148, 165, 0.1) !important;
  outline: none;
}

/* Red asterisk for required fields */
label[data-required="true"]::after {
  content: ' *';
  color: hsl(0 74% 42%);
  font-weight: 700;
  margin-left: 2px;
}
</style>

<div style="background: #7194A5; color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
  <div class="container">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 32px; backdrop-filter: blur(10px);">
        📦
      </div>
      <div style="flex: 1;">
        <h1 style="font-size: 1.875rem; font-weight: 700; margin: 0 0 0.25rem 0; color: white;">Order Management</h1>
        <p style="font-size: 0.875rem; margin: 0; opacity: 0.9;">Manage sales and purchase orders with advanced tracking</p>
      </div>
      <button onclick="showNewOrderModal()" style="padding: 0.625rem 1.5rem; background: rgba(255,255,255,0.95); border: none; border-radius: 8px; color: #7194A5; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='white'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.95)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
        New Order
      </button>
      <a href="dashboard.php" style="padding: 0.625rem 1.25rem; background: rgba(255,255,255,0.2); border-radius: 8px; color: white; text-decoration: none; font-weight: 500; backdrop-filter: blur(10px); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        ← Dashboard
      </a>
    </div>
  </div>
</div>

<div id="statsCards" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.875rem; margin-bottom: 1.5rem;">
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Orders</p>
      <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(222 47% 17%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 11L12 14L22 4M21 12V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H16" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="totalOrders" style="font-size: 1.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;"><?php echo number_format(count($orders)); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Sales Orders</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(140 61% 13%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2"/><path d="M2 17L12 22L22 17M2 12L12 17L22 12" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="salesCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;"><?php echo number_format($salesCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Purchase Orders</p>
      <div style="width: 36px; height: 36px; background: hsl(262 83% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(263 70% 26%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="purchaseCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(263 70% 26%); margin: 0;"><?php echo number_format($purchaseCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Processing</p>
      <div style="width: 36px; height: 36px; background: hsl(48 96% 89%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(25 95% 16%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 8V12L15 15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="processingCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(25 95% 16%); margin: 0;"><?php echo number_format($processingCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Shipped</p>
      <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(222 47% 17%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="1" y="3" width="15" height="13" stroke="currentColor" stroke-width="2"/><path d="M16 8H20L23 11V16H16V8Z" stroke="currentColor" stroke-width="2"/><circle cx="5.5" cy="18.5" r="2.5" stroke="currentColor" stroke-width="2"/><circle cx="18.5" cy="18.5" r="2.5" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="shippedCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;"><?php echo number_format($shippedCount); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Value</p>
      <div style="width: 36px; height: 36px; background: rgba(113,148,165,0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #7194A5;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      </div>
    </div>
    <p id="totalValue" style="font-size: 1.75rem; font-weight: 700; color: #7194A5; margin: 0;"><?php echo CurrencyHelper::format($totalValue); ?></p>
  </div>
</div>

<div class="toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
  <div class="toolbar-left" style="display: flex; align-items: center; gap: 0.75rem;">
    <div class="search-wrapper" style="position: relative; display: flex; align-items: center;">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" style="position: absolute; left: 0.875rem; color: hsl(215 16% 47%); pointer-events: none;">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input type="search" class="search-input" placeholder="Search orders..." id="order-search" oninput="searchOrders()" style="padding: 0.625rem 0.875rem 0.625rem 2.5rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; min-width: 280px; transition: all 0.2s;">
    </div>
  </div>
  <div class="toolbar-right" style="display: flex; align-items: center; gap: 0.75rem;">
    <select class="form-select" id="type-filter" onchange="applyFilters()" style="padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; cursor: pointer;">
      <option value="all">All Types</option>
      <option value="Sales">Sales Orders</option>
      <option value="Purchase">Purchase Orders</option>
    </select>
    <select class="form-select" id="status-filter" onchange="applyFilters()" style="padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; cursor: pointer;">
      <option value="all">All Status</option>
      <option value="draft">Draft</option>
      <option value="pending">Pending</option>
      <option value="processing">Processing</option>
      <option value="shipped">Shipped</option>
    </select>
    <button class="btn btn-ghost btn-icon" onclick="showExportMenu()" title="Export">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
    <button class="btn btn-ghost btn-icon" onclick="showBatchOperations()" title="Batch Operations">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7"/>
        <rect x="14" y="3" width="7" height="7"/>
        <rect x="14" y="14" width="7" height="7"/>
        <rect x="3" y="14" width="7" height="7"/>
      </svg>
    </button>
  </div>
</div>

<!-- Batch Operations Toolbar (Hidden by default) -->
<div id="batchToolbar" style="display: none; background: linear-gradient(135deg, hsl(240 5% 96%) 0%, hsl(240 6% 90%) 100%); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid hsl(214 20% 88%);">
  <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
    <span style="font-weight: 600; color: hsl(222 47% 17%);">Batch Actions:</span>
    <button class="btn btn-ghost" onclick="batchUpdateStatus()" style="font-size: 0.875rem;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      Update Status
    </button>
    <button class="btn btn-ghost" onclick="batchExport()" style="font-size: 0.875rem;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
      Export Selected
    </button>
    <button class="btn btn-ghost" onclick="batchDelete()" style="font-size: 0.875rem; color: hsl(0 74% 42%);">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      Delete Selected
    </button>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 0.5rem;">
      <span id="selectedCount" style="font-size: 0.875rem; color: hsl(215 16% 47%);">0 selected</span>
      <button onclick="clearSelection()" style="background: none; border: none; color: hsl(215 16% 47%); cursor: pointer; font-size: 0.875rem; text-decoration: underline;">Clear</button>
    </div>
  </div>
</div>

<div id="ordersTableContainer" class="table-container" style="display: <?php echo empty($orders) ? 'none' : 'block'; ?>;">
  <table class="data-table">
    <thead>
      <tr>
        <th class="checkbox-column" style="width: 40px; display: none;">
          <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor: pointer;">
        </th>
        <th onclick="sortTable('order_number')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Order #
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortTable('type')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Type
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortTable('customer')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Customer/Vendor
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortTable('date')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Date
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortTable('due_date')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Due Date
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortTable('total')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Amount
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortTable('payment')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Payment
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th onclick="sortTable('status')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            Status
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.5;">
              <path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>
            </svg>
          </div>
        </th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="ordersTableBody">
      <?php foreach ($orders as $order): ?>
      <tr data-order-id="<?php echo $order['id']; ?>">
        <td class="checkbox-column" style="display: none;">
          <input type="checkbox" class="order-checkbox" value="<?php echo $order['id']; ?>" onchange="updateBatchToolbar()" style="cursor: pointer;">
        </td>
        <td class="font-mono font-medium"><?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?></td>
        <td><span class="badge <?php echo $order['type'] === 'Sales' ? 'badge-success' : 'badge-default'; ?>"><?php echo $order['type']; ?></span></td>
        <td class="font-medium"><?php echo htmlspecialchars($order['customer']); ?></td>
        <td><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
        <td><?php 
          $dueDate = isset($order['due_date']) ? $order['due_date'] : date('Y-m-d', strtotime($order['date'] . ' + 30 days'));
          echo date('M d, Y', strtotime($dueDate));
        ?></td>
        <td class="font-semibold"><?php echo CurrencyHelper::format($order['total']); ?></td>
        <td>
          <?php 
          $paid = $order['amount_paid'] ?? 0;
          $total = $order['total'];
          $percentage = $total > 0 ? ($paid / $total) * 100 : 0;
          $statusColor = $percentage >= 100 ? 'hsl(140 61% 13%)' : ($percentage > 0 ? 'hsl(25 95% 16%)' : 'hsl(215 16% 47%)');
          ?>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 60px; height: 6px; background: hsl(214 20% 92%); border-radius: 3px; overflow: hidden;">
              <div style="width: <?php echo $percentage; ?>%; height: 100%; background: <?php echo $statusColor; ?>; transition: width 0.3s;"></div>
            </div>
            <span style="font-size: 0.75rem; color: <?php echo $statusColor; ?>; font-weight: 600;"><?php echo number_format($percentage); ?>%</span>
          </div>
        </td>
        <td>
          <?php
          $badges = ['draft' => 'badge-secondary', 'pending' => 'badge-warning', 'processing' => 'badge-default', 'shipped' => 'badge-success', 'received' => 'badge-success'];
          ?>
          <span class="badge <?php echo $badges[$order['status']] ?? 'badge-default'; ?>"><?php echo ucfirst($order['status']); ?></span>
        </td>
        <td>
          <div class="flex gap-1">
            <button class="btn btn-ghost btn-sm" onclick="viewOrder('<?php echo $order['id']; ?>')" title="View Details">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="recordPayment('<?php echo $order['id']; ?>')" title="Record Payment">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="5" width="20" height="14" rx="2"/>
                <path d="M2 10h20"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="printOrder('<?php echo $order['id']; ?>')" title="Print/PDF">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M6 9V2H18V9M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18M6 14H18V22H6V14Z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="emailOrder('<?php echo $order['id']; ?>')" title="Email">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <path d="M22 6l-10 7L2 6"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="showMoreActions('<?php echo $order['id']; ?>', '<?php echo $order['status']; ?>')" title="More Actions">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="1" fill="currentColor"/>
                <circle cx="12" cy="5" r="1" fill="currentColor"/>
                <circle cx="12" cy="19" r="1" fill="currentColor"/>
              </svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  
  <!-- Pagination Controls -->
  <div id="paginationContainer" style="display: flex; align-items: center; justify-content: space-between; padding: 1.5rem 1rem; border-top: 1px solid hsl(214 20% 88%); background: hsl(220 20% 98%);">
    <div style="color: hsl(215 16% 47%); font-size: 0.875rem;">
      Showing <span id="paginationInfo" style="font-weight: 600; color: hsl(222 47% 17%);">1-6 of <?php echo count($orders); ?></span> orders
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
      <button id="prevPageBtn" onclick="changePage(-1)" style="padding: 0.5rem 0.75rem; border: 1px solid hsl(214 20% 88%); background: white; border-radius: 6px; cursor: pointer; color: hsl(222 47% 17%); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='white'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
        Previous
      </button>
      <div id="pageNumbers" style="display: flex; gap: 0.25rem;"></div>
      <button id="nextPageBtn" onclick="changePage(1)" style="padding: 0.5rem 0.75rem; border: 1px solid hsl(214 20% 88%); background: white; border-radius: 6px; cursor: pointer; color: hsl(222 47% 17%); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='white'">
        Next
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
      </button>
    </div>
  </div>
</div>

<div id="emptyStateContainer" style="display: <?php echo empty($orders) ? 'block' : 'none'; ?>; padding: 4rem 2rem; text-align: center; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
  <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#6b7280" style="opacity: 0.15; margin: 0 auto 1.5rem; stroke-width: 1.5;">
    <path d="M9 11L12 14L22 4M21 12V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H16" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <h3 style="font-size: 1.25rem; font-weight: 600; color: #111827; margin: 0 0 0.75rem 0;">No orders yet</h3>
  <p style="font-size: 0.9375rem; color: #6b7280; margin: 0 auto 1.5rem; max-width: 28rem; line-height: 1.6;">
    Get started by creating your first order. Click the "New Order" button above to begin.
  </p>
  <button onclick="showNewOrderModal()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
    Create Your First Order
  </button>
</div>

<!-- View Order Details Modal -->
<div id="viewOrderModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; pointer-events: auto;">
  <div style="width: 100%; max-width: 1300px; max-height: 90vh; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid hsl(240 6% 90%); display: flex; flex-direction: column; overflow: hidden; pointer-events: auto; animation: modalSlideIn 0.3s ease-out;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, hsl(220 20% 97%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%); display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 3px 10px rgba(113,148,165,0.3);">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M9 11L12 14L22 4M21 12V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H16"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <h2 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: hsl(240 10% 10%); line-height: 1.2;">Order Details</h2>
          <p id="viewOrderNumber" style="margin: 0.25rem 0 0; font-size: 0.75rem; color: hsl(240 5% 50%); font-weight: 500; font-family: monospace;"></p>
        </div>
      </div>
      <button type="button" onclick="closeViewOrderModal()" style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(240 5% 40%); transition: all 0.2s; flex-shrink: 0;" onmouseover="this.style.background='hsl(240 5% 92%)'; this.style.borderColor='hsl(240 6% 85%)'" onmouseout="this.style.background='hsl(240 5% 96%)'; this.style.borderColor='hsl(240 6% 90%)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6L18 18" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
    </div>
    
    <!-- Content -->
    <div style="padding: 1.5rem;">
      <!-- Top Info Grid (4 columns) -->
      <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
        <div>
          <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Customer/Vendor</div>
          <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="viewOrderCustomer">-</div>
        </div>
        <div>
          <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Type</div>
          <div><span id="viewOrderType" class="badge" style="font-size: 0.6875rem; padding: 0.25rem 0.5rem;">-</span></div>
        </div>
        <div>
          <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</div>
          <div><span id="viewOrderStatus" class="badge" style="font-size: 0.6875rem; padding: 0.25rem 0.5rem;">-</span></div>
        </div>
        <div>
          <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Order Date</div>
          <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="viewOrderDate">-</div>
        </div>
        <div>
          <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Due Date</div>
          <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="viewOrderDueDate">-</div>
        </div>
        <div>
          <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Payment Terms</div>
          <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="viewOrderTerms">-</div>
        </div>
      </div>
      
      <!-- 2 Column Layout: Line Items (Left) & Financial Summary (Right) -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        
        <!-- Left: Line Items Card -->
        <div style="background: linear-gradient(135deg, hsl(214 95% 96%) 0%, hsl(214 95% 93%) 100%); border: 1px solid hsl(214 90% 80%); border-radius: 10px; padding: 1.25rem;">
          <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 1rem;">
            <div style="width: 28px; height: 28px; background: hsl(214 95% 50%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
            </div>
            <h3 style="font-size: 0.6875rem; font-weight: 700; color: hsl(214 95% 20%); margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Line Items</h3>
          </div>
          <div id="viewOrderItems" style="max-height: 280px; overflow-y: auto;"></div>
        </div>
        
        <!-- Right: Financial Summary Card -->
        <div style="background: linear-gradient(135deg, hsl(143 85% 98%) 0%, hsl(143 85% 95%) 100%); border: 1px solid hsl(143 80% 85%); border-radius: 10px; padding: 1.25rem;">
          <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 1rem;">
            <div style="width: 28px; height: 28px; background: hsl(140 61% 50%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
              </svg>
            </div>
            <h3 style="font-size: 0.6875rem; font-weight: 700; color: hsl(140 61% 20%); margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Financial Summary</h3>
          </div>
          <div style="display: flex; flex-direction: column; gap: 0.625rem;">
            <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(143 70% 88%);">
              <span style="font-size: 0.75rem; color: hsl(140 61% 30%); font-weight: 600;">Subtotal</span>
              <span id="viewOrderSubtotal" style="font-family: monospace; font-weight: 700; color: hsl(140 61% 20%); font-size: 0.8125rem;">₱0.00</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(143 70% 88%);">
              <span style="font-size: 0.75rem; color: hsl(140 61% 30%); font-weight: 600;">Tax (10%)</span>
              <span id="viewOrderTax" style="font-family: monospace; font-weight: 700; color: hsl(140 61% 20%); font-size: 0.8125rem;">₱0.00</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(143 70% 88%);">
              <span style="font-size: 0.75rem; color: hsl(140 61% 30%); font-weight: 600;">Discount</span>
              <span id="viewOrderDiscount" style="font-family: monospace; font-weight: 700; color: hsl(0 74% 42%); font-size: 0.8125rem;">-₱0.00</span>
            </div>
            <div style="background: hsl(140 61% 50%); padding: 0.75rem; border-radius: 6px; margin: 0.5rem 0; display: flex; justify-content: space-between; align-items: center;">
              <span style="font-weight: 700; font-size: 0.875rem; color: white;">Total</span>
              <span id="viewOrderTotal" style="font-family: monospace; font-weight: 800; font-size: 1.25rem; color: white;">₱0.00</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.375rem 0;">
              <span style="font-size: 0.75rem; color: hsl(140 61% 30%); font-weight: 600;">Amount Paid</span>
              <span id="viewOrderPaid" style="font-family: monospace; font-weight: 700; color: hsl(140 61% 20%); font-size: 0.875rem;">₱0.00</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.625rem 0; padding-top: 0.75rem; border-top: 2px solid hsl(143 60% 80%);">
              <span style="font-weight: 700; color: hsl(0 74% 42%); font-size: 0.875rem;">Balance Due</span>
              <span id="viewOrderBalance" style="font-family: monospace; font-weight: 800; color: hsl(0 74% 42%); font-size: 1.125rem;">₱0.00</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Footer Actions -->
    <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap;">
      <button type="button" onclick="printOrderFromView()" class="btn btn-ghost">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2H18V9M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18M6 14H18V22H6V14Z"/></svg>
        Print/PDF
      </button>
      <button type="button" onclick="emailOrderFromView()" class="btn btn-ghost">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M22 6l-10 7L2 6"/></svg>
        Email
      </button>
      <button type="button" onclick="recordPaymentFromView()" class="btn btn-ghost" style="background: hsl(143 85% 96%); color: hsl(140 61% 13%); border: 1px solid hsl(143 60% 80%);">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        Record Payment
      </button>
      <button type="button" onclick="closeViewOrderModal()" class="btn btn-primary">Close</button>
    </div>
  </div>
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
          <polyline points="10 9 9 9 8 9"/>
        </svg>
        <h3 id="pdfViewerTitle" style="margin: 0; font-size: 1.125rem; font-weight: 700; color: hsl(222 47% 17%);">Order PDF</h3>
      </div>
      <button type="button" onclick="closePdfViewer()" style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(240 5% 40%); transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 92%)'" onmouseout="this.style.background='hsl(240 5% 96%)'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    
    <!-- PDF Iframe -->
    <iframe id="pdfViewerFrame" src="" style="width: 100%; height: 100%; border: none; background: white;"></iframe>
  </div>
</div>

<!-- Email Order Modal (Shadcn 2-Column Layout) -->
<div id="emailOrderModal" onclick="closeEmailOrderModal()" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 2rem;">
  <div onclick="event.stopPropagation()" style="width: 100%; max-width: 950px; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); display: flex; flex-direction: column; overflow: hidden;">
    
    <!-- Header -->
    <div style="background: linear-gradient(135deg, hsl(220 20% 97%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%); display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h3 style="margin: 0 0 0.25rem 0; font-size: 1.25rem; font-weight: 700; color: hsl(222 47% 17%);">Send Order via Email</h3>
        <p id="emailOrderNumber" style="margin: 0; font-size: 0.875rem; color: hsl(215 16% 47%); font-family: monospace;">ORD-001</p>
      </div>
      <button type="button" onclick="closeEmailOrderModal()" style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(240 5% 40%); transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 92%)'" onmouseout="this.style.background='hsl(240 5% 96%)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    
    <!-- Form Content with 2-Column Layout -->
    <form id="emailOrderForm" onsubmit="submitEmailOrder(event)" style="display: flex; flex-direction: column; flex: 1; overflow-y: auto;">
      <input type="hidden" id="emailOrderId" name="order_id">
      
      <!-- SMTP Warning (shown if not configured) -->
      <div id="smtpWarningOrder" style="display: none; margin: 2rem 2rem 0 2rem; padding: 1rem 1.25rem; background: hsl(48 96% 89%); border: 1px solid hsl(48 96% 75%); border-radius: 8px;">
        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;">
            <path d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
          </svg>
          <div>
            <p style="margin: 0 0 0.375rem 0; font-size: 0.875rem; font-weight: 700; color: hsl(25 95% 16%);">SMTP Not Configured</p>
            <p style="margin: 0; font-size: 0.8125rem; color: hsl(25 95% 16%); line-height: 1.5;">Email functionality requires SMTP server configuration. Please configure your email settings to send orders.</p>
          </div>
        </div>
      </div>
      
      <!-- Main Content Area with 2 Columns -->
      <div style="padding: 2rem; display: grid; grid-template-columns: 1fr 1.6fr; gap: 1.5rem;">
        
        <!-- LEFT COLUMN: Recipient Info & Options -->
        <div style="display: flex; flex-direction: column; gap: 0;">
          
          <!-- Recipient Card -->
          <div style="background: white; border: 1px solid hsl(240 6% 90%); border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <!-- Card Header -->
            <div style="background: hsl(240 5% 97%); padding: 0.875rem 1.25rem; border-bottom: 1px solid hsl(240 6% 90%);">
              <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2.5">
                  <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M8.5 11a4 4 0 100-8 4 4 0 000 8z"/>
                </svg>
                Recipient Information
              </h4>
            </div>
            <!-- Card Body -->
            <div style="padding: 1.25rem;">
              <!-- Recipient Email Field -->
              <div style="margin-bottom: 1rem;">
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(240 5% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  To <span style="color: hsl(0 72% 45%);">*</span>
                </label>
                <input type="email" id="recipientEmailOrder" name="recipient_email" required placeholder="customer@example.com" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(240 6% 90%); border-radius: 6px; font-size: 0.875rem; color: hsl(222 47% 17%); transition: all 0.2s; background: hsl(240 5% 99%);" onfocus="this.style.borderColor='#7194A5'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.12)'" onblur="this.style.borderColor='hsl(240 6% 90%)'; this.style.background='hsl(240 5% 99%)'; this.style.boxShadow='none'">
              </div>
              
              <!-- CC Email Field -->
              <div>
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(240 5% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  CC <span style="font-size: 0.625rem; font-weight: 600; color: hsl(215 16% 47%);">(Optional)</span>
                </label>
                <input type="email" name="cc_email" placeholder="cc@example.com" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(240 6% 90%); border-radius: 6px; font-size: 0.875rem; color: hsl(222 47% 17%); transition: all 0.2s; background: hsl(240 5% 99%);" onfocus="this.style.borderColor='#7194A5'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.12)'" onblur="this.style.borderColor='hsl(240 6% 90%)'; this.style.background='hsl(240 5% 99%)'; this.style.boxShadow='none'">
              </div>
            </div>
          </div>
          
          <!-- Attachment Card -->
          <div style="background: white; border: 1px solid hsl(240 6% 90%); border-radius: 10px; overflow: hidden; margin-top: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <!-- Card Header -->
            <div style="background: hsl(240 5% 97%); padding: 0.875rem 1.25rem; border-bottom: 1px solid hsl(240 6% 90%);">
              <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2.5">
                  <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                </svg>
                Attachments
              </h4>
            </div>
            
            <!-- Card Body -->
            <div style="padding: 1.25rem;">
              <!-- PDF Attachment Option -->
              <label style="display: flex; align-items: flex-start; gap: 0.875rem; padding: 0.875rem; background: hsl(240 5% 98%); border: 1px solid hsl(240 6% 90%); border-radius: 6px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 97%)'; this.style.borderColor='#7194A5'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.12)'" onmouseout="this.style.background='hsl(240 5% 98%)'; this.style.borderColor='hsl(240 6% 90%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <input type="checkbox" name="attach_pdf" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5; margin-top: 1px; flex-shrink: 0;">
                <div style="flex: 1;">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2.5">
                      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                      <polyline points="14 2 14 8 20 8"/>
                      <line x1="16" y1="13" x2="8" y2="13"/>
                      <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    <span style="font-size: 0.8125rem; font-weight: 700; color: hsl(222 47% 17%);">Order PDF</span>
                  </div>
                  <p style="margin: 0; font-size: 0.6875rem; color: hsl(215 16% 47%); line-height: 1.4;">Attach professional PDF order document</p>
                </div>
              </label>
            </div>
          </div>
          
        </div>
        
        <!-- RIGHT COLUMN: Email Content -->
        <div style="display: flex; flex-direction: column; gap: 0;">
          
          <!-- Email Content Card -->
          <div style="background: white; border: 1px solid hsl(240 6% 90%); border-radius: 10px; overflow: hidden; height: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <!-- Card Header -->
            <div style="background: hsl(240 5% 97%); padding: 0.875rem 1.25rem; border-bottom: 1px solid hsl(240 6% 90%);">
              <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2.5">
                  <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Email Content
              </h4>
            </div>
            
            <!-- Card Body -->
            <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
              <!-- Subject Field -->
              <div>
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(240 5% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  Subject <span style="color: hsl(0 72% 45%);">*</span>
                </label>
                <input type="text" id="emailSubject" name="subject" required placeholder="Order ORD-001 from Your Company" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(240 6% 90%); border-radius: 6px; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); transition: all 0.2s; background: hsl(240 5% 99%);" onfocus="this.style.borderColor='#7194A5'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.12)'" onblur="this.style.borderColor='hsl(240 6% 90%)'; this.style.background='hsl(240 5% 99%)'; this.style.boxShadow='none'">
              </div>
              
              <!-- Message Field -->
              <div style="flex: 1; display: flex; flex-direction: column;">
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(240 5% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  Message <span style="color: hsl(0 72% 45%);">*</span>
                </label>
                <textarea id="emailMessage" name="message" required rows="13" placeholder="Dear Customer,&#10;&#10;Please find attached order confirmation ORD-001.&#10;&#10;Thank you for your business!&#10;&#10;Best regards,&#10;Your Company" style="width: 100%; padding: 0.75rem 0.875rem; border: 1px solid hsl(240 6% 90%); border-radius: 6px; font-size: 0.8125rem; color: hsl(222 47% 17%); resize: none; transition: all 0.2s; font-family: inherit; line-height: 1.6; flex: 1; background: hsl(240 5% 99%);" onfocus="this.style.borderColor='#7194A5'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.12)'" onblur="this.style.borderColor='hsl(240 6% 90%)'; this.style.background='hsl(240 5% 99%)'; this.style.boxShadow='none'"></textarea>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.625rem; color: hsl(215 16% 47%); display: flex; align-items: center; gap: 0.375rem;">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 16v-4M12 8h.01"/>
                  </svg>
                  Keep language professional and check spelling
                </p>
              </div>
            </div>
          </div>
          
        </div>
        
      </div>
      
      <!-- Footer Actions -->
      <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
        <button type="button" onclick="closeEmailOrderModal()" style="padding: 0.75rem 1.5rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='white'">
          Cancel
        </button>
        <button type="submit" id="sendEmailOrderBtn" style="padding: 0.75rem 2rem; background: linear-gradient(135deg, #7194A5 0%, #5a7a8a 100%); border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 700; color: white; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(113,148,165,0.35); display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(113,148,165,0.45)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(113,148,165,0.35)'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
          </svg>
          Send Order
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Actions Dropdown Menu -->
<div id="actionsDropdown" style="display: none; position: absolute; background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); z-index: 10000; min-width: 220px;">
  <div style="padding: 0.5rem;">
    <button onclick="handleOrderAction('duplicate')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
      Duplicate Order
    </button>
    <button onclick="handleOrderAction('invoice')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12H15M9 16H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L18.7071 8.70711C18.8946 8.89464 19 9.149 19 9.41421V19C19 20.1046 18.1046 21 17 21Z"/></svg>
      Convert to Invoice
    </button>
    <button onclick="handleOrderAction('credit_note')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
      Create Credit Note
    </button>
    <button onclick="handleOrderAction('recurring')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.219-8.56"/><polyline points="17 8 17 4 13 4"/></svg>
      Add to Recurring
    </button>
    <button onclick="handleOrderAction('audit')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      View Audit Trail
    </button>
    <button onclick="handleOrderAction('attach')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
      Attach Documents
    </button>
    <div style="height: 1px; background: hsl(214 20% 88%); margin: 0.5rem 0;"></div>
    <button id="actionVoid" onclick="handleOrderAction('void')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(25 95% 45%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(48 96% 95%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
      Mark as Void
    </button>
    <button onclick="handleOrderAction('delete')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(0 74% 50%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(0 86% 97%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      Delete Order
    </button>
  </div>
</div>

<div id="newOrderModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); animation: fadeIn 0.2s ease; pointer-events: auto;">
  <div style="background: white; border-radius: 12px; width: 92%; max-width: 1140px; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); animation: slideUp 0.3s ease; display: flex; flex-direction: column; pointer-events: auto;">
    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid hsl(214 20% 92%); display: flex; align-items: center; justify-content: space-between; background: white; z-index: 10; flex-shrink: 0;">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(113,148,165,0.25);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M9 11L12 14L22 4M21 12V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H16"/></svg>
        </div>
        <div>
          <h2 style="font-size: 1.25rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0; letter-spacing: -0.02em;">Create New Order</h2>
          <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0; font-weight: 500;">Add line items and order details</p>
        </div>
      </div>
      <button onclick="closeNewOrderModal()" style="padding: 0.625rem; background: hsl(0 0% 98%); border: 1px solid hsl(214 20% 92%); cursor: pointer; border-radius: 8px; transition: all 0.2s; color: hsl(215 16% 47%);" onmouseover="this.style.background='hsl(0 0% 95%)'; this.style.borderColor='hsl(214 20% 85%)'" onmouseout="this.style.background='hsl(0 0% 98%)'; this.style.borderColor='hsl(214 20% 92%)'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M18 6L6 18M6 6L18 18" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
    
    <form id="newOrderForm" style="padding: 0; display: flex; flex-direction: column; flex: 1; min-height: 0;">
      <div id="orderGrid" style="display: grid; grid-template-columns: minmax(0,1fr) 340px; gap: 1.5rem; padding: 1.25rem 1.5rem; flex: 1; overflow: hidden; align-items: start;">
        <div style="display: flex; flex-direction: column; min-height: 0;">
          <div style="display: flex; background: hsl(240 5% 96%); border-bottom: 2px solid hsl(240 6% 90%); padding: 0.375rem; gap: 0.375rem; overflow-x: auto; flex-shrink: 0; margin-bottom: 1rem; border-radius: 10px;">
            <button type="button" class="order-tab-btn" data-tab="details" onclick="switchOrderTab('details')" style="padding: 0.625rem 1.125rem; border: none; background: white; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-weight: 600; cursor: pointer; white-space: nowrap; color: #7194A5; font-size: 0.8125rem; transition: all 0.2s;">📋 Details</button>
            <button type="button" class="order-tab-btn" data-tab="items" onclick="switchOrderTab('items')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">📦 Line Items</button>
            <button type="button" class="order-tab-btn" data-tab="party" onclick="switchOrderTab('party')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">👤 Customer & Notes</button>
          </div>
          <div id="order-tab-details" class="order-tab-content" style="padding: 1.25rem; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem; letter-spacing: -0.01em;">Order Number</label>
                <input type="text" name="order_number" value="ORD-<?php echo date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT); ?>" style="width: 100%; padding: 0.75rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-family: monospace; font-size: 0.875rem; transition: all 0.2s;">
              </div>
              <div>
                <label data-required="true" style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem; letter-spacing: -0.01em;">Order Date</label>
                <input type="date" name="date" style="width: 100%; padding: 0.75rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s;">
              </div>
              <div>
                <label data-required="true" style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem; letter-spacing: -0.01em;">Order Type</label>
                <select name="type" style="width: 100%; padding: 0.75rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s;">
                  <option value="">Select order type</option>
                  <option value="Sales">Sales Order</option>
                  <option value="Purchase">Purchase Order</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem; letter-spacing: -0.01em;">Status</label>
                <select name="status" style="width: 100%; padding: 0.75rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s;">
                  <option value="pending">Pending</option>
                  <option value="processing">Processing</option>
                  <option value="shipped">Shipped</option>
                  <option value="delivered">Delivered</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div>
                <label data-required="true" style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem; letter-spacing: -0.01em;">Customer/Vendor</label>
                <input type="text" name="customer" placeholder="Enter name" style="width: 100%; padding: 0.75rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem; letter-spacing: -0.01em;">Payment Terms</label>
                <select name="payment_terms" style="width: 100%; padding: 0.75rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s;">
                  <option value="net_30">Net 30</option>
                  <option value="net_15">Net 15</option>
                  <option value="due_on_receipt">Due on Receipt</option>
                  <option value="net_60">Net 60</option>
                  <option value="net_90">Net 90</option>
                </select>
              </div>
              <div style="grid-column: span 2;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem; letter-spacing: -0.01em;">Reference Number</label>
                <input type="text" name="reference" placeholder="PO-2024-001" style="width: 100%; padding: 0.75rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-family: monospace; font-size: 0.875rem; transition: all 0.2s;">
              </div>
            </div>
          </div>
          <div id="order-tab-items" class="order-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
              <div>
                <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 0.25rem 0; color: hsl(222 47% 17%);">Line Items</h3>
                <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0;">Add products or services to this order</p>
              </div>
              <button type="button" onclick="addLineItem()" style="background: #000000; color: white; border: none; padding: 0.625rem 1.125rem; border-radius: 7px; font-weight: 600; font-size: 0.8125rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='#1a1a1a'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='#000000'; this.style.transform='translateY(0)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
                Add Item
              </button>
            </div>
            <!-- Line Items Table Header -->
            <div id="lineItemsTableHeader" style="display: none; grid-template-columns: 1.8fr 0.7fr 1fr 1fr 48px; gap: 0.625rem; padding: 0.75rem 1rem; background: hsl(240 5% 96%); border: 1.5px solid hsl(214 20% 90%); border-radius: 8px 8px 0 0; margin-bottom: 0;">
              <div style="font-size: 0.75rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.025em;">Description</div>
              <div style="font-size: 0.75rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.025em; text-align: center;">Qty</div>
              <div style="font-size: 0.75rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.025em; text-align: right;">Unit Price</div>
              <div style="font-size: 0.75rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.025em; text-align: right;">Line Total</div>
              <div style="font-size: 0.75rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.025em; text-align: center;"></div>
            </div>
            <div id="lineItemsContainer" style="border: 1.5px solid hsl(214 20% 90%); border-top: none; border-radius: 0 0 8px 8px; background: white; display: none;"></div>
            <div id="lineItemsPagination" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: hsl(240 5% 96%); border: 1.5px solid hsl(214 20% 90%); border-radius: 8px; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button type="button" id="prevPageBtn" onclick="changePage(-1)" style="padding: 0.5rem 0.75rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 6px; cursor: pointer; font-size: 0.8125rem; font-weight: 600; color: hsl(222 47% 17%); transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 98%)'" onmouseout="this.style.background='white'">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <span id="pageInfo" style="font-size: 0.8125rem; color: hsl(215 16% 47%); font-weight: 500;">Page 1 of 1</span>
                <button type="button" id="nextPageBtn" onclick="changePage(1)" style="padding: 0.5rem 0.75rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 6px; cursor: pointer; font-size: 0.8125rem; font-weight: 600; color: hsl(222 47% 17%); transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 98%)'" onmouseout="this.style.background='white'">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                </button>
              </div>
              <span id="itemCount" style="font-size: 0.8125rem; color: hsl(215 16% 47%); font-weight: 500;">0 items</span>
            </div>
            <div id="emptyLineItemsMessage" style="padding: 2rem 1.5rem; text-align: center; border: 1.5px dashed hsl(214 20% 85%); border-radius: 8px; margin-top: 0.5rem; background: hsl(240 5% 98%);">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="hsl(215 16% 47%)" stroke-width="1.5" style="opacity: 0.3; margin: 0 auto 0.75rem;">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <line x1="9" y1="9" x2="15" y2="9"/>
                <line x1="9" y1="15" x2="15" y2="15"/>
              </svg>
              <p style="font-size: 0.875rem; color: hsl(215 16% 47%); margin: 0; font-weight: 500;">No items added yet. Click "Add Item" to get started.</p>
            </div>
          </div>
          <div id="order-tab-party" class="order-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <!-- Customer Information -->
            <div style="background: linear-gradient(135deg, hsl(0 0% 98%), hsl(0 0% 96%)); border: 1.5px solid hsl(0 0% 88%); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
              <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(0 0% 40%)" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
                <h4 style="font-size: 0.875rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Customer Information</h4>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                  <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Email</label>
                  <input type="email" name="email" placeholder="customer@example.com" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                </div>
                <div>
                  <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Phone</label>
                  <input type="tel" name="phone" placeholder="+63 900 000 0000" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                </div>
                <div style="grid-column: span 2;">
                  <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Company</label>
                  <input type="text" name="company" placeholder="Company name" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                </div>
              </div>
            </div>
            <!-- Addresses -->
            <div style="background: linear-gradient(135deg, hsl(0 0% 98%), hsl(0 0% 96%)); border: 1.5px solid hsl(0 0% 88%); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
              <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(0 0% 40%)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <h4 style="font-size: 0.875rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Addresses</h4>
              </div>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                  <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Shipping Address</label>
                  <textarea name="shipping_address" rows="3" placeholder="Street, City, State, ZIP" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
                </div>
                <div>
                  <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Billing Address</label>
                  <textarea name="billing_address" rows="3" placeholder="Street, City, State, ZIP" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
                </div>
              </div>
            </div>
            <!-- Notes -->
            <div style="background: linear-gradient(135deg, hsl(0 0% 98%), hsl(0 0% 96%)); border: 1.5px solid hsl(0 0% 88%); border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
              <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(0 0% 40%)" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
                <h4 style="font-size: 0.875rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Notes & Instructions</h4>
              </div>
              <textarea name="notes" rows="4" placeholder="Add delivery instructions, special requirements, or terms..." style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
            </div>
          </div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.875rem; min-height: 0; position: sticky; top: 0; align-self: start; height: fit-content;">
          <div style="background: white; border: 2px solid hsl(214 20% 90%); border-radius: 10px; padding: 1.125rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
            <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.875rem; padding-bottom: 0.875rem; border-bottom: 2px solid hsl(214 20% 92%);">
              <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 8px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(113,148,165,0.3);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M16 8V5a2 2 0 00-2-2H6a2 2 0 00-2 2v14a2 2 0 002 2h2"/><rect x="8" y="7" width="12" height="14" rx="2"/></svg>
              </div>
              <h3 style="font-size: 1rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Order Summary</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.625rem 0; gap: 0.75rem;">
                <span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem; display: flex; align-items: center; gap: 0.375rem; flex-shrink: 0;">
                  <span style="width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; color: hsl(215 16% 47%); font-family: monospace;"><?php echo CurrencyHelper::symbol(); ?></span>
                  Subtotal
                </span>
                <span id="subtotalDisplay" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.9375rem; font-family: monospace; text-align: right; word-break: break-word; max-width: 65%; overflow-wrap: break-word;"><?php echo CurrencyHelper::symbol(); ?>0.00</span>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.625rem 0; gap: 0.75rem;">
                <span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem; display: flex; align-items: center; gap: 0.375rem; flex-shrink: 0;">
                  <span style="width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; color: hsl(215 16% 47%); font-family: monospace;"><?php echo CurrencyHelper::symbol(); ?></span>
                  Tax (10%)
                </span>
                <span id="taxDisplay" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.9375rem; font-family: monospace; text-align: right; word-break: break-word; max-width: 65%; overflow-wrap: break-word;"><?php echo CurrencyHelper::symbol(); ?>0.00</span>
              </div>
              <div style="padding: 0.75rem; background: hsl(48 96% 97%); border: 1.5px solid hsl(48 96% 89%); border-radius: 8px; margin: 0.25rem 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.625rem; gap: 0.75rem;">
                  <label for="discountInput" style="color: hsl(25 95% 16%); font-weight: 600; font-size: 0.8125rem; display: flex; align-items: center; gap: 0.375rem; flex-shrink: 0;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9h.01M15 15h.01"/></svg>
                    Discount
                  </label>
                  <span id="discountAmountDisplay" style="color: hsl(0 74% 42%); font-size: 0.875rem; font-weight: 700; font-family: monospace; text-align: right; word-break: break-word; max-width: 55%; overflow-wrap: break-word;">-<?php echo CurrencyHelper::symbol(); ?>0.00</span>
                </div>
                <div style="position: relative;">
                  <input type="number" id="discountInput" min="0" max="100" step="0.1" value="" onchange="calculateTotals()" placeholder="Enter discount %" style="width: 100%; padding: 0.625rem 2.5rem 0.625rem 0.875rem; border: 1.5px solid hsl(25 95% 53%); border-radius: 6px; font-size: 0.875rem; font-weight: 600; background: white; transition: all 0.2s;">
                  <span style="position: absolute; right: 0.875rem; top: 50%; transform: translateY(-50%); color: hsl(25 95% 16%); font-weight: 700; font-size: 0.875rem; pointer-events: none;">%</span>
                </div>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 8px; margin-top: 0.5rem; box-shadow: 0 4px 12px rgba(113,148,165,0.35); gap: 0.75rem;">
                <span style="color: white; font-weight: 700; font-size: 0.9375rem; display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
                  <span style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.875rem; color: white; font-family: monospace;"><?php echo CurrencyHelper::symbol(); ?></span>
                  Total
                </span>
                <span id="totalDisplay" style="font-weight: 800; font-size: 1.125rem; color: white; font-family: monospace; text-align: right; word-break: break-word; max-width: 60%; overflow-wrap: break-word; line-height: 1.3;"><?php echo CurrencyHelper::symbol(); ?>0.00</span>
              </div>
            </div>
          </div>
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <button type="submit" style="width: 100%; background: #000000; color: white; border: none; padding: 0.875rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;" onmouseover="this.style.background='#1a1a1a'" onmouseout="this.style.background='#000000'">Create Order</button>
            <button type="button" onclick="closeNewOrderModal()" style="width: 100%; background: white; color: hsl(220 14% 11%); border: 1.5px solid hsl(214 20% 85%); padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.8125rem; transition: all 0.2s;" onmouseover="this.style.borderColor='hsl(214 20% 75%)'" onmouseout="this.style.borderColor='hsl(214 20% 85%)'">Cancel</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
let lineItemCounter = 1;
let lineItems = [];
let currentPage = 1;
const itemsPerPage = 3;
let initialFormState = null;

// Currency from settings
const ORDER_CURRENCY = '<?php echo CurrencyHelper::getCurrentCurrency(); ?>';
function getCurrencySymbol(currencyCode) {
  const symbols = {
    'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥', 'CNY': '¥',
    'PHP': '₱', 'SGD': 'S$', 'HKD': 'HK$', 'THB': '฿', 'MYR': 'RM',
    'IDR': 'Rp', 'VND': '₫', 'KRW': '₩', 'INR': '₹',
    'AUD': 'A$', 'CAD': 'C$', 'CHF': 'Fr', 'NZD': 'NZ$',
    'SEK': 'kr', 'NOK': 'kr', 'DKK': 'kr',
    'AED': 'د.إ', 'SAR': '﷼', 'ZAR': 'R',
    'MXN': '$', 'BRL': 'R$', 'ARS': '$'
  };
  return symbols[currencyCode] || currencyCode;
}
const ORDER_CURRENCY_SYMBOL = getCurrencySymbol(ORDER_CURRENCY);

// Table sorting and pagination
const originalOrdersData = <?php echo json_encode($orders); ?>; // Immutable original data
let ordersData = [...originalOrdersData]; // Working copy for display
let tableSortColumn = null;
let tableSortDirection = 'asc';
let tableCurrentPage = 1;
const tableItemsPerPage = 6;

function sortTable(column) {
  console.log('📊 Sorting by:', column);
  
  // Toggle direction if same column
  if (tableSortColumn === column) {
    tableSortDirection = tableSortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    tableSortColumn = column;
    tableSortDirection = 'asc';
  }
  
  // Sort the data
  ordersData.sort((a, b) => {
    let valA, valB;
    
    switch(column) {
      case 'order_number':
        valA = (a.order_number || a.id || '').toString().toLowerCase();
        valB = (b.order_number || b.id || '').toString().toLowerCase();
        break;
      case 'type':
        valA = (a.type || '').toString().toLowerCase();
        valB = (b.type || '').toString().toLowerCase();
        break;
      case 'customer':
        valA = (a.customer || '').toString().toLowerCase();
        valB = (b.customer || '').toString().toLowerCase();
        break;
      case 'date':
        valA = new Date(a.date || 0).getTime();
        valB = new Date(b.date || 0).getTime();
        break;
      case 'due_date':
        valA = new Date(a.due_date || a.date || 0).getTime();
        valB = new Date(b.due_date || b.date || 0).getTime();
        break;
      case 'total':
        valA = parseFloat(a.total || 0);
        valB = parseFloat(b.total || 0);
        break;
      case 'payment':
        const paidA = parseFloat(a.amount_paid || 0);
        const paidB = parseFloat(b.amount_paid || 0);
        const totalA = parseFloat(a.total || 0);
        const totalB = parseFloat(b.total || 0);
        valA = totalA > 0 ? (paidA / totalA) * 100 : 0;
        valB = totalB > 0 ? (paidB / totalB) * 100 : 0;
        break;
      case 'status':
        valA = (a.status || '').toString().toLowerCase();
        valB = (b.status || '').toString().toLowerCase();
        break;
      default:
        return 0;
    }
    
    if (valA < valB) return tableSortDirection === 'asc' ? -1 : 1;
    if (valA > valB) return tableSortDirection === 'asc' ? 1 : -1;
    return 0;
  });
  
  // Reset to page 1 and render
  tableCurrentPage = 1;
  renderTable();
  Toast.info(`Sorted by ${column} (${tableSortDirection})`);
}

function renderTable() {
  const tbody = document.getElementById('ordersTableBody');
  const startIndex = (tableCurrentPage - 1) * tableItemsPerPage;
  const endIndex = startIndex + tableItemsPerPage;
  const paginatedData = ordersData.slice(startIndex, endIndex);
  
  // Clear table
  tbody.innerHTML = '';
  
  // Render rows
  paginatedData.forEach(order => {
    const row = createOrderRow(order);
    tbody.appendChild(row);
  });
  
  updatePaginationInfo();
}

function createOrderRow(order) {
  const tr = document.createElement('tr');
  tr.setAttribute('data-order-id', order.id);
  
  // Calculate payment percentage
  const paid = parseFloat(order.amount_paid || 0);
  const total = parseFloat(order.total || 0);
  const percentage = total > 0 ? (paid / total) * 100 : 0;
  const statusColor = percentage >= 100 ? 'hsl(140 61% 13%)' : (percentage > 0 ? 'hsl(25 95% 16%)' : 'hsl(215 16% 47%)');
  
  // Format dates
  const orderDate = new Date(order.date);
  const dueDate = order.due_date ? new Date(order.due_date) : new Date(orderDate.getTime() + 30 * 24 * 60 * 60 * 1000);
  const formatDate = (d) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  
  // Status badges
  const badges = {
    'draft': 'badge-secondary',
    'pending': 'badge-warning',
    'processing': 'badge-default',
    'shipped': 'badge-success',
    'received': 'badge-success'
  };
  const statusBadge = badges[order.status] || 'badge-default';
  
  // Format currency
  const formatCurrency = (amount) => {
    return ORDER_CURRENCY_SYMBOL + parseFloat(amount || 0).toFixed(2);
  };
  
  tr.innerHTML = `
    <td class="checkbox-column" style="display: none;">
      <input type="checkbox" class="order-checkbox" value="${order.id}" onchange="updateBatchToolbar()" style="cursor: pointer;">
    </td>
    <td class="font-mono font-medium">${order.order_number || order.id}</td>
    <td><span class="badge ${order.type === 'Sales' ? 'badge-success' : 'badge-default'}">${order.type}</span></td>
    <td class="font-medium">${order.customer || ''}</td>
    <td>${formatDate(orderDate)}</td>
    <td>${formatDate(dueDate)}</td>
    <td class="font-semibold">${formatCurrency(order.total)}</td>
    <td>
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <div style="width: 60px; height: 6px; background: hsl(214 20% 92%); border-radius: 3px; overflow: hidden;">
          <div style="width: ${percentage}%; height: 100%; background: ${statusColor}; transition: width 0.3s;"></div>
        </div>
        <span style="font-size: 0.75rem; color: ${statusColor}; font-weight: 600;">${Math.round(percentage)}%</span>
      </div>
    </td>
    <td>
      <span class="badge ${statusBadge}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span>
    </td>
    <td>
      <div class="flex gap-1">
        <button class="btn btn-ghost btn-sm" onclick="viewOrder('${order.id}')" title="View Details">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
          </svg>
        </button>
        <button class="btn btn-ghost btn-sm" onclick="recordPayment('${order.id}')" title="Record Payment">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="5" width="20" height="14" rx="2"/>
            <path d="M2 10h20"/>
          </svg>
        </button>
        <button class="btn btn-ghost btn-sm" onclick="printOrder('${order.id}')" title="Print/PDF">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M6 9V2H18V9M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18M6 14H18V22H6V14Z" stroke="currentColor" stroke-width="2"/>
          </svg>
        </button>
        <button class="btn btn-ghost btn-sm" onclick="emailOrder('${order.id}')" title="Email">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <path d="M22 6l-10 7L2 6"/>
          </svg>
        </button>
        <button class="btn btn-ghost btn-sm" onclick="showMoreActions('${order.id}', '${order.status}')" title="More Actions">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="1" fill="currentColor"/>
            <circle cx="12" cy="5" r="1" fill="currentColor"/>
            <circle cx="12" cy="19" r="1" fill="currentColor"/>
          </svg>
        </button>
      </div>
    </td>
  `;
  
  return tr;
}

function updatePaginationInfo() {
  const totalOrders = ordersData.length;
  const startIndex = (tableCurrentPage - 1) * tableItemsPerPage + 1;
  const endIndex = Math.min(tableCurrentPage * tableItemsPerPage, totalOrders);
  const totalPages = Math.ceil(totalOrders / tableItemsPerPage);
  
  // Update info text
  document.getElementById('paginationInfo').textContent = `${startIndex}-${endIndex} of ${totalOrders}`;
  
  // Update buttons
  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');
  
  prevBtn.disabled = tableCurrentPage === 1;
  prevBtn.style.opacity = tableCurrentPage === 1 ? '0.5' : '1';
  prevBtn.style.cursor = tableCurrentPage === 1 ? 'not-allowed' : 'pointer';
  
  nextBtn.disabled = tableCurrentPage === totalPages;
  nextBtn.style.opacity = tableCurrentPage === totalPages ? '0.5' : '1';
  nextBtn.style.cursor = tableCurrentPage === totalPages ? 'not-allowed' : 'pointer';
  
  // Render page numbers
  renderPageNumbers(totalPages);
  
  // Hide pagination if only 1 page
  const paginationContainer = document.getElementById('paginationContainer');
  if (totalOrders <= tableItemsPerPage) {
    paginationContainer.style.display = 'none';
  } else {
    paginationContainer.style.display = 'flex';
  }
}

function renderPageNumbers(totalPages) {
  const pageNumbers = document.getElementById('pageNumbers');
  pageNumbers.innerHTML = '';
  
  // Show max 5 page numbers
  let startPage = Math.max(1, tableCurrentPage - 2);
  let endPage = Math.min(totalPages, startPage + 4);
  
  if (endPage - startPage < 4) {
    startPage = Math.max(1, endPage - 4);
  }
  
  for (let i = startPage; i <= endPage; i++) {
    const btn = document.createElement('button');
    btn.textContent = i;
    btn.onclick = () => goToPage(i);
    btn.style.cssText = `
      padding: 0.5rem 0.75rem;
      border: 1px solid hsl(214 20% 88%);
      background: ${i === tableCurrentPage ? 'hsl(222 47% 17%)' : 'white'};
      color: ${i === tableCurrentPage ? 'white' : 'hsl(222 47% 17%)'};
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.875rem;
      font-weight: 600;
      min-width: 36px;
      transition: all 0.2s;
    `;
    
    if (i !== tableCurrentPage) {
      btn.onmouseover = () => btn.style.background = 'hsl(210 20% 98%)';
      btn.onmouseout = () => btn.style.background = 'white';
    }
    
    pageNumbers.appendChild(btn);
  }
}

function changePage(direction) {
  const totalPages = Math.ceil(ordersData.length / tableItemsPerPage);
  const newPage = tableCurrentPage + direction;
  
  if (newPage >= 1 && newPage <= totalPages) {
    tableCurrentPage = newPage;
    renderTable();
  }
}

function goToPage(page) {
  tableCurrentPage = page;
  renderTable();
}

// Search functionality
let searchQuery = '';
let typeFilter = 'all';
let statusFilter = 'all';

function searchOrders() {
  searchQuery = document.getElementById('order-search').value.toLowerCase().trim();
  applyFilters();
}

function applyFilters() {
  typeFilter = document.getElementById('type-filter').value;
  statusFilter = document.getElementById('status-filter').value;
  searchQuery = document.getElementById('order-search').value.toLowerCase().trim();
  
  console.log('🔍 Applying filters:', { searchQuery, typeFilter, statusFilter });
  
  // Always filter from the original immutable data
  const filtered = originalOrdersData.filter(order => {
    // Type filter
    if (typeFilter !== 'all' && order.type !== typeFilter) return false;
    
    // Status filter
    if (statusFilter !== 'all' && order.status !== statusFilter) return false;
    
    // Search filter
    if (searchQuery) {
      const searchableText = [
        order.order_number || '',
        order.customer || '',
        order.id || '',
        order.type || '',
        order.status || ''
      ].join(' ').toLowerCase();
      
      if (!searchableText.includes(searchQuery)) return false;
    }
    
    return true;
  });
  
  // Update working copy with filtered results
  ordersData = [...filtered];
  
  console.log('📊 Filtered results:', filtered.length, 'of', originalOrdersData.length);
  
  // Reset to page 1 and render
  tableCurrentPage = 1;
  renderTable();
  
  // Show toast if no results
  if (filtered.length === 0 && (searchQuery || typeFilter !== 'all' || statusFilter !== 'all')) {
    Toast.info('No orders found matching your criteria');
  } else if (filtered.length > 0 && (searchQuery || typeFilter !== 'all' || statusFilter !== 'all')) {
    Toast.success(`Found ${filtered.length} order${filtered.length === 1 ? '' : 's'}`);
  }
}

// Initialize table on page load
document.addEventListener('DOMContentLoaded', function() {
  if (ordersData.length > 0) {
    renderTable();
  }
});

function showNewOrderModal() {
  const modal = document.getElementById('newOrderModal');
  modal.style.display = 'flex';
  document.getElementById('newOrderForm').reset();
  const today = new Date().toISOString().split('T')[0];
  document.querySelector('input[name="date"]').value = today;
  
  // Reset line items and show empty state
  document.getElementById('lineItemsContainer').innerHTML = '';
  lineItems.length = 0;
  lineItemCounter = 1;
  currentPage = 1;
  
  // Show empty state, hide table
  const emptyMsg = document.getElementById('emptyLineItemsMessage');
  const tableHeader = document.getElementById('lineItemsTableHeader');
  const container = document.getElementById('lineItemsContainer');
  const pagination = document.getElementById('lineItemsPagination');
  
  if (emptyMsg) emptyMsg.style.display = 'block';
  if (tableHeader) tableHeader.style.display = 'none';
  if (container) container.style.display = 'none';
  if (pagination) pagination.style.display = 'none';
  
  switchOrderTab('details');
  applyOrderResponsive();
  
  // Capture initial form state after reset and auto-fill
  setTimeout(() => {
    const form = document.getElementById('newOrderForm');
    initialFormState = new FormData(form);
  }, 100);
}

function closeNewOrderModal(skipConfirmation = false) {
  // If skipConfirmation is true (e.g., after successful save), just close
  if (skipConfirmation) {
    document.getElementById('newOrderModal').style.display = 'none';
    initialFormState = null;
    return;
  }
  
  const form = document.getElementById('newOrderForm');
  const currentFormData = new FormData(form);
  
  // Check if form has changed from initial state
  let hasChanges = false;
  
  // If no initial state captured, just close
  if (!initialFormState) {
    document.getElementById('newOrderModal').style.display = 'none';
    return;
  }
  
  // Compare current form data with initial state
  for (let [key, value] of currentFormData.entries()) {
    const initialValue = initialFormState.get(key);
    const currentValue = value ? value.toString().trim() : '';
    const initialVal = initialValue ? initialValue.toString().trim() : '';
    
    // If value has changed from initial state
    if (currentValue !== initialVal) {
      hasChanges = true;
      break;
    }
  }
  
  // Also check line items
  if (lineItems.length > 0) {
    hasChanges = true;
  }
  
  // If has changes, confirm before closing
  if (hasChanges) {
    showDraftConfirmation();
  } else {
    document.getElementById('newOrderModal').style.display = 'none';
    initialFormState = null;
  }
}

function showDraftConfirmation() {
  // Create confirmation modal overlay
  const overlay = document.createElement('div');
  overlay.id = 'draftConfirmOverlay';
  overlay.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px); animation: fadeIn 0.2s ease; pointer-events: auto;';
  
  // Create confirmation dialog
  const dialog = document.createElement('div');
  dialog.style.cssText = 'background: white; border-radius: 12px; padding: 1.75rem; max-width: 420px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); animation: slideUp 0.3s ease; pointer-events: auto;';
  
  dialog.innerHTML = `
    <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem;">
      <div style="width: 40px; height: 40px; background: hsl(48 96% 89%); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2.5">
          <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div style="flex: 1;">
        <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 0.5rem 0; color: hsl(222 47% 17%); letter-spacing: -0.02em;">Unsaved Changes</h3>
        <p style="font-size: 0.875rem; color: hsl(215 16% 47%); margin: 0; line-height: 1.5;">You have unsaved changes. Would you like to save this order as a draft before closing?</p>
      </div>
    </div>
    <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
      <button id="draftDiscardBtn" style="padding: 0.75rem 1.25rem; background: white; color: hsl(220 14% 11%); border: 1.5px solid hsl(214 20% 85%); border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;">
        Discard
      </button>
      <button id="draftSaveBtn" style="padding: 0.75rem 1.25rem; background: #000000; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;">
        Save as Draft
      </button>
    </div>
  `;
  
  overlay.appendChild(dialog);
  document.body.appendChild(overlay);
  
  // Add event listeners
  document.getElementById('draftSaveBtn').addEventListener('click', () => {
    overlay.remove();
    saveAsDraft();
  });
  
  document.getElementById('draftDiscardBtn').addEventListener('click', () => {
    overlay.remove();
    document.getElementById('newOrderModal').style.display = 'none';
    initialFormState = null;
  });
  
  // Close on overlay click
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      overlay.remove();
    }
  });
  
  // Add hover effects
  const saveBtn = document.getElementById('draftSaveBtn');
  const discardBtn = document.getElementById('draftDiscardBtn');
  
  saveBtn.addEventListener('mouseenter', () => saveBtn.style.background = '#1a1a1a');
  saveBtn.addEventListener('mouseleave', () => saveBtn.style.background = '#000000');
  
  discardBtn.addEventListener('mouseenter', () => discardBtn.style.borderColor = 'hsl(214 20% 75%)');
  discardBtn.addEventListener('mouseleave', () => discardBtn.style.borderColor = 'hsl(214 20% 85%)');
}

function switchOrderTab(name) {
  const tabs = document.querySelectorAll('.order-tab-content');
  const btns = document.querySelectorAll('.order-tab-btn');
  tabs.forEach(t => { t.style.display = t.id === `order-tab-${name}` ? 'block' : 'none'; });
  btns.forEach(b => {
    b.style.padding = '0.625rem 1.125rem';
    b.style.borderRadius = '6px';
    b.style.transition = 'all 0.2s';
    if (b.getAttribute('data-tab') === name) {
      b.style.background = 'white';
      b.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
      b.style.color = '#7194A5';
      b.style.fontWeight = '600';
    } else {
      b.style.background = 'transparent';
      b.style.boxShadow = 'none';
      b.style.color = 'hsl(215 16% 47%)';
      b.style.fontWeight = '500';
    }
  });
}

function applyOrderResponsive() {
  try {
    const grid = document.getElementById('orderGrid');
    if (!grid) return;
    if (window.innerWidth < 1200) {
      grid.style.gridTemplateColumns = '1fr';
    } else {
      grid.style.gridTemplateColumns = 'minmax(0,1fr) 310px';
    }
    grid.style.gap = '1.25rem';
    grid.style.padding = '1.25rem 1.5rem';
  } catch {}
}

function addLineItem() {
  const itemId = lineItemCounter++;
  const container = document.getElementById('lineItemsContainer');
  const emptyMsg = document.getElementById('emptyLineItemsMessage');
  const tableHeader = document.getElementById('lineItemsTableHeader');
  
  // Show table header and container, hide empty message
  if (emptyMsg) emptyMsg.style.display = 'none';
  if (tableHeader) tableHeader.style.display = 'grid';
  if (container) container.style.display = 'block';
  
  const itemDiv = document.createElement('div');
  itemDiv.id = `lineItem-${itemId}`;
  itemDiv.className = 'line-item-card';
  itemDiv.style.cssText = 'display: grid; grid-template-columns: 1.8fr 0.7fr 1fr 1fr 48px; gap: 0.625rem; padding: 0.875rem 1rem; background: white; border-bottom: 1px solid hsl(214 20% 92%); transition: all 0.2s;';
  
  itemDiv.innerHTML = `
    <input type="text" placeholder="e.g. Website Development" class="item-description" style="width: 100%; padding: 0.625rem 0.75rem; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; transition: all 0.2s; background: white; box-sizing: border-box;" oninput="calculateTotals()">
    <input type="number" placeholder="1" class="item-quantity" min="1" value="1" style="width: 100%; padding: 0.625rem 0.5rem; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; text-align: center; transition: all 0.2s; box-sizing: border-box;" oninput="calculateTotals()">
    <input type="number" placeholder="0.00" class="item-price" min="0" step="0.01" value="0" style="width: 100%; padding: 0.625rem 0.5rem; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-family: monospace; text-align: right; transition: all 0.2s; box-sizing: border-box;" oninput="calculateTotals()">
    <div class="item-total" style="width: 100%; padding: 0.625rem 0.5rem; background: hsl(143 85% 96%); border: 1.5px solid hsl(143 70% 90%); border-radius: 6px; font-weight: 700; color: hsl(140 61% 13%); text-align: right; display: flex; align-items: center; justify-content: flex-end; font-family: monospace; font-size: 0.875rem; box-sizing: border-box;">${ORDER_CURRENCY_SYMBOL}0.00</div>
    <button type="button" onclick="removeLineItem(${itemId})" title="Remove item" style="padding: 0.5rem; background: transparent; color: hsl(0 74% 42%); border: 1.5px solid transparent; border-radius: 6px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='hsl(0 86% 97%)'; this.style.borderColor='hsl(0 86% 92%)'" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
    </button>
  `;
  
  container.appendChild(itemDiv);
  lineItems.push(itemId);
  updatePagination();
  calculateTotals();
}

function removeLineItem(itemId) {
  const element = document.getElementById(`lineItem-${itemId}`);
  const emptyMsg = document.getElementById('emptyLineItemsMessage');
  const tableHeader = document.getElementById('lineItemsTableHeader');
  const container = document.getElementById('lineItemsContainer');
  const pagination = document.getElementById('lineItemsPagination');
  
  if (element) {
    element.remove();
    const index = lineItems.indexOf(itemId);
    if (index > -1) lineItems.splice(index, 1);
  }
  
  // Show/hide elements based on item count
  if (lineItems.length === 0) {
    if (emptyMsg) emptyMsg.style.display = 'block';
    if (tableHeader) tableHeader.style.display = 'none';
    if (container) container.style.display = 'none';
    if (pagination) pagination.style.display = 'none';
  } else {
    updatePagination();
  }
  
  calculateTotals();
}

function updatePagination() {
  const totalItems = lineItems.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  const pagination = document.getElementById('lineItemsPagination');
  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');
  const pageInfo = document.getElementById('pageInfo');
  const itemCount = document.getElementById('itemCount');
  
  // Show/hide pagination if more than 3 items
  if (totalItems > itemsPerPage) {
    if (pagination) pagination.style.display = 'flex';
  } else {
    if (pagination) pagination.style.display = 'none';
    currentPage = 1;
  }
  
  // Update page info
  if (pageInfo) pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
  if (itemCount) itemCount.textContent = `${totalItems} item${totalItems !== 1 ? 's' : ''}`;
  
  // Update button states
  if (prevBtn) {
    prevBtn.disabled = currentPage === 1;
    prevBtn.style.opacity = currentPage === 1 ? '0.5' : '1';
    prevBtn.style.cursor = currentPage === 1 ? 'not-allowed' : 'pointer';
  }
  if (nextBtn) {
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.style.opacity = currentPage === totalPages ? '0.5' : '1';
    nextBtn.style.cursor = currentPage === totalPages ? 'not-allowed' : 'pointer';
  }
  
  // Show/hide items based on current page
  lineItems.forEach((itemId, index) => {
    const itemEl = document.getElementById(`lineItem-${itemId}`);
    if (itemEl) {
      const itemPage = Math.floor(index / itemsPerPage) + 1;
      itemEl.style.display = itemPage === currentPage ? 'grid' : 'none';
    }
  });
}

function changePage(direction) {
  const totalPages = Math.ceil(lineItems.length / itemsPerPage);
  const newPage = currentPage + direction;
  
  if (newPage >= 1 && newPage <= totalPages) {
    currentPage = newPage;
    updatePagination();
  }
}

// Format large numbers with abbreviations (K, M, B, T)
function formatCurrency(amount) {
  const absAmount = Math.abs(amount);
  
  // For very large numbers, use abbreviations
  if (absAmount >= 1e12) {
    return ORDER_CURRENCY_SYMBOL + (amount / 1e12).toFixed(2) + 'T';
  } else if (absAmount >= 1e9) {
    return ORDER_CURRENCY_SYMBOL + (amount / 1e9).toFixed(2) + 'B';
  } else if (absAmount >= 1e6) {
    return ORDER_CURRENCY_SYMBOL + (amount / 1e6).toFixed(2) + 'M';
  } else if (absAmount >= 1e4) {
    return ORDER_CURRENCY_SYMBOL + (amount / 1e3).toFixed(2) + 'K';
  } else {
    // For normal numbers, use comma formatting
    return ORDER_CURRENCY_SYMBOL + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }
}

function calculateTotals() {
  let subtotal = 0;
  
  lineItems.forEach(itemId => {
    const itemEl = document.getElementById(`lineItem-${itemId}`);
    if (itemEl) {
      const qty = parseFloat(itemEl.querySelector('.item-quantity').value) || 0;
      const price = parseFloat(itemEl.querySelector('.item-price').value) || 0;
      const total = qty * price;
      itemEl.querySelector('.item-total').textContent = formatCurrency(total);
      subtotal += total;
    }
  });
  
  const tax = subtotal * 0.10;
  const discountPercent = parseFloat(document.getElementById('discountInput')?.value) || 0;
  const discountAmount = subtotal * (discountPercent / 100);
  const grandTotal = subtotal + tax - discountAmount;
  
  // Use formatted currency with abbreviations for large numbers
  document.getElementById('subtotalDisplay').textContent = formatCurrency(subtotal);
  document.getElementById('taxDisplay').textContent = formatCurrency(tax);
  document.getElementById('discountAmountDisplay').textContent = '-' + formatCurrency(discountAmount);
  document.getElementById('totalDisplay').textContent = formatCurrency(grandTotal);
}

async function saveAsDraft() {
  const form = document.getElementById('newOrderForm');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData);
  
  // Validate minimum required fields for draft
  if (!data.order_number || !data.date) {
    Toast.warning('Please provide at least Order Number and Date to save as draft');
    return;
  }
  
  data.status = 'draft';
  data.items = getLineItemsData();
  data.discount_percent = parseFloat(document.getElementById('discountInput').value) || 0;
  
  // Show loading
  const draftBtn = form.querySelector('[onclick="saveAsDraft()"]');
  const originalText = draftBtn.textContent;
  draftBtn.disabled = true;
  draftBtn.textContent = 'Saving...';
  
  try {
    const response = await fetch('api/orders.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      Toast.info(`Draft saved successfully! Order #${result.order_number}`);
      closeNewOrderModal(true);
      
      // Reload page to show draft order
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      Toast.error(result.message || 'Failed to save draft');
      draftBtn.disabled = false;
      draftBtn.textContent = originalText;
    }
  } catch (error) {
    console.error('Save draft error:', error);
    Toast.error('Network error. Please try again.');
    draftBtn.disabled = false;
    draftBtn.textContent = originalText;
  }
}

function getLineItemsData() {
  const items = [];
  lineItems.forEach(itemId => {
    const itemEl = document.getElementById(`lineItem-${itemId}`);
    if (itemEl) {
      const totalText = itemEl.querySelector('.item-total').textContent || '';
      const numeric = totalText.replace(/[^0-9.\-]/g, '');
      items.push({
        description: itemEl.querySelector('.item-description').value,
        quantity: parseFloat(itemEl.querySelector('.item-quantity').value) || 0,
        price: parseFloat(itemEl.querySelector('.item-price').value) || 0,
        total: parseFloat(numeric) || 0
      });
    }
  });
  return items;
}

document.getElementById('newOrderForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  
  // Custom validation with tab switching
  const orderDate = this.querySelector('[name="date"]');
  const orderType = this.querySelector('[name="type"]');
  const customer = this.querySelector('[name="customer"]');
  
  // Check Details tab fields
  if (!orderDate.value) {
    switchOrderTab('details');
    orderDate.focus();
    Toast.error('Please select an order date');
    return;
  }
  
  if (!orderType.value) {
    switchOrderTab('details');
    orderType.focus();
    Toast.error('Please select an order type');
    return;
  }
  
  if (!customer.value.trim()) {
    switchOrderTab('details');
    customer.focus();
    Toast.error('Please enter customer/vendor name');
    return;
  }
  
  // Validate line items
  const items = getLineItemsData();
  if (items.length === 0) {
    switchOrderTab('items');
    Toast.warning('Please add at least one line item');
    return;
  }
  
  if (items.every(item => !item.description || !item.description.trim())) {
    switchOrderTab('items');
    Toast.error('Please add a description for your line items');
    return;
  }
  
  // Prepare data
  const formData = new FormData(this);
  const data = Object.fromEntries(formData);
  data.items = items;
  data.discount_percent = parseFloat(document.getElementById('discountInput').value) || 0;
  
  // Show loading state
  const submitBtn = this.querySelector('[type="submit"]');
  const originalText = submitBtn.textContent;
  submitBtn.disabled = true;
  submitBtn.textContent = 'Creating...';
  
  try {
    const response = await fetch('api/orders.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      Toast.success(`Order created successfully! Order #${result.order_number}`);
      closeNewOrderModal(true);
      
      // Reload page to show new order
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      Toast.error(result.message || 'Failed to create order');
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  } catch (error) {
    console.error('Order creation error:', error);
    Toast.error('Network error. Please try again.');
    submitBtn.disabled = false;
    submitBtn.textContent = originalText;
  }
});

document.getElementById('newOrderModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeNewOrderModal();
  }
});

document.getElementById('viewOrderModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeViewOrderModal();
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const newOrderModal = document.getElementById('newOrderModal');
    const viewModal = document.getElementById('viewOrderModal');
    
    if (viewModal && viewModal.style.display === 'flex') {
      closeViewOrderModal();
    } else if (newOrderModal && newOrderModal.style.display === 'flex') {
      closeNewOrderModal();
    }
  }
});
window.addEventListener('resize', applyOrderResponsive);

// ============================================
// APPLY NUMBER FORMAT API TO CURRENCY VALUES
// ============================================
window.addEventListener('load', function() {
  const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';
  
  // Auto-apply formatting to order values
  NumberFormat.autoApply(currencySymbol, {
    customSelectors: [
      { selector: '#totalValue', maxWidth: 1 },  // Always abbreviate >= 1M
      { selector: 'td.font-semibold', maxWidth: 80 }  // Table total column
    ]
  });
});

// ============================================
// ERP FEATURES
// ============================================

// Batch Operations
function toggleSelectAll(checkbox) {
  const checkboxes = document.querySelectorAll('.order-checkbox');
  checkboxes.forEach(cb => cb.checked = checkbox.checked);
  updateBatchToolbar();
}

function updateBatchToolbar() {
  const checkboxes = document.querySelectorAll('.order-checkbox:checked');
  const count = checkboxes.length;
  const toolbar = document.getElementById('batchToolbar');
  const selectedCount = document.getElementById('selectedCount');
  const selectAll = document.getElementById('selectAll');
  const total = document.querySelectorAll('.order-checkbox').length;
  
  // Update select all checkbox state
  if (count === 0) {
    selectAll.checked = false;
    selectAll.indeterminate = false;
  } else if (count === total) {
    selectAll.checked = true;
    selectAll.indeterminate = false;
  } else {
    selectAll.checked = false;
    selectAll.indeterminate = true;
  }
  
  // Show/hide toolbar based on selection
  if (count > 0) {
    toolbar.style.display = 'block';
    selectedCount.textContent = `${count} selected`;
  } else {
    toolbar.style.display = 'none';
  }
}

function clearSelection() {
  document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = false);
  const selectAll = document.getElementById('selectAll');
  selectAll.checked = false;
  selectAll.indeterminate = false;
  updateBatchToolbar();
}

function showBatchOperations() {
  toggleCheckboxes();
  const checkboxColumns = document.querySelectorAll('.checkbox-column');
  const isVisible = checkboxColumns[0].style.display !== 'none';
  if (isVisible) {
    Toast.info('Select orders to perform batch operations');
  }
}

function toggleCheckboxes() {
  const checkboxColumns = document.querySelectorAll('.checkbox-column');
  const firstColumn = checkboxColumns[0];
  const isCurrentlyHidden = firstColumn.style.display === 'none';
  
  checkboxColumns.forEach(col => {
    col.style.display = isCurrentlyHidden ? '' : 'none';
  });
  
  // If hiding checkboxes, clear selections and hide toolbar
  if (!isCurrentlyHidden) {
    clearSelection();
  }
}

function getSelectedOrders() {
  const checkboxes = document.querySelectorAll('.order-checkbox:checked');
  return Array.from(checkboxes).map(cb => cb.value);
}

async function batchUpdateStatus() {
  const selected = getSelectedOrders();
  if (selected.length === 0) {
    Toast.warning('Please select orders first');
    return;
  }
  
  const status = prompt('Enter new status (pending, processing, shipped, delivered, cancelled):');
  if (!status) return;
  
  Toast.info(`Updating ${selected.length} orders to ${status}...`);
  // TODO: Implement API call
  setTimeout(() => {
    Toast.success(`Updated ${selected.length} orders successfully`);
    clearSelection();
  }, 1000);
}

async function batchDelete() {
  const selected = getSelectedOrders();
  if (selected.length === 0) {
    Toast.warning('Please select orders first');
    return;
  }
  
  if (!confirm(`Are you sure you want to delete ${selected.length} order(s)? This action cannot be undone.`)) {
    return;
  }
  
  Toast.info(`Deleting ${selected.length} orders...`);
  // TODO: Implement API call
  setTimeout(() => {
    Toast.success(`Deleted ${selected.length} orders successfully`);
    clearSelection();
    window.location.reload();
  }, 1000);
}

async function batchExport() {
  const selected = getSelectedOrders();
  if (selected.length === 0) {
    Toast.warning('Please select orders first');
    return;
  }
  
  Toast.info(`Exporting ${selected.length} orders...`);
  // TODO: Implement CSV export
  setTimeout(() => {
    Toast.success(`Export ready for ${selected.length} orders`);
  }, 1000);
}

// Export Menu
function showExportMenu() {
  const options = ['Export as CSV', 'Export as PDF', 'Export as Excel', 'Export Selected'];
  const choice = prompt('Export Options:\\n1. CSV (All)\\n2. PDF (All)\\n3. Excel (All)\\n4. Selected Only\\n\\nEnter choice (1-4):');
  
  if (choice === '1') {
    exportToCSV();
  } else if (choice === '2') {
    exportToPDF();
  } else if (choice === '3') {
    exportToExcel();
  } else if (choice === '4') {
    batchExport();
  }
}

function exportToCSV() {
  Toast.info('Generating CSV export...');
  // TODO: Implement CSV export
  setTimeout(() => Toast.success('CSV export ready'), 1000);
}

function exportToPDF() {
  Toast.info('Generating PDF export...');
  // TODO: Implement PDF export
  setTimeout(() => Toast.success('PDF export ready'), 1000);
}

function exportToExcel() {
  Toast.info('Generating Excel export...');
  // TODO: Implement Excel export
  setTimeout(() => Toast.success('Excel export ready'), 1000);
}

// Order Actions
async function viewOrder(orderId) {
  Toast.info('Loading order details...');
  
  try {
    const response = await fetch(`api/orders.php?id=${orderId}`);
    const result = await response.json();
    
    if (result.success && result.data) {
      const order = result.data;
      populateViewOrderModal(order, orderId);
      document.getElementById('viewOrderModal').style.display = 'flex';
    } else {
      Toast.error(result.message || 'Failed to load order details');
    }
  } catch (error) {
    console.error('Error loading order:', error);
    Toast.error('Network error loading order details');
  }
}

function populateViewOrderModal(order, orderId) {
  // Basic info
  document.getElementById('viewOrderNumber').textContent = order.order_number || 'N/A';
  document.getElementById('viewOrderCustomer').textContent = order.customer || 'N/A';
  document.getElementById('viewOrderDate').textContent = new Date(order.date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  
  // Due date
  const dueDate = order.due_date || new Date(new Date(order.date).getTime() + 30*24*60*60*1000).toISOString().split('T')[0];
  document.getElementById('viewOrderDueDate').textContent = new Date(dueDate).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  
  // Payment terms
  const termsMap = { 'net_30': 'Net 30', 'net_15': 'Net 15', 'due_on_receipt': 'Due on Receipt', 'net_60': 'Net 60', 'net_90': 'Net 90' };
  document.getElementById('viewOrderTerms').textContent = termsMap[order.payment_terms] || 'Net 30';
  
  // Type badge
  const typeElem = document.getElementById('viewOrderType');
  typeElem.textContent = order.type || 'N/A';
  typeElem.className = `badge ${order.type === 'Sales' ? 'badge-success' : 'badge-default'}`;
  
  // Status badge
  const statusElem = document.getElementById('viewOrderStatus');
  statusElem.textContent = order.status.charAt(0).toUpperCase() + order.status.slice(1);
  const statusClasses = { 'draft': 'badge-secondary', 'pending': 'badge-warning', 'processing': 'badge-default', 'shipped': 'badge-success', 'delivered': 'badge-success', 'cancelled': 'badge-danger' };
  statusElem.className = `badge ${statusClasses[order.status] || 'badge-default'}`;
  
  // Financials
  const subtotal = parseFloat(order.subtotal || 0);
  const tax = parseFloat(order.tax || 0);
  const discount = parseFloat(order.discount_amount || 0);
  const total = parseFloat(order.total || 0);
  const paid = parseFloat(order.amount_paid || 0);
  const balance = total - paid;
  
  document.getElementById('viewOrderSubtotal').textContent = ORDER_CURRENCY_SYMBOL + subtotal.toFixed(2);
  document.getElementById('viewOrderTax').textContent = ORDER_CURRENCY_SYMBOL + tax.toFixed(2);
  document.getElementById('viewOrderDiscount').textContent = '-' + ORDER_CURRENCY_SYMBOL + discount.toFixed(2);
  document.getElementById('viewOrderTotal').textContent = ORDER_CURRENCY_SYMBOL + total.toFixed(2);
  document.getElementById('viewOrderPaid').textContent = ORDER_CURRENCY_SYMBOL + paid.toFixed(2);
  document.getElementById('viewOrderBalance').textContent = ORDER_CURRENCY_SYMBOL + balance.toFixed(2);
  
  // Line items - Compact table
  const itemsContainer = document.getElementById('viewOrderItems');
  if (order.items && order.items.length > 0) {
    let itemsHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 0.75rem;"><thead><tr style="border-bottom: 2px solid hsl(214 80% 75%);"><th style="padding: 0.5rem 0; text-align: left; font-size: 0.625rem; font-weight: 700; color: hsl(214 95% 25%); text-transform: uppercase; letter-spacing: 0.05em;">Description</th><th style="padding: 0.5rem 0; text-align: center; font-size: 0.625rem; font-weight: 700; color: hsl(214 95% 25%); text-transform: uppercase;">Qty</th><th style="padding: 0.5rem 0; text-align: right; font-size: 0.625rem; font-weight: 700; color: hsl(214 95% 25%); text-transform: uppercase;">Price</th><th style="padding: 0.5rem 0; text-align: right; font-size: 0.625rem; font-weight: 700; color: hsl(214 95% 25%); text-transform: uppercase;">Total</th></tr></thead><tbody>';
    
    order.items.forEach(item => {
      itemsHTML += `<tr style="border-bottom: 1px solid hsl(214 80% 88%);"><td style="padding: 0.5rem 0; color: hsl(214 95% 20%); font-weight: 600;">${item.description || 'N/A'}</td><td style="padding: 0.5rem 0; text-align: center; font-family: monospace; font-weight: 600; color: hsl(214 95% 20%);">${item.quantity || 0}</td><td style="padding: 0.5rem 0; text-align: right; font-family: monospace; font-weight: 600; color: hsl(214 95% 20%);">${ORDER_CURRENCY_SYMBOL}${parseFloat(item.unit_price || 0).toFixed(2)}</td><td style="padding: 0.5rem 0; text-align: right; font-family: monospace; font-weight: 700; color: hsl(214 95% 20%); font-size: 0.8125rem;">${ORDER_CURRENCY_SYMBOL}${parseFloat(item.total || 0).toFixed(2)}</td></tr>`;
    });
    
    itemsHTML += '</tbody></table>';
    itemsContainer.innerHTML = itemsHTML;
  } else {
    itemsContainer.innerHTML = '<p style="text-align: center; color: hsl(214 90% 40%); padding: 2rem 1rem; font-size: 0.75rem;">No line items</p>';
  }
  
  // Store order data for payment
  document.getElementById('viewOrderModal').setAttribute('data-order-id', orderId);
  document.getElementById('viewOrderModal').setAttribute('data-balance', balance.toFixed(2));
}

function closeViewOrderModal() {
  document.getElementById('viewOrderModal').style.display = 'none';
}

function printOrderFromView() {
  const orderId = document.getElementById('viewOrderModal').getAttribute('data-order-id');
  printOrder(orderId);
}

function emailOrderFromView() {
  const orderId = document.getElementById('viewOrderModal').getAttribute('data-order-id');
  emailOrder(orderId);
}

function recordPaymentFromView() {
  const orderId = document.getElementById('viewOrderModal').getAttribute('data-order-id');
  recordPayment(orderId);
}

async function recordPayment(orderId) {
  // Show loading modal
  const loadingModal = document.createElement('div');
  loadingModal.id = 'paymentLoadingModal';
  loadingModal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px); pointer-events: auto;';
  loadingModal.innerHTML = `
    <div style="background: white; border-radius: 12px; padding: 3rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center;">
      <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 50%)" stroke-width="2" style="animation: spin 1s linear infinite; margin: 0 auto;">
        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
      </svg>
      <p style="margin-top: 1rem; color: hsl(215 16% 47%); font-weight: 600;">Loading order details...</p>
    </div>
  `;
  document.body.appendChild(loadingModal);
  
  try {
    // Fetch order data to get balance
    const response = await fetch(`api/orders.php?id=${orderId}`);
    const result = await response.json();
    
    if (!result.success || !result.data) {
      loadingModal.remove();
      Toast.error('Failed to load order details');
      return;
    }
    
    const order = result.data;
    const total = parseFloat(order.total || 0);
    const paid = parseFloat(order.amount_paid || 0);
    const balanceDue = (total - paid).toFixed(2);
    const orderNumber = order.order_number || orderId;
    
    // Remove loading modal
    loadingModal.remove();
    
    // Create payment modal
    const modal = document.createElement('div');
    modal.id = 'paymentModal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 10000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px); pointer-events: auto; animation: fadeIn 0.2s ease;';
    
    modal.innerHTML = `
      <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 520px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); pointer-events: auto; animation: slideUp 0.3s ease;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="width: 48px; height: 48px; background: linear-gradient(135deg, hsl(143 85% 96%) 0%, hsl(143 75% 92%) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid hsl(143 70% 85%);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2.5">
              <rect x="2" y="5" width="20" height="14" rx="2"/>
              <path d="M2 10h20"/>
            </svg>
          </div>
          <div style="flex: 1;">
            <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%); letter-spacing: -0.02em;">Record Payment</h3>
            <p style="font-size: 0.875rem; color: hsl(215 16% 47%); margin: 0.25rem 0 0; font-family: monospace; font-weight: 500;">${orderNumber}</p>
          </div>
        </div>
        
        <!-- Balance Due Info -->
        <div style="background: linear-gradient(135deg, hsl(0 86% 97%) 0%, hsl(0 80% 95%) 100%); padding: 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid hsl(0 74% 85%);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span style="font-size: 0.75rem; font-weight: 700; color: hsl(0 74% 30%); text-transform: uppercase; letter-spacing: 0.05em;">Total Amount</span>
            <span style="font-size: 1rem; font-weight: 700; font-family: monospace; color: hsl(0 74% 24%);">${ORDER_CURRENCY_SYMBOL}${total.toFixed(2)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid hsl(0 74% 85%);">
            <span style="font-size: 0.75rem; font-weight: 700; color: hsl(140 61% 30%); text-transform: uppercase; letter-spacing: 0.05em;">Amount Paid</span>
            <span style="font-size: 1rem; font-weight: 700; font-family: monospace; color: hsl(140 61% 20%);">${ORDER_CURRENCY_SYMBOL}${paid.toFixed(2)}</span>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.875rem; font-weight: 800; color: hsl(0 74% 24%); text-transform: uppercase; letter-spacing: 0.05em;">Balance Due</span>
            <span style="font-size: 1.5rem; font-weight: 800; font-family: monospace; color: hsl(0 74% 42%);">${ORDER_CURRENCY_SYMBOL}${balanceDue}</span>
          </div>
        </div>
        
        <div style="margin-bottom: 1rem;">
          <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">Payment Amount</label>
          <div style="position: relative;">
            <input type="number" id="paymentAmount" placeholder="0.00" value="${balanceDue}" step="0.01" min="0" style="width: 100%; padding: 0.875rem 4.5rem 0.875rem 0.875rem; border: 2px solid hsl(143 60% 80%); border-radius: 8px; font-size: 1.125rem; font-family: monospace; font-weight: 700; background: hsl(143 85% 98%); color: hsl(140 61% 13%); transition: all 0.2s;" onfocus="this.style.borderColor='hsl(140 61% 50%)'; this.style.background='white'" onblur="this.style.borderColor='hsl(143 60% 80%)'; this.style.background='hsl(143 85% 98%)'">
            <button type="button" onclick="document.getElementById('paymentAmount').value='${balanceDue}'" style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); padding: 0.5rem 0.875rem; background: hsl(143 85% 96%); color: hsl(140 61% 13%); border: 1.5px solid hsl(143 60% 80%); border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.05em;" onmouseover="this.style.background='hsl(140 61% 50%)'; this.style.color='white'; this.style.borderColor='hsl(140 61% 50%)'" onmouseout="this.style.background='hsl(143 85% 96%)'; this.style.color='hsl(140 61% 13%)'; this.style.borderColor='hsl(143 60% 80%)'">Full</button>
          </div>
        </div>
      
      <div style="margin-bottom: 1rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">Payment Date</label>
        <input type="date" id="paymentDate" value="${new Date().toISOString().split('T')[0]}" style="width: 100%; padding: 0.75rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px;">
      </div>
      
      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">Payment Method</label>
        <select id="paymentMethod" style="width: 100%; padding: 0.75rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px;">
          <option value="cash">Cash</option>
          <option value="check">Check</option>
          <option value="bank_transfer">Bank Transfer</option>
          <option value="credit_card">Credit Card</option>
          <option value="paypal">PayPal</option>
          <option value="other">Other</option>
        </select>
      </div>
      
        <div style="display: flex; gap: 0.75rem;">
          <button type="button" onclick="document.getElementById('paymentModal').remove()" style="flex: 1; padding: 0.875rem 1.25rem; background: white; border: 2px solid hsl(214 20% 85%); border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); transition: all 0.2s;" onmouseover="this.style.borderColor='hsl(214 20% 70%)'; this.style.background='hsl(240 5% 98%)'" onmouseout="this.style.borderColor='hsl(214 20% 85%)'; this.style.background='white'">Cancel</button>
          <button type="button" onclick="savePayment('${orderId}')" style="flex: 1; padding: 0.875rem 1.25rem; background: linear-gradient(135deg, hsl(140 61% 50%), hsl(140 61% 40%)); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.875rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(72, 187, 120, 0.4)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 12px rgba(72, 187, 120, 0.3)'">
            <span style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
              Record Payment
            </span>
          </button>
        </div>
      </div>
    `;
    
    document.body.appendChild(modal);
    
    // Focus on amount input
    setTimeout(() => {
      const amountInput = document.getElementById('paymentAmount');
      if (amountInput) {
        amountInput.focus();
        amountInput.select();
      }
    }, 100);
    
    // Close on clicking outside
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.remove();
      }
    });
    
  } catch (error) {
    loadingModal.remove();
    console.error('Error loading order:', error);
    Toast.error('Failed to load order details. Please try again.');
  }
}

async function savePayment(orderId) {
  const amount = document.getElementById('paymentAmount').value;
  const date = document.getElementById('paymentDate').value;
  const method = document.getElementById('paymentMethod').value;
  
  if (!amount || parseFloat(amount) <= 0) {
    Toast.error('Please enter a valid payment amount');
    return;
  }
  
  // Show loading
  const saveBtn = event.target;
  const originalText = saveBtn.textContent;
  saveBtn.disabled = true;
  saveBtn.textContent = 'Recording...';
  
  try {
    const response = await fetch('api/orders.php', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: orderId,
        action: 'record_payment',
        payment: {
          amount: parseFloat(amount),
          date: date,
          method: method
        }
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      Toast.success(`Payment of ${ORDER_CURRENCY_SYMBOL}${parseFloat(amount).toFixed(2)} recorded successfully`);
      document.getElementById('paymentModal').remove();
      
      // Reload page to show updated payment status
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      Toast.error(result.message || 'Failed to record payment');
      saveBtn.disabled = false;
      saveBtn.textContent = originalText;
    }
  } catch (error) {
    console.error('Payment recording error:', error);
    Toast.error('Network error. Please try again.');
    saveBtn.disabled = false;
    saveBtn.textContent = originalText;
  }
}

function printOrder(orderId) {
  // Open PDF in modal viewer
  try {
    if (!orderId) {
      Toast.warning('No order selected');
      return;
    }
    const url = 'api/order_pdf.php?id=' + encodeURIComponent(orderId);
    console.log('📄 Opening PDF viewer for order ' + orderId);
    
    // Set iframe source and open modal
    const modal = document.getElementById('pdfViewerModal');
    const iframe = document.getElementById('pdfViewerFrame');
    const title = document.getElementById('pdfViewerTitle');
    
    iframe.src = url;
    title.textContent = 'Order PDF - ' + orderId;
    modal.style.display = 'flex';
    
    Toast.info('Loading order PDF...');
  } catch (e) {
    console.error('PDF viewer exception:', e);
    Toast.error('Failed to open PDF: ' + e.message);
  }
}

function closePdfViewer() {
  const modal = document.getElementById('pdfViewerModal');
  const iframe = document.getElementById('pdfViewerFrame');
  
  modal.style.display = 'none';
  // Clear iframe to stop loading
  iframe.src = '';
  console.log('📄 PDF viewer closed');
}

let currentEmailOrderId = null;

async function emailOrder(orderId) {
  console.log('📧 Opening email form for order:', orderId);
  currentEmailOrderId = orderId;
  
  // Fetch order data to get details
  try {
    const response = await fetch(`api/orders.php?id=${orderId}`);
    const result = await response.json();
    
    if (!result.success || !result.data) {
      Toast.error('Failed to load order details');
      return;
    }
    
    const order = result.data;
    const orderNumber = order.order_number || orderId;
    const customer = order.customer_name || 'Customer';
    
    // Populate modal
    document.getElementById('emailOrderId').value = orderId;
    document.getElementById('emailOrderNumber').textContent = orderNumber;
    document.getElementById('emailSubject').value = `Order ${orderNumber} from Your Company`;
    document.getElementById('emailMessage').value = `Dear ${customer},\n\nPlease find attached order confirmation ${orderNumber}.\n\nThank you for your business!\n\nBest regards,\nYour Company`;
    
    // Check SMTP configuration
    try {
      const smtpResponse = await fetch('api/check_smtp_config.php');
      const smtpResult = await smtpResponse.json();
      
      const smtpWarning = document.getElementById('smtpWarningOrder');
      const sendBtn = document.getElementById('sendEmailOrderBtn');
      
      if (smtpResult.configured) {
        smtpWarning.style.display = 'none';
        sendBtn.disabled = false;
        sendBtn.style.opacity = '1';
        sendBtn.style.cursor = 'pointer';
        console.log('✓ SMTP configured and ready');
      } else {
        smtpWarning.style.display = 'block';
        sendBtn.disabled = true;
        sendBtn.style.opacity = '0.5';
        sendBtn.style.cursor = 'not-allowed';
        sendBtn.title = 'SMTP not configured';
        console.log('⚠ SMTP not configured - email sending disabled');
      }
    } catch (error) {
      console.log('⚠ Could not verify SMTP configuration');
      document.getElementById('smtpWarningOrder').style.display = 'block';
      const sendBtn = document.getElementById('sendEmailOrderBtn');
      sendBtn.disabled = true;
      sendBtn.style.opacity = '0.5';
      sendBtn.style.cursor = 'not-allowed';
    }
    
    // Show modal
    const modal = document.getElementById('emailOrderModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Focus on recipient email field
    setTimeout(() => {
      document.getElementById('recipientEmailOrder').focus();
    }, 100);
    
  } catch (error) {
    console.error('Error loading order:', error);
    Toast.error('Failed to load order details');
  }
}

function closeEmailOrderModal() {
  const modal = document.getElementById('emailOrderModal');
  modal.style.display = 'none';
  document.body.style.overflow = '';
  
  // Reset form
  document.getElementById('emailOrderForm').reset();
  document.getElementById('smtpWarningOrder').style.display = 'none';
  currentEmailOrderId = null;
  
  console.log('ℹ Email form closed');
}

async function submitEmailOrder(event) {
  event.preventDefault();
  
  const form = event.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalHTML = submitBtn.innerHTML;
  
  // Disable submit button
  submitBtn.disabled = true;
  submitBtn.innerHTML = 'Sending...';
  
  try {
    const formData = new FormData(form);
    const data = {
      order_id: formData.get('order_id'),
      recipient_email: formData.get('recipient_email'),
      cc_email: formData.get('cc_email') || null,
      subject: formData.get('subject'),
      message: formData.get('message'),
      attach_pdf: formData.get('attach_pdf') ? true : false
    };
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.recipient_email)) {
      Toast.error('Invalid email format');
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalHTML;
      return;
    }
    
    // Send to API
    const response = await fetch('api/send_order_email.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      closeEmailOrderModal();
      console.log(`✓ Order email sent successfully to ${data.recipient_email}`);
      Toast.success('Order email sent successfully!');
    } else {
      throw new Error(result.message || 'Failed to send email');
    }
  } catch (error) {
    console.error('✗ Email sending error:', error);
    Toast.error('Failed to send email: ' + error.message);
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalHTML;
  }
  
  return false;
}

let currentOrderId = null;
let currentOrderStatus = null;

// Show actions dropdown menu
function showMoreActions(orderId, status = 'Pending') {
  // Stop event propagation to prevent conflicts
  if (event) {
    event.stopPropagation();
  }
  
  const dropdown = document.getElementById('actionsDropdown');
  
  // Check if dropdown is already open for this order
  const isOpen = dropdown.style.display === 'block' && currentOrderId === orderId;
  
  if (isOpen) {
    // Close if already open
    closeDropdowns();
    console.log('📋 Actions dropdown closed');
    return;
  }
  
  // Close any open dropdowns first
  closeDropdowns();
  
  // Set current order
  currentOrderId = orderId;
  currentOrderStatus = status;
  
  const button = event.target.closest('button');
  const rect = button.getBoundingClientRect();
  const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
  const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
  
  // Position dropdown relative to viewport + scroll position
  dropdown.style.top = (rect.bottom + scrollTop + 5) + 'px';
  dropdown.style.left = (rect.left + scrollLeft - 150) + 'px';
  dropdown.style.display = 'block';
  
  console.log('📋 Actions dropdown opened for order:', orderId);
  
  // Hide void option if already cancelled
  const voidBtn = document.getElementById('actionVoid');
  if (status === 'Cancelled') {
    voidBtn.style.display = 'none';
  } else {
    voidBtn.style.display = 'flex';
  }
  
  // Close on outside click (delay to prevent immediate closure)
  setTimeout(() => {
    document.addEventListener('click', closeDropdowns, { once: true });
  }, 100);
}

// Handle individual order actions
function handleOrderAction(action) {
  closeDropdowns();
  
  switch(action) {
    case 'duplicate':
      console.log('📋 Duplicating order:', currentOrderId);
      if (confirm('Duplicate this order?\n\nA copy of this order will be created with status "Pending".')) {
        Toast.info('Duplicate order feature coming soon!');
        // TODO: Implement duplicate via API
      }
      break;
      
    case 'invoice':
      console.log('📄 Converting to invoice:', currentOrderId);
      if (confirm('Convert this order to an invoice?\n\nThis will create a new invoice based on this order.')) {
        Toast.info('Convert to invoice feature coming soon!');
        // TODO: Implement conversion via API
      }
      break;
      
    case 'credit_note':
      console.log('💳 Creating credit note for order:', currentOrderId);
      if (confirm('Create a credit note for this order?\n\nThis will generate a credit note document that can be issued to the customer.')) {
        Toast.info('Create credit note feature coming soon!');
        // TODO: Implement credit note creation via API
      }
      break;
      
    case 'recurring':
      console.log('🔄 Adding order to recurring:', currentOrderId);
      if (confirm('Add this order to recurring schedule?\n\nYou will be able to set the frequency and duration for automatic order generation.')) {
        Toast.info('Add to recurring feature coming soon!');
        // TODO: Implement recurring order setup
      }
      break;
      
    case 'audit':
      console.log('📋 Viewing audit trail for order:', currentOrderId);
      Toast.info('Loading audit trail...');
      // TODO: Open audit trail modal showing:
      // - Order creation date/time and user
      // - All modifications with timestamps
      // - Status changes
      // - Payment records
      // - Email sending history
      setTimeout(() => {
        Toast.warning('Audit trail feature coming soon!');
      }, 500);
      break;
      
    case 'attach':
      console.log('📎 Attaching documents to order:', currentOrderId);
      Toast.info('Opening document attachment dialog...');
      // TODO: Open file upload modal for attaching:
      // - Purchase orders
      // - Delivery receipts
      // - Invoices
      // - Customer correspondence
      // - Photos/images
      setTimeout(() => {
        Toast.warning('Attach documents feature coming soon!');
      }, 500);
      break;
      
    case 'void':
      if (confirm('Mark this order as void?\n\nThis will change the status to Cancelled and the order cannot be processed.')) {
        console.log('❌ Voiding order:', currentOrderId);
        fetch('api/orders.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
            id: currentOrderId,
            action: 'void'
          })
        })
        .then(response => response.json())
        .then(data => {
          console.log('Void response:', data);
          if (data.success) {
            Toast.success('Order marked as void');
            // Refresh page or update row
            setTimeout(() => window.location.reload(), 1000);
          } else {
            Toast.error(data.message || 'Failed to void order');
          }
        })
        .catch(error => {
          console.error('Network error:', error);
          Toast.error('Network error: ' + error.message);
        });
      }
      break;
      
    case 'delete':
      if (confirm('Delete this order?\n\nThis action cannot be undone. All order data will be permanently removed.')) {
        console.log('🗑️ Deleting order:', currentOrderId);
        fetch('api/orders.php?id=' + currentOrderId, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
          console.log('Delete response:', data);
          if (data.success) {
            Toast.success('Order deleted successfully');
            // Remove row from table or reload
            setTimeout(() => window.location.reload(), 1000);
          } else {
            Toast.error(data.message || 'Failed to delete order');
          }
        })
        .catch(error => {
          console.error('Network error:', error);
          Toast.error('Network error: ' + error.message);
        });
      }
      break;
  }
}

// Close all dropdowns
function closeDropdowns() {
  const actionsDropdown = document.getElementById('actionsDropdown');
  if (actionsDropdown) actionsDropdown.style.display = 'none';
  console.log('🔒 All dropdowns closed');
}

function duplicateOrder(orderId) {
  Toast.success(`Order ${orderId} duplicated`);
  // TODO: Implement duplication logic
}

function convertToInvoice(orderId) {
  Toast.info('Converting to invoice...');
  // TODO: Implement conversion logic
}

function createCreditNote(orderId) {
  Toast.info('Creating credit note...');
  // TODO: Implement credit note logic
}

function markAsVoid(orderId) {
  if (confirm('Mark this order as void?')) {
    Toast.warning(`Order ${orderId} marked as void`);
  }
}

function addToRecurring(orderId) {
  Toast.info('Setting up recurring schedule...');
  // TODO: Implement recurring logic
}

function viewAuditTrail(orderId) {
  alert(`Audit Trail for Order ${orderId}:\\n\\n- Created: 2024-10-31 10:30 AM by admin@example.com\\n- Modified: 2024-10-31 2:15 PM by admin@example.com\\n- Status Changed: pending → processing\\n- Payment Recorded: $500.00 via Bank Transfer\\n- Email Sent: customer@example.com`);
}

function attachDocuments(orderId) {
  Toast.info('Document upload feature coming soon');
  // TODO: Implement document attachment
}
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
