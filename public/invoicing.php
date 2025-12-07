<?php
/**
 * Invoicing Module - LedgerSMB Feature
 * Create invoices, track payments, send via email
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Service\CurrencyService;
use App\Model\Invoice;
use App\Helper\NotificationHelper;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Check SMTP configuration
$appConfig = require __DIR__ . '/../config/app.php';
$smtpConfigured = !empty($appConfig['mail']['host']) && !empty($appConfig['mail']['username']);

// Get notification summary for financial alerts
$userId = $user['id'] ?? 'admin';
$notificationSummary = NotificationHelper::getSummary($userId);
$financialAlerts = $notificationSummary['by_type']['financial'] ?? 0;

// Real invoices from database with pagination
$invoiceModel = new Invoice();
$searchQuery = $_GET['search'] ?? '';
try {
    if (!empty($searchQuery)) {
        // Use search method if there's a search query
        $invoices = $invoiceModel->search($searchQuery);
        $totalInvoices = count($invoices);
        $totalPages = 1;
    } else {
        // Load first page (6 items) for initial display
        $paginatedResult = $invoiceModel->getPaginated(1, 6);
        $invoices = $paginatedResult['items'];
        $totalInvoices = $paginatedResult['total'];
        $totalPages = $paginatedResult['totalPages'];
    }
    
    $totals = $invoiceModel->totals();
    $totalRevenue = $totals['total'];
    $totalPaid = $totals['paid'];
    $totalOutstanding = $totals['outstanding'];
    
    // Derive overdue count from all invoices
    $today = strtotime('today');
    $overdueCount = 0;
    $allInvoices = $invoiceModel->getAll([], ['limit' => 1000]);
    foreach ($allInvoices as $inv) {
        $status = strtolower((string)($inv['status'] ?? ''));
        $dueTs = isset($inv['due']) ? strtotime((string)$inv['due']) : null;
        $balance = (float)($inv['total'] ?? 0) - (float)($inv['paid'] ?? 0);
        if ($status === 'overdue' || ($balance > 0 && $dueTs && $dueTs < $today)) {
            $overdueCount++;
        }
    }
} catch (\Exception $e) {
    error_log('Error loading invoices: ' . $e->getMessage());
    $invoices = [];
    $totalInvoices = 0;
    $totalPages = 0;
    $totalRevenue = 0;
    $totalPaid = 0;
    $totalOutstanding = 0;
    $overdueCount = 0;
}

$pageTitle = 'Invoicing';
ob_start();
?>

<!-- Shadcn-Style Header with #7194A5 Blue-Gray -->
<div style="background: #7194A5; color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
  <div class="container">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 32px; backdrop-filter: blur(10px);">
        🧾
      </div>
      <div style="flex: 1;">
        <h1 style="font-size: 1.875rem; font-weight: 700; margin: 0 0 0.25rem 0; color: white;">Invoicing & Billing</h1>
        <p style="font-size: 0.875rem; margin: 0; opacity: 0.9;">Professional invoicing system with advanced automation tools</p>
      </div>
      <button onclick="showNewInvoiceModal()" style="padding: 0.625rem 1.5rem; background: rgba(255,255,255,0.95); border: none; border-radius: 8px; color: #7194A5; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='white'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.95)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
        New Invoice
      </button>
      <a href="dashboard.php" style="padding: 0.625rem 1.25rem; background: rgba(255,255,255,0.2); border-radius: 8px; color: white; text-decoration: none; font-weight: 500; backdrop-filter: blur(10px); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        ← Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Stats Cards - Professional Design (Matching Quotations) -->
<div id="statsCards" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.875rem; margin-bottom: 1.5rem;">
  <!-- Total Invoices -->
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Invoices</p>
      <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(222 47% 17%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 12H15M9 16H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L18.7071 8.70711C18.8946 8.89464 19 9.149 19 9.41421V19C19 20.1046 18.1046 21 17 21Z" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="totalInvoicesCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;"><?php echo number_format($totalInvoices); ?></p>
  </div>
  
  <!-- Total Revenue -->
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Revenue</p>
      <div style="width: 36px; height: 36px; background: rgba(113,148,165,0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #7194A5; font-weight: 700; font-size: 1rem;">
        <?php echo htmlspecialchars(CurrencyHelper::symbol()); ?>
      </div>
    </div>
    <p id="totalRevenueAmount" style="font-size: 1.75rem; font-weight: 700; color: #7194A5; margin: 0;"><?php echo CurrencyHelper::format($totalRevenue); ?></p>
  </div>
  
  <!-- Paid -->
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Paid</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(140 61% 13%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="paidAmount" style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;"><?php echo CurrencyHelper::format($totalPaid); ?></p>
  </div>
  
  <!-- Outstanding -->
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Outstanding</p>
      <div style="width: 36px; height: 36px; background: hsl(48 96% 89%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(25 95% 16%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 8V12L15 15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="outstandingAmount" style="font-size: 1.75rem; font-weight: 700; color: hsl(25 95% 16%); margin: 0;"><?php echo CurrencyHelper::format($totalOutstanding); ?></p>
  </div>
  
  <!-- Overdue -->
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Overdue</p>
      <div style="width: 36px; height: 36px; background: hsl(0 86% 97%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(0 74% 42%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 9V13M12 17H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </div>
    </div>
    <p id="overdueCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(0 74% 42%); margin: 0;"><?php echo number_format($overdueCount); ?></p>
    <?php if ($financialAlerts > 0): ?>
    <p style="font-size: 0.6875rem; color: hsl(25 95% 16%); margin-top: 0.375rem; margin-bottom: 0; font-weight: 600;"><?php echo $financialAlerts; ?> alerts</p>
    <?php endif; ?>
  </div>
  
  <!-- Collection Rate -->
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Collection Rate</p>
      <div style="width: 36px; height: 36px; background: hsl(262 83% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(263 70% 26%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M13 7H21M13 12H21M13 17H21M3 7H3.01M3 12H3.01M3 17H3.01" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
      </div>
    </div>
    <p id="collectionRate" style="font-size: 1.75rem; font-weight: 700; color: hsl(263 70% 26%); margin: 0;"><?php echo $totalRevenue > 0 ? round(($totalPaid / $totalRevenue) * 100, 1) : 0; ?>%</p>
  </div>
</div>

<!-- Modern Invoice Toolbar (Inspired by Xero/QuickBooks/FreshBooks) -->
<div style="background: white; border-radius: 10px; padding: 1rem 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 1.25rem; border: 1px solid hsl(214 20% 92%);">
  <div style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
    
    <!-- Left Section: Search & Filters -->
    <div style="display: flex; gap: 0.75rem; align-items: center; flex: 1; min-width: 300px;">
      <!-- Search Bar -->
      <div style="position: relative; flex: 1; max-width: 320px;">
        <svg style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: hsl(215 16% 47%); pointer-events: none;" width="16" height="16" viewBox="0 0 24 24" fill="none">
          <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
          <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <input type="search" id="invoice-search" placeholder="Search invoices..." value="<?= htmlspecialchars($searchQuery) ?>" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.75rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s; background: hsl(214 20% 98%);" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'; this.style.background='white'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'; this.style.background='hsl(214 20% 98%)'">
      </div>
      
      <!-- Status Filter -->
      <select id="status-filter" style="padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; min-width: 130px; cursor: pointer; transition: all 0.2s; background: white; font-weight: 500;" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
        <option value="all">All Status</option>
        <option value="paid">Paid</option>
        <option value="unpaid">Unpaid</option>
        <option value="partial">Partial</option>
        <option value="overdue">Overdue</option>
      </select>
      
    </div>
    
    <!-- Right Section: Action Buttons -->
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-shrink: 0;">
      <!-- Recurring -->
      <button class="btn btn-secondary btn-sm" onclick="showRecurringInvoiceDialog()" title="Recurring Invoices" style="padding: 0.625rem 0.875rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; border: 1.5px solid hsl(214 20% 88%); background: white; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 20% 98%)'; this.style.borderColor='#7194A5'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
        </svg>
        <span>Recurring</span>
      </button>
      
      <!-- Templates -->
      <button class="btn btn-secondary btn-sm" onclick="showInvoiceTemplates()" title="Invoice Templates" style="padding: 0.625rem 0.875rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; border: 1.5px solid hsl(214 20% 88%); background: white; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 20% 98%)'; this.style.borderColor='#7194A5'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 12H15M9 16H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L18.7071 8.70711C18.8946 8.89464 19 9.149 19 9.41421V19C19 20.1046 18.1046 21 17 21Z"/>
        </svg>
        <span>Templates</span>
      </button>
      
      <!-- Filters -->
      <button class="btn btn-secondary btn-sm" onclick="showAdvancedFilters()" title="Advanced Filters" style="padding: 0.625rem 0.875rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; border: 1.5px solid hsl(214 20% 88%); background: white; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 20% 98%)'; this.style.borderColor='#7194A5'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 4h18M3 8h18M3 12h16M3 16h10"/>
        </svg>
        <span>Filters</span>
      </button>
      
      <!-- Divider -->
      <div style="width: 1px; height: 32px; background: hsl(214 20% 88%); margin: 0 0.375rem;"></div>
      
      <!-- Bulk Actions -->
      <button class="btn btn-secondary btn-sm" onclick="bulkActionsMenu()" title="Bulk Actions" style="padding: 0.625rem 0.875rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; border: 1.5px solid hsl(214 20% 88%); background: white; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 20% 98%)'; this.style.borderColor='#7194A5'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 5C9 6.10457 9.89543 7 11 7H13C14.1046 7 15 6.10457 15 5M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5M12 12H15M12 16H15M9 12H9.01M9 16H9.01"/>
        </svg>
        <span>Bulk Actions</span>
      </button>
      
      <!-- Import -->
      <button class="btn btn-secondary btn-sm" title="Import Invoices" style="padding: 0.625rem 0.875rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; border: 1.5px solid hsl(214 20% 88%); background: white; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 98%)'; this.style.borderColor='hsl(214 95% 75%)'; this.style.color='hsl(222 47% 17%)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(214 20% 88%)'; this.style.color='inherit'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 17V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V17M12 3V15M12 15L7 10M12 15L17 10" stroke-linecap="round"/>
        </svg>
        <span>Import</span>
      </button>
      
      <!-- Export -->
      <button class="btn btn-secondary btn-sm" onclick="exportInvoices()" title="Export Invoices" style="padding: 0.625rem 0.875rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; border: 1.5px solid hsl(143 70% 70%); background: hsl(143 85% 96%); color: hsl(140 61% 13%); transition: all 0.2s;" onmouseover="this.style.background='hsl(143 70% 50%)'; this.style.borderColor='hsl(143 70% 45%)'; this.style.color='white'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'" onmouseout="this.style.background='hsl(143 85% 96%)'; this.style.borderColor='hsl(143 70% 70%)'; this.style.color='hsl(140 61% 13%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke-linecap="round"/>
        </svg>
        <span>Export</span>
      </button>
    </div>
  </div>
</div>

<!-- View Invoice Modal (Matching Quotations.php Style) -->
<div id="viewInvoiceModal" onclick="closeViewInvoiceModal()" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 2rem;">
  <div onclick="event.stopPropagation()" style="width: 100%; max-width: 900px; max-height: 90vh; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); display: flex; flex-direction: column; overflow: hidden;">
    
    <!-- Header -->
    <div style="background: linear-gradient(135deg, hsl(210 20% 98%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(214 20% 88%); display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h3 style="margin: 0 0 0.25rem 0; font-size: 1.25rem; font-weight: 700; color: hsl(222 47% 17%);">Invoice Details</h3>
        <p id="viewInvoiceNumber" style="margin: 0; font-size: 0.875rem; color: hsl(215 16% 47%); font-family: monospace;">INV-001</p>
      </div>
      <button type="button" onclick="closeViewInvoiceModal()" style="background: hsl(240 5% 96%); border: 1px solid hsl(214 20% 88%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(215 16% 47%); transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 92%)'" onmouseout="this.style.background='hsl(240 5% 96%)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    
    <!-- Content -->
    <div style="padding: 2rem; overflow-y: auto; flex: 1;">
      <!-- Main Info Grid -->
      <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Customer</label>
          <p id="viewCustomerName" style="margin: 0; font-size: 1rem; font-weight: 600; color: hsl(222 47% 17%);">-</p>
          <p id="viewCustomerEmail" style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: hsl(215 16% 47%);">-</p>
        </div>
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Status</label>
          <span id="viewInvoiceStatus" class="badge"></span>
        </div>
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Invoice Date</label>
          <p id="viewInvoiceDate" style="margin: 0; font-size: 1rem; color: hsl(222 47% 17%);">-</p>
        </div>
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Due Date</label>
          <p id="viewDueDate" style="margin: 0; font-size: 1rem; color: hsl(222 47% 17%);">-</p>
        </div>
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Total Amount</label>
          <p id="viewTotalAmount" style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #7194A5;">₱0.00</p>
        </div>
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Currency</label>
          <p id="viewCurrency" style="margin: 0; font-size: 1rem; font-weight: 600; color: hsl(222 47% 17%);">PHP</p>
        </div>
      </div>
      
      <!-- Payment Summary Box -->
      <div style="padding: 1.5rem; background: hsl(210 20% 98%); border-radius: 8px; border: 1px solid hsl(214 20% 90%);">
        <h4 style="margin: 0 0 1rem 0; font-size: 0.9375rem; font-weight: 700; color: hsl(222 47% 17%); display: flex; align-items: center; gap: 0.5rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <rect x="1" y="4" width="22" height="16" rx="2"/>
            <line x1="1" y1="10" x2="23" y2="10"/>
          </svg>
          Payment Summary
        </h4>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
          <div>
            <label style="display: block; font-size: 0.75rem; color: hsl(215 16% 47%); margin-bottom: 0.375rem;">Amount Paid</label>
            <p id="viewPaidAmount" style="margin: 0; font-size: 1.125rem; font-weight: 700; color: hsl(140 61% 35%);">₱0.00</p>
          </div>
          <div>
            <label style="display: block; font-size: 0.75rem; color: hsl(215 16% 47%); margin-bottom: 0.375rem;">Balance Due</label>
            <p id="viewBalanceAmount" style="margin: 0; font-size: 1.125rem; font-weight: 700; color: hsl(25 95% 35%);">₱0.00</p>
          </div>
        </div>
      </div>
      
      <!-- Notes Section -->
      <div id="viewNotesSection" style="display: none; margin-top: 1.5rem; padding: 1rem; background: hsl(214 95% 98%); border-radius: 8px; border-left: 4px solid hsl(214 95% 75%);">
        <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.5rem;">Notes</label>
        <p id="viewNotes" style="font-size: 0.875rem; color: hsl(222 47% 17%); margin: 0; line-height: 1.6; white-space: pre-wrap;"></p>
      </div>
    </div>
    
    <!-- Footer Actions -->
    <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
      <button type="button" onclick="emailInvoice(currentViewInvoiceId)" class="btn btn-ghost" <?php if (!$smtpConfigured): ?>disabled<?php endif; ?> style="padding: 0.625rem 1.25rem; background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; font-weight: 500; color: <?php echo $smtpConfigured ? 'hsl(222 47% 17%)' : 'hsl(215 16% 60%)'; ?>; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>; transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem; opacity: <?php echo $smtpConfigured ? '1' : '0.5'; ?>;" <?php if ($smtpConfigured): ?>onmouseover="this.style.background='hsl(214 20% 96%)'" onmouseout="this.style.background='white'"<?php endif; ?>>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        Send Email<?php if (!$smtpConfigured): ?> <span style="font-size: 0.75rem;">(SMTP not configured)</span><?php endif; ?>
      </button>
      <button type="button" onclick="downloadPDF(currentViewInvoiceId)" class="btn btn-ghost" style="padding: 0.625rem 1.25rem; background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; font-weight: 500; color: hsl(222 47% 17%); cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='hsl(214 20% 96%)'" onmouseout="this.style.background='white'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Download PDF
      </button>
      <button type="button" onclick="closeViewInvoiceModal()" class="btn btn-primary" style="padding: 0.625rem 1.25rem; background: #7194A5; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: white; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#5d7a8a'" onmouseout="this.style.background='#7194A5'">
        Close
      </button>
    </div>
  </div>
</div>

<!-- Bulk Actions Dropdown -->
<div id="bulkActionsDropdown" style="display: none; position: fixed; background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); z-index: 10000; min-width: 220px;">
  <div style="padding: 0.75rem 1rem; border-bottom: 1px solid hsl(214 20% 88%);">
    <p style="margin: 0; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em;">
      <span id="selectedCount">0</span> Selected
    </p>
  </div>
  <div style="padding: 0.5rem;">
    <button onclick="handleBulkAction('send_email')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Send Email
    </button>
    <button onclick="handleBulkAction('mark_paid')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(140 61% 35%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(143 85% 96%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>
      Mark as Paid
    </button>
    <button onclick="handleBulkAction('mark_sent')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Mark as Sent
    </button>
    <button onclick="handleBulkAction('export_pdf')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
      Export PDF
    </button>
    <div style="height: 1px; background: hsl(214 20% 88%); margin: 0.5rem 0;"></div>
    <button onclick="handleBulkAction('delete')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(0 74% 50%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(0 86% 97%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      Delete Selected
    </button>
  </div>
</div>

<!-- Record Payment Modal (Shadcn Style) -->
<div id="recordPaymentModal" onclick="closeRecordPaymentModal()" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 2rem;">
  <div onclick="event.stopPropagation()" style="width: 100%; max-width: 500px; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); overflow: hidden;">
    
    <!-- Header -->
    <div style="background: linear-gradient(135deg, hsl(143 85% 96%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(143 85% 88%);">
      <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
        <div style="width: 48px; height: 48px; background: hsl(143 85% 90%); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 25%)" stroke-width="2.5">
            <rect x="1" y="4" width="22" height="16" rx="2"/>
            <line x1="1" y1="10" x2="23" y2="10"/>
          </svg>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: hsl(140 61% 13%);">Record Payment</h3>
          <p id="paymentInvoiceNumber" style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: hsl(140 61% 35%); font-family: monospace;">INV-001</p>
        </div>
      </div>
    </div>
    
    <!-- Form Content -->
    <form id="recordPaymentForm" onsubmit="submitPaymentRecord(event)" style="padding: 2rem;">
      <input type="hidden" id="paymentInvoiceId" name="invoice_id">
      
      <!-- Payment Amount -->
      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">
          Payment Amount <span style="color: hsl(0 74% 42%);">*</span>
        </label>
        <div style="position: relative;">
          <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1rem; font-weight: 600; color: hsl(140 61% 35%);"><?php echo htmlspecialchars(CurrencyHelper::symbol()); ?></span>
          <input type="number" id="paymentAmount" name="amount" required min="0.01" step="0.01" placeholder="0.00" style="width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 1rem; font-weight: 600; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(140 61% 45%)'; this.style.boxShadow='0 0 0 3px rgba(34, 197, 94, 0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
        </div>
        <p id="balanceInfo" style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: hsl(215 16% 47%);">Balance due: <?php echo htmlspecialchars(CurrencyHelper::symbol()); ?>0.00</p>
      </div>
      
      <!-- Payment Date -->
      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">
          Payment Date <span style="color: hsl(0 74% 42%);">*</span>
        </label>
        <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(140 61% 45%)'; this.style.boxShadow='0 0 0 3px rgba(34, 197, 94, 0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
      </div>
      
      <!-- Payment Method -->
      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">
          Payment Method <span style="color: hsl(0 74% 42%);">*</span>
        </label>
        <select name="payment_method" required style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; cursor: pointer; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(140 61% 45%)'; this.style.boxShadow='0 0 0 3px rgba(34, 197, 94, 0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
          <option value="bank_transfer">Bank Transfer</option>
          <option value="credit_card">Credit Card</option>
          <option value="cash">Cash</option>
          <option value="check">Check</option>
          <option value="paypal">PayPal</option>
          <option value="gcash">GCash</option>
          <option value="paymaya">PayMaya</option>
        </select>
      </div>
      
      <!-- Reference Number -->
      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">
          Reference / Transaction ID
        </label>
        <input type="text" name="reference" placeholder="e.g., TXN-123456, Check #789" style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(140 61% 45%)'; this.style.boxShadow='0 0 0 3px rgba(34, 197, 94, 0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
      </div>
      
      <!-- Notes -->
      <div style="margin-bottom: 2rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">
          Notes
        </label>
        <textarea name="notes" rows="3" placeholder="Additional payment notes..." style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; resize: vertical; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(140 61% 45%)'; this.style.boxShadow='0 0 0 3px rgba(34, 197, 94, 0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'"></textarea>
      </div>
      
      <!-- Action Buttons -->
      <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
        <button type="button" onclick="closeRecordPaymentModal()" style="padding: 0.75rem 1.5rem; background: hsl(240 5% 96%); border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 92%)'" onmouseout="this.style.background='hsl(240 5% 96%)'">
          Cancel
        </button>
        <button type="submit" style="padding: 0.75rem 1.5rem; background: linear-gradient(135deg, hsl(140 61% 45%) 0%, hsl(140 61% 35%) 100%); border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: white; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(34, 197, 94, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(34, 197, 94, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(34, 197, 94, 0.3)'">
          Record Payment
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Email Invoice Modal (Mindblowing Biodata-Style 2-Column Layout) -->
<div id="emailInvoiceModal" onclick="closeEmailInvoiceModal()" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 2rem;">
  <div onclick="event.stopPropagation()" style="width: 100%; max-width: 950px; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); display: flex; flex-direction: column; overflow: hidden;">
    
    <!-- Header -->
    <div style="background: linear-gradient(135deg, hsl(0 0% 98%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(0 0% 88%); display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h3 style="margin: 0 0 0.25rem 0; font-size: 1.25rem; font-weight: 700; color: hsl(0 0% 12%);">Send Invoice via Email</h3>
        <p id="emailInvoiceNumber" style="margin: 0; font-size: 0.875rem; color: hsl(0 0% 45%); font-family: monospace;">INV-001</p>
      </div>
      <button type="button" onclick="closeEmailInvoiceModal()" style="background: hsl(0 0% 96%); border: 1px solid hsl(0 0% 86%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(0 0% 40%); transition: all 0.2s;" onmouseover="this.style.background='hsl(0 0% 92%)'" onmouseout="this.style.background='hsl(0 0% 96%)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    
    <!-- Form Content with 2-Column Layout -->
    <form id="emailInvoiceForm" onsubmit="submitEmailInvoice(event)" style="display: flex; flex-direction: column; flex: 1; overflow-y: auto;">
      <input type="hidden" id="emailInvoiceId" name="invoice_id">
      
      <!-- SMTP Warning (shown if not configured) -->
      <div id="smtpWarning" style="display: none; margin: 2rem 2rem 0 2rem; padding: 1rem 1.25rem; background: hsl(48 96% 89%); border: 1px solid hsl(48 96% 75%); border-radius: 8px;">
        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;">
            <path d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
          </svg>
          <div>
            <p style="margin: 0 0 0.375rem 0; font-size: 0.875rem; font-weight: 700; color: hsl(25 95% 16%);">SMTP Not Configured</p>
            <p style="margin: 0; font-size: 0.8125rem; color: hsl(25 95% 16%); line-height: 1.5;">Email functionality requires SMTP server configuration. Please configure your email settings to send invoices.</p>
          </div>
        </div>
      </div>
      
      <!-- Main Content Area with 2 Columns -->
      <div style="padding: 2rem; display: grid; grid-template-columns: 1fr 1.6fr; gap: 1.5rem;">
        
        <!-- LEFT COLUMN: Recipient Info & Options -->
        <div style="display: flex; flex-direction: column; gap: 0;">
          
          <!-- Recipient Card -->
          <div style="background: white; border: 1px solid hsl(0 0% 86%); border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <!-- Card Header -->
            <div style="background: hsl(0 0% 97%); padding: 0.875rem 1.25rem; border-bottom: 1px solid hsl(0 0% 90%);">
              <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(0 0% 12%); text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(0 0% 12%)" stroke-width="2.5">
                  <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M8.5 11a4 4 0 100-8 4 4 0 000 8z"/>
                </svg>
                Recipient Information
              </h4>
            </div>
            <!-- Card Body -->
            <div style="padding: 1.25rem;">
              <!-- Recipient Email Field -->
              <div style="margin-bottom: 1rem;">
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(0 0% 12%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(0 0% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  To <span style="color: hsl(0 72% 45%);">*</span>
                </label>
                <input type="email" id="recipientEmail" name="recipient_email" required placeholder="customer@example.com" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(0 0% 85%); border-radius: 6px; font-size: 0.875rem; color: hsl(0 0% 12%); transition: all 0.2s; background: hsl(0 0% 99%);" onfocus="this.style.borderColor='hsl(0 0% 18%)'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(0,0,0,0.12)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.background='hsl(0 0% 99%)'; this.style.boxShadow='none'">
              </div>
              
              <!-- CC Email Field -->
              <div>
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(0 0% 12%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(0 0% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  CC <span style="font-size: 0.625rem; font-weight: 600; color: hsl(0 0% 45%);">(Optional)</span>
                </label>
                <input type="email" name="cc_email" placeholder="cc@example.com" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(0 0% 85%); border-radius: 6px; font-size: 0.875rem; color: hsl(0 0% 12%); transition: all 0.2s; background: hsl(0 0% 99%);" onfocus="this.style.borderColor='hsl(0 0% 18%)'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(0,0,0,0.12)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.background='hsl(0 0% 99%)'; this.style.boxShadow='none'">
              </div>
            </div>
          </div>
          
          <!-- Attachment Card -->
          <div style="background: white; border: 1px solid hsl(0 0% 86%); border-radius: 10px; overflow: hidden; margin-top: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <!-- Card Header -->
            <div style="background: hsl(0 0% 97%); padding: 0.875rem 1.25rem; border-bottom: 1px solid hsl(0 0% 90%);">
              <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(0 0% 12%); text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(0 0% 12%)" stroke-width="2.5">
                  <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                </svg>
                Attachments
              </h4>
            </div>
            
            <!-- Card Body -->
            <div style="padding: 1.25rem;">
              <!-- PDF Attachment Option -->
              <label style="display: flex; align-items: flex-start; gap: 0.875rem; padding: 0.875rem; background: hsl(0 0% 98%); border: 1px solid hsl(0 0% 88%); border-radius: 6px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(0 0% 97%)'; this.style.borderColor='hsl(0 0% 50%)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.12)'" onmouseout="this.style.background='hsl(0 0% 98%)'; this.style.borderColor='hsl(0 0% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <input type="checkbox" name="attach_pdf" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: hsl(0 0% 12%); margin-top: 1px; flex-shrink: 0;">
                <div style="flex: 1;">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(0 0% 12%)" stroke-width="2.5">
                      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                      <polyline points="14 2 14 8 20 8"/>
                      <line x1="16" y1="13" x2="8" y2="13"/>
                      <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    <span style="font-size: 0.8125rem; font-weight: 700; color: hsl(0 0% 12%);">Invoice PDF</span>
                  </div>
                  <p style="margin: 0; font-size: 0.6875rem; color: hsl(0 0% 40%); line-height: 1.4;">Attach professional PDF invoice document</p>
                </div>
              </label>
            </div>
          </div>
          
        </div>
        
        <!-- RIGHT COLUMN: Email Content -->
        <div style="display: flex; flex-direction: column; gap: 0;">
          
          <!-- Email Content Card -->
          <div style="background: white; border: 1px solid hsl(0 0% 86%); border-radius: 10px; overflow: hidden; height: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <!-- Card Header -->
            <div style="background: hsl(0 0% 97%); padding: 0.875rem 1.25rem; border-bottom: 1px solid hsl(0 0% 90%);">
              <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(0 0% 12%); text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(0 0% 12%)" stroke-width="2.5">
                  <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Email Content
              </h4>
            </div>
            
            <!-- Card Body -->
            <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
              <!-- Subject Field -->
              <div>
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(0 0% 12%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(0 0% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  Subject <span style="color: hsl(0 72% 45%);">*</span>
                </label>
                <input type="text" id="emailSubject" name="subject" required placeholder="Invoice INV-001 from Your Company" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(0 0% 85%); border-radius: 6px; font-size: 0.875rem; font-weight: 600; color: hsl(0 0% 12%); transition: all 0.2s; background: hsl(0 0% 99%);" onfocus="this.style.borderColor='hsl(0 0% 18%)'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(0,0,0,0.12)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.background='hsl(0 0% 99%)'; this.style.boxShadow='none'">
              </div>
              
              <!-- Message Field -->
              <div style="flex: 1; display: flex; flex-direction: column;">
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(0 0% 12%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(0 0% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  Message <span style="color: hsl(0 72% 45%);">*</span>
                </label>
                <textarea id="emailMessage" name="message" required rows="13" placeholder="Dear Customer,&#10;&#10;Please find attached invoice INV-001.&#10;&#10;Thank you for your business!&#10;&#10;Best regards,&#10;Your Company" style="width: 100%; padding: 0.75rem 0.875rem; border: 1px solid hsl(0 0% 85%); border-radius: 6px; font-size: 0.8125rem; color: hsl(0 0% 12%); resize: none; transition: all 0.2s; font-family: inherit; line-height: 1.6; flex: 1; background: hsl(0 0% 99%);" onfocus="this.style.borderColor='hsl(0 0% 18%)'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(0,0,0,0.12)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.background='hsl(0 0% 99%)'; this.style.boxShadow='none'"></textarea>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.625rem; color: hsl(0 0% 40%); display: flex; align-items: center; gap: 0.375rem;">
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
      <div style="background: hsl(0 0% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(0 0% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
        <button type="button" onclick="closeEmailInvoiceModal()" style="padding: 0.75rem 1.5rem; background: white; border: 1.5px solid hsl(0 0% 86%); border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: hsl(0 0% 12%); cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(0 0% 96%)'" onmouseout="this.style.background='white'">
          Cancel
        </button>
        <button type="submit" id="sendEmailBtn" style="padding: 0.75rem 2rem; background: linear-gradient(135deg, hsl(0 0% 16%) 0%, hsl(0 0% 10%) 100%); border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 700; color: white; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.35); display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0, 0, 0, 0.45)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0, 0, 0, 0.35)'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
          </svg>
          Send Invoice
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Invoices Table with Sortable Headers -->
<div id="invoicesTableContainer" class="table-container" style="display: <?php echo empty($invoices) ? 'none' : 'block'; ?>;">
  <table class="data-table" id="invoicesTable">
    <thead>
      <tr>
        <th class="checkbox-column" style="width: 40px; display: none;">
          <input type="checkbox" id="selectAllInvoices" onclick="toggleAllInvoices(this)" style="cursor: pointer;">
        </th>
        <th>Invoice #</th>
        <th class="sortable-header" onclick="sortTable('customer')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Customer</span>
            <svg id="sort-customer-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('date')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Invoice Date</span>
            <svg id="sort-date-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('due_date')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Due Date</span>
            <svg id="sort-due-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('total')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Total Amount</span>
            <svg id="sort-total-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('paid')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Paid</span>
            <svg id="sort-paid-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('balance')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Balance</span>
            <svg id="sort-balance-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('status')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Status</span>
            <svg id="sort-status-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </th>
        <th style="width: 180px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($invoices as $invoice): 
        $balance = ($invoice['total'] ?? 0) - ($invoice['paid'] ?? 0);
        $invId = $invoice['id'] ?? ((isset($invoice['_id']) && is_object($invoice['_id'])) ? (string)$invoice['_id'] : ($invoice['_id'] ?? ''));
        $dateTs = null; if (isset($invoice['date'])) { if (is_string($invoice['date'])) { $dateTs = strtotime($invoice['date']); } elseif (is_object($invoice['date']) && method_exists($invoice['date'], 'toDateTime')) { $dateTs = $invoice['date']->toDateTime()->getTimestamp(); }}
        $dueTs = null; $dueField = $invoice['due_date'] ?? $invoice['due'] ?? null; if (isset($dueField)) { if (is_string($dueField)) { $dueTs = strtotime($dueField); } elseif (is_object($dueField) && method_exists($dueField, 'toDateTime')) { $dueTs = $dueField->toDateTime()->getTimestamp(); }}
      ?>
      <tr data-invoice-id="<?php echo htmlspecialchars($invId); ?>" 
          data-customer="<?php echo htmlspecialchars($invoice['customer'] ?? ''); ?>"
          data-date="<?php echo $dateTs ?? 0; ?>"
          data-due="<?php echo $dueTs ?? 0; ?>"
          data-total="<?php echo $invoice['total'] ?? 0; ?>"
          data-paid="<?php echo $invoice['paid'] ?? 0; ?>"
          data-balance="<?php echo $balance; ?>"
          data-status="<?php echo $invoice['status'] ?? ''; ?>">
        <td class="checkbox-column" style="display: none;">
          <input type="checkbox" class="invoice-checkbox" value="<?php echo htmlspecialchars($invId); ?>" style="cursor: pointer;">
        </td>
        <td class="font-mono font-medium"><?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></td>
        <td class="font-medium"><?php echo htmlspecialchars($invoice['customer']); ?></td>
        <td><?php echo $dateTs ? date('M d, Y', $dateTs) : '-'; ?></td>
        <td><?php echo $dueTs ? date('M d, Y', $dueTs) : '-'; ?></td>
        <td class="font-semibold"><?php echo CurrencyHelper::format($invoice['total']); ?></td>
        <td class="text-success">
          <?php 
          // Show payment in original currency if different
          if ($invoice['paid'] > 0 && $invoice['payment_currency'] && $invoice['payment_currency'] !== CurrencyHelper::getCurrentCurrency()) {
            echo CurrencyService::format($invoice['paid'], $invoice['payment_currency']);
            echo ' <span class="text-muted" style="font-size: 0.75rem;">(' . $invoice['payment_currency'] . ')</span>';
          } else {
            echo CurrencyHelper::format($invoice['paid']);
          }
          ?>
        </td>
        <td class="font-semibold <?php echo $balance > 0 ? 'text-warning' : 'text-success'; ?>">
          <?php echo CurrencyHelper::format($balance); ?>
        </td>
        <td>
          <?php
          $statusBadges = [
            'paid' => 'badge-success',
            'unpaid' => 'badge-default',
            'partial' => 'badge-warning',
            'overdue' => 'badge-danger'
          ];
          $badgeClass = $statusBadges[$invoice['status']] ?? 'badge-default';
          ?>
          <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($invoice['status']); ?></span>
        </td>
        <td>
          <div class="flex gap-1">
            <button class="btn btn-ghost btn-sm" onclick="viewInvoice('<?php echo htmlspecialchars($invId); ?>')" title="View Details">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <?php if ($balance > 0): ?>
            <button class="btn btn-ghost btn-sm text-success" onclick="recordPayment('<?php echo htmlspecialchars($invId); ?>')" title="Record Payment">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <rect x="1" y="4" width="22" height="16" rx="2" stroke="currentColor" stroke-width="2"/>
                <line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm text-primary" onclick="emailInvoice('<?php echo htmlspecialchars($invId); ?>')" title="<?php echo $smtpConfigured ? 'Send Email' : 'SMTP not configured'; ?>" <?php if (!$smtpConfigured): ?>disabled style="opacity: 0.5; cursor: not-allowed;"<?php endif; ?>>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M3 8L10.89 13.26C11.2187 13.4793 11.6049 13.5963 12 13.5963C12.3951 13.5963 12.7813 13.4793 13.11 13.26L21 8M5 19H19C19.5304 19 20.0391 18.7893 20.4142 18.4142C20.7893 18.0391 21 17.5304 21 17V7C21 6.46957 20.7893 5.96086 20.4142 5.58579C20.0391 5.21071 19.5304 5 19 5H5C4.46957 5 3.96086 5.21071 3.58579 5.58579C3.21071 5.96086 3 6.46957 3 7V17C3 17.5304 3.21071 18.0391 3.58579 18.4142C3.96086 18.7893 4.46957 19 5 19Z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="downloadPDF('<?php echo htmlspecialchars($invId); ?>')" title="Download PDF">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M7 10L12 15M12 15L17 10M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Empty State (Separate from table) -->
<div id="emptyStateContainer" style="display: <?php echo empty($invoices) ? 'block' : 'none'; ?>; padding: 4rem 2rem; text-align: center; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
  <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#6b7280" style="opacity: 0.15; margin: 0 auto 1.5rem; stroke-width: 1.5;">
    <path d="M9 12H15M9 16H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L18.7071 8.70711C18.8946 8.89464 19 9.149 19 9.41421V19C19 20.1046 18.1046 21 17 21Z" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <h3 style="font-size: 1.25rem; font-weight: 600; color: #111827; margin: 0 0 0.75rem 0;">No invoices yet</h3>
  <p style="font-size: 0.9375rem; color: #6b7280; margin: 0 auto 1.5rem; max-width: 28rem; line-height: 1.6;">
    Get started by creating your first invoice. Click the "New Invoice" button above to begin billing your customers.
  </p>
  <button onclick="showNewInvoiceModal()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
    Create Your First Invoice
  </button>
</div>

<!-- Shadcn-Inspired Pagination Controls -->
<div id="paginationContainer" style="display: <?php echo empty($invoices) ? 'none' : 'flex'; ?>; align-items: center; justify-content: space-between; margin-top: 1.5rem; padding: 1rem 0; border-top: 1px solid hsl(214 20% 92%);">
  <!-- Results Info -->
  <div style="display: flex; align-items: center; gap: 0.5rem;">
    <span style="font-size: 0.875rem; color: hsl(215 16% 47%);">
      Showing <span id="paginationStart" style="font-weight: 600; color: hsl(0 0% 12%);">1</span> to 
      <span id="paginationEnd" style="font-weight: 600; color: hsl(0 0% 12%);">6</span> of 
      <span id="paginationTotal" style="font-weight: 600; color: hsl(0 0% 12%);"><?php echo $totalInvoices; ?></span> invoices
    </span>
  </div>
  
  <!-- Pagination Buttons -->
  <div style="display: flex; align-items: center; gap: 0.375rem;">
    <button id="prevPageBtn" onclick="goToPage('prev')" style="padding: 0.5rem 0.875rem; background: white; border: 1px solid hsl(214 20% 88%); border-radius: 6px; font-size: 0.875rem; font-weight: 500; color: hsl(0 0% 12%); cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.375rem;" onmouseover="this.style.background='hsl(0 0% 96%)'" onmouseout="this.style.background='white'" disabled>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M15 18L9 12L15 6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Previous
    </button>
    
    <div id="pageNumbers" style="display: flex; gap: 0.25rem;">
      <!-- Page number buttons will be inserted here -->
    </div>
    
    <button id="nextPageBtn" onclick="goToPage('next')" style="padding: 0.5rem 0.875rem; background: white; border: 1px solid hsl(214 20% 88%); border-radius: 6px; font-size: 0.875rem; font-weight: 500; color: hsl(0 0% 12%); cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.375rem;" onmouseover="this.style.background='hsl(0 0% 96%)'" onmouseout="this.style.background='white'">
      Next
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 18L15 12L9 6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </div>
</div>

<!-- Enhanced Modal Styles -->
<style>
.invoice-modal-input, .invoice-modal-select, .invoice-modal-textarea {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid hsl(214 20% 92%);
  border-radius: 6px;
  font-size: 0.875rem;
  transition: all 0.2s;
  background: white;
}

.invoice-modal-input:focus, .invoice-modal-select:focus, .invoice-modal-textarea:focus {
  outline: none;
  border-color: #7194A5;
  box-shadow: 0 0 0 3px rgba(113, 148, 165, 0.1);
}

.invoice-modal-input:hover, .invoice-modal-select:hover, .invoice-modal-textarea:hover {
  border-color: hsl(214 20% 80%);
}

.invoice-modal-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: hsl(222 47% 17%);
  margin-bottom: 0.375rem;
}

.invoice-tab-content {
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes slideDown {
  from { opacity: 0; max-height: 0; transform: translateY(-10px); }
  to { opacity: 1; max-height: 500px; transform: translateY(0); }
}

@keyframes slideUp2 {
  from { opacity: 1; max-height: 500px; transform: translateY(0); }
  to { opacity: 0; max-height: 0; transform: translateY(-10px); }
}
</style>

<!-- Custom Confirmation Dialog (Shadcn Style) -->
<div id="confirmationDialog" style="display: none; position: fixed; inset: 0; z-index: 100; align-items: center; justify-content: center; padding: 2rem;">
  <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);" onclick="hideConfirmationDialog()"></div>
  
  <div id="confirmationDialogContent" style="position: relative; z-index: 101; background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); max-width: 448px; width: 100%; padding: 1.5rem; animation: slideUp 0.2s cubic-bezier(0.4, 0, 0.2, 1); outline: none;">
    <!-- Dialog Header -->
    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
      <div style="width: 40px; height: 40px; background: hsl(0 86% 97%); border: 1.5px solid hsl(0 86% 90%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="hsl(0 74% 42%)" stroke-width="2.5">
          <path d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
      </div>
      <div>
        <h3 style="font-size: 1.125rem; font-weight: 600; color: hsl(222 47% 17%); margin: 0 0 0.25rem 0;">Unsaved Changes</h3>
        <p style="font-size: 0.875rem; color: hsl(215 16% 47%); margin: 0;">Are you sure you want to close?</p>
      </div>
    </div>
    
    <!-- Dialog Content -->
    <div style="margin-bottom: 1.5rem;">
      <p style="font-size: 0.875rem; color: hsl(215 16% 47%); line-height: 1.5;">You have unsaved changes that will be lost if you close this window.</p>
    </div>
    
    <!-- Dialog Actions -->
    <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
      <button type="button" onclick="hideConfirmationDialog()" style="padding: 0.5rem 1rem; background: hsl(240 5% 98%); border: 1.5px solid hsl(214 20% 88%); border-radius: 6px; font-size: 0.875rem; font-weight: 500; color: hsl(222 47% 17%); cursor: pointer; transition: all 0.15s;" onmouseover="this.style.background='hsl(240 5% 95%)'" onmouseout="this.style.background='hsl(240 5% 98%)'">
        Cancel
      </button>
      <button type="button" onclick="confirmDialogAction()" style="padding: 0.5rem 1rem; background: hsl(0 86% 97%); border: 1.5px solid hsl(0 86% 90%); border-radius: 6px; font-size: 0.875rem; font-weight: 500; color: hsl(0 74% 42%); cursor: pointer; transition: all 0.15s;" onmouseover="this.style.background='hsl(0 86% 95%)'" onmouseout="this.style.background='hsl(0 86% 97%)'">
        Close and Discard Changes
      </button>
    </div>
  </div>
</div>

<!-- New Invoice Modal with Tabs -->
<div id="newInvoiceModal" onclick="attemptCloseInvoiceModal()" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; align-items: center; justify-content: center; padding: 2rem; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);">
  <div id="invoiceModalContent" onclick="event.stopPropagation()" tabindex="-1" style="width: 100%; max-width: 1200px; max-height: 92vh; background: white; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35), 0 10px 25px -5px rgba(0,0,0,0.15); animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; overflow: hidden; position: relative; pointer-events: auto; outline: none;">
    
    <!-- Enhanced Header with Pattern -->
    <div style="background: linear-gradient(135deg, hsl(0 0% 100%) 0%, hsl(210 20% 98%) 100%); padding: 2rem 2.5rem; display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; border-bottom: 1px solid hsl(214 20% 88%);">
      <!-- Decorative Background Pattern -->
      <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.03; background-image: radial-gradient(circle at 2px 2px, hsl(215 16% 47%) 1px, transparent 1px); background-size: 24px 24px; pointer-events: none;"></div>
      
      <div style="position: relative; z-index: 1;">
        <div style="display: inline-flex; align-items: center; gap: 0.625rem; background: linear-gradient(135deg, hsl(215 20% 55%) 0%, hsl(215 25% 65%) 100%); padding: 0.5rem 1rem; border-radius: 24px; font-size: 0.75rem; font-weight: 700; color: white; margin-bottom: 0.75rem; border: 1px solid hsl(215 20% 50%); box-shadow: 0 2px 8px rgba(113,148,165,0.25), 0 1px 2px rgba(0,0,0,0.05); letter-spacing: 0.05em;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M9 12H15M9 16H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L18.7071 8.70711C18.8946 8.89464 19 9.149 19 9.41421V19C19 20.1046 18.1046 21 17 21Z"/>
          </svg>
          NEW INVOICE
        </div>
        <h2 style="font-size: 1.875rem; font-weight: 800; margin: 0; letter-spacing: -0.03em; color: hsl(215 25% 35%); text-shadow: 0 1px 2px rgba(0,0,0,0.05);">
          Create Professional Invoice
        </h2>
      </div>
      <button type="button" onclick="attemptCloseInvoiceModal()" style="background: hsl(240 5% 96%); border: 1px solid hsl(214 20% 88%); border-radius: 10px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(215 16% 47%); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; z-index: 1;" onmouseover="this.style.background='hsl(240 5% 92%)'; this.style.borderColor='hsl(215 20% 75%)'; this.style.transform='rotate(90deg) scale(1.05)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.background='hsl(240 5% 96%)'; this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='rotate(0) scale(1)'; this.style.boxShadow='none'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    
    <!-- Modal Body with Tabs -->
    <form id="newInvoiceForm" onsubmit="return submitNewInvoice(event)" novalidate style="flex: 1; overflow: hidden; display: flex; flex-direction: column;">
      <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.75rem; padding: 1.75rem; flex: 1; overflow: hidden;">
        
        <!-- LEFT COLUMN: Tabbed Content -->
        <div style="display: flex; flex-direction: column; min-height: 0;">
          <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; height: 100%;">
            
            <!-- Tab Navigation -->
            <div style="display: flex; background: hsl(240 5% 96%); border-bottom: 2px solid hsl(240 6% 90%); padding: 0; overflow-x: auto; flex-shrink: 0;">
              <button type="button" class="invoice-tab-btn active" onclick="switchInvoiceTab('details')" data-tab="details" style="padding: 0.75rem 1.125rem; border: none; background: white; border-bottom: 3px solid #7194A5; font-weight: 600; cursor: pointer; white-space: nowrap; color: #7194A5; font-size: 0.8125rem;">
                📋 Invoice Details
              </button>
              <button type="button" class="invoice-tab-btn" onclick="switchInvoiceTab('items')" data-tab="items" style="padding: 0.75rem 1.125rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem;">
                📦 Line Items
              </button>
              <button type="button" class="invoice-tab-btn" onclick="switchInvoiceTab('customer')" data-tab="customer" style="padding: 0.75rem 1.125rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem;">
                👤 Customer
              </button>
              <button type="button" class="invoice-tab-btn" onclick="switchInvoiceTab('payment')" data-tab="payment" style="padding: 0.75rem 1.125rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem;">
                💳 Transaction
              </button>
              <button type="button" class="invoice-tab-btn" onclick="switchInvoiceTab('notes')" data-tab="notes" style="padding: 0.75rem 1.125rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem;">
                📝 Notes
              </button>
            </div>
            
            <!-- Tab Content Container -->
            <div style="flex: 1; overflow-y: auto; min-height: 0;">
        
        <!-- Tab: Invoice Details -->
        <div class="invoice-tab-content active" id="invoice-tab-details" style="padding: 1.5rem;">
          <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem;">
            <div>
              <label class="invoice-modal-label">
                Invoice Number <span style="color: hsl(0 74% 42%);">*</span>
              </label>
              <div style="display: flex; gap: 0.5rem;">
                <input type="text" name="invoice_number" id="invoiceNumberInput" required placeholder="INV-2024-001" class="invoice-modal-input" style="flex: 1;" />
                <button type="button" onclick="generateInvoiceNumber()" title="Generate Invoice Number" style="padding: 0.625rem 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 6px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; font-weight: 600; color: #7194A5;" onmouseover="this.style.background='hsl(210 20% 96%)'; this.style.borderColor='#7194A5'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                  </svg>
                  Generate
                </button>
              </div>
            </div>
            <div>
              <label class="invoice-modal-label">
                Reference / PO Number
              </label>
              <div style="display: flex; gap: 0.5rem;">
                <input type="text" name="reference" id="referenceNumberInput" placeholder="PO-12345" class="invoice-modal-input" style="flex: 1;" />
                <button type="button" onclick="generateReferenceNumber()" title="Generate Reference Number" style="padding: 0.625rem 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 6px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; font-weight: 600; color: #7194A5;" onmouseover="this.style.background='hsl(210 20% 96%)'; this.style.borderColor='#7194A5'; this.style.transform='translateY(-1px)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                  </svg>
                  Generate
                </button>
              </div>
            </div>
            <div>
              <label class="invoice-modal-label">
                Invoice Date <span style="color: hsl(0 74% 42%);">*</span>
              </label>
              <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>" class="invoice-modal-input" />
            </div>
            <div>
              <label class="invoice-modal-label">
                Due Date <span style="color: hsl(0 74% 42%);">*</span>
              </label>
              <input type="date" name="due_date" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" class="invoice-modal-input" />
            </div>
            <div>
              <label class="invoice-modal-label">
                Tax Rate (%)
              </label>
              <input type="number" name="tax_rate" value="12" min="0" max="100" step="0.01" class="invoice-modal-input" />
            </div>
            <div>
              <label class="invoice-modal-label">
                Discount (%)
              </label>
              <input type="number" name="discount_percent" value="0" min="0" max="100" step="0.01" class="invoice-modal-input" />
            </div>
          </div>
        </div>
        
        <!-- Tab: Line Items -->
        <div class="invoice-tab-content" id="invoice-tab-items" style="padding: 1.5rem; display: none;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Products & Services</h3>
            <button type="button" onclick="addInvoiceLineItem()" style="padding: 0.5rem 1rem; background: #7194A5; border: none; border-radius: 6px; color: white; font-size: 0.8125rem; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#5d7a8a'" onmouseout="this.style.background='#7194A5'">
              + Add Line Item
            </button>
          </div>
          <div id="invoiceLineItems"></div>
        </div>
        
        <!-- Tab: Customer -->
        <div class="invoice-tab-content" id="invoice-tab-customer" style="padding: 1.5rem; display: none;">
          <div style="display: grid; grid-template-columns: 1fr; gap: 1.25rem;">
            <div>
              <label style="display: block; font-size: 0.875rem; font-weight: 500; color: hsl(222 47% 17%); margin-bottom: 0.375rem;">
                Customer Name <span style="color: hsl(0 74% 42%);">*</span>
              </label>
              <input type="text" name="customer" required placeholder="ABC Corporation" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; font-size: 0.875rem;" />
            </div>
            <div>
              <label style="display: block; font-size: 0.875rem; font-weight: 500; color: hsl(222 47% 17%); margin-bottom: 0.375rem;">
                Email Address
              </label>
              <input type="email" name="customer_email" placeholder="contact@customer.com" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; font-size: 0.875rem;" />
            </div>
            <div>
              <label style="display: block; font-size: 0.875rem; font-weight: 500; color: hsl(222 47% 17%); margin-bottom: 0.375rem;">
                Phone Number
              </label>
              <input type="tel" name="customer_phone" placeholder="+63 912 345 6789" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; font-size: 0.875rem;" />
            </div>
            <div>
              <label style="display: block; font-size: 0.875rem; font-weight: 500; color: hsl(222 47% 17%); margin-bottom: 0.375rem;">
                Billing Address
              </label>
              <textarea name="customer_address" rows="3" placeholder="Street address, city, state, postal code" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; font-size: 0.875rem; resize: vertical;"></textarea>
            </div>
          </div>
        </div>
        
        <!-- Tab: Transaction -->
        <div class="invoice-tab-content" id="invoice-tab-payment" style="padding: 1.5rem; display: none; overflow-y: auto;">
          
          <!-- Payment Terms & Currency -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <label class="invoice-modal-label">Payment Terms <span style="color: hsl(0 74% 42%);">*</span></label>
              <select name="payment_terms" class="invoice-modal-select" required>
                <option value="Due on Receipt">Due on Receipt</option>
                <option value="Net 7">Net 7 Days</option>
                <option value="Net 15">Net 15 Days</option>
                <option value="Net 30" selected>Net 30 Days</option>
                <option value="Net 45">Net 45 Days</option>
                <option value="Net 60">Net 60 Days</option>
                <option value="Net 90">Net 90 Days</option>
              </select>
            </div>
            <div>
              <label class="invoice-modal-label">Currency <span style="color: hsl(0 74% 42%);">*</span></label>
              <select name="currency" class="invoice-modal-select" required onchange="updateCurrencySymbols(this.value)">
                <?php
                $currentCurrency = CurrencyHelper::getCurrentCurrency();
                $currencies = [
                  // Major currencies
                  'USD' => '$ USD - US Dollar',
                  'EUR' => '€ EUR - Euro',
                  'GBP' => '£ GBP - British Pound',
                  'JPY' => '¥ JPY - Japanese Yen',
                  'CNY' => '¥ CNY - Chinese Yuan',
                  
                  // Asian currencies
                  'PHP' => '₱ PHP - Philippine Peso',
                  'SGD' => 'S$ SGD - Singapore Dollar',
                  'HKD' => 'HK$ HKD - Hong Kong Dollar',
                  'THB' => '฿ THB - Thai Baht',
                  'MYR' => 'RM MYR - Malaysian Ringgit',
                  'IDR' => 'Rp IDR - Indonesian Rupiah',
                  'VND' => '₫ VND - Vietnamese Dong',
                  'KRW' => '₩ KRW - South Korean Won',
                  'INR' => '₹ INR - Indian Rupee',
                  
                  // Other major currencies
                  'AUD' => 'A$ AUD - Australian Dollar',
                  'CAD' => 'C$ CAD - Canadian Dollar',
                  'CHF' => 'Fr CHF - Swiss Franc',
                  'NZD' => 'NZ$ NZD - New Zealand Dollar',
                  'SEK' => 'kr SEK - Swedish Krona',
                  'NOK' => 'kr NOK - Norwegian Krone',
                  'DKK' => 'kr DKK - Danish Krone',
                  
                  // Middle East & Africa
                  'AED' => 'د.إ AED - UAE Dirham',
                  'SAR' => '﷼ SAR - Saudi Riyal',
                  'ZAR' => 'R ZAR - South African Rand',
                  
                  // Latin America
                  'MXN' => '$ MXN - Mexican Peso',
                  'BRL' => 'R$ BRL - Brazilian Real',
                  'ARS' => '$ ARS - Argentine Peso'
                ];
                
                foreach ($currencies as $code => $label) {
                  $selected = ($code === $currentCurrency) ? 'selected' : '';
                  echo "<option value=\"$code\" $selected>$label</option>";
                }
                ?>
              </select>
              <p style="font-size: 0.75rem; color: hsl(215 16% 47%); margin: 0.375rem 0 0 0;">
                Default from settings: <strong><?php echo CurrencyHelper::getCurrentCurrency(); ?></strong>
              </p>
            </div>
          </div>
          
          <!-- Payment Methods with Ewallet Support -->
          <div style="margin-bottom: 1.5rem;">
            <label class="invoice-modal-label" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.875rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                <line x1="1" y1="10" x2="23" y2="10"/>
              </svg>
              Accepted Payment Methods
            </label>
            <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0 0 1rem 0;">Select all payment methods you accept for this invoice</p>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">
              <label class="payment-method-checkbox" style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                <input type="checkbox" name="payment_methods[]" value="bank_transfer" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                <span style="font-size: 0.875rem; font-weight: 600;">🏦 Bank Transfer</span>
              </label>
              <label class="payment-method-checkbox" style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                <input type="checkbox" name="payment_methods[]" value="credit_card" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                <span style="font-size: 0.875rem; font-weight: 600;">💳 Credit Card</span>
              </label>
              <label class="payment-method-checkbox" style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                <input type="checkbox" name="payment_methods[]" value="cash" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                <span style="font-size: 0.875rem; font-weight: 600;">💵 Cash</span>
              </label>
              <label class="payment-method-checkbox" style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                <input type="checkbox" name="payment_methods[]" value="paypal" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                <span style="font-size: 0.875rem; font-weight: 600;">🅿️ PayPal</span>
              </label>
              <label class="payment-method-checkbox" style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                <input type="checkbox" name="payment_methods[]" value="gcash" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                <span style="font-size: 0.875rem; font-weight: 600;">📱 GCash</span>
              </label>
              <label class="payment-method-checkbox" style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                <input type="checkbox" name="payment_methods[]" value="paymaya" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                <span style="font-size: 0.875rem; font-weight: 600;">💚 PayMaya</span>
              </label>
            </div>
          </div>
          
          <!-- Payment Instructions -->
          <div style="margin-bottom: 1.5rem;">
            <label class="invoice-modal-label">Payment Instructions</label>
            <textarea name="payment_instructions" class="invoice-modal-textarea" rows="3" placeholder="Additional payment instructions or notes..."></textarea>
          </div>
          
          <!-- Collapsible Bank Account Details -->
          <div style="margin-bottom: 1rem;">
            <button type="button" onclick="toggleBankDetails()" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1rem; background: hsl(240 5% 98%); border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'; this.style.borderColor='#7194A5'" onmouseout="this.style.background='hsl(240 5% 98%)'; this.style.borderColor='hsl(214 20% 88%)'">
              <div style="display: flex; align-items: center; gap: 0.625rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2.5">
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                  <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span style="font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Bank Account Details</span>
                <span style="font-size: 0.75rem; color: hsl(215 16% 47%); font-weight: 500;">(Optional)</span>
              </div>
              <svg id="bankDetailsChevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition: transform 0.2s;">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </button>
            
            <div id="bankDetailsContent" style="display: none; margin-top: 0.875rem; padding: 1rem; background: hsl(214 95% 98%); border: 1.5px solid hsl(214 95% 90%); border-radius: 8px; animation: slideDown 0.3s ease;">
              <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0 0 1rem 0;">Provide banking information for wire transfers and direct deposits</p>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.875rem;">
                <div>
                  <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">Bank Name</label>
                  <input type="text" name="bank_name" class="invoice-modal-input" placeholder="e.g., BDO, BPI, Metrobank">
                </div>
                <div>
                  <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">Account Number</label>
                  <input type="text" name="bank_account" class="invoice-modal-input" placeholder="XXXX-XXXX-XXXX">
                </div>
                <div>
                  <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">Account Name</label>
                  <input type="text" name="bank_account_name" class="invoice-modal-input" placeholder="Account holder name">
                </div>
                <div>
                  <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">SWIFT/BIC Code</label>
                  <input type="text" name="bank_swift" class="invoice-modal-input" placeholder="ABCDPHM1XXX">
                </div>
              </div>
            </div>
          </div>
          
          <!-- Collapsible Ewallet Details -->
          <div style="margin-bottom: 1rem;">
            <button type="button" onclick="toggleEwalletDetails()" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1rem; background: hsl(240 5% 98%); border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'; this.style.borderColor='#7194A5'" onmouseout="this.style.background='hsl(240 5% 98%)'; this.style.borderColor='hsl(214 20% 88%)'">
              <div style="display: flex; align-items: center; gap: 0.625rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2.5">
                  <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                  <line x1="12" y1="18" x2="12" y2="18"/>
                </svg>
                <span style="font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Ewallet Information</span>
                <span style="font-size: 0.75rem; color: hsl(215 16% 47%); font-weight: 500;">(Optional)</span>
              </div>
              <svg id="ewalletDetailsChevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition: transform 0.2s;">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </button>
            
            <div id="ewalletDetailsContent" style="display: none; margin-top: 0.875rem; padding: 1rem; background: hsl(143 85% 97%); border: 1.5px solid hsl(143 85% 90%); border-radius: 8px; animation: slideDown 0.3s ease;">
              <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0 0 1rem 0;">Provide your GCash or PayMaya details for digital payments</p>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.875rem;">
                <div>
                  <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">GCash Number</label>
                  <input type="text" name="gcash_number" class="invoice-modal-input" placeholder="09XX-XXX-XXXX">
                </div>
                <div>
                  <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">GCash Name</label>
                  <input type="text" name="gcash_name" class="invoice-modal-input" placeholder="Registered name">
                </div>
                <div>
                  <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">PayMaya Number</label>
                  <input type="text" name="paymaya_number" class="invoice-modal-input" placeholder="09XX-XXX-XXXX">
                </div>
                <div>
                  <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">PayMaya Name</label>
                  <input type="text" name="paymaya_name" class="invoice-modal-input" placeholder="Registered name">
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Tab: Notes -->
        <div class="invoice-tab-content" id="invoice-tab-notes" style="padding: 1.5rem; display: none;">
          <div style="display: grid; grid-template-columns: 1fr; gap: 1.25rem;">
            <div>
              <label style="display: block; font-size: 0.875rem; font-weight: 500; color: hsl(222 47% 17%); margin-bottom: 0.375rem;">
                Notes / Terms & Conditions
              </label>
              <textarea name="notes" rows="8" placeholder="Payment instructions, terms and conditions, warranty information..." style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; font-size: 0.875rem; resize: vertical;"></textarea>
            </div>
            <div>
              <label style="display: block; font-size: 0.875rem; font-weight: 500; color: hsl(222 47% 17%); margin-bottom: 0.375rem;">
                Internal Notes
              </label>
              <textarea name="internal_notes" rows="4" placeholder="Private notes not visible to customer..." style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; font-size: 0.875rem; resize: vertical;"></textarea>
            </div>
          </div>
            </div>
          </div>
        </div>
        </div>
        
        <!-- RIGHT COLUMN: Summary & Actions -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem; min-height: 0;">
          
          <!-- Invoice Summary -->
          <div style="background: linear-gradient(135deg, hsl(240 5% 98%), white); border: 1.5px solid hsl(214 20% 88%); border-radius: 10px; padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.875rem;">
              <div style="width: 32px; height: 32px; background: linear-gradient(135deg, rgba(113,148,165,0.15), rgba(113,148,165,0.25)); border-radius: 7px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; color: #7194A5;">
                <?php echo htmlspecialchars(CurrencyHelper::symbol()); ?>
              </div>
              <h3 style="font-size: 0.9375rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;">Invoice Summary</h3>
            </div>
            
            <div style="space-y: 0.75rem;">
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0; border-bottom: 1px solid hsl(214 20% 92%);">
                <span style="font-size: 0.8125rem; color: hsl(215 16% 47%); font-weight: 500;">Subtotal</span>
                <span id="invoiceSubtotal" style="font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);"><?php echo htmlspecialchars(CurrencyHelper::symbol()); ?>0.00</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0; border-bottom: 1px solid hsl(214 20% 92%);">
                <span style="font-size: 0.8125rem; color: hsl(215 16% 47%); font-weight: 500;">Tax (<span id="taxRateDisplay">12</span>%)</span>
                <span id="invoiceTax" style="font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);"><?php echo htmlspecialchars(CurrencyHelper::symbol()); ?>0.00</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0; border-bottom: 1px solid hsl(214 20% 92%);">
                <span style="font-size: 0.8125rem; color: hsl(215 16% 47%); font-weight: 500;">Discount (<span id="discountDisplay">0</span>%)</span>
                <span id="invoiceDiscount" style="font-size: 0.875rem; font-weight: 600; color: hsl(0 74% 42%);">-<?php echo htmlspecialchars(CurrencyHelper::symbol()); ?>0.00</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.875rem 0; background: linear-gradient(135deg, rgba(113,148,165,0.08), rgba(113,148,165,0.12)); margin: 0.5rem -0.75rem -0.75rem; padding: 1rem 0.75rem; border-radius: 0 0 10px 10px;">
                <span style="font-size: 0.9375rem; font-weight: 700; color: hsl(222 47% 17%);">Total Amount</span>
                <span id="invoiceTotal" style="font-size: 1.25rem; font-weight: 800; color: #7194A5;"><?php echo htmlspecialchars(CurrencyHelper::symbol()); ?>0.00</span>
              </div>
            </div>
          </div>
          
          <!-- Action Buttons -->
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <button type="submit" style="width: 100%; padding: 0.875rem 1.5rem; background: linear-gradient(135deg, #7194A5 0%, #5d7a8a 100%); border: none; border-radius: 8px; color: white; font-weight: 700; font-size: 0.9375rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(113,148,165,0.3), 0 4px 8px rgba(113,148,165,0.15);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(113,148,165,0.4), 0 8px 16px rgba(113,148,165,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(113,148,165,0.3), 0 4px 8px rgba(113,148,165,0.15)'">
                Create Invoice
            </button>
            <button type="button" onclick="attemptCloseInvoiceModal()" style="width: 100%; padding: 0.75rem; background: hsl(240 5% 98%); border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; color: hsl(222 47% 17%); font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 95%)'" onmouseout="this.style.background='hsl(240 5% 98%)'">
              Cancel
            </button>
          </div>
          
          <!-- Quick Tips -->
          <div style="background: hsl(214 95% 98%); border: 1px solid hsl(214 95% 85%); border-radius: 8px; padding: 0.875rem;">
            <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
              <span style="font-size: 1rem;">💡</span>
              <div>
                <p style="font-size: 0.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0 0 0.25rem 0;">Quick Tip</p>
                <p style="font-size: 0.6875rem; color: hsl(215 16% 47%); margin: 0; line-height: 1.4;">All fields are auto-saved. You can close and resume later!</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
// ========================================
// ADVANCED INVOICE MANAGEMENT
// ========================================

// Table Sorting State
let currentSortColumn = '';
let currentSortDirection = 'asc';

// Table Sorting Function
function sortTable(column) {
  const table = document.getElementById('invoicesTable');
  const tbody = table.querySelector('tbody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  
  // Toggle sort direction if same column, otherwise default to ascending
  if (currentSortColumn === column) {
    currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    currentSortDirection = 'asc';
    currentSortColumn = column;
  }
  
  // Sort rows based on data attributes
  rows.sort((a, b) => {
    let aVal, bVal;
    
    switch(column) {
      case 'customer':
        aVal = a.dataset.customer.toLowerCase();
        bVal = b.dataset.customer.toLowerCase();
        break;
      case 'date':
        aVal = parseFloat(a.dataset.date);
        bVal = parseFloat(b.dataset.date);
        break;
      case 'due_date':
        aVal = parseFloat(a.dataset.due);
        bVal = parseFloat(b.dataset.due);
        break;
      case 'total':
        aVal = parseFloat(a.dataset.total);
        bVal = parseFloat(b.dataset.total);
        break;
      case 'paid':
        aVal = parseFloat(a.dataset.paid);
        bVal = parseFloat(b.dataset.paid);
        break;
      case 'balance':
        aVal = parseFloat(a.dataset.balance);
        bVal = parseFloat(b.dataset.balance);
        break;
      case 'status':
        aVal = a.dataset.status.toLowerCase();
        bVal = b.dataset.status.toLowerCase();
        break;
      default:
        return 0;
    }
    
    if (aVal < bVal) return currentSortDirection === 'asc' ? -1 : 1;
    if (aVal > bVal) return currentSortDirection === 'asc' ? 1 : -1;
    return 0;
  });
  
  // Clear and re-append sorted rows
  tbody.innerHTML = '';
  rows.forEach(row => tbody.appendChild(row));
  
  // Update sort indicators
  updateSortIndicators(column);
  
  // Log sort action
  const direction = currentSortDirection === 'asc' ? 'ascending' : 'descending';
  console.log(`✓ Sorted by ${column} (${direction})`);
}

// Toggle table and empty state visibility based on data
function toggleTableVisibility() {
  const tbody = document.querySelector('#invoicesTable tbody');
  const allRows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
  
  // Count only visible rows (not hidden by filters/search)
  const visibleRows = allRows.filter(row => {
    const display = window.getComputedStyle(row).display;
    return display !== 'none';
  }).length;
  
  const tableContainer = document.getElementById('invoicesTableContainer');
  const emptyState = document.getElementById('emptyStateContainer');
  const pagination = document.getElementById('paginationContainer');
  
  if (visibleRows === 0 || allRows.length === 0) {
    // No visible invoices: show empty state, hide table and pagination
    if (tableContainer) tableContainer.style.display = 'none';
    if (emptyState) emptyState.style.display = 'block';
    if (pagination) pagination.style.display = 'none';
    console.log('✓ Empty state displayed');
  } else {
    // Has visible invoices: show table, hide empty state
    if (tableContainer) tableContainer.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';
    if (pagination) pagination.style.display = 'flex';
    console.log(`✓ Table displayed with ${visibleRows} visible invoice(s)`);
  }
}

// Update Sort Indicators
function updateSortIndicators(activeColumn) {
  // Reset all sort icons to default opacity
  const allIcons = ['customer', 'date', 'due', 'total', 'paid', 'balance', 'status'];
  allIcons.forEach(col => {
    const icon = document.getElementById(`sort-${col}-icon`);
    if (icon) {
      icon.style.opacity = '0.4';
      icon.style.color = '';
      icon.style.transform = 'rotate(0deg)';
    }
  });
  
  // Highlight active sort column
  const columnMap = {
    'customer': 'customer',
    'date': 'date',
    'due_date': 'due',
    'total': 'total',
    'paid': 'paid',
    'balance': 'balance',
    'status': 'status'
  };
  
  const iconId = `sort-${columnMap[activeColumn]}-icon`;
  const activeIcon = document.getElementById(iconId);
  if (activeIcon) {
    activeIcon.style.opacity = '1';
    activeIcon.style.color = '#7194A5';
    
    // Rotate icon based on direction
    if (currentSortDirection === 'desc') {
      activeIcon.style.transform = 'rotate(180deg)';
    } else {
      activeIcon.style.transform = 'rotate(0deg)';
    }
  }
}

// Checkbox visibility state
let checkboxesVisible = false;

// Toggle checkboxes visibility
function toggleCheckboxes() {
  checkboxesVisible = !checkboxesVisible;
  const checkboxColumns = document.querySelectorAll('.checkbox-column');
  
  checkboxColumns.forEach(col => {
    col.style.display = checkboxesVisible ? 'table-cell' : 'none';
  });
  
  console.log('Checkboxes visible:', checkboxesVisible);
}

// Toggle All Invoices Selection
function toggleAllInvoices(checkbox) {
  const checkboxes = document.querySelectorAll('.invoice-checkbox');
  checkboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
}

// Close all dropdowns
function closeDropdowns() {
  const dropdown = document.getElementById('bulkActionsDropdown');
  if (dropdown) {
    dropdown.style.display = 'none';
  }
}

// Bulk actions menu - Toggle checkboxes and show menu
function bulkActionsMenu() {
  // Stop event propagation
  if (event) {
    event.stopPropagation();
  }
  
  const dropdown = document.getElementById('bulkActionsDropdown');
  const isDropdownOpen = dropdown.style.display === 'block';
  
  // If dropdown is already open, close everything
  if (isDropdownOpen) {
    closeDropdowns();
    if (checkboxesVisible) {
      toggleCheckboxes();
    }
    console.log('Bulk actions menu closed, checkboxes hidden');
    return;
  }
  
  // If checkboxes are not visible, show them first
  if (!checkboxesVisible) {
    toggleCheckboxes();
    console.log('Checkboxes shown');
    return;
  }
  
  // Checkboxes are visible - check if any are selected
  const selectedCount = document.querySelectorAll('.invoice-checkbox:checked').length;
  
  // If no selections, hide checkboxes (toggle off)
  if (selectedCount === 0) {
    toggleCheckboxes();
    console.log('No selections - checkboxes hidden');
    return;
  }
  
  // Selections exist - open the dropdown menu
  const button = event.target.closest('button');
  if (!button) return;
  
  const rect = button.getBoundingClientRect();
  
  // Position dropdown
  dropdown.style.top = (rect.bottom + 5) + 'px';
  dropdown.style.right = '20px';
  dropdown.style.display = 'block';
  
  // Update count
  document.getElementById('selectedCount').textContent = selectedCount;
  
  console.log('Bulk actions menu opened for', selectedCount, 'invoices');
  
  // Close on outside click
  setTimeout(() => {
    document.addEventListener('click', closeDropdowns, { once: true });
  }, 50);
}

// Close New Invoice modal with ESC key (shadcn behavior parity)
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('newInvoiceModal');
    if (modal && modal.style.display === 'flex') {
      attemptCloseInvoiceModal();
    }
  }
});

// Handle bulk actions
function handleBulkAction(action) {
  const selected = document.querySelectorAll('.invoice-checkbox:checked');
  const count = selected.length;
  
  closeDropdowns();
  
  const ids = Array.from(selected).map(cb => cb.value);
  
  switch(action) {
    case 'send_email':
      if (confirm(`Send email to ${count} selected invoice(s)?`)) {
        console.log(`✓ Sending ${count} invoices via email...`);
        console.log('Bulk send email:', ids);
      }
      break;
      
    case 'mark_paid':
      if (confirm(`Mark ${count} selected invoice(s) as paid?`)) {
        console.log(`✓ Marked ${count} invoices as paid`);
        console.log('Bulk mark paid:', ids);
      }
      break;
      
    case 'mark_sent':
      if (confirm(`Mark ${count} selected invoice(s) as sent?`)) {
        console.log(`✓ Marked ${count} invoices as sent`);
        console.log('Bulk mark sent:', ids);
      }
      break;
      
    case 'export_pdf':
      console.log(`ℹ Exporting ${count} invoices to PDF...`);
      console.log('Bulk export PDF:', ids);
      break;
      
    case 'delete':
      if (confirm(`Delete ${count} selected invoice(s)? This action cannot be undone.`)) {
        console.log(`✗ Deleted ${count} invoices`);
        console.log('Bulk delete:', ids);
      }
      break;
  }
}

// Recurring Invoice Dialog
function showRecurringInvoiceDialog() {
  console.log('ℹ Recurring invoice feature - Coming soon!');
  alert('Recurring Invoice Setup\n\nConfigure automated invoice generation:\n- Weekly\n- Monthly\n- Quarterly\n- Annually\n\nFeature coming soon!');
}

// Invoice Templates
function showInvoiceTemplates() {
  console.log('ℹ Invoice templates - Coming soon!');
  alert('Invoice Templates\n\nChoose from professional templates:\n- Modern Design\n- Classic Layout\n- Minimalist\n- Corporate\n\nFeature coming soon!');
}

// Advanced Filters
function showAdvancedFilters() {
  console.log('ℹ Advanced filters - Coming soon!');
  alert('Advanced Filters\n\nFilter invoices by:\n- Date Range\n- Amount Range\n- Customer\n- Status\n- Payment Method\n- Currency\n\nFeature coming soon!');
}

// Export Invoices
function exportInvoices() {
  console.log('✓ Exporting invoices to CSV...');
  console.log('Export invoices to CSV');
}

// Tab Switching
function switchInvoiceTab(tabName) {
  document.querySelectorAll('.invoice-tab-content').forEach(tab => {
    tab.style.display = 'none';
  });
  
  document.querySelectorAll('.invoice-tab-btn').forEach(btn => {
    btn.style.background = 'transparent';
    btn.style.borderBottom = '3px solid transparent';
    btn.style.color = 'hsl(215 16% 47%)';
    btn.classList.remove('active');
  });
  
  document.getElementById('invoice-tab-' + tabName).style.display = 'block';
  
  const activeBtn = document.querySelector(`.invoice-tab-btn[data-tab="${tabName}"]`);
  activeBtn.style.background = 'white';
  activeBtn.style.borderBottom = '3px solid #7194A5';
  activeBtn.style.color = '#7194A5';
  activeBtn.classList.add('active');
}

// Generate Invoice Number
function generateInvoiceNumber() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  
  // For frontend, we'll use a random number since we can't count existing invoices
  // The backend will override this with the correct sequential number
  const random = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0');
  
  const invoiceNumber = `INV-${year}${month}-${random}`;
  document.getElementById('invoiceNumberInput').value = invoiceNumber;
  
  // Add animation effect
  const input = document.getElementById('invoiceNumberInput');
  input.style.background = 'hsl(143 85% 96%)';
  input.style.borderColor = 'hsl(143 85% 70%)';
  setTimeout(() => {
    input.style.background = '';
    input.style.borderColor = '';
  }, 1000);
  
  console.log(`✓ Invoice number generated: ${invoiceNumber}`);
  
  return invoiceNumber;
}

// Generate Reference/PO Number
function generateReferenceNumber() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const random = String(Math.floor(Math.random() * 10000)).padStart(4, '0');
  
  const referenceNumber = `REF-${year}${month}-${random}`;
  document.getElementById('referenceNumberInput').value = referenceNumber;
  
  // Add animation effect
  const input = document.getElementById('referenceNumberInput');
  input.style.background = 'hsl(143 85% 96%)';
  input.style.borderColor = 'hsl(143 85% 70%)';
  setTimeout(() => {
    input.style.background = '';
    input.style.borderColor = '';
  }, 1000);
  
  console.log(`✓ Reference number generated: ${referenceNumber}`);
  
  return referenceNumber;
}

// Modal Management
function showNewInvoiceModal() {
  const modal = document.getElementById('newInvoiceModal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  if (document.getElementById('invoiceLineItems').children.length === 0) {
    addInvoiceLineItem();
  }
  
  // Initialize real-time calculations
  setupInvoiceCalculations();
}

function attemptCloseInvoiceModal() {
  // Check if form has meaningful user input
  const form = document.getElementById('newInvoiceForm');
  
  // Check if any text inputs have been filled (excluding auto-generated fields)
  const textInputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], textarea, input[type="date"]');
  let hasTextInput = false;
  textInputs.forEach(input => {
    // Skip auto-generated invoice/reference numbers that match the pattern
    const isInvoiceNumber = input.name === 'invoice_number' && /^INV-\d{6}-\d{3}$/.test(input.value);
    const isReferenceNumber = input.name === 'reference' && /^REF-\d{6}-\d{4}$/.test(input.value);
    
    // Skip date fields that have default values
    const isDateWithDefault = input.type === 'date' && input.hasAttribute('value');
    
    if (!isInvoiceNumber && !isReferenceNumber && !isDateWithDefault) {
      if (input.value.trim() !== '' && input.value !== input.getAttribute('placeholder')) {
        hasTextInput = true;
      }
    }
  });
  
  // Check if line items have actual data (not just empty rows)
  const lineItems = document.querySelectorAll('.invoice-line-item');
  let hasLineItemData = false;
  lineItems.forEach(item => {
    const description = item.querySelector('input[placeholder="Item description"]');
    const price = item.querySelector('input[placeholder="Price"]');
    if ((description && description.value.trim() !== '') || (price && price.value.trim() !== '')) {
      hasLineItemData = true;
    }
  });
  
  // Check if any checkboxes were modified from default state
  const checkboxes = form.querySelectorAll('input[type="checkbox"]');
  let checkboxModified = false;
  checkboxes.forEach(checkbox => {
    // Only bank_transfer and credit_card are checked by default
    const isDefaultChecked = checkbox.value === 'bank_transfer' || checkbox.value === 'credit_card';
    if (checkbox.checked !== isDefaultChecked) {
      checkboxModified = true;
    }
  });
  
  const hasData = hasTextInput || hasLineItemData || checkboxModified;
  
  if (hasData) {
    // Show custom confirmation popup for unsaved changes
    showConfirmationDialog(() => {
      closeInvoiceModal();
    });
  } else {
    // No data entered, just close
    closeInvoiceModal();
  }
}

function closeInvoiceModal() {
  const modal = document.getElementById('newInvoiceModal');
  modal.style.display = 'none';
  document.body.style.overflow = '';
  document.getElementById('newInvoiceForm').reset();
  document.getElementById('invoiceLineItems').innerHTML = '';
  
  // Reset summary with default currency from settings
  const defaultCurrency = '<?php echo CurrencyHelper::getCurrentCurrency(); ?>';
  const currencySymbol = getCurrencySymbol(defaultCurrency);
  document.getElementById('invoiceSubtotal').textContent = currencySymbol + '0.00';
  document.getElementById('invoiceTax').textContent = currencySymbol + '0.00';
  document.getElementById('invoiceDiscount').textContent = '-' + currencySymbol + '0.00';
  document.getElementById('invoiceTotal').textContent = currencySymbol + '0.00';
  
  switchInvoiceTab('details');
}

// Custom Confirmation Dialog Functions (Shadcn Style)
let confirmDialogCallback = null;

function showConfirmationDialog(onConfirm) {
  confirmDialogCallback = onConfirm;
  
  const dialog = document.getElementById('confirmationDialog');
  const content = document.getElementById('confirmationDialogContent');
  
  dialog.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  // Focus the dialog content for accessibility
  setTimeout(() => {
    content.focus();
  }, 100);
}

function hideConfirmationDialog() {
  const dialog = document.getElementById('confirmationDialog');
  dialog.style.display = 'none';
  document.body.style.overflow = '';
  
  // Restore focus to invoice modal content
  setTimeout(() => {
    const modalContent = document.getElementById('invoiceModalContent');
    if (modalContent) {
      modalContent.focus();
    }
  }, 50);
  
  confirmDialogCallback = null;
}

function confirmDialogAction() {
  if (confirmDialogCallback) {
    confirmDialogCallback();
  }
  hideConfirmationDialog();
}

// Line Items Management
let invoiceLineItemCount = 0;
function addInvoiceLineItem() {
  invoiceLineItemCount++;
  const container = document.getElementById('invoiceLineItems');
  const itemHtml = `
    <div class="invoice-line-item" style="display: grid; grid-template-columns: 2fr 1fr 1fr 40px; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.875rem; background: hsl(214 95% 98%); border-radius: 6px;">
      <input type="text" name="items[${invoiceLineItemCount}][description]" required placeholder="Item description" style="padding: 0.5rem 0.75rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; font-size: 0.875rem; background: white;" />
      <input type="number" name="items[${invoiceLineItemCount}][quantity]" required placeholder="Qty" min="0.01" step="0.01" value="1" class="invoice-calc-input" oninput="calculateInvoiceTotal()" style="padding: 0.5rem 0.75rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; font-size: 0.875rem; background: white;" />
      <input type="number" name="items[${invoiceLineItemCount}][unit_price]" required placeholder="Price" min="0" step="0.01" class="invoice-calc-input" oninput="calculateInvoiceTotal()" style="padding: 0.5rem 0.75rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; font-size: 0.875rem; background: white;" />
      <button type="button" onclick="this.parentElement.remove(); calculateInvoiceTotal();" style="width: 32px; height: 32px; background: hsl(0 86% 97%); border: 1px solid hsl(0 86% 90%); border-radius: 6px; color: hsl(0 74% 42%); cursor: pointer; display: flex; align-items: center; justify-content: center;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </div>
  `;
  container.insertAdjacentHTML('beforeend', itemHtml);
  calculateInvoiceTotal();
}

// Currency Symbol Mapping
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

// Update Currency Symbols when currency changes
function updateCurrencySymbols(currencyCode) {
  calculateInvoiceTotal();
  console.log(`ℹ Currency changed to ${currencyCode}`);
}

// Real-time Invoice Calculations
function setupInvoiceCalculations() {
  // Add listeners to tax and discount fields
  const form = document.getElementById('newInvoiceForm');
  const taxInput = form.querySelector('input[name="tax_rate"]');
  const discountInput = form.querySelector('input[name="discount_percent"]');
  
  if (taxInput) {
    taxInput.addEventListener('input', calculateInvoiceTotal);
  }
  if (discountInput) {
    discountInput.addEventListener('input', calculateInvoiceTotal);
  }
  
  calculateInvoiceTotal();
}

function calculateInvoiceTotal() {
  let subtotal = 0;
  
  // Calculate subtotal from line items
  const lineItems = document.querySelectorAll('.invoice-line-item');
  lineItems.forEach(item => {
    const qty = parseFloat(item.querySelector('input[placeholder="Qty"]')?.value || 0);
    const price = parseFloat(item.querySelector('input[placeholder="Price"]')?.value || 0);
    subtotal += qty * price;
  });
  
  // Get tax rate and discount
  const form = document.getElementById('newInvoiceForm');
  const taxRate = parseFloat(form.querySelector('input[name="tax_rate"]')?.value || 0);
  const discountPercent = parseFloat(form.querySelector('input[name="discount_percent"]')?.value || 0);
  const shippingCost = parseFloat(form.querySelector('input[name="shipping_cost"]')?.value || 0);
  
  // Calculate discount
  const discountAmount = subtotal * (discountPercent / 100);
  const subtotalAfterDiscount = subtotal - discountAmount;
  
  // Calculate tax
  const taxAmount = subtotalAfterDiscount * (taxRate / 100);
  
  // Calculate total
  const total = subtotalAfterDiscount + taxAmount + shippingCost;
  
  // Get selected currency symbol
  const currencySelect = form.querySelector('select[name="currency"]');
  const selectedCurrency = currencySelect ? currencySelect.value : 'PHP';
  const currencySymbol = getCurrencySymbol(selectedCurrency);
  
  // Update display with dynamic currency symbol
  document.getElementById('invoiceSubtotal').textContent = currencySymbol + subtotal.toFixed(2);
  document.getElementById('invoiceTax').textContent = currencySymbol + taxAmount.toFixed(2);
  document.getElementById('invoiceDiscount').textContent = '-' + currencySymbol + discountAmount.toFixed(2);
  document.getElementById('invoiceTotal').textContent = currencySymbol + total.toFixed(2);
  
  // Update rate displays
  document.getElementById('taxRateDisplay').textContent = taxRate.toFixed(1);
  document.getElementById('discountDisplay').textContent = discountPercent.toFixed(1);
}

// Toggle Bank Details Section
function toggleBankDetails() {
  const content = document.getElementById('bankDetailsContent');
  const chevron = document.getElementById('bankDetailsChevron');
  
  if (content.style.display === 'none' || content.style.display === '') {
    content.style.display = 'block';
    content.style.animation = 'slideDown 0.3s ease forwards';
    chevron.style.transform = 'rotate(180deg)';
  } else {
    content.style.animation = 'slideUp2 0.3s ease forwards';
    chevron.style.transform = 'rotate(0deg)';
    setTimeout(() => {
      content.style.display = 'none';
    }, 300);
  }
}

// Toggle Ewallet Details Section
function toggleEwalletDetails() {
  const content = document.getElementById('ewalletDetailsContent');
  const chevron = document.getElementById('ewalletDetailsChevron');
  
  if (content.style.display === 'none' || content.style.display === '') {
    content.style.display = 'block';
    content.style.animation = 'slideDown 0.3s ease forwards';
    chevron.style.transform = 'rotate(180deg)';
  } else {
    content.style.animation = 'slideUp2 0.3s ease forwards';
    chevron.style.transform = 'rotate(0deg)';
    setTimeout(() => {
      content.style.display = 'none';
    }, 300);
  }
}

// Form Submission
async function submitNewInvoice(event) {
  event.preventDefault();
  
  const submitBtn = event.target.querySelector('button[type="submit"]');
  const originalHTML = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = 'Creating...';
  
  try {
    const formData = new FormData(event.target);
    
    // Collect payment methods
    const paymentMethods = [];
    const paymentMethodCheckboxes = event.target.querySelectorAll('input[name="payment_methods[]"]:checked');
    paymentMethodCheckboxes.forEach(cb => paymentMethods.push(cb.value));
    
    const data = {
      invoice_number: formData.get('invoice_number'),
      customer: formData.get('customer'),
      customer_email: formData.get('customer_email'),
      customer_phone: formData.get('customer_phone'),
      customer_address: formData.get('customer_address'),
      date: formData.get('date'),
      due_date: formData.get('due_date'),
      tax_rate: formData.get('tax_rate') || 12,
      discount_percent: formData.get('discount_percent') || 0,
      payment_terms: formData.get('payment_terms'),
      reference: formData.get('reference'),
      shipping_cost: formData.get('shipping_cost') || 0,
      currency: formData.get('currency'),
      notes: formData.get('notes'),
      internal_notes: formData.get('internal_notes'),
      payment_instructions: formData.get('payment_instructions'),
      payment_methods: paymentMethods,
      bank_name: formData.get('bank_name'),
      bank_account: formData.get('bank_account'),
      bank_account_name: formData.get('bank_account_name'),
      bank_swift: formData.get('bank_swift'),
      gcash_number: formData.get('gcash_number'),
      gcash_name: formData.get('gcash_name'),
      paymaya_number: formData.get('paymaya_number'),
      paymaya_name: formData.get('paymaya_name'),
      items: []
    };
    
    // Collect line items from DOM (not FormData due to dynamic field names)
    const lineItemElements = document.querySelectorAll('.invoice-line-item');
    lineItemElements.forEach(item => {
      const description = item.querySelector('input[placeholder="Item description"]')?.value;
      const quantity = item.querySelector('input[placeholder="Qty"]')?.value;
      const unit_price = item.querySelector('input[placeholder="Price"]')?.value;
      
      if (description && quantity && unit_price) {
        data.items.push({
          description: description.trim(),
          quantity: parseFloat(quantity),
          unit_price: parseFloat(unit_price)
        });
      }
    });
    
    // Validate required fields and navigate to first missing field
    const requiredFields = [
      { name: 'invoice_number', label: 'Invoice Number', tab: 'details' },
      { name: 'date', label: 'Invoice Date', tab: 'details' },
      { name: 'due_date', label: 'Due Date', tab: 'details' },
      { name: 'customer', label: 'Customer Name', tab: 'customer' },
      { name: 'payment_terms', label: 'Payment Terms', tab: 'payment' },
      { name: 'currency', label: 'Currency', tab: 'payment' }
    ];
    
    let firstEmptyField = null;
    let firstEmptyTab = null;
    
    for (const field of requiredFields) {
      const value = data[field.name];
      if (!value || (typeof value === 'string' && value.trim() === '')) {
        const element = event.target.querySelector(`[name="${field.name}"]`);
        firstEmptyField = { ...field, element };
        firstEmptyTab = field.tab;
        break;
      }
    }
    
    // Check line items
    if (!firstEmptyField && data.items.length === 0) {
      firstEmptyField = { label: 'at least one line item', tab: 'items' };
      firstEmptyTab = 'items';
    }
    
    // If missing required field, navigate to it
    if (firstEmptyField) {
      // Switch to the tab with the missing field
      switchInvoiceTab(firstEmptyTab);
      
      // Focus and highlight the field if it exists
      if (firstEmptyField.element) {
        setTimeout(() => {
          firstEmptyField.element.focus();
          firstEmptyField.element.style.borderColor = 'hsl(0 74% 42%)';
          firstEmptyField.element.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.1)';
          
          // Scroll into view
          firstEmptyField.element.scrollIntoView({ behavior: 'smooth', block: 'center' });
          
          setTimeout(() => {
            firstEmptyField.element.style.borderColor = '';
            firstEmptyField.element.style.boxShadow = '';
          }, 3000);
        }, 300);
      }
      
      // Log validation message
      console.log(`⚠ Please fill in ${firstEmptyField.label} to create invoice`);
      
      // Re-enable submit button
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalHTML;
      
      return false;
    }
    
    // Send to API
    const response = await fetch('api/save_invoice.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      closeInvoiceModal();
      console.log(`✓ Invoice ${result.invoice_number} created successfully!`);
      setTimeout(() => window.location.reload(), 1000);
    } else {
      throw new Error(result.message || 'Failed to create invoice');
    }
  } catch (error) {
    console.error('✗ Invoice creation error:', error);
    console.error('Error: ' + error.message);
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalHTML;
  }
  
  return false;
}

// View Invoice Modal
let currentViewInvoiceId = null;

function viewInvoice(id) {
  console.log('ℹ Viewing invoice:', id);
  currentViewInvoiceId = id;
  
  // Find the invoice row in the table
  const row = document.querySelector(`tr[data-invoice-id="${id}"]`);
  if (!row) {
    console.error('✗ Invoice not found:', id);
    return;
  }
  
  // Extract data from the row
  const invoiceNumber = row.querySelector('.font-mono')?.textContent || 'N/A';
  const customer = row.dataset.customer || '-';
  const status = row.dataset.status || 'unknown';
  const total = parseFloat(row.dataset.total) || 0;
  const paid = parseFloat(row.dataset.paid) || 0;
  const balance = parseFloat(row.dataset.balance) || 0;
  const dateTimestamp = parseInt(row.dataset.date) || 0;
  const dueTimestamp = parseInt(row.dataset.due) || 0;
  
  // Format dates
  const invoiceDate = dateTimestamp ? new Date(dateTimestamp * 1000).toLocaleDateString('en-US', { 
    month: 'short', day: 'numeric', year: 'numeric' 
  }) : '-';
  const dueDate = dueTimestamp ? new Date(dueTimestamp * 1000).toLocaleDateString('en-US', { 
    month: 'short', day: 'numeric', year: 'numeric' 
  }) : '-';
  
  // Get currency symbol (default to PHP)
  const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';
  const currencyCode = '<?php echo CurrencyHelper::getCurrentCurrency(); ?>';
  
  // Format amounts
  const formatAmount = (amount) => `${currencySymbol}${amount.toFixed(2)}`;
  
  // Status badge classes (matching quotations.php)
  const statusBadges = {
    'paid': 'badge-success',
    'unpaid': 'badge-default',
    'partial': 'badge-warning',
    'overdue': 'badge-danger'
  };
  const badgeClass = statusBadges[status.toLowerCase()] || 'badge-default';
  
  // Populate modal
  document.getElementById('viewInvoiceNumber').textContent = invoiceNumber;
  
  const statusBadge = document.getElementById('viewInvoiceStatus');
  statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
  statusBadge.className = 'badge ' + badgeClass;
  
  document.getElementById('viewCustomerName').textContent = customer;
  document.getElementById('viewCustomerEmail').textContent = '-'; // Email not in table data
  document.getElementById('viewInvoiceDate').textContent = invoiceDate;
  document.getElementById('viewDueDate').textContent = dueDate;
  document.getElementById('viewTotalAmount').textContent = formatAmount(total);
  document.getElementById('viewPaidAmount').textContent = formatAmount(paid);
  document.getElementById('viewBalanceAmount').textContent = formatAmount(balance);
  document.getElementById('viewCurrency').textContent = currencyCode;
  
  // Hide notes section (no notes in table data)
  document.getElementById('viewNotesSection').style.display = 'none';
  
  // Show modal
  const modal = document.getElementById('viewInvoiceModal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  console.log('ℹ Viewing invoice:', invoiceNumber);
}

function closeViewInvoiceModal() {
  const modal = document.getElementById('viewInvoiceModal');
  modal.style.display = 'none';
  document.body.style.overflow = '';
  currentViewInvoiceId = null;
  console.log('ℹ Invoice modal closed');
}

// Record Payment Modal Functions
let currentPaymentInvoiceId = null;
let currentPaymentBalance = 0;

function recordPayment(id) {
  console.log('ℹ Opening payment form for invoice:', id);
  currentPaymentInvoiceId = id;
  
  // Find the invoice row in the table
  const row = document.querySelector(`tr[data-invoice-id="${id}"]`);
  if (!row) {
    console.error('✗ Invoice not found:', id);
    return;
  }
  
  // Extract invoice data
  const invoiceNumber = row.querySelector('.font-mono')?.textContent || 'N/A';
  const balance = parseFloat(row.dataset.balance) || 0;
  currentPaymentBalance = balance;
  
  // Get currency symbol
  const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';
  
  // Populate modal
  document.getElementById('paymentInvoiceId').value = id;
  document.getElementById('paymentInvoiceNumber').textContent = invoiceNumber;
  document.getElementById('paymentAmount').value = balance.toFixed(2);
  document.getElementById('balanceInfo').textContent = `Balance due: ${currencySymbol}${balance.toFixed(2)}`;
  
  // Show modal
  const modal = document.getElementById('recordPaymentModal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  // Focus on amount field
  setTimeout(() => {
    document.getElementById('paymentAmount').focus();
  }, 100);
}

function closeRecordPaymentModal() {
  const modal = document.getElementById('recordPaymentModal');
  modal.style.display = 'none';
  document.body.style.overflow = '';
  
  // Reset form
  document.getElementById('recordPaymentForm').reset();
  currentPaymentInvoiceId = null;
  currentPaymentBalance = 0;
  
  console.log('ℹ Payment form closed');
}

async function submitPaymentRecord(event) {
  event.preventDefault();
  
  const form = event.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalHTML = submitBtn.innerHTML;
  
  // Disable submit button
  submitBtn.disabled = true;
  submitBtn.innerHTML = 'Recording...';
  
  try {
    const formData = new FormData(form);
    const data = {
      invoice_id: formData.get('invoice_id'),
      amount: parseFloat(formData.get('amount')),
      payment_date: formData.get('payment_date'),
      payment_method: formData.get('payment_method'),
      reference: formData.get('reference') || null,
      notes: formData.get('notes') || null
    };
    
    // Validate payment amount
    if (data.amount <= 0) {
      console.log('⚠ Payment amount must be greater than zero');
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalHTML;
      return;
    }
    
    if (data.amount > currentPaymentBalance) {
      if (!confirm(`Payment amount (${data.amount.toFixed(2)}) exceeds balance due (${currentPaymentBalance.toFixed(2)}). Continue?`)) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
        return;
      }
    }
    
    // Send to API
    const response = await fetch('api/record_payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      closeRecordPaymentModal();
      console.log(`✓ Payment of ${data.amount.toFixed(2)} recorded successfully`);
      
      // Reload page to show updated data
      setTimeout(() => window.location.reload(), 500);
    } else {
      throw new Error(result.message || 'Failed to record payment');
    }
  } catch (error) {
    console.error('✗ Payment recording error:', error);
    console.error('Error: ' + error.message);
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalHTML;
  }
  
  return false;
}

// Email Invoice Modal Functions
let currentEmailInvoiceId = null;

async function emailInvoice(id) {
  console.log('ℹ Opening email form for invoice:', id);
  
  // Check if SMTP is configured
  const smtpConfigured = <?php echo $smtpConfigured ? 'true' : 'false'; ?>;
  if (!smtpConfigured) {
    alert('SMTP server not configured. Please configure email settings in Settings > System Configuration.');
    return;
  }
  
  currentEmailInvoiceId = id;
  
  // Find the invoice row in the table
  const row = document.querySelector(`tr[data-invoice-id="${id}"]`);
  if (!row) {
    console.error('✗ Invoice not found:', id);
    return;
  }
  
  // Extract invoice data
  const invoiceNumber = row.querySelector('.font-mono')?.textContent || 'N/A';
  const customer = row.dataset.customer || 'Customer';
  
  // Populate modal
  document.getElementById('emailInvoiceId').value = id;
  document.getElementById('emailInvoiceNumber').textContent = invoiceNumber;
  document.getElementById('emailSubject').value = `Invoice ${invoiceNumber} from Your Company`;
  document.getElementById('emailMessage').value = `Dear ${customer},\n\nPlease find attached invoice ${invoiceNumber}.\n\nThank you for your business!\n\nBest regards,\nYour Company`;
  
  // Check SMTP configuration
  try {
    const response = await fetch('api/check_smtp_config.php');
    const result = await response.json();
    
    const smtpWarning = document.getElementById('smtpWarning');
    const sendBtn = document.getElementById('sendEmailBtn');
    
    if (result.configured) {
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
    // If check fails, assume not configured
    console.log('⚠ Could not verify SMTP configuration');
    document.getElementById('smtpWarning').style.display = 'block';
    const sendBtn = document.getElementById('sendEmailBtn');
    sendBtn.disabled = true;
    sendBtn.style.opacity = '0.5';
    sendBtn.style.cursor = 'not-allowed';
  }
  
  // Show modal
  const modal = document.getElementById('emailInvoiceModal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  // Focus on recipient email field
  setTimeout(() => {
    document.getElementById('recipientEmail').focus();
  }, 100);
}

function closeEmailInvoiceModal() {
  const modal = document.getElementById('emailInvoiceModal');
  modal.style.display = 'none';
  document.body.style.overflow = '';
  
  // Reset form
  document.getElementById('emailInvoiceForm').reset();
  document.getElementById('smtpWarning').style.display = 'none';
  currentEmailInvoiceId = null;
  
  console.log('ℹ Email form closed');
}

async function submitEmailInvoice(event) {
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
      invoice_id: formData.get('invoice_id'),
      recipient_email: formData.get('recipient_email'),
      cc_email: formData.get('cc_email') || null,
      subject: formData.get('subject'),
      message: formData.get('message'),
      attach_pdf: formData.get('attach_pdf') ? true : false
    };
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.recipient_email)) {
      console.log('⚠ Invalid email format');
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalHTML;
      return;
    }
    
    // Send to API
    const response = await fetch('api/send_invoice_email.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      closeEmailInvoiceModal();
      console.log(`✓ Invoice email sent successfully to ${data.recipient_email}`);
      
      // Show success notification
      if (typeof Toast !== 'undefined') {
        Toast.success('Invoice email sent successfully!');
      }
    } else {
      throw new Error(result.message || 'Failed to send email');
    }
  } catch (error) {
    console.error('✗ Email sending error:', error);
    
    // Show error notification
    if (typeof Toast !== 'undefined') {
      Toast.error('Failed to send email: ' + error.message);
    } else {
      console.error('Error: ' + error.message);
    }
    
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalHTML;
  }
  
  return false;
}

function downloadPDF(id) {
  // Use direct navigation to avoid extension/SW message-channel issues
  try {
    if (!id) {
      if (typeof Toast !== 'undefined') Toast.warning('No invoice selected');
      return;
    }
    const url = 'api/invoice_pdf.php?id=' + encodeURIComponent(id);
    console.log('ℹ Downloading PDF for invoice ' + id);
    // Prefer opening in a new tab to let the browser handle the attachment
    const a = document.createElement('a');
    a.href = url;
    a.target = '_blank';
    a.rel = 'noopener';
    // Rely on server Content-Disposition for filename
    document.body.appendChild(a);
    a.click();
    a.remove();
    if (typeof Toast !== 'undefined') Toast.info('Preparing PDF download...');
  } catch (e) {
    console.error('PDF download exception:', e);
    if (typeof Toast !== 'undefined') Toast.error('Failed to download PDF: ' + e.message);
  }
}

function exportInvoices() {
  console.log('ℹ Exporting invoices to CSV...');
}

// Filter invoices by status
function filterInvoicesByStatus(status) {
  console.log('Filtering by status:', status);
  
  const rows = document.querySelectorAll('#invoicesTable tbody tr');
  let visibleCount = 0;
  
  rows.forEach(row => {
    const rowStatus = row.dataset.status ? row.dataset.status.toLowerCase() : '';
    
    if (status === 'all' || rowStatus === status.toLowerCase()) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });
  
  console.log(`Filtered: ${visibleCount} of ${rows.length} invoices (Status: ${status})`);
  
  // Toggle table visibility based on filtered results
  toggleTableVisibility();
  
  // Log filter action
  if (status === 'all') {
    console.log(`ℹ Showing all ${visibleCount} invoices`);
  } else {
    console.log(`ℹ Showing ${visibleCount} ${status} invoice(s)`);
  }
}

// Filter by status event listener
document.getElementById('status-filter')?.addEventListener('change', function(e) {
  const status = e.target.value;
  filterInvoicesByStatus(status);
});

// ============================================
// AJAX PAGINATION
// ============================================
let currentPage = 1;
let totalPages = <?php echo $totalPages; ?>;
let perPage = 6;
let currentStatus = 'all';

function loadInvoices(page, status = 'all') {
  const tbody = document.querySelector('#invoicesTable tbody');
  const url = `api/invoices_paginated.php?page=${page}&perPage=${perPage}${status !== 'all' ? '&status=' + status : ''}`;
  
  console.log(`ℹ Loading invoices page ${page}...`);
  
  fetch(url, { credentials: 'same-origin' })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        tbody.innerHTML = data.html;
        currentPage = data.pagination.page;
        totalPages = data.pagination.totalPages;
        currentStatus = status;
        
        updatePaginationUI(data.pagination);
        console.log(`✓ Loaded ${data.pagination.perPage} invoices (page ${currentPage}/${totalPages})`);
      } else {
        throw new Error(data.message || 'Failed to load invoices');
      }
    })
    .catch(err => {
      console.error('✗ Pagination error:', err);
      if (typeof Toast !== 'undefined') {
        Toast.error('Failed to load invoices: ' + err.message);
      }
    });
}

function updatePaginationUI(pagination) {
  const start = (pagination.page - 1) * pagination.perPage + 1;
  const end = Math.min(pagination.page * pagination.perPage, pagination.total);
  
  document.getElementById('paginationStart').textContent = start;
  document.getElementById('paginationEnd').textContent = end;
  document.getElementById('paginationTotal').textContent = pagination.total;
  
  // Update prev/next buttons
  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');
  
  prevBtn.disabled = currentPage <= 1;
  nextBtn.disabled = currentPage >= totalPages;
  
  prevBtn.style.opacity = currentPage <= 1 ? '0.5' : '1';
  prevBtn.style.cursor = currentPage <= 1 ? 'not-allowed' : 'pointer';
  nextBtn.style.opacity = currentPage >= totalPages ? '0.5' : '1';
  nextBtn.style.cursor = currentPage >= totalPages ? 'not-allowed' : 'pointer';
  
  // Render page number buttons
  renderPageNumbers();
}

function renderPageNumbers() {
  const container = document.getElementById('pageNumbers');
  container.innerHTML = '';
  
  // Show max 5 page buttons
  let startPage = Math.max(1, currentPage - 2);
  let endPage = Math.min(totalPages, startPage + 4);
  
  if (endPage - startPage < 4) {
    startPage = Math.max(1, endPage - 4);
  }
  
  for (let i = startPage; i <= endPage; i++) {
    const btn = document.createElement('button');
    btn.textContent = i;
    btn.onclick = () => goToPage(i);
    btn.style.cssText = `
      min-width: 36px;
      padding: 0.5rem 0.75rem;
      background: ${i === currentPage ? 'hsl(0 0% 12%)' : 'white'};
      color: ${i === currentPage ? 'white' : 'hsl(0 0% 12%)'};
      border: 1px solid ${i === currentPage ? 'hsl(0 0% 12%)' : 'hsl(214 20% 88%)'};
      border-radius: 6px;
      font-size: 0.875rem;
      font-weight: ${i === currentPage ? '600' : '500'};
      cursor: pointer;
      transition: all 0.2s;
    `;
    
    if (i !== currentPage) {
      btn.onmouseover = function() {
        this.style.background = 'hsl(0 0% 96%)';
      };
      btn.onmouseout = function() {
        this.style.background = 'white';
      };
    }
    
    container.appendChild(btn);
  }
}

function goToPage(target) {
  let newPage = currentPage;
  
  if (target === 'prev') {
    newPage = Math.max(1, currentPage - 1);
  } else if (target === 'next') {
    newPage = Math.min(totalPages, currentPage + 1);
  } else if (typeof target === 'number') {
    newPage = target;
  }
  
  if (newPage !== currentPage && newPage >= 1 && newPage <= totalPages) {
    loadInvoices(newPage, currentStatus);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

// Override status filter to work with pagination
const originalFilterInvoicesByStatus = filterInvoicesByStatus;
filterInvoicesByStatus = function(status) {
  currentStatus = status;
  currentPage = 1;
  if (status === 'all') {
    loadInvoices(1, 'all');
  } else {
    loadInvoices(1, status);
  }
};

// Search functionality
document.getElementById('invoice-search')?.addEventListener('input', function(e) {
  const searchTerm = e.target.value.toLowerCase().trim();
  const rows = document.querySelectorAll('#invoicesTable tbody tr');
  let visibleCount = 0;
  
  rows.forEach(row => {
    const searchableText = [
      row.querySelector('.font-mono')?.textContent || '',
      row.dataset.customer || '',
      row.querySelector('.badge')?.textContent || ''
    ].join(' ').toLowerCase();
    
    if (!searchTerm || searchableText.includes(searchTerm)) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });
  
  // Toggle table visibility based on search results
  toggleTableVisibility();
  
  console.log(`Search: ${visibleCount} of ${rows.length} invoices match "${searchTerm}"`);
});

// Initialize pagination on page load
document.addEventListener('DOMContentLoaded', function() {
  renderPageNumbers();
  toggleTableVisibility(); // Check initial state
  console.log('ℹ Pagination initialized (page 1/' + totalPages + ')');
});

// ============================================
// APPLY NUMBER FORMAT API TO CURRENCY VALUES
// ============================================
window.addEventListener('load', function() {
  const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';
  
  // Auto-apply formatting to invoice values
  NumberFormat.autoApply(currencySymbol, {
    customSelectors: [
      { selector: '#totalRevenueAmount', maxWidth: 1 },  // Always abbreviate >= 1M
      { selector: '#paidAmount', maxWidth: 1 },
      { selector: '#outstandingAmount', maxWidth: 1 },
      { selector: 'td.font-semibold', maxWidth: 80 },  // Table columns
      { selector: 'td.text-success', maxWidth: 80 }
    ]
  });
});
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
