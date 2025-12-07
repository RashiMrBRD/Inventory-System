<?php
/**
 * Sidebar Component
 * Navigation sidebar with collapsible functionality
 */

require_once __DIR__ . '/../../vendor/autoload.php';
use App\Controller\AuthController;

// Initialize auth controller and load current user's sidebar preferences
$authController = new AuthController();
$currentUser = $authController->getCurrentUser();

$defaultHiddenSidebarItems = [
    'projects',
    'bir-compliance',
    'fda-compliance',
    'notifications',
    'chart-of-accounts',
    'journal-entries',
    'financial-reports',
    'conversations',
    'system-alerts',
];

$sidebarHiddenItems = $defaultHiddenSidebarItems;
if ($currentUser && isset($currentUser['sidebar_hidden_items']) && is_array($currentUser['sidebar_hidden_items'])) {
    $sidebarHiddenItems = $currentUser['sidebar_hidden_items'];
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$uriBase = basename($uriPath);
if ($uriBase === '' || $uriBase === 'index.php') {
    $uriBase = $currentPage;
}
$currentKey = preg_replace('/\.php$/i', '', $uriBase);
?>

<aside class="sidebar" data-state="expanded">
  <a href="dashboard" class="sidebar-header" style="text-decoration: none; color: inherit;">
    <svg class="sidebar-logo" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
      <path d="M9 9H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M9 15H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span class="sidebar-title">Inventory System</span>
  </a>

  <nav class="sidebar-nav">
    <div class="sidebar-nav-top">
    <div class="sidebar-section">
      <div class="sidebar-section-title">Main</div>
      <?php if (!in_array('dashboard', $sidebarHiddenItems, true)): ?>
      <a href="dashboard" class="sidebar-link <?= $currentKey === 'dashboard' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Dashboard</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('analytics', $sidebarHiddenItems, true)): ?>
      <a href="analytics-dashboard" class="sidebar-link <?= $currentKey === 'analytics-dashboard' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 17H7V12H9V17ZM13 17H11V7H13V17ZM17 17H15V14H17V17ZM19 19H5V5H19V19ZM19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Analytics</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('inventory-list', $sidebarHiddenItems, true)): ?>
      <a href="inventory-list" class="sidebar-link <?= $currentKey === 'inventory-list' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
          <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
          <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Inventory</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('add_item', $sidebarHiddenItems, true)): ?>
      <a href="add_item" class="sidebar-link <?= $currentKey === 'add_item' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
          <path d="M12 8V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M8 12H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Add Item</span>
      </a>
      <?php endif; ?>
    </div>

    <?php
    $hasSalesVisible =
        !in_array('quotations', $sidebarHiddenItems, true) ||
        !in_array('invoicing', $sidebarHiddenItems, true) ||
        !in_array('orders', $sidebarHiddenItems, true) ||
        !in_array('projects', $sidebarHiddenItems, true) ||
        !in_array('shipping', $sidebarHiddenItems, true);
    ?>
    <?php if ($hasSalesVisible): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-title">Sales & Operations</div>
      
      <?php if (!in_array('quotations', $sidebarHiddenItems, true)): ?>
      <a href="quotations" class="sidebar-link <?= $currentKey === 'quotations' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Quotations</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('invoicing', $sidebarHiddenItems, true)): ?>
      <a href="invoicing" class="sidebar-link <?= $currentKey === 'invoicing' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
          <line x1="8" y1="13" x2="16" y2="13" stroke="currentColor" stroke-width="2"/>
          <line x1="8" y1="17" x2="16" y2="17" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Invoicing</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('orders', $sidebarHiddenItems, true)): ?>
      <a href="orders" class="sidebar-link <?= $currentKey === 'orders' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="currentColor" stroke-width="2"/>
          <path d="M16 7V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V7" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Orders</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('projects', $sidebarHiddenItems, true)): ?>
      <a href="projects" class="sidebar-link <?= $currentKey === 'projects' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="2" y="7" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
          <path d="M16 21V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V21" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Projects</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('shipping', $sidebarHiddenItems, true)): ?>
      <a href="shipping" class="sidebar-link <?= $currentKey === 'shipping' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15" stroke="currentColor" stroke-width="2"/>
          <polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2"/>
          <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Shipping</span>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $hasComplianceVisible =
        !in_array('bir-compliance', $sidebarHiddenItems, true) ||
        !in_array('fda-compliance', $sidebarHiddenItems, true) ||
        !in_array('notifications', $sidebarHiddenItems, true);
    ?>
    <?php if ($hasComplianceVisible): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-title">Compliance</div>
      
      <?php if (!in_array('bir-compliance', $sidebarHiddenItems, true)): ?>
      <a href="bir-compliance" class="sidebar-link <?= $currentKey === 'bir-compliance' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
          <path d="M12 18V12M9 15H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">BIR Compliance</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('fda-compliance', $sidebarHiddenItems, true)): ?>
      <a href="fda-compliance" class="sidebar-link <?= $currentKey === 'fda-compliance' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">FDA Compliance</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('notifications', $sidebarHiddenItems, true)): ?>
      <a href="notifications" class="sidebar-link <?= $currentKey === 'notifications' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2"/>
          <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Notifications</span>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $hasAccountingVisible =
        !in_array('chart-of-accounts', $sidebarHiddenItems, true) ||
        !in_array('journal-entries', $sidebarHiddenItems, true) ||
        !in_array('financial-reports', $sidebarHiddenItems, true);
    ?>
    <?php if ($hasAccountingVisible): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-title">Accounting</div>
      
      <?php if (!in_array('chart-of-accounts', $sidebarHiddenItems, true)): ?>
      <a href="chart-of-accounts" class="sidebar-link <?= $currentKey === 'chart-of-accounts' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
          <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
          <path d="M9 12H15M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Chart of Accounts</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('journal-entries', $sidebarHiddenItems, true)): ?>
      <a href="journal-entries" class="sidebar-link <?= $currentKey === 'journal-entries' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 20H21M3.00003 20H5.00003M5.00003 20V4M5.00003 20H12M5.00003 4H3.00003M5.00003 4H12M12 4H21M12 4V20M12 12H21M5 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Journal Entries</span>
      </a>
      <?php endif; ?>

      <?php if (!in_array('financial-reports', $sidebarHiddenItems, true)): ?>
      <a href="financial-reports" class="sidebar-link <?= $currentKey === 'financial-reports' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 17H7V12H9V17ZM13 17H11V7H13V17ZM17 17H15V14H17V17ZM19 19H5V5H19V19ZM19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" fill="currentColor"/>
        </svg>
        <span class="sidebar-link-text">Financial Reports</span>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $hasCollaborationVisible =
        !in_array('conversations', $sidebarHiddenItems, true) ||
        !in_array('system-alerts', $sidebarHiddenItems, true);
    ?>
    <?php if ($hasCollaborationVisible): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-title">Collaboration</div>
      <?php if (!in_array('conversations', $sidebarHiddenItems, true)): ?>
      <a href="conversations" class="sidebar-link <?= $currentKey === 'conversations' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4v8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="sidebar-link-text">Conversations</span>
      </a>
      <?php endif; ?>
      <?php if (!in_array('system-alerts', $sidebarHiddenItems, true)): ?>
      <a href="system-alerts" class="sidebar-link <?= $currentKey === 'system-alerts' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="sidebar-link-text">System Alerts</span>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $hasDocsVisible = !in_array('docs', $sidebarHiddenItems, true);
    ?>
    <?php if ($hasDocsVisible): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-title">Documentation</div>
      <?php if (!in_array('docs', $sidebarHiddenItems, true)): ?>
      <a href="docs" class="sidebar-link <?= $currentKey === 'docs' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="sidebar-link-text">Documentations</span>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    </div>

    <div class="sidebar-nav-bottom">
      <?php
      $hasSettingsVisible =
          !in_array('settings', $sidebarHiddenItems, true) ||
          !in_array('logout', $sidebarHiddenItems, true);
      ?>
      <?php if ($hasSettingsVisible): ?>
      <div class="sidebar-section">
        <div class="sidebar-section-title">Settings</div>
        
        <?php if (!in_array('settings', $sidebarHiddenItems, true)): ?>
        <a href="settings" class="sidebar-link <?= $currentKey === 'settings' ? 'active' : '' ?>">
          <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            <path d="M12 1v6m0 6v6M1 12h6m6 0h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span class="sidebar-link-text">Settings</span>
        </a>
        <?php endif; ?>

        <?php if (!in_array('logout', $sidebarHiddenItems, true)): ?>
        <a href="logout" class="sidebar-link">
          <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M16 17L21 12L16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span class="sidebar-link-text">Logout</span>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </nav>

  <button class="sidebar-toggle" aria-label="Toggle sidebar">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </button>
</aside>
