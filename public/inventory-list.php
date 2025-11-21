<?php
/**
 * Inventory List Page
 * Professional data table with search and filtering
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Inventory;
use App\Helper\CurrencyHelper;
use App\Helper\NotificationHelper;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();
$inventoryModel = new Inventory();

// Get notification summary for inventory alerts
$userId = $user['id'] ?? 'admin';
$notificationSummary = NotificationHelper::getSummary($userId);
$inventoryAlerts = $notificationSummary['by_type']['inventory'] ?? 0;

// Get filter and pagination parameters
$searchQuery = $_GET['search'] ?? '';
$filterType = $_GET['filter'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'asc';
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = (int)($_GET['per_page'] ?? 6);
$viewMode = $_GET['view'] ?? 'table'; // table or grid

// Get ALL inventory items first (for total count)
try {
    if (!empty($searchQuery)) {
        $allItems = $inventoryModel->search($searchQuery);
    } elseif ($filterType === 'low_stock') {
        $allItems = $inventoryModel->getLowStock(5);
    } elseif ($filterType === 'out_of_stock') {
        $allItems = $inventoryModel->getLowStock(0);
    } else {
        $allItems = $inventoryModel->getAll();
    }
    
    // Apply filters
    if ($categoryFilter !== 'all') {
        $allItems = array_filter($allItems, fn($item) => ($item['type'] ?? '') === $categoryFilter);
    }
    
    if ($statusFilter !== 'all') {
        $allItems = array_filter($allItems, function($item) use ($statusFilter) {
            $qty = $item['quantity'] ?? 0;
            if ($statusFilter === 'in_stock') return $qty > 5;
            if ($statusFilter === 'low_stock') return $qty > 0 && $qty <= 5;
            if ($statusFilter === 'out_of_stock') return $qty == 0;
            return true;
        });
    }
    
    // Sorting with proper field mapping
    usort($allItems, function($a, $b) use ($sortBy, $sortOrder) {
        // Map sort field to actual data
        switch($sortBy) {
            case 'barcode':
            case 'sku':
                $aVal = $a['barcode'] ?? '';
                $bVal = $b['barcode'] ?? '';
                break;
            case 'name':
                $aVal = $a['name'] ?? '';
                $bVal = $b['name'] ?? '';
                break;
            case 'category':
            case 'type':
                $aVal = $a['type'] ?? '';
                $bVal = $b['type'] ?? '';
                break;
            case 'price':
                $aVal = (float)($a['sell_price'] ?? $a['price'] ?? 0);
                $bVal = (float)($b['sell_price'] ?? $b['price'] ?? 0);
                break;
            case 'quantity':
            case 'stock':
                $aVal = (int)($a['quantity'] ?? 0);
                $bVal = (int)($b['quantity'] ?? 0);
                break;
            case 'status':
                // Sort by stock status (out->low->in)
                $aQty = (int)($a['quantity'] ?? 0);
                $bQty = (int)($b['quantity'] ?? 0);
                $aStatus = $aQty == 0 ? 0 : ($aQty <= 5 ? 1 : 2);
                $bStatus = $bQty == 0 ? 0 : ($bQty <= 5 ? 1 : 2);
                $aVal = $aStatus;
                $bVal = $bStatus;
                break;
            case 'value':
                $aVal = ((int)($a['quantity'] ?? 0)) * ((float)($a['sell_price'] ?? $a['price'] ?? 0));
                $bVal = ((int)($b['quantity'] ?? 0)) * ((float)($b['sell_price'] ?? $b['price'] ?? 0));
                break;
            case 'date':
            case 'updated':
                $aVal = isset($a['date_added']) ? $a['date_added']->toDateTime()->getTimestamp() : 0;
                $bVal = isset($b['date_added']) ? $b['date_added']->toDateTime()->getTimestamp() : 0;
                break;
            default:
                $aVal = $a[$sortBy] ?? '';
                $bVal = $b[$sortBy] ?? '';
        }
        
        $result = $aVal <=> $bVal;
        return $sortOrder === 'desc' ? -$result : $result;
    });
    
    // Calculate totals
    $totalValue = 0;
    $totalQuantity = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;
    foreach ($allItems as $item) {
        $qty = $item['quantity'] ?? 0;
        // Use sell_price field (which is stored in database) instead of price
        $price = (float)($item['sell_price'] ?? $item['price'] ?? 0);
        $totalValue += $qty * $price;
        $totalQuantity += $qty;
        if ($qty == 0) $outOfStockCount++;
        elseif ($qty <= 5) $lowStockCount++;
    }
    
    // Calculate pagination
    $totalItems = count($allItems);
    $totalPages = max(1, ceil($totalItems / $itemsPerPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Get items for current page
    $items = array_slice($allItems, $offset, $itemsPerPage);
} catch (Exception $e) {
    $items = [];
    $allItems = [];
    $totalItems = 0;
    $totalPages = 1;
    $totalValue = 0;
    $totalQuantity = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $categories = [];
    $error = $e->getMessage();
}

// Set page variables
$pageTitle = 'Inventory List';

// Get unique categories from all items
$categories = array_unique(array_column($allItems, 'type'));
sort($categories);

// Start output buffering for content
ob_start();
?>

<style>
/* Inventory Color Theme - Blue-Gray #7194A5 */
:root {
  --inventory-primary: #7194A5;
  --inventory-primary-hover: #5d7a8a;
  --inventory-primary-light: #e8eef1;
  --inventory-border: #d1dce2;
  --inventory-accent: #4a90e2;
}

/* Shadcn Table Styles */
.inventory-table-container {
  width: 100%;
  max-width: 100%;
  margin: 0 auto;
  background: var(--bg-primary);
  border-radius: var(--radius-lg);
  border: 1px solid var(--border-color);
  overflow-x: auto;
  overflow-y: hidden;
  position: relative;
  /* Smooth scroll */
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
  /* Prevent container from expanding beyond viewport */
  contain: layout;
}

/* Hide print-only elements on screen */
.inventory-table-container.print-only,
.print-items-list,
.print-summary {
  display: none !important;
}

/* Custom scrollbar - Gray style matching other pages */
.inventory-table-container::-webkit-scrollbar {
  height: 8px;
}

.inventory-table-container::-webkit-scrollbar-track {
  background: var(--bg-secondary);
  border-radius: var(--radius-full);
}

.inventory-table-container::-webkit-scrollbar-thumb {
  background: var(--color-gray-300);
  border-radius: var(--radius-full);
}

.inventory-table-container::-webkit-scrollbar-thumb:hover {
  background: var(--color-gray-400);
}

.inventory-table {
  width: 100%;
  min-width: 1000px;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.875rem;
}

.inventory-table thead tr {
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-secondary);
}

.inventory-table th {
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  font-size: 0.8125rem;
  letter-spacing: 0.01em;
}

.inventory-table tbody tr {
  border-bottom: 1px solid var(--border-color);
  transition: background-color 0.15s ease;
}

.inventory-table tbody tr:hover {
  background: var(--bg-hover, hsl(0 0% 0% / 0.02));
}

.inventory-table tbody tr:last-child {
  border-bottom: none;
}

.inventory-table td {
  padding: 0.875rem 1rem;
  white-space: nowrap;
  color: var(--text-primary);
}

/* Shadcn Badge Styles */
.inventory-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid transparent;
  padding: 0.25rem 0.625rem;
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.inventory-badge-success {
  background: hsl(143 85% 96%);
  color: hsl(140 61% 13%);
  border-color: hsl(143 85% 90%);
}

.inventory-badge-warning {
  background: hsl(48 96% 89%);
  color: hsl(25 95% 16%);
  border-color: hsl(48 96% 80%);
}

.inventory-badge-danger {
  background: hsl(0 86% 97%);
  color: hsl(0 74% 24%);
  border-color: hsl(0 86% 90%);
}

.inventory-badge-primary {
  background: var(--inventory-primary-light);
  color: var(--inventory-primary);
  border-color: var(--inventory-border);
}

/* Stats Cards */
.inventory-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.inventory-stat-card {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 1.25rem;
  transition: all 0.2s ease;
}

.inventory-stat-card:hover {
  border-color: var(--inventory-primary);
  box-shadow: 0 4px 12px rgba(113, 148, 165, 0.1);
}

.stat-label {
  font-size: 0.8125rem;
  color: var(--text-secondary);
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--inventory-primary);
  line-height: 1.2;
}

.stat-change {
  font-size: 0.75rem;
  margin-top: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

/* Filter Bar */
.inventory-filter-bar {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 1rem;
  margin-bottom: 1.5rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.filter-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-secondary);
  white-space: nowrap;
}

/* Bulk Actions Bar */
.bulk-actions-bar {
  background: var(--inventory-primary-light);
  border: 1px solid var(--inventory-border);
  border-radius: var(--radius-md);
  padding: 0.875rem 1rem;
  margin-bottom: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* Quick Actions */
.quick-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--inventory-primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.quick-action-btn:hover {
  background: var(--inventory-primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(113, 148, 165, 0.2);
}

/* Stock Level Indicators */
.stock-level-bar {
  width: 60px;
  height: 6px;
  background: var(--bg-secondary);
  border-radius: 3px;
  overflow: hidden;
  position: relative;
}

.stock-level-fill {
  height: 100%;
  transition: width 0.3s ease;
}

.stock-level-fill.high {
  background: hsl(143 85% 50%);
}

.stock-level-fill.medium {
  background: hsl(48 96% 50%);
}

.stock-level-fill.low {
  background: hsl(0 86% 60%);
}

/* Sortable Column Headers */
.sortable-header {
  cursor: pointer;
  user-select: none;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.sortable-header:hover {
  color: var(--inventory-primary);
}

.sortable-header.active {
  color: var(--inventory-primary);
  font-weight: 700;
}

.sort-icon {
  display: inline-flex;
  flex-direction: column;
  width: 12px;
  height: 16px;
  opacity: 0.3;
  transition: opacity 0.2s ease;
}

.sortable-header:hover .sort-icon {
  opacity: 0.6;
}

.sortable-header.active .sort-icon {
  opacity: 1;
}

.sort-arrow {
  width: 0;
  height: 0;
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
}

.sort-arrow.up {
  border-bottom: 6px solid currentColor;
  margin-bottom: 1px;
}

.sort-arrow.down {
  border-top: 6px solid currentColor;
  margin-top: 1px;
}

.sortable-header.active.asc .sort-arrow.up {
  opacity: 1;
}

.sortable-header.active.asc .sort-arrow.down {
  opacity: 0.2;
}

.sortable-header.active.desc .sort-arrow.up {
  opacity: 0.2;
}

.sortable-header.active.desc .sort-arrow.down {
  opacity: 1;
}

/* Loading State */
.inventory-loading {
  position: relative;
  pointer-events: none;
  opacity: 0.6;
}

.inventory-loading::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 24px;
  height: 24px;
  margin: -12px 0 0 -12px;
  border: 3px solid var(--inventory-primary-light);
  border-top-color: var(--inventory-primary);
  border-radius: 50%;
  animation: inventory-spin 0.8s linear infinite;
}

@keyframes inventory-spin {
  to { transform: rotate(360deg); }
}

.filter-loading {
  opacity: 0.6;
  pointer-events: none;
}

/* Smooth transitions */
.inventory-table tbody {
  transition: opacity 0.2s ease;
}

.inventory-table.updating tbody {
  opacity: 0.4;
}

/* Responsive - Progressive Column Hiding */
@media (max-width: 1400px) {
  .inventory-table-container {
    max-width: 100%;
  }
  
  /* Slightly reduce padding for better fit */
  .inventory-table th,
  .inventory-table td {
    padding: 0.625rem 0.875rem;
  }
}

@media (max-width: 1280px) {
  /* Hide Last Updated column (least important) */
  .inventory-table th:nth-child(10),
  .inventory-table td:nth-child(10) {
    display: none;
  }
  
  .inventory-table {
    min-width: 900px;
  }
  
  /* Further compress padding */
  .inventory-table th,
  .inventory-table td {
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
  }
  
  .inventory-table th {
    font-size: 0.75rem;
  }
}

@media (max-width: 1150px) {
  /* Hide Value column */
  .inventory-table th:nth-child(9),
  .inventory-table td:nth-child(9) {
    display: none;
  }
  
  .inventory-table {
    min-width: 800px;
  }
  
  /* More compression */
  .inventory-table th,
  .inventory-table td {
    padding: 0.5rem 0.625rem;
  }
}

@media (max-width: 1024px) {
  /* Hide Status column (stock level bar shows status) */
  .inventory-table th:nth-child(8),
  .inventory-table td:nth-child(8) {
    display: none;
  }
  
  .inventory-table {
    min-width: 700px;
  }
  
  /* Tight compression */
  .inventory-table th,
  .inventory-table td {
    padding: 0.5rem;
  }
}

@media (max-width: 900px) {
  /* Stats grid to 2 columns */
  .inventory-stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }
  
  .stat-value {
    font-size: 1.5rem;
  }
  
  /* Stack filter bar */
  .inventory-filter-bar {
    flex-direction: column;
    align-items: stretch;
    gap: 0.5rem;
  }
  
  .filter-group {
    width: 100%;
  }
  
  .filter-group select,
  .filter-group input {
    width: 100% !important;
    min-width: auto !important;
  }
  
  /* Stack header actions */
  .content-header {
    flex-direction: column;
    align-items: flex-start !important;
    gap: 1rem;
  }
  
  .content-actions {
    width: 100%;
    display: grid !important;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
  }
  
  .content-actions .btn {
    width: 100%;
    justify-content: center;
  }
  
  /* Hide Category column */
  .inventory-table th:nth-child(5),
  .inventory-table td:nth-child(5) {
    display: none;
  }
  
  .inventory-table {
    min-width: 600px;
  }
}

/* Card transformation at 640px for very small screens/actual mobile */
@media (max-width: 640px) {
  /* Switch to column layout - table can't fit */
  .inventory-table-container {
    border-radius: 8px;
    overflow-x: visible;
  }
  
  .inventory-table {
    min-width: 100% !important;
    display: block;
  }
  
  .inventory-table thead {
    display: none;
  }
  
  .inventory-table tbody {
    display: block;
  }
  
  .inventory-table tbody tr {
    display: block;
    margin-bottom: 1rem;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    position: relative;
  }
  
  .inventory-table tbody tr:hover {
    background: var(--bg-hover, hsl(0 0% 0% / 0.02));
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }
  
  .inventory-table tbody td {
    display: block;
    text-align: left !important;
    padding: 0.5rem 0 !important;
    border: none;
    white-space: normal;
  }
  
  .inventory-table tbody td:first-child,
  .inventory-table tbody td:nth-child(2) {
    display: none;
  }
  
  .inventory-table tbody td:before {
    content: attr(data-label);
    font-weight: 600;
    font-size: 0.6875rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.025em;
    display: block;
    margin-bottom: 0.375rem;
  }
  
  .inventory-table tbody td:last-child {
    padding-top: 1rem !important;
    margin-top: 1rem;
    border-top: 1px solid var(--border-color);
  }
  
  /* Enhanced card view styling */
  .inventory-table tbody td[data-label="Item Name"] {
    font-size: 1rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem !important;
    border-bottom: 1px solid hsl(240 6% 92%);
  }
  
  .inventory-table tbody td[data-label="Item Name"]:before {
    font-size: 0.625rem;
    margin-bottom: 0.5rem;
  }
}

@media (max-width: 480px) {
  /* Extra compact refinements for very small screens */
  .inventory-stats-grid {
    grid-template-columns: 1fr;
    gap: 0.625rem;
  }
  
  .inventory-stat-card {
    padding: 1rem;
  }
  
  .stat-value {
    font-size: 1.75rem;
  }
  
  .content-actions {
    grid-template-columns: 1fr;
  }
  
  .bulk-actions-bar {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }
  
  .bulk-actions-bar > div {
    width: 100%;
    justify-content: center;
  }
  
  /* Tighter spacing on cards */
  .inventory-table tbody tr {
    padding: 0.875rem;
  }
  
  .inventory-table tbody td {
    padding: 0.375rem 0 !important;
  }
  
  .inventory-table tbody td:before {
    font-size: 0.625rem;
    margin-bottom: 0.25rem;
  }
  
  /* Actions become full width buttons */
  .inventory-table tbody td[data-label="Actions"] > div {
    flex-direction: row;
    justify-content: space-around;
    gap: 0.5rem;
  }
  
  .inventory-table tbody td[data-label="Actions"] .btn {
    flex: 1;
  }
}

/* ============================================
   PRINT STYLES - Professional Inventory Report
   Based on Xero/QuickBooks Standards
   ============================================ */
@media print {
  /* Reset and base print styles */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  
  html {
    margin: 0 !important;
    padding: 0 !important;
  }
  
  body {
    margin: 0 !important;
    padding: 8mm 5mm !important;
    background: white !important;
    width: 100% !important;
    box-sizing: border-box !important;
  }
  
  /* Hide all non-essential elements */
  .breadcrumb,
  .content-actions,
  .inventory-stats-grid,
  .inventory-filter-bar,
  .bulk-actions-bar,
  .btn,
  button,
  .empty-state .btn,
  nav,
  .sidebar,
  .header,
  footer,
  .alert,
  [onclick],
  .sort-icon,
  .sortable-header,
  .mode-indicator,
  input[type="checkbox"],
  .bulk-checkbox,
  .mt-6,
  .pagination,
  .text-secondary,
  .inventory-table th:first-child,
  .inventory-table td:first-child,
  .inventory-table th:nth-child(2),
  .inventory-table td:nth-child(2),
  .inventory-table th:last-child,
  .inventory-table td:last-child,
  .inventory-table th:nth-child(8),
  .inventory-table td:nth-child(8) {
    display: none !important;
  }
  
  /* Print header - Professional Report Style */
  .content-header {
    margin-bottom: 1rem !important;
    padding-bottom: 0.5rem !important;
    border-bottom: 3px solid #000 !important;
    page-break-after: avoid !important;
  }
  
  .content-title {
    display: none !important;
  }
  
  /* Report title */
  .content-header::before {
    content: "INVENTORY VALUATION REPORT";
    display: block;
    font-size: 12pt;
    font-weight: 700;
    color: #000;
    margin-bottom: 0.2rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
  }
  
  /* Report date */
  .content-header::after {
    content: "Report Date: " attr(data-print-date);
    display: block;
    font-size: 7pt;
    color: #333;
    margin-top: 0.2rem;
    font-weight: 600;
  }
  
  /* Hide all tables on print */
  .inventory-table-container,
  .inventory-table-container.print-only {
    display: none !important;
  }
  
  /* Custom Print List Styling - Computation Focus */
  .print-items-list {
    display: block !important;
    margin-top: 1rem !important;
  }
  
  .print-item-card {
    display: grid !important;
    grid-template-columns: 22px 240px 85px 60px 85px !important;
    gap: 1pt !important;
    padding: 2pt 4pt !important;
    border-bottom: 1px solid #ddd !important;
    page-break-inside: avoid !important;
    align-items: center !important;
    font-size: 6.5pt !important;
    background: #fff !important;
  }
  
  .print-item-card:nth-child(even) {
    background: #fafafa !important;
  }
  
  .print-item-card:first-child {
    border-top: 2px solid #000 !important;
  }
  
  /* Item number */
  .print-item-num {
    font-weight: 600 !important;
    color: #666 !important;
    font-size: 7.5pt !important;
  }
  
  /* Item name */
  .print-item-name {
    font-weight: 600 !important;
    font-size: 7pt !important;
    color: #000 !important;
    line-height: 1.15 !important;
    word-break: break-word !important;
    overflow: hidden !important;
    max-height: 2.5em !important;
  }
  
  /* Unit Price column */
  .print-item-price {
    text-align: right !important;
    font-weight: 600 !important;
    font-size: 6.5pt !important;
    color: #000 !important;
    font-family: monospace !important;
  }
  
  /* Quantity Available column */
  .print-item-stock {
    text-align: center !important;
    font-weight: 700 !important;
    font-size: 7pt !important;
    color: #000 !important;
  }
  
  /* Total Value column */
  .print-item-value {
    text-align: right !important;
    font-weight: 700 !important;
    font-size: 7pt !important;
    color: #000 !important;
    font-family: monospace !important;
  }
  
  /* Print list header */
  .print-list-header {
    display: grid !important;
    grid-template-columns: 22px 240px 85px 60px 85px !important;
    gap: 1pt !important;
    padding: 2pt 4pt !important;
    border-top: 2px solid #000 !important;
    border-bottom: 2px solid #000 !important;
    background: #f0f0f0 !important;
    font-weight: 700 !important;
    font-size: 6.5pt !important;
    margin-bottom: 0 !important;
  }
  
  .print-list-header > div {
    color: #000 !important;
  }
  
  /* Page setup - removes browser headers/footers */
  @page {
    size: auto;
    margin: 0mm;
  }
  
  thead {
    display: table-header-group;
  }
  
  tfoot {
    display: table-footer-group;
  }
  
  /* Print summary box - Accounting Style (appears FIRST before table) */
  .print-summary {
    display: block !important;
    margin: 0 0 0.75rem 0 !important;
    padding: 0.5rem !important;
    border: 2px solid #000 !important;
    page-break-inside: avoid !important;
    page-break-after: auto !important;
    background: #fafafa !important;
  }
  
  .print-summary h3 {
    margin: 0 0 0.5rem 0 !important;
    font-size: 9pt !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    border-bottom: 2px solid #000 !important;
    padding-bottom: 0.25rem !important;
  }
  
  .print-summary-grid {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 0.5rem !important;
  }
  
  .print-summary-item {
    display: flex !important;
    justify-content: space-between !important;
    padding: 0.25rem 0 !important;
    border-bottom: 1px solid #ccc !important;
    font-size: 7.5pt !important;
  }
  
  .print-summary-item:last-child {
    border-bottom: none !important;
  }
  
  .print-summary-item strong {
    font-weight: 700 !important;
    font-family: monospace !important;
  }
  
  /* Hide abbreviated numbers, show full values */
  .abbreviated-number {
    text-decoration: none !important;
    cursor: default !important;
  }
  
  .abbreviated-number::after {
    content: attr(data-full) !important;
  }
  
  .abbreviated-number > * {
    display: none !important;
  }
}
</style>

<!-- Compact Header with Breadcrumb -->
<div class="content-header" style="margin-bottom: 1.5rem;">
  <div>
    <nav class="breadcrumb">
      <a href="dashboard.php" class="breadcrumb-link">Dashboard</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current">Inventory</span>
    </nav>
    <h1 class="content-title" style="color: var(--inventory-primary);">📦 Inventory Management</h1>
  </div>
  <div class="content-actions">
    <button class="btn btn-ghost btn-sm" onclick="toggleBulkMode()" id="bulk-mode-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
      </svg>
      Bulk Select
    </button>
    <button class="btn btn-secondary btn-sm" onclick="window.print()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M6 9V2H18V9M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18M6 14H18V22H6V14Z" stroke="currentColor" stroke-width="2"/>
      </svg>
      Print
    </button>
    <button class="btn btn-secondary btn-sm" onclick="exportInventory()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Export
    </button>
    <a href="add_item.php" class="btn btn-sm" style="background: var(--inventory-primary); color: white; border: none;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Add Item
    </a>
  </div>
</div>

<!-- Stats Overview (Xero/QuickBooks Style) -->
<div class="inventory-stats-grid">
  <div class="inventory-stat-card">
    <div class="stat-label">Total Items</div>
    <div class="stat-value"><?php echo number_format($totalItems); ?></div>
    <div class="stat-change" style="color: var(--text-secondary);">
      <span><?php echo count($items); ?> on this page</span>
    </div>
  </div>
  
  <div class="inventory-stat-card">
    <div class="stat-label">Total Quantity</div>
    <div class="stat-value"><?php echo number_format($totalQuantity); ?></div>
    <div class="stat-change" style="color: var(--text-secondary);">
      <span>units in stock</span>
    </div>
  </div>
  
  <div class="inventory-stat-card">
    <div class="stat-label">Inventory Value</div>
    <div class="stat-value"><?php echo CurrencyHelper::format($totalValue); ?></div>
    <div class="stat-change" style="color: var(--text-secondary);">
      <span><?php echo CurrencyHelper::symbol(); ?> total worth</span>
    </div>
  </div>
  
  <div class="inventory-stat-card">
    <div class="stat-label">Stock Alerts</div>
    <div class="stat-value" style="color: <?php echo ($lowStockCount + $outOfStockCount) > 0 ? 'hsl(25 95% 16%)' : 'hsl(143 85% 30%)'; ?>;">
      <?php echo $lowStockCount + $outOfStockCount; ?>
    </div>
    <div class="stat-change" style="color: var(--text-secondary);">
      <span><?php echo $lowStockCount; ?> low, <?php echo $outOfStockCount; ?> out</span>
    </div>
  </div>
</div>

<!-- Advanced Filter Bar (Xero/QuickBooks/LedgerSMB Style) -->
<div class="inventory-filter-bar">
  <!-- Search -->
  <div class="filter-group" style="flex: 1; min-width: 300px;">
    <div class="search-wrapper" style="width: 100%;">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input 
        type="search" 
        class="search-input" 
        placeholder="Search items... (name, barcode, SKU)"
        id="inventory-search"
        value="<?php echo htmlspecialchars($searchQuery); ?>"
        style="color: var(--text-primary); background-color: var(--bg-secondary);"
      >
    </div>
  </div>
  
  <!-- Status Filter -->
  <div class="filter-group">
    <span class="filter-label">Status:</span>
    <select class="form-select" id="status-filter" style="width: auto; min-width: 140px;">
      <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
      <option value="in_stock" <?php echo $statusFilter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
      <option value="low_stock" <?php echo $statusFilter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
      <option value="out_of_stock" <?php echo $statusFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
    </select>
  </div>
  
  <!-- Category Filter -->
  <?php if (!empty($categories)): ?>
  <div class="filter-group">
    <span class="filter-label">Category:</span>
    <select class="form-select" id="category-filter" style="width: auto; min-width: 140px;">
      <option value="all" <?php echo $categoryFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($cat); ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  
  <!-- Sort By -->
  <div class="filter-group">
    <span class="filter-label">Sort:</span>
    <select class="form-select" id="sort-by" style="width: auto; min-width: 150px;">
      <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Item Name</option>
      <option value="sku" <?php echo $sortBy === 'sku' ? 'selected' : ''; ?>>SKU/Barcode</option>
      <option value="category" <?php echo $sortBy === 'category' ? 'selected' : ''; ?>>Category</option>
      <option value="price" <?php echo $sortBy === 'price' ? 'selected' : ''; ?>>Price</option>
      <option value="stock" <?php echo $sortBy === 'stock' ? 'selected' : ''; ?>>Stock Level</option>
      <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
      <option value="value" <?php echo $sortBy === 'value' ? 'selected' : ''; ?>>Value</option>
      <option value="updated" <?php echo $sortBy === 'updated' ? 'selected' : ''; ?>>Last Updated</option>
    </select>
  </div>
  
  <!-- Sort Order -->
  <div class="filter-group">
    <select class="form-select" id="sort-order" style="width: auto; min-width: 100px;">
      <option value="asc" <?php echo $sortOrder === 'asc' ? 'selected' : ''; ?>>↑ Ascending</option>
      <option value="desc" <?php echo $sortOrder === 'desc' ? 'selected' : ''; ?>>↓ Descending</option>
    </select>
  </div>
  
  <!-- Per Page -->
  <div class="filter-group">
    <span class="filter-label">Show:</span>
    <select class="form-select" id="per-page" style="width: auto; min-width: 80px;">
      <option value="6" <?php echo $itemsPerPage === 6 ? 'selected' : ''; ?>>6</option>
      <option value="10" <?php echo $itemsPerPage === 10 ? 'selected' : ''; ?>>10</option>
      <option value="25" <?php echo $itemsPerPage === 25 ? 'selected' : ''; ?>>25</option>
      <option value="50" <?php echo $itemsPerPage === 50 ? 'selected' : ''; ?>>50</option>
      <option value="100" <?php echo $itemsPerPage === 100 ? 'selected' : ''; ?>>100</option>
    </select>
  </div>
  
  <!-- Clear Filters -->
  <?php if ($statusFilter !== 'all' || $categoryFilter !== 'all' || !empty($searchQuery)): ?>
  <a href="inventory-list.php" class="btn btn-ghost btn-sm" style="color: var(--inventory-primary);">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
      <path d="M6 18L18 6M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Clear
  </a>
  <?php endif; ?>
</div>

<!-- Bulk Actions Bar (Hidden by default) -->
<div class="bulk-actions-bar" id="bulk-actions-bar" style="display: none;">
  <div style="display: flex; align-items: center; gap: 1rem;">
    <input type="checkbox" id="select-all-checkbox" onclick="toggleSelectAll()" style="width: 18px; height: 18px; cursor: pointer;">
    <span style="font-weight: 600; color: var(--inventory-primary);" id="selected-count">0 items selected</span>
  </div>
  <div style="display: flex; gap: 0.5rem;">
    <button class="btn btn-sm" style="background: var(--inventory-primary); color: white; border: none;" onclick="bulkEdit()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2"/>
        <path d="M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/>
      </svg>
      Bulk Edit
    </button>
    <button class="btn btn-secondary btn-sm" onclick="bulkDelete()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2"/>
      </svg>
      Delete
    </button>
    <button class="btn btn-ghost btn-sm" onclick="toggleBulkMode()">
      Cancel
    </button>
  </div>
</div>

<!-- Inventory Table -->
<?php if (isset($error)): ?>
<div class="alert alert-danger" style="margin-bottom: 1.5rem;">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
    <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
  </svg>
  <span><?php echo htmlspecialchars($error); ?></span>
</div>
<?php endif; ?>

<?php if (empty($items)): ?>
<div class="card">
  <div class="card-content">
    <div class="empty-state">
      <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none">
        <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 5C9 6.10457 9.89543 7 11 7H13C14.1046 7 15 6.10457 15 5M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5M9 12H15M9 16H12" stroke="currentColor" stroke-width="2"/>
      </svg>
      <p class="empty-state-title">
        <?php 
        if (!empty($searchQuery)) {
          echo "No items found for \"" . htmlspecialchars($searchQuery) . "\"";
        } elseif ($filterType === 'low_stock') {
          echo "No low stock items";
        } elseif ($filterType === 'out_of_stock') {
          echo "No out of stock items";
        } else {
          echo "No inventory items found";
        }
        ?>
      </p>
      <p class="empty-state-description">
        <?php if (empty($searchQuery) && $filterType === 'all'): ?>
          Start by adding your first inventory item
        <?php else: ?>
          Try adjusting your search or filters
        <?php endif; ?>
      </p>
      <?php if (empty($searchQuery) && $filterType === 'all'): ?>
        <a href="add_item.php" class="btn btn-primary">Add First Item</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>

<!-- Print Summary (hidden on screen, visible on print - appears FIRST in print) -->
<div class="print-summary" style="display: none;">
  <h3>INVENTORY SUMMARY</h3>
  <div class="print-summary-grid">
    <div class="print-summary-item">
      <span>Total Items:</span>
      <strong><?php echo number_format($totalItems); ?></strong>
    </div>
    <div class="print-summary-item">
      <span>Total Quantity:</span>
      <strong><?php echo number_format($totalQuantity); ?> units</strong>
    </div>
    <div class="print-summary-item">
      <span>Total Inventory Value:</span>
      <strong><?php echo CurrencyHelper::format($totalValue); ?></strong>
    </div>
    <div class="print-summary-item">
      <span>Stock Alerts:</span>
      <strong><?php echo $lowStockCount + $outOfStockCount; ?> items</strong>
    </div>
    <div class="print-summary-item">
      <span>Low Stock:</span>
      <strong><?php echo $lowStockCount; ?> items</strong>
    </div>
    <div class="print-summary-item">
      <span>Out of Stock:</span>
      <strong><?php echo $outOfStockCount; ?> items</strong>
    </div>
  </div>
</div>

<!-- Custom Print Items List (Computation Focus) -->
<div class="print-items-list" style="display: none;">
  <!-- Header Row -->
  <div class="print-list-header">
    <div>#</div>
    <div>Item Name</div>
    <div style="text-align: right;">Unit Price</div>
    <div style="text-align: center;">Qty Available</div>
    <div style="text-align: right;">Total Value</div>
  </div>
  
  <!-- Items -->
  <?php 
  $printCounter = 1;
  foreach ($allItems as $item): 
    $itemId = (string)$item['_id'];
    $quantity = $item['quantity'] ?? 0;
    $price = (float)($item['sell_price'] ?? $item['price'] ?? 0);
    $itemValue = $quantity * $price;
  ?>
  <div class="print-item-card">
    <!-- Number -->
    <div class="print-item-num"><?php echo $printCounter++; ?></div>
    
    <!-- Item Name -->
    <div class="print-item-name"><?php echo htmlspecialchars($item['name'] ?? 'Unnamed Item'); ?></div>
    
    <!-- Unit Price -->
    <div class="print-item-price"><?php echo CurrencyHelper::format($price); ?></div>
    
    <!-- Quantity Available -->
    <div class="print-item-stock"><?php echo number_format($quantity); ?></div>
    
    <!-- Total Value -->
    <div class="print-item-value"><?php echo CurrencyHelper::format($itemValue); ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="inventory-table-container">
  <table class="inventory-table">
    <thead>
      <tr>
        <th style="width: 40px;">
          <input type="checkbox" class="bulk-checkbox" id="header-checkbox" style="display: none; width: 18px; height: 18px; cursor: pointer;" onclick="toggleSelectAll()">
        </th>
        <th style="width: 60px;">#</th>
        <th style="width: 110px; text-align: center;">
          <span class="sortable-header <?php echo $sortBy === 'sku' || $sortBy === 'barcode' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('sku')" title="Click to sort | Long-press any code to toggle mode">
            <span id="barcode-header-label" style="display: inline-flex; align-items: center; gap: 0.25rem; transition: all 0.3s;">
              <span style="font-size: 0.875rem; font-weight: 600; letter-spacing: 0.02em;">Barcode</span>
              <span class="mode-indicator" style="display: inline-block; width: 6px; height: 6px; background: hsl(199 89% 48%); border-radius: 50%; box-shadow: 0 0 0 2px hsla(199, 89%, 48%, 0.2); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); opacity: 0; transform: scale(0.5);"></span>
            </span>
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="min-width: 200px;">
          <span class="sortable-header <?php echo $sortBy === 'name' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('name')">
            Item Name
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 120px;">
          <span class="sortable-header <?php echo $sortBy === 'category' || $sortBy === 'type' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('category')">
            Category
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 100px;">
          <span class="sortable-header <?php echo $sortBy === 'price' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('price')">
            Price
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 140px;">
          <span class="sortable-header <?php echo $sortBy === 'stock' || $sortBy === 'quantity' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('stock')">
            Stock Level
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 100px;">
          <span class="sortable-header <?php echo $sortBy === 'status' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('status')">
            Status
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 120px;">
          <span class="sortable-header <?php echo $sortBy === 'value' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('value')">
            Value
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 120px;">
          <span class="sortable-header <?php echo $sortBy === 'updated' || $sortBy === 'date' ? 'active ' . $sortOrder : ''; ?>" onclick="sortTable('updated')">
            Last Updated
            <span class="sort-icon">
              <span class="sort-arrow up"></span>
              <span class="sort-arrow down"></span>
            </span>
          </span>
        </th>
        <th style="width: 140px; text-align: center;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $counter = $offset + 1;
      foreach ($items as $item): 
        $itemId = (string)$item['_id'];
        $quantity = $item['quantity'] ?? 0;
        // Use sell_price field (which is stored in database) instead of price
        $price = (float)($item['sell_price'] ?? $item['price'] ?? 0);
        $itemValue = $quantity * $price;
        $isLowStock = $quantity > 0 && $quantity <= 5;
        $isOutOfStock = $quantity == 0;
        $dateAdded = isset($item['date_added']) ? $item['date_added']->toDateTime()->format('M d, Y') : 'N/A';
        
        // Stock level percentage (assume max stock is 50 for visual indicator)
        $maxStock = 50;
        $stockPercent = min(100, ($quantity / $maxStock) * 100);
        $stockLevel = $quantity > 5 ? 'high' : ($quantity > 0 ? 'medium' : 'low');
      ?>
      <tr data-item-id="<?php echo $itemId; ?>">
        <td data-label="">
          <input type="checkbox" class="bulk-checkbox item-checkbox" data-item-id="<?php echo $itemId; ?>" style="display: none; width: 18px; height: 18px; cursor: pointer;" onclick="updateSelectedCount()">
        </td>
        <td data-label="#" style="color: var(--text-secondary); font-weight: 500;"><?php echo $counter++; ?></td>
        <td data-label="Barcode">
          <?php
            // Generate proper fallback values
            $skuValue = !empty($item['sku']) ? $item['sku'] : 'SKU-' . str_pad($itemId, 6, '0', STR_PAD_LEFT);
            $upcValue = !empty($item['barcode']) && $item['barcode'] !== $item['sku'] ? $item['barcode'] : 'UPC-' . str_pad($itemId, 12, '0', STR_PAD_LEFT);
            $barcodeValue = !empty($item['barcode']) ? $item['barcode'] : (!empty($item['sku']) ? $item['sku'] : 'BC-' . str_pad($itemId, 10, '0', STR_PAD_LEFT));
          ?>
          <div class="barcode-container" 
               data-sku="<?php echo htmlspecialchars($skuValue); ?>"
               data-upc="<?php echo htmlspecialchars($upcValue); ?>"
               data-barcode="<?php echo htmlspecialchars($barcodeValue); ?>"
               data-mode="barcode"
               style="display: flex; flex-direction: column; gap: 0.25rem; align-items: center; cursor: pointer; transition: all 0.2s; position: relative; padding: 0.25rem 0.5rem; border-radius: 6px;" 
               onmousedown="handleBarcodePress(event, this)"
               onmouseup="handleBarcodeRelease(event, this)"
               onmouseleave="cancelBarcodePress(event, this)"
               ontouchstart="handleBarcodePress(event, this)"
               ontouchend="handleBarcodeRelease(event, this)"
               ontouchcancel="cancelBarcodePress(event, this)"
               onmouseenter="this.style.background='hsl(240 5% 98%)'"
               onmouseleave="if(!this.classList.contains('long-pressing')) { this.style.background='transparent'; }"
               title="Click: Copy | Long press: Toggle SKU/UPC/Barcode">
            <div style="position: absolute; top: -2px; right: -2px; opacity: 0; transition: opacity 0.2s;" class="mode-badge">
              <span style="display: inline-block; padding: 0.125rem 0.375rem; background: hsl(199 89% 48%); color: white; border-radius: 4px; font-size: 0.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">BAR</span>
            </div>
            <svg class="barcode-svg" data-barcode="<?php echo htmlspecialchars($barcodeValue); ?>" style="max-width: 80px; height: 24px;"></svg>
            <span class="barcode-text font-mono" style="font-size: 0.625rem; color: hsl(240 5% 50%); line-height: 1; display: flex; align-items: center; gap: 0.25rem;">
              <?php echo htmlspecialchars(substr($barcodeValue, 0, 12)); ?><?php echo strlen($barcodeValue) > 12 ? '...' : ''; ?>
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity: 0.5;" class="copy-icon">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
              </svg>
            </span>
          </div>
        </td>
        <td data-label="Item Name">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 40px; height: 40px; background: var(--inventory-primary-light); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--inventory-primary); font-size: 0.875rem;">
              <?php echo strtoupper(substr($item['name'] ?? 'I', 0, 1)); ?>
            </div>
            <div>
              <div style="font-weight: 600; color: var(--text-primary);">
                <?php echo htmlspecialchars($item['name'] ?? 'Unnamed Item'); ?>
              </div>
              <?php if (!empty($item['lifespan'])): ?>
              <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.125rem;">
                Lifespan: <?php echo htmlspecialchars($item['lifespan']); ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td data-label="Category">
          <span class="inventory-badge inventory-badge-primary">
            <?php echo htmlspecialchars($item['type'] ?? 'General'); ?>
          </span>
        </td>
        <td data-label="Price">
          <span style="font-weight: 600; color: var(--inventory-primary);">
            <?php echo CurrencyHelper::format($price); ?>
          </span>
        </td>
        <td data-label="Stock Level">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-weight: 700; color: var(--text-primary); min-width: 35px;">
              <?php echo $quantity; ?>
            </span>
            <div class="stock-level-bar">
              <div class="stock-level-fill <?php echo $stockLevel; ?>" style="width: <?php echo $stockPercent; ?>%;"></div>
            </div>
          </div>
        </td>
        <td data-label="Status">
          <?php if ($isOutOfStock): ?>
            <span class="inventory-badge inventory-badge-danger">Out of Stock</span>
          <?php elseif ($isLowStock): ?>
            <span class="inventory-badge inventory-badge-warning">Low Stock</span>
          <?php else: ?>
            <span class="inventory-badge inventory-badge-success">In Stock</span>
          <?php endif; ?>
        </td>
        <td data-label="Value">
          <span style="font-weight: 600; color: var(--text-primary);">
            <?php echo CurrencyHelper::format($itemValue); ?>
          </span>
        </td>
        <td data-label="Last Updated" style="color: var(--text-secondary); font-size: 0.8125rem;">
          <?php echo $dateAdded; ?>
        </td>
        <td data-label="Actions">
          <div style="display: flex; gap: 0.375rem; justify-content: center;">
            <a href="edit_item.php?id=<?php echo $itemId; ?>" 
               class="btn btn-ghost btn-sm" 
               style="padding: 0.375rem 0.5rem;"
               title="Edit Item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2"/>
                <path d="M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </a>
            <button 
              onclick="quickView('<?php echo $itemId; ?>')" 
              class="btn btn-ghost btn-sm" 
              style="padding: 0.375rem 0.5rem; color: var(--inventory-primary);"
              title="Quick View">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button 
              onclick="deleteItem('<?php echo $itemId; ?>', '<?php echo htmlspecialchars($item['name'] ?? 'this item'); ?>')" 
              class="btn btn-ghost btn-sm" 
              style="padding: 0.375rem 0.5rem; color: hsl(0 74% 24%);"
              title="Delete Item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Print-Only Table (ALL ITEMS) - Hidden on screen, visible on print -->
<div class="inventory-table-container print-only" style="display: none;">
  <table class="inventory-table">
    <thead>
      <tr>
        <th style="width: 40px;"></th>
        <th style="width: 60px;">#</th>
        <th style="width: 110px; text-align: center;">Barcode</th>
        <th style="min-width: 200px;">Item Name</th>
        <th style="width: 120px;">Category</th>
        <th style="width: 100px;">Price</th>
        <th style="width: 140px;">Stock Level</th>
        <th style="width: 100px;">Status</th>
        <th style="width: 120px;">Value</th>
        <th style="width: 120px;">Last Updated</th>
        <th style="width: 140px; text-align: center;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $printCounter = 1;
      foreach ($allItems as $item): 
        $itemId = (string)$item['_id'];
        $quantity = $item['quantity'] ?? 0;
        $price = (float)($item['sell_price'] ?? $item['price'] ?? 0);
        $itemValue = $quantity * $price;
        $isLowStock = $quantity > 0 && $quantity <= 5;
        $isOutOfStock = $quantity == 0;
        $dateAdded = isset($item['date_added']) ? $item['date_added']->toDateTime()->format('M d, Y') : 'N/A';
        $maxStock = 50;
        $stockPercent = min(100, ($quantity / $maxStock) * 100);
        $stockLevel = $quantity > 5 ? 'high' : ($quantity > 0 ? 'medium' : 'low');
        
        $skuValue = !empty($item['sku']) ? $item['sku'] : 'SKU-' . str_pad($itemId, 6, '0', STR_PAD_LEFT);
        $upcValue = !empty($item['barcode']) && $item['barcode'] !== $item['sku'] ? $item['barcode'] : 'UPC-' . str_pad($itemId, 12, '0', STR_PAD_LEFT);
        $barcodeValue = !empty($item['barcode']) ? $item['barcode'] : (!empty($item['sku']) ? $item['sku'] : 'BC-' . str_pad($itemId, 10, '0', STR_PAD_LEFT));
      ?>
      <tr data-item-id="<?php echo $itemId; ?>">
        <td data-label=""></td>
        <td data-label="#" style="color: var(--text-secondary); font-weight: 500;"><?php echo $printCounter++; ?></td>
        <td data-label="Barcode">
          <div class="barcode-container" 
               data-sku="<?php echo htmlspecialchars($skuValue); ?>"
               data-upc="<?php echo htmlspecialchars($upcValue); ?>"
               data-barcode="<?php echo htmlspecialchars($barcodeValue); ?>"
               data-mode="barcode"
               style="display: flex; flex-direction: column; gap: 0.25rem; align-items: center;">
            <svg class="barcode-svg" data-barcode="<?php echo htmlspecialchars($barcodeValue); ?>" style="max-width: 80px; height: 24px;"></svg>
            <span class="barcode-text font-mono" style="font-size: 0.625rem; color: hsl(240 5% 50%); line-height: 1;">
              <?php echo htmlspecialchars(substr($barcodeValue, 0, 12)); ?><?php echo strlen($barcodeValue) > 12 ? '...' : ''; ?>
            </span>
          </div>
        </td>
        <td data-label="Item Name">
          <div style="font-weight: 600; color: var(--text-primary);">
            <?php echo htmlspecialchars($item['name'] ?? 'Unnamed Item'); ?>
          </div>
        </td>
        <td data-label="Category">
          <span class="inventory-badge inventory-badge-primary">
            <?php echo htmlspecialchars($item['type'] ?? 'General'); ?>
          </span>
        </td>
        <td data-label="Price">
          <span style="font-weight: 600; color: var(--inventory-primary);">
            <?php echo CurrencyHelper::format($price); ?>
          </span>
        </td>
        <td data-label="Stock Level">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-weight: 700; color: var(--text-primary); min-width: 35px;">
              <?php echo $quantity; ?>
            </span>
          </div>
        </td>
        <td data-label="Status">
          <?php if ($isOutOfStock): ?>
            <span class="inventory-badge inventory-badge-danger">Out of Stock</span>
          <?php elseif ($isLowStock): ?>
            <span class="inventory-badge inventory-badge-warning">Low Stock</span>
          <?php else: ?>
            <span class="inventory-badge inventory-badge-success">In Stock</span>
          <?php endif; ?>
        </td>
        <td data-label="Value">
          <span style="font-weight: 600; color: var(--text-primary);">
            <?php echo CurrencyHelper::format($itemValue); ?>
          </span>
        </td>
        <td data-label="Last Updated" style="color: var(--text-secondary); font-size: 0.8125rem;">
          <?php echo $dateAdded; ?>
        </td>
        <td data-label="Actions"></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<div class="mt-6" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
  <!-- Summary -->
  <div class="text-sm text-secondary">
    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $itemsPerPage, $totalItems); ?></strong> of <strong><?php echo $totalItems; ?></strong> 
    <?php echo $totalItems === 1 ? 'item' : 'items'; ?>
  </div>
  
  <!-- Pagination Controls (AJAX) -->
  <?php if ($totalPages > 1): ?>
  <div id="pagination-controls" style="display: flex; gap: 0.25rem; align-items: center;">
    <!-- Previous Button -->
    <?php if ($currentPage > 1): ?>
      <button onclick="goToPage(<?php echo $currentPage - 1; ?>)" 
         class="btn btn-ghost btn-sm" 
         style="padding: 0.5rem 0.75rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Previous
      </button>
    <?php else: ?>
      <button class="btn btn-ghost btn-sm" disabled style="padding: 0.5rem 0.75rem; opacity: 0.5;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Previous
      </button>
    <?php endif; ?>
    
    <!-- Page Numbers -->
    <div style="display: flex; gap: 0.25rem;">
      <?php
      $startPage = max(1, $currentPage - 2);
      $endPage = min($totalPages, $currentPage + 2);
      
      // Show first page if not in range
      if ($startPage > 1):
      ?>
        <button onclick="goToPage(1)" 
           class="btn btn-ghost btn-sm" 
           style="min-width: 2.5rem; padding: 0.5rem;">
          1
        </button>
        <?php if ($startPage > 2): ?>
        <span style="padding: 0.5rem; color: var(--text-muted);">...</span>
        <?php endif; ?>
      <?php endif; ?>
      
      <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
        <?php if ($page === $currentPage): ?>
          <button class="btn btn-primary btn-sm" 
                  style="min-width: 2.5rem; padding: 0.5rem; font-weight: 600;">
            <?php echo $page; ?>
          </button>
        <?php else: ?>
          <button onclick="goToPage(<?php echo $page; ?>)" 
             class="btn btn-ghost btn-sm" 
             style="min-width: 2.5rem; padding: 0.5rem;">
            <?php echo $page; ?>
          </button>
        <?php endif; ?>
      <?php endfor; ?>
      
      <!-- Show last page if not in range -->
      <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
        <span style="padding: 0.5rem; color: var(--text-muted);">...</span>
        <?php endif; ?>
        <button onclick="goToPage(<?php echo $totalPages; ?>)" 
           class="btn btn-ghost btn-sm" 
           style="min-width: 2.5rem; padding: 0.5rem;">
          <?php echo $totalPages; ?>
        </button>
      <?php endif; ?>
    </div>
    
    <!-- Next Button -->
    <?php if ($currentPage < $totalPages): ?>
      <button onclick="goToPage(<?php echo $currentPage + 1; ?>)" 
         class="btn btn-ghost btn-sm" 
         style="padding: 0.5rem 0.75rem;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    <?php else: ?>
      <button class="btn btn-ghost btn-sm" disabled style="padding: 0.5rem 0.75rem; opacity: 0.5;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  
  <!-- Clear Filters -->
  <?php if ($filterType !== 'all' || !empty($searchQuery)): ?>
  <div>
    <a href="inventory-list.php" class="text-primary text-sm">Clear filters</a>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Quick View Modal -->
<div id="quickViewModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.4); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 1rem;">
  <div style="background: white; border-radius: 16px; max-width: 1300px; width: 95%; max-height: 90vh; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid hsl(240 6% 90%); position: relative; animation: modalSlideIn 0.3s ease-out;">
    <!-- Modal Header -->
    <div style="padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%); display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, hsl(220 20% 97%) 0%, white 100%);">
      <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
        <div style="width: 48px; height: 48px; background: var(--inventory-primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: white; box-shadow: 0 3px 10px rgba(113, 148, 165, 0.3);" id="qv-avatar">
          ?
        </div>
        <div style="flex: 1;">
          <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: hsl(240 10% 10%); line-height: 1.2;" id="qv-title">Item Details</h2>
          <p style="font-size: 0.75rem; margin: 0.25rem 0 0; color: hsl(240 5% 50%); font-weight: 500;" id="qv-subtitle">Loading...</p>
        </div>
      </div>
      <button onclick="closeQuickView()" style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 90%); width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: hsl(240 5% 40%); transition: all 0.2s; flex-shrink: 0;" onmouseover="this.style.background='hsl(240 5% 92%)'; this.style.borderColor='hsl(240 6% 85%)'" onmouseout="this.style.background='hsl(240 5% 96%)'; this.style.borderColor='hsl(240 6% 90%)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M18 6L6 18M6 6L18 18" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
    
    <!-- Modal Body -->
    <div style="overflow-y: auto; max-height: calc(90vh - 200px);">
      <!-- Loading State -->
      <div id="qv-loading" style="text-align: center; padding: 4rem 2rem;">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite; color: var(--inventory-primary); margin: 0 auto;">
          <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
          <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
        </svg>
        <p style="margin-top: 1.5rem; color: hsl(240 5% 50%); font-weight: 500;">Loading item details...</p>
      </div>
      
      <!-- Content (Hidden initially) -->
      <div id="qv-content" style="display: none;">
        
        <!-- Tabs -->
        <div style="border-bottom: 1px solid hsl(240 6% 90%); background: hsl(240 5% 98%);">
          <div style="display: flex; padding: 0 1.5rem;">
            <button class="qv-tab active" data-tab="details" onclick="switchQvTab('details')" style="padding: 0.875rem 1.25rem; border: none; background: transparent; cursor: pointer; font-weight: 600; font-size: 0.8125rem; color: hsl(240 5% 40%); border-bottom: 2px solid transparent; transition: all 0.2s;">
              Details
            </button>
            <button class="qv-tab" data-tab="specs" onclick="switchQvTab('specs')" style="padding: 0.875rem 1.25rem; border: none; background: transparent; cursor: pointer; font-weight: 600; font-size: 0.8125rem; color: hsl(240 5% 40%); border-bottom: 2px solid transparent; transition: all 0.2s;">
              Specifications
            </button>
            <button class="qv-tab" data-tab="additional" onclick="switchQvTab('additional')" style="padding: 0.875rem 1.25rem; border: none; background: transparent; cursor: pointer; font-weight: 600; font-size: 0.8125rem; color: hsl(240 5% 40%); border-bottom: 2px solid transparent; transition: all 0.2s;">
              Additional Info
            </button>
          </div>
        </div>
        
        <div style="padding: 1.5rem;">
          <!-- Tab: Details -->
          <div id="qv-tab-details" class="qv-tab-content" style="display: block;">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Barcode / UPC</div>
                <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="qv-barcode">-</div>
              </div>
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">SKU / Code</div>
                <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="qv-sku">-</div>
              </div>
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Category</div>
                <div><span class="inventory-badge inventory-badge-primary" id="qv-type" style="font-size: 0.6875rem; padding: 0.25rem 0.5rem;">-</span></div>
              </div>
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Shelf Life</div>
                <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="qv-lifespan">-</div>
              </div>
            </div>
            
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: hsl(240 5% 98%); border-radius: 8px; border: 1px solid hsl(240 6% 90%);">
              <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Description</div>
              <div style="font-weight: 500; line-height: 1.5; color: hsl(240 5% 35%); font-size: 0.8125rem;" id="qv-description">-</div>
            </div>
            
            <!-- Bottom Row: Pricing Left, Inventory Right -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
              
              <!-- Pricing Card (Bottom Left) -->
              <div style="background: linear-gradient(135deg, hsl(143 85% 98%) 0%, hsl(143 85% 95%) 100%); border: 1px solid hsl(143 80% 85%); border-radius: 10px; padding: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 1rem;">
                  <div style="width: 28px; height: 28px; background: hsl(140 61% 50%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                      <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                  </div>
                  <h3 style="font-size: 0.6875rem; font-weight: 700; color: hsl(140 61% 20%); margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Pricing</h3>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                  <div>
                    <div style="font-size: 0.5625rem; font-weight: 600; color: hsl(140 61% 30%); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Cost</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: hsl(140 61% 20%);" id="qv-cost-price">-</div>
                  </div>
                  <div>
                    <div style="font-size: 0.5625rem; font-weight: 600; color: hsl(140 61% 30%); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Selling</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: hsl(140 61% 20%); line-height: 1;" id="qv-sell-price">-</div>
                  </div>
                  <div>
                    <div style="font-size: 0.5625rem; font-weight: 600; color: hsl(140 61% 30%); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Tax</div>
                    <div style="font-weight: 600; color: hsl(140 61% 20%); font-size: 0.9375rem;" id="qv-tax-rate">-</div>
                  </div>
                </div>
              </div>
              
              <!-- Stock & Inventory Card (Bottom Right) -->
              <div style="background: linear-gradient(135deg, hsl(214 95% 96%) 0%, hsl(214 95% 93%) 100%); border: 1px solid hsl(214 90% 80%); border-radius: 10px; padding: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 1rem;">
                  <div style="width: 28px; height: 28px; background: hsl(214 95% 50%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="7.5 4.21 12 6.81 16.5 4.21"/><polyline points="7.5 19.79 7.5 14.6 3 12"/><polyline points="21 12 16.5 14.6 16.5 19.79"/><polyline points="3 12 7.5 14.6 12 12"/><line x1="12" y1="22" x2="12" y2="12"/>
                    </svg>
                  </div>
                  <h3 style="font-size: 0.6875rem; font-weight: 700; color: hsl(214 95% 20%); margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Stock & Inventory</h3>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                  <div>
                    <div style="font-size: 0.5625rem; font-weight: 600; color: hsl(214 90% 30%); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Stock</div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                      <span style="font-size: 1.5rem; font-weight: 800; color: hsl(214 95% 20%); line-height: 1;" id="qv-quantity">-</span>
                      <span id="qv-status-badge"></span>
                    </div>
                  </div>
                  <div>
                    <div style="font-size: 0.5625rem; font-weight: 600; color: hsl(214 90% 30%); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Range</div>
                    <div style="font-weight: 600; color: hsl(214 95% 20%); font-size: 0.9375rem;" id="qv-stock-range">-</div>
                  </div>
                  <div>
                    <div style="font-size: 0.5625rem; font-weight: 600; color: hsl(214 90% 30%); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Value</div>
                    <div style="font-size: 1.125rem; font-weight: 700; color: hsl(214 95% 20%);" id="qv-total-value">-</div>
                  </div>
                </div>
              </div>
              
            </div>
          </div>
          
          <!-- Tab: Specifications -->
          <div id="qv-tab-specs" class="qv-tab-content" style="display: none;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase;">Unit of Measure</div>
                <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="qv-uom">-</div>
              </div>
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase;">Brand</div>
                <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="qv-brand">-</div>
              </div>
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase;">Date Added</div>
                <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="qv-date-added">-</div>
              </div>
            </div>
          </div>
          
          <!-- Tab: Additional Info -->
          <div id="qv-tab-additional" class="qv-tab-content" style="display: none;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase;">Location</div>
                <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="qv-location">-</div>
              </div>
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase;">Supplier</div>
                <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="qv-supplier">-</div>
              </div>
              <div>
                <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase;">Reorder Point</div>
                <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;" id="qv-reorder">-</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div style="padding: 1.5rem 2rem; border-top: 1px solid hsl(240 6% 90%); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(180deg, white 0%, hsl(240 5% 98%) 100%);">
      <button onclick="closeQuickView()" style="padding: 0.625rem 1.25rem; border: 1px solid hsl(240 6% 85%); border-radius: 8px; background: white; cursor: pointer; font-weight: 600; color: hsl(240 5% 40%); transition: all 0.2s; font-size: 0.875rem;" onmouseover="this.style.background='hsl(240 5% 96%)'; this.style.borderColor='hsl(240 6% 80%)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(240 6% 85%)'">
        Close
      </button>
      <a href="#" id="qv-edit-btn" style="padding: 0.625rem 1.5rem; border: none; border-radius: 8px; background: var(--inventory-primary); color: white; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.625rem; transition: all 0.2s; box-shadow: 0 2px 8px rgba(113, 148, 165, 0.2); font-size: 0.875rem;" onmouseover="this.style.background='hsl(199 35% 50%)'; this.style.boxShadow='0 4px 12px rgba(113, 148, 165, 0.3)'" onmouseout="this.style.background='var(--inventory-primary)'; this.style.boxShadow='0 2px 8px rgba(113, 148, 165, 0.2)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13"/>
          <path d="M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z"/>
        </svg>
        Edit Item
      </a>
    </div>
  </div>
</div>

<!-- Modal Animation CSS -->
<style>
@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(-10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

#quickViewModal {
  animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Tab Styling */
.qv-tab {
  position: relative;
}

.qv-tab.active {
  color: var(--inventory-primary) !important;
  border-bottom-color: var(--inventory-primary) !important;
  background: white !important;
}

.qv-tab:hover:not(.active) {
  color: hsl(240 5% 20%) !important;
  background: hsl(240 5% 96%) !important;
}
</style>

<script>
// ============================================
// GLOBAL CONSTANTS
// ============================================
const currencyCode = 'PHP'; // Philippine Peso

// ============================================
// HELPER FUNCTIONS
// ============================================
function formatCurrency(amount) {
  try {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: currencyCode
    }).format(amount);
  } catch (error) {
    console.error('Currency formatting error:', error);
    return '₱' + parseFloat(amount).toFixed(2);
  }
}

// ============================================
// BULK SELECTION MODE
// ============================================
let bulkModeActive = false;
const selectedItems = new Set();

function toggleBulkMode() {
  bulkModeActive = !bulkModeActive;
  const bulkCheckboxes = document.querySelectorAll('.bulk-checkbox');
  const bulkActionsBar = document.getElementById('bulk-actions-bar');
  const bulkModeBtn = document.getElementById('bulk-mode-btn');
  
  bulkCheckboxes.forEach(cb => {
    cb.style.display = bulkModeActive ? 'inline-block' : 'none';
    if (!bulkModeActive) cb.checked = false;
  });
  
  bulkActionsBar.style.display = bulkModeActive ? 'flex' : 'none';
  bulkModeBtn.textContent = bulkModeActive ? 'Exit Bulk Mode' : 'Bulk Select';
  
  if (!bulkModeActive) {
    selectedItems.clear();
    updateSelectedCount();
  }
}

function toggleSelectAll() {
  const headerCheckbox = document.getElementById('header-checkbox');
  const itemCheckboxes = document.querySelectorAll('.item-checkbox');
  const isChecked = headerCheckbox.checked;
  
  itemCheckboxes.forEach(cb => {
    cb.checked = isChecked;
    const itemId = cb.dataset.itemId;
    if (isChecked) {
      selectedItems.add(itemId);
    } else {
      selectedItems.delete(itemId);
    }
  });
  
  updateSelectedCount();
}

function updateSelectedCount() {
  const itemCheckboxes = document.querySelectorAll('.item-checkbox');
  selectedItems.clear();
  
  itemCheckboxes.forEach(cb => {
    if (cb.checked) selectedItems.add(cb.dataset.itemId);
  });
  
  const count = selectedItems.size;
  const countEl = document.getElementById('selected-count');
  countEl.textContent = `${count} item${count !== 1 ? 's' : ''} selected`;
  
  // Update header checkbox state
  const headerCheckbox = document.getElementById('header-checkbox');
  if (count === 0) {
    headerCheckbox.checked = false;
    headerCheckbox.indeterminate = false;
  } else if (count === itemCheckboxes.length) {
    headerCheckbox.checked = true;
    headerCheckbox.indeterminate = false;
  } else {
    headerCheckbox.checked = false;
    headerCheckbox.indeterminate = true;
  }
}

function bulkEdit() {
  if (selectedItems.size === 0) {
    alert('Please select items to edit');
    return;
  }
  alert(`Bulk editing ${selectedItems.size} items (feature coming soon)`);
}

function bulkDelete() {
  if (selectedItems.size === 0) {
    alert('Please select items to delete');
    return;
  }
  
  if (confirm(`Are you sure you want to delete ${selectedItems.size} item${selectedItems.size !== 1 ? 's' : ''}?`)) {
    alert('Deleting items... (feature coming soon)');
  }
}

// ============================================
// AJAX INVENTORY LOADING (NO PAGE REFRESH)
// ============================================
function loadInventory(params = {}) {
  const url = new URL('/api/inventory.php', window.location.origin);
  const currentParams = new URLSearchParams(window.location.search);
  
  // Merge current params with new ones
  for (const [key, value] of currentParams.entries()) {
    if (!params.hasOwnProperty(key)) {
      params[key] = value;
    }
  }
  
  // Set action
  url.searchParams.set('action', 'list');
  
  // Apply all params
  Object.keys(params).forEach(key => {
    const value = params[key];
    if (value && value !== 'all' && value !== null) {
      url.searchParams.set(key, value);
    } else {
      url.searchParams.delete(key);
    }
  });
  
  // Show loading state
  const tableContainer = document.querySelector('.inventory-table-container');
  const statsGrid = document.querySelector('.inventory-stats-grid');
  if (tableContainer) tableContainer.classList.add('inventory-loading');
  if (statsGrid) statsGrid.classList.add('filter-loading');
  
  // Fetch data
  fetch(url.toString())
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update stats
        updateStats(data.stats);
        
        // Update table
        updateTable(data.items);
        
        // Update pagination
        updatePagination(data.pagination);
        
        // Update URL without reload
        const newUrl = new URL(window.location.href);
        Object.keys(params).forEach(key => {
          if (params[key] && params[key] !== 'all') {
            newUrl.searchParams.set(key, params[key]);
          } else {
            newUrl.searchParams.delete(key);
          }
        });
        window.history.pushState({}, '', newUrl);
      }
    })
    .catch(error => console.error('Error loading inventory:', error))
    .finally(() => {
      // Remove loading state
      if (tableContainer) tableContainer.classList.remove('inventory-loading');
      if (statsGrid) statsGrid.classList.remove('filter-loading');
    });
}

function updateStats(stats) {
  const statCards = document.querySelectorAll('.inventory-stat-card');
  if (statCards[0]) {
    statCards[0].querySelector('.stat-value').textContent = stats.total_items.toLocaleString();
  }
  if (statCards[1]) {
    statCards[1].querySelector('.stat-value').textContent = stats.total_quantity.toLocaleString();
  }
  if (statCards[2]) {
    statCards[2].querySelector('.stat-value').textContent = stats.total_value_formatted;
  }
  if (statCards[3]) {
    const alertCount = stats.low_stock_count + stats.out_of_stock_count;
    statCards[3].querySelector('.stat-value').textContent = alertCount;
    statCards[3].querySelector('.stat-value').style.color = alertCount > 0 ? 'hsl(25 95% 16%)' : 'hsl(143 85% 30%)';
    statCards[3].querySelector('.stat-change span').textContent = `${stats.low_stock_count} low, ${stats.out_of_stock_count} out`;
  }
}

// Generate proper fallback values for barcode data
function generateBarcodeValues(item) {
  // Helper to check if value exists and is not empty
  const isValid = (val) => val && typeof val === 'string' && val.trim() !== '';
  
  // SKU: use item.sku if valid, otherwise generate
  const skuValue = isValid(item.sku) 
    ? item.sku.trim()
    : `SKU-${String(item.id).padStart(6, '0')}`;
  
  // UPC: use item.barcode if valid and different from SKU, otherwise generate
  const upcValue = isValid(item.barcode) && item.barcode !== item.sku
    ? item.barcode.trim()
    : `UPC-${String(item.id).padStart(12, '0')}`;
  
  // Barcode: prefer item.barcode, fallback to item.sku, then generate
  const barcodeValue = isValid(item.barcode)
    ? item.barcode.trim()
    : isValid(item.sku)
      ? item.sku.trim()
      : `BC-${String(item.id).padStart(10, '0')}`;
  
  return { skuValue, upcValue, barcodeValue };
}

function updateTable(items) {
  const tbody = document.querySelector('.inventory-table tbody');
  if (!tbody) return;
  
  if (items.length === 0) {
    tbody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No items found</td></tr>';
    return;
  }
  
  tbody.innerHTML = items.map(item => {
    const { skuValue, upcValue, barcodeValue } = generateBarcodeValues(item);
    return `
    <tr data-item-id="${item.id}">
      <td>
        <input type="checkbox" class="bulk-checkbox item-checkbox" data-item-id="${item.id}" style="display: none; width: 18px; height: 18px; cursor: pointer;" onclick="updateSelectedCount()">
      </td>
      <td style="color: var(--text-secondary); font-weight: 500;">${item.counter}</td>
      <td>
        <div class="barcode-container" 
             data-sku="${skuValue}"
             data-upc="${upcValue}"
             data-barcode="${barcodeValue}"
             data-mode="barcode"
             style="display: flex; flex-direction: column; gap: 0.25rem; align-items: center; cursor: pointer; transition: all 0.2s; position: relative; padding: 0.25rem 0.5rem; border-radius: 6px;" 
             onmousedown="handleBarcodePress(event, this)"
             onmouseup="handleBarcodeRelease(event, this)"
             onmouseleave="cancelBarcodePress(event, this)"
             ontouchstart="handleBarcodePress(event, this)"
             ontouchend="handleBarcodeRelease(event, this)"
             ontouchcancel="cancelBarcodePress(event, this)"
             onmouseenter="this.style.background='hsl(240 5% 98%)'"
             onmouseleave="if(!this.classList.contains('long-pressing')) { this.style.background='transparent'; }"
             title="Click: Copy | Long press: Toggle SKU/UPC/Barcode">
          <div style="position: absolute; top: -2px; right: -2px; opacity: 0; transition: opacity 0.2s;" class="mode-badge">
            <span style="display: inline-block; padding: 0.125rem 0.375rem; background: hsl(199 89% 48%); color: white; border-radius: 4px; font-size: 0.5rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">BAR</span>
          </div>
          <svg class="barcode-svg" data-barcode="${barcodeValue}" style="max-width: 80px; height: 24px;"></svg>
          <span class="barcode-text font-mono" style="font-size: 0.625rem; color: hsl(240 5% 50%); line-height: 1; display: flex; align-items: center; gap: 0.25rem;">
            ${barcodeValue.substring(0, 12)}${barcodeValue.length > 12 ? '...' : ''}
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity: 0.5;" class="copy-icon">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
          </span>
        </div>
      </td>
      <td>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <div style="width: 40px; height: 40px; background: var(--inventory-primary-light); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--inventory-primary); font-size: 0.875rem;">
            ${item.avatar_letter}
          </div>
          <div>
            <div style="font-weight: 600; color: var(--text-primary);">${item.name}</div>
            ${item.lifespan ? `<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.125rem;">Lifespan: ${item.lifespan}</div>` : ''}
          </div>
        </div>
      </td>
      <td>
        <span class="inventory-badge inventory-badge-primary">${item.type}</span>
      </td>
      <td>
        <span style="font-weight: 600; color: var(--inventory-primary);">${item.price_formatted}</span>
      </td>
      <td>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <span style="font-weight: 700; color: var(--text-primary); min-width: 35px;">${item.quantity}</span>
          <div class="stock-level-bar">
            <div class="stock-level-fill ${item.stock_level}" style="width: ${item.stock_percent}%;"></div>
          </div>
        </div>
      </td>
      <td>
        ${item.is_out_of_stock ? '<span class="inventory-badge inventory-badge-danger">Out of Stock</span>' : 
          item.is_low_stock ? '<span class="inventory-badge inventory-badge-warning">Low Stock</span>' : 
          '<span class="inventory-badge inventory-badge-success">In Stock</span>'}
      </td>
      <td>
        <span style="font-weight: 600; color: var(--text-primary);">${item.value_formatted}</span>
      </td>
      <td style="color: var(--text-secondary); font-size: 0.8125rem;">${item.date_added}</td>
      <td>
        <div style="display: flex; gap: 0.375rem; justify-content: center;">
          <a href="edit_item.php?id=${item.id}" class="btn btn-ghost btn-sm" style="padding: 0.375rem 0.5rem;" title="Edit Item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13" stroke="currentColor" stroke-width="2"/><path d="M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/></svg>
          </a>
          <button onclick="quickView('${item.id}')" class="btn btn-ghost btn-sm" style="padding: 0.375rem 0.5rem; color: var(--inventory-primary);" title="Quick View">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
          </button>
          <button onclick="deleteItem('${item.id}', '${item.name}')" class="btn btn-ghost btn-sm" style="padding: 0.375rem 0.5rem; color: hsl(0 74% 24%);" title="Delete Item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `;
  }).join('');
  
  // Render barcodes and sync to current mode after table update
  setTimeout(() => {
    renderBarcodes();
    syncBarcodesToCurrentMode();
  }, 150);
}

function updatePagination(pagination) {
  const paginationContainer = document.querySelector('.mt-6');
  if (!paginationContainer) return;
  
  // Update summary
  const summary = paginationContainer.querySelector('.text-sm');
  if (summary) {
    summary.innerHTML = `Showing <strong>${pagination.showing_from}</strong> to <strong>${pagination.showing_to}</strong> of <strong>${pagination.total_items}</strong> ${pagination.total_items === 1 ? 'item' : 'items'}`;
  }
  
  // Rebuild pagination controls
  const controls = document.getElementById('pagination-controls');
  if (!controls || pagination.total_pages <= 1) {
    if (controls) controls.style.display = 'none';
    return;
  }
  
  controls.style.display = 'flex';
  
  const currentPage = pagination.current_page;
  const totalPages = pagination.total_pages;
  const startPage = Math.max(1, currentPage - 2);
  const endPage = Math.min(totalPages, currentPage + 2);
  
  let html = '';
  
  // Previous button
  if (currentPage > 1) {
    html += `
      <button onclick="goToPage(${currentPage - 1})" class="btn btn-ghost btn-sm" style="padding: 0.5rem 0.75rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Previous
      </button>
    `;
  } else {
    html += `
      <button class="btn btn-ghost btn-sm" disabled style="padding: 0.5rem 0.75rem; opacity: 0.5;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Previous
      </button>
    `;
  }
  
  // Page numbers container
  html += '<div style="display: flex; gap: 0.25rem;">';
  
  // First page if not in range
  if (startPage > 1) {
    html += `<button onclick="goToPage(1)" class="btn btn-ghost btn-sm" style="min-width: 2.5rem; padding: 0.5rem;">1</button>`;
    if (startPage > 2) {
      html += '<span style="padding: 0.5rem; color: var(--text-muted);">...</span>';
    }
  }
  
  // Page number buttons
  for (let page = startPage; page <= endPage; page++) {
    if (page === currentPage) {
      html += `<button class="btn btn-primary btn-sm" style="min-width: 2.5rem; padding: 0.5rem; font-weight: 600;">${page}</button>`;
    } else {
      html += `<button onclick="goToPage(${page})" class="btn btn-ghost btn-sm" style="min-width: 2.5rem; padding: 0.5rem;">${page}</button>`;
    }
  }
  
  // Last page if not in range
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      html += '<span style="padding: 0.5rem; color: var(--text-muted);">...</span>';
    }
    html += `<button onclick="goToPage(${totalPages})" class="btn btn-ghost btn-sm" style="min-width: 2.5rem; padding: 0.5rem;">${totalPages}</button>`;
  }
  
  html += '</div>';
  
  // Next button
  if (currentPage < totalPages) {
    html += `
      <button onclick="goToPage(${currentPage + 1})" class="btn btn-ghost btn-sm" style="padding: 0.5rem 0.75rem;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    `;
  } else {
    html += `
      <button class="btn btn-ghost btn-sm" disabled style="padding: 0.5rem 0.75rem; opacity: 0.5;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    `;
  }
  
  controls.innerHTML = html;
}

// ============================================
// SEARCH & FILTERS (NO TOAST)
// ============================================
const searchInput = document.getElementById('inventory-search');
if (searchInput) {
  searchInput.addEventListener('input', debounce(function(e) {
    const query = e.target.value.trim();
    loadInventory({ search: query || null, page: 1 });
  }, 600));
}

const statusFilter = document.getElementById('status-filter');
if (statusFilter) {
  statusFilter.addEventListener('change', function(e) {
    loadInventory({ status: e.target.value, page: 1 });
  });
}

const categoryFilter = document.getElementById('category-filter');
if (categoryFilter) {
  categoryFilter.addEventListener('change', function(e) {
    loadInventory({ category: e.target.value, page: 1 });
  });
}

const sortBy = document.getElementById('sort-by');
if (sortBy) {
  sortBy.addEventListener('change', function(e) {
    loadInventory({ sort: e.target.value, page: 1 });
  });
}

const sortOrder = document.getElementById('sort-order');
if (sortOrder) {
  sortOrder.addEventListener('change', function(e) {
    loadInventory({ order: e.target.value, page: 1 });
  });
}

const perPage = document.getElementById('per-page');
if (perPage) {
  perPage.addEventListener('change', function(e) {
    loadInventory({ per_page: e.target.value, page: 1 });
  });
}

// ============================================
// PAGINATION (AJAX)
// ============================================
function goToPage(page) {
  loadInventory({ page: page });
}

// ============================================
// TABLE SORTING (NO TOAST, NO REFRESH)
// ============================================
function sortTable(column) {
  const url = new URL(window.location.href);
  const currentSort = url.searchParams.get('sort') || 'name';
  const currentOrder = url.searchParams.get('order') || 'asc';
  
  // Toggle order if clicking same column, otherwise default to asc
  let newOrder = 'asc';
  if (currentSort === column) {
    newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
  }
  
  // Load with new sort order (no page refresh)
  loadInventory({ sort: column, order: newOrder, page: 1 });
}

// ============================================
// QUICK VIEW MODAL
// ============================================
async function quickView(itemId) {
  console.log('Quick view for item:', itemId);
  
  const modal = document.getElementById('quickViewModal');
  const loading = document.getElementById('qv-loading');
  const content = document.getElementById('qv-content');
  
  // Show modal with loading state
  modal.style.display = 'flex';
  loading.style.display = 'block';
  content.style.display = 'none';
  
  try {
    // Fetch item details from API
    const response = await fetch(`/api/inventory.php?action=get_item&id=${itemId}`);
    const data = await response.json();
    
    if (!data.success) {
      throw new Error(data.error || 'Failed to load item details');
    }
    
    const item = data.item;
    
    // Populate modal with item data
    document.getElementById('qv-avatar').textContent = (item.name || 'I').charAt(0).toUpperCase();
    document.getElementById('qv-title').textContent = item.name || 'Unnamed Item';
    document.getElementById('qv-subtitle').textContent = `SKU: ${item.sku || item.barcode || 'N/A'}`;
    
    // Basic Information
    document.getElementById('qv-barcode').textContent = item.barcode || 'N/A';
    document.getElementById('qv-sku').textContent = item.sku || 'N/A';
    document.getElementById('qv-type').textContent = item.type || 'General';
    document.getElementById('qv-lifespan').textContent = item.lifespan || 'N/A';
    document.getElementById('qv-description').textContent = item.description || 'No description available';
    
    // Pricing
    const costPrice = parseFloat(item.cost_price || 0);
    const sellPrice = parseFloat(item.sell_price || 0);
    const taxRate = parseFloat(item.tax_rate || 0);
    
    document.getElementById('qv-cost-price').textContent = formatCurrency(costPrice);
    document.getElementById('qv-sell-price').textContent = formatCurrency(sellPrice);
    document.getElementById('qv-tax-rate').textContent = taxRate + '%';
    
    // Inventory
    const quantity = parseInt(item.quantity || 0);
    const minStock = parseInt(item.min_stock || 0);
    const maxStock = parseInt(item.max_stock || 0);
    const totalValue = quantity * sellPrice;
    
    document.getElementById('qv-quantity').textContent = quantity;
    
    // Status badge
    const statusBadge = document.getElementById('qv-status-badge');
    if (quantity === 0) {
      statusBadge.innerHTML = '<span style="padding: 0.25rem 0.625rem; background: hsl(0 86% 97%); color: hsl(0 74% 42%); border: 1px solid hsl(0 74% 85%); border-radius: 6px; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">Out</span>';
    } else if (quantity <= 5) {
      statusBadge.innerHTML = '<span style="padding: 0.25rem 0.625rem; background: hsl(48 96% 89%); color: hsl(25 95% 35%); border: 1px solid hsl(48 96% 75%); border-radius: 6px; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">Low</span>';
    } else {
      statusBadge.innerHTML = '<span style="padding: 0.25rem 0.625rem; background: hsl(143 85% 96%); color: hsl(140 61% 35%); border: 1px solid hsl(143 85% 80%); border-radius: 6px; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.025em;">✓ Stock</span>';
    }
    
    document.getElementById('qv-stock-range').textContent = `${minStock} - ${maxStock}`;
    document.getElementById('qv-total-value').textContent = formatCurrency(totalValue);
    
    // Additional Details
    document.getElementById('qv-location').textContent = item.location || 'N/A';
    document.getElementById('qv-supplier').textContent = item.supplier || 'N/A';
    document.getElementById('qv-brand').textContent = item.brand || 'N/A';
    document.getElementById('qv-date-added').textContent = item.date_added_formatted || 'N/A';
    document.getElementById('qv-uom').textContent = item.unit_of_measure || 'pcs';
    document.getElementById('qv-reorder').textContent = item.reorder_point || 'N/A';
    
    // Update edit button link
    document.getElementById('qv-edit-btn').href = `edit_item?id=${itemId}`;
    
    // Hide loading, show content
    loading.style.display = 'none';
    content.style.display = 'block';
    
    console.log('✓ Quick view loaded successfully');
    
  } catch (error) {
    console.error('Quick view error:', error);
    loading.innerHTML = `
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="hsl(0 74% 50%)" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <path d="M12 8V12M12 16H12.01" stroke-linecap="round"/>
      </svg>
      <p style="margin-top: 1rem; color: hsl(0 74% 50%); font-weight: 600;">Failed to load item details</p>
      <p style="margin-top: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">${error.message}</p>
      <button onclick="closeQuickView()" style="margin-top: 1rem; padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; background: white; cursor: pointer; font-weight: 500;">Close</button>
    `;
  }
}

function closeQuickView() {
  const modal = document.getElementById('quickViewModal');
  modal.style.display = 'none';
  console.log('✓ Quick view closed');
}

// Switch tabs in Quick View modal
function switchQvTab(tabName) {
  // Remove active class from all tabs
  document.querySelectorAll('.qv-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Hide all tab contents
  document.querySelectorAll('.qv-tab-content').forEach(content => {
    content.style.display = 'none';
  });
  
  // Activate selected tab
  const selectedTab = document.querySelector(`.qv-tab[data-tab="${tabName}"]`);
  if (selectedTab) {
    selectedTab.classList.add('active');
  }
  
  // Show selected content
  const selectedContent = document.getElementById(`qv-tab-${tabName}`);
  if (selectedContent) {
    selectedContent.style.display = 'block';
  }
  
  console.log('✓ Switched to tab:', tabName);
}

// Close modal on backdrop click
document.getElementById('quickViewModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeQuickView();
  }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('quickViewModal');
    if (modal && modal.style.display === 'flex') {
      closeQuickView();
    }
  }
});

// ============================================
// DELETE ITEM (NO TOAST)
// ============================================
function deleteItem(id, name) {
  if (confirm(`Delete "${name}"?\n\nThis action cannot be undone.`)) {
    window.location.href = `delete_item?id=${id}`;
  }
}

// ============================================
// EXPORT INVENTORY (NO TOAST)
// ============================================
function exportInventory() {
  console.log('Export inventory');
  // Future: Generate CSV/Excel export
}

// ============================================
// UTILITIES
// ============================================
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// No toast notifications - loading state handled by CSS
</script>

<!-- JsBarcode Library - Local copy for offline use -->
<script src="assets/js/vendor/jsbarcode.min.js"></script>

<script>
// ============================================
// BARCODE RENDERING
// ============================================

// Function to render barcodes
function renderBarcodes() {
  if (!window.JsBarcode) {
    console.warn('JsBarcode library not loaded yet');
    return;
  }
  
  const barcodes = document.querySelectorAll('.barcode-svg');
  barcodes.forEach(svg => {
    const value = svg.getAttribute('data-barcode');
    if (value && value !== 'N/A') {
      try {
        JsBarcode(svg, value, {
          format: 'CODE128',
          width: 1,
          height: 24,
          displayValue: false,
          margin: 0,
          background: 'transparent',
          lineColor: '#000'
        });
      } catch(e) {
        console.error('Barcode generation failed for value:', value, e);
        // Fallback: show text if barcode fails
        svg.style.display = 'none';
      }
    }
  });
}

// Wait for JsBarcode library to load, then render
window.addEventListener('load', function() {
  setTimeout(() => {
    renderBarcodes();
    // Sync to current mode if not default
    if (currentGlobalMode !== 'barcode') {
      syncBarcodesToCurrentMode();
    }
  }, 200);
});

// ============================================
// LONG PRESS TOGGLE & COPY FUNCTIONALITY
// ============================================

// Sync all barcodes to current global mode (for AJAX updates)
function syncBarcodesToCurrentMode() {
  if (currentGlobalMode === 'barcode') return; // Already in default mode
  if (!modeConfig || !modeConfig[currentGlobalMode]) return; // Config not loaded yet
  
  const allContainers = document.querySelectorAll('.barcode-container');
  const config = modeConfig[currentGlobalMode];
  
  allContainers.forEach(container => {
    // Silently update without animations
    updateSingleBarcode(container, currentGlobalMode, config);
    
    // Hide badges (no visual feedback needed for sync)
    const modeBadge = container.querySelector('.mode-badge');
    if (modeBadge) {
      modeBadge.style.opacity = '0';
    }
  });
  
  // Update column header text without animation
  const headerLabel = document.getElementById('barcode-header-label');
  if (headerLabel) {
    const textSpan = headerLabel.querySelector('span:first-child');
    if (textSpan) {
      textSpan.textContent = config.headerLabel;
    }
  }
  
  console.log(`✓ Synced ${allContainers.length} barcodes to ${currentGlobalMode} mode`);
}

let pressTimer = null;
let isLongPress = false;

const modeConfig = {
  barcode: {
    next: 'sku',
    label: 'BAR',
    headerLabel: 'Barcode',
    color: 'hsl(199 89% 48%)',
    attr: 'data-barcode'
  },
  sku: {
    next: 'upc',
    label: 'SKU',
    headerLabel: 'SKU',
    color: 'hsl(262 83% 58%)',
    attr: 'data-sku'
  },
  upc: {
    next: 'barcode',
    label: 'UPC',
    headerLabel: 'UPC',
    color: 'hsl(142 71% 45%)',
    attr: 'data-upc'
  }
};

// Current global mode (tracks what mode all barcodes are in)
let currentGlobalMode = 'barcode';

// Update column header based on current mode
function updateColumnHeader(mode) {
  const headerLabel = document.getElementById('barcode-header-label');
  if (!headerLabel) return;
  
  const config = modeConfig[mode];
  const textSpan = headerLabel.querySelector('span:first-child');
  const indicator = headerLabel.querySelector('.mode-indicator');
  
  if (textSpan && indicator) {
    // Update text
    textSpan.textContent = config.headerLabel;
    
    // Update indicator color
    indicator.style.background = config.color;
    indicator.style.boxShadow = `0 0 0 2px ${config.color}33`;
    
    // Show and pulse animation
    indicator.style.opacity = '1';
    indicator.style.transform = 'scale(1.8)';
    
    setTimeout(() => {
      indicator.style.transform = 'scale(1)';
    }, 300);
    
    // Fade out after 3 seconds
    setTimeout(() => {
      indicator.style.opacity = '0';
      indicator.style.transform = 'scale(0.5)';
    }, 3000);
  }
  
  currentGlobalMode = mode;
}

function handleBarcodePress(event, element) {
  event.preventDefault();
  isLongPress = false;
  
  // Add visual feedback for long press
  element.classList.add('long-pressing');
  
  // Start long press timer (700ms)
  pressTimer = setTimeout(() => {
    isLongPress = true;
    toggleBarcodeMode(element);
  }, 700);
}

function handleBarcodeRelease(event, element) {
  // Clear the timer
  if (pressTimer) {
    clearTimeout(pressTimer);
    pressTimer = null;
  }
  
  element.classList.remove('long-pressing');
  
  // If it wasn't a long press, copy the value
  if (!isLongPress) {
    const currentMode = element.getAttribute('data-mode');
    const value = element.getAttribute(modeConfig[currentMode].attr);
    copyBarcodeValue(value, element);
  }
  
  isLongPress = false;
}

function cancelBarcodePress(event, element) {
  // Clear the timer without triggering copy
  if (pressTimer) {
    clearTimeout(pressTimer);
    pressTimer = null;
  }
  
  element.classList.remove('long-pressing');
  isLongPress = false;
}

function toggleBarcodeMode(container) {
  const currentMode = container.getAttribute('data-mode');
  const nextMode = modeConfig[currentMode].next;
  const nextConfig = modeConfig[nextMode];
  
  // Update ALL barcode containers to keep them in sync
  const allContainers = document.querySelectorAll('.barcode-container');
  allContainers.forEach(barcodeContainer => {
    updateSingleBarcode(barcodeContainer, nextMode, nextConfig);
  });
  
  // Update column header
  updateColumnHeader(nextMode);
  
  // Visual feedback for triggered container
  container.style.background = 'hsl(214 95% 93%)';
  container.style.transform = 'scale(1.05)';
  
  setTimeout(() => {
    container.style.background = 'hsl(240 5% 98%)';
    container.style.transform = 'scale(1)';
  }, 200);
  
  // Show toast
  if (typeof Toast !== 'undefined') {
    Toast.info(`All codes switched to ${nextConfig.headerLabel} mode`, 1500);
  }
  
  console.log(`✓ All codes toggled to ${nextMode} mode`);
}

function updateSingleBarcode(container, mode, config) {
  // Update mode attribute
  container.setAttribute('data-mode', mode);
  
  // Get new value
  let newValue = container.getAttribute(config.attr);
  
  // Skip if value is invalid
  if (!newValue || newValue === 'N/A' || newValue.trim() === '') {
    console.warn('Skipping invalid barcode value:', newValue);
    return;
  }
  
  // Update barcode SVG
  const svg = container.querySelector('.barcode-svg');
  if (svg) {
    svg.setAttribute('data-barcode', newValue);
    
    // Re-render barcode
    try {
      if (window.JsBarcode) {
        JsBarcode(svg, newValue, {
          format: 'CODE128',
          width: 1,
          height: 24,
          displayValue: false,
          margin: 0,
          background: 'transparent',
          lineColor: '#000'
        });
      }
    } catch(e) {
      console.error('Barcode re-generation failed for:', newValue, e);
      // Keep existing barcode on error
    }
  }
  
  // Update text display
  const textSpan = container.querySelector('.barcode-text');
  if (textSpan) {
    // Find the text node (first child)
    const textNode = textSpan.childNodes[0];
    if (textNode && textNode.nodeType === Node.TEXT_NODE) {
      const displayValue = newValue.substring(0, 12) + (newValue.length > 12 ? '...' : '');
      textNode.textContent = displayValue;
    }
  }
  
  // Update mode badge
  const badge = container.querySelector('.mode-badge span');
  if (badge) {
    badge.textContent = config.label;
    badge.style.background = config.color;
  }
  
  // Show badge temporarily
  const modeBadge = container.querySelector('.mode-badge');
  if (modeBadge) {
    modeBadge.style.opacity = '1';
    setTimeout(() => {
      modeBadge.style.opacity = '0';
    }, 2000);
  }
}

function copyBarcodeValue(value, container) {
  // Copy to clipboard
  navigator.clipboard.writeText(value).then(function() {
    // Visual feedback - change background color
    container.style.background = 'hsl(143 85% 96%)';
    container.style.borderRadius = '6px';
    container.style.padding = '0.25rem 0.5rem';
    
    // Change copy icon to checkmark temporarily
    const copyIcon = container.querySelector('.copy-icon');
    if (copyIcon) {
      const originalIcon = copyIcon.innerHTML;
      copyIcon.innerHTML = '<polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/>';
      copyIcon.style.stroke = 'hsl(140 61% 35%)';
      copyIcon.style.opacity = '1';
      
      // Reset after 1 second
      setTimeout(() => {
        copyIcon.innerHTML = originalIcon;
        copyIcon.style.stroke = 'currentColor';
        copyIcon.style.opacity = '0.5';
        container.style.background = 'transparent';
        container.style.padding = '0';
      }, 1000);
    }
    
    // Show success toast
    const mode = container.getAttribute('data-mode').toUpperCase();
    if (typeof Toast !== 'undefined') {
      Toast.success(`${mode} copied: ${value}`, 2000);
    }
    
    console.log(`✓ ${mode} copied to clipboard:`, value);
  }).catch(function(err) {
    console.error('Failed to copy:', err);
    if (typeof Toast !== 'undefined') {
      Toast.error('Failed to copy to clipboard', 2000);
    }
  });
}

// ============================================
// APPLY NUMBER FORMAT API
// ============================================
window.addEventListener('load', function() {
  const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';
  
  // Auto-apply formatting using NumberFormat API
  NumberFormat.autoApply(currencySymbol, {
    statCardsSelector: '.stat-value',
    tablePriceSelector: '[data-label="Price"] span',
    tableValueSelector: '[data-label="Value"] span',
    tableMaxWidth: 70
  });
  
  // Set print date for report header
  const contentHeader = document.querySelector('.content-header');
  if (contentHeader) {
    const now = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    const formattedDate = now.toLocaleDateString('en-US', options);
    contentHeader.setAttribute('data-print-date', formattedDate);
  }
});
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
