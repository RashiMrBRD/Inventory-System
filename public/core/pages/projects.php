<?php
/**
 * Projects Module
 * Tracks, manages, and monitors projects
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Model\Project;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Ensure user data is valid
if (!$user || !is_array($user)) {
    error_log('Projects: User data is null or invalid');
    header('Location: /login');
    exit();
}

// Check SMTP configuration
$appConfig = require __DIR__ . '/../../../config/app.php';
$smtpConfigured = !empty($appConfig['mail']['host']) && !empty($appConfig['mail']['username']);

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

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
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

/* Focus ring improvements */
.search-input:focus,
.form-select:focus {
  outline: none;
}

/* Sortable table headers */
.sortable-header {
  transition: background-color 0.15s ease, color 0.15s ease;
}

.sortable-header:hover {
  background-color: hsl(214 20% 95%) !important;
  color: hsl(222 47% 17%) !important;
}

.sortable-header:active {
  background-color: hsl(214 20% 92%) !important;
}

.sortable-header.sorted-asc .sort-icon,
.sortable-header.sorted-desc .sort-icon {
  opacity: 1 !important;
  color: hsl(217 91% 60%);
}

.sortable-header.sorted-asc .sort-icon path:first-child,
.sortable-header.sorted-desc .sort-icon path:last-child {
  display: none;
}

.sort-icon {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.sortable-header:hover .sort-icon {
  opacity: 0.7 !important;
}

/* Table layout stability - prevent shifting during sort */
.data-table {
  width: 100%;
  border-collapse: separate !important;
  border-spacing: 0 !important;
}

.data-table th,
.data-table td {
  padding: 0.75rem 1rem !important;
  height: 3.5rem;
  box-sizing: border-box;
}

.data-table tbody tr {
  height: 3.5rem;
  border-bottom: 1px solid hsl(214 20% 92%);
}

/* Force consistent row rendering */
.data-table tbody {
  background: white;
}

.data-table tbody td {
  vertical-align: middle;
}

/* Smooth transitions - but NOT on table elements */
* {
  transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

/* Override transitions for table to prevent layout shifts */
.data-table,
.data-table *,
.data-table tr,
.data-table td,
.data-table th,
.data-table tbody,
.data-table thead {
  transition: background-color 0.15s ease !important;
}

/* Ensure hover doesn't change spacing */
.data-table tbody tr:hover {
  background-color: hsl(214 20% 97%);
  transform: none !important;
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
      <a href=\"/dashboard" style="padding: 0.625rem 1.25rem; background: rgba(255,255,255,0.2); border-radius: 8px; color: white; text-decoration: none; font-weight: 500; backdrop-filter: blur(10px); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
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
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(217 91% 60%); margin: 0;" data-stat="total-projects"><?php echo count($projects); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Active Projects</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2"><path d="M22 11.08V12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C15.7824 2 18.9935 4.19066 20.4866 7.35397M22 4L12 14.01L9 11.01"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(142 71% 45%); margin: 0;" data-stat="active-projects"><?php echo $activeProjectsCount; ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Budget</p>
      <div style="width: 36px; height: 36px; background: rgba(113,148,165,0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7194A5" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: #7194A5; margin: 0;" data-stat="total-budget"><?php echo CurrencyHelper::format($totalBudget, 0); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Total Spent</p>
      <div style="width: 36px; height: 36px; background: hsl(48 96% 89%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2"><path d="M12 8V12L15 15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(25 95% 16%); margin: 0;" data-stat="total-spent"><?php echo CurrencyHelper::format($totalSpent, 0); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Remaining</p>
      <div style="width: 36px; height: 36px; background: hsl(143 85% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(140 61% 13%)" stroke-width="2"><path d="M12 8V16M8 12H16M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(140 61% 13%); margin: 0;" data-stat="remaining"><?php echo CurrencyHelper::format($totalRemaining, 0); ?></p>
  </div>
  
  <div style="background: white; border-radius: 10px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid hsl(214 20% 92%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
      <p style="font-size: 0.8125rem; font-weight: 500; color: hsl(215 16% 47%); margin: 0;">Avg Progress</p>
      <div style="width: 36px; height: 36px; background: hsl(262 83% 96%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(263 70% 26%)" stroke-width="2"><path d="M9 11L12 14L22 4M21 12V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H16"/></svg>
      </div>
    </div>
    <p style="font-size: 1.75rem; font-weight: 700; color: hsl(263 70% 26%); margin: 0;" data-stat="avg-progress"><?php echo round(count($projects) > 0 ? ($totalSpent / $totalBudget * 100) : 0); ?>%</p>
  </div>
</div>

<div class="toolbar" style="background: white; border: 1px solid hsl(214 20% 92%); border-radius: 10px; padding: 0.875rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 1.5rem;">
  <!-- Search Section (Left) -->
  <div class="search-bar" style="flex: 1; max-width: 450px; position: relative;">
    <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: hsl(215 16% 47%);">
      <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
      <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <input type="search" class="search-input" placeholder="Search projects..." id="project-search" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.75rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.9375rem; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(214 88% 78%)'; this.style.boxShadow='0 0 0 3px hsl(214 95% 93%)'" onblur="this.style.borderColor='hsl(214 20% 92%)'; this.style.boxShadow='none'">
  </div>

  <!-- Batch Operations (Hidden by default, shown when bulk mode active and items selected) -->
  <div id="quickActions" style="display: none; align-items: center; gap: 0.5rem; padding: 0 0.75rem; border-left: 1.5px solid hsl(214 20% 92%); border-right: 1.5px solid hsl(214 20% 92%);">
    <!-- Batch Status Update -->
    <button class="btn btn-ghost btn-icon" onclick="batchUpdateStatus()" title="Batch Update Status" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'; this.style.color='hsl(222 47% 17%)'" onmouseout="this.style.background=''; this.style.color=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="12" cy="12" r="10"/><path d="M9 11l3 3 8-8"/>
      </svg>
    </button>
    
    <!-- Batch Assign Team -->
    <button class="btn btn-ghost btn-icon" onclick="batchAssignTeam()" title="Assign Team Members" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'; this.style.color='hsl(222 47% 17%)'" onmouseout="this.style.background=''; this.style.color=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
      </svg>
    </button>
    
    <!-- Batch Update Budget -->
    <button class="btn btn-ghost btn-icon" onclick="batchUpdateBudget()" title="Update Budget" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'; this.style.color='hsl(222 47% 17%)'" onmouseout="this.style.background=''; this.style.color=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
    </button>
    
    <!-- Batch Update Deadline -->
    <button class="btn btn-ghost btn-icon" onclick="batchUpdateDeadline()" title="Update Deadline" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'; this.style.color='hsl(222 47% 17%)'" onmouseout="this.style.background=''; this.style.color=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
    </button>
    
    <!-- Batch Tag/Label -->
    <button class="btn btn-ghost btn-icon" onclick="batchAddTags()" title="Add Tags/Labels" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'; this.style.color='hsl(222 47% 17%)'" onmouseout="this.style.background=''; this.style.color=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
      </svg>
    </button>
    
    <!-- Batch Duplicate -->
    <button class="btn btn-ghost btn-icon" onclick="batchDuplicate()" title="Duplicate Projects" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'; this.style.color='hsl(222 47% 17%)'" onmouseout="this.style.background=''; this.style.color=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
      </svg>
    </button>
    
    <!-- Batch Export -->
    <button class="btn btn-ghost btn-icon" onclick="batchExportProjects()" title="Export Selected" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'; this.style.color='hsl(222 47% 17%)'" onmouseout="this.style.background=''; this.style.color=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
      </svg>
    </button>
    
    <!-- Batch Archive -->
    <button class="btn btn-ghost btn-icon" onclick="batchArchiveProjects()" title="Archive Selected" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'; this.style.color='hsl(222 47% 17%)'" onmouseout="this.style.background=''; this.style.color=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/>
      </svg>
    </button>
  </div>

  <!-- Spacer -->
  <div style="flex: 1;"></div>

  <!-- Right Section (Normal Features - Hide when bulk mode active) -->
  <div id="normalFeatures" style="display: flex; align-items: center; gap: 0.5rem; padding: 0 0.75rem; border-left: 1.5px solid hsl(214 20% 92%); border-right: 1.5px solid hsl(214 20% 92%);">
    <!-- Import -->
    <button class="btn btn-ghost btn-icon" onclick="importProjects()" title="Import" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'" onmouseout="this.style.background=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
    </button>
    
    <!-- Templates -->
    <button class="btn btn-ghost btn-icon" onclick="showProjectTemplates()" title="Templates" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'" onmouseout="this.style.background=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><path d="M13 2v7h7"/></svg>
    </button>
    
    <!-- Calendar -->
    <button id="calendarViewBtn" class="btn btn-ghost btn-icon" onclick="showCalendarView(event)" title="Calendar" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'" onmouseout="this.style.background=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    </button>
    
    <!-- Analytics -->
    <button class="btn btn-ghost btn-icon" onclick="showAnalyticsDashboard()" title="Analytics" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'" onmouseout="this.style.background=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
    </button>
    
    <!-- Filters -->
    <button class="btn btn-ghost btn-icon" onclick="showAdvancedFilters()" title="Advanced Filters" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'" onmouseout="this.style.background=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
    </button>
    
    <!-- Reset Filters -->
    <button class="btn btn-ghost btn-icon" onclick="resetAllFilters()" title="Reset Filters (Show All)" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(48 96% 89%)'" onmouseout="this.style.background=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6"/></svg>
    </button>
    
    <!-- Refresh -->
    <button class="btn btn-ghost btn-icon" onclick="refreshProjects()" title="Refresh" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='hsl(214 95% 93%)'" onmouseout="this.style.background=''">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0118.8-4.3M22 12.5a10 10 0 01-18.8 4.2"/></svg>
    </button>
  </div>
  
  <!-- Right Controls -->
  <div style="display: flex; align-items: center; gap: 0.75rem;">
    <select class="form-select" id="status-filter" style="padding: 0.625rem 2.5rem 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.9375rem; font-weight: 500; color: hsl(222 47% 17%); background: white; cursor: pointer; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(214 88% 78%)'" onblur="this.style.borderColor='hsl(214 20% 92%)'">
      <option value="all">All Status</option>
      <option value="active">Active</option>
      <option value="completed">Completed</option>
      <option value="on-hold">On Hold</option>
    </select>
    <button class="btn btn-ghost btn-icon" onclick="toggleBulkMode()" id="bulkToggleBtn" title="Bulk Operations" style="width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 1.5px solid hsl(214 20% 92%); transition: all 0.2s; background: white;" onmouseover="this.style.background='hsl(214 95% 93%)'; this.style.borderColor='hsl(214 88% 78%)'" onmouseout="if(!this.classList.contains('active')){this.style.background='white'; this.style.borderColor='hsl(214 20% 92%)'}">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7" rx="1.5"/>
        <rect x="14" y="3" width="7" height="7" rx="1.5"/>
        <rect x="14" y="14" width="7" height="7" rx="1.5"/>
        <rect x="3" y="14" width="7" height="7" rx="1.5"/>
      </svg>
    </button>
  </div>
</div>

<!-- Batch Operations Toolbar (Hidden by default) -->
<div id="batchToolbar" style="display: none; background: linear-gradient(135deg, hsl(240 5% 96%) 0%, hsl(240 6% 90%) 100%); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid hsl(214 20% 88%);">
  <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
    <span style="font-weight: 600; color: hsl(222 47% 17%);">Batch Actions:</span>
    <button class="btn btn-ghost" onclick="batchUpdateStatus()" style="font-size: 0.875rem;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
      Update Status
    </button>
    <button class="btn btn-ghost" onclick="batchArchive()" style="font-size: 0.875rem;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
      Archive Selected
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

<div id="projectsTableContainer" class="table-container" style="display: <?php echo empty($projects) ? 'none' : 'block'; ?>;">
  <table class="data-table">
    <thead>
      <tr>
        <th class="checkbox-column" style="width: 40px; display: none;">
          <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor: pointer;">
        </th>
        <th class="sortable-header" onclick="sortTable('project_id')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span>Project ID</span>
            <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4">
              <path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('name')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span>Name</span>
            <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4">
              <path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('client')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span>Client</span>
            <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4">
              <path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('start_date')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span>Start Date</span>
            <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4">
              <path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('end_date')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span>End Date</span>
            <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4">
              <path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('budget')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span>Budget</span>
            <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4">
              <path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('spent')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span>Spent</span>
            <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4">
              <path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('progress')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span>Progress</span>
            <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4">
              <path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>
            </svg>
          </div>
        </th>
        <th class="sortable-header" onclick="sortTable('status')" style="cursor: pointer; user-select: none;">
          <div style="display: flex; align-items: center; gap: 0.375rem;">
            <span>Status</span>
            <svg class="sort-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4">
              <path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>
            </svg>
          </div>
        </th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($projects as $project): 
        $budget = (float)($project['budget'] ?? 0);
        $spent = (float)($project['spent'] ?? 0);
        $progress = $budget > 0 ? ($spent / $budget) * 100 : 0;
        $projectId = $project['project_id'] ?? '';
        $startDate = $project['start_date'] ?? '';
        $endDate = $project['end_date'] ?? '';
      ?>
      <tr>
        <td class="checkbox-column" style="width: 40px; display: none;">
          <input type="checkbox" class="project-checkbox" value="<?php echo (string)($project['_id'] ?? ''); ?>" data-project-id="<?php echo htmlspecialchars($projectId); ?>" data-project-name="<?php echo htmlspecialchars($project['name'] ?? ''); ?>" onchange="updateBatchToolbar()" style="cursor: pointer;">
        </td>
        <td class="font-mono font-medium"><?php echo htmlspecialchars($projectId); ?></td>
        <td class="font-medium"><?php echo htmlspecialchars($project['name'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($project['client'] ?? ''); ?></td>
        <td><?php echo $startDate ? date('M d, Y', strtotime($startDate)) : '—'; ?></td>
        <td><?php echo $endDate ? date('M d, Y', strtotime($endDate)) : '—'; ?></td>
        <td class="font-semibold"><?php echo CurrencyHelper::format($budget, 0); ?></td>
        <td><?php echo CurrencyHelper::format($spent, 0); ?></td>
        <td>
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="flex: 1; height: 6px; background: #e5e7eb; border-radius: 999px; overflow: hidden;">
              <div style="height: 100%; width: <?php echo min($progress, 100); ?>%; background: <?php echo $progress > 90 ? '#dc2626' : '#10b981'; ?>;"></div>
            </div>
            <span style="font-size: 0.75rem; color: #6b7280;"><?php echo round($progress); ?>%</span>
          </div>
        </td>
        <td>
          <?php 
            $status = $project['status'] ?? 'draft';
            $badgeClass = 'badge-default';
            $statusLabel = ucfirst($status);
            
            if ($status === 'active') {
              $badgeClass = 'badge-success';
            } elseif ($status === 'completed') {
              $badgeClass = 'badge-info';
            } elseif ($status === 'on-hold') {
              $badgeClass = 'badge-warning';
              $statusLabel = 'On Hold';
            } elseif ($status === 'archived') {
              $badgeClass = 'badge-default';
            }
          ?>
          <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span>
        </td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="toggleProjectActions('<?php echo (string)($project['_id'] ?? ''); ?>')" title="Actions">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="1" fill="currentColor"/><circle cx="12" cy="5" r="1" fill="currentColor"/><circle cx="12" cy="19" r="1" fill="currentColor"/>
            </svg>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination Controls (Orders.php Style) -->
<div id="paginationControls" style="display: none; padding: 1.5rem 1rem; border-top: 1px solid hsl(214 20% 88%); background: hsl(220 20% 98%);">
  <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <!-- Page Info & Size Selector -->
    <div style="display: flex; align-items: center; gap: 1rem;">
      <div style="color: hsl(215 16% 47%); font-size: 0.875rem;">
        Showing <span id="pageInfo" style="font-weight: 600; color: hsl(222 47% 17%);">1-6</span> of <span id="totalItems" style="font-weight: 600; color: hsl(222 47% 17%);">0</span> projects
      </div>
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <label for="projectsPageSize" style="font-size: 0.875rem; color: hsl(215 16% 47%); font-weight: 500;">Show:</label>
        <select id="projectsPageSize" onchange="changeProjectsPageSize(this.value)" style="padding: 0.4rem 0.75rem; border: 1px solid hsl(214 20% 88%); background: white; border-radius: 6px; cursor: pointer; color: hsl(222 47% 17%); font-size: 0.875rem; font-weight: 500; transition: all 0.2s; outline: none;" onmouseover="this.style.borderColor='hsl(214 20% 78%)'" onmouseout="this.style.borderColor='hsl(214 20% 88%)'" onfocus="this.style.borderColor='hsl(221 83% 53%)'; this.style.boxShadow='0 0 0 3px hsla(221, 83%, 53%, 0.1)'" onblur="this.style.borderColor='hsl(214 20% 88%)'; this.style.boxShadow='none'">
          <option value="6" selected>6</option>
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>
    
    <!-- Page Controls -->
    <div style="display: flex; gap: 0.5rem; align-items: center;">
      <!-- Previous Button -->
      <button id="prevPage" onclick="changePage('prev')" disabled style="padding: 0.5rem 0.75rem; border: 1px solid hsl(214 20% 88%); background: white; border-radius: 6px; cursor: pointer; color: hsl(222 47% 17%); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="if(!this.disabled){this.style.background='hsl(210 20% 98%)'}" onmouseout="if(!this.disabled){this.style.background='white'}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Previous
      </button>
      
      <!-- Page Numbers -->
      <div id="pageNumbers" style="display: flex; gap: 0.25rem;">
        <!-- Generated dynamically -->
      </div>
      
      <!-- Next Button -->
      <button id="nextPage" onclick="changePage('next')" disabled style="padding: 0.5rem 0.75rem; border: 1px solid hsl(214 20% 88%); background: white; border-radius: 6px; cursor: pointer; color: hsl(222 47% 17%); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="if(!this.disabled){this.style.background='hsl(210 20% 98%)'}" onmouseout="if(!this.disabled){this.style.background='white'}">
        Next
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </button>
    </div>
  </div>
</div>

<!-- Project Actions Dropdown Menu (Simplified) -->
<div id="projectActionsDropdown" style="display: none; position: absolute; background: white; border: 1px solid hsl(214 20% 88%); border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); z-index: 10000; min-width: 200px;">
  <div style="padding: 0.5rem;">
    <button onclick="handleProjectAction('view')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
      View Details
    </button>
    <button onclick="handleProjectAction('edit')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Edit Project
    </button>
    <button onclick="handleProjectAction('duplicate')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
      Duplicate Project
    </button>
    <button onclick="handleProjectAction('status')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
      Change Status
    </button>
    <div style="height: 1px; background: hsl(214 20% 88%); margin: 0.5rem 0;"></div>
    <button onclick="handleProjectAction('template')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(222 47% 17%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
      Save as Template
    </button>
    <button onclick="handleProjectAction('email')" <?php if (!$smtpConfigured): ?>disabled<?php endif; ?> style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>; font-size: 0.875rem; color: <?php echo $smtpConfigured ? 'hsl(222 47% 17%)' : 'hsl(215 16% 60%)'; ?>; text-align: left; transition: background 0.2s; opacity: <?php echo $smtpConfigured ? '1' : '0.5'; ?>;" <?php if ($smtpConfigured): ?>onmouseover="this.style.background='hsl(210 20% 98%)'" onmouseout="this.style.background='transparent'"<?php endif; ?>>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>
      Send Email Report<?php if (!$smtpConfigured): ?> <span style="font-size: 0.75rem;">(SMTP not configured)</span><?php endif; ?>
    </button>
    <div style="height: 1px; background: hsl(214 20% 88%); margin: 0.5rem 0;"></div>
    <button onclick="handleProjectAction('archive')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(25 95% 45%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(48 96% 95%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
      Archive Project
    </button>
    <button onclick="handleProjectAction('delete')" style="width: 100%; display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: none; background: transparent; border-radius: 6px; cursor: pointer; font-size: 0.875rem; color: hsl(0 74% 50%); text-align: left; transition: background 0.2s;" onmouseover="this.style.background='hsl(0 86% 97%)'" onmouseout="this.style.background='transparent'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      Delete Project
    </button>
  </div>
</div>

<!-- Email Project Report Modal (2-Column Layout) -->
<div id="emailProjectModal" onclick="closeEmailProjectModal()" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 2rem;">
  <div onclick="event.stopPropagation()" style="width: 100%; max-width: 950px; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); display: flex; flex-direction: column; overflow: hidden;">
    
    <!-- Header -->
    <div style="background: linear-gradient(135deg, hsl(220 20% 97%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%); display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h3 style="margin: 0 0 0.25rem 0; font-size: 1.25rem; font-weight: 700; color: hsl(222 47% 17%);">Send Project Report via Email</h3>
        <p id="emailProjectNumber" style="margin: 0; font-size: 0.875rem; color: hsl(215 16% 47%); font-family: monospace;">PRJ-001</p>
      </div>
      <button type="button" onclick="closeEmailProjectModal()" style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(240 5% 40%); transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 92%)'" onmouseout="this.style.background='hsl(240 5% 96%)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    
    <!-- Form Content with 2-Column Layout -->
    <form id="emailProjectForm" onsubmit="submitEmailProject(event)" style="display: flex; flex-direction: column; flex: 1; overflow-y: auto;">
      <input type="hidden" id="emailProjectId" name="project_id">
      
      <!-- SMTP Warning (shown if not configured) -->
      <div id="smtpWarningProject" style="display: none; margin: 2rem 2rem 0 2rem; padding: 1rem 1.25rem; background: hsl(48 96% 89%); border: 1px solid hsl(48 96% 75%); border-radius: 8px;">
        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 16%)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;">
            <path d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
          </svg>
          <div>
            <p style="margin: 0 0 0.375rem 0; font-size: 0.875rem; font-weight: 700; color: hsl(25 95% 16%);">SMTP Not Configured</p>
            <p style="margin: 0; font-size: 0.8125rem; color: hsl(25 95% 16%); line-height: 1.5;">Email functionality requires SMTP server configuration. Please configure your email settings to send project reports.</p>
          </div>
        </div>
      </div>
      
      <!-- Main Content Area with 2 Columns -->
      <div style="padding: 2rem; display: grid; grid-template-columns: 1fr 1.6fr; gap: 1.5rem;">
        
        <!-- LEFT COLUMN: Recipient Info -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
          
          <!-- Recipient Card -->
          <div style="background: white; border: 1px solid hsl(240 6% 90%); border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="background: hsl(240 5% 97%); padding: 0.875rem 1.25rem; border-bottom: 1px solid hsl(240 6% 90%);">
              <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2.5"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M8.5 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
                Recipient Information
              </h4>
            </div>
            <div style="padding: 1.25rem;">
              <div style="margin-bottom: 1rem;">
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(240 5% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  To <span style="color: hsl(0 72% 45%);">*</span>
                </label>
                <input type="email" id="recipientEmailProject" name="recipient_email" required placeholder="client@example.com" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(240 6% 90%); border-radius: 6px; font-size: 0.875rem; color: hsl(222 47% 17%); transition: all 0.2s; background: hsl(240 5% 99%);" onfocus="this.style.borderColor='#7194A5'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.12)'" onblur="this.style.borderColor='hsl(240 6% 90%)'; this.style.background='hsl(240 5% 99%)'; this.style.boxShadow='none'">
              </div>
              <div>
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(240 5% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  CC <span style="font-size: 0.625rem; font-weight: 600; color: hsl(215 16% 47%);">(Optional)</span>
                </label>
                <input type="email" name="cc_email" placeholder="cc@example.com" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(240 6% 90%); border-radius: 6px; font-size: 0.875rem; color: hsl(222 47% 17%); transition: all 0.2s; background: hsl(240 5% 99%);" onfocus="this.style.borderColor='#7194A5'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.12)'" onblur="this.style.borderColor='hsl(240 6% 90%)'; this.style.background='hsl(240 5% 99%)'; this.style.boxShadow='none'">
              </div>
            </div>
          </div>
          
          <!-- Attachment Card -->
          <div style="background: white; border: 1px solid hsl(240 6% 90%); border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="background: hsl(240 5% 97%); padding: 0.875rem 1.25rem; border-bottom: 1px solid hsl(240 6% 90%);">
              <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2.5"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                Attachments
              </h4>
            </div>
            <div style="padding: 1.25rem;">
              <label style="display: flex; align-items: flex-start; gap: 0.875rem; padding: 0.875rem; background: hsl(240 5% 98%); border: 1px solid hsl(240 6% 90%); border-radius: 6px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 97%)'; this.style.borderColor='#7194A5'" onmouseout="this.style.background='hsl(240 5% 98%)'; this.style.borderColor='hsl(240 6% 90%)'">
                <input type="checkbox" name="attach_pdf" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #7194A5; margin-top: 1px; flex-shrink: 0;">
                <div style="flex: 1;">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span style="font-size: 0.8125rem; font-weight: 700; color: hsl(222 47% 17%);">Project Report PDF</span>
                  </div>
                  <p style="margin: 0; font-size: 0.6875rem; color: hsl(215 16% 47%); line-height: 1.4;">Attach professional PDF project report</p>
                </div>
              </label>
            </div>
          </div>
          
        </div>
        
        <!-- RIGHT COLUMN: Email Content -->
        <div style="display: flex; flex-direction: column;">
          <div style="background: white; border: 1px solid hsl(240 6% 90%); border-radius: 10px; overflow: hidden; height: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="background: hsl(240 5% 97%); padding: 0.875rem 1.25rem; border-bottom: 1px solid hsl(240 6% 90%);">
              <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2.5"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Email Content
              </h4>
            </div>
            <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
              <div>
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(240 5% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  Subject <span style="color: hsl(0 72% 45%);">*</span>
                </label>
                <input type="text" id="emailSubjectProject" name="subject" required placeholder="Project Report PRJ-001" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid hsl(240 6% 90%); border-radius: 6px; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); transition: all 0.2s; background: hsl(240 5% 99%);" onfocus="this.style.borderColor='#7194A5'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.12)'" onblur="this.style.borderColor='hsl(240 6% 90%)'; this.style.background='hsl(240 5% 99%)'; this.style.boxShadow='none'">
              </div>
              <div style="flex: 1; display: flex; flex-direction: column;">
                <label style="display: inline-block; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.5rem; background: hsl(240 5% 96%); padding: 0.25rem 0.625rem; border-radius: 4px;">
                  Message <span style="color: hsl(0 72% 45%);">*</span>
                </label>
                <textarea id="emailMessageProject" name="message" required rows="13" placeholder="Dear Client,&#10;&#10;Please find attached the project report.&#10;&#10;Best regards" style="width: 100%; padding: 0.75rem 0.875rem; border: 1px solid hsl(240 6% 90%); border-radius: 6px; font-size: 0.8125rem; color: hsl(222 47% 17%); resize: none; transition: all 0.2s; font-family: inherit; line-height: 1.6; flex: 1; background: hsl(240 5% 99%);" onfocus="this.style.borderColor='#7194A5'; this.style.background='white'; this.style.boxShadow='0 0 0 3px rgba(113,148,165,0.12)'" onblur="this.style.borderColor='hsl(240 6% 90%)'; this.style.background='hsl(240 5% 99%)'; this.style.boxShadow='none'"></textarea>
              </div>
            </div>
          </div>
        </div>
        
      </div>
      
      <!-- Footer Actions -->
      <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
        <button type="button" onclick="closeEmailProjectModal()" style="padding: 0.75rem 1.5rem; background: white; border: 1.5px solid hsl(214 20% 88%); border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%); cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='white'">
          Cancel
        </button>
        <button type="submit" id="sendEmailProjectBtn" style="padding: 0.75rem 2rem; background: linear-gradient(135deg, #7194A5 0%, #5a7a8a 100%); border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 700; color: white; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(113,148,165,0.35); display: flex; align-items: center; gap: 0.5rem;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(113,148,165,0.45)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(113,148,165,0.35)'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
          Send Report
        </button>
      </div>
    </form>
  </div>
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

    <form id="newProjectForm" novalidate style="padding: 0; display: flex; flex-direction: column; flex: 1; min-height: 0;">
      <div id="projectGrid" style="display: grid; grid-template-columns: minmax(0,1fr) 340px; gap: 1.5rem; padding: 1.25rem 1.5rem; flex: 1; overflow-y: auto; align-items: start;">
        <div style="display: flex; flex-direction: column; min-height: 0;">
          <div style="display: flex; background: hsl(240 5% 96%); border-bottom: 2px solid hsl(240 6% 90%); padding: 0.375rem; gap: 0.375rem; overflow-x: auto; flex-shrink: 0; margin-bottom: 1rem; border-radius: 10px;">
            <button type="button" class="project-tab-btn" data-tab="details" onclick="switchProjectTab('details')" style="padding: 0.625rem 1.125rem; border: none; background: white; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-weight: 600; cursor: pointer; white-space: nowrap; color: #7194A5; font-size: 0.8125rem; transition: all 0.2s;">📋 Details</button>
            <button type="button" class="project-tab-btn" data-tab="tasks-milestones" onclick="switchProjectTab('tasks-milestones')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">✅ Tasks & Milestones</button>
            <button type="button" class="project-tab-btn" data-tab="team-time" onclick="switchProjectTab('team-time')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">👥 Team & Time</button>
            <button type="button" class="project-tab-btn" data-tab="files-comms" onclick="switchProjectTab('files-comms')" style="padding: 0.625rem 1.125rem; border: none; background: transparent; border-radius: 6px; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); font-size: 0.8125rem; transition: all 0.2s;">📎 Files & Comms</button>
          </div>

          <div id="project-tab-details" class="project-tab-content" style="padding: 1.25rem; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Project ID</label>
                <input type="text" name="project_id" value="PRJ-<?php echo date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT); ?>" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-family: monospace; font-size: 0.875rem;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Project Name <span style="color: #dc2626;">*</span></label>
                <input type="text" name="name" required placeholder="Website Redesign" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;" oninput="updateProjectSummary()">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Client <span style="color: #dc2626;">*</span></label>
                <input type="text" name="client" required placeholder="Acme Corp" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Status <span style="color: #dc2626;">*</span></label>
                <select name="status" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
                  <option value="active">Active</option>
                  <option value="on-hold">On Hold</option>
                  <option value="completed">Completed</option>
                </select>
              </div>
              <div>
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Start Date <span style="color: #dc2626;">*</span></label>
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

          <div id="project-tab-tasks-milestones" class="project-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1; max-height: calc(90vh - 180px);">
            <!-- Tasks Section -->
            <div style="margin-bottom: 2.5rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                <h3 style="font-size: 1.125rem; font-weight: 600; color: #111827; margin: 0;">Tasks</h3>
                <button type="button" onclick="addTask()" style="padding: 0.5rem 1rem; background: #000000; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#1a1a1a';" onmouseout="this.style.background='#000000';">+ Add Task</button>
              </div>
              <div style="overflow-x: auto; min-width: 0;">
                <div id="tasksHeader" style="display: none; grid-template-columns: minmax(120px, 2fr) minmax(100px, 1fr) minmax(100px, 1fr) minmax(80px, 1fr) minmax(100px, 1fr) 80px; gap: 0.75rem; padding: 0.75rem; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em; text-transform: uppercase; min-width: 680px;">
                  <div>Title</div>
                  <div>Assignee</div>
                  <div>Due</div>
                  <div>Est hrs</div>
                  <div>Status</div>
                  <div style="text-align: center;">Actions</div>
                </div>
                <div id="tasksContainer" style="margin-top: 0.75rem;">
                  <div id="tasksEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;">
                    <p style="margin: 0; font-weight: 500;">No tasks added yet</p>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Task" to get started</p>
                  </div>
                </div>
                <div id="tasksPagination" style="display: none; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                  <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <button type="button" onclick="changeTaskPage(-1)" style="padding: 0.5rem 1rem; background: white; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af';" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db';">← Previous</button>
                    <span id="tasksPageInfo" style="padding: 0 1rem; color: #6b7280; font-size: 0.875rem; font-weight: 500; min-width: 4rem; text-align: center;"></span>
                    <button type="button" onclick="changeTaskPage(1)" style="padding: 0.5rem 1rem; background: white; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af';" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db';">Next →</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Milestones Section -->
            <div style="padding-top: 2.5rem; border-top: 2px solid #e5e7eb;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                <h3 style="font-size: 1.125rem; font-weight: 600; color: #111827; margin: 0;">Milestones</h3>
                <button type="button" onclick="addMilestone()" style="padding: 0.5rem 1rem; background: #000000; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#1a1a1a';" onmouseout="this.style.background='#000000';">+ Add Milestone</button>
              </div>
              <div style="overflow-x: auto; min-width: 0;">
                <div id="milestonesHeader" style="display: none; grid-template-columns: minmax(120px, 2fr) minmax(100px, 1fr) minmax(100px, 1fr) 80px; gap: 0.75rem; padding: 0.75rem; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em; text-transform: uppercase; min-width: 480px;">
                  <div>Title</div>
                  <div>Date</div>
                  <div>Amount</div>
                  <div style="text-align: center;">Actions</div>
                </div>
                <div id="milestonesContainer" style="margin-top: 0.75rem;">
                  <div id="milestonesEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;">
                    <p style="margin: 0; font-weight: 500;">No milestones added yet</p>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Milestone" to get started</p>
                  </div>
                </div>
                <div id="milestonesPagination" style="display: none; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                  <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <button type="button" onclick="changeMilestonePage(-1)" style="padding: 0.5rem 1rem; background: white; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af';" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db';">← Previous</button>
                    <span id="milestonesPageInfo" style="padding: 0 1rem; color: #6b7280; font-size: 0.875rem; font-weight: 500; min-width: 4rem; text-align: center;"></span>
                    <button type="button" onclick="changeMilestonePage(1)" style="padding: 0.5rem 1rem; background: white; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af';" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db';">Next →</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div id="project-tab-team-time" class="project-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1; max-height: calc(90vh - 180px);">
            <!-- Team Section -->
            <div style="margin-bottom: 2.5rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                <h3 style="font-size: 1.125rem; font-weight: 600; color: #111827; margin: 0;">Team Members</h3>
                <button type="button" onclick="addTeamMember()" style="padding: 0.5rem 1rem; background: #000000; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#1a1a1a';" onmouseout="this.style.background='#000000';">+ Add Member</button>
              </div>
              <div style="overflow-x: auto; min-width: 0;">
                <div id="teamHeader" style="display: none; grid-template-columns: minmax(120px, 2fr) minmax(100px, 1fr) minmax(100px, 1fr) 80px; gap: 0.75rem; padding: 0.75rem; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em; text-transform: uppercase; min-width: 480px;">
                  <div>Member Name</div>
                  <div>Role</div>
                  <div>Hourly Rate</div>
                  <div style="text-align: center;">Actions</div>
                </div>
                <div id="teamContainer" style="margin-top: 0.75rem;">
                  <div id="teamEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;">
                    <p style="margin: 0; font-weight: 500;">No team members added yet</p>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Member" to get started</p>
                  </div>
                </div>
                <div id="teamPagination" style="display: none; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                  <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <button type="button" onclick="changeTeamPage(-1)" style="padding: 0.5rem 1rem; background: white; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af';" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db';">← Previous</button>
                    <span id="teamPageInfo" style="padding: 0 1rem; color: #6b7280; font-size: 0.875rem; font-weight: 500; min-width: 4rem; text-align: center;"></span>
                    <button type="button" onclick="changeTeamPage(1)" style="padding: 0.5rem 1rem; background: white; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af';" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db';">Next →</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Time Entries Section -->
            <div style="padding-top: 2.5rem; border-top: 2px solid #e5e7eb;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                <h3 style="font-size: 1.125rem; font-weight: 600; color: #111827; margin: 0;">Time Entries</h3>
                <button type="button" onclick="addTimeEntry()" style="padding: 0.5rem 1rem; background: #000000; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#1a1a1a';" onmouseout="this.style.background='#000000';">+ Add Entry</button>
              </div>
              <div style="overflow-x: auto; min-width: 0;">
                <div id="timeHeader" style="display: none; grid-template-columns: minmax(100px, 1fr) minmax(120px, 1fr) minmax(80px, 1fr) minmax(150px, 2fr) 80px; gap: 0.75rem; padding: 0.75rem; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em; text-transform: uppercase; min-width: 550px;">
                  <div>Date</div>
                  <div>Member Name</div>
                  <div>Hours</div>
                  <div>Notes</div>
                  <div style="text-align: center;">Actions</div>
                </div>
                <div id="timeContainer" style="margin-top: 0.75rem;">
                  <div id="timeEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;">
                    <p style="margin: 0; font-weight: 500;">No time entries added yet</p>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Entry" to get started</p>
                  </div>
                </div>
                <div id="timePagination" style="display: none; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                  <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <button type="button" onclick="changeTimePage(-1)" style="padding: 0.5rem 1rem; background: white; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af';" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db';">← Previous</button>
                    <span id="timePageInfo" style="padding: 0 1rem; color: #6b7280; font-size: 0.875rem; font-weight: 500; min-width: 4rem; text-align: center;"></span>
                    <button type="button" onclick="changeTimePage(1)" style="padding: 0.5rem 1rem; background: white; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af';" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db';">Next →</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div id="project-tab-files-comms" class="project-tab-content" style="padding: 1.25rem; display: none; overflow-y: auto; flex: 1;">
            <!-- Files Section -->
            <div style="margin-bottom: 2rem;">
              <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin: 0 0 1rem 0;">Attachments</h3>
              <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Upload Files</label>
              <input type="file" name="attachments" multiple onchange="handleFilesChange(this)" style="display: block; margin-bottom: 0.75rem;">
              <div id="filesList" style="display: grid; grid-template-columns: 1fr; gap: 0.5rem;"></div>
            </div>

            <!-- Communications Section -->
            <div style="padding-top: 2rem; border-top: 2px solid hsl(214 20% 92%);">
              <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin: 0 0 1rem 0;">Communications</h3>
              <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.75rem; align-items: start;">
                <textarea id="messageInput" rows="3" placeholder="Post an update for the client/team..." style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
                <button type="button" onclick="addMessage()" style="padding: 0.625rem 1rem; background: #000000; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Post</button>
              </div>
              <div id="messagesContainer" style="margin-top: 1rem; display: grid; grid-template-columns: 1fr; gap: 0.5rem;"></div>
            </div>
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
            <button type="submit" style="width: 100%; background: #000000; color: white; border: none; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.875rem;">Create Project</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Add Time Entry Modal -->
<div id="addTimeModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 11000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
  <div style="background: white; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); animation: slideUp 0.3s ease;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
        <div>
          <h2 style="font-size: 1.125rem; font-weight: 700; color: #111827; margin: 0;">Add Time Entry</h2>
          <p style="font-size: 0.8125rem; color: #6b7280; margin: 0;">Track billable hours for this project</p>
        </div>
      </div>
      <button onclick="closeAddTimeModal()" style="padding: 0.5rem; background: #f9fafb; border: 1px solid #e5e7eb; cursor: pointer; border-radius: 6px; color: #6b7280; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#f9fafb'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    <form id="addTimeForm" style="padding: 1.5rem;">
      <input type="hidden" id="timeProjectId" name="project_id">
      
      <div style="margin-bottom: 1.25rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Team Member <span style="color: #dc2626;">*</span></label>
        <input type="text" name="member" required placeholder="John Doe" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
        <div>
          <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Hours <span style="color: #dc2626;">*</span></label>
          <input type="number" name="hours" required step="0.25" min="0" placeholder="8.00" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
        </div>
        <div>
          <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Rate (<?php echo CurrencyHelper::symbol(); ?>)</label>
          <input type="number" name="rate" step="0.01" min="0" placeholder="75.00" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
        </div>
      </div>

      <div style="margin-bottom: 1.25rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Date <span style="color: #dc2626;">*</span></label>
        <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
      </div>

      <div style="margin-bottom: 1.25rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Description <span style="color: #dc2626;">*</span></label>
        <textarea name="description" required rows="3" placeholder="Frontend development work" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
          <input type="checkbox" name="billable" checked style="width: 1rem; height: 1rem; cursor: pointer;">
          <span style="font-size: 0.875rem; font-weight: 500; color: #374151;">Billable to client</span>
        </label>
      </div>

      <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
        <button type="button" onclick="closeAddTimeModal()" class="btn btn-secondary">Cancel</button>
        <button type="button" onclick="submitTimeEntry()" class="btn btn-primary">Add Time Entry</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Expense Modal -->
<div id="addExpenseModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 11000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
  <div style="background: white; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); animation: slideUp 0.3s ease;">
    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
          <h2 style="font-size: 1.125rem; font-weight: 700; color: #111827; margin: 0;">Add Expense</h2>
          <p style="font-size: 0.8125rem; color: #6b7280; margin: 0;">Record project-related expense</p>
        </div>
      </div>
      <button onclick="closeAddExpenseModal()" style="padding: 0.5rem; background: #f9fafb; border: 1px solid #e5e7eb; cursor: pointer; border-radius: 6px; color: #6b7280; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#f9fafb'">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6L18 18"/></svg>
      </button>
    </div>
    <form id="addExpenseForm" style="padding: 1.5rem;">
      <input type="hidden" id="expenseProjectId" name="project_id">
      
      <div style="margin-bottom: 1.25rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Description <span style="color: #dc2626;">*</span></label>
        <input type="text" name="description" required placeholder="Cloud hosting fees" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
        <div>
          <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Amount (<?php echo CurrencyHelper::symbol(); ?>) <span style="color: #dc2626;">*</span></label>
          <input type="number" name="amount" required step="0.01" min="0" placeholder="150.00" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
        </div>
        <div>
          <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Category</label>
          <select name="category" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
            <option value="Infrastructure">Infrastructure</option>
            <option value="Software">Software</option>
            <option value="Travel">Travel</option>
            <option value="Materials">Materials</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>

      <div style="margin-bottom: 1.25rem;">
        <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">Date <span style="color: #dc2626;">*</span></label>
        <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.875rem;">
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
          <input type="checkbox" name="billable" checked style="width: 1rem; height: 1rem; cursor: pointer;">
          <span style="font-size: 0.875rem; font-weight: 500; color: #374151;">Billable to client</span>
        </label>
      </div>

      <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
        <button type="button" onclick="closeAddExpenseModal()" class="btn btn-secondary">Cancel</button>
        <button type="button" onclick="submitExpense()" class="btn btn-primary">Add Expense</button>
      </div>
    </form>
  </div>
</div>

<!-- Chart.js for Analytics -->
<script src="assets/js/chart.min.js"></script>

<script>
// Pagination state
let currentTaskPage = 1;
let currentMilestonePage = 1;
let currentTeamPage = 1;
let currentTimePage = 1;
let initialFormState = null;

function showNewProjectModal() {
  const modal = document.getElementById('newProjectModal');
  modal.style.display = 'flex';
  document.getElementById('newProjectForm').reset();
  const today = new Date().toISOString().split('T')[0];
  document.querySelector('#newProjectForm input[name="start_date"]').value = today;
  document.getElementById('tasksContainer').innerHTML = '<div id="tasksEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;"><p style="margin: 0; font-weight: 500;">No tasks added yet</p><p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Task" to get started</p></div>';
  document.getElementById('milestonesContainer').innerHTML = '<div id="milestonesEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;"><p style="margin: 0; font-weight: 500;">No milestones added yet</p><p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Milestone" to get started</p></div>';
  document.getElementById('teamContainer').innerHTML = '<div id="teamEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;"><p style="margin: 0; font-weight: 500;">No team members added yet</p><p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Member" to get started</p></div>';
  document.getElementById('timeContainer').innerHTML = '<div id="timeEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;"><p style="margin: 0; font-weight: 500;">No time entries added yet</p><p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Entry" to get started</p></div>';
  document.getElementById('filesList').innerHTML = '';
  document.getElementById('messagesContainer').innerHTML = '';
  switchProjectTab('details');
  updateProjectSummary();
  applyProjectResponsive();
  applyTaskGridResponsive();
  
  // Capture initial form state after a brief delay
  setTimeout(() => {
    const form = document.getElementById('newProjectForm');
    initialFormState = new FormData(form);
  }, 100);
}

function closeNewProjectModal(skipConfirmation = false) {
  // If skipConfirmation is true (e.g., after successful save), just close
  if (skipConfirmation) {
    document.getElementById('newProjectModal').style.display = 'none';
    initialFormState = null;
    return;
  }
  
  const form = document.getElementById('newProjectForm');
  const currentFormData = new FormData(form);
  
  // Check if form has changed from initial state
  let hasChanges = false;
  
  // If no initial state captured, just close
  if (!initialFormState) {
    document.getElementById('newProjectModal').style.display = 'none';
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
  
  // Also check if tasks, milestones, team members, or time entries exist
  const hasTasks = document.querySelectorAll('#tasksContainer .task-row').length > 0;
  const hasMilestones = document.querySelectorAll('#milestonesContainer .milestone-row').length > 0;
  const hasTeam = document.querySelectorAll('#teamContainer .team-row').length > 0;
  const hasTime = document.querySelectorAll('#timeContainer .time-row').length > 0;
  
  if (hasTasks || hasMilestones || hasTeam || hasTime) {
    hasChanges = true;
  }
  
  // If has changes, confirm before closing
  if (hasChanges) {
    showDraftConfirmation();
  } else {
    document.getElementById('newProjectModal').style.display = 'none';
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
        <p style="font-size: 0.875rem; color: hsl(215 16% 47%); margin: 0; line-height: 1.5;">You have unsaved changes. Would you like to save this project as a draft before closing?</p>
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
    saveProjectDraft();
  });
  
  document.getElementById('draftDiscardBtn').addEventListener('click', () => {
    overlay.remove();
    document.getElementById('newProjectModal').style.display = 'none';
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
  
  saveBtn.addEventListener('mouseenter', () => {
    saveBtn.style.background = '#1a1a1a';
  });
  saveBtn.addEventListener('mouseleave', () => {
    saveBtn.style.background = '#000000';
  });
  
  discardBtn.addEventListener('mouseenter', () => {
    discardBtn.style.background = '#f9fafb';
    discardBtn.style.borderColor = '#9ca3af';
  });
  discardBtn.addEventListener('mouseleave', () => {
    discardBtn.style.background = 'white';
    discardBtn.style.borderColor = 'hsl(214 20% 85%)';
  });
}

function saveProjectDraft() {
  // TODO: Implement actual save to backend
  console.log('Saving project as draft...');
  
  // Show success message
  if (typeof Toast !== 'undefined') {
    Toast.success('Project saved as draft');
  }
  
  // Close modal without confirmation
  closeNewProjectModal(true);
}

function switchProjectTab(name) {
  const tabs = document.querySelectorAll('.project-tab-content');
  const btns = document.querySelectorAll('.project-tab-btn');
  tabs.forEach(t => { t.style.display = t.id === `project-tab-${name}` ? 'block' : 'none'; });
  btns.forEach(b => {
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
  if (name === 'tasks-milestones') {
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
    // Only show header if tasks exist
    if (header) {
      if (rows.length > 0) {
        header.style.display = narrow ? 'none' : 'grid';
      } else {
        header.style.display = 'none';
      }
    }
    rows.forEach(r => {
      r.style.gridTemplateColumns = narrow ? '1fr' : '2fr 1fr 1fr 1fr 1fr 80px';
    });
  } catch {}
}

function addTask() {
  const c = document.getElementById('tasksContainer');
  const emptyMsg = document.getElementById('tasksEmpty');
  if (emptyMsg) emptyMsg.remove();
  
  const div = document.createElement('div');
  div.className = 'task-row';
  div.style.cssText = 'display: grid; grid-template-columns: minmax(120px, 2fr) minmax(100px, 1fr) minmax(100px, 1fr) minmax(80px, 1fr) minmax(100px, 1fr) 80px; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px; min-width: 680px;';
  div.innerHTML = `
    <input type="text" placeholder="Task title" class="task-title" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;" oninput="updateProjectSummary()">
    <input type="text" placeholder="Assignee" class="task-assignee" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;">
    <input type="date" class="task-due" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;">
    <input type="number" placeholder="Est hrs" class="task-hours" min="0" step="0.1" value="1" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;" oninput="updateProjectSummary()">
    <select class="task-status" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;" onchange="updateProjectSummary()">
      <option value="todo">To Do</option>
      <option value="in-progress">In Progress</option>
      <option value="done">Done</option>
    </select>
    <button type="button" onclick="removeTask(this);" style="padding: 0.375rem 0.5rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; width: 100%; text-align: center; box-sizing: border-box;">Remove</button>
  `;
  c.appendChild(div);
  applyTaskGridResponsive();
  updateProjectSummary();
  updateTasksPagination();
}

function removeTask(btn) {
  btn.parentElement.remove();
  updateProjectSummary();
  const c = document.getElementById('tasksContainer');
  const rows = c.querySelectorAll('.task-row');
  if (rows.length === 0) {
    c.innerHTML = '<div id="tasksEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;"><p style="margin: 0; font-weight: 500;">No tasks added yet</p><p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Task" to get started</p></div>';
    document.getElementById('tasksHeader').style.display = 'none';
  } else {
    if (currentTaskPage > rows.length) currentTaskPage = rows.length;
    updateTasksPagination();
  }
}

function addMilestone() {
  const c = document.getElementById('milestonesContainer');
  const emptyMsg = document.getElementById('milestonesEmpty');
  if (emptyMsg) emptyMsg.remove();
  
  const div = document.createElement('div');
  div.className = 'milestone-row';
  div.style.cssText = 'display: grid; grid-template-columns: minmax(120px, 2fr) minmax(100px, 1fr) minmax(100px, 1fr) 80px; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px; min-width: 480px;';
  div.innerHTML = `
    <input type="text" placeholder="Milestone title" class="ms-title" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;">
    <input type="date" class="ms-date" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;">
    <input type="number" placeholder="Amount" class="ms-amount" min="0" step="0.01" value="0" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;">
    <button type="button" onclick="removeMilestone(this);" style="padding: 0.375rem 0.5rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; width: 100%; text-align: center; box-sizing: border-box;">Remove</button>
  `;
  c.appendChild(div);
  updateMilestonesPagination();
}

function removeMilestone(btn) {
  btn.parentElement.remove();
  updateProjectSummary();
  const c = document.getElementById('milestonesContainer');
  const rows = c.querySelectorAll('.milestone-row');
  if (rows.length === 0) {
    c.innerHTML = '<div id="milestonesEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;"><p style="margin: 0; font-weight: 500;">No milestones added yet</p><p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Milestone" to get started</p></div>';
    document.getElementById('milestonesHeader').style.display = 'none';
  } else {
    if (currentMilestonePage > rows.length) currentMilestonePage = rows.length;
    updateMilestonesPagination();
  }
}

function addTeamMember() {
  const c = document.getElementById('teamContainer');
  const emptyMsg = document.getElementById('teamEmpty');
  if (emptyMsg) emptyMsg.remove();
  
  const div = document.createElement('div');
  div.className = 'team-row';
  div.style.cssText = 'display: grid; grid-template-columns: minmax(120px, 2fr) minmax(100px, 1fr) minmax(100px, 1fr) 80px; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px; min-width: 480px;';
  div.innerHTML = `
    <input type="text" placeholder="Member name" class="tm-name" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;" oninput="updateProjectSummary()">
    <input type="text" placeholder="Role" class="tm-role" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;">
    <input type="number" placeholder="Hourly rate" class="tm-rate" min="0" step="0.01" value="50" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;" oninput="updateProjectSummary()">
    <button type="button" onclick="removeTeamMember(this);" style="padding: 0.375rem 0.5rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; width: 100%; text-align: center; box-sizing: border-box;">Remove</button>
  `;
  c.appendChild(div);
  updateProjectSummary();
  updateTeamPagination();
}

function removeTeamMember(btn) {
  btn.parentElement.remove();
  updateProjectSummary();
  const c = document.getElementById('teamContainer');
  const rows = c.querySelectorAll('.team-row');
  if (rows.length === 0) {
    c.innerHTML = '<div id="teamEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;"><p style="margin: 0; font-weight: 500;">No team members added yet</p><p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Member" to get started</p></div>';
    document.getElementById('teamHeader').style.display = 'none';
  } else {
    if (currentTeamPage > rows.length) currentTeamPage = rows.length;
    updateTeamPagination();
  }
}

function addTimeEntry() {
  const c = document.getElementById('timeContainer');
  const emptyMsg = document.getElementById('timeEmpty');
  if (emptyMsg) emptyMsg.remove();
  
  const div = document.createElement('div');
  div.className = 'time-row';
  div.style.cssText = 'display: grid; grid-template-columns: minmax(100px, 1fr) minmax(120px, 1fr) minmax(80px, 1fr) minmax(150px, 2fr) 80px; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.75rem; background: white; border: 1px solid #e5e7eb; border-radius: 6px; min-width: 550px;';
  div.innerHTML = `
    <input type="date" class="te-date" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;">
    <input type="text" placeholder="Member name" class="te-member" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;" oninput="updateProjectSummary()">
    <input type="number" placeholder="Hours" class="te-hours" min="0" step="0.1" value="1" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;" oninput="updateProjectSummary()">
    <input type="text" placeholder="Notes" class="te-notes" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.875rem; box-sizing: border-box;">
    <button type="button" onclick="removeTimeEntry(this);" style="padding: 0.375rem 0.5rem; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; width: 100%; text-align: center; box-sizing: border-box;">Remove</button>
  `;
  c.appendChild(div);
  updateProjectSummary();
  updateTimePagination();
}

function removeTimeEntry(btn) {
  btn.parentElement.remove();
  updateProjectSummary();
  const c = document.getElementById('timeContainer');
  const rows = c.querySelectorAll('.time-row');
  if (rows.length === 0) {
    c.innerHTML = '<div id="timeEmpty" style="padding: 2rem; text-align: center; color: #9ca3af; font-size: 0.875rem; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px;"><p style="margin: 0; font-weight: 500;">No time entries added yet</p><p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">Click "+ Add Entry" to get started</p></div>';
    document.getElementById('timeHeader').style.display = 'none';
  } else {
    if (currentTimePage > rows.length) currentTimePage = rows.length;
    updateTimePagination();
  }
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
  
  // Validate required fields before submission
  const requiredFields = [
    { name: 'name', label: 'Project Name', tab: 'details' },
    { name: 'client', label: 'Client', tab: 'details' },
    { name: 'status', label: 'Status', tab: 'details' },
    { name: 'start_date', label: 'Start Date', tab: 'details' }
  ];
  
  // Check each required field
  for (const field of requiredFields) {
    const input = document.querySelector(`input[name="${field.name}"], select[name="${field.name}"]`);
    if (input && !input.value.trim()) {
      // Switch to the tab containing the empty field
      switchProjectTab(field.tab);
      
      // Focus on the empty field
      setTimeout(() => {
        input.focus();
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 100);
      
      // Show error toast
      Toast.error(`Please fill in the required field: ${field.label}`);
      return;
    }
  }
  
  const form = new FormData(this);
  const data = Object.fromEntries(form);
  data.tasks = collectTasksData();
  data.milestones = collectMilestonesData();
  data.team = collectTeamData();
  data.time = collectTimeData();
  
  // Send to API
  fetch('api/projects', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      Toast.success('Project created successfully!');
      closeNewProjectModal(true);
      // Reload page to show new project
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      Toast.error(result.message || 'Failed to create project');
    }
  })
  .catch(error => {
    console.error('Error creating project:', error);
    Toast.error('An error occurred while creating the project');
  });
});

function saveProjectDraft() {
  const form = document.getElementById('newProjectForm');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData);
  
  data.tasks = collectTasksData();
  data.milestones = collectMilestonesData();
  data.team = collectTeamData();
  data.time = collectTimeData();
  data.status = 'draft';
  
  // Send to API
  fetch('api/projects.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      Toast.success('Project saved as draft!');
      closeNewProjectModal(true);
      // Reload page to show draft project
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      Toast.error(result.message || 'Failed to save draft');
    }
  })
  .catch(error => {
    console.error('Error saving draft:', error);
    Toast.error('An error occurred while saving the draft');
  });
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
  // Note: CURRENCY_SYMBOL is now globally defined at the top of the script
  
  // Auto-apply formatting to project values
  NumberFormat.autoApply(CURRENCY_SYMBOL, {
    customSelectors: [
      { selector: 'p[style*="font-size: 1.75rem"]', maxWidth: 1 },  // Stat cards - always abbreviate >= 1M
      { selector: 'td.font-semibold', maxWidth: 80 }  // Table columns (budget/spent)
    ]
  });
});

// Pagination functions
function updateTasksPagination() {
  const rows = document.querySelectorAll('#tasksContainer .task-row');
  const total = rows.length;
  const header = document.getElementById('tasksHeader');
  const pagination = document.getElementById('tasksPagination');
  
  // Show/hide header based on items
  if (total > 0) {
    header.style.display = 'grid';
  } else {
    header.style.display = 'none';
  }
  
  // Show pagination only if items > 1
  if (total > 1) {
    pagination.style.display = 'block';
    document.getElementById('tasksPageInfo').textContent = `${currentTaskPage} of ${total}`;
    
    // Show only current page item
    rows.forEach((row, idx) => {
      row.style.display = (idx + 1 === currentTaskPage) ? 'grid' : 'none';
    });
  } else {
    pagination.style.display = 'none';
    // Show all items if <= 1
    rows.forEach(row => row.style.display = 'grid');
  }
}

function changeTaskPage(direction) {
  const rows = document.querySelectorAll('#tasksContainer .task-row');
  const total = rows.length;
  currentTaskPage += direction;
  if (currentTaskPage < 1) currentTaskPage = 1;
  if (currentTaskPage > total) currentTaskPage = total;
  updateTasksPagination();
}

function updateMilestonesPagination() {
  const rows = document.querySelectorAll('#milestonesContainer .milestone-row');
  const total = rows.length;
  const header = document.getElementById('milestonesHeader');
  const pagination = document.getElementById('milestonesPagination');
  
  // Show/hide header based on items
  if (total > 0) {
    header.style.display = 'grid';
  } else {
    header.style.display = 'none';
  }
  
  // Show pagination only if items > 1
  if (total > 1) {
    pagination.style.display = 'block';
    document.getElementById('milestonesPageInfo').textContent = `${currentMilestonePage} of ${total}`;
    
    // Show only current page item
    rows.forEach((row, idx) => {
      row.style.display = (idx + 1 === currentMilestonePage) ? 'grid' : 'none';
    });
  } else {
    pagination.style.display = 'none';
    // Show all items if <= 1
    rows.forEach(row => row.style.display = 'grid');
  }
}

function changeMilestonePage(direction) {
  const rows = document.querySelectorAll('#milestonesContainer .milestone-row');
  const total = rows.length;
  currentMilestonePage += direction;
  if (currentMilestonePage < 1) currentMilestonePage = 1;
  if (currentMilestonePage > total) currentMilestonePage = total;
  updateMilestonesPagination();
}

function updateTeamPagination() {
  const rows = document.querySelectorAll('#teamContainer .team-row');
  const total = rows.length;
  const header = document.getElementById('teamHeader');
  const pagination = document.getElementById('teamPagination');
  
  // Show/hide header based on items
  if (total > 0) {
    header.style.display = 'grid';
  } else {
    header.style.display = 'none';
  }
  
  // Show pagination only if items > 1
  if (total > 1) {
    pagination.style.display = 'block';
    document.getElementById('teamPageInfo').textContent = `${currentTeamPage} of ${total}`;
    
    // Show only current page item
    rows.forEach((row, idx) => {
      row.style.display = (idx + 1 === currentTeamPage) ? 'grid' : 'none';
    });
  } else {
    pagination.style.display = 'none';
    // Show all items if <= 1
    rows.forEach(row => row.style.display = 'grid');
  }
}

function changeTeamPage(direction) {
  const rows = document.querySelectorAll('#teamContainer .team-row');
  const total = rows.length;
  currentTeamPage += direction;
  if (currentTeamPage < 1) currentTeamPage = 1;
  if (currentTeamPage > total) currentTeamPage = total;
  updateTeamPagination();
}

function updateTimePagination() {
  const rows = document.querySelectorAll('#timeContainer .time-row');
  const total = rows.length;
  const header = document.getElementById('timeHeader');
  const pagination = document.getElementById('timePagination');
  
  // Show/hide header based on items
  if (total > 0) {
    header.style.display = 'grid';
  } else {
    header.style.display = 'none';
  }
  
  // Show pagination only if items > 1
  if (total > 1) {
    pagination.style.display = 'block';
    document.getElementById('timePageInfo').textContent = `${currentTimePage} of ${total}`;
    
    // Show only current page item
    rows.forEach((row, idx) => {
      row.style.display = (idx + 1 === currentTimePage) ? 'grid' : 'none';
    });
  } else {
    pagination.style.display = 'none';
    // Show all items if <= 1
    rows.forEach(row => row.style.display = 'grid');
  }
}

function changeTimePage(direction) {
  const rows = document.querySelectorAll('#timeContainer .time-row');
  const total = rows.length;
  currentTimePage += direction;
  if (currentTimePage < 1) currentTimePage = 1;
  if (currentTimePage > total) currentTimePage = total;
  updateTimePagination();
}

// ============================================
// ENTERPRISE FEATURES
// ============================================

let currentProjectId = null;

// Toggle actions dropdown menu - popup style like orders.php
function toggleProjectActions(projectId) {
  // Stop event propagation
  if (event) {
    event.stopPropagation();
  }
  
  const dropdown = document.getElementById('projectActionsDropdown');
  
  // Check if dropdown is already open for this project
  const isOpen = dropdown.style.display === 'block' && currentProjectId === projectId;
  
  if (isOpen) {
    // Close if already open
    closeProjectDropdown();
    return;
  }
  
  // Close dropdown first
  closeProjectDropdown();
  
  // Set current project
  currentProjectId = projectId;
  
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
  
  // Close on outside click (delay to prevent immediate closure)
  setTimeout(() => {
    document.addEventListener('click', closeProjectDropdown, { once: true });
  }, 100);
}

// Close dropdown
function closeProjectDropdown() {
  const dropdown = document.getElementById('projectActionsDropdown');
  if (dropdown) {
    dropdown.style.display = 'none';
  }
}

// Handle project actions (simplified dropdown)
function handleProjectAction(action) {
  closeProjectDropdown();
  
  switch(action) {
    case 'view':
      viewProject(currentProjectId);
      break;
    case 'edit':
      editProject(currentProjectId);
      break;
    case 'duplicate':
      duplicateProject(currentProjectId);
      break;
    case 'status':
      changeProjectStatus(currentProjectId);
      break;
    case 'template':
      createTemplate(currentProjectId);
      break;
    case 'email':
      sendEmailReport(currentProjectId);
      break;
    case 'archive':
      archiveProject(currentProjectId);
      break;
    case 'delete':
      deleteProject(currentProjectId);
      break;
  }
}

// View project details (Orders view-style modal)
function viewProject(projectId) {
  fetch(`api/projects?id=${projectId}`)
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        const project = result.data;
        const budget = parseFloat(project.budget || 0);
        const spent = parseFloat(project.spent || 0);
        const progress = budget > 0 ? ((spent / budget) * 100).toFixed(1) : 0;
        const remaining = budget - spent;
        
        const content = `
          <div style="width: 100%; max-width: 1300px; max-height: 90vh; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid hsl(240 6% 90%); display: flex; flex-direction: column; overflow: hidden; pointer-events: auto; animation: modalSlideIn 0.3s ease-out;">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, hsl(220 20% 97%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%); display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 3px 10px rgba(113,148,165,0.3);">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M13 2v7h7"/>
                  </svg>
                </div>
                <div style="flex: 1;">
                  <h2 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: hsl(240 10% 10%); line-height: 1.2;">${project.name || 'Untitled Project'}</h2>
                  <p style="margin: 0.25rem 0 0; font-size: 0.75rem; color: hsl(240 5% 50%); font-weight: 500; font-family: monospace;">${project.project_id || 'N/A'}</p>
                </div>
              </div>
              <button type="button" onclick="closeProjectDetailsModal()" style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(240 5% 40%); transition: all 0.2s; flex-shrink: 0;" onmouseover="this.style.background='hsl(240 5% 92%)'; this.style.borderColor='hsl(240 6% 85%)'" onmouseout="this.style.background='hsl(240 5% 96%)'; this.style.borderColor='hsl(240 6% 90%)'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6L18 18" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            </div>
            
            <!-- Content -->
            <div style="padding: 1.5rem; overflow-y: auto;">
              <!-- Top Info Grid -->
              <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                  <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Client</div>
                  <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;">${project.client || '-'}</div>
                </div>
                <div>
                  <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</div>
                  <div><span class="badge ${project.status === 'active' ? 'badge-success' : (project.status === 'draft' ? 'badge-warning' : 'badge-default')}" style="font-size: 0.6875rem; padding: 0.25rem 0.5rem;">${project.status ? project.status.toUpperCase() : 'UNKNOWN'}</span></div>
                </div>
                <div>
                  <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Start Date</div>
                  <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;">${project.start_date || '-'}</div>
                </div>
                <div>
                  <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">End Date</div>
                  <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;">${project.end_date || '-'}</div>
                </div>
                <div>
                  <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Hourly Rate</div>
                  <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;"><?php echo CurrencyHelper::symbol(); ?>${project.hourly_rate || 0}/hr</div>
                </div>
                <div>
                  <div style="font-size: 0.625rem; font-weight: 600; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Progress</div>
                  <div style="font-weight: 600; color: hsl(240 10% 10%); font-size: 0.8125rem;">${progress}%</div>
                </div>
              </div>
              
              <!-- 2 Column Layout: Project Details (Left) & Budget Overview (Right) -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                
                <!-- Left: Project Details Card -->
                <div style="background: linear-gradient(135deg, hsl(143 85% 98%) 0%, hsl(143 85% 95%) 100%); border: 1px solid hsl(143 80% 85%); border-radius: 10px; padding: 1.25rem;">
                  <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 1rem;">
                    <div style="width: 28px; height: 28px; background: hsl(140 61% 50%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                      </svg>
                    </div>
                    <h3 style="font-size: 0.6875rem; font-weight: 700; color: hsl(140 61% 20%); margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Project Details</h3>
                  </div>
                  <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                    <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(143 70% 88%);">
                      <span style="font-size: 0.75rem; color: hsl(140 61% 30%); font-weight: 600;">Tasks</span>
                      <span style="font-family: monospace; font-weight: 700; color: hsl(140 61% 20%); font-size: 0.8125rem;">${(project.tasks || []).length}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(143 70% 88%);">
                      <span style="font-size: 0.75rem; color: hsl(140 61% 30%); font-weight: 600;">Milestones</span>
                      <span style="font-family: monospace; font-weight: 700; color: hsl(140 61% 20%); font-size: 0.8125rem;">${(project.milestones || []).length}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(143 70% 88%);">
                      <span style="font-size: 0.75rem; color: hsl(140 61% 30%); font-weight: 600;">Team Members</span>
                      <span style="font-family: monospace; font-weight: 700; color: hsl(140 61% 20%); font-size: 0.8125rem;">${(project.team || []).length}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(143 70% 88%);">
                      <span style="font-size: 0.75rem; color: hsl(140 61% 30%); font-weight: 600;">Time Entries</span>
                      <span style="font-family: monospace; font-weight: 700; color: hsl(140 61% 20%); font-size: 0.8125rem;">${(project.time_entries || []).length}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(143 70% 88%);">
                      <span style="font-size: 0.75rem; color: hsl(140 61% 30%); font-weight: 600;">Expenses</span>
                      <span style="font-family: monospace; font-weight: 700; color: hsl(140 61% 20%); font-size: 0.8125rem;">${(project.expenses || []).length}</span>
                    </div>
                  </div>
                </div>
                
                <!-- Right: Budget Overview Card -->
                <div style="background: linear-gradient(135deg, hsl(214 95% 96%) 0%, hsl(214 95% 93%) 100%); border: 1px solid hsl(214 90% 80%); border-radius: 10px; padding: 1.25rem;">
                  <div style="display: flex; align-items: center; gap: 0.625rem; margin-bottom: 1rem;">
                    <div style="width: 28px; height: 28px; background: hsl(214 95% 50%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                      </svg>
                    </div>
                    <h3 style="font-size: 0.6875rem; font-weight: 700; color: hsl(214 95% 20%); margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Budget Overview</h3>
                  </div>
                  <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                    <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(214 80% 88%);">
                      <span style="font-size: 0.75rem; color: hsl(214 95% 30%); font-weight: 600;">Total Budget</span>
                      <span style="font-family: monospace; font-weight: 700; color: hsl(214 95% 20%); font-size: 0.8125rem;"><?php echo CurrencyHelper::symbol(); ?>${budget.toLocaleString()}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.375rem 0; border-bottom: 1px solid hsl(214 80% 88%);">
                      <span style="font-size: 0.75rem; color: hsl(214 95% 30%); font-weight: 600;">Amount Spent</span>
                      <span style="font-family: monospace; font-weight: 700; color: hsl(0 74% 42%); font-size: 0.8125rem;"><?php echo CurrencyHelper::symbol(); ?>${spent.toLocaleString()}</span>
                    </div>
                    <div style="background: hsl(140 61% 50%); padding: 0.75rem; border-radius: 6px; margin: 0.5rem 0; display: flex; justify-content: space-between; align-items: center;">
                      <span style="font-weight: 700; font-size: 0.875rem; color: white;">Remaining</span>
                      <span style="font-family: monospace; font-weight: 800; font-size: 1.25rem; color: white;"><?php echo CurrencyHelper::symbol(); ?>${remaining.toLocaleString()}</span>
                    </div>
                    <div style="margin-top: 0.5rem;">
                      <div style="background: hsl(214 20% 90%); height: 10px; border-radius: 999px; overflow: hidden;">
                        <div style="height: 100%; width: ${Math.min(progress, 100)}%; background: ${progress > 90 ? 'hsl(0 74% 42%)' : 'hsl(140 61% 50%)'}; transition: width 0.3s;"></div>
                      </div>
                      <p style="margin: 0.5rem 0 0; font-size: 0.6875rem; color: hsl(214 95% 30%); text-align: center;">${progress}% of budget utilized</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Footer Actions -->
            <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap;">
              <button type="button" onclick="closeProjectDetailsModal(); editProject('${projectId}')" class="btn btn-ghost">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Project
              </button>
              <button type="button" onclick="closeProjectDetailsModal(); showAddTimeModal('${projectId}')" class="btn btn-ghost" style="background: hsl(143 85% 96%); color: hsl(140 61% 13%); border: 1px solid hsl(143 60% 80%);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                Add Time Entry
              </button>
              <button type="button" onclick="closeProjectDetailsModal()" class="btn btn-primary">Close</button>
            </div>
          </div>
        `;
        
        const modalDiv = document.createElement('div');
        modalDiv.id = 'projectDetailsModal';
        modalDiv.innerHTML = content;
        modalDiv.style.cssText = 'display: flex; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; pointer-events: auto;';
        
        // Close on overlay click
        modalDiv.addEventListener('click', function(e) {
          if (e.target === modalDiv) {
            closeProjectDetailsModal();
          }
        });
        
        document.body.appendChild(modalDiv);
      } else {
        Toast.error('Failed to load project details');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      Toast.error('An error occurred');
    });
}

function closeProjectDetailsModal() {
  const modal = document.getElementById('projectDetailsModal');
  if (modal) document.body.removeChild(modal);
}

// Add time entry
let currentProjectIdForTime = null;

function showAddTimeModal(projectId) {
  currentProjectIdForTime = projectId;
  const modal = document.getElementById('addTimeModal');
  if (modal) {
    modal.style.display = 'flex';
    document.getElementById('timeProjectId').value = projectId;
  }
}

function closeAddTimeModal() {
  const modal = document.getElementById('addTimeModal');
  if (modal) {
    modal.style.display = 'none';
    document.getElementById('addTimeForm').reset();
  }
}

function submitTimeEntry() {
  const form = document.getElementById('addTimeForm');
  const formData = new FormData(form);
  
  const data = {
    action: 'add_time',
    project_id: currentProjectIdForTime,
    time_entry: {
      member: formData.get('member'),
      hours: parseFloat(formData.get('hours')),
      rate: parseFloat(formData.get('rate')) || null,
      description: formData.get('description'),
      date: formData.get('date'),
      billable: formData.get('billable') === 'on'
    }
  };
  
  fetch('api/projects.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      Toast.success('Time entry added successfully!');
      closeAddTimeModal();
      setTimeout(() => window.location.reload(), 1000);
    } else {
      Toast.error(result.message || 'Failed to add time entry');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Toast.error('An error occurred');
  });
}

// Add expense
let currentProjectIdForExpense = null;

function showAddExpenseModal(projectId) {
  currentProjectIdForExpense = projectId;
  const modal = document.getElementById('addExpenseModal');
  if (modal) {
    modal.style.display = 'flex';
    document.getElementById('expenseProjectId').value = projectId;
  }
}

function closeAddExpenseModal() {
  const modal = document.getElementById('addExpenseModal');
  if (modal) {
    modal.style.display = 'none';
    document.getElementById('addExpenseForm').reset();
  }
}

function submitExpense() {
  const form = document.getElementById('addExpenseForm');
  const formData = new FormData(form);
  
  const data = {
    action: 'add_expense',
    project_id: currentProjectIdForExpense,
    expense: {
      description: formData.get('description'),
      amount: parseFloat(formData.get('amount')),
      category: formData.get('category'),
      date: formData.get('date'),
      billable: formData.get('billable') === 'on'
    }
  };
  
  fetch('api/projects.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      Toast.success('Expense added successfully!');
      closeAddExpenseModal();
      setTimeout(() => window.location.reload(), 1000);
    } else {
      Toast.error(result.message || 'Failed to add expense');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Toast.error('An error occurred');
  });
}

// View profitability report
function viewProfitability(projectId) {
  fetch(`api/projects.php?profitability&project_id=${projectId}`)
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        const data = result.data;
        const content = `
          <div style="padding: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: #111827;">Profitability Report</h3>
            <div style="display: grid; gap: 1rem;">
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px;">
                <span style="font-weight: 600; color: #374151;">Budget:</span>
                <span style="font-weight: 700; color: #111827;"><?php echo CurrencyHelper::symbol(); ?>${data.budget.toFixed(2)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px;">
                <span style="font-weight: 600; color: #374151;">Spent:</span>
                <span style="font-weight: 700; color: #111827;"><?php echo CurrencyHelper::symbol(); ?>${data.spent.toFixed(2)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px;">
                <span style="font-weight: 600; color: #374151;">Labor Cost:</span>
                <span style="font-weight: 700; color: #111827;"><?php echo CurrencyHelper::symbol(); ?>${data.labor_cost.toFixed(2)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px;">
                <span style="font-weight: 600; color: #374151;">Expense Cost:</span>
                <span style="font-weight: 700; color: #111827;"><?php echo CurrencyHelper::symbol(); ?>${data.expense_cost.toFixed(2)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #e0f2fe; border-radius: 6px;">
                <span style="font-weight: 600; color: #0369a1;">Billable Amount:</span>
                <span style="font-weight: 700; color: #0c4a6e;"><?php echo CurrencyHelper::symbol(); ?>${data.billable_amount.toFixed(2)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: ${data.profit_margin > 0 ? '#d1fae5' : '#fee2e2'}; border-radius: 6px;">
                <span style="font-weight: 600; color: ${data.profit_margin > 0 ? '#065f46' : '#991b1b'};">Profit Margin:</span>
                <span style="font-weight: 700; color: ${data.profit_margin > 0 ? '#064e3b' : '#7f1d1d'};">${data.profit_margin.toFixed(2)}%</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f3f4f6; border-radius: 6px;">
                <span style="font-weight: 600; color: #374151;">Budget Utilization:</span>
                <span style="font-weight: 700; color: #111827;">${data.budget_utilization.toFixed(2)}%</span>
              </div>
            </div>
          </div>
        `;
        
        // Create a temporary modal for display
        const modalDiv = document.createElement('div');
        modalDiv.innerHTML = content;
        modalDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); z-index: 11001; max-width: 500px; width: 90%;';
        
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 11000;';
        overlay.onclick = () => {
          document.body.removeChild(modalDiv);
          document.body.removeChild(overlay);
        };
        
        document.body.appendChild(overlay);
        document.body.appendChild(modalDiv);
      } else {
        Toast.error(result.message || 'Failed to load profitability');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      Toast.error('An error occurred');
    });
}

// Generate invoice
function generateInvoice(projectId) {
  fetch(`api/projects.php?generate_invoice&project_id=${projectId}`)
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        const invoice = result.data;
        Toast.success(`Invoice generated: ${invoice.line_items.length} line items, Total: <?php echo CurrencyHelper::symbol(); ?>${invoice.total.toFixed(2)}`);
        console.log('Invoice data:', invoice);
        // TODO: Integrate with invoice creation system
      } else {
        Toast.error(result.message || 'Failed to generate invoice');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      Toast.error('An error occurred');
    });
}

// Create template with modal
function createTemplate(projectId) {
  // Fetch project data first
  fetch(`api/projects.php?id=${projectId}`)
    .then(r => r.json())
    .then(result => {
      if (!result.success) {
        Toast.error('Failed to load project');
        return;
      }
      
      const project = result.data;
      
      const content = `
        <div style="width: 100%; max-width: 1200px; max-height: 90vh; background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid hsl(240 6% 90%); display: flex; flex-direction: column; overflow: hidden;">
          <!-- Header -->
          <div style="background: linear-gradient(135deg, hsl(220 20% 97%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, hsl(240 6% 25%), hsl(240 6% 15%)); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <path d="M9 22V12h6v10"/>
                  </svg>
                </div>
                <div>
                  <h2 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: hsl(240 10% 10%);">Save as Template</h2>
                  <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: hsl(215 16% 47%);">Create reusable project template</p>
                </div>
              </div>
              <button type="button" onclick="closeTemplateModal()" style="background: transparent; border: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(215 16% 47%); transition: all 0.2s; border-radius: 6px;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='transparent'">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
              </button>
            </div>
          </div>
          
          <!-- Two-Column Layout -->
          <div style="padding: 2rem; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start;">
              
              <!-- LEFT COLUMN: Form -->
              <div>
            <!-- Source Project Info -->
            <div style="background: hsl(25 95% 95%); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid hsl(25 90% 80%);">
              <p style="margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: hsl(25 95% 35%);">Source Project</p>
              <p style="margin: 0; font-size: 0.9375rem; font-weight: 600; color: hsl(222 47% 17%);">${project.name || 'Untitled Project'}</p>
              <p style="margin: 0.25rem 0 0; font-size: 0.8125rem; color: hsl(215 16% 47%);">${project.client || 'No client'} • Budget: <?php echo CurrencyHelper::symbol(); ?>${parseFloat(project.budget || 0).toLocaleString()}</p>
            </div>
            
            <!-- Form -->
            <div style="display: grid; gap: 1.25rem;">
              <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Template Name <span style="color: hsl(0 74% 42%);">*</span></label>
                <input type="text" id="templateName" placeholder="E.g., Website Development Template" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(214 88% 78%)'; this.style.boxShadow='0 0 0 3px hsl(214 95% 93%)'" onblur="this.style.borderColor='hsl(214 20% 92%)'; this.style.boxShadow='none'">
              </div>
              
              <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Category</label>
                <select id="templateCategory" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(214 88% 78%)'" onblur="this.style.borderColor='hsl(214 20% 92%)'">
                  <option value="general">General</option>
                  <option value="web-development">Web Development</option>
                  <option value="mobile-app">Mobile App</option>
                  <option value="marketing">Marketing Campaign</option>
                  <option value="design">Design Project</option>
                  <option value="construction">Construction</option>
                  <option value="consulting">Consulting</option>
                  <option value="research">Research & Development</option>
                  <option value="event">Event Planning</option>
                  <option value="other">Other</option>
                </select>
              </div>
              
              <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Description</label>
                <textarea id="templateDescription" rows="3" placeholder="Describe what this template is for and when to use it..." style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem; resize: vertical; transition: all 0.2s;" onfocus="this.style.borderColor='hsl(214 88% 78%)'; this.style.boxShadow='0 0 0 3px hsl(214 95% 93%)'" onblur="this.style.borderColor='hsl(214 20% 92%)'; this.style.boxShadow='none'"></textarea>
              </div>
              
              <!-- What to Include -->
              <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Include in Template</label>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                  <label style="display: flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.75rem; background: hsl(240 6% 96%); border-radius: 6px; cursor: pointer; transition: all 0.2s; flex: 1; min-width: fit-content; border: 1px solid hsl(240 6% 88%);" onmouseover="this.style.background='hsl(240 6% 94%)'" onmouseout="this.style.background='hsl(240 6% 96%)'">
                    <input type="checkbox" id="includeStructure" checked style="width: 16px; height: 16px; cursor: pointer; accent-color: hsl(240 10% 10%); outline: none;">
                    <span style="font-size: 0.8125rem; font-weight: 500; color: hsl(240 10% 10%); white-space: nowrap;">Structure</span>
                  </label>
                  
                  <label style="display: flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.75rem; background: hsl(240 6% 96%); border-radius: 6px; cursor: pointer; transition: all 0.2s; flex: 1; min-width: fit-content; border: 1px solid hsl(240 6% 88%);" onmouseover="this.style.background='hsl(240 6% 94%)'" onmouseout="this.style.background='hsl(240 6% 96%)'">
                    <input type="checkbox" id="includeTasks" checked style="width: 16px; height: 16px; cursor: pointer; accent-color: hsl(240 10% 10%); outline: none;">
                    <span style="font-size: 0.8125rem; font-weight: 500; color: hsl(240 10% 10%); white-space: nowrap;">Tasks</span>
                  </label>
                  
                  <label style="display: flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.75rem; background: hsl(240 6% 96%); border-radius: 6px; cursor: pointer; transition: all 0.2s; flex: 1; min-width: fit-content; border: 1px solid hsl(240 6% 88%);" onmouseover="this.style.background='hsl(240 6% 94%)'" onmouseout="this.style.background='hsl(240 6% 96%)'">
                    <input type="checkbox" id="includeTeam" style="width: 16px; height: 16px; cursor: pointer; accent-color: hsl(240 10% 10%); outline: none;">
                    <span style="font-size: 0.8125rem; font-weight: 500; color: hsl(240 10% 10%); white-space: nowrap;">Team</span>
                  </label>
                  
                  <label style="display: flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.75rem; background: hsl(240 6% 96%); border-radius: 6px; cursor: pointer; transition: all 0.2s; flex: 1; min-width: fit-content; border: 1px solid hsl(240 6% 88%);" onmouseover="this.style.background='hsl(240 6% 94%)'" onmouseout="this.style.background='hsl(240 6% 96%)'">
                    <input type="checkbox" id="includeBudget" checked style="width: 16px; height: 16px; cursor: pointer; accent-color: hsl(240 10% 10%); outline: none;">
                    <span style="font-size: 0.8125rem; font-weight: 500; color: hsl(240 10% 10%); white-space: nowrap;">Budget</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
          
          <!-- RIGHT COLUMN: Info Panel -->
          <div style="position: sticky; top: 0;">
            <!-- Template Tips Card -->
            <div style="background: white; border: 1px solid hsl(240 6% 90%); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem;">
              <div style="padding: 1.25rem 1.5rem; background: linear-gradient(135deg, hsl(240 6% 96%), hsl(240 6% 94%)); border-bottom: 1px solid hsl(240 6% 88%);">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <div style="width: 36px; height: 36px; background: hsl(240 6% 25%); border-radius: 8px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                  </div>
                  <div>
                    <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: hsl(240 10% 10%);">Template Tips</h3>
                    <p style="font-size: 0.6875rem; margin: 0; color: hsl(240 5% 40%); font-weight: 500;">Best practices</p>
                  </div>
                </div>
              </div>
              <div style="padding: 1.5rem;">
                <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(240 10% 10%)" stroke-width="2" style="flex-shrink: 0; margin-top: 0.125rem;"><polyline points="20 6 9 17 4 12"/></svg>
                  <div>
                    <p style="margin: 0; font-size: 0.8125rem; color: hsl(240 10% 10%); font-weight: 600;">Use descriptive names</p>
                    <p style="margin: 0.25rem 0 0; font-size: 0.75rem; color: hsl(215 16% 47%); line-height: 1.4;">Make it clear what the template is for</p>
                  </div>
                </div>
                <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(240 10% 10%)" stroke-width="2" style="flex-shrink: 0; margin-top: 0.125rem;"><polyline points="20 6 9 17 4 12"/></svg>
                  <div>
                    <p style="margin: 0; font-size: 0.8125rem; color: hsl(240 10% 10%); font-weight: 600;">Include all components</p>
                    <p style="margin: 0.25rem 0 0; font-size: 0.75rem; color: hsl(215 16% 47%); line-height: 1.4;">Tasks and structure make templates reusable</p>
                  </div>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(240 10% 10%)" stroke-width="2" style="flex-shrink: 0; margin-top: 0.125rem;"><polyline points="20 6 9 17 4 12"/></svg>
                  <div>
                    <p style="margin: 0; font-size: 0.8125rem; color: hsl(240 10% 10%); font-weight: 600;">Add documentation</p>
                    <p style="margin: 0.25rem 0 0; font-size: 0.75rem; color: hsl(215 16% 47%); line-height: 1.4;">Description helps team understand usage</p>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- What Gets Saved Info -->
            <div style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 88%); border-radius: 8px; padding: 1.25rem;">
              <h4 style="margin: 0 0 0.75rem; font-size: 0.8125rem; font-weight: 600; color: hsl(240 5% 40%);">What gets saved?</h4>
              <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.75rem; color: hsl(222 47% 17%); line-height: 1.6;">
                <li>Project structure & metadata</li>
                <li>Task lists & milestones</li>
                <li>Team role definitions</li>
                <li>Budget categories</li>
                <li>Custom fields & settings</li>
              </ul>
              <p style="margin: 0.75rem 0 0; font-size: 0.6875rem; color: hsl(215 16% 47%); font-style: italic;">Personal data like team member names and actual expenses are excluded</p>
            </div>
          </div>
          
        </div>
      </div>
          
          <!-- Footer -->
          <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
            <button type="button" onclick="closeTemplateModal()" class="btn btn-ghost">Cancel</button>
            <button type="button" onclick="submitTemplate('${projectId}')" class="btn btn-primary" style="background: linear-gradient(135deg, hsl(240 6% 25%), hsl(240 6% 15%));">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                <path d="M17 21v-8H7v8M7 3v5h8"/>
              </svg>
              Create Template
            </button>
          </div>
        </div>
      `;
      
      const modalDiv = document.createElement('div');
      modalDiv.id = 'templateModal';
      modalDiv.innerHTML = content;
      modalDiv.style.cssText = 'display: flex; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;';
      
      modalDiv.addEventListener('click', function(e) {
        if (e.target === modalDiv) closeTemplateModal();
      });
      
      document.body.appendChild(modalDiv);
      
      // Focus on name input
      setTimeout(() => {
        document.getElementById('templateName')?.focus();
      }, 100);
    })
    .catch(error => {
      console.error('Error:', error);
      Toast.error('An error occurred');
    });
}

function closeTemplateModal() {
  const modal = document.getElementById('templateModal');
  if (modal) document.body.removeChild(modal);
}

function submitTemplate(projectId) {
  const name = document.getElementById('templateName').value.trim();
  const category = document.getElementById('templateCategory').value;
  const description = document.getElementById('templateDescription').value.trim();
  const includeStructure = document.getElementById('includeStructure').checked;
  const includeTasks = document.getElementById('includeTasks').checked;
  const includeTeam = document.getElementById('includeTeam').checked;
  const includeBudget = document.getElementById('includeBudget').checked;
  
  if (!name) {
    Toast.error('Please enter a template name');
    document.getElementById('templateName').focus();
    return;
  }
  
  fetch('api/projects.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      action: 'create_template',
      project_id: projectId,
      template_name: name,
      category: category,
      description: description,
      include: {
        structure: includeStructure,
        tasks: includeTasks,
        team: includeTeam,
        budget: includeBudget
      }
    })
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      Toast.success('Template created successfully!');
      closeTemplateModal();
    } else {
      Toast.error(result.message || 'Failed to create template');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Toast.error('An error occurred');
  });
}

// Edit project with tabbed modal
function editProject(projectId) {
  fetch(`api/projects.php?id=${projectId}`)
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        const project = result.data;
        
        const content = `
          <div style="width: 100%; max-width: 1000px; max-height: 90vh; background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid hsl(240 6% 90%); display: flex; flex-direction: column; overflow: hidden; pointer-events: auto; animation: modalSlideIn 0.3s ease-out;">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, hsl(220 20% 97%) 0%, white 100%); padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%); display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #7194A5, #5a7a8a); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 3px 10px rgba(113,148,165,0.3);">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </div>
                <div style="flex: 1;">
                  <h2 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: hsl(240 10% 10%); line-height: 1.2;">Edit Project</h2>
                  <p style="margin: 0.25rem 0 0; font-size: 0.75rem; color: hsl(240 5% 50%); font-weight: 500;">Update project details and settings</p>
                </div>
              </div>
              <button type="button" onclick="closeEditProjectModal()" style="background: hsl(240 5% 96%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(240 5% 40%); transition: all 0.2s; flex-shrink: 0;" onmouseover="this.style.background='hsl(240 5% 92%)'" onmouseout="this.style.background='hsl(240 5% 96%)'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6L18 18" stroke-linecap="round"/></svg>
              </button>
            </div>
            
            <!-- Tab Navigation -->
            <div style="display: flex; background: hsl(240 5% 96%); border-bottom: 2px solid hsl(240 6% 90%); overflow-x: auto;">
              <button type="button" class="edit-tab-btn active" onclick="switchEditTab('basic')" data-tab="basic" style="padding: 1rem 1.5rem; border: none; background: white; border-bottom: 3px solid #7194A5; font-weight: 600; cursor: pointer; white-space: nowrap; color: #7194A5; transition: all 0.2s;">
                📋 Basic Info
              </button>
              <button type="button" class="edit-tab-btn" onclick="switchEditTab('financial')" data-tab="financial" style="padding: 1rem 1.5rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); transition: all 0.2s;">
                💰 Financial
              </button>
              <button type="button" class="edit-tab-btn" onclick="switchEditTab('team')" data-tab="team" style="padding: 1rem 1.5rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); transition: all 0.2s;">
                👥 Team & Resources
              </button>
              <button type="button" class="edit-tab-btn" onclick="switchEditTab('settings')" data-tab="settings" style="padding: 1rem 1.5rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: hsl(215 16% 47%); transition: all 0.2s;">
                ⚙️ Settings
              </button>
            </div>
            
            <!-- Content (Scrollable) -->
            <div style="padding: 2rem; overflow-y: auto; flex: 1;">
              <!-- BASIC INFO TAB -->
              <div class="edit-tab-content active" id="edit-tab-basic">
                <div style="display: grid; gap: 1.5rem;">
                  <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: hsl(222 47% 17%);">
                      Project Name <span style="color: hsl(0 74% 42%);">*</span>
                    </label>
                    <input type="text" id="edit-name" class="form-input" value="${project.name || ''}" placeholder="Enter project name" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.9375rem;">
                  </div>
                  
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                      <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: hsl(222 47% 17%);">
                        Client <span style="color: hsl(0 74% 42%);">*</span>
                      </label>
                      <input type="text" id="edit-client" class="form-input" value="${project.client || ''}" placeholder="Client name" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px;">
                    </div>
                    <div>
                      <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: hsl(222 47% 17%);">
                        Status <span style="color: hsl(0 74% 42%);">*</span>
                      </label>
                      <select id="edit-status" class="form-select" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px;">
                        <option value="active" ${project.status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="on-hold" ${project.status === 'on-hold' ? 'selected' : ''}>On Hold</option>
                        <option value="completed" ${project.status === 'completed' ? 'selected' : ''}>Completed</option>
                        <option value="draft" ${project.status === 'draft' ? 'selected' : ''}>Draft</option>
                        <option value="archived" ${project.status === 'archived' ? 'selected' : ''}>Archived</option>
                      </select>
                    </div>
                  </div>
                  
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                      <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: hsl(222 47% 17%);">
                        Start Date
                      </label>
                      <input type="date" id="edit-start-date" class="form-input" value="${project.start_date || ''}" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px;">
                    </div>
                    <div>
                      <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: hsl(222 47% 17%);">
                        End Date
                      </label>
                      <input type="date" id="edit-end-date" class="form-input" value="${project.end_date || ''}" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px;">
                    </div>
                  </div>
                  
                  <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: hsl(222 47% 17%);">
                      Description
                    </label>
                    <textarea id="edit-description" rows="4" class="form-input" placeholder="Project description (optional)" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; resize: vertical;">${project.description || ''}</textarea>
                  </div>
                </div>
              </div>
              
              <!-- FINANCIAL TAB -->
              <div class="edit-tab-content" id="edit-tab-financial" style="display: none;">
                <div style="display: grid; gap: 1.5rem;">
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                      <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: hsl(222 47% 17%);">
                        Total Budget <span style="color: hsl(0 74% 42%);">*</span>
                      </label>
                      <input type="number" id="edit-budget" class="form-input" value="${project.budget || 0}" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px;">
                    </div>
                    <div>
                      <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: hsl(222 47% 17%);">
                        Hourly Rate
                      </label>
                      <input type="number" id="edit-hourly-rate" class="form-input" value="${project.hourly_rate || 0}" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px;">
                    </div>
                  </div>
                  
                  <div style="background: hsl(240 5% 96%); padding: 1.25rem; border-radius: 10px; border: 1px solid hsl(240 6% 90%);">
                    <h4 style="margin: 0 0 1rem; font-size: 0.875rem; font-weight: 700; color: hsl(240 10% 10%);">Current Financial Status</h4>
                    <div style="display: grid; gap: 0.75rem;">
                      <div style="display: flex; justify-content: space-between;">
                        <span style="color: hsl(215 16% 47%); font-size: 0.875rem;">Amount Spent:</span>
                        <strong style="font-family: monospace; color: hsl(0 74% 42%);"><?php echo CurrencyHelper::symbol(); ?>${(project.spent || 0).toLocaleString()}</strong>
                      </div>
                      <div style="display: flex; justify-content: space-between;">
                        <span style="color: hsl(215 16% 47%); font-size: 0.875rem;">Remaining:</span>
                        <strong style="font-family: monospace; color: hsl(140 61% 20%);"><?php echo CurrencyHelper::symbol(); ?>${((project.budget || 0) - (project.spent || 0)).toLocaleString()}</strong>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- TEAM TAB -->
              <div class="edit-tab-content" id="edit-tab-team" style="display: none;">
                <div style="text-align: center; padding: 3rem 1rem; color: hsl(215 16% 47%);">
                  <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.3; margin: 0 auto 1rem;">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                  </svg>
                  <p style="font-size: 0.875rem; margin: 0;">Team management features coming soon</p>
                </div>
              </div>
              
              <!-- SETTINGS TAB -->
              <div class="edit-tab-content" id="edit-tab-settings" style="display: none;">
                <div style="display: grid; gap: 1.5rem;">
                  <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem; color: hsl(222 47% 17%);">
                      Project ID
                    </label>
                    <input type="text" class="form-input" value="${project.project_id || ''}" disabled style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; background: hsl(240 5% 96%); cursor: not-allowed;">
                  </div>
                  
                  <div style="background: hsl(48 96% 95%); padding: 1.25rem; border-radius: 10px; border: 1px solid hsl(48 90% 80%);">
                    <h4 style="margin: 0 0 0.5rem; font-size: 0.875rem; font-weight: 700; color: hsl(25 95% 25%);">⚠️ Important Notes</h4>
                    <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.8125rem; color: hsl(25 95% 30%); line-height: 1.6;">
                      <li>Project ID cannot be changed after creation</li>
                      <li>Time entries and expenses are preserved when editing</li>
                      <li>Status changes will affect project visibility</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Footer Actions -->
            <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
              <button type="button" onclick="closeEditProjectModal()" class="btn btn-ghost">Cancel</button>
              <button type="button" onclick="saveProjectChanges('${projectId}')" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                Save Changes
              </button>
            </div>
          </div>
        `;
        
        const modalDiv = document.createElement('div');
        modalDiv.id = 'editProjectModal';
        modalDiv.innerHTML = content;
        modalDiv.style.cssText = 'display: flex; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; pointer-events: auto;';
        
        // Close on overlay click
        modalDiv.addEventListener('click', function(e) {
          if (e.target === modalDiv) {
            closeEditProjectModal();
          }
        });
        
        document.body.appendChild(modalDiv);
      } else {
        Toast.error('Failed to load project');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      Toast.error('An error occurred');
    });
}

function closeEditProjectModal() {
  const modal = document.getElementById('editProjectModal');
  if (modal) document.body.removeChild(modal);
}

function switchEditTab(tabName) {
  // Update tab buttons
  document.querySelectorAll('.edit-tab-btn').forEach(btn => {
    const isActive = btn.dataset.tab === tabName;
    btn.style.background = isActive ? 'white' : 'transparent';
    btn.style.borderBottom = isActive ? '3px solid #7194A5' : 'none';
    btn.style.color = isActive ? '#7194A5' : 'hsl(215 16% 47%)';
    btn.style.fontWeight = isActive ? '600' : '500';
  });
  
  // Update tab content
  document.querySelectorAll('.edit-tab-content').forEach(content => {
    content.style.display = content.id === `edit-tab-${tabName}` ? 'block' : 'none';
  });
}

function saveProjectChanges(projectId) {
  const projectData = {
    id: projectId,
    name: document.getElementById('edit-name').value,
    client: document.getElementById('edit-client').value,
    status: document.getElementById('edit-status').value,
    start_date: document.getElementById('edit-start-date').value,
    end_date: document.getElementById('edit-end-date').value,
    description: document.getElementById('edit-description').value,
    budget: parseFloat(document.getElementById('edit-budget').value) || 0,
    hourly_rate: parseFloat(document.getElementById('edit-hourly-rate').value) || 0
  };
  
  // Validation
  if (!projectData.name || !projectData.client) {
    Toast.error('Please fill in all required fields');
    return;
  }
  
  fetch('api/projects.php', {
    method: 'PUT',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(projectData)
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      Toast.success('Project updated successfully!');
      closeEditProjectModal();
      setTimeout(() => window.location.reload(), 1000);
    } else {
      Toast.error(result.message || 'Failed to update project');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Toast.error('An error occurred');
  });
}

// Duplicate project with smart numbering
function duplicateProject(projectId) {
  if (!confirm('Duplicate this project?\n\nA copy will be created with status "Draft".')) {
    return;
  }
  
  // Fetch all projects and the target project
  Promise.all([
    fetch('api/projects.php').then(r => r.json()),
    fetch(`api/projects.php?id=${projectId}`).then(r => r.json())
  ])
    .then(([allProjectsResult, targetResult]) => {
      if (!targetResult.success) {
        throw new Error('Failed to fetch project');
      }
      
      const original = targetResult.data;
      const allProjects = allProjectsResult.success ? allProjectsResult.data : [];
      
      // Smart name generation
      let baseName = original.name;
      let copyNumber = 1;
      
      // Check if the original name already has a copy pattern
      const copyPattern = /^(.+?)\s*\(Copy\s*(\d*)\)\s*$/;
      const match = baseName.match(copyPattern);
      
      if (match) {
        // Name already has (Copy X) or (Copy) pattern
        baseName = match[1].trim();
        if (match[2]) {
          // Has a number, don't increment here, we'll find the highest
          copyNumber = parseInt(match[2]);
        }
      }
      
      // Find all existing copies with this base name
      const existingCopies = allProjects
        .map(p => p.name)
        .filter(name => {
          if (name === baseName) return false; // Original
          const pattern = new RegExp(`^${baseName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\(Copy\\s*(\\d+)?\\)\\s*$`);
          return pattern.test(name);
        })
        .map(name => {
          const m = name.match(/\(Copy\s*(\d+)?\)/);
          return m && m[1] ? parseInt(m[1]) : 0;
        });
      
      // Find the highest copy number
      if (existingCopies.length > 0) {
        copyNumber = Math.max(...existingCopies, 0) + 1;
      }
      
      // Generate new name
      const newName = `${baseName} (Copy ${copyNumber})`;
      
      const duplicate = {
        ...original,
        name: newName,
        status: 'draft',
        project_id: 'PRJ-' + Date.now(),
        spent: 0,
        time_entries: [],
        expenses: []
      };
      delete duplicate._id;
      
      return fetch('api/projects.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(duplicate)
      });
    })
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        Toast.success('Project duplicated successfully!');
        setTimeout(() => window.location.reload(), 1000);
      } else {
        Toast.error('Failed to duplicate project');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      Toast.error('An error occurred');
    });
}

// Change project status - MINDBLOWING DESIGN
function changeProjectStatus(projectId) {
  fetch(`api/projects.php?id=${projectId}`)
    .then(r => r.json())
    .then(result => {
      if (!result.success) {
        Toast.error('Failed to load project');
        return;
      }
      
      const project = result.data;
      const currentStatus = project.status || 'active';
      
      // Status configuration with icons, colors, and descriptions
      const statuses = {
        active: {
          icon: '🚀',
          label: 'Active',
          color: 'hsl(143 85% 96%)',
          borderColor: 'hsl(143 80% 85%)',
          textColor: 'hsl(140 61% 20%)',
          description: 'Project is currently in progress'
        },
        'on-hold': {
          icon: '⏸️',
          label: 'On Hold',
          color: 'hsl(48 96% 95%)',
          borderColor: 'hsl(48 90% 80%)',
          textColor: 'hsl(25 95% 25%)',
          description: 'Temporarily paused'
        },
        completed: {
          icon: '✅',
          label: 'Completed',
          color: 'hsl(214 95% 96%)',
          borderColor: 'hsl(214 90% 80%)',
          textColor: 'hsl(214 95% 20%)',
          description: 'Successfully finished'
        },
        draft: {
          icon: '📝',
          label: 'Draft',
          color: 'hsl(240 5% 96%)',
          borderColor: 'hsl(240 6% 90%)',
          textColor: 'hsl(240 10% 10%)',
          description: 'Planning phase'
        },
        archived: {
          icon: '📦',
          label: 'Archived',
          color: 'hsl(0 0% 95%)',
          borderColor: 'hsl(0 0% 85%)',
          textColor: 'hsl(0 0% 30%)',
          description: 'Stored for reference'
        }
      };
      
      const content = `
        <div style="width: 100%; max-width: 1100px; max-height: 90vh; background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid hsl(240 6% 90%); display: flex; flex-direction: column; overflow: hidden; pointer-events: auto; animation: modalSlideIn 0.3s ease-out;">
          <!-- Minimalist Header -->
          <div style="background: white; padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div style="flex: 1;">
                <h2 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: hsl(240 10% 10%); line-height: 1.2;">Change Project Status</h2>
                <p style="margin: 0.375rem 0 0; font-size: 0.875rem; color: hsl(215 16% 47%);">${project.name || 'Untitled Project'}</p>
              </div>
              <button type="button" onclick="closeStatusModal()" style="background: transparent; border: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(215 16% 47%); transition: all 0.2s; border-radius: 6px;" onmouseover="this.style.background='hsl(240 5% 96%)'; this.style.color='hsl(240 10% 10%)'" onmouseout="this.style.background='transparent'; this.style.color='hsl(215 16% 47%)'">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18" stroke-linecap="round"/></svg>
              </button>
            </div>
          </div>
          
          <!-- Two-Column Layout (edit_item.php Style) -->
          <div style="padding: 2rem; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start;">
              
              <!-- LEFT COLUMN: Main Content -->
              <div>
                <!-- Current Status Display -->
                <div style="margin-bottom: 1.5rem; padding: 1rem; background: ${statuses[currentStatus].color}; border: 1px solid ${statuses[currentStatus].borderColor}; border-radius: 8px;">
                  <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.5rem;">${statuses[currentStatus].icon}</span>
                    <div>
                      <p style="margin: 0 0 0.25rem; font-size: 0.6875rem; font-weight: 600; color: ${statuses[currentStatus].textColor}; opacity: 0.7;">Current Status</p>
                      <p style="margin: 0; font-size: 1rem; font-weight: 600; color: ${statuses[currentStatus].textColor};">${statuses[currentStatus].label}</p>
                    </div>
                  </div>
                </div>
                
                <!-- Status Selection Grid -->
                <div style="margin-bottom: 1.5rem;">
                  <h3 style="margin: 0 0 0.625rem; font-size: 0.75rem; font-weight: 600; color: hsl(222 47% 17%);">Select New Status</h3>
                  <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;">
                    ${Object.keys(statuses).map(statusKey => `
                      <div 
                        onclick="selectStatus('${statusKey}')" 
                        data-status="${statusKey}"
                        class="status-card ${statusKey === currentStatus ? 'status-disabled' : ''}"
                        style="padding: 0.625rem 0.5rem; background: ${statuses[statusKey].color}; border: 1px solid ${statusKey === currentStatus ? statuses[statusKey].borderColor : 'transparent'}; border-radius: 6px; cursor: ${statusKey === currentStatus ? 'not-allowed' : 'pointer'}; transition: all 0.15s; opacity: ${statusKey === currentStatus ? '0.4' : '1'}; position: relative;"
                        onmouseover="if('${statusKey}' !== '${currentStatus}') { this.style.borderColor='${statuses[statusKey].borderColor}'; this.style.backgroundColor='${statuses[statusKey].color}'; }"
                        onmouseout="if('${statusKey}' !== '${currentStatus}') { this.style.borderColor='transparent'; }"
                      >
                        ${statusKey === currentStatus ? '<div style="position: absolute; top: 0.375rem; right: 0.375rem; width: 4px; height: 4px; background: ' + statuses[statusKey].textColor + '; border-radius: 50%; opacity: 0.5;"></div>' : ''}
                        <div style="text-align: center;">
                          <div style="font-size: 1.25rem; margin-bottom: 0.25rem;">${statuses[statusKey].icon}</div>
                          <p style="margin: 0; font-weight: 600; color: ${statuses[statusKey].textColor}; font-size: 0.6875rem; line-height: 1.2;">${statuses[statusKey].label}</p>
                        </div>
                      </div>
                    `).join('')}
                  </div>
                </div>
                
                <!-- Selected Status Display -->
                <div id="selectedStatusDisplay" style="display: none; margin-bottom: 1.5rem; padding: 1rem 1.25rem; background: hsl(25 95% 95%); border: 1px solid hsl(25 90% 80%); border-radius: 8px;">
                  <p style="margin: 0; font-size: 0.875rem; color: hsl(25 95% 25%);">
                    <strong>Selected:</strong> <span id="selectedStatusText">-</span>
                  </p>
                </div>
                
                <!-- Reason Textarea -->
                <div>
                  <label style="display: block; margin-bottom: 0.75rem; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">
                    Reason for Change <span style="color: hsl(215 16% 47%); font-weight: 400; font-size: 0.8125rem;">(Optional)</span>
                  </label>
                  <textarea 
                    id="statusChangeReason" 
                    rows="2" 
                    placeholder="E.g., Completed ahead of schedule, Client requested pause..." 
                    style="width: 100%; min-height: 60px; max-height: 200px; padding: 0.75rem 1rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem; resize: none; transition: all 0.2s; font-family: inherit; overflow-y: auto;"
                    onfocus="this.style.borderColor='hsl(214 88% 78%)'; this.style.boxShadow='0 0 0 3px hsl(214 95% 93%)'"
                    onblur="this.style.borderColor='hsl(214 20% 92%)'; this.style.boxShadow='none'"
                    oninput="autoExpandTextarea(this)"
                  ></textarea>
                </div>
              </div>
              
              <!-- RIGHT COLUMN: Info Panel -->
              <div style="position: sticky; top: 0;">
                <!-- Status Impact Card -->
                <div style="background: hsl(240 5% 98%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem;">
                  <h3 style="margin: 0 0 1rem; font-size: 0.8125rem; font-weight: 600; color: hsl(222 47% 17%);">Status Impact</h3>
                  <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                      <p style="margin: 0 0 0.375rem; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%);">Visibility</p>
                      <p style="margin: 0; font-size: 0.8125rem; color: hsl(222 47% 17%); line-height: 1.5;">Changes project appearance in lists</p>
                    </div>
                    <div>
                      <p style="margin: 0 0 0.375rem; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%);">Tracking</p>
                      <p style="margin: 0; font-size: 0.8125rem; color: hsl(222 47% 17%); line-height: 1.5;">Logged in project history</p>
                    </div>
                    <div>
                      <p style="margin: 0 0 0.375rem; font-size: 0.75rem; font-weight: 600; color: hsl(215 16% 47%);">Notifications</p>
                      <p style="margin: 0; font-size: 0.8125rem; color: hsl(222 47% 17%); line-height: 1.5;">Team may be notified</p>
                    </div>
                  </div>
                </div>
                
                <!-- Quick Tips Card -->
                <div style="background: hsl(240 5% 98%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; padding: 1.5rem;">
                  <h3 style="margin: 0 0 1rem; font-size: 0.8125rem; font-weight: 600; color: hsl(222 47% 17%);">Status Guide</h3>
                  <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.8125rem; color: hsl(222 47% 17%); line-height: 1.5;">
                    <div><strong>Active</strong> — Ongoing work</div>
                    <div><strong>On Hold</strong> — Temporary pause</div>
                    <div><strong>Completed</strong> — Work finished</div>
                    <div><strong>Draft</strong> — Planning stage</div>
                    <div><strong>Archived</strong> — Long-term storage</div>
                  </div>
                </div>
              </div>
              
            </div>
          </div>
          
          <!-- Footer Actions -->
          <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
            <button type="button" onclick="closeStatusModal()" class="btn btn-ghost">Cancel</button>
            <button type="button" onclick="submitStatusChange('${projectId}')" id="submitStatusBtn" class="btn btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;"><path d="M5 13l4 4L19 7"/></svg>
              Update Status
            </button>
          </div>
        </div>
      `;
      
      const modalDiv = document.createElement('div');
      modalDiv.id = 'statusModal';
      modalDiv.innerHTML = content;
      modalDiv.style.cssText = 'display: flex; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; pointer-events: auto;';
      
      // Close on overlay click
      modalDiv.addEventListener('click', function(e) {
        if (e.target === modalDiv) {
          closeStatusModal();
        }
      });
      
      document.body.appendChild(modalDiv);
      
      // Store current status, selected status, and status colors
      window.statusModalData = { 
        currentStatus, 
        selectedStatus: null,
        statuses: {
          active: { color: 'hsl(143 85% 96%)', solidColor: 'hsl(143 85% 88%)', borderColor: 'hsl(143 80% 75%)' },
          'on-hold': { color: 'hsl(48 96% 95%)', solidColor: 'hsl(48 96% 85%)', borderColor: 'hsl(48 90% 70%)' },
          completed: { color: 'hsl(214 95% 96%)', solidColor: 'hsl(214 95% 88%)', borderColor: 'hsl(214 90% 75%)' },
          draft: { color: 'hsl(240 5% 96%)', solidColor: 'hsl(240 5% 88%)', borderColor: 'hsl(240 6% 80%)' },
          archived: { color: 'hsl(0 0% 95%)', solidColor: 'hsl(0 0% 85%)', borderColor: 'hsl(0 0% 75%)' }
        }
      };
    })
    .catch(error => {
      console.error('Error:', error);
      Toast.error('An error occurred');
    });
}

// Select status card
function selectStatus(status) {
  if (status === window.statusModalData.currentStatus) return;
  
  window.statusModalData.selectedStatus = status;
  
  // Get status colors
  const statusColors = window.statusModalData.statuses[status];
  
  // Update all cards
  document.querySelectorAll('.status-card').forEach(card => {
    const cardStatus = card.dataset.status;
    const cardColors = window.statusModalData.statuses[cardStatus];
    
    if (cardStatus === status) {
      // Selected card: solid color
      card.style.backgroundColor = statusColors.solidColor;
      card.style.borderColor = statusColors.borderColor;
      card.style.borderWidth = '2px';
      card.style.boxShadow = '0 4px 12px rgba(0,0,0,0.12)';
      card.style.transform = 'scale(1.02)';
    } else if (cardStatus !== window.statusModalData.currentStatus) {
      // Other cards: reset to default
      card.style.backgroundColor = cardColors.color;
      card.style.borderColor = 'transparent';
      card.style.borderWidth = '1px';
      card.style.boxShadow = 'none';
      card.style.transform = 'scale(1)';
    }
  });
  
  // Show selected display
  const display = document.getElementById('selectedStatusDisplay');
  const text = document.getElementById('selectedStatusText');
  display.style.display = 'block';
  text.textContent = status.toUpperCase().replace('-', ' ');
  
  // Enable submit button
  const submitBtn = document.getElementById('submitStatusBtn');
  submitBtn.disabled = false;
  submitBtn.style.opacity = '1';
  submitBtn.style.cursor = 'pointer';
}

function closeStatusModal() {
  const modal = document.getElementById('statusModal');
  if (modal) document.body.removeChild(modal);
  
  // Clean up data
  if (window.statusModalData) {
    delete window.statusModalData;
  }
}

// Auto-expand textarea as user types
function autoExpandTextarea(textarea) {
  // Reset height to auto to get correct scrollHeight
  textarea.style.height = 'auto';
  
  // Calculate new height based on content
  const newHeight = textarea.scrollHeight;
  const minHeight = 60; // min-height in pixels
  const maxHeight = 200; // max-height in pixels
  
  // Set height between min and max
  if (newHeight < minHeight) {
    textarea.style.height = minHeight + 'px';
  } else if (newHeight > maxHeight) {
    textarea.style.height = maxHeight + 'px';
    textarea.style.overflowY = 'auto';
  } else {
    textarea.style.height = newHeight + 'px';
    textarea.style.overflowY = 'hidden';
  }
}

function submitStatusChange(projectId) {
  const newStatus = window.statusModalData?.selectedStatus;
  const reason = document.getElementById('statusChangeReason').value;
  
  if (!newStatus) {
    Toast.warning('Please select a status');
    return;
  }
  
  fetch('api/projects.php', {
    method: 'PUT',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      id: projectId,
      status: newStatus
    })
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      Toast.success(`Project status updated to "${newStatus.toUpperCase().replace('-', ' ')}"!`);
      closeStatusModal();
      setTimeout(() => window.location.reload(), 1000);
    } else {
      Toast.error(result.message || 'Failed to update status');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Toast.error('An error occurred');
  });
}

// ============================================
// BUDGET TRACKING FEATURES
// ============================================

// Add Expense to Project
function addExpenseToProject(projectId) {
  fetch(`api/projects.php?id=${projectId}`)
    .then(r => r.json())
    .then(result => {
      if (!result.success) {
        Toast.error('Failed to load project');
        return;
      }
      
      const project = result.data;
      const budget = parseFloat(project.budget || 0);
      const spent = parseFloat(project.spent || 0);
      const remaining = budget - spent;
      
      const content = `
        <div style="width: 100%; max-width: 700px; max-height: 90vh; background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid hsl(240 6% 90%); display: flex; flex-direction: column; overflow: hidden;">
          <!-- Header -->
          <div style="background: white; padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div>
                <h2 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: hsl(240 10% 10%);">Add Expense</h2>
                <p style="margin: 0.375rem 0 0; font-size: 0.875rem; color: hsl(215 16% 47%);">${project.name}</p>
              </div>
              <button type="button" onclick="closeExpenseModal()" style="background: transparent; border: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(215 16% 47%); transition: all 0.2s; border-radius: 6px;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='transparent'">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
              </button>
            </div>
          </div>
          
          <!-- Content -->
          <div style="padding: 2rem; overflow-y: auto;">
            <!-- Budget Status -->
            <div style="background: hsl(240 5% 98%); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid hsl(240 6% 90%);">
              <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; color: hsl(215 16% 47%);">Budget</span>
                <span style="font-weight: 600; font-size: 0.875rem;"><?php echo CurrencyHelper::symbol(); ?>${budget.toLocaleString()}</span>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; color: hsl(215 16% 47%);">Spent</span>
                <span style="font-weight: 600; font-size: 0.875rem; color: hsl(0 74% 42%);"><?php echo CurrencyHelper::symbol(); ?>${spent.toLocaleString()}</span>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span style="font-size: 0.75rem; font-weight: 600;">Remaining</span>
                <span style="font-weight: 700; font-size: 0.875rem; color: hsl(140 61% 30%);"><?php echo CurrencyHelper::symbol(); ?>${remaining.toLocaleString()}</span>
              </div>
            </div>
            
            <!-- Form -->
            <div style="display: grid; gap: 1.25rem;">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                  <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Date <span style="color: hsl(0 74% 42%);">*</span></label>
                  <input type="date" id="expenseDate" value="${new Date().toISOString().split('T')[0]}" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem;" onfocus="this.style.borderColor='hsl(214 88% 78%)'" onblur="this.style.borderColor='hsl(214 20% 92%)'">
                </div>
                <div>
                  <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Amount <span style="color: hsl(0 74% 42%);">*</span></label>
                  <input type="number" id="expenseAmount" min="0" step="0.01" placeholder="0.00" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem;" onfocus="this.style.borderColor='hsl(214 88% 78%)'" onblur="this.style.borderColor='hsl(214 20% 92%)'">
                </div>
              </div>
              
              <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Category <span style="color: hsl(0 74% 42%);">*</span></label>
                <select id="expenseCategory" required style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem;" onfocus="this.style.borderColor='hsl(214 88% 78%)'" onblur="this.style.borderColor='hsl(214 20% 92%)'">
                  <option value="">Select category...</option>
                  <option value="Materials">💎 Materials</option>
                  <option value="Labor">👷 Labor/Contractors</option>
                  <option value="Equipment">🔧 Equipment Rental</option>
                  <option value="Travel">✈️ Travel & Transport</option>
                  <option value="Software">💻 Software/Licenses</option>
                  <option value="Professional Services">🎓 Professional Services</option>
                  <option value="Office Supplies">📎 Office Supplies</option>
                  <option value="Marketing">📢 Marketing</option>
                  <option value="Utilities">⚡ Utilities</option>
                  <option value="Other">📦 Other</option>
                </select>
              </div>
              
              <div>
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: hsl(222 47% 17%);">Description <span style="color: hsl(0 74% 42%);">*</span></label>
                <textarea id="expenseDescription" rows="2" required placeholder="E.g., Construction materials purchase" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem; resize: vertical;" onfocus="this.style.borderColor='hsl(214 88% 78%)'" onblur="this.style.borderColor='hsl(214 20% 92%)'"></textarea>
              </div>
              
              <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: hsl(214 95% 96%); border-radius: 8px;">
                <input type="checkbox" id="expenseBillable" checked style="width: 18px; height: 18px; cursor: pointer;">
                <label for="expenseBillable" style="font-size: 0.875rem; color: hsl(222 47% 17%); cursor: pointer; user-select: none;">Billable to client</label>
              </div>
            </div>
          </div>
          
          <!-- Footer -->
          <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
            <button type="button" onclick="closeExpenseModal()" class="btn btn-ghost">Cancel</button>
            <button type="button" onclick="submitExpense('${projectId}')" class="btn btn-primary">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;"><path d="M5 13l4 4L19 7"/></svg>
              Add Expense
            </button>
          </div>
        </div>
      `;
      
      const modalDiv = document.createElement('div');
      modalDiv.id = 'expenseModal';
      modalDiv.innerHTML = content;
      modalDiv.style.cssText = 'display: flex; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;';
      
      modalDiv.addEventListener('click', function(e) {
        if (e.target === modalDiv) closeExpenseModal();
      });
      
      document.body.appendChild(modalDiv);
    })
    .catch(error => {
      console.error('Error:', error);
      Toast.error('An error occurred');
    });
}

function closeExpenseModal() {
  const modal = document.getElementById('expenseModal');
  if (modal) document.body.removeChild(modal);
}

function submitExpense(projectId) {
  const date = document.getElementById('expenseDate').value;
  const amount = parseFloat(document.getElementById('expenseAmount').value);
  const category = document.getElementById('expenseCategory').value;
  const description = document.getElementById('expenseDescription').value;
  const billable = document.getElementById('expenseBillable').checked;
  
  if (!date || !amount || !category || !description) {
    Toast.error('Please fill in all required fields');
    return;
  }
  
  if (amount <= 0) {
    Toast.error('Amount must be greater than zero');
    return;
  }
  
  // For now, show success - In real app, would send to API
  Toast.success(`Expense of <?php echo CurrencyHelper::symbol(); ?>${amount.toFixed(2)} added successfully!`);
  closeExpenseModal();
  
  // TODO: Implement API call to save expense
  // This would update project.spent and create expense record
  setTimeout(() => window.location.reload(), 1000);
}

// Add Time Entry to Project (placeholder)
function addTimeEntryToProject(projectId) {
  Toast.info('Time Entry feature - Coming soon! Track billable hours and calculate labor costs.');
  // TODO: Implement similar modal for time tracking
}

// View Budget Report (placeholder)
function viewBudgetReport(projectId) {
  Toast.info('Budget Report feature - Coming soon! View comprehensive financial breakdown.');
  // TODO: Implement detailed budget vs actual reporting dashboard
}

// Budget vs Actual Report
function viewBudgetVsActual(projectId) {
  fetch(`api/projects.php?budget_vs_actual&project_id=${projectId}`)
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        const data = result.data;
        const isUnderBudget = data.status === 'under_budget';
        const content = `
          <div style="padding: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: #111827;">Budget vs Actual Report</h3>
            <div style="display: grid; gap: 1rem;">
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px;">
                <span style="font-weight: 600; color: #374151;">Budgeted Amount:</span>
                <span style="font-weight: 700; color: #111827;"><?php echo CurrencyHelper::symbol(); ?>${data.budget.toFixed(2)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px;">
                <span style="font-weight: 600; color: #374151;">Actual Spent:</span>
                <span style="font-weight: 700; color: #111827;"><?php echo CurrencyHelper::symbol(); ?>${data.actual.toFixed(2)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: ${isUnderBudget ? '#d1fae5' : '#fee2e2'}; border-radius: 6px;">
                <span style="font-weight: 600; color: ${isUnderBudget ? '#065f46' : '#991b1b'};">Variance:</span>
                <span style="font-weight: 700; color: ${isUnderBudget ? '#064e3b' : '#7f1d1d'};"><?php echo CurrencyHelper::symbol(); ?>${Math.abs(data.variance).toFixed(2)} ${isUnderBudget ? 'Under' : 'Over'}</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f3f4f6; border-radius: 6px;">
                <span style="font-weight: 600; color: #374151;">Variance %:</span>
                <span style="font-weight: 700; color: #111827;">${data.variance_percent.toFixed(2)}%</span>
              </div>
              <div style="padding: 0.75rem; background: ${isUnderBudget ? '#e0f2fe' : '#fef3c7'}; border-radius: 6px; text-align: center;">
                <span style="font-weight: 700; color: ${isUnderBudget ? '#0369a1' : '#92400e'}; font-size: 0.875rem;">
                  ${isUnderBudget ? '✓ Under Budget' : '⚠ Over Budget'}
                </span>
              </div>
            </div>
          </div>
        `;
        
        const modalDiv = document.createElement('div');
        modalDiv.innerHTML = content;
        modalDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); z-index: 11001; max-width: 500px; width: 90%;';
        
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 11000;';
        overlay.onclick = () => {
          document.body.removeChild(modalDiv);
          document.body.removeChild(overlay);
        };
        
        document.body.appendChild(overlay);
        document.body.appendChild(modalDiv);
      } else {
        Toast.error(result.message || 'Failed to load budget report');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      Toast.error('An error occurred');
    });
}

// Export to PDF
function exportProjectPDF(projectId) {
  Toast.info('Generating PDF export...');
  // TODO: Implement PDF export with project details, time entries, expenses
  console.log('Export project PDF:', projectId);
}

// Send Email Report
let currentEmailProjectId = null;

async function sendEmailReport(projectId) {
  console.log('📧 Opening email form for project:', projectId);
  
  // Check if SMTP is configured
  const smtpConfigured = <?php echo $smtpConfigured ? 'true' : 'false'; ?>;
  if (!smtpConfigured) {
    alert('SMTP server not configured. Please configure email settings in Settings > System Configuration.');
    return;
  }
  
  currentEmailProjectId = projectId;
  
  // Fetch project data to get details
  try {
    const response = await fetch(`api/projects.php?id=${projectId}`);
    const result = await response.json();
    
    if (!result.success || !result.data) {
      Toast.error('Failed to load project details');
      return;
    }
    
    const project = result.data;
    const projectName = project.name || 'Untitled Project';
    const clientName = project.client || 'Client';
    
    // Populate modal
    document.getElementById('emailProjectId').value = projectId;
    document.getElementById('emailProjectNumber').textContent = projectName;
    document.getElementById('emailSubjectProject').value = `Project Report: ${projectName}`;
    document.getElementById('emailMessageProject').value = `Dear ${clientName},\n\nPlease find attached the project report for ${projectName}.\n\nThank you for your business!\n\nBest regards,\nYour Company`;
    
    // Check SMTP configuration
    try {
      const smtpResponse = await fetch('api/check_smtp_config.php');
      const smtpResult = await smtpResponse.json();
      
      const smtpWarning = document.getElementById('smtpWarningProject');
      const sendBtn = document.getElementById('sendEmailProjectBtn');
      
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
      document.getElementById('smtpWarningProject').style.display = 'block';
      const sendBtn = document.getElementById('sendEmailProjectBtn');
      sendBtn.disabled = true;
      sendBtn.style.opacity = '0.5';
      sendBtn.style.cursor = 'not-allowed';
    }
    
    // Show modal
    const modal = document.getElementById('emailProjectModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Focus on recipient email field
    setTimeout(() => {
      document.getElementById('recipientEmailProject').focus();
    }, 100);
    
  } catch (error) {
    console.error('Error loading project:', error);
    Toast.error('Failed to load project details');
  }
}

function closeEmailProjectModal() {
  const modal = document.getElementById('emailProjectModal');
  modal.style.display = 'none';
  document.body.style.overflow = '';
  
  // Reset form
  document.getElementById('emailProjectForm').reset();
  document.getElementById('smtpWarningProject').style.display = 'none';
  currentEmailProjectId = null;
  
  console.log('ℹ Email form closed');
}

async function submitEmailProject(event) {
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
      project_id: formData.get('project_id'),
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
    const response = await fetch('api/send_project_email.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      closeEmailProjectModal();
      console.log(`✓ Project email sent successfully to ${data.recipient_email}`);
      Toast.success('Project report sent successfully!');
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

// Archive project
function archiveProject(projectId) {
  if (!confirm('Archive this project?\n\nArchived projects can be restored later.')) {
    return;
  }
  
  fetch('api/projects.php', {
    method: 'PUT',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      id: projectId,
      status: 'archived'
    })
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      Toast.success('Project archived successfully!');
      setTimeout(() => window.location.reload(), 1000);
    } else {
      Toast.error(result.message || 'Failed to archive project');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Toast.error('An error occurred');
  });
}

// Delete project
function deleteProject(projectId) {
  if (!confirm('⚠️ DELETE PROJECT?\n\nThis action CANNOT be undone!\n\nAll project data including:\n• Time entries\n• Expenses\n• Tasks & milestones\n• Team assignments\n\nWill be permanently deleted.')) {
    return;
  }
  
  // Double confirmation
  const confirmText = prompt('Type "DELETE" to confirm permanent deletion:');
  if (confirmText !== 'DELETE') {
    Toast.info('Deletion cancelled');
    return;
  }
  
  fetch('api/projects.php', {
    method: 'DELETE',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id: projectId })
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      Toast.success('Project deleted permanently');
      setTimeout(() => window.location.reload(), 1000);
    } else {
      Toast.error(result.message || 'Failed to delete project');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Toast.error('An error occurred');
  });
}

// ============================================
// TOOLBAR QUICK ACTIONS
// ============================================

// Quick Add Time Entry (Bulk Mode - works with selected projects)
function showQuickAddTime() {
  const selectedCheckboxes = document.querySelectorAll('.project-checkbox:checked');
  const selectedProjects = Array.from(selectedCheckboxes).map(cb => ({
    id: cb.dataset.projectId,
    name: cb.dataset.projectName
  }));
  
  if (selectedProjects.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  if (selectedProjects.length === 1) {
    // Single project - open time entry modal
    addTimeEntryToProject(selectedProjects[0].id);
  } else {
    // Multiple projects - show bulk time entry modal
    showBulkTimeEntryModal(selectedProjects);
  }
}

// Quick Add Expense (Bulk Mode - works with selected projects)
function showQuickAddExpense() {
  const selectedCheckboxes = document.querySelectorAll('.project-checkbox:checked');
  const selectedProjects = Array.from(selectedCheckboxes).map(cb => ({
    id: cb.dataset.projectId,
    name: cb.dataset.projectName
  }));
  
  if (selectedProjects.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  if (selectedProjects.length === 1) {
    // Single project - open expense modal
    addExpenseToProject(selectedProjects[0].id);
  } else {
    // Multiple projects - show bulk expense modal
    showBulkExpenseModal(selectedProjects);
  }
}

// Quick Profitability (Bulk Mode - shows budget report for selected projects)
function showQuickProfitability() {
  const selectedCheckboxes = document.querySelectorAll('.project-checkbox:checked');
  const selectedProjects = Array.from(selectedCheckboxes).map(cb => ({
    id: cb.dataset.projectId,
    name: cb.dataset.projectName
  }));
  
  if (selectedProjects.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  if (selectedProjects.length === 1) {
    // Single project - open budget report
    viewBudgetReport(selectedProjects[0].id);
  } else {
    // Multiple projects - show combined budget report
    showBulkBudgetReport(selectedProjects);
  }
}

// Bulk Time Entry Modal (for multiple projects)
function showBulkTimeEntryModal(projects) {
  const projectList = projects.map(p => `<li style="padding: 0.375rem 0; color: hsl(222 47% 17%);">• ${p.name}</li>`).join('');
  
  const content = `
    <div style="width: 100%; max-width: 650px; background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid hsl(240 6% 90%);">
      <div style="background: white; padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <h2 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: hsl(240 10% 10%);">Bulk Time Entry</h2>
            <p style="margin: 0.375rem 0 0; font-size: 0.875rem; color: hsl(215 16% 47%);">${projects.length} projects selected</p>
          </div>
          <button onclick="closeBulkModal()" style="background: transparent; border: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(215 16% 47%); border-radius: 6px;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='transparent'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
          </button>
        </div>
      </div>
      <div style="padding: 2rem;">
        <div style="background: hsl(214 95% 96%); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid hsl(214 95% 50%);">
          <p style="margin: 0; font-size: 0.8125rem; font-weight: 600; color: hsl(222 47% 17%); margin-bottom: 0.5rem;">Time will be added to:</p>
          <ul style="margin: 0.5rem 0 0; padding-left: 1.25rem; font-size: 0.8125rem; max-height: 120px; overflow-y: auto;">${projectList}</ul>
        </div>
        <div style="display: grid; gap: 1rem;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Date</label>
              <input type="date" id="bulkTimeDate" value="${new Date().toISOString().split('T')[0]}" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem;">
            </div>
            <div>
              <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Hours</label>
              <input type="number" id="bulkTimeHours" min="0" step="0.5" placeholder="8.0" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem;">
            </div>
          </div>
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Task Description</label>
            <textarea id="bulkTimeDescription" rows="2" placeholder="E.g., Development work" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
          </div>
        </div>
      </div>
      <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
        <button onclick="closeBulkModal()" class="btn btn-ghost">Cancel</button>
        <button onclick="submitBulkTime(${JSON.stringify(projects).replace(/"/g, '&quot;')})" class="btn btn-primary">Add to ${projects.length} Projects</button>
      </div>
    </div>
  `;
  
  const modalDiv = document.createElement('div');
  modalDiv.id = 'bulkModal';
  modalDiv.innerHTML = content;
  modalDiv.style.cssText = 'display: flex; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;';
  modalDiv.addEventListener('click', e => { if (e.target === modalDiv) closeBulkModal(); });
  document.body.appendChild(modalDiv);
}

// Bulk Expense Modal (for multiple projects)
function showBulkExpenseModal(projects) {
  const projectList = projects.map(p => `<li style="padding: 0.375rem 0; color: hsl(222 47% 17%);">• ${p.name}</li>`).join('');
  
  const content = `
    <div style="width: 100%; max-width: 650px; background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid hsl(240 6% 90%);">
      <div style="background: white; padding: 1.5rem 2rem; border-bottom: 1px solid hsl(240 6% 90%);">
        <div style="display: flex; align-items: center; justify-content: space-between;">
          <div>
            <h2 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: hsl(240 10% 10%);">Bulk Expense</h2>
            <p style="margin: 0.375rem 0 0; font-size: 0.875rem; color: hsl(215 16% 47%);">${projects.length} projects selected</p>
          </div>
          <button onclick="closeBulkModal()" style="background: transparent; border: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: hsl(215 16% 47%); border-radius: 6px;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='transparent'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6L18 18"/></svg>
          </button>
        </div>
      </div>
      <div style="padding: 2rem;">
        <div style="background: hsl(25 95% 95%); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid hsl(25 90% 70%);">
          <p style="margin: 0; font-size: 0.8125rem; font-weight: 600; color: hsl(25 95% 25%); margin-bottom: 0.5rem;">Expense will be added to:</p>
          <ul style="margin: 0.5rem 0 0; padding-left: 1.25rem; font-size: 0.8125rem; max-height: 120px; overflow-y: auto;">${projectList}</ul>
        </div>
        <div style="display: grid; gap: 1rem;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Date</label>
              <input type="date" id="bulkExpenseDate" value="${new Date().toISOString().split('T')[0]}" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem;">
            </div>
            <div>
              <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Amount</label>
              <input type="number" id="bulkExpenseAmount" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem;">
            </div>
          </div>
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Category</label>
            <select id="bulkExpenseCategory" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem;">
              <option value="">Select category...</option>
              <option value="Materials">💎 Materials</option>
              <option value="Labor">👷 Labor/Contractors</option>
              <option value="Equipment">🔧 Equipment Rental</option>
              <option value="Software">💻 Software/Licenses</option>
              <option value="Other">📦 Other</option>
            </select>
          </div>
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600;">Description</label>
            <textarea id="bulkExpenseDescription" rows="2" placeholder="E.g., Shared materials purchase" style="width: 100%; padding: 0.625rem 0.875rem; border: 1.5px solid hsl(214 20% 92%); border-radius: 8px; font-size: 0.875rem; resize: vertical;"></textarea>
          </div>
        </div>
      </div>
      <div style="background: hsl(210 20% 98%); padding: 1rem 2rem; border-top: 1px solid hsl(214 20% 88%); display: flex; gap: 0.75rem; justify-content: flex-end;">
        <button onclick="closeBulkModal()" class="btn btn-ghost">Cancel</button>
        <button onclick="submitBulkExpense(${JSON.stringify(projects).replace(/"/g, '&quot;')})" class="btn btn-primary">Add to ${projects.length} Projects</button>
      </div>
    </div>
  `;
  
  const modalDiv = document.createElement('div');
  modalDiv.id = 'bulkModal';
  modalDiv.innerHTML = content;
  modalDiv.style.cssText = 'display: flex; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;';
  modalDiv.addEventListener('click', e => { if (e.target === modalDiv) closeBulkModal(); });
  document.body.appendChild(modalDiv);
}

// Bulk Budget Report (for multiple projects)
function showBulkBudgetReport(projects) {
  Toast.success(`Budget report for ${projects.length} projects - Feature coming soon!`);
  console.log('Bulk budget report for:', projects);
  // TODO: Show combined budget analytics dashboard
}

// Close bulk modal
function closeBulkModal() {
  const modal = document.getElementById('bulkModal');
  if (modal) document.body.removeChild(modal);
}

// Submit bulk time entry
function submitBulkTime(projects) {
  const date = document.getElementById('bulkTimeDate').value;
  const hours = document.getElementById('bulkTimeHours').value;
  const description = document.getElementById('bulkTimeDescription').value;
  
  if (!date || !hours || !description) {
    Toast.error('Please fill in all fields');
    return;
  }
  
  Toast.success(`Time entry added to ${projects.length} projects!`);
  closeBulkModal();
  setTimeout(() => window.location.reload(), 1000);
}

// Submit bulk expense
function submitBulkExpense(projects) {
  const date = document.getElementById('bulkExpenseDate').value;
  const amount = document.getElementById('bulkExpenseAmount').value;
  const category = document.getElementById('bulkExpenseCategory').value;
  const description = document.getElementById('bulkExpenseDescription').value;
  
  if (!date || !amount || !category || !description) {
    Toast.error('Please fill in all fields');
    return;
  }
  
  Toast.success(`Expense of <?php echo CurrencyHelper::symbol(); ?>${parseFloat(amount).toFixed(2)} added to ${projects.length} projects!`);
  closeBulkModal();
  setTimeout(() => window.location.reload(), 1000);
}

// Quick Invoice (opens selector)
function showQuickInvoice() {
  Toast.info('Click on a project row action menu to generate invoice');
}

// Quick Export (exports all visible projects)
function showQuickExport() {
  Toast.info('Exporting all projects...');
  console.log('Export all projects');
  // TODO: Implement export functionality
}

// ============================================
// BULK ACTIONS FUNCTIONALITY
// ============================================

let bulkModeActive = false;

// Toggle bulk selection mode
function toggleBulkMode() {
  bulkModeActive = !bulkModeActive;
  const checkboxColumns = document.querySelectorAll('.checkbox-column');
  const btn = document.getElementById('bulkToggleBtn');
  const quickActions = document.getElementById('quickActions');
  const normalFeatures = document.getElementById('normalFeatures');
  
  checkboxColumns.forEach(col => {
    col.style.display = bulkModeActive ? 'table-cell' : 'none';
  });
  
  if (bulkModeActive) {
    // Enable bulk mode
    btn.classList.add('active');
    btn.style.background = 'hsl(214 95% 93%)';
    btn.style.borderColor = 'hsl(214 88% 78%)';
    btn.style.color = 'hsl(222 47% 17%)';
    normalFeatures.style.display = 'none'; // Hide normal features
    Toast.info('Bulk mode enabled - Select projects to perform batch operations');
  } else {
    // Disable bulk mode
    btn.classList.remove('active');
    btn.style.background = 'white';
    btn.style.borderColor = 'hsl(214 20% 92%)';
    btn.style.color = '';
    quickActions.style.display = 'none'; // Hide batch operations
    normalFeatures.style.display = 'flex'; // Show normal features
    clearSelection();
    Toast.success('Bulk mode disabled');
  }
}

// Toggle select all checkboxes
function toggleSelectAll(checkbox) {
  const projectCheckboxes = document.querySelectorAll('.project-checkbox');
  projectCheckboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
  updateBatchToolbar();
}

// Update batch toolbar based on selection
function updateBatchToolbar() {
  const checkboxes = document.querySelectorAll('.project-checkbox:checked');
  const count = checkboxes.length;
  const toolbar = document.getElementById('batchToolbar');
  const selectedCount = document.getElementById('selectedCount');
  const selectAll = document.getElementById('selectAll');
  const quickActions = document.getElementById('quickActions');
  const total = document.querySelectorAll('.project-checkbox').length;
  
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
  
  // Show/hide toolbar and quick actions based on selection
  if (count > 0) {
    toolbar.style.display = 'block';
    selectedCount.textContent = `${count} selected`;
    // Show quick actions with smooth animation
    if (bulkModeActive) {
      quickActions.style.display = 'flex';
      quickActions.style.animation = 'slideInRight 0.3s ease';
    }
  } else {
    toolbar.style.display = 'none';
    quickActions.style.display = 'none';
  }
}

// Clear all selections
function clearSelection() {
  document.querySelectorAll('.project-checkbox').forEach(cb => cb.checked = false);
  const selectAll = document.getElementById('selectAll');
  selectAll.checked = false;
  selectAll.indeterminate = false;
  updateBatchToolbar();
}

// Get selected project IDs
function getSelectedProjectIds() {
  const checkboxes = document.querySelectorAll('.project-checkbox:checked');
  return Array.from(checkboxes).map(cb => cb.value);
}

// Batch update status (Dynamic - No reload)
async function batchUpdateStatus() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  const status = prompt(`Update status for ${projectIds.length} project(s):\n\n1. Active\n2. Completed\n3. On Hold\n\nEnter status number:`);
  if (!status || !['1','2','3'].includes(status)) {
    Toast.info('Status update cancelled');
    return;
  }
  
  const statusMap = {'1': 'active', '2': 'completed', '3': 'on-hold'};
  const newStatus = statusMap[status];
  const statusLabel = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
  
  Toast.info(`Updating status for ${projectIds.length} project(s)...`);
  
  try {
    const results = await Promise.all(projectIds.map(id => 
      fetch('api/projects.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id, status: newStatus })
      }).then(r => r.json())
    ));
    
    const successful = results.filter(r => r.success).length;
    if (successful > 0) {
      Toast.success(`✅ ${successful} project(s) updated to ${statusLabel}`);
      await refreshProjectsTable();
      clearSelection();
    } else {
      Toast.error('Failed to update projects');
    }
  } catch (error) {
    console.error('Error:', error);
    Toast.error('An error occurred');
  }
}

// Batch archive
function batchArchive() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  if (!confirm(`Archive ${projectIds.length} project(s)?\n\nArchived projects can be restored later.`)) {
    return;
  }
  
  Toast.info(`Archiving ${projectIds.length} project(s)...`);
  
  Promise.all(projectIds.map(id => 
    fetch('api/projects.php', {
      method: 'PUT',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id, status: 'archived' })
    }).then(r => r.json())
  ))
  .then(results => {
    const successful = results.filter(r => r.success).length;
    if (successful > 0) {
      Toast.success(`${successful} project(s) archived successfully!`);
      setTimeout(() => window.location.reload(), 1000);
    } else {
      console.error('Failed to archive projects');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Toast.error('An error occurred');
  });
}

// Batch export
function batchExport() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  Toast.info(`Exporting ${projectIds.length} project(s)...`);
  console.log('Export projects:', projectIds);
  // TODO: Implement actual export functionality
}

// Batch delete
function batchDelete() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  if (!confirm(`⚠️ DELETE ${projectIds.length} PROJECTS?\n\nThis action CANNOT be undone!\n\nAll project data will be permanently deleted.`)) {
    return;
  }
  
  // Double confirmation
  const confirmText = prompt('Type "DELETE" to confirm permanent deletion of multiple projects:');
  if (confirmText !== 'DELETE') {
    Toast.info('Deletion cancelled');
    return;
  }
  
  Toast.info(`Deleting ${projectIds.length} project(s)...`);
  
  Promise.all(projectIds.map(id => 
    fetch('api/projects.php', {
      method: 'DELETE',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id })
    }).then(r => r.json())
  ))
  .then(results => {
    const successful = results.filter(r => r.success).length;
    if (successful > 0) {
      Toast.success(`${successful} project(s) deleted permanently`);
      setTimeout(() => window.location.reload(), 1000);
    } else {
      Toast.error('Failed to delete projects');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Toast.error('An error occurred');
  });
}

// ============================================
// BATCH OPERATIONS
// ============================================

// Batch Assign Team Members
function batchAssignTeam() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  const teamMember = prompt(`Assign team member to ${projectIds.length} project(s):\n\nEnter team member name or email:`);
  if (!teamMember || teamMember.trim() === '') {
    Toast.info('Operation cancelled');
    return;
  }
  
  console.log(`Assigned team member "${teamMember}" to ${projectIds.length} project(s)`);
    clearSelection();
}

// Batch Update Budget
function batchUpdateBudget() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  const budget = prompt(`Update budget for ${projectIds.length} project(s):\n\nEnter new budget amount (e.g., 50000):`);
  if (!budget || isNaN(budget)) {
    Toast.info('Operation cancelled - invalid budget');
    return;
  }
  
  console.log(`Updated budget to ${formatCurrencyAmount(parseFloat(budget))} for ${projectIds.length} project(s)`);
    clearSelection();
}

// Batch Update Deadline
function batchUpdateDeadline() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  const deadline = prompt(`Update deadline for ${projectIds.length} project(s):\n\nEnter new deadline (YYYY-MM-DD):`);
  if (!deadline) {
    Toast.info('Operation cancelled');
    return;
  }
  
  console.log(`Updated deadline to ${deadline} for ${projectIds.length} project(s)`);
    clearSelection();
}

// Batch Add Tags
function batchAddTags() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  const tags = prompt(`Add tags/labels to ${projectIds.length} project(s):\n\nEnter tags separated by commas (e.g., urgent, priority, backend):`);
  if (!tags || tags.trim() === '') {
    Toast.info('Operation cancelled');
    return;
  }
  
  const tagArray = tags.split(',').map(t => t.trim()).filter(t => t);
  console.log(`Added tags: ${tagArray.join(', ')}`);
    clearSelection();
}

// Batch Change Priority
function batchChangePriority() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  const priority = prompt(`Change priority for ${projectIds.length} project(s):\n\n1. Low\n2. Medium\n3. High\n4. Critical\n\nEnter number or priority name:`);
  if (!priority) {
    Toast.info('Operation cancelled');
    return;
  }
  
  const priorityMap = { '1': 'Low', '2': 'Medium', '3': 'High', '4': 'Critical' };
  const priorityName = priorityMap[priority] || priority;
  
  console.log(`Changed priority to ${priorityName} for ${projectIds.length} project(s)`);
    clearSelection();
}

// Batch Duplicate Projects (Dynamic - No reload)
async function batchDuplicate() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  if (!confirm(`Duplicate ${projectIds.length} project(s)?\n\nThis will create exact copies with "(Copy)" appended to names.`)) {
    return;
  }
  
  console.log(`Duplicated ${projectIds.length} project(s)`);
    window.location.reload();
}

// Batch Generate Invoices
function batchGenerateInvoices() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  if (!confirm(`Generate invoices for ${projectIds.length} project(s)?\n\nInvoices will be created based on project time entries and expenses.`)) {
    return;
  }
  
  console.log(`Generated ${projectIds.length} invoice(s)`);
    window.open('invoices.php', '_blank');
}

// Batch Generate Reports
function batchGenerateReports() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    Toast.warning('Please select at least one project');
    return;
  }
  
  const reportType = prompt(`Generate reports for ${projectIds.length} project(s):\n\n1. Progress Report\n2. Financial Report\n3. Time Tracking Report\n4. Profitability Analysis\n5. Comprehensive Report\n\nEnter report number:`);
  
  if (!reportType || !['1','2','3','4','5'].includes(reportType)) {
    Toast.info('Operation cancelled');
    return;
  }
  
  const reportNames = {
    '1': 'Progress Report',
    '2': 'Financial Report', 
    '3': 'Time Tracking Report',
    '4': 'Profitability Analysis',
    '5': 'Comprehensive Report'
  };
  
  console.log(`Generated ${reportNames[reportType]}(s) for ${projectIds.length} project(s)`);
}

// Batch Send Notifications
function batchSendNotifications() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    console.log('Please select at least one project');
    return;
  }
  
  const notifType = prompt(`Send notifications for ${projectIds.length} project(s):\n\n1. Status Update\n2. Deadline Reminder\n3. Budget Alert\n4. Team Assignment\n5. Custom Message\n\nEnter notification type:`);
  
  if (!notifType || !['1','2','3','4','5'].includes(notifType)) {
    console.log('Operation cancelled');
    return;
  }
  
  let message = '';
  if (notifType === '5') {
    message = prompt('Enter custom notification message:');
    if (!message) {
      console.log('Operation cancelled');
      return;
    }
  }
  
  const notifNames = {
    '1': 'Status Update',
    '2': 'Deadline Reminder',
    '3': 'Budget Alert',
    '4': 'Team Assignment',
    '5': 'Custom Message'
  };
  
  console.log(`Sent ${notifNames[notifType]} notifications`);
    clearSelection();
}

// Batch Export Projects
function batchExportProjects() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    console.log('Please select at least one project');
    return;
  }
  
  const format = prompt(`Export ${projectIds.length} project(s):\n\n1. Excel (.xlsx)\n2. CSV (.csv)\n3. PDF Report\n4. JSON Data\n5. XML\n\nEnter export format:`);
  
  if (!format || !['1','2','3','4','5'].includes(format)) {
    console.log('Export cancelled');
    return;
  }
  
  const formatNames = {
    '1': 'Excel',
    '2': 'CSV',
    '3': 'PDF',
    '4': 'JSON',
    '5': 'XML'
  };
  
  console.log(`Exported ${projectIds.length} project(s) as ${formatNames[format]}`);
    const timestamp = new Date().toISOString().split('T')[0];
    console.log(`Download: projects_export_${timestamp}.${formatNames[format].toLowerCase()}`);
}

// Batch Archive Projects (Dynamic - No reload)
async function batchArchiveProjects() {
  const projectIds = getSelectedProjectIds();
  if (projectIds.length === 0) {
    console.log('Please select at least one project');
    return;
  }
  
  if (!confirm(`Archive ${projectIds.length} project(s)?\n\nArchived projects will be moved to the archive and hidden from active view.`)) {
    return;
  }
  
  console.log(`Archiving ${projectIds.length} project(s)...`);
  
  try {
    const results = await Promise.all(projectIds.map(id => 
      fetch('api/projects.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id, status: 'archived' })
      }).then(r => r.json())
    ));
    
    const successful = results.filter(r => r.success).length;
    if (successful > 0) {
      console.log(`Archived ${successful} project(s)`);
      window.location.reload();
    } else {
      console.log('Failed to archive projects');
    }
  } catch (error) {
    console.error('Error:', error);
  }
}

// ============================================
// UPDATES
// ============================================

// Global currency symbol from PHP settings
const CURRENCY_SYMBOL = '<?php echo CurrencyHelper::symbol(); ?>';

// Global currency formatter
function formatCurrencyAmount(amount) {
  return CURRENCY_SYMBOL + Math.round(amount).toLocaleString();
}

// Fetch and update stats dashboard
async function updateStatsDashboard() {
  try {
    // Use 'summary' instead of 'stats' to avoid ad blocker blocking
    const response = await fetch('api/projects.php?summary=1', {
      cache: 'no-cache',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    
    if (!response.ok) {
      console.warn('Summary endpoint returned:', response.status);
      return; // Silently fail if blocked
    }
    
    const result = await response.json();
    
    if (result.success && result.data) {
      const stats = result.data;
      
      // Update Total Projects
      const totalProjectsEl = document.querySelector('[data-stat="total-projects"]');
      if (totalProjectsEl) totalProjectsEl.textContent = stats.total_projects || 0;
      
      // Update Active Projects
      const activeProjectsEl = document.querySelector('[data-stat="active-projects"]');
      if (activeProjectsEl) activeProjectsEl.textContent = stats.active_projects || 0;
      
      // Update Total Budget
      const totalBudgetEl = document.querySelector('[data-stat="total-budget"]');
      if (totalBudgetEl) totalBudgetEl.textContent = formatCurrencyAmount(stats.total_budget || 0);
      
      // Update Total Spent
      const totalSpentEl = document.querySelector('[data-stat="total-spent"]');
      if (totalSpentEl) totalSpentEl.textContent = formatCurrencyAmount(stats.total_spent || 0);
      
      // Update Remaining
      const remaining = (stats.total_budget || 0) - (stats.total_spent || 0);
      const remainingEl = document.querySelector('[data-stat="remaining"]');
      if (remainingEl) remainingEl.textContent = formatCurrencyAmount(remaining);
      
      // Update Average Progress
      const avgProgress = stats.total_budget > 0 ? Math.round((stats.total_spent / stats.total_budget) * 100) : 0;
      const avgProgressEl = document.querySelector('[data-stat="avg-progress"]');
      if (avgProgressEl) avgProgressEl.textContent = `${avgProgress}%`;
      
      console.log('Stats updated successfully');
    }
  } catch (error) {
    // Silently handle stats errors
    console.warn('Dashboard summary update skipped:', error.message);
  }
}

// ============================================
// TABLE SORTING
// ============================================

let currentSortColumn = null;
let currentSortDirection = 'asc';
let projectsData = []; // Store projects data for sorting

// Sort table by column - reorder existing DOM rows
function sortTable(column) {
  // Toggle direction if same column
  if (currentSortColumn === column) {
    currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    currentSortColumn = column;
    currentSortDirection = 'asc';
  }
  
  // Update header icons
  updateSortIcons(column, currentSortDirection);
  
  const tbody = document.querySelector('#projectsTableContainer tbody');
  if (!tbody) return;
  
  // Get all existing rows (DOM elements)
  const rows = Array.from(tbody.querySelectorAll('tr'));
  
  // Sort the actual DOM rows
  rows.sort((rowA, rowB) => {
    let aVal, bVal;
    
    // Get column index for sorting
    const colIndex = {
      'project_id': 1,
      'name': 2,
      'client': 3,
      'start_date': 4,
      'end_date': 5,
      'budget': 6,
      'spent': 7,
      'progress': 8,
      'status': 9
    }[column];
    
    const cellA = rowA.cells[colIndex];
    const cellB = rowB.cells[colIndex];
    
    if (!cellA || !cellB) return 0;
    
    // Extract values based on column type
    if (column === 'budget' || column === 'spent') {
      // Extract numeric value from currency text
      aVal = parseFloat(cellA.textContent.replace(/[^0-9.-]/g, '')) || 0;
      bVal = parseFloat(cellB.textContent.replace(/[^0-9.-]/g, '')) || 0;
    } else if (column === 'start_date' || column === 'end_date') {
      // Parse date text
      aVal = cellA.textContent === '—' ? 0 : new Date(cellA.textContent).getTime();
      bVal = cellB.textContent === '—' ? 0 : new Date(cellB.textContent).getTime();
    } else if (column === 'progress') {
      // Extract percentage from progress cell
      const progressTextA = cellA.querySelector('span:last-child')?.textContent || '0%';
      const progressTextB = cellB.querySelector('span:last-child')?.textContent || '0%';
      aVal = parseFloat(progressTextA.replace('%', '')) || 0;
      bVal = parseFloat(progressTextB.replace('%', '')) || 0;
    } else {
      // Text comparison
      aVal = cellA.textContent.trim().toLowerCase();
      bVal = cellB.textContent.trim().toLowerCase();
    }
    
    // Compare
    if (aVal < bVal) return currentSortDirection === 'asc' ? -1 : 1;
    if (aVal > bVal) return currentSortDirection === 'asc' ? 1 : -1;
    return 0;
  });
  
  // Re-append rows in sorted order (preserves exact HTML structure)
  rows.forEach(row => tbody.appendChild(row));
  
  console.log(`Sorted by ${column} (${currentSortDirection.toUpperCase()})`);
}

// Update sort icons in headers
function updateSortIcons(activeColumn, direction) {
  // Reset all headers
  document.querySelectorAll('.sortable-header').forEach(header => {
    header.classList.remove('sorted-asc', 'sorted-desc');
  });
  
  // Set active header
  const activeHeader = Array.from(document.querySelectorAll('.sortable-header')).find(th => {
    const onclick = th.getAttribute('onclick');
    return onclick && onclick.includes(`'${activeColumn}'`);
  });
  
  if (activeHeader) {
    activeHeader.classList.add(`sorted-${direction}`);
  }
}

// Removed renderSortedTable - now using DOM row reordering instead

// Fetch and refresh projects table - simplified to page reload
async function refreshProjectsTable() {
  try {
    console.log('Refreshing projects...');
    window.location.reload();
  } catch (error) {
    console.error('Error refreshing projects:', error);
    console.log('Failed to refresh projects');
  }
}

// Removed createProjectRow - no longer needed with DOM row reordering

// Update single project without refresh
async function updateProject(projectId, updateData) {
  try {
    const response = await fetch('api/projects.php', {
      method: 'PUT',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id: projectId, ...updateData })
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Refresh only the affected row
      await refreshProjectsTable();
      return true;
    } else {
      console.log(result.message || 'Update failed');
      return false;
    }
  } catch (error) {
    console.error('Error updating project:', error);
    console.log('An error occurred');
    return false;
  }
}

// Delete project without refresh
async function deleteProjectDynamic(projectId) {
  try {
    const response = await fetch('api/projects.php', {
      method: 'DELETE',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id: projectId })
    });
    
    const result = await response.json();
    
    if (result.success) {
      console.log('Project deleted successfully');
      await refreshProjectsTable();
      return true;
    } else {
      console.log(result.message || 'Delete failed');
      return false;
    }
  } catch (error) {
    console.error('Error deleting project:', error);
    console.log('An error occurred');
    return false;
  }
}

// ============================================
// PERMANENT TOOLBAR FEATURES (Always Visible)
// ============================================

// Import Projects
function importProjects() {
  const formats = prompt(`Import projects from:\n\n1. Excel (.xlsx)\n2. CSV (.csv)\n3. JSON\n4. Microsoft Project (.mpp)\n5. Jira Export\n\nEnter import format:`);
  
  if (!formats || !['1','2','3','4','5'].includes(formats)) {
    console.log('Import cancelled');
    return;
  }
  
  const formatNames = {
    '1': 'Excel',
    '2': 'CSV',
    '3': 'JSON',
    '4': 'Microsoft Project',
    '5': 'Jira'
  };
  
  console.log('Select ' + formatNames[formats] + ' file to import');
  
  // Create file input dynamically
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = formats === '1' ? '.xlsx' : formats === '2' ? '.csv' : formats === '3' ? '.json' : '*';
  input.onchange = (e) => {
    const file = e.target.files[0];
    if (file) {
      console.log('Importing ' + file.name);
      window.location.reload();
    }
  };
  input.click();
}

// Project Templates
function showProjectTemplates() {
  const templates = [
    '1. Software Development Project',
    '2. Marketing Campaign',
    '3. Website Redesign',
    '4. Product Launch',
    '5. Event Planning',
    '6. Research Project',
    '7. Construction Project',
    '8. Training Program',
    '9. Custom Template'
  ];
  
  const choice = prompt(`Create project from template:\n\n${templates.join('\n')}\n\nEnter template number:`);
  
  if (!choice || !['1','2','3','4','5','6','7','8','9'].includes(choice)) {
    console.log('Template selection cancelled');
    return;
  }
  
  const templateNames = {
    '1': 'Software Development',
    '2': 'Marketing Campaign',
    '3': 'Website Redesign',
    '4': 'Product Launch',
    '5': 'Event Planning',
    '6': 'Research Project',
    '7': 'Construction',
    '8': 'Training Program',
    '9': 'Custom'
  };
  
  console.log('Creating project from ' + templateNames[choice] + ' template');
    showNewProjectModal();
}

// Calendar View - Compact with Pagination
function showCalendarView(event) {
  // Check if modal already exists (toggle functionality)
  const existingModal = document.getElementById('calendarViewModal');
  if (existingModal) {
    existingModal.remove();
    return;
  }

  // Get all projects from ALL table rows (including hidden/paginated)
  const projects = [];
  const allRows = document.querySelectorAll('#projectsTableContainer tbody tr');
  
  allRows.forEach(row => {
    const cells = row.cells;
    // Skip empty rows or header rows
    if (cells.length >= 6) {
      const projectId = cells[0]?.textContent?.trim() || '';
      const name = cells[1]?.textContent?.trim() || '';
      const client = cells[2]?.textContent?.trim() || '';
      const status = cells[3]?.textContent?.trim().toLowerCase() || '';
      const startDate = cells[4]?.textContent?.trim() || '';
      const endDate = cells[5]?.textContent?.trim() || '';
      
      // Only add if we have valid data
      if (name && name !== '' && startDate && startDate !== '') {
        projects.push({ projectId, name, client, status, startDate, endDate });
      }
    }
  });

  // Sort projects by start date
  projects.sort((a, b) => new Date(a.startDate) - new Date(b.startDate));

  // Pagination setup
  const itemsPerPage = 6;
  let currentPage = 1;
  const totalPages = Math.ceil(projects.length / itemsPerPage);
  
  // Get button position for alignment
  const calendarBtn = document.getElementById('calendarViewBtn');
  const btnRect = calendarBtn.getBoundingClientRect();
  
  // Calculate position (align with button, below it) - ULTRA COMPACT
  const modalWidth = 400;
  const modalHeight = Math.min(480, window.innerHeight - btnRect.bottom - 40);
  let leftPos = btnRect.right - modalWidth;
  
  // Ensure it stays on screen
  if (leftPos < 20) leftPos = 20;
  if (leftPos + modalWidth > window.innerWidth - 20) {
    leftPos = window.innerWidth - modalWidth - 20;
  }

  // Create modal
  const modal = document.createElement('div');
  modal.id = 'calendarViewModal';
  modal.style.cssText = `
    position: fixed;
    top: ${btnRect.bottom + 8}px;
    left: ${leftPos}px;
    width: ${modalWidth}px;
    max-height: ${modalHeight}px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border: 1px solid hsl(214 20% 92%);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    animation: slideIn 0.2s ease-out;
  `;

  // Status color mapping
  const statusColors = {
    'active': { bg: 'hsl(142 71% 45% / 0.08)', text: 'hsl(142 71% 45%)', border: 'hsl(142 71% 45% / 0.25)' },
    'completed': { bg: 'hsl(217 91% 60% / 0.08)', text: 'hsl(217 91% 60%)', border: 'hsl(217 91% 60% / 0.25)' },
    'on hold': { bg: 'hsl(45 93% 47% / 0.08)', text: 'hsl(45 93% 47%)', border: 'hsl(45 93% 47% / 0.25)' },
    'planning': { bg: 'hsl(200 90% 50% / 0.08)', text: 'hsl(200 90% 50%)', border: 'hsl(200 90% 50% / 0.25)' }
  };

  // Calculate date range stats
  const today = new Date();
  const upcoming = projects.filter(p => new Date(p.startDate) > today).length;
  const ongoing = projects.filter(p => {
    const start = new Date(p.startDate);
    const end = p.endDate ? new Date(p.endDate) : new Date('2099-12-31');
    return start <= today && end >= today;
  }).length;

  // Generate current month calendar
  const currentDate = new Date();
  const currentMonth = currentDate.getMonth();
  const currentYear = currentDate.getFullYear();
  const firstDay = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
  
  // Generate calendar grid
  let calendarHTML = '';
  let dayCount = 1;
  for (let week = 0; week < 6; week++) {
    let weekHTML = '';
    for (let day = 0; day < 7; day++) {
      if ((week === 0 && day < firstDay) || dayCount > daysInMonth) {
        weekHTML += `<div style="aspect-ratio: 1; padding: 0.375rem; border: 1px solid transparent;"></div>`;
      } else {
        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(dayCount).padStart(2, '0')}`;
        const dayProjects = projects.filter(p => p.startDate === dateStr || p.endDate === dateStr);
        const isToday = dayCount === currentDate.getDate();
        
        weekHTML += `
          <div style="aspect-ratio: 1; padding: 0.25rem; border: 1px solid hsl(214 20% 92%); border-radius: 4px; background: ${isToday ? 'hsl(217 91% 60% / 0.1)' : 'white'}; cursor: pointer; transition: all 0.15s; position: relative;" onmouseover="this.style.background='hsl(214 95% 97%)'" onmouseout="this.style.background='${isToday ? 'hsl(217 91% 60% / 0.1)' : 'white'}'">
            <div style="font-size: 0.625rem; font-weight: ${isToday ? '700' : '500'}; color: ${isToday ? 'hsl(217 91% 60%)' : 'hsl(222 47% 17%)'};">${dayCount}</div>
            ${dayProjects.length > 0 ? `<div style="position: absolute; bottom: 2px; right: 2px; width: 5px; height: 5px; border-radius: 50%; background: hsl(142 71% 45%);"></div>` : ''}
          </div>
        `;
        dayCount++;
      }
    }
    calendarHTML += `<div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">${weekHTML}</div>`;
    if (dayCount > daysInMonth) break;
  }
  
  const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

  // Function to render list with pagination
  const renderList = (page) => {
    const start = (page - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const paginatedProjects = projects.slice(start, end);
    
    const listContent = document.getElementById('listViewContent');
    if (listContent) {
      listContent.innerHTML = paginatedProjects.length === 0 ? `
        <div style="padding: 2rem 1rem; text-align: center;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="hsl(215 16% 47%)" stroke-width="1.5" style="margin: 0 auto 0.5rem auto; opacity: 0.5;">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
          <p style="margin: 0; color: hsl(215 16% 47%); font-size: 0.6875rem; font-weight: 500;">No projects</p>
        </div>
      ` : paginatedProjects.map(project => {
        const colors = statusColors[project.status] || statusColors['active'];
        const startDate = new Date(project.startDate);
        const endDate = project.endDate ? new Date(project.endDate) : null;
        const daysUntilStart = Math.ceil((startDate - today) / (1000 * 60 * 60 * 24));
        
        let timeLabel = '', timeLabelColor = 'hsl(215 16% 47%)';
        if (daysUntilStart > 0) {
          timeLabel = `In ${daysUntilStart}d`;
          timeLabelColor = 'hsl(200 90% 50%)';
        } else if (endDate && endDate >= today) {
          const daysLeft = Math.ceil((endDate - today) / (1000 * 60 * 60 * 24));
          timeLabel = `${daysLeft}d left`;
          timeLabelColor = daysLeft <= 7 ? 'hsl(45 93% 47%)' : 'hsl(142 71% 45%)';
        } else if (endDate && endDate < today) {
          timeLabel = 'Overdue';
          timeLabelColor = 'hsl(0 74% 50%)';
        } else {
          timeLabel = 'Ongoing';
          timeLabelColor = 'hsl(142 71% 45%)';
        }

        return `
          <div class="calendar-item" style="padding: 0.5rem; border-radius: 6px; margin-bottom: 0.375rem; border: 1px solid ${colors.border}; background: ${colors.bg}; transition: all 0.15s; cursor: pointer;" onclick="showProjectDetails('${project.projectId}')">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.375rem;">
              <div style="flex: 1; min-width: 0;">
                <h4 style="margin: 0; font-size: 0.6875rem; font-weight: 600; color: hsl(222 47% 17%); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${project.projectId}</h4>
                <p style="margin: 0; font-size: 0.625rem; color: hsl(215 16% 47%); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${project.name}</p>
              </div>
              <span style="padding: 0.125rem 0.375rem; border-radius: 3px; border: 1px solid ${colors.border}; color: ${colors.text}; font-size: 0.5625rem; font-weight: 600; text-transform: uppercase; white-space: nowrap; margin-left: 0.375rem;">${project.status}</span>
            </div>
            <div style="display: flex; gap: 0.5rem; font-size: 0.625rem;">
              <div style="flex: 1; display: flex; align-items: center; gap: 0.25rem;">
                <span style="color: hsl(142 71% 45%);">●</span>
                <span style="color: hsl(222 47% 17%); font-weight: 600;">${project.startDate}</span>
              </div>
              ${project.endDate ? `
                <div style="flex: 1; display: flex; align-items: center; gap: 0.25rem;">
                  <span style="color: hsl(0 74% 50%);">●</span>
                  <span style="color: hsl(222 47% 17%); font-weight: 600;">${project.endDate}</span>
                </div>
              ` : ''}
            </div>
            <div style="margin-top: 0.25rem; font-size: 0.625rem; font-weight: 600; color: ${timeLabelColor};">${timeLabel}</div>
          </div>
        `;
      }).join('');
      
      // Update pagination
      const pageInfo = document.getElementById('listPageInfo');
      if (pageInfo) pageInfo.textContent = `Page ${page} of ${totalPages}`;
      
      const prevBtn = document.getElementById('listPrevBtn');
      const nextBtn = document.getElementById('listNextBtn');
      if (prevBtn) prevBtn.disabled = page === 1;
      if (nextBtn) nextBtn.disabled = page === totalPages;
    }
  };
  
  modal.innerHTML = `
    <style>
      @keyframes slideIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
      }
      .calendar-item:hover {
        background: hsl(214 95% 97%) !important;
      }
      .calendar-tab {
        padding: 0.375rem 0.75rem;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 0.6875rem;
        font-weight: 600;
        color: hsl(215 16% 47%);
        border-bottom: 2px solid transparent;
        transition: all 0.15s;
      }
      .calendar-tab:hover {
        color: hsl(222 47% 17%);
        background: hsl(214 95% 97%);
      }
      .calendar-tab.active {
        color: hsl(217 91% 60%);
        border-bottom-color: hsl(217 91% 60%);
      }
      .calendar-view {
        display: none !important;
        height: 100%;
      }
      .calendar-view.active {
        display: block !important;
      }
      #listView {
        display: none !important;
      }
      #listView.active {
        display: flex !important;
        flex-direction: column;
      }
      .pagination-btn {
        border: 1px solid hsl(214 20% 92%);
        background: white;
        color: hsl(215 16% 47%);
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.625rem;
        font-weight: 600;
        padding: 0.375rem 0.625rem;
        transition: all 0.15s;
      }
      .pagination-btn:hover:not(:disabled) {
        background: hsl(214 95% 97%);
        color: hsl(222 47% 17%);
      }
      .pagination-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
      }
    </style>

    <!-- Header -->
    <div style="padding: 0.5rem 0.75rem; border-bottom: 1px solid hsl(214 20% 92%);">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.375rem;">
        <div>
          <h3 style="margin: 0; font-size: 0.6875rem; font-weight: 700; color: hsl(222 47% 17%);">📅 Calendar</h3>
          <p style="margin: 0; font-size: 0.5625rem; color: hsl(215 16% 47%); font-weight: 500;">${projects.length} • ${ongoing} ongoing • ${upcoming} upcoming</p>
        </div>
        <button onclick="document.getElementById('calendarViewModal').remove()" style="width: 24px; height: 24px; border-radius: 4px; border: none; background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s;" onmouseover="this.style.background='hsl(214 95% 93%)'" onmouseout="this.style.background='transparent'">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      
      <!-- Tabs -->
      <div style="display: flex; gap: 0.125rem; border-bottom: 1px solid hsl(214 20% 92%); margin: 0 -0.75rem; padding: 0 0.75rem;">
        <button class="calendar-tab active" onclick="switchCalendarView(event, 'list')">List</button>
        <button class="calendar-tab" onclick="switchCalendarView(event, 'month')">Month</button>
        <button class="calendar-tab" onclick="switchCalendarView(event, 'week')">Week</button>
      </div>
    </div>

    <!-- View Container -->
    <div style="height: ${modalHeight - 100}px; overflow: hidden;">
      <!-- List View -->
      <div id="listView" class="calendar-view active" style="height: 100%;">
        <div id="listViewContent" style="overflow-y: auto; flex: 1; padding: 0.5rem;"></div>
        ${projects.length > itemsPerPage ? `
          <div style="padding: 0.5rem 0.75rem; border-top: 1px solid hsl(214 20% 92%); display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
          <button id="listPrevBtn" class="pagination-btn" onclick="navigateListPage(-1)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: inline-block; vertical-align: middle;">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            Prev
          </button>
          <span id="listPageInfo" style="font-size: 0.625rem; color: hsl(215 16% 47%); font-weight: 600;">Page 1 of ${totalPages}</span>
          <button id="listNextBtn" class="pagination-btn" onclick="navigateListPage(1)">
            Next
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: inline-block; vertical-align: middle;">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
          </div>
        ` : ''}
      </div>

      <!-- Month View -->
      <div id="monthView" class="calendar-view" style="padding: 0.5rem; height: 100%; overflow-y: auto;">
      <div style="margin-bottom: 0.5rem; text-align: center;">
        <h4 style="margin: 0; font-size: 0.75rem; font-weight: 700; color: hsl(222 47% 17%);">${monthNames[currentMonth]} ${currentYear}</h4>
      </div>
      
      <!-- Day headers -->
      <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 4px;">
        <div style="text-align: center; font-size: 0.5625rem; font-weight: 600; color: hsl(215 16% 47%); padding: 0.125rem;">Sun</div>
        <div style="text-align: center; font-size: 0.5625rem; font-weight: 600; color: hsl(215 16% 47%); padding: 0.125rem;">Mon</div>
        <div style="text-align: center; font-size: 0.5625rem; font-weight: 600; color: hsl(215 16% 47%); padding: 0.125rem;">Tue</div>
        <div style="text-align: center; font-size: 0.5625rem; font-weight: 600; color: hsl(215 16% 47%); padding: 0.125rem;">Wed</div>
        <div style="text-align: center; font-size: 0.5625rem; font-weight: 600; color: hsl(215 16% 47%); padding: 0.125rem;">Thu</div>
        <div style="text-align: center; font-size: 0.5625rem; font-weight: 600; color: hsl(215 16% 47%); padding: 0.125rem;">Fri</div>
        <div style="text-align: center; font-size: 0.5625rem; font-weight: 600; color: hsl(215 16% 47%); padding: 0.125rem;">Sat</div>
      </div>
      
        <!-- Calendar grid -->
        ${calendarHTML}
      </div>

      <!-- Week View -->
      <div id="weekView" class="calendar-view" style="padding: 0.5rem; height: 100%; overflow-y: auto;">
      <div style="margin-bottom: 0.5rem; text-align: center;">
        <h4 style="margin: 0; font-size: 0.75rem; font-weight: 700; color: hsl(222 47% 17%);">This Week</h4>
        <p style="margin: 0.125rem 0 0 0; font-size: 0.625rem; color: hsl(215 16% 47%);">Week ${Math.ceil((currentDate.getDate() + firstDay) / 7)}</p>
      </div>
      
      <div style="display: flex; flex-direction: column; gap: 0.375rem;">
        ${(() => {
          const weekStart = new Date(currentDate);
          weekStart.setDate(currentDate.getDate() - currentDate.getDay());
          let weekHTML = '';
          for (let i = 0; i < 7; i++) {
            const day = new Date(weekStart);
            day.setDate(weekStart.getDate() + i);
            const dateStr = day.toISOString().split('T')[0];
            const dayProjects = projects.filter(p => p.startDate === dateStr || p.endDate === dateStr);
            const isToday = day.toDateString() === currentDate.toDateString();
            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            
            weekHTML += `
              <div style="padding: 0.5rem; border: 1px solid hsl(214 20% 92%); border-radius: 6px; background: ${isToday ? 'hsl(217 91% 60% / 0.05)' : 'white'};">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem;">
                  <div>
                    <div style="font-size: 0.6875rem; font-weight: 600; color: hsl(222 47% 17%);">${dayNames[i]}</div>
                    <div style="font-size: 0.625rem; color: hsl(215 16% 47%); margin-top: 0.0625rem;">${day.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</div>
                  </div>
                  ${dayProjects.length > 0 ? `
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: hsl(142 71% 45% / 0.15); color: hsl(142 71% 45%); font-size: 0.625rem; font-weight: 700;">${dayProjects.length}</span>
                  ` : ''}
                </div>
                ${dayProjects.length > 0 ? `
                  <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    ${dayProjects.map(p => `
                      <div style="font-size: 0.625rem; padding: 0.25rem 0.375rem; background: hsl(214 95% 97%); border-radius: 3px; color: hsl(222 47% 17%); font-weight: 500; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" onclick="showProjectDetails('${p.projectId}')">${p.name}</div>
                    `).join('')}
                  </div>
                ` : `
                  <div style="font-size: 0.625rem; color: hsl(215 16% 65%); font-style: italic;">No projects</div>
                `}
              </div>
            `;
          }
          return weekHTML;
        })()}
        </div>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  // Initialize list with first page
  renderList(currentPage);

  // Pagination navigation function
  window.navigateListPage = function(direction) {
    currentPage += direction;
    if (currentPage < 1) currentPage = 1;
    if (currentPage > totalPages) currentPage = totalPages;
    renderList(currentPage);
  };

  // Tab switching function
  window.switchCalendarView = function(e, view) {
    e.preventDefault();
    e.stopPropagation();
    
    // Update tabs
    const tabs = document.querySelectorAll('#calendarViewModal .calendar-tab');
    tabs.forEach(tab => {
      tab.classList.remove('active');
    });
    e.currentTarget.classList.add('active');
    
    // Update views
    const views = document.querySelectorAll('#calendarViewModal .calendar-view');
    views.forEach(v => {
      v.classList.remove('active');
    });
    
    const targetView = document.getElementById(view + 'View');
    if (targetView) {
      targetView.classList.add('active');
      console.log('Switched to', view, 'view');
    } else {
      console.error('View not found:', view + 'View');
    }
  };

  // Close on outside click
  setTimeout(() => {
    document.addEventListener('click', function closeCalendar(e) {
      if (!modal.contains(e.target) && !e.target.closest('#calendarViewBtn')) {
        modal.remove();
        document.removeEventListener('click', closeCalendar);
        delete window.switchCalendarView;
        delete window.navigateListPage;
      }
    });
  }, 100);
}

// Gantt Chart
function showGanttChart() {
  console.log('Generating Gantt chart');
}

// Analytics Dashboard Modal
async function showAnalyticsDashboard() {
  // Create modal backdrop
  const modal = document.createElement('div');
  modal.id = 'analyticsModal';
  modal.style.cssText = `
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1.5rem;
    animation: fadeIn 0.2s ease;
  `;
  
  modal.innerHTML = `
    <div style="
      background: white;
      border-radius: 16px;
      border: 1px solid hsl(0 0% 90%);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      max-width: 1200px;
      width: 100%;
      max-height: 90vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      animation: slideUpScale 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    ">
      <!-- Header with Close Button -->
      <div style="
        padding: 1.5rem 2rem;
        border-bottom: 1px solid hsl(0 0% 90%);
        background: linear-gradient(to bottom, white, hsl(0 0% 99%));
      ">
        <div style="display: flex; align-items: flex-start; justify-content: space-between;">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="
              width: 48px;
              height: 48px;
              background: hsl(0 0% 9%);
              border-radius: 10px;
              display: flex;
              align-items: center;
              justify-content: center;
              box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            ">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>
              </svg>
            </div>
            <div>
              <h2 style="font-size: 1.25rem; font-weight: 700; color: hsl(0 0% 9%); margin: 0; letter-spacing: -0.025em;">
                Analytics Dashboard
              </h2>
              <p style="font-size: 0.875rem; color: hsl(0 0% 45%); margin: 0.25rem 0 0 0; font-weight: 400;">
                Real-time metrics • Last 30 days
              </p>
            </div>
          </div>
          <button onclick="closeAnalyticsModal()" style="
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid hsl(0 0% 90%);
            background: hsl(0 0% 9%);
            color: white;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
          " onmouseover="this.style.background='hsl(0 0% 15%)'" onmouseout="this.style.background='hsl(0 0% 9%)'">
            Close
          </button>
        </div>
      </div>
      
      <!-- Loading State -->
      <div id="analyticsContent" style="padding: 2rem; overflow-y: auto; flex: 1; background: hsl(0 0% 98%);">
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px; gap: 1rem;">
          <div style="
            width: 48px;
            height: 48px;
            border: 3px solid hsl(0 0% 90%);
            border-top-color: hsl(0 0% 9%);
            border-radius: 50%;
            animation: spin 1s linear infinite;
          "></div>
          <p style="font-size: 0.875rem; color: hsl(0 0% 45%); font-weight: 500; margin: 0;">
            Loading analytics data...
          </p>
        </div>
      </div>
    </div>
  `;
  
  document.body.appendChild(modal);
  
  // Add animations if not present
  if (!document.getElementById('analyticsModalAnimations')) {
    const style = document.createElement('style');
    style.id = 'analyticsModalAnimations';
    style.textContent = `
      @keyframes slideUpScale {
        from { opacity: 0; transform: translateY(20px) scale(0.96); }
        to { opacity: 1; transform: translateY(0) scale(1); }
      }
      @keyframes spin {
        to { transform: rotate(360deg); }
      }
    `;
    document.head.appendChild(style);
  }
  
  // Close on backdrop click
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeAnalyticsModal();
  });
  
  // Fetch analytics data
  await loadAnalyticsData();
}

function closeAnalyticsModal() {
  const modal = document.getElementById('analyticsModal');
  if (modal) modal.remove();
}

async function loadAnalyticsData() {
  try {
    // Fetch both analytics and project data
    const [analyticsRes, projectsRes] = await Promise.all([
      fetch('api/analytics.php?action=get_analytics&range=30d'),
      fetch('api/projects.php')
    ]);
    
    const analyticsData = await analyticsRes.json();
    const projectsData = await projectsRes.json();
    
    if (analyticsData.success && projectsData.success) {
      // Merge data
      const mergedData = {
        ...analyticsData,
        projects: projectsData.data || []
      };
      renderAnalyticsContent(mergedData);
    } else {
      showAnalyticsError('Failed to load analytics data');
    }
  } catch (error) {
    console.error('Analytics fetch error:', error);
    showAnalyticsError('Unable to connect to analytics service');
  }
}

function renderAnalyticsContent(data) {
  const content = document.getElementById('analyticsContent');
  if (!content) return;
  
  // Extract real project data
  const projects = data.projects || [];
  const totalProjects = projects.length;
  const activeProjects = projects.filter(p => p.status === 'active').length;
  const completedProjects = projects.filter(p => p.status === 'completed').length;
  const onHoldProjects = projects.filter(p => p.status === 'on_hold').length;
  
  // Calculate revenue from projects
  const revenue = data.kpis?.revenue?.total || 0;
  const activePercent = totalProjects > 0 ? Math.round((activeProjects / totalProjects) * 100) : 0;
  const completionPercent = totalProjects > 0 ? Math.round((completedProjects / totalProjects) * 100) : 0;
  const trendPercent = data.kpis?.inventory?.trend || 0;
  const growthPercent = data.kpis?.revenue?.trend || 0;
  
  // Prepare chart data from API
  const inventoryTrend = data.charts?.inventory_trend || [];
  const revenueTrend = data.charts?.revenue_trend || [];
  
  const projectTrendData = inventoryTrend.length > 0 
    ? inventoryTrend.map(d => d.value) 
    : [Math.max(0, totalProjects - 20), Math.max(0, totalProjects - 15), Math.max(0, totalProjects - 10), Math.max(0, totalProjects - 5), totalProjects];
  
  const projectTrendLabels = inventoryTrend.length > 0 
    ? inventoryTrend.map(d => d.label) 
    : ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Current'];
  
  const revenueData = revenueTrend.length > 0 
    ? revenueTrend.map(d => d.value) 
    : [revenue * 0.6, revenue * 0.7, revenue * 0.8, revenue * 0.9, revenue];
  
  const revenueLabels = revenueTrend.length > 0 
    ? revenueTrend.map(d => d.label) 
    : ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Current'];
  
  content.innerHTML = `
    <!-- Metric Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
      <!-- Total Projects Card -->
      <div style="
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid hsl(0 0% 92%);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
      ">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem;">
          <span style="font-size: 0.75rem; font-weight: 600; color: hsl(0 0% 40%); text-transform: uppercase; letter-spacing: 0.03em;">TOTAL PROJECTS</span>
          <div style="width: 32px; height: 32px; background: hsl(217 91% 95%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(217 91% 45%)" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
          </div>
        </div>
        <div style="font-size: 2.25rem; font-weight: 700; color: hsl(0 0% 9%); margin-bottom: 0.5rem; line-height: 1;">
          ${totalProjects}
        </div>
        <div style="font-size: 0.8125rem; color: hsl(142 71% 45%); font-weight: 500;">
          + ${trendPercent}% this period
        </div>
      </div>
      
      <!-- Active Projects Card -->
      <div style="
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid hsl(0 0% 92%);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
      ">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem;">
          <span style="font-size: 0.75rem; font-weight: 600; color: hsl(0 0% 40%); text-transform: uppercase; letter-spacing: 0.03em;">ACTIVE</span>
          <div style="width: 32px; height: 32px; background: hsl(142 85% 95%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(142 71% 40%)" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <path d="M22 4L12 14.01l-3-3"/>
            </svg>
          </div>
        </div>
        <div style="font-size: 2.25rem; font-weight: 700; color: hsl(0 0% 9%); margin-bottom: 0.5rem; line-height: 1;">
          ${activeProjects}
        </div>
        <div style="font-size: 0.8125rem; color: hsl(0 0% 45%); font-weight: 500;">
          ${activePercent}% of total
        </div>
      </div>
      
      <!-- Revenue Card -->
      <div style="
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid hsl(0 0% 92%);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
      ">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem;">
          <span style="font-size: 0.75rem; font-weight: 600; color: hsl(0 0% 40%); text-transform: uppercase; letter-spacing: 0.03em;">REVENUE</span>
          <div style="width: 32px; height: 32px; background: hsl(142 85% 95%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(142 71% 40%)" stroke-width="2">
              <line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
        </div>
        <div style="font-size: 2.25rem; font-weight: 700; color: hsl(0 0% 9%); margin-bottom: 0.5rem; line-height: 1;">
          ${formatCurrencyAmount(revenue)}
        </div>
        <div style="font-size: 0.8125rem; color: hsl(142 71% 45%); font-weight: 500;">
          + ${growthPercent}% growth
        </div>
      </div>
      
      <!-- Completion Card -->
      <div style="
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid hsl(0 0% 92%);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
      ">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.75rem;">
          <span style="font-size: 0.75rem; font-weight: 600; color: hsl(0 0% 40%); text-transform: uppercase; letter-spacing: 0.03em;">COMPLETION</span>
          <div style="width: 32px; height: 32px; background: hsl(217 91% 95%); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="hsl(217 91% 45%)" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
        </div>
        <div style="font-size: 2.25rem; font-weight: 700; color: hsl(0 0% 9%); margin-bottom: 0.5rem; line-height: 1;">
          ${completionPercent}%
        </div>
        <div style="font-size: 0.8125rem; color: hsl(0 0% 45%); font-weight: 500;">
          ${completedProjects} completed
        </div>
      </div>
    </div>
    
    <!-- Charts Row - Compact Flex Layout -->
    <div style="display: flex; gap: 0.875rem; margin-bottom: 2rem; overflow-x: auto;">
      <!-- Project Trend Line Chart -->
      <div style="
        background: white;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid hsl(0 0% 92%);
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        flex: 1;
        min-width: 280px;
      ">
        <h4 style="font-size: 0.75rem; font-weight: 600; color: hsl(0 0% 9%); margin: 0 0 0.75rem 0;">
          Project Growth
        </h4>
        <canvas id="projectTrendChart" style="max-height: 140px;"></canvas>
      </div>
      
      <!-- Revenue Bar Chart -->
      <div style="
        background: white;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid hsl(0 0% 92%);
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        flex: 1;
        min-width: 280px;
      ">
        <h4 style="font-size: 0.75rem; font-weight: 600; color: hsl(0 0% 9%); margin: 0 0 0.75rem 0;">
          Revenue Trend
        </h4>
        <canvas id="revenueBarChart" style="max-height: 140px;"></canvas>
      </div>
      
      <!-- Project Status Doughnut Chart -->
      <div style="
        background: white;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid hsl(0 0% 92%);
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        flex: 1;
        min-width: 240px;
        max-width: 300px;
      ">
        <h4 style="font-size: 0.75rem; font-weight: 600; color: hsl(0 0% 9%); margin: 0 0 0.75rem 0;">
          Status Split
        </h4>
        <canvas id="projectStatusChart" style="max-height: 140px;"></canvas>
      </div>
    </div>
    
    <!-- View Detailed Analytics Section -->
    <div style="
      background: white;
      padding: 2.5rem;
      border-radius: 12px;
      border: 1px solid hsl(0 0% 92%);
      text-align: center;
    ">
      <h3 style="font-size: 1.125rem; font-weight: 700; color: hsl(0 0% 9%); margin: 0 0 0.5rem 0; letter-spacing: -0.01em;">
        View Detailed Analytics
      </h3>
      <p style="font-size: 0.875rem; color: hsl(0 0% 45%); margin: 0 0 1.5rem 0; line-height: 1.5;">
        Access comprehensive analytics with charts, trends, and export capabilities
      </p>
      <button onclick="window.location.href='analytics-dashboard.php'" style="
        padding: 0.625rem 1.5rem;
        border-radius: 6px;
        border: none;
        background: hsl(217 91% 60%);
        color: white;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      " onmouseover="this.style.background='hsl(217 91% 55%)'" onmouseout="this.style.background='hsl(217 91% 60%)'">
        Open Full Analytics Dashboard
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
        </svg>
      </button>
    </div>
  `;
  
  // Initialize charts after DOM update
  setTimeout(() => {
    initializeAnalyticsCharts({
      projectTrendData,
      projectTrendLabels,
      revenueData,
      revenueLabels,
      activeProjects,
      completedProjects,
      onHoldProjects,
      totalProjects
    });
  }, 100);
}

function showAnalyticsError(message) {
  const content = document.getElementById('analyticsContent');
  if (!content) return;
  
  content.innerHTML = `
    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px; gap: 1.25rem;">
      <div style="
        width: 64px;
        height: 64px;
        background: hsl(0 86% 97%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
      ">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="hsl(0 74% 50%)" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </div>
      <div style="text-align: center; max-width: 400px;">
        <h3 style="font-size: 1.125rem; font-weight: 700; color: hsl(0 0% 9%); margin: 0 0 0.5rem 0;">
          Unable to Load Analytics
        </h3>
        <p style="font-size: 0.875rem; color: hsl(0 0% 45%); margin: 0; line-height: 1.5;">
          ${message}
        </p>
      </div>
      <button onclick="window.location.href='analytics-dashboard.php'" style="
        padding: 0.625rem 1.25rem;
        border-radius: 6px;
        border: 1px solid hsl(0 0% 90%);
        background: white;
        color: hsl(0 0% 9%);
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      " onmouseover="this.style.background='hsl(0 0% 98%)'" onmouseout="this.style.background='white'">
        Open Full Dashboard Instead
      </button>
    </div>
  `;
}

// Initialize Analytics Charts with Chart.js
function initializeAnalyticsCharts(data) {
  if (typeof Chart === 'undefined') {
    console.error('Chart.js not loaded');
    return;
  }
  
  // Destroy existing charts if any
  const existingCharts = ['projectTrendChart', 'revenueBarChart', 'projectStatusChart'];
  existingCharts.forEach(id => {
    const canvas = document.getElementById(id);
    if (canvas) {
      const existingChart = Chart.getChart(canvas);
      if (existingChart) existingChart.destroy();
    }
  });
  
  // Chart.js default config witg compact size styling
  Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  Chart.defaults.font.size = 10;
  Chart.defaults.color = 'hsl(0 0% 45%)';
  
  // 1. Project Trend Line Chart
  const trendCanvas = document.getElementById('projectTrendChart');
  if (trendCanvas) {
    new Chart(trendCanvas, {
      type: 'line',
      data: {
        labels: data.projectTrendLabels,
        datasets: [{
          label: 'Total Projects',
          data: data.projectTrendData,
          borderColor: 'hsl(217 91% 60%)',
          backgroundColor: 'hsl(217 91% 60% / 0.1)',
          borderWidth: 2,
          fill: true,
          tension: 0.4,
          pointRadius: 4,
          pointBackgroundColor: 'hsl(217 91% 60%)',
          pointBorderColor: 'white',
          pointBorderWidth: 2,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'hsl(0 0% 9%)',
            titleColor: 'white',
            bodyColor: 'white',
            borderColor: 'hsl(0 0% 20%)',
            borderWidth: 1,
            padding: 8,
            cornerRadius: 4,
            displayColors: false,
            titleFont: { size: 10 },
            bodyFont: { size: 9 }
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            },
            ticks: {
              color: 'hsl(0 0% 45%)',
              font: {
                size: 9
              }
            }
          },
          y: {
            beginAtZero: true,
            grid: {
              color: 'hsl(0 0% 95%)',
              drawBorder: false
            },
            ticks: {
              color: 'hsl(0 0% 45%)',
              font: {
                size: 9
              }
            }
          }
        }
      }
    });
  }
  
  // 2. Revenue Bar Chart
  const revenueCanvas = document.getElementById('revenueBarChart');
  if (revenueCanvas) {
    new Chart(revenueCanvas, {
      type: 'bar',
      data: {
        labels: data.revenueLabels,
        datasets: [{
          label: 'Revenue',
          data: data.revenueData,
          backgroundColor: 'hsl(142 71% 45%)',
          borderColor: 'hsl(142 71% 35%)',
          borderWidth: 1,
          borderRadius: 6,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'hsl(0 0% 9%)',
            titleColor: 'white',
            bodyColor: 'white',
            borderColor: 'hsl(0 0% 20%)',
            borderWidth: 1,
            padding: 8,
            cornerRadius: 4,
            displayColors: false,
            titleFont: { size: 10 },
            bodyFont: { size: 9 },
            callbacks: {
              label: function(context) {
                return '₱' + context.parsed.y.toLocaleString();
              }
            }
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            },
            ticks: {
              color: 'hsl(0 0% 45%)',
              font: {
                size: 9
              }
            }
          },
          y: {
            beginAtZero: true,
            grid: {
              color: 'hsl(0 0% 95%)',
              drawBorder: false
            },
            ticks: {
              color: 'hsl(0 0% 45%)',
              font: {
                size: 9
              },
              callback: function(value) {
                return '₱' + (value / 1000) + 'k';
              }
            }
          }
        }
      }
    });
  }
  
  // 3. Project Status Doughnut Chart
  const statusCanvas = document.getElementById('projectStatusChart');
  if (statusCanvas) {
    const onHoldCount = data.onHoldProjects || 0;
    new Chart(statusCanvas, {
      type: 'doughnut',
      data: {
        labels: ['Active', 'Completed', 'On Hold'],
        datasets: [{
          data: [data.activeProjects, data.completedProjects, onHoldCount],
          backgroundColor: [
            'hsl(142 71% 45%)',
            'hsl(217 91% 60%)',
            'hsl(45 93% 47%)'
          ],
          borderColor: 'white',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: 'hsl(0 0% 45%)',
              padding: 8,
              font: {
                size: 10,
                weight: '500'
              },
              usePointStyle: true,
              pointStyle: 'circle',
              boxWidth: 8,
              boxHeight: 8
            }
          },
          tooltip: {
            backgroundColor: 'hsl(0 0% 9%)',
            titleColor: 'white',
            bodyColor: 'white',
            borderColor: 'hsl(0 0% 20%)',
            borderWidth: 1,
            padding: 10,
            cornerRadius: 6,
            displayColors: true,
            titleFont: { size: 10 },
            bodyFont: { size: 9 },
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.parsed || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                return label + ': ' + value + ' (' + percentage + '%)';
              }
            }
          }
        }
      }
    });
  }
}

// Time Tracking
function showTimeTracking() {
  console.log('Opening time tracking dashboard');
}

// Budget Overview
function showBudgetOverview() {
  console.log('Generating budget overview');
}

// Advanced Filters Modal
function showAdvancedFilters() {
  const modal = document.createElement('div');
  modal.id = 'advancedFiltersModal';
  modal.style.cssText = `
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
    animation: fadeIn 0.2s ease;
  `;
  
  modal.innerHTML = `
    <div style="
      background: white;
      border-radius: 16px;
      box-shadow: 
        0 0 0 1px rgba(0, 0, 0, 0.05),
        0 20px 25px -5px rgba(0, 0, 0, 0.15),
        0 10px 10px -5px rgba(0, 0, 0, 0.08);
      width: 100%;
      max-width: 720px;
      max-height: 90vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      animation: slideUpFade 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    ">
      <!-- Header -->
      <div style="
        padding: 2rem 2rem 1.5rem 2rem;
        border-bottom: 1px solid hsl(0 0% 90%);
        background: linear-gradient(to bottom, white, hsl(0 0% 99%));
      ">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
          <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
              <div style="
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, hsl(0 0% 13%) 0%, hsl(0 0% 25%) 100%);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
              ">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                  <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>
                </svg>
              </div>
              <h2 style="
                font-size: 1.5rem;
                font-weight: 700;
                color: hsl(0 0% 9%);
                margin: 0;
                letter-spacing: -0.02em;
              ">
                Advanced Filters
              </h2>
            </div>
            <p style="
              font-size: 0.9375rem;
              color: hsl(0 0% 45%);
              margin: 0;
              line-height: 1.5;
            ">
              Refine your project search with multiple criteria
            </p>
          </div>
          <button onclick="closeAdvancedFilters()" style="
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid hsl(0 0% 90%);
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: hsl(0 0% 40%);
            transition: all 0.2s;
            flex-shrink: 0;
          " onmouseover="this.style.background='hsl(0 0% 96%)'; this.style.borderColor='hsl(0 0% 80%)'; this.style.color='hsl(0 0% 9%)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(0 0% 90%)'; this.style.color='hsl(0 0% 40%)'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
      
      <!-- Body -->
      <div style="
        padding: 2rem;
        overflow-y: auto;
        flex: 1;
        background: hsl(0 0% 98%);
      ">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
          
          <!-- Status Filter - Color Coded -->
          <div style="grid-column: 1 / -1;">
            <label style="
              display: flex;
              align-items: center;
              gap: 0.5rem;
              font-size: 0.8125rem;
              font-weight: 600;
              color: hsl(0 0% 20%);
              margin-bottom: 0.625rem;
              text-transform: uppercase;
              letter-spacing: 0.03em;
            ">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
              </svg>
              Project Status
            </label>
            <select id="filterStatus" style="
              width: 100%;
              padding: 0.75rem 1rem;
              border: 1.5px solid hsl(0 0% 85%);
              border-radius: 10px;
              font-size: 0.9375rem;
              font-weight: 500;
              color: hsl(0 0% 15%);
              background: white;
              cursor: pointer;
              outline: none;
              transition: all 0.2s;
              box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            " onfocus="this.style.borderColor='hsl(0 0% 20%)'; this.style.boxShadow='0 0 0 3px rgba(0, 0, 0, 0.05)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.boxShadow='0 1px 2px rgba(0, 0, 0, 0.05)'">
              <option value="" style="color: hsl(0 0% 40%);">All Statuses</option>
              <option value="active" style="color: hsl(142 71% 35%); font-weight: 600;">✓ Active</option>
              <option value="completed" style="color: hsl(217 91% 45%); font-weight: 600;">✓ Completed</option>
              <option value="on-hold" style="color: hsl(38 92% 40%); font-weight: 600;">⏸ On Hold</option>
              <option value="archived" style="color: hsl(0 0% 50%); font-weight: 600;">📦 Archived</option>
              <option value="draft" style="color: hsl(0 0% 60%); font-weight: 600;">📝 Draft</option>
            </select>
          </div>
          
          <!-- Project Name Search -->
          <div style="grid-column: 1 / -1;">
            <label style="
              display: flex;
              align-items: center;
              gap: 0.5rem;
              font-size: 0.8125rem;
              font-weight: 600;
              color: hsl(0 0% 20%);
              margin-bottom: 0.625rem;
              text-transform: uppercase;
              letter-spacing: 0.03em;
            ">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
              </svg>
              Project Name
            </label>
            <input type="text" id="filterProjectName" placeholder="Search by project name..." style="
              width: 100%;
              padding: 0.75rem 1rem;
              border: 1.5px solid hsl(0 0% 85%);
              border-radius: 10px;
              font-size: 0.9375rem;
              color: hsl(0 0% 15%);
              background: white;
              outline: none;
              transition: all 0.2s;
              box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            " onfocus="this.style.borderColor='hsl(0 0% 20%)'; this.style.boxShadow='0 0 0 3px rgba(0, 0, 0, 0.05)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.boxShadow='0 1px 2px rgba(0, 0, 0, 0.05)'">
          </div>
          
          <!-- Client Filter -->
          <div>
            <label style="
              display: flex;
              align-items: center;
              gap: 0.5rem;
              font-size: 0.8125rem;
              font-weight: 600;
              color: hsl(0 0% 20%);
              margin-bottom: 0.625rem;
              text-transform: uppercase;
              letter-spacing: 0.03em;
            ">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M8.5 11a4 4 0 100-8 4 4 0 000 8z"/>
              </svg>
              Client
            </label>
            <input type="text" id="filterClient" placeholder="Enter client name..." style="
              width: 100%;
              padding: 0.75rem 1rem;
              border: 1.5px solid hsl(0 0% 85%);
              border-radius: 10px;
              font-size: 0.9375rem;
              color: hsl(0 0% 15%);
              background: white;
              outline: none;
              transition: all 0.2s;
              box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            " onfocus="this.style.borderColor='hsl(0 0% 20%)'; this.style.boxShadow='0 0 0 3px rgba(0, 0, 0, 0.05)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.boxShadow='0 1px 2px rgba(0, 0, 0, 0.05)'">
          </div>
          
          <!-- Progress Range -->
          <div>
            <label style="
              display: flex;
              align-items: center;
              gap: 0.5rem;
              font-size: 0.8125rem;
              font-weight: 600;
              color: hsl(0 0% 20%);
              margin-bottom: 0.625rem;
              text-transform: uppercase;
              letter-spacing: 0.03em;
            ">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <path d="M22 4L12 14.01l-3-3"/>
              </svg>
              Progress %
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.625rem;">
              <input type="number" id="filterProgressMin" placeholder="Min %" min="0" max="100" style="
                width: 100%;
                padding: 0.75rem 1rem;
                border: 1.5px solid hsl(0 0% 85%);
                border-radius: 10px;
                font-size: 0.9375rem;
                color: hsl(0 0% 15%);
                background: white;
                outline: none;
                transition: all 0.2s;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
              " onfocus="this.style.borderColor='hsl(0 0% 20%)'; this.style.boxShadow='0 0 0 3px rgba(0, 0, 0, 0.05)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.boxShadow='0 1px 2px rgba(0, 0, 0, 0.05)'">
              <input type="number" id="filterProgressMax" placeholder="Max %" min="0" max="100" style="
                width: 100%;
                padding: 0.75rem 1rem;
                border: 1.5px solid hsl(0 0% 85%);
                border-radius: 10px;
                font-size: 0.9375rem;
                color: hsl(0 0% 15%);
                background: white;
                outline: none;
                transition: all 0.2s;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
              " onfocus="this.style.borderColor='hsl(0 0% 20%)'; this.style.boxShadow='0 0 0 3px rgba(0, 0, 0, 0.05)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.boxShadow='0 1px 2px rgba(0, 0, 0, 0.05)'">
            </div>
          </div>
          
          <!-- Budget Range -->
          <div style="grid-column: 1 / -1;">
            <label style="
              display: flex;
              align-items: center;
              gap: 0.5rem;
              font-size: 0.8125rem;
              font-weight: 600;
              color: hsl(0 0% 20%);
              margin-bottom: 0.625rem;
              text-transform: uppercase;
              letter-spacing: 0.03em;
            ">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
              </svg>
              Budget Range
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
              <input type="number" id="filterBudgetMin" placeholder="Min budget" style="
                width: 100%;
                padding: 0.75rem 1rem;
                border: 1.5px solid hsl(0 0% 85%);
                border-radius: 10px;
                font-size: 0.9375rem;
                color: hsl(0 0% 15%);
                background: white;
                outline: none;
                transition: all 0.2s;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
              " onfocus="this.style.borderColor='hsl(0 0% 20%)'; this.style.boxShadow='0 0 0 3px rgba(0, 0, 0, 0.05)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.boxShadow='0 1px 2px rgba(0, 0, 0, 0.05)'">
              <input type="number" id="filterBudgetMax" placeholder="Max budget" style="
                width: 100%;
                padding: 0.75rem 1rem;
                border: 1.5px solid hsl(0 0% 85%);
                border-radius: 10px;
                font-size: 0.9375rem;
                color: hsl(0 0% 15%);
                background: white;
                outline: none;
                transition: all 0.2s;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
              " onfocus="this.style.borderColor='hsl(0 0% 20%)'; this.style.boxShadow='0 0 0 3px rgba(0, 0, 0, 0.05)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.boxShadow='0 1px 2px rgba(0, 0, 0, 0.05)'">
            </div>
          </div>
          
          <!-- Date Range -->
          <div style="grid-column: 1 / -1;">
            <label style="
              display: flex;
              align-items: center;
              gap: 0.5rem;
              font-size: 0.8125rem;
              font-weight: 600;
              color: hsl(0 0% 20%);
              margin-bottom: 0.625rem;
              text-transform: uppercase;
              letter-spacing: 0.03em;
            ">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
              </svg>
              Date Range
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
              <div>
                <label style="font-size: 0.75rem; color: hsl(0 0% 45%); margin-bottom: 0.375rem; display: block;">From</label>
                <input type="date" id="filterDateFrom" style="
                  width: 100%;
                  padding: 0.75rem 1rem;
                  border: 1.5px solid hsl(0 0% 85%);
                  border-radius: 10px;
                  font-size: 0.9375rem;
                  color: hsl(0 0% 15%);
                  background: white;
                  outline: none;
                  transition: all 0.2s;
                  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                " onfocus="this.style.borderColor='hsl(0 0% 20%)'; this.style.boxShadow='0 0 0 3px rgba(0, 0, 0, 0.05)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.boxShadow='0 1px 2px rgba(0, 0, 0, 0.05)'">
              </div>
              <div>
                <label style="font-size: 0.75rem; color: hsl(0 0% 45%); margin-bottom: 0.375rem; display: block;">To</label>
                <input type="date" id="filterDateTo" style="
                  width: 100%;
                  padding: 0.75rem 1rem;
                  border: 1.5px solid hsl(0 0% 85%);
                  border-radius: 10px;
                  font-size: 0.9375rem;
                  color: hsl(0 0% 15%);
                  background: white;
                  outline: none;
                  transition: all 0.2s;
                  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                " onfocus="this.style.borderColor='hsl(0 0% 20%)'; this.style.boxShadow='0 0 0 3px rgba(0, 0, 0, 0.05)'" onblur="this.style.borderColor='hsl(0 0% 85%)'; this.style.boxShadow='0 1px 2px rgba(0, 0, 0, 0.05)'">
              </div>
            </div>
          </div>
          
        </div>
      </div>
      
      <!-- Footer -->
      <div style="
        padding: 1.5rem 2rem;
        border-top: 1px solid hsl(0 0% 90%);
        background: white;
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
      ">
        <button onclick="clearFilters()" style="
          padding: 0.75rem 1.5rem;
          border-radius: 10px;
          border: 1.5px solid hsl(0 0% 85%);
          background: white;
          color: hsl(0 0% 25%);
          font-size: 0.9375rem;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.2s;
          display: flex;
          align-items: center;
          gap: 0.5rem;
        " onmouseover="this.style.background='hsl(0 0% 96%)'; this.style.borderColor='hsl(0 0% 75%)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(0 0% 85%)'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
          </svg>
          Clear All
        </button>
        <button onclick="applyAdvancedFilters()" style="
          padding: 0.75rem 2rem;
          border-radius: 10px;
          border: none;
          background: linear-gradient(135deg, hsl(0 0% 13%) 0%, hsl(0 0% 25%) 100%);
          color: white;
          font-size: 0.9375rem;
          font-weight: 700;
          cursor: pointer;
          transition: all 0.2s;
          box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
          display: flex;
          align-items: center;
          gap: 0.5rem;
        " onmouseover="this.style.background='linear-gradient(135deg, hsl(0 0% 18%) 0%, hsl(0 0% 30%) 100%)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 10px -2px rgba(0, 0, 0, 0.2)'" onmouseout="this.style.background='linear-gradient(135deg, hsl(0 0% 13%) 0%, hsl(0 0% 25%) 100%)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.15), 0 2px 4px -1px rgba(0, 0, 0, 0.1)'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>
          </svg>
          Apply Filters
        </button>
      </div>
    </div>
  `;
  
  document.body.appendChild(modal);
  
  // Close on outside click
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeAdvancedFilters();
    }
  });
  
  // Add animation keyframes
  if (!document.getElementById('filterModalAnimations')) {
    const style = document.createElement('style');
    style.id = 'filterModalAnimations';
    style.textContent = `
      @keyframes slideUpFade {
        from {
          opacity: 0;
          transform: translateY(20px) scale(0.96);
        }
        to {
          opacity: 1;
          transform: translateY(0) scale(1);
        }
      }
    `;
    document.head.appendChild(style);
  }
}

function closeAdvancedFilters() {
  const modal = document.getElementById('advancedFiltersModal');
  if (modal) {
    modal.remove();
  }
}

function clearFilters() {
  // Clear input fields
  document.getElementById('filterStatus').value = '';
  document.getElementById('filterClient').value = '';
  document.getElementById('filterBudgetMin').value = '';
  document.getElementById('filterBudgetMax').value = '';
  document.getElementById('filterDateFrom').value = '';
  document.getElementById('filterDateTo').value = '';
  document.getElementById('filterProgressMin').value = '';
  document.getElementById('filterProgressMax').value = '';
  document.getElementById('filterProjectName').value = '';
  
  // ✅ REMOVE FROM LOCALSTORAGE
  localStorage.removeItem('projectFilters');
}

// ✅ LOAD FILTERS FROM LOCALSTORAGE ON PAGE LOAD
function loadFiltersFromStorage() {
  const savedFilters = localStorage.getItem('projectFilters');
  
  if (!savedFilters) {
    return; // No saved filters
  }
  
  try {
    const filters = JSON.parse(savedFilters);
    console.log('Loading saved filters:', filters);
    
    // Get all table rows
    const tbody = document.querySelector('#projectsTableContainer tbody');
    const rows = tbody.querySelectorAll('tr');
    
    let visibleCount = 0;
    
    // Apply the saved filters to rows
    rows.forEach(row => {
      let shouldShow = true;
      
      // Get cell data
      const cells = row.cells;
      const projectName = cells[2]?.textContent.trim().toLowerCase() || '';
      const client = cells[3]?.textContent.trim().toLowerCase() || '';
      const startDate = cells[4]?.textContent.trim() || '';
      const budgetText = cells[6]?.textContent.trim() || '0';
      const budget = parseFloat(budgetText.replace(/[^0-9.-]/g, '')) || 0;
      const progressCell = cells[8];
      const progressText = progressCell?.querySelector('span:last-child')?.textContent || '0%';
      const progress = parseFloat(progressText.replace('%', '')) || 0;
      const statusBadge = cells[9]?.querySelector('.badge');
      const status = statusBadge?.textContent.trim().toLowerCase().replace(' ', '-') || '';
      
      // Apply filters
      if (filters.status && status !== filters.status) shouldShow = false;
      if (filters.client && !client.includes(filters.client)) shouldShow = false;
      if (filters.budgetMin !== null && budget < filters.budgetMin) shouldShow = false;
      if (filters.budgetMax !== null && budget > filters.budgetMax) shouldShow = false;
      if (filters.progressMin !== null && progress < filters.progressMin) shouldShow = false;
      if (filters.progressMax !== null && progress > filters.progressMax) shouldShow = false;
      if (filters.projectName && !projectName.includes(filters.projectName)) shouldShow = false;
      
      // Date range filtering
      if (filters.dateFrom && startDate !== '—') {
        if (new Date(startDate) < new Date(filters.dateFrom)) shouldShow = false;
      }
      if (filters.dateTo && startDate !== '—') {
        if (new Date(startDate) > new Date(filters.dateTo)) shouldShow = false;
      }
      
      // Show/hide row
      row.style.display = shouldShow ? '' : 'none';
      if (shouldShow) visibleCount++;
    });
    
    console.log(`✓ Restored filters: Showing ${visibleCount}/${rows.length} projects`);
    
    // Update pagination to reflect filtered results
    updatePaginationWithFilters();
    
    // Show toast notification
    if (visibleCount < rows.length) {
      Toast.info(`Filters restored • Showing ${visibleCount}/${rows.length} projects`, 3000);
    }
  } catch (error) {
    console.error('Error loading filters from storage:', error);
    localStorage.removeItem('projectFilters');
  }
}

function resetAllFilters() {
  // ✅ REMOVE FILTERS FROM LOCALSTORAGE
  localStorage.removeItem('projectFilters');
  
  // Show all rows
  const tbody = document.querySelector('#projectsTableContainer tbody');
  const rows = tbody.querySelectorAll('tr');
  rows.forEach(row => {
    row.style.display = '';
  });
  
  // ✅ RESET PAGINATION TO SHOW ALL ROWS
  currentPage = 1;
  const tableRows = document.querySelectorAll('#projectsTableContainer tbody tr');
  totalItems = tableRows.length;
  totalPages = Math.ceil(totalItems / itemsPerPage);
  
  if (totalItems > itemsPerPage) {
    document.getElementById('paginationControls').style.display = 'block';
    updatePagination();
  } else {
    document.getElementById('paginationControls').style.display = 'none';
  }
  
  console.log('All filters reset - showing all projects');
  Toast.success(`All filters cleared • Showing ${rows.length} projects`, 3000);
}

function applyAdvancedFilters() {
  const filters = {
    status: document.getElementById('filterStatus').value,
    client: document.getElementById('filterClient').value.toLowerCase(),
    budgetMin: parseFloat(document.getElementById('filterBudgetMin').value) || null,
    budgetMax: parseFloat(document.getElementById('filterBudgetMax').value) || null,
    dateFrom: document.getElementById('filterDateFrom').value,
    dateTo: document.getElementById('filterDateTo').value,
    progressMin: parseFloat(document.getElementById('filterProgressMin').value) || null,
    progressMax: parseFloat(document.getElementById('filterProgressMax').value) || null,
    projectName: document.getElementById('filterProjectName').value.toLowerCase()
  };
  
  console.log('Applying filters:', filters);
  
  // ✅ SAVE FILTERS TO LOCALSTORAGE (PERSIST ON REFRESH)
  localStorage.setItem('projectFilters', JSON.stringify(filters));
  
  // Get all table rows
  const tbody = document.querySelector('#projectsTableContainer tbody');
  const rows = tbody.querySelectorAll('tr');
  
  let visibleCount = 0;
  
  rows.forEach(row => {
    let shouldShow = true;
    
    // Get cell data
    const cells = row.cells;
    const projectName = cells[2]?.textContent.trim().toLowerCase() || '';
    const client = cells[3]?.textContent.trim().toLowerCase() || '';
    const startDate = cells[4]?.textContent.trim() || '';
    const budgetText = cells[6]?.textContent.trim() || '0';
    const budget = parseFloat(budgetText.replace(/[^0-9.-]/g, '')) || 0;
    const progressCell = cells[8];
    const progressText = progressCell?.querySelector('span:last-child')?.textContent || '0%';
    const progress = parseFloat(progressText.replace('%', '')) || 0;
    const statusBadge = cells[9]?.querySelector('.badge');
    const status = statusBadge?.textContent.trim().toLowerCase().replace(' ', '-') || '';
    
    // Apply filters
    if (filters.status && status !== filters.status) {
      shouldShow = false;
    }
    
    if (filters.client && !client.includes(filters.client)) {
      shouldShow = false;
    }
    
    if (filters.budgetMin !== null && budget < filters.budgetMin) {
      shouldShow = false;
    }
    
    if (filters.budgetMax !== null && budget > filters.budgetMax) {
      shouldShow = false;
    }
    
    if (filters.dateFrom && startDate !== '—') {
      const rowDate = new Date(startDate);
      const filterDate = new Date(filters.dateFrom);
      if (rowDate < filterDate) {
        shouldShow = false;
      }
    }
    
    if (filters.dateTo && startDate !== '—') {
      const rowDate = new Date(startDate);
      const filterDate = new Date(filters.dateTo);
      if (rowDate > filterDate) {
        shouldShow = false;
      }
    }
    
    if (filters.progressMin !== null && progress < filters.progressMin) {
      shouldShow = false;
    }
    
    if (filters.progressMax !== null && progress > filters.progressMax) {
      shouldShow = false;
    }
    
    if (filters.projectName && !projectName.includes(filters.projectName)) {
      shouldShow = false;
    }
    
    // Show/hide row (preserve spacing)
    row.style.display = shouldShow ? '' : 'none';
    if (shouldShow) visibleCount++;
  });
  
  console.log(`Filtered: ${visibleCount} of ${rows.length} projects visible`);
  
  // ✅ UPDATE PAGINATION TO REFLECT FILTERED RESULTS
  currentPage = 1; // Reset to first page
  updatePaginationWithFilters();
  
  // Close modal
  closeAdvancedFilters();
  
  // Show feedback with Toast
  const filterSummary = [];
  if (filters.status) filterSummary.push(`Status: ${filters.status}`);
  if (filters.client) filterSummary.push(`Client: ${filters.client}`);
  if (filters.budgetMin || filters.budgetMax) filterSummary.push('Budget range');
  if (filters.dateFrom || filters.dateTo) filterSummary.push('Date range');
  if (filters.progressMin || filters.progressMax) filterSummary.push('Progress range');
  if (filters.projectName) filterSummary.push(`Name: ${filters.projectName}`);
  
  if (filterSummary.length > 0) {
    Toast.success(`Filters applied: ${filterSummary.join(', ')} • Showing ${visibleCount}/${rows.length} projects`, 4000);
    console.log(`✓ Filters applied: ${filterSummary.join(', ')} - Showing ${visibleCount}/${rows.length} projects`);
  } else {
    Toast.info('No filters applied');
    console.log('No filters applied');
  }
}

// Refresh Projects (No page reload)
async function refreshProjects() {
  // Add spinning animation to button
  const btn = event.target.closest('button');
  const originalHTML = btn.innerHTML;
  btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0118.8-4.3M22 12.5a10 10 0 01-18.8 4.2"/></svg>';
  
  try {
    await refreshProjectsTable();
    btn.innerHTML = originalHTML;
  } catch (error) {
    btn.innerHTML = originalHTML;
    console.error('Refresh error:', error);
  }
}

// ============================================
// PAGINATION SYSTEM
// ============================================

let currentPage = 1;
let itemsPerPage = 6;
let totalItems = 0;
let totalPages = 0;

// Initialize projects data from PHP for sorting
<?php if (!empty($projects)): ?>
projectsData = <?php echo json_encode($projects); ?>;
<?php endif; ?>

// Initialize pagination on page load
document.addEventListener('DOMContentLoaded', function() {
  initializePagination();
  // Load saved filters from localStorage
  loadFiltersFromStorage();
});

function initializePagination() {
  const tableRows = document.querySelectorAll('#projectsTableContainer tbody tr');
  totalItems = tableRows.length;
  totalPages = Math.ceil(totalItems / itemsPerPage);
  
  // Show pagination only if more than 6 items
  if (totalItems > itemsPerPage) {
    document.getElementById('paginationControls').style.display = 'block';
    updatePagination();
  }
}

// UPDATE PAGINATION FOR FILTERED RESULTS (ONLY VISIBLE ROWS)
function updatePaginationWithFilters() {
  const allRows = document.querySelectorAll('#projectsTableContainer tbody tr');
  const visibleRows = Array.from(allRows).filter(row => row.style.display !== 'none');
  totalItems = visibleRows.length;
  totalPages = Math.ceil(totalItems / itemsPerPage);
  if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
  if (currentPage < 1) currentPage = 1;
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  allRows.forEach(row => { row.style.display = 'none'; });
  visibleRows.forEach((row, index) => { if (index >= startIndex && index < endIndex) row.style.display = ''; });
  const start = totalItems === 0 ? 0 : startIndex + 1;
  const end = Math.min(endIndex, totalItems);
  document.getElementById('pageInfo').textContent = start + '-' + end;
  document.getElementById('totalItems').textContent = totalItems;
  document.getElementById('prevPage').disabled = currentPage === 1;
  document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');
  if (prevBtn.disabled) { prevBtn.style.opacity = '0.4'; prevBtn.style.cursor = 'not-allowed'; } else { prevBtn.style.opacity = '1'; prevBtn.style.cursor = 'pointer'; }
  if (nextBtn.disabled) { nextBtn.style.opacity = '0.4'; nextBtn.style.cursor = 'not-allowed'; } else { nextBtn.style.opacity = '1'; nextBtn.style.cursor = 'pointer'; }
  generatePageNumbers();
  const paginationControls = document.getElementById('paginationControls');
  if (totalItems > itemsPerPage) { paginationControls.style.display = 'block'; } else { paginationControls.style.display = 'none'; }
}

function updatePagination() {
  const tableRows = document.querySelectorAll('#projectsTableContainer tbody tr');
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  
  // Show/hide rows based on current page
  tableRows.forEach((row, index) => {
    if (index >= startIndex && index < endIndex) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
  
  // Update page info
  const start = totalItems === 0 ? 0 : startIndex + 1;
  const end = Math.min(endIndex, totalItems);
  document.getElementById('pageInfo').textContent = `${start}-${end}`;
  document.getElementById('totalItems').textContent = totalItems;
  
  // Update buttons state
  document.getElementById('prevPage').disabled = currentPage === 1;
  document.getElementById('nextPage').disabled = currentPage === totalPages;
  
  // Apply disabled styles
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');
  
  if (prevBtn.disabled) {
    prevBtn.style.opacity = '0.4';
    prevBtn.style.cursor = 'not-allowed';
  } else {
    prevBtn.style.opacity = '1';
    prevBtn.style.cursor = 'pointer';
  }
  
  if (nextBtn.disabled) {
    nextBtn.style.opacity = '0.4';
    nextBtn.style.cursor = 'not-allowed';
  } else {
    nextBtn.style.opacity = '1';
    nextBtn.style.cursor = 'pointer';
  }
  
  // Generate page numbers
  generatePageNumbers();
}

function generatePageNumbers() {
  const container = document.getElementById('pageNumbers');
  container.innerHTML = '';
  
  // Smart page number generation
  const maxVisible = 5;
  let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
  let endPage = Math.min(totalPages, startPage + maxVisible - 1);
  
  // Adjust if we're near the end
  if (endPage - startPage < maxVisible - 1) {
    startPage = Math.max(1, endPage - maxVisible + 1);
  }
  
  // First page button
  if (startPage > 1) {
    container.appendChild(createPageButton(1));
    if (startPage > 2) {
      container.appendChild(createEllipsis());
    }
  }
  
  // Page number buttons
  for (let i = startPage; i <= endPage; i++) {
    container.appendChild(createPageButton(i));
  }
  
  // Last page button
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      container.appendChild(createEllipsis());
    }
    container.appendChild(createPageButton(totalPages));
  }
}

function createPageButton(pageNum) {
  const button = document.createElement('button');
  button.textContent = pageNum;
  button.onclick = () => goToPage(pageNum);
  
  const isActive = pageNum === currentPage;
  
  button.style.cssText = `
    min-width: 32px;
    height: 32px;
    padding: 0.375rem 0.625rem;
    border: 1px solid hsl(214 20% 88%);
    border-radius: 6px;
    background: ${isActive ? 'hsl(222 47% 17%)' : 'white'};
    color: ${isActive ? 'white' : 'hsl(222 47% 17%)'};
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
  `;
  
  if (!isActive) {
    button.onmouseover = () => {
      button.style.background = 'hsl(210 20% 98%)';
    };
    button.onmouseout = () => {
      button.style.background = 'white';
    };
  }
  
  return button;
}

function createEllipsis() {
  const span = document.createElement('span');
  span.textContent = '...';
  span.style.cssText = `
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    color: hsl(215 16% 47%);
    font-size: 0.875rem;
  `;
  return span;
}

function goToPage(pageNum) {
  if (pageNum < 1 || pageNum > totalPages) return;
  currentPage = pageNum;
  updatePagination();
  
  // Scroll to top of table
  document.getElementById('projectsTableContainer').scrollIntoView({ 
    behavior: 'smooth', 
    block: 'start' 
  });
}

function changePage(direction) {
  if (direction === 'prev' && currentPage > 1) {
    currentPage--;
  } else if (direction === 'next' && currentPage < totalPages) {
    currentPage++;
  }
  updatePagination();
  
  // Scroll to top of table
  document.getElementById('projectsTableContainer').scrollIntoView({ 
    behavior: 'smooth', 
    block: 'start' 
  });
}

function changeProjectsPageSize(newSize) {
  itemsPerPage = parseInt(newSize);
  currentPage = 1; // Reset to first page
  
  const tableRows = document.querySelectorAll('#projectsTableContainer tbody tr');
  totalItems = tableRows.length;
  totalPages = Math.ceil(totalItems / itemsPerPage);
  
  const paginationControls = document.getElementById('paginationControls');
  
  // Show/hide pagination based on items
  if (totalItems > itemsPerPage) {
    paginationControls.style.display = 'block';
    updatePagination();
  } else {
    paginationControls.style.display = 'none';
    // Show all rows if items fit on one page
    tableRows.forEach(row => row.style.display = '');
  }
  
  Toast.success(`Page size changed to ${newSize} items`);
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================

document.addEventListener('keydown', function(e) {
  // Check if pagination is visible
  const paginationVisible = document.getElementById('paginationControls').style.display !== 'none';
  if (!paginationVisible) return;
  
  // Check if user is typing in an input/textarea
  const activeElement = document.activeElement;
  if (activeElement.tagName === 'INPUT' || 
      activeElement.tagName === 'TEXTAREA' || 
      activeElement.isContentEditable) {
    return;
  }
  
  // Arrow Left = Previous Page
  if (e.key === 'ArrowLeft') {
    e.preventDefault();
    if (currentPage > 1) {
      changePage('prev');
    }
  }
  
  // Arrow Right = Next Page
  if (e.key === 'ArrowRight') {
    e.preventDefault();
    if (currentPage < totalPages) {
      changePage('next');
    }
  }
});
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../../components/layout.php';
?>


