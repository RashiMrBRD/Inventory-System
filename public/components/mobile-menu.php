<?php
/**
 * Mobile Menu Component
 * Beautiful slide-in navigation for mobile devices
 * Shadcn-inspired design
 */

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Get user info
$rawFullName = isset($_SESSION['full_name']) ? trim((string)$_SESSION['full_name']) : '';
$rawUsername = isset($_SESSION['username']) ? trim((string)$_SESSION['username']) : '';

if ($rawFullName !== '') {
    $username = $rawFullName;
} elseif ($rawUsername !== '') {
    $username = $rawUsername;
} else {
    $username = 'User';
}

$userInitial = strtoupper(substr($username, 0, 1));
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';

// Get profile photo
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Controller\AuthController;
$authController = new AuthController();
$currentUser = $authController->getCurrentUser();
$profilePhoto = $currentUser['profile_photo'] ?? '';
?>

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay" id="mobile-menu-overlay"></div>

<!-- Mobile Menu -->
<nav class="mobile-menu" id="mobile-menu">
  <!-- Mobile Menu Header -->
  <div class="mobile-menu-header">
    <a href="dashboard" class="mobile-menu-logo">
      <svg class="mobile-menu-logo-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
        <path d="M9 9H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M9 15H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <span>Inventory System</span>
    </a>
    <button class="mobile-menu-close" id="mobile-menu-close" aria-label="Close menu">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </div>

  <!-- Mobile Menu Content -->
  <div class="mobile-menu-content">
    <!-- Main Section -->
    <div class="mobile-menu-section">
      <a href="dashboard" class="mobile-menu-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="mobile-menu-link-text">Dashboard</span>
      </a>

      <a href="analytics-dashboard" class="mobile-menu-link <?= $currentPage === 'analytics-dashboard.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 17H7V12H9V17ZM13 17H11V7H13V17ZM17 17H15V14H17V17ZM19 19H5V5H19V19ZM19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="mobile-menu-link-text">Analytics</span>
      </a>

      <a href="inventory-list" class="mobile-menu-link <?= $currentPage === 'inventory-list.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
          <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
          <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="mobile-menu-link-text">Inventory</span>
      </a>

      <a href="add_item" class="mobile-menu-link <?= $currentPage === 'add_item.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
          <path d="M12 8V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M8 12H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="mobile-menu-link-text">Add Item</span>
      </a>
    </div>

    <!-- Sales & Operations Section -->
    <div class="mobile-menu-section">
      <div class="mobile-menu-section-title">Sales & Operations</div>
      
      <a href="quotations" class="mobile-menu-link <?= $currentPage === 'quotations.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="mobile-menu-link-text">Quotations</span>
      </a>

      <a href="invoicing" class="mobile-menu-link <?= $currentPage === 'invoicing.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
          <line x1="8" y1="13" x2="16" y2="13" stroke="currentColor" stroke-width="2"/>
          <line x1="8" y1="17" x2="16" y2="17" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="mobile-menu-link-text">Invoicing</span>
      </a>

      <a href="orders" class="mobile-menu-link <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="currentColor" stroke-width="2"/>
          <path d="M16 7V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V7" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="mobile-menu-link-text">Orders</span>
      </a>

      <a href="projects" class="mobile-menu-link <?= $currentPage === 'projects.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="2" y="7" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
          <path d="M16 21V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V21" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="mobile-menu-link-text">Projects</span>
      </a>

      <a href="shipping" class="mobile-menu-link <?= $currentPage === 'shipping.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15" stroke="currentColor" stroke-width="2"/>
          <polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2"/>
          <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="mobile-menu-link-text">Shipping</span>
      </a>
    </div>

    <!-- Compliance Section -->
    <div class="mobile-menu-section">
      <div class="mobile-menu-section-title">Compliance</div>
      
      <a href="bir-compliance" class="mobile-menu-link <?= $currentPage === 'bir-compliance.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
          <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="2"/>
          <path d="M12 18V12M9 15H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="mobile-menu-link-text">BIR Compliance</span>
      </a>

      <a href="fda-compliance" class="mobile-menu-link <?= $currentPage === 'fda-compliance.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="mobile-menu-link-text">FDA Compliance</span>
      </a>

      <a href="notifications" class="mobile-menu-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2"/>
          <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="mobile-menu-link-text">Notifications</span>
      </a>
    </div>

    <!-- Accounting Section -->
    <div class="mobile-menu-section">
      <div class="mobile-menu-section-title">Accounting</div>
      
      <a href="chart-of-accounts" class="mobile-menu-link <?= $currentPage === 'chart-of-accounts.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
          <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
          <path d="M9 12H15M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="mobile-menu-link-text">Chart of Accounts</span>
      </a>

      <a href="journal-entries" class="mobile-menu-link <?= $currentPage === 'journal-entries.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 20H21M3.00003 20H5.00003M5.00003 20V4M5.00003 20H12M5.00003 4H3.00003M5.00003 4H12M12 4H21M12 4V20M12 12H21M5 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="mobile-menu-link-text">Journal Entries</span>
      </a>

      <a href="financial-reports" class="mobile-menu-link <?= $currentPage === 'financial-reports.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 17H7V12H9V17ZM13 17H11V7H13V17ZM17 17H15V14H17V17ZM19 19H5V5H19V19ZM19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z" fill="currentColor"/>
        </svg>
        <span class="mobile-menu-link-text">Financial Reports</span>
      </a>
    </div>

    <!-- Collaboration Section -->
    <div class="mobile-menu-section">
      <div class="mobile-menu-section-title">Collaboration</div>
      
      <a href="conversations" class="mobile-menu-link <?= $currentPage === 'conversations.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4v8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="mobile-menu-link-text">Conversations</span>
      </a>

      <a href="system-alerts" class="mobile-menu-link <?= $currentPage === 'system-alerts.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="mobile-menu-link-text">System Alerts</span>
      </a>
    </div>

    <!-- Documentation Section -->
    <div class="mobile-menu-section">
      <div class="mobile-menu-section-title">Documentation</div>
      
      <a href="docs" class="mobile-menu-link <?= $currentPage === 'docs.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="mobile-menu-link-text">Documentations</span>
      </a>
    </div>

    <!-- Settings Section -->
    <div class="mobile-menu-section">
      <div class="mobile-menu-section-title">Settings</div>
      
      <a href="profile" class="mobile-menu-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span class="mobile-menu-link-text">Profile</span>
      </a>

      <a href="settings" class="mobile-menu-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
          <path d="M12 1v6m0 6v6M1 12h6m6 0h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="mobile-menu-link-text">Settings</span>
      </a>

      <a href="logout" class="mobile-menu-link">
        <svg class="mobile-menu-link-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M9 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M16 17L21 12L16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="mobile-menu-link-text">Logout</span>
      </a>
    </div>
  </div>

</nav>
