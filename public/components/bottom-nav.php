<?php
/**
 * Bottom Navigation Bar
 * Mobile bottom navigation for quick access to key pages
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

// Get profile photo
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Controller\AuthController;
$authController = new AuthController();
$currentUser = $authController->getCurrentUser();
$profilePhoto = $currentUser['profile_photo'] ?? '';
?>

<!-- Bottom Navigation Bar (Mobile Only) -->
<nav class="bottom-nav" id="bottom-nav">
  <a href="dashboard" class="bottom-nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
    <svg class="bottom-nav-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
      <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
      <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
      <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
    </svg>
    <span class="bottom-nav-label">Home</span>
  </a>

  <a href="inventory-list" class="bottom-nav-item <?= $currentPage === 'inventory-list.php' ? 'active' : '' ?>">
    <svg class="bottom-nav-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
      <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
      <path d="M9 12H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span class="bottom-nav-label">Inventory</span>
  </a>

  <a href="add_item" class="bottom-nav-item bottom-nav-item-fab <?= $currentPage === 'add_item.php' ? 'active' : '' ?>">
    <div class="bottom-nav-fab">
      <svg class="bottom-nav-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 5V19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M5 12H19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>
    <span class="bottom-nav-label">Add</span>
  </a>

  <a href="notifications" class="bottom-nav-item <?= $currentPage === 'notifications.php' ? 'active' : '' ?>">
    <svg class="bottom-nav-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2"/>
      <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span class="bottom-nav-label">Alerts</span>
  </a>

  <a href="profile" class="bottom-nav-item <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
    <div class="bottom-nav-avatar">
      <?php if ($profilePhoto): ?>
        <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= htmlspecialchars($username) ?>">
      <?php else: ?>
        <div class="bottom-nav-avatar-initial"><?= $userInitial ?></div>
      <?php endif; ?>
    </div>
    <span class="bottom-nav-label">Profile</span>
  </a>
</nav>
