<?php
/**
 * Quotations Module
 * Create, read, manage, update, and delete quotations
 * as well as converting quotes to orders
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Model\Quotation;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Check SMTP configuration
$appConfig = require __DIR__ . '/../config/app.php';
$smtpConfigured = !empty($appConfig['mail']['host']) && !empty($appConfig['mail']['username']);

// Real quotations from database
$quotationModel = new Quotation();
try {
    $quotations = $quotationModel->getAll();
    // Convert _id ObjectId to string 'id' for easier access
    foreach ($quotations as &$quote) {
        if (isset($quote['_id'])) {
            $quote['id'] = (string)$quote['_id'];
        }
    }
    unset($quote);
    $stats = $quotationModel->getStats();
} catch (\Exception $e) {
    $quotations = [];
    $stats = [
        'total' => 0,
        'total_value' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'converted' => 0,
        'expired' => 0
    ];
}

$pageTitle = 'Sales Quotations';
ob_start();
?>

<div style="background: #7194A5; color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
  <div class="container">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 32px; backdrop-filter: blur(10px);">
        📋
      </div>
      <div style="flex: 1;">
        <h1 style="font-size: 1.875rem; font-weight: 700; margin: 0 0 0.25rem 0; color: white;">Sales Quotations</h1>
        <p style="font-size: 0.875rem; margin: 0; opacity: 0.9;">Create and manage customer quotes with professional templates</p>
      </div>
      <button onclick="showNewQuoteModal()" style="padding: 0.625rem 1.5rem; background: rgba(255,255,255,0.95); border: none; border-radius: 8px; color: #7194A5; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='white'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.95)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
        New Quote
      </button>
      <a href="dashboard.php" style="padding: 0.625rem 1.25rem; background: rgba(255,255,255,0.2); border-radius: 8px; color: white; text-decoration: none; font-weight: 500; backdrop-filter: blur(10px); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        ← Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Stats Cards - Compact Design with 6 tiles -->
<div id="statsCards" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.875rem; margin-bottom: 1.5rem;">
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Quotes</p>
      <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(222 47% 17%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 12H15M9 16H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L18.7071 8.70711C18.8946 8.89464 19 9.149 19 9.41421V19C19 20.1046 18.1046 21 17 21Z" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="totalQuotesCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;"><?php echo number_format($stats['total']); ?></p>
  </div>
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Pending Review</p>
      <div style="width: 36px; height: 36px; background: hsl(48 96% 89%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(25 95% 16%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 8V12L15 15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="pendingCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(25 95% 16%); margin: 0;"><?php echo number_format($stats['pending']); ?></p>
  </div>
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Approved</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(140 61% 13%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
    <p id="approvedCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;"><?php echo number_format($stats['approved']); ?></p>
  </div>
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Rejected</p>
      <div style="width: 36px; height: 36px; background: hsl(0 86% 97%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(0 74% 42%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </div>
    </div>
    <p id="rejectedCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(0 74% 42%); margin: 0;"><?php echo number_format($stats['rejected']); ?></p>
  </div>
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Converted</p>
      <div style="width: 36px; height: 36px; background: hsl(262 83% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: hsl(263 70% 26%);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 11L12 14L22 4M21 12V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
    </div>
    <p id="convertedCount" style="font-size: 1.75rem; font-weight: 700; color: hsl(263 70% 26%); margin: 0;"><?php echo number_format($stats['converted']); ?></p>
  </div>
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%); transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)'">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Value</p>
      <div style="width: 36px; height: 36px; background: rgba(113,148,165,0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #7194A5;">
        <?php
        // Display currency icon based on settings
        $currentCurrency = $_SESSION['currency'] ?? 'PHP';
        $currencyIcons = [
          'PHP' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2V22M7 7H14C15.0609 7 16.0783 7.42143 16.8284 8.17157C17.5786 8.92172 18 9.93913 18 11C18 12.0609 17.5786 13.0783 16.8284 13.8284C16.0783 14.5786 15.0609 15 14 15H7"/><path d="M7 11H17" stroke-width="2"/></svg>',
          'USD' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2V22M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6"/></svg>',
          'EUR' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18.5 5C17 5 15.5 6 14.5 7.5C13.5 9 13 11 13 13C13 15 13.5 17 14.5 18.5C15.5 20 17 21 18.5 21M7 10H15M7 14H15"/></svg>',
          'GBP' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 19H6L9 13M9 13C9 11 8.5 9 10 7C11 5.5 13 5 14 5C15.5 5 17 6 17 7.5M9 13H15"/></svg>',
          'JPY' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 4L12 12M18 4L12 12M12 12V22M8 14H16M8 17H16"/></svg>',
          'CNY' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 4L12 12M18 4L12 12M12 12V22M8 14H16M8 17H16"/></svg>'
        ];
        echo $currencyIcons[$currentCurrency] ?? $currencyIcons['USD'];
        ?>
      </div>
    </div>
    <p id="totalValue" style="font-size: 1.75rem; font-weight: 700; color: #7194A5; margin: 0;"><?php echo CurrencyHelper::format($stats['total_value']); ?></p>
  </div>
</div>

<!-- Toolbar -->
<div class="toolbar">
  <div class="toolbar-left">
    <div class="search-wrapper">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input type="search" class="search-input" placeholder="Search quotations..." id="quote-search">
    </div>
  </div>
  <div class="toolbar-right">
    <select class="form-select" id="status-filter" style="width: auto;">
      <option value="all">All Status</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
      <option value="converted">Converted</option>
    </select>
    <button class="btn btn-ghost btn-icon" onclick="printQuotes()" title="Print Quotations">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M6 9V2H18V9M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18M6 14H18V22H6V14Z" stroke="currentColor" stroke-width="2"/>
      </svg>
    </button>
    <button class="btn btn-ghost btn-icon" onclick="exportQuotes()" title="Export to CSV/Excel">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
    <button class="btn btn-ghost btn-icon" onclick="bulkActionsMenu()" title="Bulk Actions">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15M9 5C9 6.10457 9.89543 7 11 7H13C14.1046 7 15 6.10457 15 5M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5M12 12H15M12 16H15M9 12H9.01M9 16H9.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
</div>

<!-- Print-Only Summary Statistics -->
<div class="print-summary" style="display: none;">
  <h2>Quotations Summary Report</h2>
  <div class="print-stats">
    <div class="print-stat-item">
      <p class="print-stat-label">Total Quotes</p>
      <p class="print-stat-value" id="printTotalQuotes">0</p>
    </div>
    <div class="print-stat-item">
      <p class="print-stat-label">Pending Review</p>
      <p class="print-stat-value" id="printPendingCount">0</p>
    </div>
    <div class="print-stat-item">
      <p class="print-stat-label">Approved Total</p>
      <p class="print-stat-value" id="printApprovedCount">0</p>
    </div>
    <div class="print-stat-item">
      <p class="print-stat-label">Rejected Loss</p>
      <p class="print-stat-value" id="printRejectedCount">0</p>
    </div>
    <div class="print-stat-item">
      <p class="print-stat-label">Overall Total Value</p>
      <p class="print-stat-value" id="printTotalValue">₱0.00</p>
    </div>
  </div>
</div>

<!-- Print-Only Receipt Summary (Above Table) -->
<div class="print-receipt-top" style="display: none;">
  <div style="margin: 25px 0;">
    <div style="max-width: 450px; margin: 0 auto; background: white; border: 2px solid #333; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
      <h3 style="text-align: center; margin: 0 0 18px 0; font-size: 13pt; font-weight: 700; color: #333; border-bottom: 2px solid #333; padding-bottom: 10px;">FINANCIAL SUMMARY</h3>
      
      <!-- Counts Section -->
      <div style="margin-bottom: 15px;">
        <div style="display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed #ddd;">
          <span style="font-size: 10pt; color: #666;">Total Quotes:</span>
          <span style="font-size: 10pt; font-weight: 600; color: #333;" id="receiptTopTotalQuotes">0</span>
        </div>
        
        <div style="display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed #ddd;">
          <span style="font-size: 10pt; color: #666;">Pending Review:</span>
          <span style="font-size: 10pt; font-weight: 600; color: #856404;" id="receiptTopPendingCount">0</span>
        </div>
        
        <div style="display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed #ddd;">
          <span style="font-size: 10pt; color: #666;">Approved Quotes:</span>
          <span style="font-size: 10pt; font-weight: 600; color: #155724;" id="receiptTopApprovedCount">0</span>
        </div>
        
        <div style="display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 2px solid #333;">
          <span style="font-size: 10pt; color: #666;">Rejected Quotes:</span>
          <span style="font-size: 10pt; font-weight: 600; color: #721c24;" id="receiptTopRejectedCount">0</span>
        </div>
      </div>
      
      <!-- Financial Values Section -->
      <div>
        <div style="display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #333; background: #f9f9f9; margin: 0 -20px; padding-left: 20px; padding-right: 20px;">
          <span style="font-size: 11pt; font-weight: 600; color: #333;">Gross Total Value:</span>
          <span style="font-size: 11pt; font-weight: 700; color: #333;" id="receiptTopGrossTotal">₱0.00</span>
        </div>
        
        <div style="display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed #ddd;">
          <span style="font-size: 10pt; color: #155724;">✓ Approved Value:</span>
          <span style="font-size: 10pt; font-weight: 600; color: #155724;" id="receiptTopApprovedValue">₱0.00</span>
        </div>
        
        <div style="display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed #ddd;">
          <span style="font-size: 10pt; color: #721c24;">✗ Rejected Loss:</span>
          <span style="font-size: 10pt; font-weight: 600; color: #721c24;" id="receiptTopRejectedLoss">- ₱0.00</span>
        </div>
        
        <div style="display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 2px solid #333;">
          <span style="font-size: 10pt; color: #856404;">⏳ Pending Value:</span>
          <span style="font-size: 10pt; font-weight: 600; color: #856404;" id="receiptTopPendingValue">₱0.00</span>
        </div>
        
        <!-- Net Realizable Value -->
        <div style="display: flex; justify-content: space-between; padding: 12px 0; background: linear-gradient(135deg, #f0f8f4 0%, #e8f5e9 100%); margin: 10px -20px -20px -20px; padding: 15px 20px; border-radius: 0 0 6px 6px;">
          <span style="font-size: 12pt; font-weight: 700; color: #155724;">💰 Net Realizable Value:</span>
          <span style="font-size: 13pt; font-weight: 700; color: #155724;" id="receiptTopNetValue">₱0.00</span>
        </div>
      </div>
    </div>
    
    <p style="text-align: center; margin-top: 12px; font-size: 8pt; color: #666; font-style: italic;">
      Net Realizable = Approved Value (excludes Pending & Rejected)
    </p>
  </div>
</div>

<!-- Quotations Table -->
<div id="quotationsTableContainer" class="table-container" style="display: <?php echo empty($quotations) ? 'none' : 'block'; ?>;">
  <table class="data-table">
    <thead>
      <tr>
        <th class="checkbox-column" style="display: none;">
          <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor: pointer;">
        </th>
        <th class="<?php echo !empty($quotations) ? 'sortable-header' : ''; ?>" <?php echo !empty($quotations) ? 'onclick="sortTable(\"quote_number\")" style="cursor: pointer; user-select: none;"' : ''; ?>>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Quote #</span>
            <?php if (!empty($quotations)): ?>
            <svg id="sort-quote_number-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php endif; ?>
          </div>
        </th>
        <th class="<?php echo !empty($quotations) ? 'sortable-header' : ''; ?>" <?php echo !empty($quotations) ? 'onclick="sortTable(\"customer\")" style="cursor: pointer; user-select: none;"' : ''; ?>>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Customer</span>
            <?php if (!empty($quotations)): ?>
            <svg id="sort-customer-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php endif; ?>
          </div>
        </th>
        <th class="<?php echo !empty($quotations) ? 'sortable-header' : ''; ?>" <?php echo !empty($quotations) ? 'onclick="sortTable(\"date\")" style="cursor: pointer; user-select: none;"' : ''; ?>>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Date</span>
            <?php if (!empty($quotations)): ?>
            <svg id="sort-date-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php endif; ?>
          </div>
        </th>
        <th class="<?php echo !empty($quotations) ? 'sortable-header' : ''; ?>" <?php echo !empty($quotations) ? 'onclick="sortTable(\"amount\")" style="cursor: pointer; user-select: none;"' : ''; ?>>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Total Amount</span>
            <?php if (!empty($quotations)): ?>
            <svg id="sort-amount-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php endif; ?>
          </div>
        </th>
        <th class="<?php echo !empty($quotations) ? 'sortable-header' : ''; ?>" <?php echo !empty($quotations) ? 'onclick="sortTable(\"status\")" style="cursor: pointer; user-select: none;"' : ''; ?>>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>Status</span>
            <?php if (!empty($quotations)): ?>
            <svg id="sort-status-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.4;">
              <path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php endif; ?>
          </div>
        </th>
        <th style="width: 180px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($quotations as $quote): ?>
      <tr data-quote-id="<?php echo $quote['id']; ?>" data-status="<?php echo $quote['status']; ?>">
        <td class="checkbox-column" style="display: none;">
          <input type="checkbox" class="quote-checkbox" value="<?php echo $quote['id']; ?>" onchange="updateBulkActions()" style="cursor: pointer;">
        </td>
        <td class="font-mono font-medium"><?php echo htmlspecialchars($quote['quote_number'] ?? 'N/A'); ?></td>
        <td class="font-medium"><?php echo htmlspecialchars($quote['customer']); ?></td>
        <td><?php echo date('M d, Y', strtotime($quote['date'])); ?></td>
        <td class="font-semibold"><?php echo CurrencyHelper::format($quote['total']); ?></td>
        <td>
          <?php
          $statusBadges = [
            'pending' => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            'converted' => 'badge-default'
          ];
          $badgeClass = $statusBadges[$quote['status']] ?? 'badge-default';
          ?>
          <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($quote['status']); ?></span>
        </td>
        <td>
          <div class="flex gap-1">
            <button class="btn btn-ghost btn-sm" onclick="viewQuote('<?php echo $quote['id']; ?>')" title="View Details">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <?php if ($quote['status'] === 'pending'): ?>
            <button class="btn btn-ghost btn-sm text-success" onclick="approveQuote('<?php echo $quote['id']; ?>')" title="Approve">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>
            <?php endif; ?>
            <?php if ($quote['status'] === 'approved'): ?>
            <button class="btn btn-ghost btn-sm text-primary" onclick="convertToOrder('<?php echo $quote['id']; ?>')" title="Convert to Order">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm" onclick="showQuoteActions('<?php echo $quote['id']; ?>', '<?php echo $quote['status']; ?>')" title="More Actions">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="5" r="1" fill="currentColor"/>
                <circle cx="12" cy="12" r="1" fill="currentColor"/>
                <circle cx="12" cy="19" r="1" fill="currentColor"/>
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
<div id="emptyStateContainer" style="display: <?php echo empty($quotations) ? 'block' : 'none'; ?>; padding: 4rem 2rem; text-align: center; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
  <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#6b7280" style="opacity: 0.15; margin: 0 auto 1.5rem; stroke-width: 1.5;">
    <path d="M9 12H15M9 16H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L18.7071 8.70711C18.8946 8.89464 19 9.149 19 9.41421V19C19 20.1046 18.1046 21 17 21Z" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <h3 style="font-size: 1.25rem; font-weight: 600; color: #111827; margin: 0 0 0.75rem 0;">No quotations yet</h3>
  <p style="font-size: 0.9375rem; color: #6b7280; margin: 0 auto 1.5rem; max-width: 28rem; line-height: 1.6;">
    Get started by creating your first quote. Click the "New Quote" button above to begin.
  </p>
  <button onclick="showNewQuoteModal()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
    Create Your First Quote
  </button>
</div>

<!-- Pagination -->
<div id="paginationControls" style="display: <?php echo (count($quotations) === 0 || count($quotations) <= 6) ? 'none' : 'flex'; ?>; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding: 1rem; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
  <div class="text-sm text-secondary">
    Showing <span id="showingStart">1</span> to <span id="showingEnd">6</span> of <span id="totalCount">0</span> quotations
  </div>
  <div style="display: flex; gap: 0.5rem;">
    <button id="prevPage" onclick="changePage(-1)" class="btn btn-ghost btn-sm" style="padding: 0.5rem 1rem;" disabled>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M15 18l-6-6 6-6"/>
      </svg>
      Previous
    </button>
    <div id="pageNumbers" style="display: flex; gap: 0.25rem;"></div>
    <button id="nextPage" onclick="changePage(1)" class="btn btn-ghost btn-sm" style="padding: 0.5rem 1rem;">
      Next
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 18l6-6-6-6"/>
      </svg>
    </button>
  </div>
  <div style="display: flex; align-items: center; gap: 0.5rem;">
    <label class="text-sm text-secondary">Items per page:</label>
    <select id="itemsPerPage" onchange="changeItemsPerPage()" style="padding: 0.375rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; cursor: pointer;">
      <option value="6" selected>6</option>
      <option value="10">10</option>
      <option value="25">25</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
  </div>
</div>

<!-- View Quotation Modal -->
<div id="viewQuoteModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 2rem;">
  <div style="width: 100%; max-width: 900px; max-height: 90vh; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); display: flex; flex-direction: column; overflow: hidden;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, hsl(210 20% 98%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(214 20% 88%); display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h3 style="margin: 0 0 0.25rem 0; font-size: 1.25rem; font-weight: 700; color: hsl(222 47% 17%);">Quotation Details</h3>
        <p id="viewQuoteNumber" style="margin: 0; font-size: 0.875rem; color: hsl(215 16% 47%); font-family: monospace;"></p>
      </div>
      <button type="button" onclick="closeViewModal()" style="background: hsl(240 5% 96%); border: 1px solid hsl(214 20% 88%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(215 16% 47%); transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 92%)'" onmouseout="this.style.background='hsl(240 5% 96%)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    
    <!-- Content -->
    <div style="padding: 2rem; overflow-y: auto; flex: 1;">
      <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Customer</label>
          <p id="viewCustomer" style="margin: 0; font-size: 1rem; font-weight: 600; color: hsl(222 47% 17%);"></p>
        </div>
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Status</label>
          <span id="viewStatus" class="badge"></span>
        </div>
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Date</label>
          <p id="viewDate" style="margin: 0; font-size: 1rem; color: hsl(222 47% 17%);"></p>
        </div>
        <div>
          <label style="display: block; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Total Amount</label>
          <p id="viewTotal" style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #7194A5;"></p>
        </div>
      </div>
      
      <div style="margin-top: 2rem; padding: 1rem; background: hsl(210 20% 98%); border-radius: 8px; border: 1px solid hsl(214 20% 90%);">
        <p style="margin: 0; font-size: 0.875rem; color: hsl(215 16% 47%); text-align: center;">
          📋 Detailed quotation information and line items will be loaded here
        </p>
      </div>
    </div>
    
    <!-- Footer Actions -->
    <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
      <button type="button" onclick="downloadQuotePDF()" class="btn btn-ghost">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Download PDF
      </button>
      <button type="button" onclick="closeViewModal()" class="btn btn-primary">Close</button>
    </div>
  </div>
</div>

<!-- Actions Dropdown Menu -->
<div id="actionsDropdown" style="display: none; position: absolute; background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); z-index: 10000; min-width: 200px;">
  <div style="padding: 0.5rem;">
    <button onclick="handleQuoteAction('email')" <?php if (!$smtpConfigured): ?>disabled<?php endif; ?> style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>; font-size: 0.875rem; color: <?php echo $smtpConfigured ? 'hsl(222 47% 17%)' : 'hsl(215 16% 60%)'; ?>; text-align: left; transition: background 0.2s; opacity: <?php echo $smtpConfigured ? '1' : '0.5'; ?>;" <?php if ($smtpConfigured): ?>onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'"<?php endif; ?>>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Email Quote<?php if (!$smtpConfigured): ?> <span style="font-size: 0.75rem;">(SMTP not configured)</span><?php endif; ?>
    </button>
    <button onclick="handleQuoteAction('download')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
      Download PDF
    </button>
    <button onclick="handleQuoteAction('duplicate')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
      Duplicate
    </button>
    <div style="height: 1px; background: hsl(214 20% 88%); margin: 0.5rem 0;"></div>
    <button id="actionVoid" onclick="handleQuoteAction('void')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(25 95% 45%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(48 96% 95%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
      Void Quote
    </button>
    <button onclick="handleQuoteAction('delete')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(0 74% 50%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(0 86% 97%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      Delete
    </button>
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
    <button onclick="handleBulkAction('approve')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(140 61% 35%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(143 85% 96%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>
      Approve Selected
    </button>
    <button onclick="handleBulkAction('void')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(25 95% 45%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(48 96% 95%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
      Void Selected
    </button>
    <button onclick="handleBulkAction('email')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Email Selected
    </button>
    <button onclick="handleBulkAction('export')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
      Export Selected
    </button>
    <div style="height: 1px; background: hsl(214 20% 88%); margin: 0.5rem 0;"></div>
    <button onclick="handleBulkAction('delete')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(0 74% 50%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(0 86% 97%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      Delete Selected
    </button>
  </div>
</div>

<!-- Additional Features Section -->
<div id="advancedCapabilities" style="background: linear-gradient(135deg, hsl(210 20% 98%) 0%, white 100%); border: 1px solid hsl(214 20% 90%); border-radius: 12px; padding: 1.5rem; margin: 2rem 0 1.5rem 0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
  <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem;">
    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #7194A5 0%, hsl(215 25% 65%) 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(113,148,165,0.25);">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
      </svg>
    </div>
    <div>
      <h3 style="margin: 0 0 0.25rem 0; font-size: 1.125rem; font-weight: 700; color: hsl(222 47% 17%);">Advanced Capabilities</h3>
      <p style="margin: 0; font-size: 0.8125rem; color: hsl(215 16% 47%);">Powerful features supported by leading business platforms</p>
    </div>
  </div>
  
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
    <!-- Xero: Multi-Currency Support -->
    <div style="background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; padding: 1rem; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.borderColor='#7194A5'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'" onclick="Toast.info('Multi-Currency: Support for 150+ currencies with live exchange rates')">
      <div style="display: flex; align-items: start; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 6v6l4 2"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <h4 style="margin: 0 0 0.375rem 0; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Multi-Currency</h4>
          <p style="margin: 0; font-size: 0.75rem; color: hsl(215 16% 47%); line-height: 1.5;">Handle quotes in 150+ currencies with real-time exchange rates</p>
        </div>
      </div>
    </div>
    
    <!-- QuickBooks: Automated Tax Calculations -->
    <div style="background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; padding: 1rem; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.borderColor='#7194A5'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'" onclick="Toast.info('Tax Engine: Automatic tax calculation for 200+ jurisdictions')">
      <div style="display: flex; align-items: start; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2">
            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <h4 style="margin: 0 0 0.375rem 0; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Smart Tax Engine</h4>
          <p style="margin: 0; font-size: 0.75rem; color: hsl(215 16% 47%); line-height: 1.5;">Automated tax calculations for 200+ jurisdictions worldwide</p>
        </div>
      </div>
    </div>
    
    <!-- LedgerSMB: Advanced Workflows -->
    <div style="background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; padding: 1rem; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.borderColor='#7194A5'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'" onclick="Toast.info('Approval Workflows: Multi-level approval with custom rules')">
      <div style="display: flex; align-items: start; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; background: hsl(48 96% 89%); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <h4 style="margin: 0 0 0.375rem 0; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Approval Workflows</h4>
          <p style="margin: 0; font-size: 0.75rem; color: hsl(215 16% 47%); line-height: 1.5;">Multi-level approvals with custom business rules & routing</p>
        </div>
      </div>
    </div>
    
    <!-- Xero: Invoice Generation -->
    <div style="background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; padding: 1rem; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.borderColor='#7194A5'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'" onclick="Toast.success('Smart Templates: Generate professional invoices instantly')">
      <div style="display: flex; align-items: start; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; background: rgba(113,148,165,0.12); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2">
            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            <path d="M14 2v5a1 1 0 001 1h5"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <h4 style="margin: 0 0 0.375rem 0; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Smart Templates</h4>
          <p style="margin: 0; font-size: 0.75rem; color: hsl(215 16% 47%); line-height: 1.5;">Professional PDF generation with custom branding</p>
        </div>
      </div>
    </div>
    
    <!-- QuickBooks: Payment Integration -->
    <div style="background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; padding: 1rem; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.borderColor='#7194A5'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'" onclick="Toast.info('Payment Gateway: Accept payments via credit card, PayPal, eWallets')">
      <div style="display: flex; align-items: start; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2">
            <rect x="2" y="5" width="20" height="14" rx="2"/>
            <line x1="2" y1="10" x2="22" y2="10"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <h4 style="margin: 0 0 0.375rem 0; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Payment Gateway</h4>
          <p style="margin: 0; font-size: 0.75rem; color: hsl(215 16% 47%); line-height: 1.5;">Accept payments via cards, PayPal, GCash, PayMaya & more</p>
        </div>
      </div>
    </div>
    
    <!-- LedgerSMB: Analytics Dashboard -->
    <div style="background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; padding: 1rem; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.borderColor='#7194A5'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='translateY(0)'; this.style.boxShadow='none'" onclick="Toast.success('Analytics: Real-time insights & forecasting powered by AI')">
      <div style="display: flex; align-items: start; gap: 0.75rem;">
        <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2">
            <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <h4 style="margin: 0 0 0.375rem 0; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">AI Analytics</h4>
          <p style="margin: 0; font-size: 0.75rem; color: hsl(215 16% 47%); line-height: 1.5;">Real-time insights, forecasting & predictive analysis</p>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="newQuoteModal" onclick="attemptCloseQuoteModal()" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; align-items: center; justify-content: center; padding: 2rem; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);">
  <div onclick="event.stopPropagation()" style="width: 100%; max-width: 1200px; max-height: 92vh; background: white; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35), 0 10px 25px -5px rgba(0,0,0,0.15); animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; overflow: hidden; position: relative; pointer-events: auto;">
    
    <!-- Enhanced Header -->
    <div style="background: linear-gradient(135deg, hsl(0 0% 100%) 0%, hsl(210 20% 98%) 100%); padding: 2rem 2.5rem; display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; border-bottom: 1px solid hsl(214 20% 88%);">
      <!-- Decorative Background Pattern -->
      <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.03; background-image: radial-gradient(circle at 2px 2px, hsl(215 16% 47%) 1px, transparent 1px); background-size: 24px 24px; pointer-events: none;"></div>
      
      <div style="position: relative; z-index: 1;">
        <div style="display: inline-flex; align-items: center; gap: 0.625rem; background: linear-gradient(135deg, hsl(215 20% 55%) 0%, hsl(215 25% 65%) 100%); padding: 0.5rem 1rem; border-radius: 24px; font-size: 0.75rem; font-weight: 700; color: white; margin-bottom: 0.75rem; border: 1px solid hsl(215 20% 50%); box-shadow: 0 2px 8px rgba(113,148,165,0.25), 0 1px 2px rgba(0,0,0,0.05); letter-spacing: 0.05em;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <path d="M14 2v6h6"/>
            <path d="M12 18v-6"/>
            <path d="M9 15h6"/>
          </svg>
          NEW QUOTATION
        </div>
        <h2 style="font-size: 1.875rem; font-weight: 800; margin: 0; letter-spacing: -0.03em; color: hsl(215 25% 35%); text-shadow: 0 1px 2px rgba(0,0,0,0.05);">
          Create New Professional Quote
        </h2>
      </div>
      <button type="button" onclick="attemptCloseQuoteModal()" style="background: hsl(240 5% 96%); border: 1px solid hsl(214 20% 88%); border-radius: 10px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(215 16% 47%); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; z-index: 1;" onmouseover="this.style.background='hsl(240 5% 92%)'; this.style.borderColor='hsl(215 20% 75%)'; this.style.transform='rotate(90deg) scale(1.05)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.background='hsl(240 5% 96%)'; this.style.borderColor='hsl(214 20% 88%)'; this.style.transform='rotate(0) scale(1)'; this.style.boxShadow='none'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    <form id="quoteForm" onsubmit="return submitQuote(event)" novalidate style="flex: 1; overflow: hidden; display: flex; flex-direction: column;">
      <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.75rem; padding: 1.75rem; flex: 1; overflow: hidden;">
        
        <!-- LEFT COLUMN: Tabbed Content -->
        <div style="display: flex; flex-direction: column; min-height: 0;">
          <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; height: 100%;">
            
            <!-- Tab Navigation -->
            <div style="display: flex; background: hsl(240 5% 96%); border-bottom: 2px solid hsl(240 6% 90%); padding: 0; overflow-x: auto; flex-shrink: 0;">
              <button type="button" class="quote-tab-btn active" onclick="switchQuoteTab('details')" data-tab="details" style="padding: 0.75rem 1.125rem; border: none; background: white; border-bottom: 3px solid #7194A5; font-weight: 600; cursor: pointer; white-space: nowrap; color: #7194A5; font-size: 0.8125rem;">
                📋 Quote Details
              </button>
              <button type="button" class="quote-tab-btn" onclick="switchQuoteTab('items')" data-tab="items" style="padding: 0.75rem 1.125rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem;">
                📦 Line Items
              </button>
              <button type="button" class="quote-tab-btn" onclick="switchQuoteTab('customer')" data-tab="customer" style="padding: 0.75rem 1.125rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem;">
                👤 Customer
              </button>
              <button type="button" class="quote-tab-btn" onclick="switchQuoteTab('transaction')" data-tab="transaction" style="padding: 0.75rem 1.125rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem;">
                💳 Transaction
              </button>
              <button type="button" class="quote-tab-btn" onclick="switchQuoteTab('terms')" data-tab="terms" style="padding: 0.75rem 1.125rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem;">
                📝 Terms
              </button>
            </div>

            <!-- Tab Content: Quote Details -->
            <div class="quote-tab-content active" id="quote-tab-details" style="padding: 1.5rem; overflow-y: auto; flex: 1;">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Quote Number <span style="color: hsl(0 74% 24%);">*</span>
                  </label>
                  <input type="text" name="quote_number" class="form-input" value="QT-<?php echo date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Date <span style="color: hsl(0 74% 24%);">*</span>
                  </label>
                  <input type="date" name="date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Valid For <span style="color: hsl(0 74% 24%);">*</span>
                  </label>
                  <select name="validity_days" class="form-select" required>
                    <option value="7">7 Days</option>
                    <option value="14">14 Days</option>
                    <option value="30" selected>30 Days</option>
                    <option value="60">60 Days</option>
                    <option value="90">90 Days</option>
                  </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Payment Terms
                  </label>
                  <select name="payment_terms" class="form-select">
                    <option value="Due on Receipt">Due on Receipt</option>
                    <option value="Net 7">Net 7 Days</option>
                    <option value="Net 15">Net 15 Days</option>
                    <option value="Net 30" selected>Net 30 Days</option>
                    <option value="Net 45">Net 45 Days</option>
                    <option value="Net 60">Net 60 Days</option>
                    <option value="Net 90">Net 90 Days</option>
                    <option value="2/10 Net 30">2/10 Net 30 (2% discount if paid within 10 days)</option>
                    <option value="COD">Cash on Delivery</option>
                  </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Reference / PO Number
                  </label>
                  <input type="text" name="reference" class="form-input" placeholder="Customer PO or reference">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Sales Rep / Department
                  </label>
                  <input type="text" name="department" class="form-input" placeholder="Sales department" value="<?php echo $user['name'] ?? ''; ?>">
                </div>
              </div>
              
              <!-- Discount & Shipping -->
              <div style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid hsl(214 20% 90%);">
                <h4 style="font-size: 0.8125rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0 0 1rem 0; text-transform: uppercase; letter-spacing: 0.05em;">Additional Options</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                  <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                      Discount (%)
                    </label>
                    <input type="number" name="discount_percent" class="form-input" placeholder="0" min="0" max="100" step="0.01" value="0">
                  </div>
                  <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                      Shipping Cost
                    </label>
                    <input type="number" name="shipping_cost" class="form-input" placeholder="0.00" min="0" step="0.01" value="0">
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab Content: Line Items -->
            <div class="quote-tab-content" id="quote-tab-items" style="padding: 1.5rem; display: none; overflow-y: auto; flex: 1;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Products & Services</h3>
                <button type="button" onclick="addLineItem()" style="background: linear-gradient(135deg, #7194A5, #5a7a8a); color: white; border: none; padding: 0.625rem 1rem; border-radius: 7px; font-weight: 600; font-size: 0.8125rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(113,148,165,0.25);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(113,148,165,0.35)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(113,148,165,0.25)'">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5V19M5 12H19"/></svg>
                  Add Item
                </button>
              </div>
              <div id="lineItemsContainer"></div>
            </div>

            <!-- Tab Content: Customer -->
            <div class="quote-tab-content" id="quote-tab-customer" style="padding: 1.5rem; display: none; overflow-y: auto; flex: 1;">
              <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Customer Name <span style="color: hsl(0 74% 24%);">*</span>
                  </label>
                  <input type="text" name="customer" class="form-input" placeholder="Enter customer name" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Email
                  </label>
                  <input type="email" name="customer_email" class="form-input" placeholder="email@example.com">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Phone
                  </label>
                  <input type="tel" name="customer_phone" class="form-input" placeholder="+1 234 567 8900">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                    Company
                  </label>
                  <input type="text" name="customer_company" class="form-input" placeholder="Company name">
                </div>
              </div>
            </div>

            <!-- Tab Content: Transaction -->
            <div class="quote-tab-content" id="quote-tab-transaction" style="padding: 1.5rem; display: none; overflow-y: auto; flex: 1;">
              
              <!-- Payment Methods (QuickBooks Feature) -->
              <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.875rem; font-weight: 700; font-size: 0.9375rem; color: hsl(222 47% 17%);">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                  </svg>
                  Accepted Payment Methods
                </label>
                <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0 0 1rem 0;">Select all payment methods you accept for this quotation</p>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">
                  <label style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                    <input type="checkbox" name="payment_methods[]" value="bank_transfer" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                    <span style="font-size: 0.875rem; font-weight: 600;">🏦 Bank Transfer</span>
                  </label>
                  <label style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                    <input type="checkbox" name="payment_methods[]" value="credit_card" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                    <span style="font-size: 0.875rem; font-weight: 600;">💳 Credit Card</span>
                  </label>
                  <label style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                    <input type="checkbox" name="payment_methods[]" value="check" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                    <span style="font-size: 0.875rem; font-weight: 600;">📝 Check</span>
                  </label>
                  <label style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                    <input type="checkbox" name="payment_methods[]" value="cash" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                    <span style="font-size: 0.875rem; font-weight: 600;">💵 Cash</span>
                  </label>
                  <label style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                    <input type="checkbox" name="payment_methods[]" value="paypal" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                    <span style="font-size: 0.875rem; font-weight: 600;">🅿️ PayPal</span>
                  </label>
                  <label style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                    <input type="checkbox" name="payment_methods[]" value="wire" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                    <span style="font-size: 0.875rem; font-weight: 600;">🌐 Wire Transfer</span>
                  </label>
                  <label style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                    <input type="checkbox" name="payment_methods[]" value="gcash" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                    <span style="font-size: 0.875rem; font-weight: 600;">📱 GCash</span>
                  </label>
                  <label style="display: flex; align-items: center; gap: 0.625rem; padding: 0.875rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#7194A5'; this.style.background='hsl(210 20% 98%)'; this.style.transform='translateX(4px)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'; this.style.background='white'; this.style.transform='translateX(0)'">
                    <input type="checkbox" name="payment_methods[]" value="paymaya" style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5;">
                    <span style="font-size: 0.875rem; font-weight: 600;">💚 PayMaya</span>
                  </label>
                </div>
              </div>

              <!-- Bank Details (Xero Feature) -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.875rem; font-weight: 700; font-size: 0.9375rem; color: hsl(222 47% 17%);">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                  </svg>
                  Bank Account Details <span style="font-size: 0.75rem; font-weight: 500; color: hsl(215 16% 47%); margin-left: 0.5rem;">(Optional)</span>
                </label>
                <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0 0 1rem 0;">Provide banking information for wire transfers and direct deposits</p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.875rem;">
                  <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">Bank Name</label>
                    <input type="text" name="bank_name" class="form-input" placeholder="e.g., Wells Fargo" style="font-size: 0.875rem;">
                  </div>
                  <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">Account Number</label>
                    <input type="text" name="bank_account" class="form-input" placeholder="XXXX-XXXX-XXXX" style="font-size: 0.875rem;">
                  </div>
                  <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">Routing Number</label>
                    <input type="text" name="bank_routing" class="form-input" placeholder="123456789" style="font-size: 0.875rem;">
                  </div>
                  <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%); display: block; margin-bottom: 0.5rem;">SWIFT/BIC Code</label>
                    <input type="text" name="bank_swift" class="form-input" placeholder="ABCDUS33" style="font-size: 0.875rem;">
                  </div>
                </div>
              </div>

            </div>

            <!-- Tab Content: Terms -->
            <div class="quote-tab-content" id="quote-tab-terms" style="padding: 1.5rem; display: none; overflow-y: auto; flex: 1;">
              
              <!-- Terms & Conditions -->
              <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 700; font-size: 0.875rem; color: hsl(222 47% 17%);">
                  Terms & Conditions
                </label>
                <textarea id="termsTextarea" name="notes" class="form-input auto-expand-textarea" rows="4" placeholder="Enter payment terms, delivery notes, warranties, refund policy, special conditions..." style="font-size: 0.875rem; resize: none; overflow: hidden; min-height: 100px; line-height: 1.6; transition: all 0.2s;" oninput="autoExpandTextarea(this)" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'"></textarea>
                <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                  <button type="button" onclick="insertTemplate('standard')" style="background: hsl(240 5% 98%); color: hsl(222 47% 17%); border: 1px solid hsl(214 20% 88%); padding: 0.375rem 0.625rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#7194A5'; this.style.color='white'" onmouseout="this.style.background='hsl(240 5% 98%)'; this.style.color='hsl(222 47% 17%)'">📄 Standard Terms</button>
                  <button type="button" onclick="insertTemplate('warranty')" style="background: hsl(240 5% 98%); color: hsl(222 47% 17%); border: 1px solid hsl(214 20% 88%); padding: 0.375rem 0.625rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#7194A5'; this.style.color='white'" onmouseout="this.style.background='hsl(240 5% 98%)'; this.style.color='hsl(222 47% 17%)'">🛡️ Warranty</button>
                  <button type="button" onclick="insertTemplate('refund')" style="background: hsl(240 5% 98%); color: hsl(222 47% 17%); border: 1px solid hsl(214 20% 88%); padding: 0.375rem 0.625rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#7194A5'; this.style.color='white'" onmouseout="this.style.background='hsl(240 5% 98%)'; this.style.color='hsl(222 47% 17%)'">💰 Refund Policy</button>
                </div>
              </div>

              <!-- Late Fees (QuickBooks Feature) -->
              <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.625rem; font-weight: 700; font-size: 0.875rem; color: hsl(222 47% 17%);">
                  <input type="checkbox" id="enableLateFees" name="enable_late_fees" style="width: 16px; height: 16px; cursor: pointer;">
                  Late Payment Fee
                </label>
                <div id="lateFeeFields" style="display: none; padding: 0.875rem; background: hsl(48 96% 95%); border: 1.5px solid hsl(48 96% 80%); border-radius: 7px;">
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div>
                      <label style="font-size: 0.75rem; font-weight: 600; color: hsl(25 95% 16%); display: block; margin-bottom: 0.375rem;">Fee Type</label>
                      <select name="late_fee_type" class="form-select" style="font-size: 0.875rem;">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount</option>
                      </select>
                    </div>
                    <div>
                      <label style="font-size: 0.75rem; font-weight: 600; color: hsl(25 95% 16%); display: block; margin-bottom: 0.375rem;">Amount</label>
                      <input type="number" name="late_fee_amount" class="form-input" placeholder="5.00" step="0.01" min="0" style="font-size: 0.875rem;">
                    </div>
                  </div>
                  <div style="margin-top: 0.625rem;">
                    <label style="font-size: 0.75rem; font-weight: 600; color: hsl(25 95% 16%); display: block; margin-bottom: 0.375rem;">Applied After (Days)</label>
                    <input type="number" name="late_fee_days" class="form-input" placeholder="30" min="1" value="30" style="font-size: 0.875rem;">
                  </div>
                </div>
              </div>

              <!-- Footer Text (LedgerSMB Feature) -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 700; font-size: 0.875rem; color: hsl(222 47% 17%);">
                  Quote Footer / Additional Notes
                </label>
                <textarea name="footer_text" class="form-input auto-expand-textarea" rows="2" placeholder="Thank you for your business! • Contact us at info@company.com" style="font-size: 0.8125rem; resize: none; overflow: hidden; min-height: 60px; line-height: 1.5;" oninput="autoExpandTextarea(this)" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'"></textarea>
              </div>

            </div>

          </div>
        </div>

        <!-- RIGHT COLUMN: Summary & Actions -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem; min-height: 0;">
          
          <!-- Quote Summary -->
          <div style="background: linear-gradient(135deg, hsl(240 5% 98%), white); border: 1.5px solid hsl(214 20% 88%); border-radius: 10px; padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.875rem;">
              <div style="width: 32px; height: 32px; background: linear-gradient(135deg, rgba(113,148,165,0.15), rgba(113,148,165,0.25)); border-radius: 7px; display: flex; align-items: center; justify-content: center;">
                <?php
                // Display dynamic currency icon based on settings
                $modalCurrency = $_SESSION['currency'] ?? 'PHP';
                $modalCurrencyIcons = [
                  'PHP' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2" stroke-linecap="round"><path d="M12 2V22M7 7H14C15.0609 7 16.0783 7.42143 16.8284 8.17157C17.5786 8.92172 18 9.93913 18 11C18 12.0609 17.5786 13.0783 16.8284 13.8284C16.0783 14.5786 15.0609 15 14 15H7"/><path d="M7 11H17" stroke-width="2"/></svg>',
                  'USD' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2" stroke-linecap="round"><path d="M12 2V22M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6"/></svg>',
                  'EUR' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2" stroke-linecap="round"><path d="M18.5 5C17 5 15.5 6 14.5 7.5C13.5 9 13 11 13 13C13 15 13.5 17 14.5 18.5C15.5 20 17 21 18.5 21M7 10H15M7 14H15"/></svg>',
                  'GBP' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2" stroke-linecap="round"><path d="M18 19H6L9 13M9 13C9 11 8.5 9 10 7C11 5.5 13 5 14 5C15.5 5 17 6 17 7.5M9 13H15"/></svg>',
                  'JPY' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2" stroke-linecap="round"><path d="M6 4L12 12M18 4L12 12M12 12V22M8 14H16M8 17H16"/></svg>',
                  'CNY' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2" stroke-linecap="round"><path d="M6 4L12 12M18 4L12 12M12 12V22M8 14H16M8 17H16"/></svg>'
                ];
                echo $modalCurrencyIcons[$modalCurrency] ?? $modalCurrencyIcons['USD'];
                ?>
              </div>
              <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Quote Summary</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.625rem;">
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;">
                <span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Subtotal</span>
                <span id="modalSubtotal" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;"><?php echo CurrencyHelper::symbol(); ?>0.00</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;">
                <span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Tax (12%)</span>
                <span id="modalTax" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;"><?php echo CurrencyHelper::symbol(); ?>0.00</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.875rem; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 7px; box-shadow: 0 4px 12px rgba(113,148,165,0.3);">
                <span style="color: white; font-weight: 700; font-size: 0.875rem;">Total</span>
                <span id="modalTotal" style="font-weight: 800; font-size: 1.125rem; color: white;"><?php echo CurrencyHelper::symbol(); ?>0.00</span>
              </div>
            </div>
          </div>

          <!-- Action Buttons - Swapped Order -->
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <button type="button" onclick="attemptCloseQuoteModal()" style="width: 100%; background: white; color: hsl(220 14% 11%); border: 1.5px solid hsl(214 20% 85%); padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; font-size: 0.8125rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='hsl(240 5% 98%)'; this.style.borderColor='hsl(214 20% 75%)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.08)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(214 20% 85%)'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'">
              Cancel
            </button>
            <button type="submit" id="submitQuoteBtn" style="width: 100%; background: linear-gradient(135deg, hsl(220 14% 11%) 0%, hsl(220 13% 18%) 100%); color: white; border: none; padding: 0.875rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.625rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1), inset 0 -2px 0 rgba(0, 0, 0, 0.2); font-size: 0.875rem; letter-spacing: 0.025em; position: relative; overflow: hidden;" onmouseover="this.style.transform='translateY(-2px) scale(1.02)'; this.style.boxShadow='0 8px 20px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.15), inset 0 -2px 0 rgba(0, 0, 0, 0.25)'; this.style.background='linear-gradient(135deg, hsl(220 14% 14%) 0%, hsl(220 13% 22%) 100%)'" onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 4px 14px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1), inset 0 -2px 0 rgba(0, 0, 0, 0.2)'; this.style.background='linear-gradient(135deg, hsl(220 14% 11%) 0%, hsl(220 13% 18%) 100%)'" onmousedown="this.style.transform='translateY(0) scale(0.98)'" onmouseup="this.style.transform='translateY(-2px) scale(1.02)'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12l5 5L20 7"/>
              </svg>
              <span>Create Quotation</span>
            </button>
          </div>

        </div>
      </div>
    </form>
  </div>
</div>

<!-- Close Confirmation Modal -->
<div id="closeConfirmModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 60; align-items: center; justify-content: center; padding: 2rem; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
  <div style="width: 100%; max-width: 480px; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.4); overflow: hidden; animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, hsl(48 96% 89%) 0%, hsl(48 90% 85%) 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(48 80% 75%);">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 48px; height: 48px; background: hsl(25 95% 16%); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="hsl(48 96% 89%)" stroke-width="2.5">
            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <div>
          <h3 style="margin: 0 0 0.25rem 0; font-size: 1.25rem; font-weight: 700; color: hsl(25 95% 16%);">Discard Changes?</h3>
          <p style="margin: 0; font-size: 0.875rem; color: hsl(25 75% 25%); opacity: 0.9;">Your unsaved changes will be lost</p>
        </div>
      </div>
    </div>
    
    <!-- Content -->
    <div style="padding: 2rem;">
      <p style="margin: 0 0 1.5rem 0; font-size: 0.9375rem; color: hsl(215 16% 35%); line-height: 1.6;">
        Are you sure you want to close this quotation? <strong>All text fields will be reset</strong> and any information you've entered will be lost.
      </p>
      
      <div style="background: hsl(48 96% 95%); border: 1px solid hsl(48 90% 80%); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: start; gap: 0.75rem;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 40%)" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <p style="margin: 0; font-size: 0.8125rem; color: hsl(25 85% 25%); line-height: 1.5;">
            <strong>Tip:</strong> Click "Create Quotation" to save your work before closing.
          </p>
        </div>
      </div>
      
      <!-- Action Buttons -->
      <div style="display: flex; gap: 0.75rem;">
        <button type="button" onclick="cancelCloseQuoteModal()" style="flex: 1; background: white; color: hsl(220 14% 11%); border: 1.5px solid hsl(214 20% 85%); padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; font-size: 0.875rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;" onmouseover="this.style.background='hsl(240 5% 98%)'; this.style.borderColor='hsl(214 20% 70%)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(214 20% 85%)'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 19l-7-7 7-7"/>
          </svg>
          Keep Editing
        </button>
        <button type="button" onclick="confirmCloseQuoteModal()" style="flex: 1; background: linear-gradient(135deg, hsl(0 74% 50%) 0%, hsl(0 74% 42%) 100%); color: white; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; font-size: 0.875rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(220, 38, 38, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(220, 38, 38, 0.3)'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
          </svg>
          Discard & Close
        </button>
      </div>
    </div>
  </div>
</div>

<style>
@keyframes slideUp {
  from { 
    transform: translateY(40px) scale(0.95); 
    opacity: 0; 
  }
  to { 
    transform: translateY(0) scale(1); 
    opacity: 1; 
  }
}

.quote-tab-btn {
  transition: all 0.2s ease;
  position: relative;
}

.quote-tab-btn:hover:not(.active) {
  background: rgba(113, 148, 165, 0.08) !important;
  color: #7194A5 !important;
}

.quote-tab-content {
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Sortable table header styles */
.sortable-header {
  transition: all 0.2s ease;
  background: #f9fafb; /* neutral gray-50 */
}

.sortable-header:hover {
  background: #f3f4f6 !important; /* neutral gray-100 */
  color: #111827; /* neutral gray-900 */
}

.sortable-header:active {
  transform: scale(0.98);
}

/* Shimmer effect for submit button - Black Theme */
#submitQuoteBtn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, 
    transparent, 
    rgba(255,255,255,0.2), 
    transparent
  );
  transition: left 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

#submitQuoteBtn:hover::before {
  left: 100%;
}

/* Pulse animation on button - Black Theme */
@keyframes pulse {
  0%, 100% {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1), inset 0 -2px 0 rgba(0, 0, 0, 0.2);
  }
  50% {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.15), inset 0 -2px 0 rgba(0, 0, 0, 0.25);
  }
}

#submitQuoteBtn:not(:disabled) {
  animation: pulse 2.5s ease-in-out infinite;
}

/* Glow effect on hover */
#submitQuoteBtn:hover {
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5), 
              inset 0 1px 0 rgba(255, 255, 255, 0.15), 
              inset 0 -2px 0 rgba(0, 0, 0, 0.25),
              0 0 20px rgba(255, 255, 255, 0.1) !important;
}

/* Spin animation for loading */
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Custom scrollbar for modal */
#newQuoteModal ::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

#newQuoteModal ::-webkit-scrollbar-track {
  background: hsl(240 5% 96%);
  border-radius: 10px;
}

#newQuoteModal ::-webkit-scrollbar-thumb {
  background: hsl(214 20% 88%);
  border-radius: 10px;
  transition: background 0.2s;
}

#newQuoteModal ::-webkit-scrollbar-thumb:hover {
  background: #7194A5;
}

/* Smooth scrolling */
.quote-tab-content {
  scrollbar-width: thin;
  scrollbar-color: hsl(214 20% 88%) hsl(240 5% 96%);
}

@media print {
  /* Remove browser headers and footers */
  @page {
    margin: 0.5cm;
    size: auto;
  }
  
  /* Reset body */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  
  html, body {
    height: auto !important;
    overflow: visible !important;
  }
  
  /* Show only essential content */
  body {
    background: white !important;
    margin: 0 !important;
    padding: 15px !important;
  }
  
  /* Hide specific elements - DON'T hide body > div:first-child as it contains everything! */
  header,
  nav,
  .header,
  .navigation,
  #statsCards,
  #advancedCapabilities,
  .toolbar,
  .btn,
  button,
  a,
  #newQuoteModal,
  #viewQuoteModal,
  #actionsDropdown,
  #bulkActionsDropdown,
  #paginationControls,
  [style*="background: #7194A5"] {
    display: none !important;
  }
  
  /* Force show print summary */
  .print-summary {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    page-break-after: avoid;
    position: relative !important;
  }
  
  /* Force show table container */
  .table-container {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
  }
  
  /* Force show print receipt at top (above table) */
  .print-receipt-top {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    page-break-after: avoid;
    page-break-inside: avoid;
  }
  
  .print-receipt-top * {
    visibility: visible !important;
    opacity: 1 !important;
  }
  
  /* Print summary styles */
  .print-summary {
    display: block !important;
    margin: 0 0 20px 0;
    padding: 0 0 15px 0;
    border-bottom: 2px solid #333;
    page-break-inside: avoid;
  }
  
  .print-summary h2 {
    font-size: 18pt;
    color: #333 !important;
    margin: 0 0 15px 0;
    font-weight: 700;
    display: block !important;
  }
  
  .print-stats {
    display: flex !important;
    justify-content: space-between;
    gap: 10px;
    margin-top: 10px;
    flex-wrap: nowrap;
  }
  
  .print-stat-item {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    background: #f9f9f9 !important;
    display: block !important;
  }
  
  .print-stat-label {
    font-size: 9pt;
    color: #666 !important;
    margin: 0 0 5px 0;
    font-weight: 500;
    display: block !important;
  }
  
  .print-stat-value {
    font-size: 14pt;
    color: #333 !important;
    margin: 0;
    font-weight: 700;
    display: block !important;
  }
  
  /* Table styling */
  .table-container {
    display: block !important;
    page-break-inside: avoid;
    margin-top: 0;
    width: 100%;
  }
  
  .data-table {
    width: 100% !important;
    border-collapse: collapse;
    font-size: 10pt;
    display: table !important;
  }
  
  .data-table thead {
    display: table-header-group !important;
  }
  
  .data-table tbody {
    display: table-row-group !important;
  }
  
  .data-table th {
    background: #f0f0f0 !important;
    color: #333 !important;
    border: 1px solid #333;
    padding: 10px 8px;
    text-align: left;
    font-weight: bold;
    font-size: 10pt;
    display: table-cell !important;
  }
  
  .data-table td {
    border: 1px solid #ddd;
    padding: 8px;
    color: #333 !important;
    font-size: 10pt;
    display: table-cell !important;
  }
  
  .data-table tbody tr {
    page-break-inside: avoid;
    display: table-row !important;
    visibility: visible !important;
  }
  
  /* Force ALL rows to show (override pagination hiding) */
  .data-table tbody tr[style*="display: none"],
  .data-table tbody tr[style*="display:none"] {
    display: table-row !important;
  }
  
  /* Hide checkbox and action columns in table */
  .data-table .checkbox-column,
  .data-table th:last-child,
  .data-table td:last-child {
    display: none !important;
  }
  
  /* Badge styling */
  .badge {
    border: 1px solid #666;
    padding: 3px 8px;
    font-size: 9pt;
    border-radius: 3px;
    display: inline-block !important;
  }
  
  .badge-warning {
    background: #fff3cd !important;
    color: #856404 !important;
    border-color: #856404;
  }
  
  .badge-success {
    background: #d4edda !important;
    color: #155724 !important;
    border-color: #155724;
  }
  
  .badge-danger {
    background: #f8d7da !important;
    color: #721c24 !important;
    border-color: #721c24;
  }
  
  .badge-default {
    background: #e2e3e5 !important;
    color: #383d41 !important;
    border-color: #383d41;
  }
  
  /* Force visibility of print content */
  .print-summary,
  .print-summary *,
  .print-receipt-top,
  .print-receipt-top *,
  .table-container,
  .table-container *,
  .data-table,
  .data-table *,
  .data-table tbody tr {
    visibility: visible !important;
    opacity: 1 !important;
  }
  
  /* Override inline styles that hide elements */
  body * {
    max-height: none !important;
  }
  
  /* Debug: Make sure containers don't interfere */
  .container {
    width: 100% !important;
    max-width: none !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  
  /* Ensure all content is visible and positioned correctly */
  .print-summary,
  .table-container {
    transform: none !important;
    clip: auto !important;
    clip-path: none !important;
  }
  
  /* Show all elements within print content */
  .print-summary > *,
  .table-container > * {
    display: block !important;
  }
  
  .print-stats {
    display: flex !important;
  }
  
  .data-table {
    display: table !important;
  }
}

/* Responsive adjustments */
@media (max-height: 700px) {
  #newQuoteModal > div {
    max-height: 95vh;
  }
}
</style>

<script>
// Settings from PHP session
const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';
const currencyCode = '<?php echo $_SESSION['currency'] ?? 'PHP'; ?>';
const userTimezone = '<?php echo $_SESSION['timezone'] ?? date_default_timezone_get(); ?>';
const phpTimestamp = '<?php echo date('c'); ?>'; // ISO 8601 format with timezone

console.log('Settings loaded:', { currencySymbol, currencyCode, userTimezone });

let lineItemCounter = 0;

// Pagination variables
let currentPage = 1;
let itemsPerPage = 6;
let allQuotations = <?php echo json_encode($quotations); ?>;
let originalQuotations = <?php echo json_encode($quotations); ?>; // Backup for filtering
let currentStatusFilter = 'all';
let checkboxesVisible = false;

// Sorting state
let currentSortColumn = null;
let currentSortDirection = 'asc'; // 'asc' or 'desc'

// Initialize pagination on page load
document.addEventListener('DOMContentLoaded', function() {
  console.log('Quotations page loaded with', allQuotations.length, 'quotations');
  initializePagination();
});

// Track if form has been modified
let isFormDirty = false;

function showNewQuoteModal() {
  const modal = document.getElementById('newQuoteModal');
  modal.style.display = 'flex';
  lineItemCounter = 0;
  document.getElementById('lineItemsContainer').innerHTML = '';
  
  // Reset dirty flag - form is pristine when opened
  isFormDirty = false;

  // Add first line item
  addLineItem();
  
  // Set date to today (fix Jan 01, 1970 issue)
  const dateInput = modal.querySelector('input[name="date"]');
  if (dateInput) {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    dateInput.value = `${yyyy}-${mm}-${dd}`;
    console.log('✓ Date set to:', dateInput.value);
  }
  
  // Set up form change detection
  setupFormChangeDetection();
  
  // Focus first input after modal animation
  setTimeout(() => {
    const firstInput = modal.querySelector('input[name="quote_number"]');
    if (firstInput) {
      firstInput.focus();
      firstInput.select(); // Select text for easy override
    }
  }, 100);
  
  console.log('✓ Quote modal opened - Form is pristine');
}

function closeQuoteModal() {
  const modal = document.getElementById('newQuoteModal');
  modal.style.display = 'none';
  document.getElementById('quoteForm').reset();
  document.getElementById('lineItemsContainer').innerHTML = '';
  lineItemCounter = 0;
  isFormDirty = false; // Reset dirty flag
  console.log('✓ Quote modal closed');
}

// Show confirmation modal before closing (only if form has changes)
function attemptCloseQuoteModal() {
  // If form is pristine (no changes), close directly
  if (!isFormDirty) {
    closeQuoteModal();
    console.log('✓ Form is pristine - Closing without confirmation');
    return;
  }
  
  // Form has changes - show confirmation
  const confirmModal = document.getElementById('closeConfirmModal');
  confirmModal.style.display = 'flex';
  console.log('⚠️ Form has changes - Close confirmation modal displayed');
}

// Confirm close action - Close both modals and reset form
function confirmCloseQuoteModal() {
  const confirmModal = document.getElementById('closeConfirmModal');
  const quoteModal = document.getElementById('newQuoteModal');
  
  // Hide confirmation modal
  confirmModal.style.display = 'none';
  
  // Close quote modal and reset
  quoteModal.style.display = 'none';
  document.getElementById('quoteForm').reset();
  document.getElementById('lineItemsContainer').innerHTML = '';
  lineItemCounter = 0;
  isFormDirty = false; // Reset dirty flag
  
  console.log('✅ Quote modal closed - All fields reset');
  Toast.info('Quotation discarded. All fields have been reset.');
}

// Cancel close action - Keep quote modal open
function cancelCloseQuoteModal() {
  const confirmModal = document.getElementById('closeConfirmModal');
  confirmModal.style.display = 'none';
  console.log('ℹ️ Close action cancelled - Quote modal remains open');
}

// Set up form change detection
function setupFormChangeDetection() {
  const form = document.getElementById('quoteForm');
  if (!form) return;
  
  // Mark form as dirty on any input change
  const markDirty = () => {
    if (!isFormDirty) {
      isFormDirty = true;
      console.log('📝 Form modified - Dirty flag set to true');
    }
  };
  
  // Monitor all input fields
  const inputs = form.querySelectorAll('input, textarea, select');
  inputs.forEach(input => {
    // Skip the auto-generated quote number and date (they're set by default)
    const isQuoteNumber = input.name === 'quote_number';
    const isDate = input.name === 'date';
    
    // For quote number and date, only mark dirty if user manually changes them
    if (isQuoteNumber || isDate) {
      input.addEventListener('input', markDirty, { once: false });
      input.addEventListener('change', markDirty, { once: false });
    } else {
      // For all other fields, mark dirty on any change
      input.addEventListener('input', markDirty, { once: false });
      input.addEventListener('change', markDirty, { once: false });
    }
  });
  
  // Also monitor dynamically added line items
  const lineItemsContainer = document.getElementById('lineItemsContainer');
  if (lineItemsContainer) {
    // Use event delegation for dynamically added inputs
    lineItemsContainer.addEventListener('input', markDirty);
    lineItemsContainer.addEventListener('change', markDirty);
  }
  
  console.log('✓ Form change detection initialized');
}

// Handle Escape key to close modal
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const confirmModal = document.getElementById('closeConfirmModal');
    const quoteModal = document.getElementById('newQuoteModal');
    
    // Priority 1: Close confirmation modal if open (cancel the close action)
    if (confirmModal && confirmModal.style.display === 'flex') {
      cancelCloseQuoteModal();
      return;
    }
    
    // Priority 2: Show confirmation if quote modal is open
    if (quoteModal && quoteModal.style.display === 'flex') {
      attemptCloseQuoteModal();
    }
  }
});

// Auto-expand textarea as user types
function autoExpandTextarea(textarea) {
  textarea.style.height = 'auto';
  textarea.style.height = textarea.scrollHeight + 'px';
}

// Insert predefined terms templates
function insertTemplate(type) {
  const textarea = document.getElementById('termsTextarea');
  const templates = {
    standard: `PAYMENT TERMS:
• Payment is due within 30 days of invoice date
• Late payments may incur additional fees
• All prices are in ${currencySymbol}

DELIVERY:
• Delivery within 5-7 business days
• Shipping charges may apply
• Customer is responsible for inspection upon delivery

WARRANTY:
• All products come with a standard 1-year warranty
• Warranty covers manufacturing defects only`,
    
    warranty: `WARRANTY POLICY:
• Products are covered by a 12-month manufacturer's warranty
• Warranty begins from the date of delivery
• Covers defects in materials and workmanship
• Does not cover damage from misuse, accidents, or normal wear and tear
• Warranty claim requires proof of purchase
• Repair or replacement at our discretion`,
    
    refund: `REFUND POLICY:
• 30-day money-back guarantee
• Items must be returned in original condition
• Return shipping costs are the responsibility of the customer
• Refunds will be processed within 5-7 business days
• Custom or personalized items are non-refundable
• Contact support@company.com to initiate a return`
  };
  
  if (templates[type]) {
    textarea.value = templates[type];
    autoExpandTextarea(textarea);
    textarea.focus();
    Toast.success(`${type.charAt(0).toUpperCase() + type.slice(1)} template inserted!`);
  }
}

// Toggle late fee fields
document.addEventListener('DOMContentLoaded', function() {
  const lateFeeCheckbox = document.getElementById('enableLateFees');
  const lateFeeFields = document.getElementById('lateFeeFields');
  
  if (lateFeeCheckbox && lateFeeFields) {
    lateFeeCheckbox.addEventListener('change', function() {
      if (this.checked) {
        lateFeeFields.style.display = 'block';
        lateFeeFields.style.animation = 'fadeIn 0.3s ease';
      } else {
        lateFeeFields.style.display = 'none';
      }
    });
  }
  
  // Auto-expand all textareas on page load
  document.querySelectorAll('.auto-expand-textarea').forEach(function(textarea) {
    autoExpandTextarea(textarea);
  });
});

// Tab Switching for Quote Modal
function switchQuoteTab(tabName) {
  // Hide all tabs
  document.querySelectorAll('.quote-tab-content').forEach(tab => {
    tab.style.display = 'none';
  });
  
  // Remove active class from all buttons
  document.querySelectorAll('.quote-tab-btn').forEach(btn => {
    btn.style.background = 'transparent';
    btn.style.borderBottom = '3px solid transparent';
    btn.style.color = 'hsl(215 16% 47%)';
    btn.classList.remove('active');
  });
  
  // Show selected tab
  document.getElementById('quote-tab-' + tabName).style.display = 'block';
  
  // Mark button as active
  const activeBtn = document.querySelector(`.quote-tab-btn[data-tab="${tabName}"]`);
  activeBtn.style.background = 'white';
  activeBtn.style.borderBottom = '3px solid #7194A5';
  activeBtn.style.color = '#7194A5';
  activeBtn.classList.add('active');
}

function addLineItem() {
  lineItemCounter++;
  const container = document.getElementById('lineItemsContainer');
  const item = document.createElement('div');
  item.className = 'line-item';
  item.id = 'lineItem' + lineItemCounter;
  item.style.cssText = 'display: grid; grid-template-columns: 3fr 0.8fr 1fr 1fr auto; gap: 0.875rem; padding: 1.25rem; background: linear-gradient(135deg, hsl(210 20% 98%), white); border: 1.5px solid hsl(214 20% 88%); border-radius: 10px; margin-bottom: 1rem; align-items: end; transition: all 0.2s ease;';
  item.onmouseover = function() { this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.08)'; this.style.borderColor = 'hsl(214 20% 75%)'; };
  item.onmouseout = function() { this.style.boxShadow = 'none'; this.style.borderColor = 'hsl(214 20% 88%)'; };
  
  item.innerHTML = `
    <div class="form-group" style="margin: 0; min-width: 0;">
      <label class="form-label" style="font-size: 0.75rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.375rem; display: block;">Description <span style="color: #ef4444;">*</span></label>
      <input type="text" name="items[${lineItemCounter}][description]" class="form-input" placeholder="Product or service name" required style="border-radius: 7px; border: 1.5px solid hsl(214 20% 88%); padding: 0.625rem 0.75rem; font-size: 0.875rem; width: 100%; box-sizing: border-box; transition: all 0.2s;" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
    </div>
    <div class="form-group" style="margin: 0;">
      <label class="form-label" style="font-size: 0.75rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.375rem; display: block;">Qty <span style="color: #ef4444;">*</span></label>
      <input type="number" name="items[${lineItemCounter}][quantity]" class="form-input item-quantity" min="1" value="1" required onchange="calculateTotals()" style="border-radius: 7px; border: 1.5px solid hsl(214 20% 88%); padding: 0.625rem 0.75rem; font-size: 0.875rem; width: 100%; transition: all 0.2s;" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
    </div>
    <div class="form-group" style="margin: 0;">
      <label class="form-label" style="font-size: 0.75rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.375rem; display: block;">Price <span style="color: #ef4444;">*</span></label>
      <input type="number" name="items[${lineItemCounter}][unit_price]" class="form-input item-price" step="0.01" min="0" required onchange="calculateTotals()" placeholder="0.00" style="border-radius: 7px; border: 1.5px solid hsl(214 20% 88%); padding: 0.625rem 0.75rem; font-size: 0.875rem; width: 100%; transition: all 0.2s;" onfocus="this.style.borderColor='#7194A5'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
    </div>
    <div class="form-group" style="margin: 0;">
      <label class="form-label" style="font-size: 0.75rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.375rem; display: block;">Total</label>
      <input type="text" class="form-input item-total" readonly style="background: hsl(143 85% 96%); font-weight: 700; color: hsl(140 61% 13%); border-radius: 7px; border: 1.5px solid hsl(143 60% 85%); padding: 0.625rem 0.75rem; font-size: 0.875rem; width: 100%; cursor: not-allowed;" value="${currencySymbol}0.00">
    </div>
    <button type="button" onclick="removeLineItem('lineItem${lineItemCounter}')" style="background: hsl(0 86% 97%); color: hsl(0 74% 42%); border: 1.5px solid hsl(0 86% 90%); border-radius: 7px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; flex-shrink: 0;" title="Remove item" onmouseover="this.style.background='hsl(0 86% 94%)'; this.style.borderColor='hsl(0 74% 75%)'; this.style.transform='scale(1.05)'" onmouseout="this.style.background='hsl(0 86% 97%)'; this.style.borderColor='hsl(0 86% 90%)'; this.style.transform='scale(1)'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6L18 18"/></svg>
    </button>
  `;
  
  container.appendChild(item);
  calculateTotals();
}

function removeLineItem(id) {
  const item = document.getElementById(id);
  if (document.querySelectorAll('.line-item').length > 1) {
    item.remove();
    calculateTotals();
  } else {
    Toast.warning('At least one line item is required');
  }
}

function calculateTotals() {
  let subtotal = 0;
  
  document.querySelectorAll('.line-item').forEach(item => {
    const qty = parseFloat(item.querySelector('.item-quantity').value) || 0;
    const price = parseFloat(item.querySelector('.item-price').value) || 0;
    const total = qty * price;
    
    item.querySelector('.item-total').value = currencySymbol + total.toFixed(2);
    subtotal += total;
  });
  
  // Fixed at 12% for now
  const taxRate = 12;
  const tax = subtotal * (taxRate / 100);
  const total = subtotal + tax;
  
  const subtotalEl = document.getElementById('modalSubtotal');
  const taxEl = document.getElementById('modalTax');
  const totalEl = document.getElementById('modalTotal');
  if (subtotalEl) subtotalEl.textContent = currencySymbol + subtotal.toFixed(2);
  if (taxEl) taxEl.textContent = currencySymbol + tax.toFixed(2);
  if (totalEl) totalEl.textContent = currencySymbol + total.toFixed(2);
}

async function submitQuote(event) {
  event.preventDefault();
  const form = event.target;
  const submitBtn = document.getElementById('submitQuoteBtn');
  const originalHTML = submitBtn.innerHTML;
  
  // Custom validation for fields in hidden tabs
  const requiredFields = form.querySelectorAll('[required]');
  let firstInvalidField = null;
  let invalidTab = null;
  
  for (const field of requiredFields) {
    if (!field.value || field.value.trim() === '') {
      firstInvalidField = field;
      
      // Find which tab this field belongs to
      const tabContent = field.closest('.quote-tab-content');
      if (tabContent) {
        invalidTab = tabContent.id.replace('quote-tab-', '');
      }
      break;
    }
  }
  
  if (firstInvalidField) {
    // Log validation error
    console.log('❌ Validation failed: Required fields missing');
    console.log('Field:', firstInvalidField.name, 'Placeholder:', firstInvalidField.placeholder);
    
    // Detect which tab the field belongs to (special handling for dynamic fields)
    let targetTab = invalidTab;
    
    // Check if field is a line item (items[x][...])
    if (firstInvalidField.name && firstInvalidField.name.startsWith('items[')) {
      targetTab = 'items';
      console.log('Field is a line item, switching to items tab');
    }
    
    // Add visual feedback - red border with shake animation
    firstInvalidField.style.borderColor = 'hsl(0 74% 50%)';
    firstInvalidField.style.borderWidth = '2px';
    firstInvalidField.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.15)';
    firstInvalidField.style.animation = 'shake 0.4s ease-in-out';
    
    // Add shake keyframes if not exists
    if (!document.getElementById('shake-animation-style')) {
      const style = document.createElement('style');
      style.id = 'shake-animation-style';
      style.textContent = `
        @keyframes shake {
          0%, 100% { transform: translateX(0); }
          25% { transform: translateX(-5px); }
          75% { transform: translateX(5px); }
        }
      `;
      document.head.appendChild(style);
    }
    
    // Create floating error tooltip
    const errorTooltip = document.createElement('div');
    errorTooltip.id = 'validation-error-tooltip';
    errorTooltip.style.cssText = `
      position: absolute;
      background: hsl(0 74% 50%);
      color: white;
      padding: 0.5rem 0.75rem;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 600;
      z-index: 10000;
      pointer-events: none;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      animation: slideDown 0.3s ease-out;
    `;
    errorTooltip.textContent = '⚠️ ' + (firstInvalidField.placeholder || 'This field is required');
    
    // Add slideDown animation
    if (!document.getElementById('slideDown-animation-style')) {
      const slideStyle = document.createElement('style');
      slideStyle.id = 'slideDown-animation-style';
      slideStyle.textContent = `
        @keyframes slideDown {
          from { opacity: 0; transform: translateY(-10px); }
          to { opacity: 1; transform: translateY(0); }
        }
      `;
      document.head.appendChild(slideStyle);
    }
    
    // Remove red border and tooltip on input
    const cleanup = () => {
      firstInvalidField.style.borderColor = '';
      firstInvalidField.style.borderWidth = '';
      firstInvalidField.style.boxShadow = '';
      firstInvalidField.style.animation = '';
      const tooltip = document.getElementById('validation-error-tooltip');
      if (tooltip) tooltip.remove();
    };
    
    firstInvalidField.addEventListener('input', cleanup, { once: true });
    firstInvalidField.addEventListener('focus', cleanup, { once: true });
    
    // Switch to the tab containing the invalid field
    if (targetTab) {
      switchQuoteTab(targetTab);
      
      // Wait for tab switch animation, then focus
      setTimeout(() => {
        // Ensure field is visible and in the DOM
        if (firstInvalidField.offsetParent === null) {
          console.warn('Field not visible after tab switch, retrying...');
          setTimeout(() => {
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Position tooltip
            const rect = firstInvalidField.getBoundingClientRect();
            errorTooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
            errorTooltip.style.left = (rect.left + window.scrollX) + 'px';
            document.body.appendChild(errorTooltip);
            
            // Remove tooltip after 3 seconds
            setTimeout(() => errorTooltip.remove(), 3000);
          }, 100);
        } else {
          firstInvalidField.focus();
          firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
          
          // Position tooltip
          const rect = firstInvalidField.getBoundingClientRect();
          errorTooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
          errorTooltip.style.left = (rect.left + window.scrollX) + 'px';
          document.body.appendChild(errorTooltip);
          
          // Remove tooltip after 3 seconds
          setTimeout(() => errorTooltip.remove(), 3000);
        }
      }, 400);
    } else {
      // Field is in current tab
      firstInvalidField.focus();
      firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
      
      // Position tooltip
      setTimeout(() => {
        const rect = firstInvalidField.getBoundingClientRect();
        errorTooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
        errorTooltip.style.left = (rect.left + window.scrollX) + 'px';
        document.body.appendChild(errorTooltip);
        
        // Remove tooltip after 3 seconds
        setTimeout(() => errorTooltip.remove(), 3000);
      }, 100);
    }
    
    return false;
  }
  
  // Show loading state
  submitBtn.disabled = true;
  submitBtn.innerHTML = `
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
      <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
      <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
    </svg>
    <span>Creating...</span>
  `;
  
  try {
    // Collect form data with all enterprise features
    const formData = new FormData(form);
    const data = {
      quote_number: formData.get('quote_number'),
      customer: formData.get('customer'),
      customer_email: formData.get('customer_email') || '',
      customer_phone: formData.get('customer_phone') || '',
      customer_company: formData.get('customer_company') || '',
      date: formData.get('date'),
      validity_days: formData.get('validity_days'),
      payment_terms: formData.get('payment_terms') || 'Net 30',
      reference: formData.get('reference') || '',
      department: formData.get('department') || '',
      discount_percent: parseFloat(formData.get('discount_percent')) || 0,
      shipping_cost: parseFloat(formData.get('shipping_cost')) || 0,
      notes: formData.get('notes') || '',
      footer_text: formData.get('footer_text') || '',
      tax_rate: 12,
      items: [],
      
      // Payment methods
      payment_methods: formData.getAll('payment_methods[]'),
      
      // Bank details
      bank_name: formData.get('bank_name') || '',
      bank_account: formData.get('bank_account') || '',
      bank_routing: formData.get('bank_routing') || '',
      bank_swift: formData.get('bank_swift') || '',
      
      // Late fees
      enable_late_fees: formData.get('enable_late_fees') ? true : false,
      late_fee_type: formData.get('late_fee_type') || '',
      late_fee_amount: parseFloat(formData.get('late_fee_amount')) || 0,
      late_fee_days: parseInt(formData.get('late_fee_days')) || 30
    };
    
    // Collect line items
    const itemDescriptions = formData.getAll('items[1][description]');
    const itemQuantities = formData.getAll('items[1][quantity]');
    const itemPrices = formData.getAll('items[1][unit_price]');
    
    for (let i = 0; i < itemDescriptions.length; i++) {
      if (itemDescriptions[i] && itemQuantities[i] && itemPrices[i]) {
        data.items.push({
          description: itemDescriptions[i],
          quantity: parseFloat(itemQuantities[i]),
          unit_price: parseFloat(itemPrices[i])
        });
      }
    }
    
    // Send AJAX request
    const response = await fetch('api/save_quotation.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      closeQuoteModal();
      Toast.success(`Quotation ${result.quote_number} created successfully!`);
      
      // Reload page to show new quotation
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      throw new Error(result.message || 'Failed to create quotation');
    }
    
  } catch (error) {
    console.error('Quotation creation error:', error);
    Toast.error('Error: ' + error.message);
    
    // Restore button
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalHTML;
  }
  
  return false;
}

function convertToOrder(id) {
  if (confirm('Convert quotation to a sales order?\n\nThis will create a new order and mark the quotation as converted.')) {
    console.log('Converting quotation to order:', id);
    fetch('/api/quotations.php?action=convert_to_order', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
      console.log('Convert response:', data);
      if (data.success) {
        updateQuotationRow(id, 'converted');
      } else {
        console.error('Failed to convert:', data.message);
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Network error:', error);
      alert('Network error: ' + error.message);
    });
  }
}

function emailQuote(id) {
  const email = prompt('Enter email address to send quotation:');
  if (email) {
    console.warn('Email functionality not implemented');
    alert('Email functionality requires SMTP configuration.');
  }
}

function deleteQuote(id) {
  if (confirm('Delete this quotation?\n\nThis action cannot be undone.')) {
    console.log('Deleting quotation:', id);
    fetch('/api/quotations.php?action=delete', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
      console.log('Delete response:', data);
      if (data.success) {
        allQuotations = allQuotations.filter(q => q.id !== id);
        originalQuotations = originalQuotations.filter(q => q.id !== id);
        updateStats();
        rebuildTable(); // Rebuild table after delete
        updatePagination();
        renderCurrentPage();
        console.log('Quotation deleted successfully');
      } else {
        console.error('Failed to delete:', data.message);
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Network error:', error);
      alert('Network error: ' + error.message);
    });
  }
}

// Print quotations with validation
function printQuotes() {
  // Check all tbody rows (including hidden ones initially)
  const allRows = document.querySelectorAll('.data-table tbody tr');
  
  if (!allRows || allRows.length === 0) {
    console.warn('No quotations to print');
    alert('No quotations to print. Please add quotations first.');
    return;
  }
  
  // Update print summary with current stats
  updatePrintSummary();
  
  // Debug: Check if elements exist
  const printSummary = document.querySelector('.print-summary');
  const tableContainer = document.querySelector('.table-container');
  console.log('Print summary found:', !!printSummary, printSummary);
  console.log('Table container found:', !!tableContainer, tableContainer);
  console.log('Print summary display:', window.getComputedStyle(printSummary).display);
  console.log('Table container display:', window.getComputedStyle(tableContainer).display);
  
  // Store current display state of all rows
  const rowStates = Array.from(allRows).map(row => ({
    element: row,
    display: row.style.display
  }));
  
  // Show ALL rows for printing (override pagination)
  allRows.forEach(row => {
    row.style.display = 'table-row';
  });
  
  console.log(`Printing all ${allRows.length} quotation(s)`);
  
  // Print
  window.print();
  
  // Restore original display states after print dialog
  setTimeout(() => {
    rowStates.forEach(state => {
      state.element.style.display = state.display;
    });
    console.log('Row visibility restored after print');
  }, 100);
}

// Update print summary statistics
function updatePrintSummary() {
  // Calculate stats from current data with financial breakdown
  const stats = {
    total: allQuotations.length,
    pending: 0,
    approved: 0,
    rejected: 0,
    total_value: 0,
    approved_value: 0,
    rejected_value: 0,
    pending_value: 0
  };
  
  allQuotations.forEach(q => {
    const status = q.status || 'pending';
    const amount = parseFloat(q.total) || 0;
    
    stats.total_value += amount;
    
    if (status === 'pending') {
      stats.pending++;
      stats.pending_value += amount;
    } else if (status === 'approved') {
      stats.approved++;
      stats.approved_value += amount;
    } else if (status === 'rejected') {
      stats.rejected++;
      stats.rejected_value += amount;
    }
  });
  
  // Calculate net realizable value (approved only)
  const net_value = stats.approved_value;
  
  // Currency formatter
  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currencyCode // Use currency code from settings
    }).format(amount);
  };
  
  // Update top print summary elements
  document.getElementById('printTotalQuotes').textContent = stats.total.toLocaleString();
  document.getElementById('printPendingCount').textContent = stats.pending.toLocaleString();
  document.getElementById('printApprovedCount').textContent = stats.approved.toLocaleString();
  document.getElementById('printRejectedCount').textContent = stats.rejected.toLocaleString();
  document.getElementById('printTotalValue').textContent = formatCurrency(stats.total_value);
  
  // Update receipt summary elements (top - above table)
  document.getElementById('receiptTopTotalQuotes').textContent = stats.total.toLocaleString();
  document.getElementById('receiptTopPendingCount').textContent = stats.pending.toLocaleString();
  document.getElementById('receiptTopApprovedCount').textContent = stats.approved.toLocaleString();
  document.getElementById('receiptTopRejectedCount').textContent = stats.rejected.toLocaleString();
  
  document.getElementById('receiptTopGrossTotal').textContent = formatCurrency(stats.total_value);
  document.getElementById('receiptTopApprovedValue').textContent = formatCurrency(stats.approved_value);
  document.getElementById('receiptTopRejectedLoss').textContent = '- ' + formatCurrency(stats.rejected_value);
  document.getElementById('receiptTopPendingValue').textContent = formatCurrency(stats.pending_value);
  document.getElementById('receiptTopNetValue').textContent = formatCurrency(net_value);
  
  console.log('Print summary updated:', stats);
  console.log('Financial breakdown:', {
    gross: stats.total_value,
    approved: stats.approved_value,
    rejected: stats.rejected_value,
    pending: stats.pending_value,
    net: net_value
  });
}

// Global variables for dropdown positioning
let currentQuoteId = null;
let currentQuoteStatus = null;

// Toggle select all checkboxes
function toggleSelectAll(checkbox) {
  const checkboxes = document.querySelectorAll('.quote-checkbox');
  checkboxes.forEach(cb => cb.checked = checkbox.checked);
  updateBulkActions();
}

// Update bulk actions button state
function updateBulkActions() {
  const selectedCount = document.querySelectorAll('.quote-checkbox:checked').length;
  const selectAll = document.getElementById('selectAll');
  const total = document.querySelectorAll('.quote-checkbox').length;
  
  // Update select all checkbox state
  if (selectedCount === 0) {
    selectAll.checked = false;
    selectAll.indeterminate = false;
  } else if (selectedCount === total) {
    selectAll.checked = true;
    selectAll.indeterminate = false;
  } else {
    selectAll.checked = false;
    selectAll.indeterminate = true;
  }
  
  // Update count in dropdown
  document.getElementById('selectedCount').textContent = selectedCount;
}

// View quotation details
function viewQuote(id) {
  // Fetch quotation details from API
  fetch(`/api/quotations.php?action=get&id=${id}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const quote = data.data;
        document.getElementById('viewQuoteNumber').textContent = quote.quote_number || 'N/A';
        document.getElementById('viewCustomer').textContent = quote.customer || 'N/A';
        document.getElementById('viewDate').textContent = new Date(quote.date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        document.getElementById('viewTotal').textContent = currencySymbol + parseFloat(quote.total || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        const statusBadge = document.getElementById('viewStatus');
        statusBadge.textContent = quote.status.charAt(0).toUpperCase() + quote.status.slice(1);
        
        const statusClasses = {
          'pending': 'badge-warning',
          'approved': 'badge-success',
          'rejected': 'badge-danger',
          'converted': 'badge-default'
        };
        statusBadge.className = 'badge ' + (statusClasses[quote.status] || 'badge-default');
        
        document.getElementById('viewQuoteModal').style.display = 'flex';
      } else {
        console.error('Failed to load:', data.message);
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Network error:', error);
      alert('Network error: ' + error.message);
    });
}

function closeViewModal() {
  document.getElementById('viewQuoteModal').style.display = 'none';
}

// Toggle sortable headers functionality
function toggleSortableHeaders(enable) {
  const sortableColumns = {
    'quote_number': 'Quote #',
    'customer': 'Customer',
    'date': 'Date',
    'amount': 'Total Amount',
    'status': 'Status'
  };
  
  Object.keys(sortableColumns).forEach(column => {
    // Find header by the sort icon ID (more reliable)
    const icon = document.getElementById(`sort-${column}-icon`);
    let header = null;
    
    if (icon) {
      // Icon exists, find parent th
      header = icon.closest('th');
    } else {
      // Icon doesn't exist yet, find th containing the column name
      const headers = document.querySelectorAll('.data-table thead th');
      headers.forEach(th => {
        const text = th.textContent.trim();
        if (text === sortableColumns[column]) {
          header = th;
        }
      });
    }
    
    if (header) {
      if (enable) {
        // Enable sorting
        header.classList.add('sortable-header');
        header.style.cursor = 'pointer';
        header.style.userSelect = 'none';
        header.setAttribute('onclick', `sortTable('${column}')`);
        
        // Show icon if it exists, or create placeholder
        if (icon) {
          icon.style.display = '';
        } else {
          // Icon needs to be created dynamically
          const div = header.querySelector('div');
          if (div && !div.querySelector('svg')) {
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.id = `sort-${column}-icon`;
            svg.setAttribute('width', '14');
            svg.setAttribute('height', '14');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', 'currentColor');
            svg.setAttribute('stroke-width', '2');
            svg.style.opacity = '0.4';
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', 'M7 15L12 20L17 15M7 9L12 4L17 9');
            path.setAttribute('stroke-linecap', 'round');
            path.setAttribute('stroke-linejoin', 'round');
            svg.appendChild(path);
            div.appendChild(svg);
          }
        }
      } else {
        // Disable sorting
        header.classList.remove('sortable-header');
        header.style.cursor = 'default';
        header.style.userSelect = 'auto';
        header.removeAttribute('onclick');
        if (icon) icon.style.display = 'none';
      }
    }
  });
}

// Pagination functions
function initializePagination() {
  updatePagination();
  renderCurrentPage();
}

function updatePagination() {
  const totalPages = Math.ceil(allQuotations.length / itemsPerPage);
  const start = (currentPage - 1) * itemsPerPage + 1;
  const end = Math.min(currentPage * itemsPerPage, allQuotations.length);
  
  // Toggle sortable headers based on quotations availability
  toggleSortableHeaders(allQuotations.length > 0);
  
  // Toggle table and empty state visibility
  const tableContainer = document.getElementById('quotationsTableContainer');
  const emptyState = document.getElementById('emptyStateContainer');
  const paginationControls = document.getElementById('paginationControls');
  
  if (allQuotations.length === 0) {
    // Show empty state, hide table and pagination
    if (tableContainer) tableContainer.style.display = 'none';
    if (emptyState) emptyState.style.display = 'block';
    if (paginationControls) paginationControls.style.display = 'none';
    return;
  } else {
    // Show table, hide empty state
    if (tableContainer) tableContainer.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';
  }
  
  // Hide pagination if all items fit on one page
  if (allQuotations.length <= itemsPerPage) {
    if (paginationControls) paginationControls.style.display = 'none';
  } else {
    if (paginationControls) paginationControls.style.display = 'flex';
  }
  
  document.getElementById('showingStart').textContent = allQuotations.length > 0 ? start : 0;
  document.getElementById('showingEnd').textContent = end;
  document.getElementById('totalCount').textContent = allQuotations.length;
  
  document.getElementById('prevPage').disabled = currentPage === 1;
  document.getElementById('nextPage').disabled = currentPage === totalPages;
  
  renderPageNumbers(totalPages);
}

function renderPageNumbers(totalPages) {
  const container = document.getElementById('pageNumbers');
  container.innerHTML = '';
  
  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
      const btn = document.createElement('button');
      btn.textContent = i;
      btn.style.minWidth = '2.5rem';
      btn.style.padding = '0.5rem';
      if (i === currentPage) {
        btn.className = 'btn btn-primary btn-sm';
        btn.style.fontWeight = '600';
      } else {
        btn.className = 'btn btn-ghost btn-sm';
      }
      btn.onclick = () => goToPage(i);
      container.appendChild(btn);
    } else if (i === currentPage - 2 || i === currentPage + 2) {
      const ellipsis = document.createElement('span');
      ellipsis.textContent = '...';
      ellipsis.style.padding = '0.5rem';
      ellipsis.style.color = '#9ca3af'; // var(--text-muted) neutral gray
      container.appendChild(ellipsis);
    }
  }
}

// Rebuild the table from allQuotations array
function rebuildTable() {
  const tbody = document.querySelector('.data-table tbody');
  tbody.innerHTML = ''; // Clear existing rows
  
  // Empty state is now handled separately, just build rows
  allQuotations.forEach(quote => {
    const row = document.createElement('tr');
    row.setAttribute('data-quote-id', quote.id);
    row.setAttribute('data-status', quote.status || 'pending');
    
    // Status badge classes
    const statusBadges = {
      'pending': 'badge-warning',
      'approved': 'badge-success',
      'rejected': 'badge-danger',
      'converted': 'badge-default'
    };
    const badgeClass = statusBadges[quote.status] || 'badge-default';
    
    // Format date with validation
    let formattedDate = 'Invalid Date';
    if (quote.date) {
      const date = new Date(quote.date);
      // Check if date is valid (not NaN and not epoch 0)
      if (!isNaN(date.getTime()) && date.getTime() !== 0) {
        formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
      } else {
        console.warn('Invalid date for quote:', quote.quote_number, 'Date value:', quote.date);
        formattedDate = 'Invalid Date';
      }
    } else {
      console.warn('Missing date for quote:', quote.quote_number);
      formattedDate = 'No Date';
    }
    
    // Format currency using settings
    const formattedAmount = new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currencyCode // Use currency code from settings
    }).format(quote.total || 0);
    
    // Build row HTML
    row.innerHTML = `
      <td class="checkbox-column" style="display: ${checkboxesVisible ? '' : 'none'};">
        <input type="checkbox" class="quote-checkbox" value="${quote.id}" onchange="updateBulkActions()" style="cursor: pointer;">
      </td>
      <td class="font-mono font-medium">${quote.quote_number || 'N/A'}</td>
      <td class="font-medium">${quote.customer || 'N/A'}</td>
      <td>${formattedDate}</td>
      <td class="font-semibold">${formattedAmount}</td>
      <td>
        <span class="badge ${badgeClass}">${(quote.status || 'pending').charAt(0).toUpperCase() + (quote.status || 'pending').slice(1)}</span>
      </td>
      <td>
        <div class="flex gap-1">
          <button class="btn btn-ghost btn-sm" onclick="viewQuote('${quote.id}')" title="View Details">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            </svg>
          </button>
          ${quote.status === 'pending' ? `
          <button class="btn btn-ghost btn-sm text-success" onclick="approveQuote('${quote.id}')" title="Approve">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>` : ''}
          ${quote.status === 'approved' ? `
          <button class="btn btn-ghost btn-sm text-primary" onclick="convertToOrder('${quote.id}')" title="Convert to Order">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="2"/>
            </svg>
          </button>` : ''}
          <button class="btn btn-ghost btn-sm" onclick="showQuoteActions('${quote.id}', '${quote.status || 'pending'}')" title="More Actions">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="5" r="1" fill="currentColor"/>
              <circle cx="12" cy="12" r="1" fill="currentColor"/>
              <circle cx="12" cy="19" r="1" fill="currentColor"/>
            </svg>
          </button>
        </div>
      </td>
    `;
    
    tbody.appendChild(row);
  });
  
  console.log(`Table rebuilt with ${allQuotations.length} quotations`);
}

function renderCurrentPage() {
  const tbody = document.querySelector('.data-table tbody');
  const rows = tbody.querySelectorAll('tr');
  const start = (currentPage - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  
  rows.forEach((row, index) => {
    row.style.display = (index >= start && index < end) ? '' : 'none';
  });
  
  console.log(`Displaying quotations ${start + 1} to ${Math.min(end, allQuotations.length)}`);
}

function changePage(direction) {
  const totalPages = Math.ceil(allQuotations.length / itemsPerPage);
  currentPage = Math.max(1, Math.min(currentPage + direction, totalPages));
  console.log('Changed to page', currentPage);
  updatePagination();
  renderCurrentPage();
}

function goToPage(page) {
  currentPage = page;
  console.log('Go to page', currentPage);
  updatePagination();
  renderCurrentPage();
}

function changeItemsPerPage() {
  itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
  currentPage = 1;
  console.log('Items per page changed to', itemsPerPage);
  updatePagination();
  renderCurrentPage();
}

// Toggle checkbox visibility
function toggleCheckboxes() {
  checkboxesVisible = !checkboxesVisible;
  const checkboxColumns = document.querySelectorAll('.checkbox-column');
  checkboxColumns.forEach(col => {
    col.style.display = checkboxesVisible ? '' : 'none';
  });
  console.log('Checkboxes', checkboxesVisible ? 'shown' : 'hidden');
}

// Approve quotation with AJAX
function approveQuote(id) {
  if (confirm('Approve this quotation?\n\nThis will change the status to Approved and allow conversion to an order.')) {
    console.log('Approving quotation:', id);
    fetch('/api/quotations.php?action=approve', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
      console.log('Approve response:', data);
      if (data.success) {
        updateQuotationRow(id, 'approved');
      } else {
        console.error('Failed to approve:', data.message);
        alert('Error: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Network error:', error);
      alert('Network error: ' + error.message);
    });
  }
}

// Update quotation row without page refresh
function updateQuotationRow(id, newStatus) {
  const row = document.querySelector(`tr[data-quote-id="${id}"]`);
  if (!row) return;
  
  const statusCell = row.querySelector('.badge');
  if (statusCell) {
    statusCell.className = 'badge';
    const statusClasses = {
      'pending': 'badge-warning',
      'approved': 'badge-success',
      'rejected': 'badge-danger',
      'converted': 'badge-default'
    };
    statusCell.classList.add(statusClasses[newStatus] || 'badge-default');
    statusCell.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
  }
  
  row.setAttribute('data-status', newStatus);
  
  // Update action buttons
  updateActionButtons(row, newStatus);
  
  // Update data array
  const quoteIndex = allQuotations.findIndex(q => q.id === id);
  if (quoteIndex !== -1) {
    allQuotations[quoteIndex].status = newStatus;
  }
  
  // Update stats
  updateStats();
  
  console.log('Updated quotation', id, 'to status', newStatus);
}

// Update stats cards dynamically
function updateStats() {
  const stats = {
    total: allQuotations.length,
    pending: 0,
    approved: 0,
    rejected: 0,
    converted: 0,
    total_value: 0
  };
  
  allQuotations.forEach(q => {
    const status = q.status || 'pending';
    if (status === 'pending') stats.pending++;
    else if (status === 'approved') stats.approved++;
    else if (status === 'rejected') stats.rejected++;
    else if (status === 'converted') stats.converted++;
    
    stats.total_value += parseFloat(q.total) || 0;
  });
  
  // Update DOM
  document.getElementById('totalQuotesCount').textContent = stats.total.toLocaleString();
  document.getElementById('pendingCount').textContent = stats.pending.toLocaleString();
  document.getElementById('approvedCount').textContent = stats.approved.toLocaleString();
  document.getElementById('rejectedCount').textContent = stats.rejected.toLocaleString();
  document.getElementById('convertedCount').textContent = stats.converted.toLocaleString();
  
  // Format currency with proper symbol using settings
  const formattedValue = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currencyCode // Use currency code from settings
  }).format(stats.total_value);
  
  document.getElementById('totalValue').textContent = formattedValue;
  
  console.log('Total Value formatted:', formattedValue, 'Currency:', currencyCode);
  
  console.log('Stats updated:', stats);
}

// Table column sorting function
function sortTable(column) {
  // Toggle sort direction if same column, otherwise start with ascending
  if (currentSortColumn === column) {
    currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    currentSortColumn = column;
    currentSortDirection = 'asc';
  }
  
  // Sort the allQuotations array
  allQuotations.sort((a, b) => {
    let valueA, valueB;
    
    switch(column) {
      case 'quote_number':
        valueA = (a.quote_number || '').toLowerCase();
        valueB = (b.quote_number || '').toLowerCase();
        break;
        
      case 'customer':
        valueA = (a.customer || '').toLowerCase();
        valueB = (b.customer || '').toLowerCase();
        break;
        
      case 'date':
        valueA = new Date(a.date || 0);
        valueB = new Date(b.date || 0);
        break;
        
      case 'amount':
        valueA = parseFloat(a.total || 0);
        valueB = parseFloat(b.total || 0);
        break;
        
      case 'status':
        // Define status priority: pending > approved > rejected > converted
        const statusPriority = { 'pending': 1, 'approved': 2, 'rejected': 3, 'converted': 4 };
        valueA = statusPriority[a.status || 'pending'] || 999;
        valueB = statusPriority[b.status || 'pending'] || 999;
        break;
        
      default:
        return 0;
    }
    
    // Compare values
    let comparison = 0;
    if (valueA > valueB) comparison = 1;
    else if (valueA < valueB) comparison = -1;
    
    // Apply sort direction
    return currentSortDirection === 'asc' ? comparison : -comparison;
  });
  
  // Update sort indicators
  updateSortIndicators(column, currentSortDirection);
  
  // Refresh the table display
  currentPage = 1; // Reset to first page
  rebuildTable(); // Rebuild table with sorted data
  updatePagination();
  renderCurrentPage();
  
  // Console log notification
  const columnNames = {
    'quote_number': 'Quote #',
    'customer': 'Customer',
    'date': 'Date',
    'amount': 'Total Amount',
    'status': 'Status'
  };
  const directionText = currentSortDirection === 'asc' ? 'A→Z' : 'Z→A';
  console.log(`✓ Sorted by ${columnNames[column]} (${directionText})`);
  console.log(`Table sorted by ${column} (${currentSortDirection}) - ${allQuotations.length} quotations`);
}

// Update sort indicator icons
function updateSortIndicators(activeColumn, direction) {
  const columns = ['quote_number', 'customer', 'date', 'amount', 'status'];
  
  columns.forEach(col => {
    const icon = document.getElementById(`sort-${col}-icon`);
    if (!icon) return;
    
    if (col === activeColumn) {
      // Active column - show direction
      icon.style.opacity = '1';
      icon.style.color = '#111827'; // neutral gray-900
      
      if (direction === 'asc') {
        // Ascending - up arrow highlighted
        icon.innerHTML = '<path d="M7 15L12 20L17 15" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/><path d="M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>';
      } else {
        // Descending - down arrow highlighted
        icon.innerHTML = '<path d="M7 15L12 20L17 15" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/>';
      }
    } else {
      // Inactive columns - show both arrows faded
      icon.style.opacity = '0.4';
      icon.style.color = '#6b7280'; // neutral gray-500
      icon.innerHTML = '<path d="M7 15L12 20L17 15M7 9L12 4L17 9" stroke-linecap="round" stroke-linejoin="round"/>';
    }
  });
}

function updateActionButtons(row, status) {
  const actionsCell = row.querySelector('td:last-child');
  const buttonsContainer = actionsCell.querySelector('.flex');
  
  // Clear existing action buttons except view and more actions
  const viewBtn = buttonsContainer.querySelector('button:first-child');
  const moreBtn = buttonsContainer.querySelector('button:last-child');
  buttonsContainer.innerHTML = '';
  buttonsContainer.appendChild(viewBtn);
  
  // Add status-specific buttons
  if (status === 'pending') {
    const approveBtn = document.createElement('button');
    approveBtn.className = 'btn btn-ghost btn-sm text-success';
    approveBtn.title = 'Approve';
    approveBtn.onclick = () => approveQuote(row.getAttribute('data-quote-id'));
    approveBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    buttonsContainer.appendChild(approveBtn);
  } else if (status === 'approved') {
    const convertBtn = document.createElement('button');
    convertBtn.className = 'btn btn-ghost btn-sm text-primary';
    convertBtn.title = 'Convert to Order';
    convertBtn.onclick = () => convertToOrder(row.getAttribute('data-quote-id'));
    convertBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="2"/></svg>';
    buttonsContainer.appendChild(convertBtn);
  }
  
  buttonsContainer.appendChild(moreBtn);
}

// Show actions dropdown menu
function showQuoteActions(id, status) {
  // Stop event propagation to prevent conflicts
  if (event) {
    event.stopPropagation();
  }
  
  const dropdown = document.getElementById('actionsDropdown');
  
  // Check if dropdown is already open for this quotation
  const isOpen = dropdown.style.display === 'block' && currentQuoteId === id;
  
  if (isOpen) {
    // Close if already open
    closeDropdowns();
    console.log('Actions dropdown closed');
    return;
  }
  
  // Close any open dropdowns first
  closeDropdowns();
  
  // Set new quotation
  currentQuoteId = id;
  currentQuoteStatus = status;
  
  const button = event.target.closest('button');
  const rect = button.getBoundingClientRect();
  const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
  const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
  
  // Get dropdown dimensions
  dropdown.style.display = 'block';
  dropdown.style.visibility = 'hidden';
  const dropdownRect = dropdown.getBoundingClientRect();
  dropdown.style.visibility = 'visible';
  
  // Calculate viewport dimensions
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;
  
  // Calculate positions
  let top = rect.bottom + scrollTop + 5;
  let left = rect.left + scrollLeft - 150;
  
  // Check if dropdown goes below viewport (bottom of screen)
  if (rect.bottom + dropdownRect.height + 10 > viewportHeight) {
    // Position above the button instead
    top = rect.top + scrollTop - dropdownRect.height - 5;
  }
  
  // Check if dropdown goes beyond right edge
  if (left + dropdownRect.width > viewportWidth + scrollLeft) {
    left = viewportWidth + scrollLeft - dropdownRect.width - 20;
  }
  
  // Check if dropdown goes beyond left edge
  if (left < scrollLeft) {
    left = scrollLeft + 10;
  }
  
  // Position dropdown
  dropdown.style.top = top + 'px';
  dropdown.style.left = left + 'px';
  
  console.log('Actions dropdown opened for quotation:', id);
  
  // Hide void option if already voided
  const voidBtn = document.getElementById('actionVoid');
  if (status === 'rejected') {
    voidBtn.style.display = 'none';
  } else {
    voidBtn.style.display = 'flex';
  }
  
  // Close on outside click (delay to prevent immediate closure)
  setTimeout(() => {
    document.addEventListener('click', closeDropdowns, { once: true });
  }, 100);
}

// Handle individual quote actions
function handleQuoteAction(action) {
  closeDropdowns();
  
  switch(action) {
    case 'email':
      console.warn('Email functionality not implemented');
      alert('Email functionality requires SMTP configuration. Please contact your administrator.');
      break;
      
    case 'download':
      console.warn('PDF download not implemented');
      alert('PDF generation feature is not yet implemented. Please contact your administrator.');
      break;
      
    case 'duplicate':
      console.log('Duplicating quotation:', currentQuoteId);
      fetch('/api/quotations.php?action=duplicate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentQuoteId })
      })
      .then(response => response.json())
      .then(data => {
        console.log('Duplicate response:', data);
        if (data.success) {
          window.location.reload();
        } else {
          console.error('Failed to duplicate:', data.message);
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Network error:', error);
        alert('Network error: ' + error.message);
      });
      break;
      
    case 'void':
      if (confirm('Void this quotation?\n\nThis will mark the quotation as rejected and it cannot be converted to an order.')) {
        console.log('Voiding quotation:', currentQuoteId);
        fetch('/api/quotations.php?action=void', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: currentQuoteId })
        })
        .then(response => response.json())
        .then(data => {
          console.log('Void response:', data);
          if (data.success) {
            updateQuotationRow(currentQuoteId, 'rejected');
          } else {
            console.error('Failed to void:', data.message);
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Network error:', error);
          alert('Network error: ' + error.message);
        });
      }
      break;
      
    case 'delete':
      if (confirm('Delete this quotation?\n\nThis action cannot be undone.')) {
        console.log('Deleting quotation:', currentQuoteId);
        fetch('/api/quotations.php?action=delete', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: currentQuoteId })
        })
        .then(response => response.json())
        .then(data => {
          console.log('Delete response:', data);
          if (data.success) {
            allQuotations = allQuotations.filter(q => q.id !== currentQuoteId);
            originalQuotations = originalQuotations.filter(q => q.id !== currentQuoteId);
            updateStats();
            rebuildTable(); // Rebuild table after delete
            updatePagination();
            renderCurrentPage();
            console.log('Quotation deleted successfully');
          } else {
            console.error('Failed to delete:', data.message);
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Network error:', error);
          alert('Network error: ' + error.message);
        });
      }
      break;
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
  
  // Toggle behavior:
  // 1st click: Show checkboxes (if hidden)
  // 2nd click: If no selections, hide checkboxes. If selections exist, show dropdown menu
  // When dropdown open + click: Close dropdown and hide checkboxes
  
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
  const selectedCount = document.querySelectorAll('.quote-checkbox:checked').length;
  
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
  
  console.log('Bulk actions menu opened for', selectedCount, 'quotations');
  
  // Close on outside click
  setTimeout(() => {
    document.addEventListener('click', closeDropdowns, { once: true });
  }, 100);
}

// Handle bulk actions
function handleBulkAction(action) {
  const selected = document.querySelectorAll('.quote-checkbox:checked');
  const count = selected.length;
  
  closeDropdowns();
  
  const ids = Array.from(selected).map(cb => cb.value);
  
  switch(action) {
    case 'approve':
      if (confirm(`Approve ${count} selected quotation(s)?`)) {
        console.log('Bulk approving:', ids);
        fetch('/api/quotations.php?action=bulk_approve', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ids: ids })
        })
        .then(response => response.json())
        .then(data => {
          console.log('Bulk approve response:', data);
          if (data.success) {
            ids.forEach(id => updateQuotationRow(id, 'approved'));
            document.querySelectorAll('.quote-checkbox:checked').forEach(cb => cb.checked = false);
            console.log('Bulk approve completed');
          } else {
            console.error('Failed to bulk approve:', data.message);
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Network error:', error);
          alert('Network error: ' + error.message);
        });
      }
      break;
      
    case 'void':
      if (confirm(`Void ${count} selected quotation(s)?\n\nThis will mark them as rejected.`)) {
        console.log('Bulk voiding:', ids);
        fetch('/api/quotations.php?action=bulk_void', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ids: ids })
        })
        .then(response => response.json())
        .then(data => {
          console.log('Bulk void response:', data);
          if (data.success) {
            ids.forEach(id => updateQuotationRow(id, 'rejected'));
            document.querySelectorAll('.quote-checkbox:checked').forEach(cb => cb.checked = false);
            console.log('Bulk void completed');
          } else {
            console.error('Failed to bulk void:', data.message);
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Network error:', error);
          alert('Network error: ' + error.message);
        });
      }
      break;
      
    case 'email':
      console.warn('Email functionality not implemented');
      alert('Email functionality requires SMTP configuration. Please contact your administrator.');
      break;
      
    case 'export':
      console.log('Exporting', count, 'quotations');
      exportSelectedQuotations(ids);
      break;
      
    case 'delete':
      if (confirm(`Delete ${count} selected quotation(s)?\n\nThis action cannot be undone.`)) {
        console.log('Bulk deleting:', ids);
        fetch('/api/quotations.php?action=bulk_delete', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ids: ids })
        })
        .then(response => response.json())
        .then(data => {
          console.log('Bulk delete response:', data);
          if (data.success) {
            allQuotations = allQuotations.filter(q => !ids.includes(q.id));
            originalQuotations = originalQuotations.filter(q => !ids.includes(q.id));
            updateStats();
            rebuildTable(); // Rebuild table after bulk delete
            updatePagination();
            renderCurrentPage();
            console.log('Bulk delete completed');
          } else {
            console.error('Failed to bulk delete:', data.message);
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Network error:', error);
          alert('Network error: ' + error.message);
        });
      }
      break;
  }
}

// Close all dropdowns
function closeDropdowns() {
  const actionsDropdown = document.getElementById('actionsDropdown');
  const bulkDropdown = document.getElementById('bulkActionsDropdown');
  
  if (actionsDropdown) actionsDropdown.style.display = 'none';
  if (bulkDropdown) bulkDropdown.style.display = 'none';
  
  console.log('All dropdowns closed');
}

// Download PDF from view modal
function downloadQuotePDF() {
  console.warn('PDF generation not implemented');
  alert('PDF generation feature is not yet implemented. Please contact your administrator.');
}

// Export ALL quotations with sorting options
function exportSelectedQuotations(ids = null) {
  // If no IDs provided, export ALL quotations from all statuses
  const quotations = ids ? allQuotations.filter(q => ids.includes(q.id)) : allQuotations;
  
  if (quotations.length === 0) {
    console.error('No quotations to export');
    console.log('⚠ No quotations available for export');
    return;
  }
  
  // Prompt user for sorting preference
  const sortChoice = prompt(
    '📊 EXPORT SORTING\n' +
    '━━━━━━━━━━━━━━━━━━━━━━━━\n' +
    '1️⃣  Sort by Customer (A-Z)\n' +
    '2️⃣  Sort by Date (Newest First)\n' +
    '3️⃣  Sort by Total Amount (Highest First)\n' +
    '0️⃣  No Sorting (Current Order)\n' +
    '━━━━━━━━━━━━━━━━━━━━━━━━\n' +
    `📌 ${quotations.length} quotation(s) ready\n\n` +
    'Enter number (0-3):'
  );
  
  if (sortChoice === null) {
    console.log('ℹ Export cancelled');
    return;
  }
  
  // Apply sorting
  let sortedQuotations = [...quotations];
  const sortOption = parseInt(sortChoice.trim());
  
  switch(sortOption) {
    case 1: // Sort by Customer
      sortedQuotations.sort((a, b) => {
        const nameA = (a.customer || '').toLowerCase();
        const nameB = (b.customer || '').toLowerCase();
        return nameA.localeCompare(nameB);
      });
      console.log('✓ Export sorted by Customer (A-Z)');
      break;
      
    case 2: // Sort by Date (Newest First)
      sortedQuotations.sort((a, b) => {
        const dateA = new Date(a.date || 0);
        const dateB = new Date(b.date || 0);
        return dateB - dateA;
      });
      console.log('✓ Export sorted by Date (Newest First)');
      break;
      
    case 3: // Sort by Total Amount (Highest First)
      sortedQuotations.sort((a, b) => {
        const totalA = parseFloat(a.total || 0);
        const totalB = parseFloat(b.total || 0);
        return totalB - totalA;
      });
      console.log('✓ Export sorted by Amount (Highest First)');
      break;
      
    case 0: // No sorting
      console.log('ℹ No sorting applied');
      break;
      
    default:
      console.log('❌ Invalid sorting option');
      return;
  }
  
  // Prompt user for export format
  const formatChoice = prompt(
    '📄 SELECT EXPORT FORMAT\n' +
    '━━━━━━━━━━━━━━━━━━━━━━━━\n' +
    '1️⃣  CSV Format (.csv)\n' +
    '    ⚠️ Excel auto-sizes columns (no fixed widths)\n' +
    '\n' +
    '2️⃣  Excel Format (.xlsx) ⭐ RECOMMENDED\n' +
    '    ✅ Fixed column widths\n' +
    '    ✅ Professional formatting\n' +
    '    ✅ Colors & formulas\n' +
    '\n' +
    '0️⃣  Cancel Export\n' +
    '━━━━━━━━━━━━━━━━━━━━━━━━\n' +
    `📌 ${sortedQuotations.length} quotation(s)\n\n` +
    'Enter number (0-2):'
  );
  
  // Validate input
  if (formatChoice === null || formatChoice.trim() === '') {
    console.log('Export cancelled by user');
    return;
  }
  
  const choice = parseInt(formatChoice.trim());
  
  switch(choice) {
    case 0:
      console.log('ℹ Export cancelled by user');
      return;
      
    case 1:
      console.log(`📄 Exporting ${sortedQuotations.length} quotations to CSV`);
      console.log('ℹ Preparing CSV export...');
      exportToCSV(sortedQuotations);
      break;
      
    case 2:
      console.log(`📊 Exporting ${sortedQuotations.length} quotations to XLSX`);
      console.log('ℹ Preparing Excel export...');
      exportToXLSX(sortedQuotations);
      break;
      
    default:
      console.log('❌ Invalid selection. Please enter 0, 1, or 2');
      console.error('Invalid format choice:', formatChoice);
  }
}

// Enhanced CSV Export with professional formatting
// Note: CSV format doesn't support fixed-width columns when opened in Excel.
// Use XLSX export for precise column width control.
function exportToCSV(quotations) {
  const now = new Date();
  
  // Format timestamp with timezone awareness
  const timestamp = now.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
    timeZoneName: 'short'
  });
  const dateStr = now.toISOString().slice(0, 10);
  
  // Calculate summary statistics
  const stats = calculateExportStats(quotations);
  
  // Build CSV with header, data, and summary
  let csv = [];
  
  // Title Section (full width)
  csv.push(['SALES QUOTATIONS EXPORT REPORT', '', '', '', '', '', '', '', '', '']);
  csv.push(['Generated:', timestamp, '', '', '', '', '', '', '', '']);
  csv.push(['Timezone:', userTimezone, '', '', '', '', '', '', '', '']);
  csv.push(['Total Records:', quotations.length, '', '', '', '', '', '', '', '']);
  csv.push(['Currency:', `${currencySymbol} (${currencyCode})`, '', '', '', '', '', '', '', '']);
  csv.push(['', '', '', '', '', '', '', '', '', '']);
  
  // Summary Statistics (aligned columns)
  csv.push(['SUMMARY STATISTICS', '', '', '', 'FINANCIAL SUMMARY', '', '', '', '', '']);
  csv.push(['Total Quotations:', stats.total, '', '', 'Total Value:', formatCurrency(stats.totalValue), '', '', '', '']);
  csv.push(['Pending:', stats.pending, stats.pendingPercent + '%', '', 'Approved Value:', formatCurrency(stats.approvedValue), '', '', '', '']);
  csv.push(['Approved:', stats.approved, stats.approvedPercent + '%', '', 'Pending Value:', formatCurrency(stats.pendingValue), '', '', '', '']);
  csv.push(['Rejected:', stats.rejected, stats.rejectedPercent + '%', '', 'Rejected Loss:', formatCurrency(stats.rejectedValue), '', '', '', '']);
  csv.push(['Converted:', stats.converted, stats.convertedPercent + '%', '', 'Net Realizable:', formatCurrency(stats.netValue), '', '', '', '']);
  csv.push(['', '', '', '', '', '', '', '', '', '']);
  csv.push(['', '', '', '', '', '', '', '', '', '']);
  
  // Column Headers
  csv.push([
    'Quote #',
    'Customer',
    'Company',
    'Email',
    'Phone',
    'Date',
    'Validity (Days)',
    'Status',
    'Amount',
    'Currency'
  ]);
  
  // Data Rows
  quotations.forEach(q => {
    // Safe date formatting
    let formattedDate = 'N/A';
    if (q.date) {
      try {
        const dateObj = new Date(q.date);
        if (!isNaN(dateObj.getTime())) {
          formattedDate = dateObj.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: '2-digit' 
          });
        }
      } catch (e) {
        formattedDate = q.date; // Use original if parsing fails
      }
    }
    
    // Safe number formatting
    const amount = parseFloat(q.total || 0);
    const formattedAmount = isNaN(amount) ? '0.00' : amount.toFixed(2);
    
    // Add data row (Excel will auto-size columns)
    csv.push([
      q.quote_number || 'N/A',
      q.customer || 'N/A',
      q.customer_company || '',
      q.customer_email || '',
      q.customer_phone || '',
      formattedDate,
      q.validity_days || '30',
      (q.status || 'pending').toUpperCase(),
      formattedAmount,
      currencySymbol
    ]);
  });
  
  // Footer (maintain 10 columns)
  csv.push(['', '', '', '', '', '', '', '', '', '']);
  csv.push(['', '', '', '', '', '', '', '', '', '']);
  csv.push(['Report End', '', '', '', '', '', '', '', '', '']);
  csv.push(['Exported by:', '<?php echo htmlspecialchars($user["username"] ?? "System"); ?>', '', '', '', '', '', '', '', '']);
  
  // Convert to CSV string
  const csvContent = csv.map(row => 
    row.map(cell => {
      const str = String(cell || '');
      // Escape quotes and wrap in quotes if contains comma, quote, or newline
      if (str.includes(',') || str.includes('"') || str.includes('\n')) {
        return '"' + str.replace(/"/g, '""') + '"';
      }
      return str;
    }).join(',')
  ).join('\r\n');
  
  // Create and download
  const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  
  link.setAttribute('href', url);
  link.setAttribute('download', `Quotations_Export_${dateStr}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
  
  console.log(`✅ CSV exported successfully! ${quotations.length} quotations`);
  console.log('✓ CSV export completed:', quotations.length, 'quotations');
}

// Professional XLSX Export with formatting and formulas
function exportToXLSX(quotations) {
  // Send data to server-side PHP script for Excel generation with timezone/currency settings
  const exportData = {
    quotations: quotations,
    stats: calculateExportStats(quotations),
    user: '<?php echo htmlspecialchars($user["username"] ?? "System"); ?>',
    currency: currencySymbol,
    currencyCode: currencyCode,
    timezone: userTimezone,
    timestamp: new Date().toISOString()
  };
  
  // Create form and submit
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '/api/export-quotations.php';
  form.target = '_blank';
  
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'data';
  input.value = JSON.stringify(exportData);
  
  form.appendChild(input);
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
  
  setTimeout(() => {
    Toast.success(`Excel file generated! ${quotations.length} quotations`);
  }, 500);
  
  console.log('XLSX export initiated:', quotations.length, 'quotations');
}

// Calculate statistics for export
function calculateExportStats(quotations) {
  const stats = {
    total: quotations.length,
    pending: 0,
    approved: 0,
    rejected: 0,
    converted: 0,
    totalValue: 0,
    pendingValue: 0,
    approvedValue: 0,
    rejectedValue: 0,
    convertedValue: 0
  };
  
  quotations.forEach(q => {
    const status = (q.status || 'pending').toLowerCase();
    const amount = parseFloat(q.total || 0);
    
    stats.totalValue += amount;
    
    switch(status) {
      case 'pending':
        stats.pending++;
        stats.pendingValue += amount;
        break;
      case 'approved':
        stats.approved++;
        stats.approvedValue += amount;
        break;
      case 'rejected':
        stats.rejected++;
        stats.rejectedValue += amount;
        break;
      case 'converted':
        stats.converted++;
        stats.convertedValue += amount;
        break;
    }
  });
  
  // Calculate percentages (safe division)
  const total = stats.total || 1; // Avoid division by zero
  stats.pendingPercent = ((stats.pending / total) * 100).toFixed(1);
  stats.approvedPercent = ((stats.approved / total) * 100).toFixed(1);
  stats.rejectedPercent = ((stats.rejected / total) * 100).toFixed(1);
  stats.convertedPercent = ((stats.converted / total) * 100).toFixed(1);
  
  // Net realizable = approved value
  stats.netValue = stats.approvedValue;
  
  return stats;
}

// Helper function to format currency
function formatCurrency(amount) {
  const num = parseFloat(amount);
  if (isNaN(num) || num === null || num === undefined) {
    return currencySymbol + '0.00';
  }
  return currencySymbol + num.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

// Export ALL quotations (from all statuses, not just visible/filtered)
function exportQuotes() {
  if (allQuotations.length === 0) {
    console.error('No quotations available');
    console.log('⚠ No quotations available to export');
    return;
  }
  
  console.log('📊 Exporting ALL quotations:', allQuotations.length);
  console.log(`ℹ Preparing to export ${allQuotations.length} quotation(s) from all statuses...`);
  
  // Call with no IDs to export ALL quotations
  exportSelectedQuotations(null);
}

// Search functionality with pagination support
let searchTimeout;
document.getElementById('quote-search')?.addEventListener('input', function(e) {
  const searchTerm = e.target.value.toLowerCase().trim();
  
  // Debounce search for better performance
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    console.log('Searching for:', searchTerm);
    
    // Apply search on original data, then apply current status filter
    let filtered = originalQuotations;
    
    // Apply search term
    if (searchTerm) {
      filtered = filtered.filter(q => {
        const searchableText = [
          q.quote_number || '',
          q.customer || '',
          q.date || '',
          q.total || '',
          q.status || ''
        ].join(' ').toLowerCase();
        
        return searchableText.includes(searchTerm);
      });
    }
    
    // Apply status filter on top of search results
    if (currentStatusFilter !== 'all') {
      filtered = filtered.filter(q => {
        const quoteStatus = (q.status || 'pending').toLowerCase();
        return quoteStatus === currentStatusFilter.toLowerCase();
      });
    }
    
    allQuotations = filtered;
    currentPage = 1;
    rebuildTable(); // Rebuild table with search results
    updatePagination();
    renderCurrentPage();
    
    console.log(`Search results: ${allQuotations.length} quotations`);
  }, 300); // 300ms debounce
});

// Filter quotations by status with proper pagination
function filterQuotationsByStatus(status) {
  console.log('Filtering by status:', status);
  currentStatusFilter = status;
  
  let filtered = originalQuotations;
  
  // Apply search term first (if any)
  const searchInput = document.getElementById('quote-search');
  const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
  
  if (searchTerm) {
    filtered = filtered.filter(q => {
      const searchableText = [
        q.quote_number || '',
        q.customer || '',
        q.date || '',
        q.total || '',
        q.status || ''
      ].join(' ').toLowerCase();
      
      return searchableText.includes(searchTerm);
    });
  }
  
  // Apply status filter
  if (status !== 'all') {
    filtered = filtered.filter(q => {
      const quoteStatus = (q.status || 'pending').toLowerCase();
      return quoteStatus === status.toLowerCase();
    });
  }
  
  allQuotations = filtered;
  
  // Reset to page 1 and update pagination
  currentPage = 1;
  updateStats(); // Update stats cards
  rebuildTable(); // Rebuild table from filtered data
  updatePagination(); // Recalculate pagination
  renderCurrentPage(); // Show filtered data
  
  console.log(`Filtered: ${allQuotations.length} of ${originalQuotations.length} quotations (Status: ${status})`);
}

// Filter by status
document.getElementById('status-filter')?.addEventListener('change', function(e) {
  const status = e.target.value;
  filterQuotationsByStatus(status);
});

// ============================================
// APPLY NUMBER FORMAT API TO CURRENCY VALUES
// ============================================
window.addEventListener('load', function() {
  const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';
  
  // Auto-apply formatting to quotation values
  NumberFormat.autoApply(currencySymbol, {
    customSelectors: [
      { selector: '#totalValue', maxWidth: 1 },  // Stat card - always abbreviate >= 1M
      { selector: 'td.font-semibold', maxWidth: 80 }  // Table total column
    ]
  });
});
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
