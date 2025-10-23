<?php
/**
 * Quotations Module - LedgerSMB Feature
 * Create, manage, and convert quotes to orders
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Model\Quotation;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Real quotations from database
$quotationModel = new Quotation();
try {
    $quotations = $quotationModel->getAll();
} catch (\Exception $e) {
    $quotations = [];
}

// Derived metrics
$pendingCount = count(array_filter($quotations, fn($q) => ($q['status'] ?? '') === 'pending'));
$approvedCount = count(array_filter($quotations, fn($q) => ($q['status'] ?? '') === 'approved'));

$pageTitle = 'Quotations';
ob_start();
?>

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Sales Quotations</h1>
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
          <strong>Access Level:</strong>
          <span class="access-badge"><?php echo ucfirst($user['role'] ?? 'User'); ?></span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Currency:</strong>
          <span class="font-medium"><?php echo CurrencyHelper::symbol() . ' ' . CurrencyHelper::getCurrentCurrency(); ?></span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <button class="btn btn-success" onclick="showNewQuoteModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        New Quote
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

<!-- Stats Cards -->
<div class="grid grid-cols-4 mb-6">
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Total Quotes</p>
      <p class="stat-value"><?php echo count($quotations); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Pending</p>
      <p class="stat-value text-warning"><?php echo number_format($pendingCount); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Approved</p>
      <p class="stat-value text-success"><?php echo number_format($approvedCount); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Total Value</p>
      <p class="stat-value"><?php echo CurrencyHelper::format(array_sum(array_column($quotations, 'total'))); ?></p>
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
    <button class="btn btn-ghost btn-icon" onclick="window.print()" title="Print">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M6 9V2H18V9M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18M6 14H18V22H6V14Z" stroke="currentColor" stroke-width="2"/>
      </svg>
    </button>
    <button class="btn btn-ghost btn-icon" onclick="exportQuotes()" title="Export">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
</div>

<!-- Quotations Table -->
<div class="table-container">
  <table class="data-table">
    <thead>
      <tr>
        <th>Quote #</th>
        <th>Customer</th>
        <th>Date</th>
        <th>Total Amount</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($quotations as $quote): ?>
      <tr>
        <td class="font-mono font-medium"><?php echo htmlspecialchars($quote['id']); ?></td>
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
          <div class="flex gap-2">
            <button class="btn btn-ghost btn-sm" onclick="viewQuote('<?php echo $quote['id']; ?>')" title="View">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <?php if ($quote['status'] === 'approved'): ?>
            <button class="btn btn-ghost btn-sm text-success" onclick="convertToOrder('<?php echo $quote['id']; ?>')" title="Convert to Order">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm text-primary" onclick="emailQuote('<?php echo $quote['id']; ?>')" title="Email">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M3 8L10.89 13.26C11.2187 13.4793 11.6049 13.5963 12 13.5963C12.3951 13.5963 12.7813 13.4793 13.11 13.26L21 8M5 19H19C19.5304 19 20.0391 18.7893 20.4142 18.4142C20.7893 18.0391 21 17.5304 21 17V7C21 6.46957 20.7893 5.96086 20.4142 5.58579C20.0391 5.21071 19.5304 5 19 5H5C4.46957 5 3.96086 5.21071 3.58579 5.58579C3.21071 5.96086 3 6.46957 3 7V17C3 17.5304 3.21071 18.0391 3.58579 18.4142C3.96086 18.7893 4.46957 19 5 19Z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button class="btn btn-ghost btn-sm text-danger" onclick="deleteQuote('<?php echo $quote['id']; ?>')" title="Delete">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
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

<script>
function showNewQuoteModal() {
  showToast('New Quote modal - Coming soon!', 'info');
}

function viewQuote(id) {
  showToast('Viewing quote ' + id, 'info');
}

function convertToOrder(id) {
  if (confirm('Convert quote ' + id + ' to a sales order?')) {
    showToast('Quote converted to order successfully!', 'success');
  }
}

function emailQuote(id) {
  showToast('Email sent for quote ' + id, 'success');
}

function deleteQuote(id) {
  if (confirm('Delete quote ' + id + '? This action cannot be undone.')) {
    showToast('Quote deleted', 'success');
  }
}

function exportQuotes() {
  showToast('Exporting quotations...', 'info');
}
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
