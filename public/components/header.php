<?php
/**
 * Header Component
 * Top navigation bar with search and user menu
 */

// Get user info from session
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

// Get profile photo from session or database
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Controller\AuthController;
$authController = new AuthController();
$currentUser = $authController->getCurrentUser();
$profilePhoto = $currentUser['profile_photo'] ?? '';
?>

<header class="app-header">
  <div class="header-left">
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-button" id="mobile-menu-button" aria-label="Open menu">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M3 12H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M3 6H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M3 18H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
    <h1 class="app-title hidden-mobile">
      <?php echo $pageTitle ?? 'Inventory Management'; ?>
    </h1>
  </div>

  <div class="header-center">
    <div class="search-wrapper">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input 
        type="search" 
        class="search-input" 
        placeholder="Search inventory..."
        id="global-search"
      >
    </div>
  </div>

  <div class="header-right">
    <!-- Notifications with Dropdown -->
    <div class="notification-menu dropdown">
      <button class="btn btn-ghost btn-icon hidden-mobile" id="notification-button" aria-label="Notifications" title="Notifications" style="position: relative;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <!-- Notification Badge -->
        <span id="notification-badge" style="position: absolute; top: 4px; right: 4px; background: var(--color-danger); color: white; border-radius: 999px; width: 18px; height: 18px; font-size: 10px; font-weight: 600; display: none; align-items: center; justify-content: center; border: 2px solid var(--bg-primary);">0</span>
      </button>

      <!-- Notification Dropdown -->
      <div class="dropdown-menu" id="notification-menu" role="menu" style="width: 400px; max-height: 600px; overflow-y: auto; z-index: 9999; padding: 0;">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--bg-primary);">
          <h3 style="font-weight: 600; font-size: 0.9375rem; margin: 0; letter-spacing: -0.01em;">Notifications</h3>
          <a href="notifications" style="font-size: 0.8125rem; color: var(--color-primary); text-decoration: none; font-weight: 500;">View All</a>
        </div>
        
        <!-- Notification Summary (will be populated by JavaScript) -->
        <div class="notification-summary"></div>
        
        <!-- Notification Items (will be populated by JavaScript) -->
        <div id="notification-items">
          <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="opacity: 0.3; margin: 0 auto 1rem;">
              <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <p style="font-size: 0.875rem;">Loading notifications...</p>
          </div>
        </div>
      </div>
    </div>

    <!-- User Menu -->
    <div class="user-menu dropdown">
      <button class="user-button" id="user-menu-button" aria-label="User menu">
        <div class="user-avatar" id="header-user-avatar">
          <?php if (!empty($profilePhoto)): ?>
            <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
          <?php else: ?>
            <span id="user-initial"><?php echo $userInitial; ?></span>
          <?php endif; ?>
        </div>
        <span class="hidden-mobile"><?php echo htmlspecialchars($username); ?></span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>

      <div class="dropdown-menu" id="user-menu" role="menu">
        <a href="profile" class="dropdown-item" role="menuitem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2"/>
            <path d="M20 21C20 16.5817 16.4183 13 12 13C7.58172 13 4 16.5817 4 21" stroke="currentColor" stroke-width="2"/>
          </svg>
          Profile
        </a>
        
        <a href="settings" class="dropdown-item" role="menuitem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            <path d="M12 1v6m0 6v6M1 12h6m6 0h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Settings
        </a>

        <div class="dropdown-divider"></div>

        <a href="logout" class="dropdown-item" role="menuitem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M16 17L21 12L16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Logout
        </a>
      </div>
    </div>
  </div>
</header>

