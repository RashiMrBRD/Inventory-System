<?php
/**
 * Sidebar Component
 * Navigation sidebar with collapsible functionality
 */

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar" data-state="expanded">
  <a href="dashboard.php" class="sidebar-header" style="text-decoration: none; color: inherit;">
    <svg class="sidebar-logo" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
      <path d="M9 9H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M9 15H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span class="sidebar-title">Inventory System</span>
  </a>

  <nav class="sidebar-nav">
    <div class="sidebar-section">
      <a href="dashboard.php" class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Dashboard</span>
      </a>

      <a href="analytics-dashboard.php" class="sidebar-link <?= $currentPage === 'analytics-dashboard.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 17H7V12H9V17ZM13 17H11V7H13V17ZM17 17H15V14H17V17ZM19 19H5V5H19V19ZM19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Analytics</span>
      </a>

      <a href="inventory-list.php" class="sidebar-link <?= ($currentPage === 'inventory-list.php') ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
          <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
          <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Inventory</span>
      </a>

      <a href="add_item.php" class="sidebar-link <?= $currentPage === 'add_item.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
          <path d="M12 8V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M8 12H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Add Item</span>
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-title">Sales & Operations</div>
      
      <a href="quotations.php" class="sidebar-link <?= $currentPage === 'quotations.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Quotations</span>
      </a>

      <a href="invoicing.php" class="sidebar-link <?= $currentPage === 'invoicing.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
          <line x1="8" y1="13" x2="16" y2="13" stroke="currentColor" stroke-width="2"/>
          <line x1="8" y1="17" x2="16" y2="17" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Invoicing</span>
      </a>

      <a href="orders.php" class="sidebar-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="currentColor" stroke-width="2"/>
          <path d="M16 7V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V7" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Orders</span>
      </a>

      <a href="projects.php" class="sidebar-link <?= $currentPage === 'projects.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="2" y="7" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
          <path d="M16 21V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V21" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Projects</span>
      </a>

      <a href="shipping.php" class="sidebar-link <?= $currentPage === 'shipping.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15" stroke="currentColor" stroke-width="2"/>
          <polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2"/>
          <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">Shipping</span>
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-title">Compliance</div>
      
      <a href="bir-compliance.php" class="sidebar-link <?= $currentPage === 'bir-compliance.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
          <path d="M12 18V12M9 15H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">BIR Compliance</span>
      </a>

      <a href="fda-compliance.php" class="sidebar-link <?= $currentPage === 'fda-compliance.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="sidebar-link-text">FDA Compliance</span>
      </a>

      <a href="notifications.php" class="sidebar-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2"/>
          <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Notifications</span>
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-title">Accounting</div>
      
      <a href="chart-of-accounts.php" class="sidebar-link <?= $currentPage === 'chart-of-accounts.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
          <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
          <path d="M9 12H15M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Chart of Accounts</span>
      </a>

      <a href="journal-entries.php" class="sidebar-link <?= $currentPage === 'journal-entries.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 20H21M3.00003 20H5.00003M5.00003 20V4M5.00003 20H12M5.00003 4H3.00003M5.00003 4H12M12 4H21M12 4V20M12 12H21M5 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Journal Entries</span>
      </a>

      <a href="financial-reports.php" class="sidebar-link <?= $currentPage === 'financial-reports.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 17H7V12H9V17ZM13 17H11V7H13V17ZM17 17H15V14H17V17ZM19 19H5V5H19V19ZM19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" fill="currentColor"/>
        </svg>
        <span class="sidebar-link-text">Financial Reports</span>
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-title">Settings</div>
      
      <a href="settings.php" class="sidebar-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
          <path d="M12 1v6m0 6v6M1 12h6m6 0h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Settings</span>
      </a>

      <a href="logout.php" class="sidebar-link">
        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M16 17L21 12L16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-link-text">Logout</span>
      </a>
    </div>
  </nav>

  <button class="sidebar-toggle" aria-label="Toggle sidebar">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </button>
</aside>
