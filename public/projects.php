<?php
/**
 * Projects Module - LedgerSMB Feature
 * Track projects, milestones, and profitability
 */

require_once __DIR__ . '/../vendor/autoload.php';
use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Model\Project;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Real projects from database
$projectModel = new Project();
try {
    $projects = $projectModel->getAll();
} catch (\Exception $e) {
    $projects = [];
}

// Derived metrics
$activeProjectsCount = count(array_filter($projects, fn($p) => ($p['status'] ?? '') === 'active'));
$totalBudget = array_sum(array_map(fn($p) => (float)($p['budget'] ?? 0), $projects));
$totalSpent = array_sum(array_map(fn($p) => (float)($p['spent'] ?? 0), $projects));
$totalRemaining = max(0, $totalBudget - $totalSpent);

$pageTitle = 'Projects';
ob_start();
?>

<!-- Page Banner -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Project Management</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>Status:</strong>
          <span class="status-indicator"><span class="status-dot"></span>Online</span>
        </div>
        <div class="page-banner-meta-item">
          <strong>User:</strong>
          <?php echo htmlspecialchars($user['username'] ?? 'Unknown'); ?>
        </div>
        <div class="page-banner-meta-item">
          <strong>Active Projects:</strong>
          <span class="font-semibold"><?php echo number_format($activeProjectsCount); ?></span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Currency:</strong>
          <span class="font-medium"><?php echo CurrencyHelper::symbol() . ' ' . CurrencyHelper::getCurrentCurrency(); ?></span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <button class="btn btn-success" onclick="showToast('New Project form', 'info')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        New Project
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

<!-- Stats -->
<div class="grid grid-cols-4 mb-6">
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Total Projects</p>
      <p class="stat-value"><?php echo count($projects); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Total Budget</p>
      <p class="stat-value"><?php echo CurrencyHelper::format($totalBudget, 0); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Total Spent</p>
      <p class="stat-value text-warning"><?php echo CurrencyHelper::format($totalSpent, 0); ?></p>
    </div>
  </div>
  <div class="card stat-card">
    <div class="card-content">
      <p class="stat-label">Remaining</p>
      <p class="stat-value text-success"><?php echo CurrencyHelper::format($totalRemaining, 0); ?></p>
    </div>
  </div>
</div>

<!-- Projects Table -->
<div class="table-container">
  <table class="data-table">
    <thead>
      <tr>
        <th>Project ID</th>
        <th>Name</th>
        <th>Client</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Budget</th>
        <th>Spent</th>
        <th>Progress</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($projects as $project): 
        $progress = ($project['spent'] / $project['budget']) * 100;
      ?>
      <tr>
        <td class="font-mono font-medium"><?php echo $project['id']; ?></td>
        <td class="font-medium"><?php echo $project['name']; ?></td>
        <td><?php echo $project['client']; ?></td>
        <td><?php echo date('M d, Y', strtotime($project['start'])); ?></td>
        <td><?php echo date('M d, Y', strtotime($project['end'])); ?></td>
        <td class="font-semibold"><?php echo CurrencyHelper::format($project['budget'], 0); ?></td>
        <td><?php echo CurrencyHelper::format($project['spent'], 0); ?></td>
        <td>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="flex: 1; height: 6px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden;">
              <div style="height: 100%; width: <?php echo min($progress, 100); ?>%; background: <?php echo $progress > 90 ? 'var(--color-danger)' : 'var(--color-success)'; ?>;"></div>
            </div>
            <span class="text-xs text-secondary"><?php echo round($progress); ?>%</span>
          </div>
        </td>
        <td>
          <span class="badge <?php echo $project['status'] === 'active' ? 'badge-success' : 'badge-default'; ?>"><?php echo ucfirst($project['status']); ?></span>
        </td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="showToast('View project details', 'info')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            </svg>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
