<?php
/**
 * Notifications Center
 * Centralized alert and notification management
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Helper\CurrencyHelper;
use App\Service\NotificationService;
use App\Database\NotificationRepository;

$authController = new AuthController();
$authController->requireLogin();
$user = $authController->getCurrentUser();

// Check SMTP configuration
$appConfig = require __DIR__ . '/../../../config/app.php';
$smtpConfigured = !empty($appConfig['mail']['host']) && !empty($appConfig['mail']['username']);

// Initialize repository with user ID
$userId = isset($user['_id']) ? (string)$user['_id'] : null;
if (!$userId) {
    // Fallback to session user_id
    $userId = $_SESSION['user_id'] ?? null;
}

if (!$userId) {
    die("User not authenticated");
}

$repo = new NotificationRepository($userId);

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 10;
$skip = ($page - 1) * $itemsPerPage;

// Get ALL notifications (no limit for total count)
$allNotifications = $repo->getAll([
    'dismissed' => 'false',
    'deleted' => 'false',
    'limit' => 10000,  // Get all for counting
    'skip' => 0
]);

// Get paginated notifications for display
$notifications = $repo->getAll([
    'dismissed' => 'false',
    'deleted' => 'false',
    'limit' => $itemsPerPage,
    'skip' => $skip
]);

// Get trash pagination
$trashPage = isset($_GET['trash_page']) ? max(1, (int)$_GET['trash_page']) : 1;
$trashSkip = ($trashPage - 1) * $itemsPerPage;

// Get all trash for counting
$allTrash = $repo->getTrash();

// Get paginated trash for display
$trash = array_slice($allTrash, $trashSkip, $itemsPerPage);

// Calculate pagination info
$totalNotifications = count($allNotifications);
$totalPages = ceil($totalNotifications / $itemsPerPage);
$totalTrash = count($allTrash);
$totalTrashPages = ceil($totalTrash / $itemsPerPage);

// Sort notifications by priority and date for consistent display
// Priority order: high > medium > normal
// Within same priority: newest first
usort($notifications, function($a, $b) {
    $priorityOrder = ['high' => 3, 'medium' => 2, 'normal' => 1];
    $priorityA = $priorityOrder[$a['priority']] ?? 0;
    $priorityB = $priorityOrder[$b['priority']] ?? 0;
    
    // First sort by priority (high first)
    if ($priorityA !== $priorityB) {
        return $priorityB - $priorityA;  // Higher priority first
    }

    // Then sort by date (newest first)
    $timeA = strtotime($a['created_at']);
    $timeB = strtotime($b['created_at']);
    return $timeB - $timeA;  // Newer first
});

$pageTitle = 'Notifications';

// Calculate notification counts consistently via repository (authoritative)
$unreadCount = $repo->getUnreadCount();
$highPriorityCount = $repo->countBy(['priority' => 'high', 'dismissed' => 'false', 'deleted' => 'false']);
$expiryCount = $repo->countBy(['type' => 'expiry', 'dismissed' => 'false', 'deleted' => 'false']);
$financialCount = $repo->countBy(['type' => 'financial', 'dismissed' => 'false', 'deleted' => 'false']);
// Compliance = BIR + FDA
$complianceCount = $repo->countBy(['type' => 'bir', 'dismissed' => 'false', 'deleted' => 'false'])
                + $repo->countBy(['type' => 'fda', 'dismissed' => 'false', 'deleted' => 'false']);
// Today count derived from current all-notifications list (approximation is fine)
$todayCount = count(array_filter($allNotifications, fn($n) => strtotime($n['created_at']) > strtotime('-1 day')));
$totalCount = $totalNotifications;  // Use total from pagination
$mediumPriorityCount = $repo->countBy(['priority' => 'medium', 'dismissed' => 'false', 'deleted' => 'false']);

ob_start();
?>

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Notifications Center</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>Unread:</strong>
          <span class="badge badge-danger"><?php echo $unreadCount; ?></span>
        </div>
        <div class="page-banner-meta-item">
          <strong>High Priority:</strong>
          <span class="text-danger font-medium"><?php echo $highPriorityCount; ?> alerts</span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Last Updated:</strong>
          <span class="text-secondary">Just now</span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <button class="btn btn-secondary" onclick="markAllAsRead()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Mark All Read
      </button>
      <button class="btn btn-secondary" onclick="openSettings()" style="cursor: pointer;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
          <path d="M12 1V3M12 21V23M4.22 4.22L5.64 5.64M18.36 18.36L19.78 19.78M1 12H3M21 12H23M4.22 19.78L5.64 18.36M18.36 5.64L19.78 4.22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Settings
      </button>
      <a href=\"/logout" class="btn btn-danger">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9M16 17L21 12M21 12L16 7M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Logout
      </a>
    </div>
  </div>
</div>

<!-- Alert Stats -->
<div class="section">
  <div class="grid grid-cols-4 mb-6" style="gap: 1.5rem;">
    
    <!-- High Priority -->
    <div class="card">
      <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
          <span class="badge badge-danger">High</span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ef4444">
            <circle cx="12" cy="12" r="10" stroke-width="2"/>
            <path d="M12 8V12M12 16H12.01" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <h3 class="text-3xl font-bold" id="stat-high-priority" style="margin-bottom: 0.25rem;"><?php echo $highPriorityCount; ?></h3>
        <p class="text-sm text-secondary">High Priority</p>
        <?php 
          $firstHigh = array_values(array_filter($notifications, fn($n) => $n['priority'] === 'high'))[0] ?? null;
          if ($firstHigh): 
        ?>
        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars(substr($firstHigh['title'], 0, 30)); ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Medium Priority -->
    <div class="card">
      <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
          <span class="badge badge-warning">Medium</span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b">
            <path d="M12 8V12L15 15" stroke-width="2" stroke-linecap="round"/>
            <circle cx="12" cy="12" r="10" stroke-width="2"/>
          </svg>
        </div>
        <h3 class="text-3xl font-bold" id="stat-medium-priority" style="margin-bottom: 0.25rem;"><?php echo $mediumPriorityCount; ?></h3>
        <p class="text-sm text-secondary">Medium Priority</p>
      </div>
    </div>

    <!-- Today's Alerts -->
    <div class="card">
      <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
          <span class="badge badge-default">Today</span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3b82f6">
            <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/>
            <path d="M16 2V6M8 2V6M3 10H21" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <?php 
          $todayHighMedium = count(array_filter($allNotifications, fn($n) => 
            strtotime($n['created_at']) > strtotime('-1 day') && 
            in_array($n['priority'], ['high', 'medium'])
          ));
        ?>
        <h3 class="text-3xl font-bold" id="stat-today-highmed" style="margin-bottom: 0.25rem;"><?php echo $todayHighMedium; ?></h3>
        <p class="text-sm text-secondary">Today's High/Med Alerts</p>
        <?php 
          $firstToday = array_values(array_filter($notifications, fn($n) => 
            strtotime($n['created_at']) > strtotime('-1 day') && 
            in_array($n['priority'], ['high', 'medium'])
          ))[0] ?? null;
          if ($firstToday): 
        ?>
        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars(substr($firstToday['title'], 0, 30)); ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Total This Week -->
    <div class="card">
      <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
          <span class="badge badge-success">Week</span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e">
            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke-width="2" stroke-linejoin="round"/>
            <path d="M2 17L12 22L22 17M2 12L12 17L22 12" stroke-width="2" stroke-linejoin="round"/>
          </svg>
        </div>
        <h3 class="text-3xl font-bold" id="stat-total" style="margin-bottom: 0.25rem;"><?php echo $totalCount; ?></h3>
        <p class="text-sm text-secondary">Total Notifications</p>
      </div>
    </div>
  </div>
</div>

<!-- Filter Tabs -->
<div class="section">
  <h2 class="section-title">All Notifications</h2>
  
  <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0;">
    <button class="btn btn-ghost filter-btn" data-filter="all" style="border-bottom: 2px solid var(--color-primary); border-radius: 0; margin-bottom: -2px;">
      All (<?php echo $totalCount; ?>)
    </button>
    <button class="btn btn-ghost filter-btn" data-filter="unread" style="border-radius: 0;">
      Unread (<?php echo $unreadCount; ?>)
    </button>
    <button class="btn btn-ghost filter-btn" data-filter="high-priority" style="border-radius: 0;">
      High Priority (<?php echo $highPriorityCount; ?>)
    </button>
    <button class="btn btn-ghost filter-btn" data-filter="expiry" style="border-radius: 0;">
      Expiry (<?php echo $expiryCount; ?>)
    </button>
    <button class="btn btn-ghost filter-btn" data-filter="financial" style="border-radius: 0;">
      Financial (<?php echo $financialCount; ?>)
    </button>
    <button class="btn btn-ghost filter-btn" data-filter="compliance" style="border-radius: 0;">
      Compliance (<?php echo $complianceCount; ?>)
    </button>
  </div>

  <!-- Notifications List -->
  <div style="display: flex; flex-direction: column; gap: 0.75rem;">
    <?php if (empty($notifications)): ?>
      <!-- Empty State -->
      <div class="card" style="padding: 4rem 2rem; text-align: center; background: white;">
        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="hsl(215 16% 47%)" style="opacity: 0.15; margin: 0 auto 1.5rem; stroke-width: 1.5;">
          <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h3 style="font-size: 1.25rem; font-weight: 600; color: hsl(0 0% 12%); margin: 0 0 0.75rem 0;">No notifications yet</h3>
        <p style="font-size: 0.9375rem; color: hsl(215 16% 47%); margin: 0 auto; max-width: 28rem; line-height: 1.6;">
          When you receive notifications about inventory, compliance, or financial updates, they'll appear here.
        </p>
      </div>
    <?php else: ?>
    <?php foreach ($notifications as $index => $notif): ?>
    <?php
    // Get styling per notification type
    $bgColor = match($notif['type']) {
        'expiry' => !$notif['read'] ? 'rgba(239, 68, 68, 0.08)' : 'transparent',
        'bir' => !$notif['read'] ? 'rgba(59, 130, 246, 0.08)' : 'transparent',
        'fda' => !$notif['read'] ? 'rgba(34, 197, 94, 0.08)' : 'transparent',
        'inventory' => !$notif['read'] ? 'rgba(245, 158, 11, 0.08)' : 'transparent',
        'financial' => !$notif['read'] ? 'rgba(239, 68, 68, 0.08)' : 'transparent',
        'success' => !$notif['read'] ? 'rgba(34, 197, 94, 0.08)' : 'transparent',
        default => 'transparent'
    };
    $borderColor = !$notif['read'] ? 'rgba(59, 130, 246, 0.2)' : 'var(--border-color)';
    ?>
    <div class="card" data-notification-id="<?php echo htmlspecialchars($notif['id']); ?>" data-notification-type="<?php echo htmlspecialchars($notif['type']); ?>" data-notification-priority="<?php echo htmlspecialchars($notif['priority']); ?>" data-notification-read="<?php echo $notif['read'] ? 'true' : 'false'; ?>" data-notification-created-at="<?php echo htmlspecialchars($notif['created_at']); ?>" style="background: <?php echo $bgColor; ?>; border: 1px solid <?php echo $borderColor; ?>; border-radius: var(--radius-md); transition: all 0.2s ease;">
      <div class="card-content" style="padding: 1rem;">
        <div style="display: flex; gap: 0.875rem; align-items: start;">
          <!-- Icon -->
          <?php
          $iconColor = match($notif['type']) {
              'expiry' => '#ef4444',
              'bir' => '#3b82f6',
              'fda' => '#22c55e',
              'inventory' => '#f59e0b',
              'financial' => '#ef4444',
              'success' => '#22c55e',
              default => '#6b7280'
          };
          ?>
          <div style="flex-shrink: 0; margin-top: 2px;">
            <?php if ($notif['type'] === 'expiry'): ?>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo $iconColor; ?>" style="stroke-width: 2;">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 6V12L16 14" stroke-linecap="round"/>
              </svg>
            <?php elseif ($notif['type'] === 'bir' || $notif['type'] === 'fda'): ?>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo $iconColor; ?>" style="stroke-width: 2;">
                <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z"/>
                <polyline points="14 2 14 8 20 8"/>
              </svg>
            <?php elseif ($notif['type'] === 'inventory'): ?>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo $iconColor; ?>" style="stroke-width: 2;">
                <path d="M21 16V8C21 6.89543 20.1046 6 19 6H5C3.89543 6 3 6.89543 3 8V16C3 17.1046 3.89543 18 5 18H19C20.1046 18 21 17.1046 21 16Z"/>
                <path d="M3 10H21"/>
              </svg>
            <?php elseif ($notif['type'] === 'financial'): ?>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo $iconColor; ?>" style="stroke-width: 2;">
                <path d="M12 2V22M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke-linecap="round"/>
              </svg>
            <?php else: ?>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo $iconColor; ?>" style="stroke-width: 2;">
                <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke-linecap="round"/>
                <path d="M22 4L12 14.01L9 11.01" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            <?php endif; ?>
          </div>

          <!-- Content -->
          <div style="flex: 1; min-width: 0;">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem; margin-bottom: 0.5rem;">
              <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.375rem;">
                  <h4 class="font-semibold" style="margin: 0; font-size: 0.9375rem; line-height: 1.2;"><?php echo htmlspecialchars($notif['title']); ?></h4>
                  <?php if (!$notif['read']): ?>
                    <span style="width: 6px; height: 6px; background: var(--color-primary); border-radius: 50%; flex-shrink: 0;"></span>
                  <?php endif; ?>
                </div>
                <p class="text-sm text-secondary" style="margin: 0; line-height: 1.5;"><?php echo htmlspecialchars($notif['message']); ?></p>
              </div>
              <div style="flex-shrink: 0;">
                <?php
                $badgeStyle = match($notif['priority']) {
                    'high' => 'background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);',
                    'medium' => 'background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);',
                    default => 'background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color);'
                };
                ?>
                <span style="display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 600; <?php echo $badgeStyle; ?>"><?php echo strtoupper($notif['priority']); ?></span>
              </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem;">
              <span class="text-xs text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($notif['time']); ?></span>
              <div style="display: flex; gap: 0.375rem;">
                <?php if (!$notif['read']): ?>
                  <button class="btn btn-ghost btn-sm" onclick="markNotificationAsRead(this)" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.25rem; stroke-width: 2;">
                      <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke-linecap="round"/>
                      <path d="M22 4L12 14.01L9 11.01" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Mark Read
                  </button>
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm" onclick="viewNotification(this)" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.25rem; stroke-width: 2;">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  View
                </button>
                <button class="btn btn-ghost btn-sm" onclick="deleteNotification(this)" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem; color: var(--color-danger);">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.25rem; stroke-width: 2;">
                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Delete
                </button>
                <button class="btn btn-ghost btn-sm" onclick="dismissNotificationPage(this)" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.25rem; stroke-width: 2;">
                    <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Dismiss
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
  
  <!-- Pagination Controls -->
  <?php if ($totalPages > 1): ?>
  <?php
    // Calculate page range to show (max 7 numbers)
    $maxVisible = 7;
    $startPage = max(1, $page - floor($maxVisible / 2));
    $endPage = min($totalPages, $startPage + $maxVisible - 1);
    
    // Adjust start if end is at max
    if ($endPage - $startPage + 1 < $maxVisible) {
      $startPage = max(1, $endPage - $maxVisible + 1);
    }
  ?>
  <div id="main-pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); flex-wrap: wrap;">
    <!-- Previous Button -->
    <?php if ($page > 1): ?>
      <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
          <path d="M15 19l-7-7 7-7"/>
        </svg>
        Previous
      </a>
    <?php else: ?>
      <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
          <path d="M15 19l-7-7 7-7"/>
        </svg>
        Previous
      </span>
    <?php endif; ?>
    
    <!-- First page + ellipsis if needed -->
    <?php if ($startPage > 1): ?>
      <a href="?page=1" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">1</a>
      <?php if ($startPage > 2): ?>
        <span style="padding: 0.5rem; color: var(--text-muted);">...</span>
      <?php endif; ?>
    <?php endif; ?>
    
    <!-- Page Numbers -->
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600;"><?php echo $i; ?></span>
      <?php else: ?>
        <a href="?page=<?php echo $i; ?>" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;"><?php echo $i; ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    
    <!-- Ellipsis + last page if needed -->
    <?php if ($endPage < $totalPages): ?>
      <?php if ($endPage < $totalPages - 1): ?>
        <span style="padding: 0.5rem; color: var(--text-muted);">...</span>
      <?php endif; ?>
      <a href="?page=<?php echo $totalPages; ?>" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;"><?php echo $totalPages; ?></a>
    <?php endif; ?>
    
    <!-- Next Button -->
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
          <path d="M9 5l7 7-7 7"/>
        </svg>
      </a>
    <?php else: ?>
      <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
          <path d="M9 5l7 7-7 7"/>
        </svg>
      </span>
    <?php endif; ?>
  </div>
  
  <!-- Page Info -->
  <div id="main-page-info" style="text-align: center; margin-top: 1rem;">
    <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary);">
      Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong>
    </p>
    <p style="margin: 0.25rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);">
      Showing <?php echo count($notifications); ?> of <?php echo $totalNotifications; ?> notifications
    </p>
  </div>
  <?php endif; ?>
</div>

<!-- Trash Section -->
<div class="section" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2 class="section-title">Trash</h2>
    <button class="btn btn-ghost" onclick="emptyTrash()" style="font-size: 0.875rem; color: var(--color-danger);">
      Empty Trash
    </button>
  </div>
  
  <div id="trash-container" style="display: flex; flex-direction: column; gap: 0.75rem;">
    <div style="padding: 2rem; text-align: center; color: var(--text-secondary); background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="opacity: 0.3; margin: 0 auto 1rem; stroke: currentColor;">
        <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <p style="font-size: 0.875rem; font-weight: 500;">Trash is empty</p>
      <p style="font-size: 0.75rem; margin-top: 0.25rem; color: var(--text-muted);">Dismissed notifications appear here</p>
    </div>
  </div>
  
  <!-- Trash Pagination Controls -->
  <?php if ($totalTrashPages > 1): ?>
  <?php
    // Calculate page range to show (max 7 numbers)
    $maxVisibleTrash = 7;
    $startTrashPage = max(1, $trashPage - floor($maxVisibleTrash / 2));
    $endTrashPage = min($totalTrashPages, $startTrashPage + $maxVisibleTrash - 1);
    
    // Adjust start if end is at max
    if ($endTrashPage - $startTrashPage + 1 < $maxVisibleTrash) {
      $startTrashPage = max(1, $endTrashPage - $maxVisibleTrash + 1);
    }
  ?>
  <div id="trash-pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); flex-wrap: wrap;">
    <!-- Previous Button -->
    <?php if ($trashPage > 1): ?>
      <a href="?trash_page=<?php echo $trashPage - 1; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
          <path d="M15 19l-7-7 7-7"/>
        </svg>
        Previous
      </a>
    <?php else: ?>
      <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
          <path d="M15 19l-7-7 7-7"/>
        </svg>
        Previous
      </span>
    <?php endif; ?>
    
    <!-- First page + ellipsis if needed -->
    <?php if ($startTrashPage > 1): ?>
      <a href="?trash_page=1" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">1</a>
      <?php if ($startTrashPage > 2): ?>
        <span style="padding: 0.5rem; color: var(--text-muted);">...</span>
      <?php endif; ?>
    <?php endif; ?>
    
    <!-- Page Numbers -->
    <?php for ($i = $startTrashPage; $i <= $endTrashPage; $i++): ?>
      <?php if ($i === $trashPage): ?>
        <span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600;"><?php echo $i; ?></span>
      <?php else: ?>
        <a href="?trash_page=<?php echo $i; ?>" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;"><?php echo $i; ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    
    <!-- Ellipsis + last page if needed -->
    <?php if ($endTrashPage < $totalTrashPages): ?>
      <?php if ($endTrashPage < $totalTrashPages - 1): ?>
        <span style="padding: 0.5rem; color: var(--text-muted);">...</span>
      <?php endif; ?>
      <a href="?trash_page=<?php echo $totalTrashPages; ?>" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;"><?php echo $totalTrashPages; ?></a>
    <?php endif; ?>
    
    <!-- Next Button -->
    <?php if ($trashPage < $totalTrashPages): ?>
      <a href="?trash_page=<?php echo $trashPage + 1; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
          <path d="M9 5l7 7-7 7"/>
        </svg>
      </a>
    <?php else: ?>
      <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
          <path d="M9 5l7 7-7 7"/>
        </svg>
      </span>
    <?php endif; ?>
  </div>
  
  <!-- Page Info -->
  <div id="trash-page-info" style="text-align: center; margin-top: 1rem;">
    <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary);">
      Page <strong><?php echo $trashPage; ?></strong> of <strong><?php echo $totalTrashPages; ?></strong>
    </p>
    <p style="margin: 0.25rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);">
      Showing <?php echo count($trash); ?> of <?php echo $totalTrash; ?> items
    </p>
  </div>
  <?php endif; ?>
  
  <div style="margin-top: 1rem; padding: 1rem; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-md);">
    <p style="font-size: 0.875rem; color: var(--text-primary); margin: 0;">
      <strong>⚠️ Important:</strong> Deleted notifications are permanently removed and cannot be recovered. Dismissed notifications can be found in Trash.
    </p>
  </div>
</div>

<!-- Notification Settings -->
<div class="section" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
  <h2 class="section-title">Notification Preferences</h2>
  <div class="grid grid-cols-2" style="gap: 1.5rem;">
    
    <!-- Alert Types -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Alert Types</h3>
      </div>
      <div class="card-content">
        <div style="display: flex; flex-direction: column; gap: 1rem;">
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="checkbox" checked style="width: 1rem; height: 1rem;">
            <span class="text-sm">Inventory alerts (low stock, expiry)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="checkbox" checked style="width: 1rem; height: 1rem;">
            <span class="text-sm">Financial alerts (overdue invoices, payments)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="checkbox" checked style="width: 1rem; height: 1rem;">
            <span class="text-sm">Compliance alerts (BIR, FDA deadlines)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="checkbox" checked style="width: 1rem; height: 1rem;">
            <span class="text-sm">Operational alerts (PO, production)</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Delivery Methods -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Delivery Methods</h3>
      </div>
      <div class="card-content">
        <div style="display: flex; flex-direction: column; gap: 1rem;">
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="checkbox" checked style="width: 1rem; height: 1rem;">
            <span class="text-sm">In-app notifications</span>
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>;">
            <input type="checkbox" <?php echo $smtpConfigured ? 'checked' : 'disabled'; ?> style="width: 1rem; height: 1rem; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>; accent-color: <?php echo $smtpConfigured ? '#7194A5' : '#9ca3af'; ?>;">
            <span class="text-sm" style="color: <?php echo $smtpConfigured ? '#374151' : '#9ca3af'; ?>;">Email notifications<?php if (!$smtpConfigured): ?> <span style="font-size: 0.75rem; color: #dc2626;">(SMTP not configured)</span><?php endif; ?></span>
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="checkbox" style="width: 1rem; height: 1rem;">
            <span class="text-sm">SMS notifications (optional)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="checkbox" style="width: 1rem; height: 1rem;">
            <span class="text-sm">Push notifications (browser)</span>
          </label>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Ensure global toast exists even if layout script loads later
if (typeof window.showToast !== 'function') {
  window.showToast = function(message, type = 'info', duration = 3000) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      container.style.position = 'fixed';
      container.style.right = '1rem';
      container.style.bottom = '1rem';
      container.style.zIndex = '99999';
      container.style.display = 'flex';
      container.style.flexDirection = 'column';
      container.style.gap = '0.5rem';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.minWidth = '240px';
    toast.style.maxWidth = '360px';
    toast.style.background = 'var(--bg-primary, #fff)';
    toast.style.border = '1px solid var(--border-color, #e5e7eb)';
    toast.style.color = 'var(--text-primary, #111827)';
    toast.style.padding = '0.75rem 1rem';
    toast.style.borderRadius = '8px';
    toast.style.boxShadow = '0 10px 15px rgba(0,0,0,0.05), 0 4px 6px rgba(0,0,0,0.05)';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.gap = '0.5rem';
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, duration);
  };
}

// Notification action functions for main page
function markNotificationAsRead(button) {
  const card = button.closest('.card');
  if (!card) return;
  
  const notificationId = card.getAttribute('data-notification-id');
  if (!notificationId) return;
  
  // Call API to mark as read
  fetch('/api/notifications.php?action=mark-read', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({notification_id: notificationId})
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Update card's data attribute
      card.setAttribute('data-notification-read', 'true');
      
      // Remove unread dot
      const unreadDot = card.querySelector('span[style*="background: var(--color-primary)"]');
      if (unreadDot) {
        unreadDot.remove();
      }
      
      // Hide Mark Read button
      button.style.opacity = '0';
      setTimeout(() => button.remove(), 200);
      
      // Update Unread tab count
      const unreadButton = document.querySelector('[data-filter="unread"]');
      if (unreadButton) {
        const text = unreadButton.textContent.trim();
        const match = text.match(/\((\d+)\)/);
        if (match) {
          const currentCount = parseInt(match[1]);
          const newCount = Math.max(0, currentCount - 1);
          unreadButton.textContent = text.replace(/\(\d+\)/, `(${newCount})`);
        }
      }
      
      // Update unread count in banner meta
      const unreadMeta = document.querySelector('.page-banner-meta-item strong');
      if (unreadMeta && unreadMeta.textContent === 'Unread:') {
        const countSpan = unreadMeta.nextElementSibling;
        if (countSpan) {
          const currentCount = parseInt(countSpan.textContent);
          const newCount = Math.max(0, currentCount - 1);
          countSpan.textContent = newCount;
        }
      }
      
      showToast('Notification marked as read', 'success');
      syncWithHeaderDropdown();
    }
  })
  .catch(error => {
    console.error('Error marking notification as read:', error);
    showToast('Failed to mark as read', 'error');
  });
}

function viewNotification(button) {
  const card = button.closest('.card');
  const title = card.querySelector('h4').textContent;
  showToast('Viewing: ' + title, 'info');
  // TODO: Open modal or redirect to detail page
}

// Update tab counts after deletion or dismissal
function updateTabCounts(notificationType, notificationPriority, notificationRead) {
  // Helper function to update button text with new count
  const updateButtonCount = (selector, decrement) => {
    const button = document.querySelector(selector);
    if (!button) return;
    
    const text = button.textContent.trim();
    const match = text.match(/\((\d+)\)/);
    if (match) {
      const currentCount = parseInt(match[1]);
      const newCount = Math.max(0, currentCount - decrement);
      button.textContent = text.replace(/\(\d+\)/, `(${newCount})`);
    }
  };
  
  // Update All tab (always -1)
  updateButtonCount('[data-filter="all"]', 1);
  
  // Update Unread tab if notification was unread
  if (!notificationRead) {
    updateButtonCount('[data-filter="unread"]', 1);
  }
  
  // Update High Priority tab if notification was high priority
  if (notificationPriority === 'high') {
    updateButtonCount('[data-filter="high-priority"]', 1);
  }
  
  // Update type-specific tabs
  if (notificationType === 'expiry') {
    updateButtonCount('[data-filter="expiry"]', 1);
  } else if (notificationType === 'financial') {
    updateButtonCount('[data-filter="financial"]', 1);
  } else if (notificationType === 'bir' || notificationType === 'fda') {
    updateButtonCount('[data-filter="compliance"]', 1);
  }
  
  // Update header unread count in banner meta
  if (!notificationRead) {
    const unreadMeta = document.querySelector('.page-banner-meta-item strong');
    if (unreadMeta && unreadMeta.textContent === 'Unread:') {
      const countSpan = unreadMeta.nextElementSibling;
      if (countSpan) {
        const currentCount = parseInt(countSpan.textContent);
        const newCount = Math.max(0, currentCount - 1);
        countSpan.textContent = newCount;
      }
    }
  }
}

// Update summary cards and banner meta based on a single change
function updateSummaryCardsOnChange(priority, createdAtISO, delta) {
  const adjust = (elId, d) => {
    const el = document.getElementById(elId);
    if (!el) return;
    const current = parseInt(el.textContent) || 0;
    el.textContent = Math.max(0, current + d);
  };

  // Total notifications (not dismissed/deleted)
  adjust('stat-total', delta);

  // High/Medium priority totals
  if (priority === 'high') adjust('stat-high-priority', delta);
  if (priority === 'medium') adjust('stat-medium-priority', delta);

  // Today's High/Med Alerts: within last 24 hours and priority high/medium
  if (priority === 'high' || priority === 'medium') {
    if (createdAtISO) {
      const created = new Date(createdAtISO);
      if (!isNaN(created.getTime())) {
        const cutoff = Date.now() - 24 * 60 * 60 * 1000;
        if (created.getTime() > cutoff) {
          adjust('stat-today-highmed', delta);
        }
      }
    }
  }

  // Update banner meta "High Priority: X alerts"
  if (priority === 'high') {
    const metaItems = document.querySelectorAll('.page-banner-meta-item');
    metaItems.forEach(mi => {
      const strong = mi.querySelector('strong');
      if (strong && strong.textContent.trim() === 'High Priority:') {
        const span = strong.nextElementSibling;
        if (span) {
          const m = span.textContent.trim().match(/(\d+)/);
          const current = m ? parseInt(m[1], 10) : 0;
          const next = Math.max(0, current + delta);
          span.textContent = `${next} alerts`;
        }
      }
    });
  }
}

// Recalculate and update pagination UI after list changes (delete/dismiss)
function updatePaginationAfterListChange() {
  const itemsPerPage = 10;

  const activeFilter = window.currentNotifFilter || 'all';

  // Read updated total from the active filter button
  const activeBtn = document.querySelector(`.filter-btn[data-filter="${activeFilter}"]`) || document.querySelector('[data-filter="all"]');
  let newTotal = null;
  if (activeBtn) {
    const m = activeBtn.textContent.trim().match(/\((\d+)\)/);
    if (m) newTotal = parseInt(m[1], 10);
  }
  if (newTotal === null) return;

  // Current page
  const url = new URL(window.location.href);
  let currentPage = parseInt(url.searchParams.get('page') || '1', 10);

  // Visible items currently rendered in the main list
  const listContainer = document.querySelector('[style*="display: flex; flex-direction: column; gap: 0.75rem"]');
  const showingCount = listContainer ? listContainer.querySelectorAll('.card[data-notification-id]').length : 0;

  if (activeFilter === 'all') {
    // For 'all' view, reload current page via AJAX to keep 10 items and update pagination
    const totalPages = Math.max(1, Math.ceil(newTotal / itemsPerPage));
    if (showingCount === 0 && newTotal > 0 && currentPage > 1) {
      const targetPage = Math.min(currentPage - 1, totalPages);
      if (typeof loadNotificationsPage === 'function') {
        loadNotificationsPage(targetPage);
        return;
      }
    }
    if (typeof loadNotificationsPage === 'function') {
      loadNotificationsPage(currentPage);
      return;
    }
  } else {
    // For filtered views, fetch filtered data again and rebuild pagination for the filter
    if (typeof loadFilteredNotifications === 'function') {
      loadFilteredNotifications(activeFilter, newTotal);
      return;
    }
    // As a fallback, update the filtered pagination UI directly
    const totalPages = Math.max(1, Math.ceil(newTotal / itemsPerPage));
    if (typeof updateFilteredPagination === 'function') {
      updateFilteredPagination(Math.max(0, showingCount), newTotal, activeFilter);
    }
  }
}

function deleteNotification(button) {
  if (!confirm('Are you sure you want to delete this notification? This action cannot be undone.')) return;
  
  const card = button.closest('.card');
  if (!card) return;
  
  const notificationId = card.getAttribute('data-notification-id');
  if (!notificationId) return;
  
  // Extract notification metadata before deletion
  const notificationType = card.getAttribute('data-notification-type');
  const notificationPriority = card.getAttribute('data-notification-priority');
  const notificationRead = card.getAttribute('data-notification-read') === 'true';
  const notificationCreatedAt = card.getAttribute('data-notification-created-at');
  
  fetch('/api/notifications.php?action=delete', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({notification_id: notificationId})
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      card.style.opacity = '0';
      card.style.transform = 'scale(0.95)';
      setTimeout(() => {
        card.remove();
        // Update tab counts immediately
        updateTabCounts(notificationType, notificationPriority, notificationRead);
        // Update summary cards and banner meta
        updateSummaryCardsOnChange(notificationPriority, notificationCreatedAt, -1);
        showToast('Notification deleted permanently', 'success');
        syncWithHeaderDropdown();
        updatePaginationAfterListChange();
      }, 200);
    }
  })
  .catch(error => {
    console.error('Error deleting notification:', error);
    showToast('Failed to delete notification', 'error');
  });
}

function dismissNotificationPage(button) {
  const card = button.closest('.card');
  if (!card) return;
  
  const notificationId = card.getAttribute('data-notification-id');
  if (!notificationId) return;
  
  // Extract notification metadata before dismissal
  const notificationType = card.getAttribute('data-notification-type');
  const notificationPriority = card.getAttribute('data-notification-priority');
  const notificationRead = card.getAttribute('data-notification-read') === 'true';
  const notificationCreatedAt = card.getAttribute('data-notification-created-at');
  
  fetch('/api/notifications.php?action=dismiss', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({notification_id: notificationId})
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      card.style.opacity = '0';
      card.style.transform = 'translateX(100%)';
      setTimeout(() => {
        card.remove();
        // Update tab counts immediately
        updateTabCounts(notificationType, notificationPriority, notificationRead);
        // Update summary cards and banner meta
        updateSummaryCardsOnChange(notificationPriority, notificationCreatedAt, -1);
        showToast('Notification dismissed', 'success');
        syncWithHeaderDropdown();
        updatePaginationAfterListChange();
        loadTrash();
      }, 200);
    }
  })
  .catch(error => {
    console.error('Error dismissing notification:', error);
    showToast('Failed to dismiss notification', 'error');
  });
}

// Synchronization with header dropdown
function syncWithHeaderDropdown() {
  // Reload notifications in header dropdown if it's open
  const dropdown = document.querySelector('.notification-menu');
  if (dropdown && dropdown.classList.contains('open')) {
    // Call the loadNotifications function from header.php
    if (typeof loadNotifications === 'function') {
      loadNotifications();
    }
  }
}

// Filter functionality - Fetch filtered data from server
document.addEventListener('click', function(e) {
  const filterBtn = e.target.closest('.filter-btn');
  if (!filterBtn) return;
  
  e.preventDefault();
  
  const filterButtons = document.querySelectorAll('.filter-btn');
  const filter = filterBtn.getAttribute('data-filter');
  // Remember active filter for later updates
  window.currentNotifFilter = filter || 'all';
  
  // Remove active state from all buttons
  filterButtons.forEach(btn => {
    btn.style.borderBottom = 'none';
    btn.style.marginBottom = '0';
    btn.style.color = 'var(--text-secondary)';
  });
  
  // Add active state to clicked button
  filterBtn.style.borderBottom = '2px solid var(--color-primary)';
  filterBtn.style.marginBottom = '-2px';
  filterBtn.style.color = 'var(--text-primary)';
  
  // Extract total count from button label
  const buttonText = filterBtn.textContent.trim();
  const countMatch = buttonText.match(/\((\d+)\)/);
  const totalFilteredCount = countMatch ? parseInt(countMatch[1]) : 0;
  
  // Fetch filtered data from server
  loadFilteredNotifications(filter, totalFilteredCount);
});

// Load filtered notifications from server
function loadFilteredNotifications(filter, totalCount) {
  // Always exclude dismissed and deleted
  let apiUrl = '/api/notifications.php?action=list&limit=10&skip=0&dismissed=false&deleted=false';
  
  // Build filter parameters
  switch(filter) {
    case 'unread':
      apiUrl += '&read=false';
      break;
    case 'high-priority':
      apiUrl += '&priority=high';
      break;
    case 'expiry':
      apiUrl += '&type=expiry';
      break;
    case 'financial':
      apiUrl += '&type=financial';
      break;
    case 'compliance':
      // For compliance, we need both bir and fda - fetch separately and combine
      loadComplianceNotifications(totalCount);
      return;
    case 'all':
    default:
      // Load page 1 normally
      loadNotificationsPage(1);
      return;
  }
  
  // Fetch filtered notifications
  fetch(apiUrl)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.notifications) {
        // Update notifications container
        updateNotificationsContainer(data.notifications);
        
        // Update pagination
        const visibleCount = data.notifications.length;
        updateFilteredPagination(visibleCount, totalCount, filter);
      }
    })
    .catch(error => {
      console.error('Error loading filtered notifications:', error);
    });
}

// Load compliance notifications (BIR + FDA)
function loadComplianceNotifications(totalCount) {
  Promise.all([
    fetch('/api/notifications.php?action=list&type=bir&limit=5&skip=0&dismissed=false&deleted=false').then(r => r.json()),
    fetch('/api/notifications.php?action=list&type=fda&limit=5&skip=0&dismissed=false&deleted=false').then(r => r.json())
  ])
  .then(([birData, fdaData]) => {
    const birNotifs = birData.success ? birData.notifications : [];
    const fdaNotifs = fdaData.success ? fdaData.notifications : [];
    
    // Combine and sort by date
    const allCompliance = [...birNotifs, ...fdaNotifs];
    allCompliance.sort((a, b) => {
      const timeA = new Date(a.created_at).getTime();
      const timeB = new Date(b.created_at).getTime();
      return timeB - timeA;
    });
    
    // Take top 10
    const top10 = allCompliance.slice(0, 10);
    
    // Update notifications container
    updateNotificationsContainer(top10);
    
    // Update pagination
    updateFilteredPagination(top10.length, totalCount, 'compliance');
  })
  .catch(error => {
    console.error('Error loading compliance notifications:', error);
  });
}

// Update pagination for filtered results
function ensureMainPaginationScaffold() {
  let paginationSection = document.getElementById('main-pagination');
  let pageInfo = document.getElementById('main-page-info');
  if (paginationSection && pageInfo) {
    return { paginationSection, pageInfo };
  }
  const mainSection = document.querySelectorAll('.section')[0];
  if (!mainSection) return { paginationSection: null, pageInfo: null };
  const listContainer = mainSection.querySelector('[style*="display: flex; flex-direction: column; gap: 0.75rem"]') || mainSection;
  if (!paginationSection) {
    paginationSection = document.createElement('div');
    paginationSection.id = 'main-pagination';
    paginationSection.style.cssText = 'display:flex; justify-content:center; align-items:center; gap:0.5rem; margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border-color); flex-wrap:wrap;';
    listContainer.insertAdjacentElement('afterend', paginationSection);
  }
  if (!pageInfo) {
    pageInfo = document.createElement('div');
    pageInfo.id = 'main-page-info';
    pageInfo.style.cssText = 'text-align:center; margin-top:1rem;';
    paginationSection.insertAdjacentElement('afterend', pageInfo);
  }
  return { paginationSection, pageInfo };
}

function updateFilteredPagination(visibleCountOnPage, totalFilteredCount, filter) {
  let paginationSection = document.getElementById('main-pagination');
  let pageInfo = document.getElementById('main-page-info');
  
  const itemsPerPage = 10;
  const totalPages = Math.ceil(totalFilteredCount / itemsPerPage);
  const currentPage = 1; // Filters always show page 1
  
  // Format filter name for display
  let filterName = filter === 'all' ? 'notifications' : 
                   filter === 'high-priority' ? 'high priority items' :
                   filter === 'unread' ? 'unread notifications' :
                   filter + ' items';
  
  if (totalFilteredCount <= 10) {
    // Hide pagination if 10 or fewer total items in this filter
    if (paginationSection) paginationSection.style.display = 'none';
    if (pageInfo) {
      const showAllLink = filter !== 'all' ? 
        `<p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; text-align: center;">
          <a href="#" class="filter-btn" data-filter="all" style="color: var(--color-primary); text-decoration: underline; cursor: pointer;">Show all notifications</a>
        </p>` : '';
      
      pageInfo.innerHTML = `
        <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary); text-align: center;">
          Showing <strong>${visibleCountOnPage}</strong> of <strong>${totalFilteredCount}</strong> ${filterName}
        </p>
        ${showAllLink}
      `;
    }
  } else {
    // Show pagination for more than 10 items
    if (!paginationSection || !pageInfo) {
      const res = ensureMainPaginationScaffold();
      paginationSection = res.paginationSection;
      pageInfo = res.pageInfo;
    }
    if (paginationSection) paginationSection.style.display = 'flex';
    // Rebuild pagination buttons with correct total pages
    if (paginationSection) rebuildPaginationButtons(paginationSection, currentPage, totalPages);
    
    if (pageInfo) {
      const showAllLink = filter !== 'all' ? 
        `<p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; text-align: center;">
          <a href="#" class="filter-btn" data-filter="all" style="color: var(--color-primary); text-decoration: underline; cursor: pointer;">Show all notifications</a>
        </p>` : '';
      
      pageInfo.innerHTML = `
        <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary); text-align: center;">
          Showing <strong>${visibleCountOnPage}</strong> of <strong>${totalFilteredCount}</strong> ${filterName}
        </p>
        <p style="margin: 0.25rem 0 0 0; font-size: 0.75rem; color: var(--text-muted); text-align: center;">
          (Filtered view showing page 1 of ${totalPages})
        </p>
        ${showAllLink}
      `;
    }
  }
}

// Rebuild pagination buttons with correct page numbers
function rebuildPaginationButtons(paginationSection, currentPage, totalPages) {
  if (!paginationSection) return;
  
  currentPage = parseInt(currentPage);
  
  // Calculate page range
  const maxVisible = 7;
  let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
  let endPage = Math.min(totalPages, startPage + maxVisible - 1);
  
  if (endPage - startPage + 1 < maxVisible) {
    startPage = Math.max(1, endPage - maxVisible + 1);
  }
  
  // Build pagination HTML
  let html = '';
  
  // Previous button
  if (currentPage > 1) {
    html += `<a href="?page=${currentPage - 1}" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
        <path d="M15 19l-7-7 7-7"/>
      </svg>
      Previous
    </a>`;
  } else {
    html += `<span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
        <path d="M15 19l-7-7 7-7"/>
      </svg>
      Previous
    </span>`;
  }
  
  // First page + ellipsis
  if (startPage > 1) {
    if (currentPage === 1) {
      html += `<span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600; cursor: default;">1</span>`;
    } else {
      html += `<a href="?page=1" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">1</a>`;
    }
    if (startPage > 2) {
      html += `<span style="padding: 0.5rem; color: var(--text-muted);">...</span>`;
    }
  }
  
  // Page numbers
  for (let i = startPage; i <= endPage; i++) {
    if (i === currentPage) {
      html += `<span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600; cursor: default;">${i}</span>`;
    } else {
      html += `<a href="?page=${i}" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">${i}</a>`;
    }
  }
  
  // Ellipsis + last page
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      html += `<span style="padding: 0.5rem; color: var(--text-muted);">...</span>`;
    }
    if (currentPage === totalPages) {
      html += `<span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600; cursor: default;">${totalPages}</span>`;
    } else {
      html += `<a href="?page=${totalPages}" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">${totalPages}</a>`;
    }
  }
  
  // Next button
  if (currentPage < totalPages) {
    html += `<a href="?page=${currentPage + 1}" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
      Next
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
        <path d="M9 5l7 7-7 7"/>
      </svg>
    </a>`;
  } else {
    html += `<span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
      Next
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
        <path d="M9 5l7 7-7 7"/>
      </svg>
    </span>`;
  }
  
  paginationSection.innerHTML = html;
}

// Set initial active state on page load
document.addEventListener('DOMContentLoaded', function() {
  const allButton = document.querySelector('[data-filter="all"]');
  if (allButton) {
    allButton.style.color = 'var(--text-primary)';
  }
});

// Add fade-in animation (avoid global 'style' collisions)
(function() {
  const fadeStyleEl = document.createElement('style');
  fadeStyleEl.textContent = `
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  `;
  document.head.appendChild(fadeStyleEl);
})();

// Mark all as read
function markAllAsRead() {
  fetch('/api/notifications.php?action=mark-all-read', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'}
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(`Marked ${data.count} notifications as read`, 'success');
      location.reload();
    }
  })
  .catch(error => {
    console.error('Error marking all as read:', error);
    showToast('Failed to mark all as read', 'error');
  });
}

// Open settings
function openSettings() {
  // Scroll to settings section
  const settingsSection = document.querySelector('[id*="settings"]') || document.querySelectorAll('.section')[document.querySelectorAll('.section').length - 1];
  if (settingsSection) {
    settingsSection.scrollIntoView({ behavior: 'smooth' });
  }
}

// Trash management
function loadTrash() {
  const trashContainer = document.getElementById('trash-container');
  let trashPage = parseInt(new URLSearchParams(window.location.search).get('trash_page') || '1', 10);
  const itemsPerPage = 10;
  const skip = (trashPage - 1) * itemsPerPage;
  
  fetch('/api/notifications.php?action=trash')
    .then(response => response.json())
    .then(data => {
      if (!data.trash || data.trash.length === 0) {
        trashContainer.innerHTML = `
          <div style="padding: 2rem; text-align: center; color: var(--text-secondary); background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="opacity: 0.3; margin: 0 auto 1rem; stroke: currentColor;">
              <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <p style="font-size: 0.875rem; font-weight: 500;">Trash is empty</p>
            <p style="font-size: 0.75rem; margin-top: 0.25rem; color: var(--text-muted);">Dismissed notifications appear here</p>
          </div>
        `;
        // Also clear/hide trash pagination when empty
        updateTrashPaginationControls(1, 1, 0, 0);
        return;
      }
      
      // Compute pagination and get items for current trash page
      const totalTrash = data.trash.length;
      let totalPages = Math.ceil(totalTrash / itemsPerPage);
      if (totalPages === 0) totalPages = 1;
      // If current page is now out of range (e.g., after restore), move back
      if (trashPage > totalPages) {
        trashPage = totalPages;
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('trash_page', String(trashPage));
        window.history.replaceState({trash_page: trashPage}, '', newUrl);
      }
      const adjustedSkip = (trashPage - 1) * itemsPerPage;
      const paginatedTrash = data.trash.slice(adjustedSkip, adjustedSkip + itemsPerPage);
      
      const trashItems = paginatedTrash.map(notif => `
        <div class="card" data-notification-id="${notif.id}" data-notification-priority="${notif.priority || ''}" data-notification-created-at="${notif.created_at || ''}" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
          <div style="flex: 1;">
            <h4 style="margin: 0 0 0.25rem 0; font-size: 0.875rem; font-weight: 500;">${notif.title}</h4>
            <p style="margin: 0; font-size: 0.8125rem; color: var(--text-secondary);">${notif.message}</p>
            <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);">Dismissed: ${notif.dismissed_at}</p>
          </div>
          <div style="display: flex; gap: 0.5rem; margin-left: 1rem;">
            <button class="btn btn-ghost" onclick="restoreNotification(this)" style="font-size: 0.75rem; color: var(--color-primary);">Restore</button>
            <button class="btn btn-ghost" onclick="permanentlyDeleteFromTrash(this)" style="font-size: 0.75rem; color: var(--color-danger);">Delete</button>
          </div>
        </div>
      `).join('');
      
      trashContainer.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
          ${trashItems}
        </div>
      `;

      // Update trash pagination controls and page info
      updateTrashPaginationControls(trashPage, totalPages, totalTrash, paginatedTrash.length);
    })
    .catch(error => {
      console.error('Error loading trash:', error);
      trashContainer.innerHTML = '<p style="color: var(--text-danger);">Error loading trash</p>';
    });
}

function emptyTrash() {
  if (!confirm('Are you sure you want to permanently delete all items in trash? This action cannot be undone.')) return;
  
  fetch('/api/notifications.php?action=empty-trash', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'}
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast(`Trash emptied (${data.count} items deleted)`, 'success');
      loadTrash();
    }
  })
  .catch(error => {
    console.error('Error emptying trash:', error);
    showToast('Failed to empty trash', 'error');
  });
}

function restoreNotification(button) {
  const card = button.closest('.card');
  if (!card) return;
  
  const notificationId = card.getAttribute('data-notification-id');
  if (!notificationId) return;
  
  fetch('/api/notifications.php?action=restore', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({notification_id: notificationId})
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Remove from trash list with a small animation
      card.style.opacity = '0';
      card.style.transform = 'translateX(-10px)';
      setTimeout(() => {
        if (card && card.parentNode) card.parentNode.removeChild(card);
      }, 200);

      // Increment All tab count (+1)
      adjustButtonCount('[data-filter="all"]', +1);
      // If high priority, increment its tab count as well
      const restoredPriority = card.getAttribute('data-notification-priority') || '';
      if (restoredPriority === 'high') {
        adjustButtonCount('[data-filter="high-priority"]', +1);
      }

      // Update summary cards and banner meta (+1)
      const restoredCreatedAt = card.getAttribute('data-notification-created-at') || '';
      updateSummaryCardsOnChange(restoredPriority, restoredCreatedAt, +1);

      // Update main pagination for the All or active filter view
      updatePaginationAfterListChange();

      // Reload trash list and its pagination
      loadTrash();
      showToast('Notification restored', 'success');
    }
  })
  .catch(error => {
    console.error('Error restoring notification:', error);
    showToast('Failed to restore notification', 'error');
  });
}

// Generic helper to adjust a tab button count by delta
function adjustButtonCount(selector, delta) {
  const button = document.querySelector(selector);
  if (!button) return;
  const text = button.textContent.trim();
  const match = text.match(/\((\d+)\)/);
  if (!match) return;
  const current = parseInt(match[1], 10);
  const next = Math.max(0, current + delta);
  button.textContent = text.replace(/\(\d+\)/, `(${next})`);
}

function permanentlyDeleteFromTrash(button) {
  if (!confirm('Permanently delete this notification? This cannot be undone.')) return;
  
  const card = button.closest('.card');
  if (!card) return;
  
  const notificationId = card.getAttribute('data-notification-id');
  if (!notificationId) return;
  
  fetch('/api/notifications.php?action=delete', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({notification_id: notificationId})
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showToast('Notification permanently deleted', 'success');
      loadTrash();
    }
  })
  .catch(error => {
    console.error('Error deleting notification:', error);
    showToast('Failed to delete notification', 'error');
  });
}

// Listen for dismissals from header
window.addEventListener('storage', (e) => {
  if (e.key === 'dismissedNotifications') {
    loadTrash();
  }
});

// Listen for dismissal events from header (same tab)
window.addEventListener('notificationDismissed', () => {
  loadTrash();
});

// Sort notifications on page load for consistent display
function sortNotificationsOnLoad() {
  const container = document.querySelector('[style*="display: flex; flex-direction: column"]');
  if (!container) return;
  
  // Get all notification cards
  const cards = Array.from(container.querySelectorAll('[data-notification-type]'));
  
  // Sort by created_at (newest first) - they should already be sorted from DB
  // But ensure they stay in order even after DOM manipulation
  // Keep default display styling from CSS to preserve layout
}

// Load trash on page load
document.addEventListener('DOMContentLoaded', () => {
  sortNotificationsOnLoad();
  loadTrash();
});

// Real-time sync with header - listen for dismissals from header.php
window.addEventListener('notificationDismissed', (event) => {
  const dismissedId = event.detail?.notificationId;
  if (dismissedId) {
    // Remove the dismissed card from the page
    const card = document.querySelector(`[data-notification-id="${dismissedId}"]`);
    if (card) {
      card.style.opacity = '0';
      card.style.transform = 'translateX(100%)';
      setTimeout(() => {
        card.remove();
      }, 200);
    }
    // Reload trash to show the dismissed notification
    loadTrash();
  }
});

// Listen for storage changes (cross-tab sync)
window.addEventListener('storage', (event) => {
  if (event.key === 'lastDismissedNotification') {
    const data = JSON.parse(event.newValue || '{}');
    if (data.id) {
      // Remove the dismissed card
      const card = document.querySelector(`[data-notification-id="${data.id}"]`);
      if (card) {
        card.style.opacity = '0';
        card.style.transform = 'translateX(100%)';
        setTimeout(() => {
          card.remove();
        }, 200);
      }
      // Reload trash
      loadTrash();
    }
  }
});

// =====================================================
// INSTANT PAGINATION - NO PAGE RELOAD
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
  // Add click handlers to all pagination links
  document.addEventListener('click', function(e) {
    // Check if clicked element is a pagination link
    const link = e.target.closest('a[href*="?page="], a[href*="?trash_page="]');
    if (!link) return;
    
    e.preventDefault(); // Prevent default navigation
    
    const url = new URL(link.href);
    const params = new URLSearchParams(url.search);
    const page = params.get('page');
    const trashPage = params.get('trash_page');
    
    if (page) {
      // Load notifications page
      loadNotificationsPage(page);
    } else if (trashPage) {
      // Load trash page
      loadTrashPage(trashPage);
    }
  });
});

// Load notifications page via AJAX
function loadNotificationsPage(page) {
  // Update URL without reload
  const newUrl = new URL(window.location);
  newUrl.searchParams.set('page', page);
  window.history.pushState({page: page}, '', newUrl);
  
  // Fetch new page content
  fetch(`/api/notifications.php?action=get-page&page=${page}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update notifications container
        updateNotificationsContainer(data.notifications);
        
        // Update pagination
        updatePaginationControls(page, data.totalPages, data.totalNotifications, data.showingCount);
      }
    })
    .catch(error => {
      console.error('Error loading page:', error);
      // Fallback to normal navigation
      window.location.href = `?page=${page}`;
    });
}

// Load trash page via AJAX
function loadTrashPage(page) {
  // Convert to integer
  page = parseInt(page);
  
  // Update URL without reload
  const newUrl = new URL(window.location);
  newUrl.searchParams.set('trash_page', page);
  window.history.pushState({trash_page: page}, '', newUrl);
  
  // Fetch trash data
  fetch('/api/notifications.php?action=trash')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.trash) {
        const itemsPerPage = 10;
        const skip = (page - 1) * itemsPerPage;
        const totalTrash = data.trash.length;
        const totalPages = Math.ceil(totalTrash / itemsPerPage);
        
        // Get paginated trash
        const paginatedTrash = data.trash.slice(skip, skip + itemsPerPage);
        
        // Update trash container
        updateTrashContainer(paginatedTrash);
        
        // Update trash pagination controls
        updateTrashPaginationControls(page, totalPages, totalTrash, paginatedTrash.length);
      }
    })
    .catch(error => {
      console.error('Error loading trash page:', error);
    });
}

// Update notifications container
function updateNotificationsContainer(notifications) {
  const container = document.querySelector('[style*="display: flex; flex-direction: column; gap: 0.75rem"]');
  if (!container) return;
  
  // Build HTML for notifications
  const html = notifications.map(notif => {
    const bgColor = getBgColor(notif);
    const borderColor = !notif.read ? 'rgba(59, 130, 246, 0.2)' : 'var(--border-color)';
    const iconColor = getIconColor(notif.type);
    const badgeStyle = getBadgeStyle(notif.priority);
    
    return `
      <div class="card" data-notification-id="${notif.id}" data-notification-type="${notif.type}" data-notification-priority="${notif.priority}" data-notification-read="${notif.read ? 'true' : 'false'}" data-notification-created-at="${notif.created_at}" style="background: ${bgColor}; border: 1px solid ${borderColor}; border-radius: var(--radius-md); transition: all 0.2s ease;">
        <div class="card-content" style="padding: 1rem;">
          <div style="display: flex; gap: 0.875rem; align-items: start;">
            <div style="flex-shrink: 0; margin-top: 2px;">
              ${getNotificationIcon(notif.type, iconColor)}
            </div>
            <div style="flex: 1; min-width: 0;">
              <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem; margin-bottom: 0.5rem;">
                <div style="flex: 1;">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.375rem;">
                    <h4 class="font-semibold" style="margin: 0; font-size: 0.9375rem; line-height: 1.2;">${notif.title}</h4>
                    ${!notif.read ? '<span style="width: 6px; height: 6px; background: var(--color-primary); border-radius: 50%; flex-shrink: 0;"></span>' : ''}
                  </div>
                  <p class="text-sm text-secondary" style="margin: 0; line-height: 1.5;">${notif.message}</p>
                </div>
                <div style="flex-shrink: 0;">
                  <span style="display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.6875rem; font-weight: 600; ${badgeStyle}">${notif.priority.toUpperCase()}</span>
                </div>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem;">
                <span class="text-xs text-muted" style="font-size: 0.75rem;">${notif.time}</span>
                <div style="display: flex; gap: 0.375rem;">
                  ${!notif.read ? `
                    <button class="btn btn-ghost btn-sm" onclick="markNotificationAsRead(this)" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.25rem; stroke-width: 2;">
                        <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke-linecap="round"/>
                        <path d="M22 4L12 14.01L9 11.01" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                      Mark Read
                    </button>
                  ` : ''}
                  <button class="btn btn-ghost btn-sm" onclick="viewNotification(this)" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.25rem; stroke-width: 2;">
                      <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                    View
                  </button>
                  <button class="btn btn-ghost btn-sm" onclick="deleteNotification(this)" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem; color: var(--color-danger);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.25rem; stroke-width: 2;">
                      <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Delete
                  </button>
                  <button class="btn btn-ghost btn-sm" onclick="dismissNotificationPage(this)" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.25rem; stroke-width: 2;">
                      <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Dismiss
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  }).join('');
  
  container.innerHTML = html;
}

// Update pagination controls
function updatePaginationControls(currentPage, totalPages, totalNotifications, showingCount) {
  // Convert to integer to ensure proper comparison
  currentPage = parseInt(currentPage);
  
  // Find or create pagination container
  let paginationSection = document.getElementById('main-pagination');
  let pageInfo = document.getElementById('main-page-info');
  if (!paginationSection || !pageInfo) {
    const res = ensureMainPaginationScaffold();
    paginationSection = res.paginationSection;
    pageInfo = res.pageInfo;
  }
  if (!paginationSection) return;
  
  // Calculate page range
  const maxVisible = 7;
  let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
  let endPage = Math.min(totalPages, startPage + maxVisible - 1);
  
  if (endPage - startPage + 1 < maxVisible) {
    startPage = Math.max(1, endPage - maxVisible + 1);
  }
  
  // Build pagination HTML
  let html = '';
  
  // Previous button
  if (currentPage > 1) {
    html += `<a href="?page=${currentPage - 1}" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
        <path d="M15 19l-7-7 7-7"/>
      </svg>
      Previous
    </a>`;
  } else {
    html += `<span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
        <path d="M15 19l-7-7 7-7"/>
      </svg>
      Previous
    </span>`;
  }
  
  // First page + ellipsis
  if (startPage > 1) {
    if (currentPage === 1) {
      html += `<span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600; text-decoration: none;">1</span>`;
    } else {
      html += `<a href="?page=1" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">1</a>`;
    }
    if (startPage > 2) {
      html += `<span style="padding: 0.5rem; color: var(--text-muted);">...</span>`;
    }
  }
  
  // Page numbers
  for (let i = startPage; i <= endPage; i++) {
    if (i === currentPage) {
      html += `<span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600; cursor: default;">${i}</span>`;
    } else {
      html += `<a href="?page=${i}" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">${i}</a>`;
    }
  }
  
  // Ellipsis + last page
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      html += `<span style="padding: 0.5rem; color: var(--text-muted);">...</span>`;
    }
    if (currentPage === totalPages) {
      html += `<span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600; cursor: default;">${totalPages}</span>`;
    } else {
      html += `<a href="?page=${totalPages}" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">${totalPages}</a>`;
    }
  }
  
  // Next button
  if (currentPage < totalPages) {
    html += `<a href="?page=${currentPage + 1}" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
      Next
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
        <path d="M9 5l7 7-7 7"/>
      </svg>
    </a>`;
  } else {
    html += `<span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
      Next
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
        <path d="M9 5l7 7-7 7"/>
      </svg>
    </span>`;
  }
  
  paginationSection.innerHTML = html;
  
  // Update page info
  if (pageInfo) {
    pageInfo.innerHTML = `
      <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary);">
        Page <strong>${currentPage}</strong> of <strong>${totalPages}</strong>
      </p>
      <p style="margin: 0.25rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);">
        Showing ${showingCount} of ${totalNotifications} notifications
      </p>
    `;
  }
}

// Helper functions
function getBgColor(notif) {
  if (notif.read) return 'transparent';
  const colors = {
    'expiry': 'rgba(239, 68, 68, 0.08)',
    'bir': 'rgba(59, 130, 246, 0.08)',
    'fda': 'rgba(34, 197, 94, 0.08)',
    'inventory': 'rgba(245, 158, 11, 0.08)',
    'financial': 'rgba(239, 68, 68, 0.08)',
    'success': 'rgba(34, 197, 94, 0.08)'
  };
  return colors[notif.type] || 'transparent';
}

function getIconColor(type) {
  const colors = {
    'expiry': '#ef4444',
    'bir': '#3b82f6',
    'fda': '#22c55e',
    'inventory': '#f59e0b',
    'financial': '#ef4444',
    'success': '#22c55e'
  };
  return colors[type] || '#6b7280';
}

function getBadgeStyle(priority) {
  const styles = {
    'high': 'background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);',
    'medium': 'background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);'
  };
  return styles[priority] || 'background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color);';
}

function getNotificationIcon(type, color) {
  const icons = {
    'expiry': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><circle cx="12" cy="12" r="10"/><path d="M12 6V12L16 14" stroke-linecap="round"/></svg>`,
    'bir': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z"/><polyline points="14 2 14 8 20 8"/></svg>`,
    'fda': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z"/><polyline points="14 2 14 8 20 8"/></svg>`,
    'inventory': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M21 16V8C21 6.89543 20.1046 6 19 6H5C3.89543 6 3 6.89543 3 8V16C3 17.1046 3.89543 18 5 18H19C20.1046 18 21 17.1046 21 16Z"/><path d="M3 10H21"/></svg>`,
    'financial': `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M12 2V22M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke-linecap="round"/></svg>`
  };
  return icons[type] || `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke-linecap="round"/><path d="M22 4L12 14.01L9 11.01" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
}

// Update trash container
function updateTrashContainer(trashItems) {
  const trashContainer = document.getElementById('trash-container');
  if (!trashContainer) return;
  
  if (!trashItems || trashItems.length === 0) {
    trashContainer.innerHTML = `
      <div style="padding: 2rem; text-align: center; color: var(--text-secondary); background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="opacity: 0.3; margin: 0 auto 1rem; stroke: currentColor;">
          <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <p style="font-size: 0.875rem; font-weight: 500;">Trash is empty</p>
        <p style="font-size: 0.75rem; margin-top: 0.25rem; color: var(--text-muted);">Dismissed notifications appear here</p>
      </div>
    `;
    return;
  }
  
  const html = trashItems.map(notif => `
    <div class="card" data-notification-id="${notif.id}" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
      <div style="flex: 1;">
        <h4 style="margin: 0 0 0.25rem 0; font-size: 0.875rem; font-weight: 500;">${notif.title}</h4>
        <p style="margin: 0; font-size: 0.8125rem; color: var(--text-secondary);">${notif.message}</p>
        <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);">Dismissed: ${notif.dismissed_at}</p>
      </div>
      <div style="display: flex; gap: 0.5rem; margin-left: 1rem;">
        <button class="btn btn-ghost" onclick="restoreNotification(this)" style="font-size: 0.75rem; color: var(--color-primary);">Restore</button>
        <button class="btn btn-ghost" onclick="permanentlyDeleteFromTrash(this)" style="font-size: 0.75rem; color: var(--color-danger);">Delete</button>
      </div>
    </div>
  `).join('');
  
  trashContainer.innerHTML = `
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
      ${html}
    </div>
  `;
}

// Ensure trash pagination scaffold exists (container + page info)
function ensureTrashPaginationScaffold() {
  let paginationSection = document.getElementById('trash-pagination');
  let pageInfo = document.getElementById('trash-page-info');
  if (paginationSection && pageInfo) {
    return { paginationSection, pageInfo };
  }
  const trashContainer = document.getElementById('trash-container');
  if (!trashContainer) return { paginationSection: null, pageInfo: null };
  if (!paginationSection) {
    paginationSection = document.createElement('div');
    paginationSection.id = 'trash-pagination';
    paginationSection.style.cssText = 'display:flex; justify-content:center; align-items:center; gap:0.5rem; margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--border-color); flex-wrap:wrap;';
    trashContainer.insertAdjacentElement('afterend', paginationSection);
  }
  if (!pageInfo) {
    pageInfo = document.createElement('div');
    pageInfo.id = 'trash-page-info';
    pageInfo.style.cssText = 'text-align:center; margin-top:1rem;';
    paginationSection.insertAdjacentElement('afterend', pageInfo);
  }
  return { paginationSection, pageInfo };
}

// Update trash pagination controls
function updateTrashPaginationControls(currentPage, totalPages, totalTrash, showingCount) {
  // Convert to integer
  currentPage = parseInt(currentPage);
  
  // Find or create trash pagination container
  const res = ensureTrashPaginationScaffold();
  const trashPaginationSection = res.paginationSection;
  const pageInfo = res.pageInfo;
  if (!trashPaginationSection) return;
  
  // If only 1 page or no items, hide pagination
  if (!totalPages || totalPages <= 1) {
    trashPaginationSection.style.display = 'none';
    if (pageInfo) {
      pageInfo.innerHTML = `
        <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary);">
          Showing ${showingCount} of ${totalTrash} items
        </p>
      `;
    }
    return;
  }
  
  trashPaginationSection.style.display = 'flex';
  
  // Calculate page range
  const maxVisible = 7;
  let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
  let endPage = Math.min(totalPages, startPage + maxVisible - 1);
  if (endPage - startPage + 1 < maxVisible) {
    startPage = Math.max(1, endPage - maxVisible + 1);
  }
  
  // Build pagination HTML
  let html = '';
  
  // Previous button
  if (currentPage > 1) {
    html += `<a href="?trash_page=${currentPage - 1}" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
        <path d="M15 19l-7-7 7-7"/>
      </svg>
      Previous
    </a>`;
  } else {
    html += `<span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 0.5rem; stroke-width: 2;">
        <path d="M15 19l-7-7 7-7"/>
      </svg>
      Previous
    </span>`;
  }
  
  // First page + ellipsis
  if (startPage > 1) {
    if (currentPage === 1) {
      html += `<span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600; cursor: default;">1</span>`;
    } else {
      html += `<a href="?trash_page=1" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">1</a>`;
    }
    if (startPage > 2) {
      html += `<span style=\"padding: 0.5rem; color: var(--text-muted);\">...</span>`;
    }
  }
  
  // Page numbers
  for (let i = startPage; i <= endPage; i++) {
    if (i === currentPage) {
      html += `<span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600; cursor: default;">${i}</span>`;
    } else {
      html += `<a href="?trash_page=${i}" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">${i}</a>`;
    }
  }
  
  // Ellipsis + last page
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      html += `<span style="padding: 0.5rem; color: var(--text-muted);">...</span>`;
    }
    if (currentPage === totalPages) {
      html += `<span class="btn btn-primary" style="min-width: 40px; padding: 0.5rem 0.75rem; font-weight: 600; cursor: default;">${totalPages}</span>`;
    } else {
      html += `<a href="?trash_page=${totalPages}" class="btn btn-ghost" style="min-width: 40px; padding: 0.5rem 0.75rem; text-decoration: none;">${totalPages}</a>`;
    }
  }
  
  // Next button
  if (currentPage < totalPages) {
    html += `<a href="?trash_page=${currentPage + 1}" class="btn btn-secondary" style="padding: 0.5rem 1rem; text-decoration: none;">
      Next
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
        <path d="M9 5l7 7-7 7"/>
      </svg>
    </a>`;
  } else {
    html += `<span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">
      Next
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-left: 0.5rem; stroke-width: 2;">
        <path d="M9 5l7 7-7 7"/>
      </svg>
    </span>`;
  }
  
  trashPaginationSection.innerHTML = html;
  
  // Update page info
  if (pageInfo) {
    pageInfo.innerHTML = `
      <p style=\"margin: 0; font-size: 0.875rem; color: var(--text-secondary);\">
        Page <strong>${currentPage}</strong> of <strong>${totalPages}</strong>
      </p>
      <p style=\"margin: 0.25rem 0 0 0; font-size: 0.75rem; color: var(--text-muted);\">
        Showing ${showingCount} of ${totalTrash} items
      </p>
    `;
  }
}

</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../../components/layout.php';
?>


