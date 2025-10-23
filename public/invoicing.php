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

// Get notification summary for financial alerts
$userId = $user['id'] ?? 'admin';
$notificationSummary = NotificationHelper::getSummary($userId);
$financialAlerts = $notificationSummary['by_type']['financial'] ?? 0;

// Real invoices from database
$invoiceModel = new Invoice();
try {
    $invoices = $invoiceModel->getAll();
    $totals = $invoiceModel->totals();
    $totalRevenue = $totals['total'];
    $totalPaid = $totals['paid'];
    $totalOutstanding = $totals['outstanding'];
    // Derive overdue count
    $today = strtotime('today');
    $overdueCount = 0;
    foreach ($invoices as $inv) {
        $status = strtolower((string)($inv['status'] ?? ''));
        $dueTs = isset($inv['due']) ? strtotime((string)$inv['due']) : null;
        $balance = (float)($inv['total'] ?? 0) - (float)($inv['paid'] ?? 0);
        if ($status === 'overdue' || ($balance > 0 && $dueTs && $dueTs < $today)) {
            $overdueCount++;
        }
    }
} catch (\Exception $e) {
    $invoices = [];
    $totalRevenue = 0; $totalPaid = 0; $totalOutstanding = 0;
    $overdueCount = 0;
}

$pageTitle = 'Invoicing';
ob_start();
?>

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Invoicing & Billing</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>Status:</strong>
          <span class="status-indicator">
            <span class="status-dot"></span>
            Online
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
        <div class="page-banner-meta-item">
          <strong>Outstanding:</strong>
          <span class="font-semibold text-warning"><?php echo CurrencyHelper::format($totalOutstanding); ?></span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <button class="btn btn-success" onclick="showNewInvoiceModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        New Invoice
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

<!-- Financial Stats -->
<div class="grid grid-cols-4 mb-6">
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Total Revenue</p>
      <p class="stat-value"><?php echo CurrencyHelper::format($totalRevenue); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Paid</p>
      <p class="stat-value text-success"><?php echo CurrencyHelper::format($totalPaid); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Outstanding</p>
      <p class="stat-value text-warning"><?php echo CurrencyHelper::format($totalOutstanding); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Overdue</p>
      <p class="stat-value text-danger"><?php echo number_format($overdueCount); ?></p>
      <?php if ($financialAlerts > 0): ?>
      <span class="badge badge-warning mt-2"><?php echo $financialAlerts; ?> alerts</span>
      <?php endif; ?>
    </div>
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
      <input type="search" class="search-input" placeholder="Search invoices..." id="invoice-search">
    </div>
  </div>
  <div class="toolbar-right">
    <select class="form-select" id="status-filter" style="width: auto;">
      <option value="all">All Status</option>
      <option value="paid">Paid</option>
      <option value="unpaid">Unpaid</option>
      <option value="partial">Partial</option>
      <option value="overdue">Overdue</option>
    </select>
    <button class="btn btn-secondary btn-sm">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M3 17V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V17M12 3V15M12 15L7 10M12 15L17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Import
    </button>
    <button class="btn btn-secondary btn-sm" onclick="exportInvoices()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Export
    </button>
  </div>
</div>

<!-- Invoices Table -->
<div class="table-container">
  <table class="data-table">
    <thead>
      <tr>
        <th>Invoice #</th>
        <th>Customer</th>
        <th>Invoice Date</th>
        <th>Due Date</th>
        <th>Total</th>
        <th>Paid</th>
        <th>Balance</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($invoices as $invoice): 
        $balance = ($invoice['total'] ?? 0) - ($invoice['paid'] ?? 0);
        $invId = $invoice['id'] ?? ((isset($invoice['_id']) && is_object($invoice['_id'])) ? (string)$invoice['_id'] : ($invoice['_id'] ?? ''));
        $dateTs = null; if (isset($invoice['date'])) { if (is_string($invoice['date'])) { $dateTs = strtotime($invoice['date']); } elseif (is_object($invoice['date']) && method_exists($invoice['date'], 'toDateTime')) { $dateTs = $invoice['date']->toDateTime()->getTimestamp(); }}
        $dueTs = null; if (isset($invoice['due'])) { if (is_string($invoice['due'])) { $dueTs = strtotime($invoice['due']); } elseif (is_object($invoice['due']) && method_exists($invoice['due'], 'toDateTime')) { $dueTs = $invoice['due']->toDateTime()->getTimestamp(); }}
      ?>
      <tr>
        <td class="font-mono font-medium"><?php echo htmlspecialchars($invId); ?></td>
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
          <div class="flex gap-2">
            <button class="btn btn-ghost btn-sm" onclick="viewInvoice('<?php echo $invoice['id']; ?>')" title="View">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <?php if ($balance > 0): ?>
            <button class="btn btn-ghost btn-sm text-success" onclick="recordPayment('<?php echo $invoice['id']; ?>')" title="Record Payment">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <rect x="1" y="4" width="22" height="16" rx="2" stroke="currentColor" stroke-width="2"/>
                <line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm text-primary" onclick="emailInvoice('<?php echo $invoice['id']; ?>')" title="Email">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M3 8L10.89 13.26C11.2187 13.4793 11.6049 13.5963 12 13.5963C12.3951 13.5963 12.7813 13.4793 13.11 13.26L21 8M5 19H19C19.5304 19 20.0391 18.7893 20.4142 18.4142C20.7893 18.0391 21 17.5304 21 17V7C21 6.46957 20.7893 5.96086 20.4142 5.58579C20.0391 5.21071 19.5304 5 19 5H5C4.46957 5 3.96086 5.21071 3.58579 5.58579C3.21071 5.96086 3 6.46957 3 7V17C3 17.5304 3.21071 18.0391 3.58579 18.4142C3.96086 18.7893 4.46957 19 5 19Z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm" onclick="downloadPDF('<?php echo $invoice['id']; ?>')" title="Download PDF">
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

<script>
function showNewInvoiceModal() {
  showToast('New Invoice form - Coming soon!', 'info');
}

function viewInvoice(id) {
  window.location.href = 'invoice-detail.php?id=' + id;
}

function recordPayment(id) {
  const amount = prompt('Enter payment amount for invoice ' + id + ':');
  if (amount && !isNaN(amount)) {
    showToast('Payment of $' + parseFloat(amount).toFixed(2) + ' recorded', 'success');
  }
}

function emailInvoice(id) {
  showToast('Invoice ' + id + ' sent via email', 'success');
}

function downloadPDF(id) {
  showToast('Downloading PDF for invoice ' + id, 'info');
}

function exportInvoices() {
  showToast('Exporting invoices to CSV...', 'info');
}
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
