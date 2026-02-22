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

<aside class="sidebar" data-state="expanded" data-sidebar-root>
  <a href="dashboard" class="sidebar-header" style="text-decoration: none; color: inherit;">
    <svg class="sidebar-logo" width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
      <path d="M9 9H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M9 15H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span class="sidebar-title">Inventory System</span>
  </a>

  <nav class="sidebar-nav">
    <div class="sidebar-nav-top" data-sidebar-slot="top">
    <div class="sidebar-section" data-sidebar-section="main">
      <div class="sidebar-section-title">Main</div>
      <a href="dashboard" data-sidebar-key="dashboard" class="sidebar-link <?= $currentKey === 'dashboard' ? 'active' : '' ?>" style="<?= in_array('dashboard', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Dashboard</span>
      </a>

      <a href="analytics-dashboard" data-sidebar-key="analytics" class="sidebar-link <?= $currentKey === 'analytics-dashboard' ? 'active' : '' ?>" style="<?= in_array('analytics', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 17H7V12H9V17ZM13 17H11V7H13V17ZM17 17H15V14H17V17ZM19 19H5V5H19V19ZM19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Analytics</span>
      </a>

      <a href="inventory-list" data-sidebar-key="inventory-list" class="sidebar-link <?= $currentKey === 'inventory-list' ? 'active' : '' ?>" style="<?= in_array('inventory-list', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
          <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
          <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Inventory</span>
      </a>

      <a href="add_item" data-sidebar-key="add_item" class="sidebar-link <?= $currentKey === 'add_item' ? 'active' : '' ?>" style="<?= in_array('add_item', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
          <path d="M12 8V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M8 12H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Add Item</span>
      </a>
    </div>

    <?php
    $hasSalesVisible =
        !in_array('quotations', $sidebarHiddenItems, true) ||
        !in_array('invoicing', $sidebarHiddenItems, true) ||
        !in_array('orders', $sidebarHiddenItems, true) ||
        !in_array('projects', $sidebarHiddenItems, true) ||
        !in_array('shipping', $sidebarHiddenItems, true);
    ?>
    <div class="sidebar-section" data-sidebar-section="sales" style="<?= $hasSalesVisible ? '' : 'display: none;' ?>">
      <div class="sidebar-section-title">Sales & Operations</div>
      
      <a href="quotations" data-sidebar-key="quotations" class="sidebar-link <?= $currentKey === 'quotations' ? 'active' : '' ?>" style="<?= in_array('quotations', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Quotations</span>
      </a>

      <a href="invoicing" data-sidebar-key="invoicing" class="sidebar-link <?= $currentKey === 'invoicing' ? 'active' : '' ?>" style="<?= in_array('invoicing', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
          <line x1="8" y1="13" x2="16" y2="13" stroke="currentColor" stroke-width="2"/>
          <line x1="8" y1="17" x2="16" y2="17" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Invoicing</span>
      </a>

      <a href="orders" data-sidebar-key="orders" class="sidebar-link <?= $currentKey === 'orders' ? 'active' : '' ?>" style="<?= in_array('orders', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="currentColor" stroke-width="2"/>
          <path d="M16 7V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V7" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Orders</span>
      </a>

      <a href="projects" data-sidebar-key="projects" class="sidebar-link <?= $currentKey === 'projects' ? 'active' : '' ?>" style="<?= in_array('projects', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="2" y="7" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
          <path d="M16 21V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V21" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Projects</span>
      </a>

      <a href="shipping" data-sidebar-key="shipping" class="sidebar-link <?= $currentKey === 'shipping' ? 'active' : '' ?>" style="<?= in_array('shipping', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15" stroke="currentColor" stroke-width="2"/>
          <polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2"/>
          <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Shipping</span>
      </a>
    </div>

    <?php
    $hasComplianceVisible =
        !in_array('bir-compliance', $sidebarHiddenItems, true) ||
        !in_array('fda-compliance', $sidebarHiddenItems, true) ||
        !in_array('notifications', $sidebarHiddenItems, true);
    ?>
    <div class="sidebar-section" data-sidebar-section="compliance" style="<?= $hasComplianceVisible ? '' : 'display: none;' ?>">
      <div class="sidebar-section-title">Compliance</div>
      
      <a href="bir-compliance" data-sidebar-key="bir-compliance" class="sidebar-link <?= $currentKey === 'bir-compliance' ? 'active' : '' ?>" style="<?= in_array('bir-compliance', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
          <path d="M12 18V12M9 15H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">BIR Compliance</span>
      </a>

      <a href="fda-compliance" data-sidebar-key="fda-compliance" class="sidebar-link <?= $currentKey === 'fda-compliance' ? 'active' : '' ?>" style="<?= in_array('fda-compliance', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">FDA Compliance</span>
      </a>

      <a href="notifications" data-sidebar-key="notifications" class="sidebar-link <?= $currentKey === 'notifications' ? 'active' : '' ?>" style="<?= in_array('notifications', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2"/>
          <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Notifications</span>
      </a>
    </div>

    <?php
    $hasAccountingVisible =
        !in_array('chart-of-accounts', $sidebarHiddenItems, true) ||
        !in_array('journal-entries', $sidebarHiddenItems, true) ||
        !in_array('financial-reports', $sidebarHiddenItems, true);
    ?>
    <div class="sidebar-section" data-sidebar-section="accounting" style="<?= $hasAccountingVisible ? '' : 'display: none;' ?>">
      <div class="sidebar-section-title">Accounting</div>
      
      <a href="chart-of-accounts" data-sidebar-key="chart-of-accounts" class="sidebar-link <?= $currentKey === 'chart-of-accounts' ? 'active' : '' ?>" style="<?= in_array('chart-of-accounts', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
          <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
          <path d="M9 12H15M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Chart of Accounts</span>
      </a>

      <a href="journal-entries" data-sidebar-key="journal-entries" class="sidebar-link <?= $currentKey === 'journal-entries' ? 'active' : '' ?>" style="<?= in_array('journal-entries', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 20H21M3.00003 20H5.00003M5.00003 20V4M5.00003 20H12M5.00003 4H3.00003M5.00003 4H12M12 4H21M12 4V20M12 12H21M5 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Journal Entries</span>
      </a>

      <a href="financial-reports" data-sidebar-key="financial-reports" class="sidebar-link <?= $currentKey === 'financial-reports' ? 'active' : '' ?>" style="<?= in_array('financial-reports', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 17H7V12H9V17ZM13 17H11V7H13V17ZM17 17H15V14H17V17ZM19 19H5V5H19V19ZM19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" fill="currentColor"/>
        </svg>
        <span class="sidebar-link-text">Financial Reports</span>
      </a>
    </div>

    <?php
    $hasCollaborationVisible =
        !in_array('conversations', $sidebarHiddenItems, true) ||
        !in_array('system-alerts', $sidebarHiddenItems, true);
    ?>
    <div class="sidebar-section" data-sidebar-section="collaboration" style="<?= $hasCollaborationVisible ? '' : 'display: none;' ?>">
      <div class="sidebar-section-title">Collaboration</div>
      <a href="conversations" data-sidebar-key="conversations" class="sidebar-link <?= $currentKey === 'conversations' ? 'active' : '' ?>" style="<?= in_array('conversations', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4v8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="sidebar-link-text">Conversations</span>
      </a>

      <a href="system-alerts" data-sidebar-key="system-alerts" class="sidebar-link <?= $currentKey === 'system-alerts' ? 'active' : '' ?>" style="<?= in_array('system-alerts', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="sidebar-link-text">System Alerts</span>
      </a>
    </div>

    <?php
    $hasDocsVisible = !in_array('docs', $sidebarHiddenItems, true);
    ?>
    <div class="sidebar-section" data-sidebar-section="documentations" style="<?= $hasDocsVisible ? '' : 'display: none;' ?>">
      <div class="sidebar-section-title">Documentation</div>
      <a href="docs" data-sidebar-key="docs" class="sidebar-link <?= $currentKey === 'docs' ? 'active' : '' ?>" style="<?= in_array('docs', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="sidebar-link-text">Documentations</span>
      </a>
    </div>

    </div>

    <div class="sidebar-nav-bottom" data-sidebar-slot="bottom">
      <?php
      $hasSettingsVisible =
          !in_array('settings', $sidebarHiddenItems, true) ||
          !in_array('logout', $sidebarHiddenItems, true);
      ?>
      <div class="sidebar-section" data-sidebar-section="settings" style="<?= $hasSettingsVisible ? '' : 'display: none;' ?>">
        <div class="sidebar-section-title">Settings</div>
        
        <a href="settings" data-sidebar-key="settings" class="sidebar-link <?= $currentKey === 'settings' ? 'active' : '' ?>" style="<?= in_array('settings', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
          <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            <path d="M12 1v6m0 6v6M1 12h6m6 0h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span class="sidebar-link-text">Settings</span>
        </a>

        <a href="logout" data-sidebar-key="logout" class="sidebar-link" style="<?= in_array('logout', $sidebarHiddenItems, true) ? 'display: none;' : '' ?>">
          <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M16 17L21 12L16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span class="sidebar-link-text">Logout</span>
        </a>
      </div>
    </div>
  </nav>

  <button class="sidebar-toggle" aria-label="Toggle sidebar">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </button>
</aside>
