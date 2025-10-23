<?php
/**
 * BIR Compliance Module
 * Philippine Bureau of Internal Revenue compliance and form generation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Service\BIRService;
use App\Model\BirForm;
use App\Helper\NotificationHelper;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Get notification summary for BIR alerts
$userId = $user['id'] ?? 'admin';
$notificationSummary = NotificationHelper::getSummary($userId);
$birAlerts = $notificationSummary['by_type']['bir'] ?? 0;

// Real BIR forms from database
$birModel = new BirForm();
try {
    $recentForms = $birModel->getRecentForms(50);
} catch (\Exception $e) {
    $recentForms = [];
}

// Determine next filing date if available from records (by 'due_date' if present)
$nextFiling = null;
foreach ($recentForms as $f) {
    if (!empty($f['due_date'])) {
        $ts = is_string($f['due_date']) ? strtotime($f['due_date']) : (is_object($f['due_date']) && method_exists($f['due_date'],'toDateTime') ? $f['due_date']->toDateTime()->getTimestamp() : null);
        if ($ts && $ts >= strtotime('today') && ($nextFiling === null || $ts < $nextFiling)) {
            $nextFiling = $ts;
        }
    }
}

$pageTitle = 'BIR Compliance';
ob_start();
?>

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">BIR Compliance</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>Status:</strong>
          <span class="status-indicator">
            <span class="status-dot"></span>
            Compliant
          </span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Currency:</strong>
          <span class="font-medium"><?php echo CurrencyHelper::symbol() . ' ' . CurrencyHelper::getCurrentCurrency(); ?></span>
        </div>
        <?php if ($birAlerts > 0): ?>
        <div class="page-banner-meta-item">
          <strong>Active Alerts:</strong>
          <span class="badge badge-warning"><?php echo number_format($birAlerts); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($nextFiling): ?>
        <div class="page-banner-meta-item">
          <strong>Next Filing:</strong>
          <span class="text-warning"><?php echo date('F j, Y', $nextFiling); ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="page-banner-actions">
      <button class="btn btn-secondary" onclick="showToast('eFPS Integration pending', 'info')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
          <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        File via eFPS
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

<!-- BIR Forms Quick Access -->
<div class="section">
  <h2 class="section-title">Generate BIR Forms</h2>
  <div class="grid grid-cols-3" style="gap: 1.5rem;">
    
    <!-- RAMSAY 307 -->
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="padding: 0.75rem; background: rgba(37, 99, 235, 0.1); border-radius: var(--radius-md);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb">
              <path d="M21 16V8C21 6.89543 20.1046 6 19 6H5C3.89543 6 3 6.89543 3 8V16C3 17.1046 3.89543 18 5 18H19C20.1046 18 21 17.1046 21 16Z" stroke-width="2"/>
              <path d="M3 10H21" stroke-width="2"/>
            </svg>
          </div>
          <div style="flex: 1;">
            <h3 class="card-title" style="margin-bottom: 0.25rem;">RAMSAY 307</h3>
            <p class="text-sm text-secondary">Import Duties Declaration</p>
          </div>
        </div>
        <p class="text-sm text-secondary" style="margin-bottom: 1.5rem; line-height: 1.6;">
          Auto-generate from container/shipment data with customs duties calculation.
        </p>
        <button class="btn btn-primary w-full" onclick="showToast('RAMSAY 307 form ready', 'info')">
          Generate Form
        </button>
      </div>
    </div>

    <!-- VAT Returns -->
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="padding: 0.75rem; background: rgba(34, 197, 94, 0.1); border-radius: var(--radius-md);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e">
              <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke-width="2"/>
              <polyline points="14 2 14 8 20 8" stroke-width="2"/>
            </svg>
          </div>
          <div style="flex: 1;">
            <h3 class="card-title" style="margin-bottom: 0.25rem;">VAT Returns</h3>
            <p class="text-sm text-secondary">2550M / 2550Q</p>
          </div>
        </div>
        <p class="text-sm text-secondary" style="margin-bottom: 1.5rem; line-height: 1.6;">
          Monthly and quarterly VAT returns auto-computed from sales and purchases.
        </p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
          <button class="btn btn-secondary btn-sm" onclick="showToast('VAT Monthly form ready', 'info')">Monthly</button>
          <button class="btn btn-secondary btn-sm" onclick="showToast('VAT Quarterly form ready', 'info')">Quarterly</button>
        </div>
      </div>
    </div>

    <!-- Withholding Tax -->
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="padding: 0.75rem; background: rgba(245, 158, 11, 0.1); border-radius: var(--radius-md);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b">
              <rect x="2" y="7" width="20" height="14" rx="2" stroke-width="2"/>
              <path d="M16 21V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V21" stroke-width="2"/>
            </svg>
          </div>
          <div style="flex: 1;">
            <h3 class="card-title" style="margin-bottom: 0.25rem;">Withholding Tax</h3>
            <p class="text-sm text-secondary">1601C / 2307</p>
          </div>
        </div>
        <p class="text-sm text-secondary" style="margin-bottom: 1.5rem; line-height: 1.6;">
          Expanded withholding tax returns and certificates auto-generated from payments.
        </p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
          <button class="btn btn-secondary btn-sm" onclick="showToast('Form 1601C ready', 'info')">1601C</button>
          <button class="btn btn-secondary btn-sm" onclick="showToast('Form 2307 ready', 'info')">2307</button>
        </div>
      </div>
    </div>

    <!-- Annual ITR -->
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="padding: 0.75rem; background: rgba(168, 85, 247, 0.1); border-radius: var(--radius-md);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#a855f7">
              <path d="M9 12H15M9 16H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L18.7071 8.70711C18.8946 8.89464 19 9.149 19 9.41421V19C19 20.1046 18.1046 21 17 21Z" stroke-width="2"/>
            </svg>
          </div>
          <div style="flex: 1;">
            <h3 class="card-title" style="margin-bottom: 0.25rem;">Annual ITR</h3>
            <p class="text-sm text-secondary">Form 1702</p>
          </div>
        </div>
        <p class="text-sm text-secondary" style="margin-bottom: 1.5rem; line-height: 1.6;">
          Annual Income Tax Return auto-populated from financial statements.
        </p>
        <button class="btn btn-primary w-full" onclick="showToast('ITR form ready', 'info')">
          Generate ITR
        </button>
      </div>
    </div>

    <!-- Percentage Tax -->
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="padding: 0.75rem; background: rgba(239, 68, 68, 0.1); border-radius: var(--radius-md);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ef4444">
              <circle cx="12" cy="12" r="10" stroke-width="2"/>
              <path d="M15 9L9 15M9 9H9.01M15 15H15.01" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div style="flex: 1;">
            <h3 class="card-title" style="margin-bottom: 0.25rem;">Percentage Tax</h3>
            <p class="text-sm text-secondary">Non-VAT Transactions</p>
          </div>
        </div>
        <p class="text-sm text-secondary" style="margin-bottom: 1.5rem; line-height: 1.6;">
          For non-VAT sales and transactions subject to percentage tax.
        </p>
        <button class="btn btn-secondary w-full" onclick="showToast('Percentage tax form ready', 'info')">
          Generate Form
        </button>
      </div>
    </div>

    <!-- Help & Guide -->
    <div class="card" style="background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%); border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 8px 32px rgba(0,0,0,0.4); color: white;">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem;">
          <div style="padding: 0.75rem; background: rgba(255,255,255,0.1); border-radius: var(--radius-md); backdrop-filter: blur(10px);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="12" cy="12" r="10" stroke-width="2"/>
              <path d="M12 16V12M12 8H12.01" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <div style="flex: 1;">
            <h3 class="card-title" style="margin-bottom: 0.25rem; color: white; font-weight: 600;">Need Help?</h3>
            <p class="text-sm" style="color: rgba(255,255,255,0.7);">BIR Filing Guide</p>
          </div>
        </div>
        <p class="text-sm" style="margin-bottom: 1.5rem; color: rgba(255,255,255,0.8); line-height: 1.6;">
          Step-by-step guide for BIR form generation and electronic filing via eFPS.
        </p>
        <button class="btn w-full" onclick="showToast('Opening BIR guide...', 'info')" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); color: white; backdrop-filter: blur(10px); transition: all 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
          View Guide
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Recent Forms Table -->
<div class="section" style="margin-top: 3rem;">
  <h2 class="section-title">Recent BIR Forms</h2>
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>Form Type</th>
          <th>Period</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Date Filed</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentForms as $form): ?>
        <tr>
          <td class="font-mono font-medium"><?php echo htmlspecialchars($form['form_type']); ?></td>
          <td><?php echo htmlspecialchars($form['period']); ?></td>
          <td class="font-semibold"><?php echo CurrencyHelper::format($form['amount']); ?></td>
          <td>
            <?php
            $statusClass = $form['status'] === 'filed' ? 'badge-success' : 'badge-warning';
            ?>
            <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($form['status']); ?></span>
          </td>
          <td>
            <?php 
            $ts = null;
            if (isset($form['date'])) {
              if (is_string($form['date'])) { $ts = strtotime($form['date']); }
              elseif (is_object($form['date']) && method_exists($form['date'], 'toDateTime')) { $ts = $form['date']->toDateTime()->getTimestamp(); }
            }
            echo $ts ? date('M d, Y', $ts) : '-';
            ?>
          </td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-sm" onclick="showToast('Viewing form...', 'info')" title="View">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                </svg>
              </button>
              <button class="btn btn-ghost btn-sm" onclick="showToast('Downloading PDF...', 'info')" title="Download PDF">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 10L12 15M12 15L7 10M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- BIR Filing Calendar -->
<div class="section" style="margin-top: 3rem;">
  <h2 class="section-title">Upcoming Filing Deadlines</h2>
  <div class="grid grid-cols-3" style="gap: 1.5rem;">
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
          <span class="badge badge-warning">Due Soon</span>
          <span class="text-sm text-secondary">November 15</span>
        </div>
        <h4 class="font-semibold" style="margin-bottom: 0.5rem;">VAT Return (2550M)</h4>
        <p class="text-sm text-secondary" style="line-height: 1.5;">October 2025 monthly VAT filing</p>
      </div>
    </div>
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
          <span class="badge badge-default">Upcoming</span>
          <span class="text-sm text-secondary">November 20</span>
        </div>
        <h4 class="font-semibold" style="margin-bottom: 0.5rem;">Withholding Tax (1601C)</h4>
        <p class="text-sm text-secondary" style="line-height: 1.5;">October 2025 withholding tax return</p>
      </div>
    </div>
    <div class="card">
      <div class="card-content" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
          <span class="badge badge-default">Upcoming</span>
          <span class="text-sm text-secondary">January 15, 2026</span>
        </div>
        <h4 class="font-semibold" style="margin-bottom: 0.5rem;">Quarterly VAT (2550Q)</h4>
        <p class="text-sm text-secondary" style="line-height: 1.5;">Q4 2025 quarterly VAT filing</p>
      </div>
    </div>
  </div>
</div>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
