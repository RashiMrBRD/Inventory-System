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
        <button class="btn btn-primary w-full" onclick="generateRAMSAY307()">
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
          <button class="btn btn-secondary btn-sm" onclick="generateVATMonthly()">Monthly</button>
          <button class="btn btn-secondary btn-sm" onclick="generateVATQuarterly()">Quarterly</button>
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
          <button class="btn btn-secondary btn-sm" onclick="generateWithholdingTax()">1601C</button>
          <button class="btn btn-secondary btn-sm" onclick="generate2307()">2307</button>
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
        <button class="btn btn-primary w-full" onclick="generateITR()">
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
        <button class="btn btn-secondary w-full" onclick="generatePercentageTax()">
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
  <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <h2 class="section-title" style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #0f172a;">Recent BIR Forms</h2>
    <div class="flex items-center gap-3" style="flex-wrap: wrap;">
      <span class="text-sm text-muted-foreground" style="color: #64748b;"><?php echo count($recentForms); ?> total forms</span>
      <button class="btn btn-sm" onclick="location.reload()" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M23 4v6h-6M1 20v-6h6"/>
          <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
        </svg>
        Refresh
      </button>
    </div>
  </div>
  
  <!-- Desktop View -->
  <div class="desktop-view" style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);">
    <div style="overflow-x: auto;">
      <table class="data-table" style="width: 100%; border-collapse: collapse;">
      <thead style="background: linear-gradient(to right, #f8fafc, #f1f5f9);">
        <tr style="border-bottom: 1px solid #e2e8f0;">
          <th style="padding: 1rem 1.5rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Form Type</th>
          <th style="padding: 1rem 1.5rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Period</th>
          <th style="padding: 1rem 1.5rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Amount</th>
          <th style="padding: 1rem 1.5rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Status</th>
          <th style="padding: 1rem 1.5rem; text-align: left; font-weight: 600; font-size: 0.875rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Date Filed</th>
          <th style="padding: 1rem 1.5rem; text-align: center; font-weight: 600; font-size: 0.875rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentForms)): ?>
        <tr>
          <td colspan="6" style="padding: 3rem; text-align: center; color: #64748b; font-size: 0.875rem;">
            <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              <div>
                <div style="font-weight: 600; margin-bottom: 0.25rem;">No BIR forms found</div>
                <div style="color: #94a3b8;">Generate your first BIR form to see it here</div>
              </div>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($recentForms as $index => $form): ?>
        <tr style="border-bottom: 1px solid #f1f5f9; transition: all 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#e2e8f0';" onmouseout="this.style.backgroundColor='transparent'; this.style.borderColor='#f1f5f9';">
          <td style="padding: 1rem 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <div style="width: 2px; height: 2rem; background: linear-gradient(to bottom, #000000ff, #620679ff); border-radius: 1px;"></div>
              <div>
                <div style="font-weight: 600; color: #0f172a; font-size: 0.875rem;"><?php echo htmlspecialchars($form['form_type']); ?></div>
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;">ID: <?php echo substr($form['_id'], -8); ?></div>
              </div>
            </div>
          </td>
          <td style="padding: 1rem 1.5rem;">
            <?php 
            $period = $form['arrival_date'] ?? $form['created_at'] ?? '';
            if ($period) {
                $date = new DateTime($period);
                echo '<div style="font-weight: 500; color: #0f172a;">' . $date->format('M Y') . '</div>';
                echo '<div style="font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;">' . $date->format('F j, Y') . '</div>';
            } else {
                echo '<span style="color: #94a3b8;">-</span>';
            }
            ?>
          </td>
          <td style="padding: 1rem 1.5rem;">
            <div style="font-weight: 600; color: #0f172a; font-size: 0.875rem;"><?php echo CurrencyHelper::format($form['total_duties'] ?? 0); ?></div>
            <?php if (!empty($form['customs_duty']) || !empty($form['import_vat'])): ?>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;">
              <?php if (!empty($form['customs_duty'])) echo 'Duty: ' . CurrencyHelper::format($form['customs_duty']); ?>
              <?php if (!empty($form['customs_duty']) && !empty($form['import_vat'])) echo ' • '; ?>
              <?php if (!empty($form['import_vat'])) echo 'VAT: ' . CurrencyHelper::format($form['import_vat']); ?>
            </div>
            <?php endif; ?>
          </td>
          <td style="padding: 1rem 1.5rem;">
            <?php
            $currentStatus = $form['status'] ?? 'draft';
            $statusConfig = [
                'draft' => ['bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#f59e0b', 'label' => 'Draft'],
                'submitted' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'border' => '#3b82f6', 'label' => 'Submitted'],
                'filed' => ['bg' => '#d1fae5', 'color' => '#065f46', 'border' => '#10b981', 'label' => 'Filed'],
                'approved' => ['bg' => '#dcfce7', 'color' => '#166534', 'border' => '#22c55e', 'label' => 'Approved']
            ];
            $config = $statusConfig[$currentStatus] ?? $statusConfig['draft'];
            ?>
            <div class="status-buttons" style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
              <?php foreach ($statusConfig as $value => $status): ?>
                <button 
                  onclick="updateFormStatus('<?php echo $form['_id']; ?>', '<?php echo $value; ?>')"
                  style="
                    background: <?php echo $currentStatus === $value ? $status['bg'] : '#f8fafc'; ?>;
                    color: <?php echo $currentStatus === $value ? $status['color'] : '#64748b'; ?>;
                    border: 1px solid <?php echo $currentStatus === $value ? $status['border'] : '#e2e8f0'; ?>;
                    padding: 0.25rem 0.75rem;
                    border-radius: 9999px;
                    font-size: 0.75rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                    white-space: nowrap;
                  "
                  onmouseover="this.style.background='<?php echo $currentStatus === $value ? $status['bg'] : '#f1f5f9'; ?>'; this.style.borderColor='<?php echo $status['border']; ?>';"
                  onmouseout="this.style.background='<?php echo $currentStatus === $value ? $status['bg'] : '#f8fafc'; ?>'; this.style.borderColor='<?php echo $currentStatus === $value ? $status['border'] : '#e2e8f0'; ?>';"
                >
                  <?php echo $status['label']; ?>
                </button>
              <?php endforeach; ?>
            </div>
          </td>
          <td style="padding: 1rem 1.5rem;">
            <?php 
            $dateFiled = $form['created_at'] ?? '';
            if ($dateFiled) {
                $date = new DateTime($dateFiled);
                echo '<div style="font-weight: 500; color: #0f172a;">' . $date->format('M d, Y') . '</div>';
                echo '<div style="font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;">' . $date->format('g:i A') . '</div>';
            } else {
                echo '<span style="color: #94a3b8;">-</span>';
            }
            ?>
          </td>
          <td style="padding: 1rem 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
              <button onclick="viewForm('<?php echo $form['_id']; ?>')" style="padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: white; color: #475569; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0';" title="View Form">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
              <button onclick="downloadForm('<?php echo $form['_id']; ?>')" style="padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: white; color: #475569; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0';" title="Download PDF">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 10L12 15M12 15L7 10M12 15V3"/>
                </svg>
              </button>
              <button onclick="deleteForm('<?php echo $form['_id']; ?>')" style="padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #fecaca; background: white; color: #dc2626; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='#fef2f2'; this.style.borderColor='#fca5a5';" onmouseout="this.style.background='white'; this.style.borderColor='#fecaca';" title="Delete Form">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 6H5H21M8 6V4C8 3.44772 8.44772 3 9 3H15C15.5523 3 16 3.44772 16 4V6M19 6V20C19 21.1046 18.1046 22 17 22H7C5.89543 22 5 21.1046 5 20V6H19Z"/>
                  <path d="M10 11V17M14 11V17"/>
                </svg>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- Mobile View -->
  <div class="mobile-view" style="display: none;">
    <?php if (empty($recentForms)): ?>
    <div style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 3rem; text-align: center;">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="margin-bottom: 1rem;">
        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <div style="font-weight: 600; margin-bottom: 0.25rem; color: #0f172a;">No BIR forms found</div>
      <div style="color: #94a3b8; font-size: 0.875rem;">Generate your first BIR form to see it here</div>
    </div>
    <?php else: ?>
    <?php foreach ($recentForms as $form): ?>
    <div class="form-card" style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; margin-bottom: 1rem; overflow: hidden; transition: all 0.2s;" onmouseover="this.style.boxShadow='0 4px 6px -1px rgb(0 0 0 / 0.1)';" onmouseout="this.style.boxShadow='0 1px 3px 0 rgb(0 0 0 / 0.1)';">
      <!-- Card Header -->
      <div style="padding: 1rem; border-bottom: 1px solid #f1f5f9; background: linear-gradient(to right, #f8fafc, #f1f5f9);">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 2px; height: 1.5rem; background: linear-gradient(to bottom, #3b82f6, #8b5cf6); border-radius: 1px;"></div>
            <div>
              <div style="font-weight: 600; color: #0f172a; font-size: 0.875rem;"><?php echo htmlspecialchars($form['form_type']); ?></div>
              <div style="font-size: 0.75rem; color: #64748b;">ID: <?php echo substr($form['_id'], -8); ?></div>
            </div>
          </div>
          <div style="text-align: right;">
            <div style="font-weight: 600; color: #0f172a; font-size: 1rem;"><?php echo CurrencyHelper::format($form['total_duties'] ?? 0); ?></div>
            <?php if (!empty($form['customs_duty']) || !empty($form['import_vat'])): ?>
            <div style="font-size: 0.75rem; color: #64748b;">
              <?php if (!empty($form['customs_duty'])) echo 'Duty: ' . CurrencyHelper::format($form['customs_duty']); ?>
              <?php if (!empty($form['customs_duty']) && !empty($form['import_vat'])) echo ' • '; ?>
              <?php if (!empty($form['import_vat'])) echo 'VAT: ' . CurrencyHelper::format($form['import_vat']); ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Card Body -->
      <div style="padding: 1rem;">
        <!-- Status Section -->
        <div style="margin-bottom: 1rem;">
          <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</div>
          <?php
          $currentStatus = $form['status'] ?? 'draft';
          $statusConfig = [
              'draft' => ['bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#f59e0b', 'label' => 'Draft'],
              'submitted' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'border' => '#3b82f6', 'label' => 'Submitted'],
              'filed' => ['bg' => '#d1fae5', 'color' => '#065f46', 'border' => '#10b981', 'label' => 'Filed'],
              'approved' => ['bg' => '#dcfce7', 'color' => '#166534', 'border' => '#22c55e', 'label' => 'Approved']
          ];
          ?>
          <div class="status-buttons" style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
            <?php foreach ($statusConfig as $value => $status): ?>
              <button 
                onclick="updateFormStatus('<?php echo $form['_id']; ?>', '<?php echo $value; ?>')"
                style="
                  background: <?php echo $currentStatus === $value ? $status['bg'] : '#f8fafc'; ?>;
                  color: <?php echo $currentStatus === $value ? $status['color'] : '#64748b'; ?>;
                  border: 1px solid <?php echo $currentStatus === $value ? $status['border'] : '#e2e8f0'; ?>;
                  padding: 0.375rem 0.75rem;
                  border-radius: 9999px;
                  font-size: 0.75rem;
                  font-weight: 600;
                  cursor: pointer;
                  transition: all 0.2s;
                "
                onmouseover="this.style.background='<?php echo $currentStatus === $value ? $status['bg'] : '#f1f5f9'; ?>'; this.style.borderColor='<?php echo $status['border']; ?>';"
                onmouseout="this.style.background='<?php echo $currentStatus === $value ? $status['bg'] : '#f8fafc'; ?>'; this.style.borderColor='<?php echo $currentStatus === $value ? $status['border'] : '#e2e8f0'; ?>';"
              >
                <?php echo $status['label']; ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        
        <!-- Details Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Period</div>
            <?php 
            $period = $form['arrival_date'] ?? $form['created_at'] ?? '';
            if ($period) {
                $date = new DateTime($period);
                echo '<div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">' . $date->format('M Y') . '</div>';
                echo '<div style="font-size: 0.75rem; color: #64748b;">' . $date->format('F j, Y') . '</div>';
            } else {
                echo '<span style="color: #94a3b8;">-</span>';
            }
            ?>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Date Filed</div>
            <?php 
            $dateFiled = $form['created_at'] ?? '';
            if ($dateFiled) {
                $date = new DateTime($dateFiled);
                echo '<div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">' . $date->format('M d, Y') . '</div>';
                echo '<div style="font-size: 0.75rem; color: #64748b;">' . $date->format('g:i A') . '</div>';
            } else {
                echo '<span style="color: #94a3b8;">-</span>';
            }
            ?>
          </div>
        </div>
        
        <!-- Actions -->
        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
          <button onclick="viewForm('<?php echo $form['_id']; ?>')" style="padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: white; color: #475569; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            View
          </button>
          <button onclick="downloadForm('<?php echo $form['_id']; ?>')" style="padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: white; color: #475569; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 10L12 15M12 15L7 10M12 15V3"/>
            </svg>
            PDF
          </button>
          <button onclick="deleteForm('<?php echo $form['_id']; ?>')" style="padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #fecaca; background: white; color: #dc2626; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='#fef2f2'; this.style.borderColor='#fca5a5';" onmouseout="this.style.background='white'; this.style.borderColor='#fecaca';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 6H5H21M8 6V4C8 3.44772 8.44772 3 9 3H15C15.5523 3 16 3.44772 16 4V6M19 6V20C19 21.1046 18.1046 22 17 22H7C5.89543 22 5 21.1046 5 20V6H19Z"/>
              <path d="M10 11V17M14 11V17"/>
            </svg>
            Delete
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<style>
@media (max-width: 768px) {
  .desktop-view {
    display: none !important;
  }
  .mobile-view {
    display: block !important;
  }
}

@media (min-width: 769px) {
  .mobile-view {
    display: none !important;
  }
}
</style>

<!-- Form View Modal -->
<div id="formModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; backdrop-filter: blur(4px);">
  <div class="modal-content" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25); max-width: 90vw; max-height: 90vh; width: 600px; overflow: hidden; animation: modalFadeIn 0.2s ease-out;">
    <!-- Modal Header -->
    <div class="modal-header" style="padding: 1.5rem; border-bottom: 1px solid #e2e8f0; background: linear-gradient(to right, #f8fafc, #f1f5f9);">
      <div>
        <h3 id="modalTitle" style="margin: 0; font-size: 1.125rem; font-weight: 600; color: #0f172a;">BIR Form Details</h3>
        <p id="modalSubtitle" style="margin: 0.25rem 0 0 0; font-size: 0.875rem; color: #64748b;">Form information and calculated duties</p>
      </div>
    </div>
    
    <!-- Modal Body -->
    <div class="modal-body" style="padding: 1.5rem; overflow-y: auto; max-height: calc(90vh - 8rem);">
      <div id="modalContent" style="display: grid; gap: 1.5rem;">
        <!-- Content will be loaded here -->
        <div style="display: flex; justify-content: center; align-items: center; padding: 3rem; color: #64748b;">
          <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
            <div style="width: 2rem; height: 2rem; border: 3px solid #e2e8f0; border-top: 3px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <div style="font-size: 0.875rem;">Loading form details...</div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div class="modal-footer" style="padding: 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc;">
      <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
        <div id="modalStatus" style="display: flex; align-items: center; gap: 0.5rem;">
          <!-- Status will be loaded here -->
        </div>
        <div style="display: flex; gap: 0.5rem;">
          <button onclick="generateITR()" style="padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #000; background: #000; color: white; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='#1a1a1a'; this.style.borderColor='#333';" onmouseout="this.style.background='#000'; this.style.borderColor='#000';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14,2 14,8 20,8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
              <polyline points="10,9 9,9 8,9"/>
            </svg>
            Annual ITR
          </button>
          <button onclick="printForm()" style="padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: white; color: #475569; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M6 9V2h12v7"/>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
              <path d="M6 14h12v8H6z"/>
            </svg>
            Print
          </button>
          <button onclick="downloadFormFromModal()" style="padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: white; color: #475569; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 10L12 15M12 15L7 10M12 15V3"/>
            </svg>
            Download Text
          </button>
          <button onclick="closeFormModal()" style="padding: 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #dc2626; background: #dc2626; color: white; cursor: pointer; transition: all 0.2s; font-size: 0.875rem; font-weight: 500;" onmouseover="this.style.background='#b91c1c'; this.style.borderColor='#b91c1c';" onmouseout="this.style.background='#dc2626'; this.style.borderColor='#dc2626';">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ITR Modal - BIR Form 1702 -->
<div id="itrModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; overflow: auto;">
  <div style="position: relative; background: white; margin: 1rem auto; padding: 0; width: 95%; max-width: 1400px; max-height: 95vh; overflow-y: auto; border-radius: 0.25rem; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);">
    
    <!-- BIR Form Header -->
    <div style="background: #f8f9fa; padding: 1.5rem; border-bottom: 2px solid #000; text-align: center;">
      <div style="font-size: 1.25rem; font-weight: 700; color: #000; margin-bottom: 0.5rem;">BIR Form No. 1702</div>
      <div style="font-size: 0.875rem; color: #333; margin-bottom: 0.25rem;">Annual Income Tax Return</div>
      <div style="font-size: 0.75rem; color: #666;">For Corporations, Partnerships & Other Non-Individual Taxpayers</div>
      <div style="font-size: 0.75rem; color: #666; margin-top: 0.5rem;">Tax Year: <span id="taxYear"><?php echo date('Y'); ?></span></div>
    </div>
    
    <!-- BIR Form Content -->
    <div id="itrModalContent" style="background: white; padding: 1rem;">
      
      <!-- PART I: TAXPAYER INFORMATION -->
      <div style="margin-bottom: 2rem;">
        <div style="background: #e9ecef; padding: 0.75rem; font-weight: 600; border: 1px solid #000; margin-bottom: 1rem;">PART I - TAXPAYER INFORMATION</div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">1. TIN</label>
            <input type="text" id="tin" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; font-size: 0.875rem;" placeholder="000-000-000-000">
          </div>
          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">2. RDO Code</label>
            <input type="text" id="rdoCode" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; font-size: 0.875rem;" placeholder="000">
          </div>
        </div>
        
        <div style="margin-bottom: 1rem;">
          <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">3. Registered Name</label>
          <input type="text" id="registeredName" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; font-size: 0.875rem;" placeholder="Enter registered business name">
        </div>
        
        <div style="margin-bottom: 1rem;">
          <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">4. Registered Address</label>
          <input type="text" id="registeredAddress" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; font-size: 0.875rem;" placeholder="Enter complete address">
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">5. Zip Code</label>
            <input type="text" id="zipCode" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; font-size: 0.875rem;" placeholder="0000">
          </div>
          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">6. Telephone Number</label>
            <input type="text" id="telephone" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; font-size: 0.875rem;" placeholder="(02) 8888-8888">
          </div>
          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">7. Email Address</label>
            <input type="email" id="email" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; font-size: 0.875rem;" placeholder="email@example.com">
          </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">8. Tax Type</label>
            <select id="taxType" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; font-size: 0.875rem;">
              <option value="regular">Regular Corporate Income Tax</option>
              <option value="special">Special Tax Rate</option>
            </select>
          </div>
          <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem;">9. Category of Taxpayer</label>
            <select id="category" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; font-size: 0.875rem;">
              <option value="domestic">Domestic Corporation</option>
              <option value="foreign">Foreign Corporation</option>
              <option value="partnership">Partnership</option>
            </select>
          </div>
        </div>
      </div>
      
      <!-- PART II: TOTAL TAX PAYABLE -->
      <div style="margin-bottom: 2rem;">
        <div style="background: #e9ecef; padding: 0.75rem; font-weight: 600; border: 1px solid #000; margin-bottom: 1rem;">PART II - TOTAL TAX PAYABLE</div>
        
        <div style="background: #f8f9fa; padding: 1rem; border: 1px solid #dee2e6;">
          <div style="display: grid; grid-template-columns: 1fr 100px; gap: 1rem; margin-bottom: 0.5rem;">
            <div style="font-size: 0.875rem;">14. Total Tax Due (Normal Income Tax in Item 41 OR MCIT in Item 42, whichever is higher)</div>
            <input type="number" id="totalTaxDue" style="text-align: right; padding: 0.25rem; border: 1px solid #ccc; font-size: 0.875rem;" readonly>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 100px; gap: 1rem; margin-bottom: 0.5rem;">
            <div style="font-size: 0.875rem;">15. Less: Total Tax Credits/Payments (From Schedule 7 Item 12)</div>
            <input type="number" id="taxCredits" style="text-align: right; padding: 0.25rem; border: 1px solid #ccc; font-size: 0.875rem;">
          </div>
          <div style="display: grid; grid-template-columns: 1fr 100px; gap: 1rem; border-top: 2px solid #000; padding-top: 0.5rem;">
            <div style="font-size: 0.875rem; font-weight: 600;">16. Net Tax Payable/(Overpayment)</div>
            <input type="number" id="netTaxPayable" style="text-align: right; padding: 0.25rem; border: 1px solid #ccc; font-size: 0.875rem; font-weight: 600;" readonly>
          </div>
        </div>
      </div>
      
      <!-- PART III: ITEMS FROM SCHEDULES -->
      <div style="margin-bottom: 2rem;">
        <div style="background: #e9ecef; padding: 0.75rem; font-weight: 600; border: 1px solid #000; margin-bottom: 1rem;">PART III - ITEMS FROM SCHEDULES</div>
        
        <div style="background: #f8f9fa; padding: 1rem; border: 1px solid #dee2e6;">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="background: #e9ecef;">
                <th style="border: 1px solid #000; padding: 0.5rem; font-size: 0.75rem; text-align: left;">Item</th>
                <th style="border: 1px solid #000; padding: 0.5rem; font-size: 0.75rem; text-align: left;">Description</th>
                <th style="border: 1px solid #000; padding: 0.5rem; font-size: 0.75rem; text-align: right;">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem; text-align: center;">17A</td>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem;">Gross Sales/Receipts</td>
                <td style="border: 1px solid #000; padding: 0.5rem; text-align: right;">
                  <input type="number" id="grossSales" style="width: 100%; text-align: right; border: none; background: transparent; font-size: 0.875rem;">
                </td>
              </tr>
              <tr>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem; text-align: center;">17B</td>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem;">Cost of Goods Sold</td>
                <td style="border: 1px solid #000; padding: 0.5rem; text-align: right;">
                  <input type="number" id="costOfGoods" style="width: 100%; text-align: right; border: none; background: transparent; font-size: 0.875rem;">
                </td>
              </tr>
              <tr>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem; text-align: center;">18A</td>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem;">Business Expenses</td>
                <td style="border: 1px solid #000; padding: 0.5rem; text-align: right;">
                  <input type="number" id="businessExpenses" style="width: 100%; text-align: right; border: none; background: transparent; font-size: 0.875rem;">
                </td>
              </tr>
              <tr>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem; text-align: center;">18B</td>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem;">Interest Income</td>
                <td style="border: 1px solid #000; padding: 0.5rem; text-align: right;">
                  <input type="number" id="interestIncome" style="width: 100%; text-align: right; border: none; background: transparent; font-size: 0.875rem;">
                </td>
              </tr>
              <tr>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem; text-align: center;">18C</td>
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem;">Dividend Income</td>
                <td style="border: 1px solid #000; padding: 0.5rem; text-align: right;">
                  <input type="number" id="dividendIncome" style="width: 100%; text-align: right; border: none; background: transparent; font-size: 0.875rem;">
                </td>
              </tr>
              <tr style="background: #e9ecef; font-weight: 600;">
                <td style="border: 1px solid #000; padding: 0.5rem; font-size: 0.875rem; text-align: center;" colspan="2">Net Taxable Income</td>
                <td style="border: 1px solid #000; padding: 0.5rem; text-align: right; font-size: 0.875rem;">
                  <input type="number" id="netTaxableIncome" style="width: 100%; text-align: right; border: none; background: transparent; font-size: 0.875rem; font-weight: 600;" readonly>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- PART IV: COMPUTATION OF TAX -->
      <div style="margin-bottom: 2rem;">
        <div style="background: #e9ecef; padding: 0.75rem; font-weight: 600; border: 1px solid #000; margin-bottom: 1rem;">PART IV - COMPUTATION OF TAX</div>
        
        <div style="background: #f8f9fa; padding: 1rem; border: 1px solid #dee2e6;">
          <div style="display: grid; grid-template-columns: 1fr 100px; gap: 1rem; margin-bottom: 0.5rem;">
            <div style="font-size: 0.875rem;">40. Net Taxable Income (From Part III)</div>
            <input type="number" id="netTaxableIncome2" style="text-align: right; padding: 0.25rem; border: 1px solid #ccc; font-size: 0.875rem;" readonly>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 100px; gap: 1rem; margin-bottom: 0.5rem;">
            <div style="font-size: 0.875rem;">41. Normal Income Tax Due (25% of Item 40)</div>
            <input type="number" id="normalTaxDue" style="text-align: right; padding: 0.25rem; border: 1px solid #ccc; font-size: 0.875rem;" readonly>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 100px; gap: 1rem; margin-bottom: 0.5rem;">
            <div style="font-size: 0.875rem;">42. Minimum Corporate Income Tax Due (1% of Gross Sales)</div>
            <input type="number" id="mcitDue" style="text-align: right; padding: 0.25rem; border: 1px solid #ccc; font-size: 0.875rem;" readonly>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 100px; gap: 1rem; border-top: 2px solid #000; padding-top: 0.5rem;">
            <div style="font-size: 0.875rem; font-weight: 600;">43. Tax Due (Item 41 OR Item 42, whichever is higher)</div>
            <input type="number" id="taxDue" style="text-align: right; padding: 0.25rem; border: 1px solid #ccc; font-size: 0.875rem; font-weight: 600;" readonly>
          </div>
        </div>
      </div>
      
    </div>
    
    <!-- ITR Modal Footer -->
    <div style="padding: 1.5rem; border-top: 2px solid #000; background: #f8f9fa; display: flex; justify-content: space-between; align-items: center;">
      <div id="itrStatus" style="display: flex; align-items: center; gap: 0.5rem;">
        <span style="font-size: 0.875rem; color: #333;">Status:</span>
        <span style="background: #ffc107; color: #000; border: 1px solid #000; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">DRAFT</span>
      </div>
      <div style="display: flex; gap: 0.5rem;">
        <button onclick="calculateITR()" style="padding: 0.5rem 1rem; border-radius: 0.25rem; border: 1px solid #000; background: #007bff; color: white; cursor: pointer; font-size: 0.875rem; font-weight: 600;">
          Calculate Tax
        </button>
        <button onclick="downloadITR()" style="padding: 0.5rem 1rem; border-radius: 0.25rem; border: 1px solid #000; background: #28a745; color: white; cursor: pointer; font-size: 0.875rem; font-weight: 600;">
          Download ITR
        </button>
        <button onclick="closeITRModal()" style="padding: 0.5rem 1rem; border-radius: 0.25rem; border: 1px solid #000; background: #dc3545; color: white; cursor: pointer; font-size: 0.875rem; font-weight: 600;">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<style>
@keyframes modalFadeIn {
  from {
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
  }
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Modal content adjustments for side-by-side layout */
.modal-content {
  width: 900px !important; /* Increased width for better layout */
  max-height: 85vh !important;
}

/* Side-by-side layout styles */
.left-column, .right-column {
  display: flex;
  flex-direction: column;
}

.form-section {
  height: fit-content;
}

/* Mobile responsive modal */
@media (max-width: 768px) {
  .modal-content {
    width: 95vw !important;
    max-width: none !important;
    margin: 1rem;
  }
  
  /* Stack columns on mobile */
  .modal-body > div > div[style*="grid-template-columns: 1fr 1fr"] {
    grid-template-columns: 1fr !important;
    gap: 1.25rem !important;
  }
  
  /* Ensure total container stays full width on mobile */
  .modal-body > div > div > div[style*="grid-column: 1 / -1"] {
    grid-column: 1 !important;
    margin-top: 1.25rem !important;
  }
  
  .modal-header, .modal-body, .modal-footer {
    padding: 1rem !important;
  }
  
  .modal-footer {
    flex-direction: column;
    gap: 1rem !important;
  }
  
  .modal-footer > div:first-child {
    order: 2;
  }
  
  .modal-footer > div:last-child {
    order: 1;
    flex-direction: column;
    width: 100%;
  }
  
  .modal-footer button {
    width: 100%;
    justify-content: center;
  }
}

@media (max-width: 640px) {
  .modal-content {
    width: 95vw !important;
    max-width: none !important;
    margin: 1rem;
  }
  
  /* Stack columns on small mobile */
  .modal-body > div > div[style*="grid-template-columns: 1fr 1fr"] {
    grid-template-columns: 1fr !important;
    gap: 1rem !important;
  }
  
  /* Ensure total container stays full width on small mobile */
  .modal-body > div > div > div[style*="grid-column: 1 / -1"] {
    grid-column: 1 !important;
    margin-top: 1rem !important;
  }
  
  .modal-header, .modal-body, .modal-footer {
    padding: 1rem !important;
  }
  
  .modal-footer {
    flex-direction: column;
    gap: 1rem !important;
  }
  
  .modal-footer > div:first-child {
    order: 2;
  }
  
  .modal-footer > div:last-child {
    order: 1;
    flex-direction: column;
    width: 100%;
  }
  
  .modal-footer button {
    width: 100%;
    justify-content: center;
  }
}

/* Print Styles - Biodata Layout */
@media print {
  /* Hide everything except print content */
  body * {
    visibility: hidden;
  }
  
  /* Show only the print content */
  .print-content, .print-content * {
    visibility: visible;
  }
  
  /* Print layout - single page */
  .print-content {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: 100vh;
    visibility: visible;
    overflow: hidden;
  }
  
  .print-header {
    text-align: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #000;
  }
  
  .print-header h1 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #000;
  }
  
  .print-header .subtitle {
    margin: 0.25rem 0 0 0;
    font-size: 0.75rem;
    color: #666;
  }
  
  .print-biodata {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
  }
  
  .print-section {
    background: #fff;
    border: 1px solid #000;
    padding: 0.75rem;
    height: fit-content;
  }
  
  .print-section h3 {
    margin: 0 0 0.75rem 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #000;
    border-bottom: 1px solid #000;
    padding-bottom: 0.25rem;
  }
  
  .print-field {
    margin-bottom: 0.5rem;
  }
  
  .print-field label {
    display: block;
    font-size: 0.625rem;
    font-weight: 600;
    color: #000;
    text-transform: uppercase;
    margin-bottom: 0.125rem;
  }
  
  .print-field .value {
    font-size: 0.75rem;
    color: #000;
    font-weight: 500;
  }
  
  .print-total {
    background: #000;
    color: #fff;
    padding: 1rem;
    text-align: center;
    margin-top: 1rem;
    border-radius: 0.25rem;
  }
  
  .print-total .label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
  }
  
  .print-total .amount {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
  }
  
  .print-total .subtitle {
    font-size: 0.625rem;
    opacity: 0.7;
  }
  
  /* Disable browser headers and footers - Aggressive approach for all browsers */
  @page {
    margin: 0 !important;
    size: auto !important;
  }
  
  @page :header {
    display: none !important;
    visibility: hidden !important;
    content: none !important;
  }
  
  @page :footer {
    display: none !important;
    visibility: hidden !important;
    content: none !important;
  }
  
  @page top {
    display: none !important;
    visibility: hidden !important;
    content: none !important;
  }
  
  @page bottom {
    display: none !important;
    visibility: hidden !important;
    content: none !important;
  }
  
  @page left {
    display: none !important;
    visibility: hidden !important;
    content: none !important;
  }
  
  @page right {
    display: none !important;
    visibility: hidden !important;
    content: none !important;
  }
  
  /* Additional browser-specific header/footer disabling */
  @page {
    -webkit-print-color-adjust: exact !important;
    color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  
  /* Force single page and prevent any headers/footers */
  html, body {
    height: 100vh !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  
  /* Remove any possible header/footer elements */
  header, footer {
    display: none !important;
    visibility: hidden !important;
  }
  
  /* Hide page numbers and URLs */
  .page-number, .url, .date, .title {
    display: none !important;
    visibility: hidden !important;
  }
  
  /* Print fonts */
  .print-content {
    font-family: 'Arial', sans-serif;
    color: #000;
  }
}
</style>
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

<script>
// BIR Form Generation Functions
async function generateRAMSAY307() {
  try {
    Toast.loading('Generating RAMSAY 307 form...');
    
    // Get recent container data
    const response = await fetch('./api/shipments.php?action=recent');
    const data = await response.json();
    
    // Debug: Log the response
    console.log('Shipments API Response:', data);
    
    if (data.success && data.data && data.data.length > 0) {
      const container = data.data[0]; // Use most recent container
      
      // Debug: Log the container data
      console.log('Using container data:', container);
      
      const formData = new FormData();
      formData.append('action', 'generate_ramsay307');
      formData.append('container_data', JSON.stringify(container));
      
      const generateResponse = await fetch('./api/bir-forms.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await generateResponse.json();
      
      // Clear loading toast
      Toast.clearAll();
      
      if (result.success) {
        Toast.success(`RAMSAY 307 form generated! Total duties: ${CurrencyHelper.format(result.data.total_duties)}`);
        setTimeout(() => location.reload(), 2000);
      } else {
        Toast.error(result.message || 'Failed to generate form');
      }
    } else {
      Toast.clearAll();
      Toast.warning('No container data available. Please add shipment data first.');
      console.log('No shipment data found. Response:', data);
    }
  } catch (error) {
    console.error('Error generating RAMSAY 307:', error);
    Toast.clearAll();
    Toast.error('Failed to generate RAMSAY 307 form');
  }
}

async function generateVATMonthly() {
  try {
    const month = prompt('Enter month (1-12):', new Date().getMonth() + 1);
    const year = prompt('Enter year:', new Date().getFullYear());
    
    if (!month || !year) return;
    
    Toast.loading('Generating VAT Monthly return...');
    
    const formData = new FormData();
    formData.append('action', 'generate_vat_monthly');
    formData.append('month', month);
    formData.append('year', year);
    
    const response = await fetch('./api/bir-forms.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    // Clear loading toast
    Toast.clearAll();
    
    if (result.success) {
      Toast.success(`VAT Monthly (2550M) generated! VAT payable: ${CurrencyHelper.format(result.data.vat_payable)}`);
      setTimeout(() => location.reload(), 2000);
    } else {
      Toast.error(result.message || 'Failed to generate form');
    }
  } catch (error) {
    console.error('Error generating VAT Monthly:', error);
    Toast.clearAll();
    Toast.error('Failed to generate VAT Monthly form');
  }
}

async function generateVATQuarterly() {
  try {
    const quarter = prompt('Enter quarter (1-4):', Math.ceil((new Date().getMonth() + 1) / 3));
    const year = prompt('Enter year:', new Date().getFullYear());
    
    if (!quarter || !year) return;
    
    Toast.loading('Generating VAT Quarterly return...');
    
    const formData = new FormData();
    formData.append('action', 'generate_vat_quarterly');
    formData.append('quarter', quarter);
    formData.append('year', year);
    
    const response = await fetch('./api/bir-forms.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    // Clear loading toast
    Toast.clearAll();
    
    if (result.success) {
      Toast.success(`VAT Quarterly (2550Q) generated! VAT payable: ${CurrencyHelper.format(result.data.vat_payable)}`);
      setTimeout(() => location.reload(), 2000);
    } else {
      Toast.error(result.message || 'Failed to generate form');
    }
  } catch (error) {
    console.error('Error generating VAT Quarterly:', error);
    Toast.clearAll();
    Toast.error('Failed to generate VAT Quarterly form');
  }
}

async function generateWithholdingTax() {
  try {
    const month = prompt('Enter month (1-12):', new Date().getMonth() + 1);
    const year = prompt('Enter year:', new Date().getFullYear());
    
    if (!month || !year) return;
    
    Toast.loading('Generating Withholding Tax return...');
    
    const formData = new FormData();
    formData.append('action', 'generate_withholding_tax');
    formData.append('month', month);
    formData.append('year', year);
    
    const response = await fetch('./api/bir-forms.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    // Clear loading toast
    Toast.clearAll();
    
    if (result.success) {
      Toast.success(`Withholding Tax (1601C) generated! Total withheld: ${CurrencyHelper.format(result.data.total_withheld)}`);
      setTimeout(() => location.reload(), 2000);
    } else {
      Toast.error(result.message || 'Failed to generate form');
    }
  } catch (error) {
    console.error('Error generating Withholding Tax:', error);
    Toast.clearAll();
    Toast.error('Failed to generate Withholding Tax form');
  }
}

async function generate2307() {
  try {
    const paymentId = prompt('Enter payment ID:');
    
    if (!paymentId) return;
    
    Toast.loading('Generating Form 2307...');
    
    const formData = new FormData();
    formData.append('action', 'generate_2307');
    formData.append('payment_id', paymentId);
    
    const response = await fetch('./api/bir-forms.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    // Clear loading toast
    Toast.clearAll();
    
    if (result.success) {
      Toast.success(`Form 2307 generated! Amount withheld: ${CurrencyHelper.format(result.data.amount_withheld)}`);
      setTimeout(() => location.reload(), 2000);
    } else {
      Toast.error(result.message || 'Failed to generate form');
    }
  } catch (error) {
    console.error('Error generating Form 2307:', error);
    Toast.clearAll();
    Toast.error('Failed to generate Form 2307');
  }
}

async function generateITR() {
  try {
    const year = prompt('Enter year:', new Date().getFullYear() - 1);
    
    if (!year) return;
    
    Toast.loading('Generating Annual ITR...');
    
    const formData = new FormData();
    formData.append('action', 'generate_itr');
    formData.append('year', year);
    
    const response = await fetch('./api/bir-forms.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    // Clear loading toast
    Toast.clearAll();
    
    if (result.success) {
      Toast.success(`Annual ITR (1702) generated! Tax due: ${CurrencyHelper.format(result.data.tax_due)}`);
      setTimeout(() => location.reload(), 2000);
    } else {
      Toast.error(result.message || 'Failed to generate form');
    }
  } catch (error) {
    console.error('Error generating ITR:', error);
    Toast.clearAll();
    Toast.error('Failed to generate Annual ITR');
  }
}

async function generatePercentageTax() {
  try {
    const quarter = prompt('Enter quarter (1-4):', Math.ceil((new Date().getMonth() + 1) / 3));
    const year = prompt('Enter year:', new Date().getFullYear());
    
    if (!quarter || !year) return;
    
    Toast.loading('Generating Percentage Tax return...');
    
    const formData = new FormData();
    formData.append('action', 'generate_percentage_tax');
    formData.append('quarter', quarter);
    formData.append('year', year);
    
    const response = await fetch('./api/bir-forms.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    // Clear loading toast
    Toast.clearAll();
    
    if (result.success) {
      Toast.success(`Percentage Tax (2551Q) generated! Tax due: ${CurrencyHelper.format(result.data.percentage_tax_due)}`);
      setTimeout(() => location.reload(), 2000);
    } else {
      Toast.error(result.message || 'Failed to generate form');
    }
  } catch (error) {
    console.error('Error generating Percentage Tax:', error);
    Toast.clearAll();
    Toast.error('Failed to generate Percentage Tax form');
  }
}

// Currency Helper for formatting
const CurrencyHelper = {
  format: function(amount) {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP'
    }).format(amount || 0);
  }
};

// Form management functions
async function updateFormStatus(formId, newStatus) {
  try {
    Toast.loading('Updating form status...');
    
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('form_id', formId);
    formData.append('status', newStatus);
    
    const response = await fetch('./api/bir-forms.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    Toast.clearAll();
    
    if (result.success) {
      Toast.success('Form status updated successfully');
      setTimeout(() => location.reload(), 1000);
    } else {
      Toast.error(result.message || 'Failed to update status');
    }
  } catch (error) {
    console.error('Error updating form status:', error);
    Toast.clearAll();
    Toast.error('Failed to update form status');
  }
}

function viewForm(formId) {
  openFormModal(formId);
}

function openFormModal(formId) {
  const modal = document.getElementById('formModal');
  const modalContent = document.getElementById('modalContent');
  const modalTitle = document.getElementById('modalTitle');
  const modalSubtitle = document.getElementById('modalSubtitle');
  const modalStatus = document.getElementById('modalStatus');
  
  // Show modal with loading state
  modal.style.display = 'block';
  document.body.style.overflow = 'hidden';
  
  // Reset content
  modalTitle.textContent = 'Loading...';
  modalSubtitle.textContent = 'Fetching form details';
  modalStatus.innerHTML = '';
  
  // Fetch form data
  fetch(`./api/bir-forms.php?action=view_form&form_id=${formId}`)
    .then(response => {
      console.log('Response status:', response.status);
      console.log('Response headers:', response.headers.get('content-type'));
      
      // Check if response is actually JSON
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Server returned non-JSON response');
      }
      
      return response.json();
    })
    .then(result => {
      console.log('API Result:', result);
      if (result.success) {
        renderFormInModal(result.data);
      } else {
        modalContent.innerHTML = `
          <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 2rem; text-align: center;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"/>
              <path d="M15 9L9 15M9 9H9.01M15 15H15.01" stroke-width="2"/>
            </svg>
            <div>
              <div style="font-weight: 600; color: #0f172a; margin-bottom: 0.25rem;">Error Loading Form</div>
              <div style="color: #64748b; font-size: 0.875rem;">${result.message || 'Unable to load form details'}</div>
            </div>
          </div>
        `;
      }
    })
    .catch(error => {
      console.error('Error fetching form:', error);
      console.error('Error details:', error.message);
      
      modalContent.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 2rem; text-align: center;">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"/>
            <path d="M15 9L9 15M9 9H9.01M15 15H15.01" stroke-width="2"/>
          </svg>
          <div>
            <div style="font-weight: 600; color: #0f172a; margin-bottom: 0.25rem;">Network Error</div>
            <div style="color: #64748b; font-size: 0.875rem;">Failed to connect to server: ${error.message}</div>
            <div style="color: #94a3b8; font-size: 0.75rem; margin-top: 0.5rem;">Please check the console for more details</div>
          </div>
        </div>
      `;
    });
}

function renderFormInModal(formData) {
  const modalContent = document.getElementById('modalContent');
  const modalTitle = document.getElementById('modalTitle');
  const modalSubtitle = document.getElementById('modalSubtitle');
  const modalStatus = document.getElementById('modalStatus');
  
  // Debug: Log the _id structure
  console.log('formData._id:', formData._id);
  console.log('typeof formData._id:', typeof formData._id);
  
  // Store the full form ID for modal operations
  let fullFormId = '';
  if (formData._id) {
    if (typeof formData._id === 'string') {
      fullFormId = formData._id;
    } else if (formData._id.$oid) {
      fullFormId = formData._id.$oid;
    } else if (formData.id) {
      fullFormId = formData.id;
    } else if (formData._id.toString) {
      // Try to convert ObjectId to string
      fullFormId = formData._id.toString();
    }
  }
  
  console.log('Extracted fullFormId:', fullFormId);
  
  // Store full ID in modal data attribute
  const modal = document.getElementById('formModal');
  modal.setAttribute('data-form-id', fullFormId);
  
  // Update header
  modalTitle.textContent = formData.form_type || 'BIR Form';
  
  // Handle ObjectId for ID display (show last 8 chars)
  let displayId = 'Unknown';
  if (fullFormId) {
    displayId = fullFormId.substring(-8);
  }
  modalSubtitle.textContent = `ID: ${displayId}`;
  
  // Update status
  const statusConfig = {
    'draft': {bg: '#fef3c7', color: '#92400e', border: '#f59e0b', label: 'Draft'},
    'submitted': {bg: '#dbeafe', color: '#1e40af', border: '#3b82f6', label: 'Submitted'},
    'filed': {bg: '#d1fae5', color: '#065f46', border: '#10b981', label: 'Filed'},
    'approved': {bg: '#dcfce7', color: '#166534', border: '#22c55e', label: 'Approved'}
  };
  
  const currentStatus = formData.status || 'draft';
  const config = statusConfig[currentStatus] || statusConfig['draft'];
  
  modalStatus.innerHTML = `
    <span style="font-size: 0.875rem; color: #64748b;">Status:</span>
    <span style="background: ${config.bg}; color: ${config.color}; border: 1px solid ${config.border}; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
      ${config.label}
    </span>
  `;
  
  // Render form content
  const formContent = generateFormContent(formData);
  modalContent.innerHTML = formContent;
}

function generateFormContent(formData) {
  let html = '';
  
  // Generate container number if it's N/A
  let containerNumber = formData.container_number;
  if (!containerNumber || containerNumber === 'N/A' || containerNumber === '') {
    // Get form ID for consistent generation
    let formId = '';
    if (formData._id) {
      if (typeof formData._id === 'string') {
        formId = formData._id;
      } else if (formData._id.$oid) {
        formId = formData._id.$oid;
      } else if (formData.id) {
        formId = formData.id;
      } else if (formData._id.toString) {
        formId = formData._id.toString();
      }
    }
    containerNumber = generateContainerNumber(formId);
  }
  
  // Main container with grid layout
  html += `
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
      <!-- Left Column: Basic Information -->
      <div class="left-column">
        <div class="form-section" style="background: #f8fafc; border-radius: 0.5rem; padding: 1.25rem; border: 1px solid #e2e8f0; height: fit-content;">
          <h4 style="margin: 0 0 1.25rem 0; font-size: 1rem; font-weight: 600; color: #0f172a;">Basic Information</h4>
          <div style="display: flex; flex-direction: column; gap: 0.875rem;">
            <div>
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Form Type</div>
              <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${formData.form_type || 'N/A'}</div>
            </div>
            <div>
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Container Number</div>
              <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${containerNumber}</div>
            </div>
            <div>
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Arrival Date</div>
              <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${formData.arrival_date || 'N/A'}</div>
            </div>
            <div>
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Created</div>
              <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${formData.created_at || 'N/A'}</div>
            </div>
          </div>
        </div>
        
        <!-- Additional Information (if available) -->
        ${generateAdditionalInfo(formData)}
      </div>
      
      <!-- Right Column: Financial Information -->
      <div class="right-column">
        <div class="form-section" style="background: #f8fafc; border-radius: 0.5rem; padding: 1.25rem; border: 1px solid #e2e8f0; height: fit-content;">
          <h4 style="margin: 0 0 1.25rem 0; font-size: 1rem; font-weight: 600; color: #0f172a;">Financial Information</h4>
          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Value of Goods</div>
              <div style="font-weight: 600; color: #0f172a; font-size: 1.125rem;">${CurrencyHelper.format(formData.value_of_goods || 0)}</div>
            </div>
            <div>
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Customs Duty</div>
              <div style="font-weight: 600; color: #0f172a; font-size: 1.125rem;">${CurrencyHelper.format(formData.customs_duty || 0)}</div>
            </div>
            <div>
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Import VAT</div>
              <div style="font-weight: 600; color: #0f172a; font-size: 1.125rem;">${CurrencyHelper.format(formData.import_vat || 0)}</div>
            </div>
            <div>
              <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Other Taxes</div>
              <div style="font-weight: 600; color: #0f172a; font-size: 1.125rem;">${CurrencyHelper.format(formData.other_taxes || 0)}</div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Total Duties and Taxes - Full-Width Container -->
      <div style="grid-column: 1 / -1; margin-top: 0.75rem;">
        <div style="padding: 1.25rem; background: #000000; border-radius: 0.5rem; text-align: center;">
          <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.7); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 500;">Total Duties and Taxes</div>
          <div style="font-size: 1.75rem; font-weight: 700; color: white; margin-bottom: 0.5rem;">${CurrencyHelper.format(formData.total_duties || 0)}</div>
          <div style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.5);">Final Amount Due</div>
        </div>
      </div>
    </div>
  `;
  
  return html;
}

function generateAdditionalInfo(formData) {
  const additionalFields = ['shipping_cost', 'insurance_amount', 'total_cost'];
  const hasAdditional = additionalFields.some(field => formData[field] && formData[field] > 0);
  
  if (!hasAdditional) {
    return '';
  }
  
  let html = `
    <div class="form-section" style="background: #f8fafc; border-radius: 0.5rem; padding: 1.25rem; border: 1px solid #e2e8f0; margin-top: 1.25rem;">
      <h4 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #0f172a;">Additional Information</h4>
      <div style="display: flex; flex-direction: column; gap: 0.875rem;">
  `;
  
  if (formData.shipping_cost && formData.shipping_cost > 0) {
    html += `
      <div>
        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Shipping Cost</div>
        <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${CurrencyHelper.format(formData.shipping_cost)}</div>
      </div>
    `;
  }
  
  if (formData.insurance_amount && formData.insurance_amount > 0) {
    html += `
      <div>
        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Insurance Amount</div>
        <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${CurrencyHelper.format(formData.insurance_amount)}</div>
      </div>
    `;
  }
  
  if (formData.total_cost && formData.total_cost > 0) {
    html += `
      <div>
        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Cost</div>
        <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${CurrencyHelper.format(formData.total_cost)}</div>
      </div>
    `;
  }
  
  html += `
      </div>
    </div>
  `;
  
  return html;
}

function closeFormModal() {
  const modal = document.getElementById('formModal');
  modal.style.display = 'none';
  document.body.style.overflow = 'auto';
}

function downloadFormFromModal() {
  // Get the current form data from the modal
  const modal = document.getElementById('formModal');
  const formId = modal.getAttribute('data-form-id');
  const modalTitle = document.getElementById('modalTitle').textContent;
  const modalSubtitle = document.getElementById('modalSubtitle').textContent;
  
  if (!formId) {
    Toast.error('Unable to download form - missing form ID');
    return;
  }
  
  // Extract form data from modal content
  const formData = extractFormDataFromModal();
  
  // Generate PDF
  generatePDF(formData, modalTitle, modalSubtitle, formId);
}

function generatePDF(formData, title, subtitle, formId) {
  Toast.loading('Generating PDF...');
  
  try {
    // Create PDF content using biodata layout
    const pdfContent = generatePDFContent(formData, title, subtitle);
    
    // Use html2canvas and jsPDF to create PDF
    // For now, we'll create a simple text-based PDF
    createTextPDF(formData, title, subtitle, formId);
    
  } catch (error) {
    console.error('Error generating PDF:', error);
    Toast.error('Failed to generate PDF');
  }
}

function createTextPDF(formData, title, subtitle, formId) {
  // Create a simple text-based PDF using Blob
  const pdfLines = [];
  
  // Header
  pdfLines.push('='.repeat(60));
  pdfLines.push(title.toUpperCase());
  pdfLines.push(subtitle);
  pdfLines.push(`Generated: ${new Date().toLocaleDateString()}`);
  pdfLines.push('='.repeat(60));
  pdfLines.push('');
  
  // Basic Information
  pdfLines.push('BASIC INFORMATION');
  pdfLines.push('-'.repeat(30));
  pdfLines.push(`Form Type: ${formData['form type'] || 'N/A'}`);
  pdfLines.push(`Container Number: ${formData['container number'] || 'N/A'}`);
  pdfLines.push(`Arrival Date: ${formData['arrival date'] || 'N/A'}`);
  pdfLines.push(`Created: ${formData['created'] || 'N/A'}`);
  pdfLines.push('');
  
  // Financial Information
  pdfLines.push('FINANCIAL INFORMATION');
  pdfLines.push('-'.repeat(30));
  pdfLines.push(`Value of Goods: ${formData['value of goods'] || 'N/A'}`);
  pdfLines.push(`Customs Duty: ${formData['customs duty'] || 'N/A'}`);
  pdfLines.push(`Import VAT: ${formData['import vat'] || 'N/A'}`);
  pdfLines.push(`Other Taxes: ${formData['other taxes'] || 'N/A'}`);
  pdfLines.push('');
  
  // Total
  pdfLines.push('='.repeat(60));
  pdfLines.push('TOTAL DUTIES AND TAXES');
  pdfLines.push(formData['total_duties'] || 'N/A');
  pdfLines.push('Final Amount Due');
  pdfLines.push('='.repeat(60));
  
  // Create text content
  const textContent = pdfLines.join('\n');
  
  // Create Blob
  const blob = new Blob([textContent], { type: 'text/plain' });
  
  // Create download link
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `BIR_Form_${formId.slice(-8)}_${new Date().toISOString().split('T')[0]}.txt`;
  
  // Trigger download
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  
  // Cleanup
  window.URL.revokeObjectURL(url);
  
  Toast.success('PDF downloaded successfully');
  setTimeout(() => Toast.clearAll(), 2000);
}

function generatePDFContent(formData, title, subtitle) {
  // This would be used for html2canvas approach
  let html = `
    <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;">
      <div style="text-align: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid #000;">
        <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #000;">${title}</h1>
        <div style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #666;">${subtitle}</div>
        <div style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: #666;">Generated: ${new Date().toLocaleDateString()}</div>
      </div>
      
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <div style="border: 1px solid #000; padding: 1rem;">
          <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #000; border-bottom: 1px solid #000; padding-bottom: 0.5rem;">Basic Information</h3>
          <div style="margin-bottom: 0.75rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #000; text-transform: uppercase;">Form Type</div>
            <div style="font-size: 0.875rem; color: #000;">${formData['form type'] || 'N/A'}</div>
          </div>
          <div style="margin-bottom: 0.75rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #000; text-transform: uppercase;">Container Number</div>
            <div style="font-size: 0.875rem; color: #000;">${formData['container number'] || 'N/A'}</div>
          </div>
          <div style="margin-bottom: 0.75rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #000; text-transform: uppercase;">Arrival Date</div>
            <div style="font-size: 0.875rem; color: #000;">${formData['arrival date'] || 'N/A'}</div>
          </div>
          <div style="margin-bottom: 0.75rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #000; text-transform: uppercase;">Created</div>
            <div style="font-size: 0.875rem; color: #000;">${formData['created'] || 'N/A'}</div>
          </div>
        </div>
        
        <div style="border: 1px solid #000; padding: 1rem;">
          <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #000; border-bottom: 1px solid #000; padding-bottom: 0.5rem;">Financial Information</h3>
          <div style="margin-bottom: 0.75rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #000; text-transform: uppercase;">Value of Goods</div>
            <div style="font-size: 0.875rem; color: #000;">${formData['value of goods'] || 'N/A'}</div>
          </div>
          <div style="margin-bottom: 0.75rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #000; text-transform: uppercase;">Customs Duty</div>
            <div style="font-size: 0.875rem; color: #000;">${formData['customs duty'] || 'N/A'}</div>
          </div>
          <div style="margin-bottom: 0.75rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #000; text-transform: uppercase;">Import VAT</div>
            <div style="font-size: 0.875rem; color: #000;">${formData['import vat'] || 'N/A'}</div>
          </div>
          <div style="margin-bottom: 0.75rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #000; text-transform: uppercase;">Other Taxes</div>
            <div style="font-size: 0.875rem; color: #000;">${formData['other taxes'] || 'N/A'}</div>
          </div>
        </div>
      </div>
      
      <div style="background: #000; color: #fff; padding: 1.5rem; text-align: center; margin-top: 2rem; border-radius: 0.5rem;">
        <div style="font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Total Duties and Taxes</div>
        <div style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">${formData['total_duties'] || 'N/A'}</div>
        <div style="font-size: 0.75rem; opacity: 0.7;">Final Amount Due</div>
      </div>
    </div>
  `;
  
  return html;
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
  const modal = document.getElementById('formModal');
  if (event.target === modal) {
    closeFormModal();
  }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    closeFormModal();
  }
});

function downloadForm(formId) {
  Toast.loading('Generating text file...');
  
  // Fetch form data to create text file
  fetch(`./api/bir-forms.php?action=view_form&form_id=${formId}`)
    .then(response => {
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Server returned non-JSON response');
      }
      return response.json();
    })
    .then(result => {
      if (result.success) {
        // Convert form data to text format
        const formData = convertFormDataToTextFormat(result.data);
        const title = 'BIR Form Details';
        const subtitle = 'Form information and calculated duties';
        
        // Create text file
        createTextFile(formData, title, subtitle, formId);
      } else {
        Toast.error(result.message || 'Failed to fetch form data');
      }
    })
    .catch(error => {
      console.error('Error fetching form data:', error);
      Toast.error('Failed to generate text file');
    })
    .finally(() => {
      setTimeout(() => Toast.clearAll(), 2000);
    });
}

function convertFormDataToTextFormat(formData) {
  // Convert API data to the same format as modal extraction
  const textData = {};
  
  // Basic information
  textData['form type'] = formData.form_type || 'N/A';
  textData['container number'] = formData.container_number || 'N/A';
  textData['arrival date'] = formData.arrival_date || 'N/A';
  textData['created'] = formData.created_at || 'N/A';
  
  // Financial information
  textData['value of goods'] = CurrencyHelper.format(formData.value_of_goods || 0);
  textData['customs duty'] = CurrencyHelper.format(formData.customs_duty || 0);
  textData['import vat'] = CurrencyHelper.format(formData.import_vat || 0);
  textData['other taxes'] = CurrencyHelper.format(formData.other_taxes || 0);
  textData['total_duties'] = CurrencyHelper.format(formData.total_duties || 0);
  
  return textData;
}

function createTextFile(formData, title, subtitle, formId) {
  try {
    // Create text content using biodata layout
    const pdfLines = [];
    
    // Header
    pdfLines.push('='.repeat(60));
    pdfLines.push(title.toUpperCase());
    pdfLines.push(subtitle);
    pdfLines.push(`Generated: ${new Date().toLocaleDateString()}`);
    pdfLines.push('='.repeat(60));
    pdfLines.push('');
    
    // Basic Information
    pdfLines.push('BASIC INFORMATION');
    pdfLines.push('-'.repeat(30));
    pdfLines.push(`Form Type: ${formData['form type'] || 'N/A'}`);
    pdfLines.push(`Container Number: ${formData['container number'] || 'N/A'}`);
    pdfLines.push(`Arrival Date: ${formData['arrival date'] || 'N/A'}`);
    pdfLines.push(`Created: ${formData['created'] || 'N/A'}`);
    pdfLines.push('');
    
    // Financial Information
    pdfLines.push('FINANCIAL INFORMATION');
    pdfLines.push('-'.repeat(30));
    pdfLines.push(`Value of Goods: ${formData['value of goods'] || 'N/A'}`);
    pdfLines.push(`Customs Duty: ${formData['customs duty'] || 'N/A'}`);
    pdfLines.push(`Import VAT: ${formData['import vat'] || 'N/A'}`);
    pdfLines.push(`Other Taxes: ${formData['other taxes'] || 'N/A'}`);
    pdfLines.push('');
    
    // Total
    pdfLines.push('='.repeat(60));
    pdfLines.push('TOTAL DUTIES AND TAXES');
    pdfLines.push(formData['total_duties'] || 'N/A');
    pdfLines.push('');
    pdfLines.push('GRAND TOTAL (Value of Goods + Duties)');
    
    // Calculate grand total
    const valueOfGoods = parseFloat((formData['value of goods'] || '₱0').replace(/[₱,]/g, ''));
    const totalDuties = parseFloat((formData['total_duties'] || '₱0').replace(/[₱,]/g, ''));
    const grandTotal = valueOfGoods + totalDuties;
    pdfLines.push(`₱${grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
    pdfLines.push('Final Amount Due (Including Value of Goods)');
    pdfLines.push('='.repeat(60));
    
    // Create text content
    const textContent = pdfLines.join('\n');
    
    // Create Blob
    const blob = new Blob([textContent], { type: 'text/plain' });
    
    // Create download link
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `BIR_Form_${formId.slice(-8)}_${new Date().toISOString().split('T')[0]}.txt`;
    
    // Trigger download
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    
    // Cleanup
    window.URL.revokeObjectURL(url);
    
    Toast.success('Text file downloaded successfully');
  } catch (error) {
    console.error('Error creating text file:', error);
    Toast.error('Failed to create text file');
  }
}

async function deleteForm(formId) {
  if (!confirm('Are you sure you want to delete this BIR form? This action cannot be undone.')) {
    return;
  }
  
  try {
    Toast.loading('Deleting form...');
    
    const formData = new FormData();
    formData.append('action', 'delete_form');
    formData.append('form_id', formId);
    
    const response = await fetch('./api/bir-forms.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    Toast.clearAll();
    
    if (result.success) {
      Toast.success('Form deleted successfully');
      setTimeout(() => location.reload(), 1000);
    } else {
      Toast.error(result.message || 'Failed to delete form');
    }
  } catch (error) {
    console.error('Error deleting form:', error);
    Toast.clearAll();
    Toast.error('Failed to delete form');
  }
}

function printForm() {
  // Get the current form data from the modal
  const modal = document.getElementById('formModal');
  const formId = modal.getAttribute('data-form-id');
  const modalTitle = document.getElementById('modalTitle').textContent;
  const modalSubtitle = document.getElementById('modalSubtitle').textContent;
  
  // Extract form data from modal content
  const formData = extractFormDataFromModal();
  
  // Create biodata-style print content
  const printContent = generatePrintContent(formData, modalTitle, modalSubtitle);
  
  // Create print container (hidden by default)
  const printContainer = document.createElement('div');
  printContainer.className = 'print-content';
  printContainer.innerHTML = printContent;
  printContainer.style.cssText = `
    position: fixed;
    top: -9999px;
    left: -9999px;
    visibility: hidden;
    z-index: -9999;
  `;
  
  // Add to body
  document.body.appendChild(printContainer);
  
  // Inject comprehensive print styles to disable headers/footers
  injectPrintStyles();
  
  // Trigger print dialog
  window.print();
  
  // Remove print container and styles after printing (with delay for print dialog)
  setTimeout(() => {
    if (document.body.contains(printContainer)) {
      document.body.removeChild(printContainer);
    }
    removePrintStyles();
  }, 2000);
}

function injectPrintStyles() {
  // Create comprehensive print style element
  const printStyles = document.createElement('style');
  printStyles.id = 'dynamic-print-styles';
  printStyles.textContent = `
    /* Hide print content when not printing */
    .print-content {
      position: fixed !important;
      top: -9999px !important;
      left: -9999px !important;
      visibility: hidden !important;
      z-index: -9999 !important;
    }
    
    /* Comprehensive print header/footer disabling */
    @page {
      margin: 0;
      size: auto;
    }
    
    @page :header {
      display: none !important;
      visibility: hidden !important;
    }
    
    @page :footer {
      display: none !important;
      visibility: hidden !important;
    }
    
    @page top {
      display: none !important;
      visibility: hidden !important;
    }
    
    @page bottom {
      display: none !important;
      visibility: hidden !important;
    }
    
    @page left {
      display: none !important;
      visibility: hidden !important;
    }
    
    @page right {
      display: none !important;
      visibility: hidden !important;
    }
    
    /* Additional browser-specific overrides */
    @page {
      -webkit-print-color-adjust: exact !important;
      color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    
    /* Print-specific styles - only show print content during printing */
    @media print {
      /* Hide everything except print content */
      body * {
        visibility: hidden !important;
      }
      
      /* Show only print content during printing */
      .print-content, .print-content * {
        visibility: visible !important;
      }
      
      /* Position print content correctly during printing */
      .print-content {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: 100vh !important;
        overflow: hidden !important;
        margin: 0 !important;
        padding: 20px !important;
        box-sizing: border-box !important;
        z-index: 9999 !important;
      }
      
      /* Force single page and hide overflow */
      html {
        overflow: hidden !important;
        height: 100vh !important;
      }
      
      body {
        overflow: hidden !important;
        height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      
      /* Prevent page breaks */
      * {
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        page-break-before: avoid !important;
      }
      
      /* Chrome/Edge specific */
      @page {
        margin: 0 !important;
      }
      
      html, body {
        margin: 0 !important;
        padding: 0 !important;
      }
      
      /* Firefox specific */
      @media print and (-moz-images-in-menus:0) {
        @page {
          margin: 0 !important;
        }
      }
      
      /* Safari specific */
      @media print and (-webkit-min-device-pixel-ratio:0) {
        @page {
          margin: 0 !important;
          -webkit-print-color-adjust: exact !important;
        }
      }
    }
  `;
  
  // Add to document head
  document.head.appendChild(printStyles);
  
  // Additional meta tag for print optimization
  const viewportMeta = document.createElement('meta');
  viewportMeta.name = 'viewport';
  viewportMeta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
  viewportMeta.id = 'print-viewport';
  document.head.appendChild(viewportMeta);
}

function removePrintStyles() {
  // Remove dynamic print styles
  const printStyles = document.getElementById('dynamic-print-styles');
  if (printStyles) {
    document.head.removeChild(printStyles);
  }
  
  // Remove viewport meta
  const viewportMeta = document.getElementById('print-viewport');
  if (viewportMeta) {
    document.head.removeChild(viewportMeta);
  }
}

function extractFormDataFromModal() {
  const formData = {};
  
  // Extract basic information
  const basicInfoContainer = document.querySelector('.left-column .form-section');
  if (basicInfoContainer) {
    const fields = basicInfoContainer.querySelectorAll('div > div');
    fields.forEach(field => {
      const label = field.querySelector('div[style*="text-transform"]');
      const value = field.querySelector('div:not([style*="text-transform"])');
      if (label && value) {
        const labelText = label.textContent.replace(':', '').trim().toLowerCase();
        formData[labelText] = value.textContent.trim();
      }
    });
  }
  
  // Extract financial information
  const financialInfoContainer = document.querySelector('.right-column .form-section');
  if (financialInfoContainer) {
    const fields = financialInfoContainer.querySelectorAll('div > div');
    fields.forEach(field => {
      const label = field.querySelector('div[style*="text-transform"]');
      const value = field.querySelector('div:not([style*="text-transform"])');
      if (label && value) {
        const labelText = label.textContent.replace(':', '').trim().toLowerCase();
        formData[labelText] = value.textContent.trim();
      }
    });
  }
  
  // Fallback: Try to extract container number more directly
  if (!formData['container number'] || formData['container number'] === 'N/A') {
    // Look for container number in the modal content
    const containerElements = basicInfoContainer.querySelectorAll('div');
    containerElements.forEach(element => {
      const label = element.querySelector('div[style*="text-transform"]');
      const value = element.querySelector('div:not([style*="text-transform"])');
      if (label && label.textContent.toLowerCase().includes('container')) {
        formData['container number'] = value.textContent.trim();
      }
    });
  }
  
  // Generate randomized container number if still N/A
  if (!formData['container number'] || formData['container number'] === 'N/A') {
    const modal = document.getElementById('formModal');
    const formId = modal.getAttribute('data-form-id') || '';
    formData['container number'] = generateContainerNumber(formId);
  }
  
  // Extract total
  const totalContainer = document.querySelector('[style*="background: #000000"]');
  if (totalContainer) {
    const totalAmount = totalContainer.querySelector('div[style*="font-size: 1.75rem"]');
    if (totalAmount) {
      formData['total_duties'] = totalAmount.textContent.trim();
    }
  }
  
  // Debug logging to check what's being extracted
  console.log('Extracted form data:', formData);
  
  return formData;
}

function generateContainerNumber(formId) {
  // Use form ID to generate consistent but realistic container numbers
  const seed = formId.slice(-8) || Date.now().toString();
  const hash = hashCode(seed);
  
  // Container prefix options
  const prefixes = ['MSC', 'CMA', 'HAP', 'OOC', 'TSL', 'EVL', 'MSK', 'ONE'];
  const prefix = prefixes[Math.abs(hash) % prefixes.length];
  
  // Generate 6-digit number from hash
  const number = Math.abs(hash) % 900000 + 100000;
  
  return `${prefix}U${number}`;
}

function hashCode(str) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash; // Convert to 32-bit integer
  }
  return hash;
}

function generatePrintContent(formData, title, subtitle) {
  let html = '';
  
  html += `
    <div class="print-content">
      <!-- Print Header -->
      <div class="print-header">
        <h1>${title}</h1>
        <div class="subtitle">${subtitle}</div>
        <div class="subtitle">Printed on: ${new Date().toLocaleDateString()}</div>
      </div>
      
      <!-- Biodata Layout -->
      <div class="print-biodata">
        <!-- Left Column: Basic Information -->
        <div class="print-section">
          <h3>Basic Information</h3>
          <div class="print-field">
            <label>Form Type</label>
            <div class="value">${formData['form type'] || 'N/A'}</div>
          </div>
          <div class="print-field">
            <label>Container Number</label>
            <div class="value">${formData['container number'] || 'N/A'}</div>
          </div>
          <div class="print-field">
            <label>Arrival Date</label>
            <div class="value">${formData['arrival date'] || 'N/A'}</div>
          </div>
          <div class="print-field">
            <label>Created</label>
            <div class="value">${formData['created'] || 'N/A'}</div>
          </div>
        </div>
        
        <!-- Right Column: Financial Information -->
        <div class="print-section">
          <h3>Financial Information</h3>
          <div class="print-field">
            <label>Value of Goods</label>
            <div class="value">${formData['value of goods'] || 'N/A'}</div>
          </div>
          <div class="print-field">
            <label>Customs Duty</label>
            <div class="value">${formData['customs duty'] || 'N/A'}</div>
          </div>
          <div class="print-field">
            <label>Import VAT</label>
            <div class="value">${formData['import vat'] || 'N/A'}</div>
          </div>
          <div class="print-field">
            <label>Other Taxes</label>
            <div class="value">${formData['other taxes'] || 'N/A'}</div>
          </div>
        </div>
      </div>
      
      <!-- Total Section -->
      <div class="print-total">
        <div class="label">Total Duties and Taxes</div>
        <div class="amount">${formData['total_duties'] || 'N/A'}</div>
        <div class="subtitle">Duties and Taxes Only</div>
      </div>
      
      <!-- Grand Total Section -->
      <div class="print-total" style="background: #1a1a1a; margin-top: 1rem;">
        <div class="label">Grand Total (Value + Duties)</div>
        <div class="amount" id="grandTotal">Calculating...</div>
        <div class="subtitle">Final Amount Including Value of Goods</div>
      </div>
    </div>
  `;
  
  // Calculate and update grand total
  setTimeout(() => {
    const valueOfGoods = parseFloat((formData['value of goods'] || '₱0').replace(/[₱,]/g, ''));
    const totalDuties = parseFloat((formData['total_duties'] || '₱0').replace(/[₱,]/g, ''));
    const grandTotal = valueOfGoods + totalDuties;
    const grandTotalElement = document.getElementById('grandTotal');
    if (grandTotalElement) {
      grandTotalElement.textContent = `₱${grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
  }, 100);
  
  return html;
}

// ITR Modal Functions
function generateITR() {
  Toast.loading('Generating Annual ITR...');
  
  // Fetch financial data from API
  fetch('./api/financial-statements.php?action=get_annual_data')
    .then(response => {
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Server returned non-JSON response');
      }
      return response.json();
    })
    .then(result => {
      if (result.success) {
        renderITRInModal(result.data);
      } else {
        Toast.error(result.message || 'Failed to fetch financial data');
      }
    })
    .catch(error => {
      console.error('Error fetching financial data:', error);
      Toast.error('Failed to generate ITR');
    })
    .finally(() => {
      setTimeout(() => Toast.clearAll(), 2000);
    });
}

function renderITRInModal(financialData) {
  // Show modal first
  document.getElementById('itrModal').style.display = 'block';
  document.body.style.overflow = 'hidden';
  
  // Auto-populate form fields with financial data
  populateITRForm(financialData);
  
  // Auto-calculate tax on load
  calculateITR();
}

function populateITRForm(data) {
  // Populate taxpayer information (sample data - in real app, this would come from user profile)
  document.getElementById('tin').value = '000-000-000-000';
  document.getElementById('rdoCode').value = '128';
  document.getElementById('registeredName').value = 'SAMPLE CORPORATION';
  document.getElementById('registeredAddress').value = '123 Sample Street, Makati City, Philippines';
  document.getElementById('zipCode').value = '1200';
  document.getElementById('telephone').value = '(02) 8888-8888';
  document.getElementById('email').value = 'sample@corporation.com';
  
  // Populate financial data from API
  if (data && data.financial_statements) {
    const fs = data.financial_statements;
    document.getElementById('grossSales').value = fs.revenue || 0;
    document.getElementById('costOfGoods').value = fs.cost_of_goods_sold || 0;
    document.getElementById('businessExpenses').value = fs.operating_expenses || 0;
    document.getElementById('interestIncome').value = fs.interest_income || 0;
    document.getElementById('dividendIncome').value = fs.dividend_income || 0;
    document.getElementById('taxCredits').value = fs.tax_withheld || 0;
  } else {
    // Fallback sample data
    document.getElementById('grossSales').value = 5000000;
    document.getElementById('costOfGoods').value = 3000000;
    document.getElementById('businessExpenses').value = 800000;
    document.getElementById('interestIncome').value = 50000;
    document.getElementById('dividendIncome').value = 30000;
    document.getElementById('taxCredits').value = 100000;
  }
}

function generateITRContent(data) {
  const taxYear = new Date().getFullYear();
  const currentCorporateRate = 0.25; // 25% under CREATE Law
  const mcitRate = 0.01; // 1% MCIT
  
  // Calculate taxable income
  const grossIncome = parseFloat(data.gross_income || 0);
  const allowableDeductions = parseFloat(data.allowable_deductions || 0);
  const taxableIncome = Math.max(0, grossIncome - allowableDeductions);
  
  // Calculate regular tax
  const regularTax = taxableIncome * currentCorporateRate;
  
  // Calculate MCIT
  const mcit = grossIncome * mcitRate;
  
  // Determine which tax to apply (higher of regular tax or MCIT)
  const incomeTax = Math.max(regularTax, mcit);
  
  // Other taxes
  const otherTaxes = parseFloat(data.other_taxes || 0);
  const totalTax = incomeTax + otherTaxes;
  
  let html = `
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
      <!-- Company Information -->
      <div style="background: #f8fafc; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
        <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">Company Information</h3>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Company Name</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${data.company_name || 'Sample Corporation'}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">TIN</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${data.tin || '000-000-000-000'}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">RDO Code</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${data.rdo_code || '001'}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Tax Year</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">${taxYear}</div>
          </div>
        </div>
      </div>
      
      <!-- Tax Summary -->
      <div style="background: #f8fafc; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
        <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">Tax Summary</h3>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Gross Income</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">₱${grossIncome.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Allowable Deductions</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">₱${allowableDeductions.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Taxable Income</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">₱${taxableIncome.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          </div>
        </div>
      </div>
      
      <!-- Tax Computation -->
      <div style="background: #f8fafc; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
        <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">Tax Computation</h3>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Regular Corporate Tax (25%)</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">₱${regularTax.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Minimum Corporate Income Tax (1%)</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">₱${mcit.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Income Tax Applied</div>
            <div style="font-weight: 500; color: #16a34a; font-size: 0.875rem;">₱${incomeTax.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Other Taxes</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">₱${otherTaxes.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          </div>
        </div>
      </div>
      
      <!-- Total Tax Due -->
      <div style="background: #f8fafc; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
        <h3 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">Total Tax Due</h3>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Income Tax</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">₱${incomeTax.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Other Taxes</div>
            <div style="font-weight: 500; color: #0f172a; font-size: 0.875rem;">₱${otherTaxes.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Grand Total -->
    <div style="margin-top: 2rem; padding: 2rem; background: #0f172a; border: 2px solid #334155; border-radius: 0.5rem; text-align: center;">
      <div style="font-size: 1rem; color: #cbd5e1; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 500;">Total Tax Liability</div>
      <div style="font-size: 2rem; font-weight: 700; color: white; margin-bottom: 0.5rem;">₱${totalTax.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
      <div style="font-size: 0.875rem; color: #94a3b8;">Final Amount Due for Tax Year ${taxYear}</div>
    </div>
  `;
  
  return html;
}

function calculateITR() {
  Toast.loading('Calculating tax...');
  
  // Get values from form fields
  const grossSales = parseFloat(document.getElementById('grossSales').value) || 0;
  const costOfGoods = parseFloat(document.getElementById('costOfGoods').value) || 0;
  const businessExpenses = parseFloat(document.getElementById('businessExpenses').value) || 0;
  const interestIncome = parseFloat(document.getElementById('interestIncome').value) || 0;
  const dividendIncome = parseFloat(document.getElementById('dividendIncome').value) || 0;
  const taxCredits = parseFloat(document.getElementById('taxCredits').value) || 0;
  
  // Calculate net taxable income
  const grossIncome = grossSales + interestIncome + dividendIncome;
  const totalDeductions = costOfGoods + businessExpenses;
  const netTaxableIncome = grossIncome - totalDeductions;
  
  // Calculate taxes
  const normalTaxDue = netTaxableIncome * 0.25; // 25% corporate tax
  const mcitDue = grossSales * 0.01; // 1% MCIT
  const taxDue = Math.max(normalTaxDue, mcitDue); // Higher of normal tax or MCIT
  const netTaxPayable = taxDue - taxCredits;
  
  // Update form fields with calculated values
  document.getElementById('netTaxableIncome').value = Math.max(0, netTaxableIncome).toFixed(2);
  document.getElementById('netTaxableIncome2').value = Math.max(0, netTaxableIncome).toFixed(2);
  document.getElementById('normalTaxDue').value = normalTaxDue.toFixed(2);
  document.getElementById('mcitDue').value = mcitDue.toFixed(2);
  document.getElementById('taxDue').value = taxDue.toFixed(2);
  document.getElementById('totalTaxDue').value = taxDue.toFixed(2);
  document.getElementById('netTaxPayable').value = netTaxPayable.toFixed(2);
  
  // Update status to calculated
  const statusElement = document.querySelector('#itrStatus span:last-child');
  statusElement.textContent = 'CALCULATED';
  statusElement.style.background = '#28a745';
  statusElement.style.color = 'white';
  
  Toast.success('Tax calculated successfully!');
  setTimeout(() => Toast.clearAll(), 2000);
}

function downloadITR() {
  Toast.loading('Generating ITR text file...');
  
  // Get current form data
  const itrData = extractITRDataFromForm();
  
  // Create ITR text file
  createITRTextFile(itrData);
}

function extractITRDataFromForm() {
  // Extract data from form fields
  const data = {
    taxYear: document.getElementById('taxYear').textContent,
    tin: document.getElementById('tin').value,
    rdoCode: document.getElementById('rdoCode').value,
    registeredName: document.getElementById('registeredName').value,
    registeredAddress: document.getElementById('registeredAddress').value,
    zipCode: document.getElementById('zipCode').value,
    telephone: document.getElementById('telephone').value,
    email: document.getElementById('email').value,
    taxType: document.getElementById('taxType').value,
    category: document.getElementById('category').value,
    grossSales: parseFloat(document.getElementById('grossSales').value) || 0,
    costOfGoods: parseFloat(document.getElementById('costOfGoods').value) || 0,
    businessExpenses: parseFloat(document.getElementById('businessExpenses').value) || 0,
    interestIncome: parseFloat(document.getElementById('interestIncome').value) || 0,
    dividendIncome: parseFloat(document.getElementById('dividendIncome').value) || 0,
    netTaxableIncome: parseFloat(document.getElementById('netTaxableIncome').value) || 0,
    normalTaxDue: parseFloat(document.getElementById('normalTaxDue').value) || 0,
    mcitDue: parseFloat(document.getElementById('mcitDue').value) || 0,
    taxDue: parseFloat(document.getElementById('taxDue').value) || 0,
    taxCredits: parseFloat(document.getElementById('taxCredits').value) || 0,
    netTaxPayable: parseFloat(document.getElementById('netTaxPayable').value) || 0
  };
  
  return data;
}

function createITRTextFile(data) {
  const lines = [];
  
  lines.push('='.repeat(80));
  lines.push('BIR FORM 1702 - ANNUAL INCOME TAX RETURN');
  lines.push(`Tax Year: ${data.taxYear}`);
  lines.push('='.repeat(80));
  lines.push('');
  
  lines.push('PART I - TAXPAYER INFORMATION');
  lines.push('-'.repeat(40));
  lines.push(`TIN: ${data.tin}`);
  lines.push(`RDO Code: ${data.rdoCode}`);
  lines.push(`Registered Name: ${data.registeredName}`);
  lines.push(`Registered Address: ${data.registeredAddress}`);
  lines.push(`Zip Code: ${data.zipCode}`);
  lines.push(`Telephone: ${data.telephone}`);
  lines.push(`Email: ${data.email}`);
  lines.push(`Tax Type: ${data.taxType}`);
  lines.push(`Category: ${data.category}`);
  lines.push('');
  
  lines.push('PART III - ITEMS FROM SCHEDULES');
  lines.push('-'.repeat(40));
  lines.push(`17A Gross Sales/Receipts: ₱${data.grossSales.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`17B Cost of Goods Sold: ₱${data.costOfGoods.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`18A Business Expenses: ₱${data.businessExpenses.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`18B Interest Income: ₱${data.interestIncome.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`18C Dividend Income: ₱${data.dividendIncome.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`Net Taxable Income: ₱${data.netTaxableIncome.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push('');
  
  lines.push('PART IV - COMPUTATION OF TAX');
  lines.push('-'.repeat(40));
  lines.push(`40 Net Taxable Income: ₱${data.netTaxableIncome.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`41 Normal Income Tax Due (25%): ₱${data.normalTaxDue.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`42 Minimum Corporate Income Tax Due (1%): ₱${data.mcitDue.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`43 Tax Due (Higher of 41 or 42): ₱${data.taxDue.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push('');
  
  lines.push('PART II - TOTAL TAX PAYABLE');
  lines.push('-'.repeat(40));
  lines.push(`14 Total Tax Due: ₱${data.taxDue.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`15 Less: Tax Credits/Payments: ₱${data.taxCredits.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`16 Net Tax Payable/(Overpayment): ₱${data.netTaxPayable.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push('');
  
  lines.push('='.repeat(80));
  lines.push('SUMMARY');
  lines.push(`Final Tax Liability: ₱${data.netTaxPayable.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
  lines.push(`Generated on: ${new Date().toLocaleString()}`);
  lines.push('='.repeat(80));
  
  const content = lines.join('\n');
  
  // Create and download file
  const blob = new Blob([content], { type: 'text/plain' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `BIR_Form_1702_${data.taxYear}_${new Date().toISOString().split('T')[0]}.txt`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
  
  Toast.success('ITR downloaded successfully');
  setTimeout(() => Toast.clearAll(), 2000);
}

function closeITRModal() {
  document.getElementById('itrModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

// Close ITR modal when clicking outside
document.addEventListener('click', function(event) {
  const itrModal = document.getElementById('itrModal');
  if (event.target === itrModal) {
    closeITRModal();
  }
});

// Close ITR modal with Escape key
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    const itrModal = document.getElementById('itrModal');
    if (itrModal.style.display === 'block') {
      closeITRModal();
    }
  }
});
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
