<?php
/**
 * Projects Module
 * Tracks, manages, and monitors projects
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
</style>

<div style="background: #7194A5; color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
  <div class="container">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 32px; backdrop-filter: blur(10px);">
        📊
      </div>
      <div style="flex: 1;">
        <h1 style="font-size: 1.875rem; font-weight: 700; margin: 0 0 0.25rem 0; color: white;">Project Management</h1>
        <p style="font-size: 0.875rem; margin: 0; opacity: 0.9;">Track projects, milestones, budgets, and profitability</p>
      </div>
      <button onclick="showNewProjectModal()" style="padding: 0.625rem 1.5rem; background: rgba(255,255,255,0.95); border: none; border-radius: 8px; color: #7194A5; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.background='white'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.95)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
        New Project
      </button>
      <a href="dashboard.php" style="padding: 0.625rem 1.25rem; background: rgba(255,255,255,0.2); border-radius: 8px; color: white; text-decoration: none; font-weight: 500; backdrop-filter: blur(10px); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        ← Dashboard
      </a>
    </div>
  </div>
</div>

<div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.875rem; margin-bottom: 1.5rem;">
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Projects</p>
      <div style="width: 36px; height: 36px; background: hsl(214 95% 93%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0;"><?php echo count($projects); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Active Projects</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2"><path d="M22 11.08V12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C15.7824 2 18.9935 4.19066 20.4866 7.35397M22 4L12 14.01L9 11.01"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;"><?php echo $activeProjectsCount; ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Budget</p>
      <div style="width: 36px; height: 36px; background: rgba(113,148,165,0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: #7194A5; margin: 0;"><?php echo CurrencyHelper::format($totalBudget, 0); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Spent</p>
      <div style="width: 36px; height: 36px; background: hsl(48 96% 89%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2"><path d="M12 8V12L15 15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(25 95% 16%); margin: 0;"><?php echo CurrencyHelper::format($totalSpent, 0); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Remaining</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2"><path d="M12 8V16M8 12H16M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;"><?php echo CurrencyHelper::format($totalRemaining, 0); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Avg Progress</p>
      <div style="width: 36px; height: 36px; background: hsl(262 83% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(263 70% 26%)" stroke-width="2"><path d="M9 11L12 14L22 4M21 12V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H16"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(263 70% 26%); margin: 0;"><?php echo round(count($projects) > 0 ? ($totalSpent / $totalBudget * 100) : 0); ?>%</p>
  </div>
</div>

<div class="toolbar">
  <div class="toolbar-left">
    <div class="search-wrapper">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input type="search" class="search-input" placeholder="Search projects..." id="project-search">
    </div>
  </div>
  <div class="toolbar-right">
    <select class="form-select" id="status-filter">
      <option value="all">All Status</option>
      <option value="active">Active</option>
      <option value="completed">Completed</option>
      <option value="on-hold">On Hold</option>
    </select>
    <button class="btn btn-ghost btn-icon" onclick="alert('Export')" title="Export">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
</div>

<div id="projectsTableContainer" class="table-container" style="display: <?php echo empty($projects) ? 'none' : 'block'; ?>;">
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
            <div style="flex: 1; height: 6px; background: #e5e7eb; border-radius: 999px; overflow: hidden;">
              <div style="height: 100%; width: <?php echo min($progress, 100); ?>%; background: <?php echo $progress > 90 ? '#dc2626' : '#10b981'; ?>;"></div>
            </div>
            <span style="font-size: 0.75rem; color: #6b7280;"><?php echo round($progress); ?>%</span>
          </div>
        </td>
        <td>
          <span class="badge <?php echo $project['status'] === 'active' ? 'badge-success' : 'badge-default'; ?>"><?php echo ucfirst($project['status']); ?></span>
        </td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="alert('View project')" title="View">
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

<div id="emptyStateContainer" style="display: <?php echo empty($projects) ? 'block' : 'none'; ?>; padding: 4rem 2rem; text-align: center; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
  <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#6b7280" style="opacity: 0.15; margin: 0 auto 1.5rem; stroke-width: 1.5;">
    <rect x="3" y="3" width="18" height="18" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <h3 style="font-size: 1.25rem; font-weight: 600; color: #111827; margin: 0 0 0.75rem 0;">No projects yet</h3>
  <p style="font-size: 0.9375rem; color: #6b7280; margin: 0 auto 1.5rem; max-width: 28rem; line-height: 1.6;">
    Get started by creating your first project. Click the "New Project" button above to begin.
  </p>
  <button onclick="showNewProjectModal()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5V19M5 12H19" stroke-linecap="round"/></svg>
    Create Your First Project
  </button>
</div>

<div id="newProjectModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
  <div style="background: white; border-radius: 12px; width: 92%; max-width: 1140px; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); animation: slideUp 0.3s ease; display: flex; flex-direction: column;">
    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid hsl(214 20% 92%); display: flex; align-items: center; justify-content: space-between; background: white; z-index: 10; flex-shrink: 0;">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(113,148,165,0.25);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z"/><path d="M9 22V12H15V22"/></svg>
        </div>
        <div>
          <h2 style="font-size: 1.25rem; font-weight: 700; color: hsl(222 47% 17%); margin: 0; letter-spacing: -0.02em;">Create New Project</h2>
          <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0; font-weight: 500;">Define scope, tasks, team, and budget</p>
        </div>
      </div>
      <button onclick="closeNewProjectModal()" style="padding: 0.625rem; background: hsl(0 0% 98%); border: 1px solid hsl(214 20% 92%); cursor: pointer; border-radius: 8px; transition: all 0.2s; color: hsl(215 16% 47%);" onmouseover="this.style.background='hsl(0 0% 95%)'; this.style.borderColor='hsl(214 20% 85%)'" onmouseout="this.style.background='hsl(0 0% 98%)'; this.style.borderColor='hsl(214 20% 92%)'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M18 6L6 18M6 6L18 18" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>

    <form id="newProjectForm" style="padding: 0; display: flex; flex-direction: column; flex: 1; min-height: 0;">
      <div id="projectGrid" style="display: grid; grid-template-columns: minmax(0,1fr) 340px; gap: 1.5rem; padding: 1.25rem 1.5rem; flex: 1; overflow-y: auto; align-items: start;">
        <div style="display: flex; flex-direction: column; min-height: 0;">
          <div style="display: flex; background: hsl(240 5% 96%); border-bottom: 2px solid hsl(240 6% 90%); padding: 0.375rem; gap: 0.375rem; overflow-x: auto; flex-shrink: 0; margin-bottom: 1rem; border-radius: 10px;">
            <button type="button" class="project-tab-btn" data-tab="details" onclick="switchProjectTab('details')" style="padding: 0.625rem 1.125rem; border: none; background: white; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-weight: 600; cursor: pointer; white-space: nowrap; color: #7194A5; font-size: 0.8125rem; transition: all 0.2s;">📋 Details</button>
            <button type="button" class="project-tab-btn" data-tab="tasks" onclick="switchProjectTab('tasks')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">✅ Tasks</button>
            <button type="button" class="project-tab-btn" data-tab="milestones" onclick="switchProjectTab('milestones')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">🏁 Milestones</button>
            <button type="button" class="project-tab-btn" data-tab="team" onclick="switchProjectTab('team')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">👥 Team</button>
            <button type="button" class="project-tab-btn" data-tab="time" onclick="switchProjectTab('time')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">⏱ Time</button>
            <button type="button" class="project-tab-btn" data-tab="files" onclick="switchProjectTab('files')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">📎 Files</button>
            <button type="button" class="project-tab-btn" data-tab="comms" onclick="switchProjectTab('comms')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">💬 Comms</button>
          </div>

          <div id="project-tab-details" class="project-tab-content" style="padding: 1.25rem; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Project ID</label>
                <input type="text" name="project_id" value="PRJ-<?php echo date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT); ?>" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-family: monospace; font-size: 0.875rem;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Project Name *</label>
                <input type="text" name="name" required placeholder="Website Redesign" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;" oninput="updateProjectSummary()">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Client *</label>
                <input type="text" name="client" required placeholder="Acme Corp" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Status *</label>
                <select name="status" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                  <option value="active">Active</option>
                  <option value="on-hold">On Hold</option>
                  <option value="completed">Completed</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Start Date *</label>
                <input type="date" name="start_date" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;" oninput="updateProjectSummary()">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">End Date</label>
                <input type="date" name="end_date" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;" oninput="updateProjectSummary()">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Budget</label>
                <input type="number" name="budget" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;" oninput="updateProjectSummary()">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Default Hourly Rate</label>
                <input type="number" name="hourly_rate" min="0" step="0.01" placeholder="50.00" value="50" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;" oninput="updateProjectSummary()">
              </div>
            </div>
          </div>

          <div id="project-tab-tasks" class="project-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin: 0;">Tasks</h3>
              <button type="button" onclick="addTask()" style="padding: 0.375rem 0.875rem; background: #000000; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer;">+ Add Task</button>
            </div>
            <div id="tasksHeader" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 60px; gap: 0.5rem; padding: 0.5rem 0.75rem; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em;">
              <div>Title</div>
              <div>Assignee</div>
              <div>Due</div>
              <div>Est hrs</div>
              <div>Status</div>
              <div style="text-align: right;">Actions</div>
            </div>
            <div id="tasksContainer" style="margin-top: 0.75rem;"></div>
          </div>

          <div id="project-tab-milestones" class="project-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin: 0;">Milestones</h3>
              <button type="button" onclick="addMilestone()" style="padding: 0.375rem 0.875rem; background: #000000; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer;">+ Add Milestone</button>
            </div>
            <div id="milestonesContainer"></div>
          </div>

          <div id="project-tab-team" class="project-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin: 0;">Team Members</h3>
              <button type="button" onclick="addTeamMember()" style="padding: 0.375rem 0.875rem; background: #000000; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer;">+ Add Member</button>
            </div>
            <div id="teamContainer"></div>
          </div>

          <div id="project-tab-time" class="project-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
              <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin: 0;">Time Entries</h3>
              <button type="button" onclick="addTimeEntry()" style="padding: 0.375rem 0.875rem; background: #000000; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer;">+ Add Entry</button>
            </div>
            <div id="timeContainer"></div>
          </div>

          <div id="project-tab-files" class="project-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Attachments</label>
            <input type="file" name="attachments" multiple onchange="handleFilesChange(this)" style="display: block; margin-bottom: 0.75rem;">
            <div id="filesList" style="display: grid; grid-template-columns: 1fr; gap: 0.5rem;"></div>
          </div>

          <div id="project-tab-comms" class="project-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.75rem; align-items: start;">
              <textarea id="messageInput" rows="3" placeholder="Post an update for the client/team..." style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
              <button type="button" onclick="addMessage()" style="padding: 0.625rem 1rem; background: #000000; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Post</button>
            </div>
            <div id="messagesContainer" style="margin-top: 1rem; display: grid; grid-template-columns: 1fr; gap: 0.5rem;"></div>
          </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1.25rem; min-height: 0; position: sticky; top: 0; align-self: start; max-width: 340px;">
          <div style="background: linear-gradient(135deg, hsl(240 5% 98%), white); border: 1.5px solid hsl(214 20% 88%); border-radius: 10px; padding: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 0.875rem;">
              <div style="width: 32px; height: 32px; background: linear-gradient(135deg, rgba(113,148,165,0.15), rgba(113,148,165,0.25)); border-radius: 7px; display: flex; align-items: center; justify-content: center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
              </div>
              <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: hsl(222 47% 17%);">Project Summary</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.625rem;">
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Budget</span><span id="projectBudgetDisplay" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">$0.00</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Spent</span><span id="projectSpentDisplay" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">$0.00</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Remaining</span><span id="projectRemainingDisplay" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">$0.00</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Progress</span><span id="projectProgressDisplay" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">0%</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Tasks</span><span id="projectTasksDisplay" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">0/0</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Team</span><span id="projectTeamDisplay" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">0 members</span></div>
              <div style="display: flex; justify-content: space-between; padding: 0.625rem 0.875rem; background: white; border-radius: 7px;"><span style="color: hsl(215 16% 47%); font-weight: 500; font-size: 0.8125rem;">Dates</span><span id="projectDatesDisplay" style="font-weight: 700; color: hsl(222 47% 17%); font-size: 0.8125rem;">—</span></div>
            </div>
          </div>

          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <button type="button" onclick="closeNewProjectModal()" style="width: 100%; background: white; color: hsl(220 14% 11%); border: 1.5px solid hsl(214 20% 85%); padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.8125rem;">Cancel</button>
            <button type="button" onclick="saveProjectDraft()" style="width: 100%; background: #6b7280; color: white; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.8125rem;">Save as Draft</button>
            <button type="submit" style="width: 100%; background: #000000; color: white; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.875rem;">Create Project</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function showNewProjectModal() {
  const modal = document.getElementById('newProjectModal');
  modal.style.display = 'flex';
  document.getElementById('newProjectForm').reset();
  const today = new Date().toISOString().split('T')[0];
  document.querySelector('#newProjectForm input[name="start_date"]').value = today;
  document.getElementById('tasksContainer').innerHTML = '';
  document.getElementById('milestonesContainer').innerHTML = '';
  document.getElementById('teamContainer').innerHTML = '';
  document.getElementById('timeContainer').innerHTML = '';
  document.getElementById('filesList').innerHTML = '';
  document.getElementById('messagesContainer').innerHTML = '';
  addTask();
  addTeamMember();
  switchProjectTab('details');
  updateProjectSummary();
  applyProjectResponsive();
  applyTaskGridResponsive();
}

function closeNewProjectModal() {
  document.getElementById('newProjectModal').style.display = 'none';
}

function switchProjectTab(name) {
  const tabs = document.querySelectorAll('.project-tab-content');
  const btns = document.querySelectorAll('.project-tab-btn');
  tabs.forEach(t => { t.style.display = t.id === `project-tab-${name}` ? 'block' : 'none'; });
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
  if (name === 'tasks') {
    applyTaskGridResponsive();
  }
}

function applyProjectResponsive() {
  try {
    const grid = document.getElementById('projectGrid');
    if (!grid) return;
    if (window.innerWidth < 1024) {
      grid.style.gridTemplateColumns = '1fr';
    } else {
      grid.style.gridTemplateColumns = '1fr 340px';
    }
  } catch {}
}

function applyTaskGridResponsive() {
  try {
    const header = document.getElementById('tasksHeader');
    const rows = document.querySelectorAll('#tasksContainer .task-row');
    const narrow = window.innerWidth < 1024;
    if (header) header.style.display = narrow ? 'none' : 'grid';
    rows.forEach(r => {
      r.style.gridTemplateColumns = narrow ? '1fr' : '2fr 1fr 1fr 1fr 1fr 80px';
    });
  } catch {}
}

function addTask() {
  const c = document.getElementById('tasksContainer');
  const div = document.createElement('div');
  div.className = 'task-row';
  div.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 80px; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px;';
  div.innerHTML = `
    <input type="text" placeholder="Task title" class="task-title" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateProjectSummary()">
    <input type="text" placeholder="Assignee" class="task-assignee" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;">
    <input type="date" class="task-due" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;">
    <input type="number" placeholder="Est hrs" class="task-hours" min="0" step="0.1" value="1" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateProjectSummary()">
    <select class="task-status" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" onchange="updateProjectSummary()">
      <option value="todo">To Do</option>
      <option value="in-progress">In Progress</option>
      <option value="done">Done</option>
    </select>
    <button type="button" onclick="this.parentElement.remove(); updateProjectSummary();" style="padding: 0.375rem 0.75rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; font-size: 0.8125rem; cursor: pointer; width: 80px; text-align: center;">Remove</button>
  `;
  c.appendChild(div);
  applyTaskGridResponsive();
  updateProjectSummary();
}

function addMilestone() {
  const c = document.getElementById('milestonesContainer');
  const div = document.createElement('div');
  div.className = 'milestone-row';
  div.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px;';
  div.innerHTML = `
    <input type="text" placeholder="Milestone title" class="ms-title" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;">
    <input type="date" class="ms-date" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;">
    <input type="number" placeholder="Amount" class="ms-amount" min="0" step="0.01" value="0" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;">
    <button type="button" onclick="this.parentElement.remove(); updateProjectSummary();" style="padding: 0.375rem 0.75rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; font-size: 0.8125rem; cursor: pointer;">Remove</button>
  `;
  c.appendChild(div);
}

function addTeamMember() {
  const c = document.getElementById('teamContainer');
  const div = document.createElement('div');
  div.className = 'team-row';
  div.style.cssText = 'display: grid; grid-template-columns: 1.5fr 1fr 1fr auto; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px;';
  div.innerHTML = `
    <input type="text" placeholder="Member name" class="tm-name" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateProjectSummary()">
    <input type="text" placeholder="Role" class="tm-role" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;">
    <input type="number" placeholder="Hourly rate" class="tm-rate" min="0" step="0.01" value="50" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateProjectSummary()">
    <button type="button" onclick="this.parentElement.remove(); updateProjectSummary();" style="padding: 0.375rem 0.75rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; font-size: 0.8125rem; cursor: pointer;">Remove</button>
  `;
  c.appendChild(div);
  updateProjectSummary();
}

function addTimeEntry() {
  const c = document.getElementById('timeContainer');
  const div = document.createElement('div');
  div.className = 'time-row';
  div.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr 1fr 2fr auto; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px;';
  div.innerHTML = `
    <input type="date" class="te-date" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;">
    <input type="text" placeholder="Member name" class="te-member" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateProjectSummary()">
    <input type="number" placeholder="Hours" class="te-hours" min="0" step="0.1" value="1" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;" oninput="updateProjectSummary()">
    <input type="text" placeholder="Notes" class="te-notes" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem;">
    <button type="button" onclick="this.parentElement.remove(); updateProjectSummary();" style="padding: 0.375rem 0.75rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; font-size: 0.8125rem; cursor: pointer;">Remove</button>
  `;
  c.appendChild(div);
  updateProjectSummary();
}

function handleFilesChange(input) {
  const list = document.getElementById('filesList');
  list.innerHTML = '';
  Array.from(input.files || []).forEach(f => {
    const row = document.createElement('div');
    row.style.cssText = 'display:flex; justify-content: space-between; align-items:center; padding: 0.5rem 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.8125rem;';
    row.innerHTML = `<span>${f.name}</span><span style="color:#6b7280">${(f.size/1024).toFixed(1)} KB</span>`;
    list.appendChild(row);
  });
}

function addMessage() {
  const input = document.getElementById('messageInput');
  const text = (input.value || '').trim();
  if (!text) return;
  const c = document.getElementById('messagesContainer');
  const row = document.createElement('div');
  row.style.cssText = 'padding: 0.625rem 0.875rem; background: white; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.875rem;';
  const when = new Date().toLocaleString();
  row.innerHTML = `<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;"><strong style="font-size:0.8125rem; color:hsl(222 47% 17%)">Update</strong><span style="font-size:0.75rem; color:#6b7280">${when}</span></div><div>${text.replace(/[&<>]/g, s=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]))}</div>`;
  c.prepend(row);
  input.value = '';
  Toast.success('Update posted');
}

function getTeamRateForMember(name) {
  const rows = document.querySelectorAll('#teamContainer .team-row');
  for (const r of rows) {
    const n = (r.querySelector('.tm-name')?.value || '').trim();
    if (n && name && n.toLowerCase() === name.toLowerCase()) {
      const rate = parseFloat(r.querySelector('.tm-rate')?.value) || 0;
      return rate;
    }
  }
  return parseFloat(document.querySelector('input[name="hourly_rate"]')?.value) || 0;
}

const PROJECT_CURRENCY = '<?php echo CurrencyHelper::getCurrentCurrency(); ?>';
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
const PROJECT_CURRENCY_SYMBOL = getCurrencySymbol(PROJECT_CURRENCY);
function currency(n) {
  const v = isFinite(n) ? Number(n) : 0;
  return PROJECT_CURRENCY_SYMBOL + v.toFixed(2);
}

function updateProjectSummary() {
  try {
    const tasks = Array.from(document.querySelectorAll('#tasksContainer .task-row'));
    const done = tasks.filter(r => (r.querySelector('.task-status')?.value) === 'done').length;
    const totalTasks = tasks.length;

    const timeRows = Array.from(document.querySelectorAll('#timeContainer .time-row'));
    let spent = 0;
    timeRows.forEach(r => {
      const member = (r.querySelector('.te-member')?.value || '').trim();
      const hours = parseFloat(r.querySelector('.te-hours')?.value) || 0;
      const rate = getTeamRateForMember(member);
      spent += hours * rate;
    });

    const budget = parseFloat(document.querySelector('input[name="budget"]')?.value) || 0;
    const remaining = Math.max(0, budget - spent);
    const progress = totalTasks > 0 ? Math.round((done / totalTasks) * 100) : 0;
    const teamCount = document.querySelectorAll('#teamContainer .team-row').length;
    const start = document.querySelector('input[name="start_date"]').value || '';
    const end = document.querySelector('input[name="end_date"]').value || '';

    const budgetEl = document.getElementById('projectBudgetDisplay');
    const spentEl = document.getElementById('projectSpentDisplay');
    const remEl = document.getElementById('projectRemainingDisplay');
    const progEl = document.getElementById('projectProgressDisplay');
    const tasksEl = document.getElementById('projectTasksDisplay');
    const teamEl = document.getElementById('projectTeamDisplay');
    const datesEl = document.getElementById('projectDatesDisplay');
    if (budgetEl) budgetEl.textContent = currency(budget);
    if (spentEl) spentEl.textContent = currency(spent);
    if (remEl) remEl.textContent = currency(remaining);
    if (progEl) progEl.textContent = `${progress}%`;
    if (tasksEl) tasksEl.textContent = `${done}/${totalTasks}`;
    if (teamEl) teamEl.textContent = `${teamCount} member${teamCount === 1 ? '' : 's'}`;
    if (datesEl) datesEl.textContent = start && end ? `${start} → ${end}` : (start || end || '—');
  } catch (e) {
    console.warn('Failed to update project summary', e);
  }
}

function collectTasksData() {
  return Array.from(document.querySelectorAll('#tasksContainer .task-row')).map(r => ({
    title: r.querySelector('.task-title')?.value || '',
    assignee: r.querySelector('.task-assignee')?.value || '',
    due: r.querySelector('.task-due')?.value || '',
    hours: parseFloat(r.querySelector('.task-hours')?.value) || 0,
    status: r.querySelector('.task-status')?.value || 'todo'
  }));
}

function collectMilestonesData() {
  return Array.from(document.querySelectorAll('#milestonesContainer .milestone-row')).map(r => ({
    title: r.querySelector('.ms-title')?.value || '',
    date: r.querySelector('.ms-date')?.value || '',
    amount: parseFloat(r.querySelector('.ms-amount')?.value) || 0
  }));
}

function collectTeamData() {
  return Array.from(document.querySelectorAll('#teamContainer .team-row')).map(r => ({
    name: r.querySelector('.tm-name')?.value || '',
    role: r.querySelector('.tm-role')?.value || '',
    rate: parseFloat(r.querySelector('.tm-rate')?.value) || 0
  }));
}

function collectTimeData() {
  return Array.from(document.querySelectorAll('#timeContainer .time-row')).map(r => ({
    date: r.querySelector('.te-date')?.value || '',
    member: r.querySelector('.te-member')?.value || '',
    hours: parseFloat(r.querySelector('.te-hours')?.value) || 0,
    notes: r.querySelector('.te-notes')?.value || ''
  }));
}

document.getElementById('newProjectForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const form = new FormData(this);
  const data = Object.fromEntries(form);
  data.tasks = collectTasksData();
  data.milestones = collectMilestonesData();
  data.team = collectTeamData();
  data.time = collectTimeData();
  const spentText = document.getElementById('projectSpentDisplay')?.textContent || '$0.00';
  const budgetText = document.getElementById('projectBudgetDisplay')?.textContent || '$0.00';
  data.summary = {
    budget: budgetText,
    spent: spentText,
    progress: document.getElementById('projectProgressDisplay')?.textContent || '0%'
  };
  console.log('Creating new project:', data);
  Toast.success('Project created successfully!');
  closeNewProjectModal();
});

function saveProjectDraft() {
  const data = {
    name: document.querySelector('input[name="name"]')?.value || '',
    client: document.querySelector('input[name="client"]')?.value || '',
    tasks: collectTasksData(),
    milestones: collectMilestonesData(),
    team: collectTeamData(),
    time: collectTimeData(),
    status: 'draft'
  };
  console.log('Draft saved:', data);
  Toast.info('Project draft saved');
}

document.getElementById('newProjectModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeNewProjectModal();
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('newProjectModal');
    if (modal && modal.style.display === 'flex') {
      closeNewProjectModal();
    }
  }
});
window.addEventListener('resize', applyProjectResponsive);
window.addEventListener('resize', applyTaskGridResponsive);

// ============================================
// APPLY NUMBER FORMAT API TO CURRENCY VALUES
// ============================================
window.addEventListener('load', function() {
  const currencySymbol = '<?php echo CurrencyHelper::symbol(); ?>';
  
  // Auto-apply formatting to project values
  NumberFormat.autoApply(currencySymbol, {
    customSelectors: [
      { selector: 'p[style*="font-size: 1.75rem"]', maxWidth: 1 },  // Stat cards - always abbreviate >= 1M
      { selector: 'td.font-semibold', maxWidth: 80 }  // Table columns (budget/spent)
    ]
  });
});
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
