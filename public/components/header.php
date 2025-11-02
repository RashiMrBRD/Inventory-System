<?php
/**
 * Header Component
 * Top navigation bar with search and user menu
 */

// Get user info from session
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
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
    <button class="sidebar-toggle btn btn-ghost btn-icon show-mobile" aria-label="Toggle menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
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
          <a href="notifications.php" style="font-size: 0.8125rem; color: var(--color-primary); text-decoration: none; font-weight: 500;">View All</a>
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
        <a href="profile.php" class="dropdown-item" role="menuitem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2"/>
            <path d="M20 21C20 16.5817 16.4183 13 12 13C7.58172 13 4 16.5817 4 21" stroke="currentColor" stroke-width="2"/>
          </svg>
          Profile
        </a>
        
        <a href="settings.php" class="dropdown-item" role="menuitem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            <path d="M12 1v6m0 6v6M1 12h6m6 0h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Settings
        </a>

        <div class="dropdown-divider"></div>

        <a href="logout.php" class="dropdown-item" role="menuitem">
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

<script>
// User menu dropdown
(function() {
  const userMenuButton = document.getElementById('user-menu-button');
  const userMenu = document.getElementById('user-menu');
  const dropdown = document.querySelector('.user-menu');

  if (userMenuButton && userMenu) {
    userMenuButton.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('open');
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
      }
    });

    // Close on escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        dropdown.classList.remove('open');
      }
    });
  }
})();

// Global search functionality
(function() {
  const searchInput = document.getElementById('global-search');
  
  if (searchInput) {
    searchInput.addEventListener('input', debounce(function(e) {
      const query = e.target.value.trim();
      if (query.length > 2) {
        // Redirect to inventory list with search query
        // Or implement AJAX search here
        console.log('Searching for:', query);
      }
    }, 300));
  }

  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
})();

// Notification dropdown functionality
(function() {
  const notificationButton = document.getElementById('notification-button');
  const notificationMenu = document.getElementById('notification-menu');
  const notificationDropdown = document.querySelector('.notification-menu');
  const badge = document.getElementById('notification-badge');
  const notificationItems = document.getElementById('notification-items');
  
  if (notificationButton && notificationMenu) {
    // Toggle dropdown
    notificationButton.addEventListener('click', (e) => {
      e.stopPropagation();
      notificationDropdown.classList.toggle('open');
      
      // Load notifications when opened
      if (notificationDropdown.classList.contains('open')) {
        loadNotifications();
      }
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!notificationDropdown.contains(e.target)) {
        notificationDropdown.classList.remove('open');
      }
    });

    // Close on escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        notificationDropdown.classList.remove('open');
      }
    });
  }
  
  // Load notifications (expose globally for other handlers)
  window.loadNotifications = function() {
    fetch('<?php echo dirname($_SERVER['PHP_SELF']); ?>/api/get-notifications.php')
      .then(response => response.json())
      .then(data => {
        displayNotifications(data.notifications || []);
        displaySummary(data.summary || {});
        updateBadge(data.total || 0);
      })
      .catch(error => {
        console.error('Error loading notifications:', error);
        // Show empty state instead of fallback demo data
        displayNotifications([]);
        updateBadge(0);
      });
  }
  
  // Display notification summary
  function displaySummary(summary) {
    const summaryContainer = document.querySelector('.notification-summary');
    if (!summaryContainer) return;
    
    const items = [];
    // Optional extra highlights to mirror notifications.php
    if (typeof summary.today_high_med === 'number' && summary.today_high_med > 0) {
      items.push(`<span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 500;">⭐ ${summary.today_high_med} Today High/Med</span>`);
    }
    if (summary.outstanding_invoices > 0) {
      items.push(`<span style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 500;">💰 ${summary.outstanding_invoices} Outstanding Invoice${summary.outstanding_invoices > 1 ? 's' : ''}</span>`);
    }
    if (summary.expiring_stock > 0) {
      items.push(`<span style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 500;">⏰ ${summary.expiring_stock} Stock Expiring</span>`);
    }
    if (summary.low_stock > 0) {
      items.push(`<span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 500;">📦 ${summary.low_stock} Low Stock</span>`);
    }
    if (summary.compliance_alerts > 0) {
      items.push(`<span style="background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 500;">✓ ${summary.compliance_alerts} Compliance Alert${summary.compliance_alerts > 1 ? 's' : ''}</span>`);
    }
    if (summary.maintenance > 0) {
      items.push(`<span style="background: rgba(107, 114, 128, 0.1); color: #6b7280; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 500;">⚙️ ${summary.maintenance} Maintenance</span>`);
    }
    
    // Top highlights: Unread, High Priority, Last Updated
    const unreadTotal = (summary && typeof summary.unread_total === 'number') ? summary.unread_total : 0;
    const highPriorityTotal = (summary && typeof summary.high_priority_total === 'number') ? summary.high_priority_total : 0;
    const lastUpdated = (summary && summary.last_updated) ? summary.last_updated : 'just now';
    
    const topHighlights = `
      <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; padding: 0.75rem 1rem; background: var(--bg-primary); border-bottom: 1px solid var(--border-color);">
        <span style="font-size: 0.8125rem; color: var(--text-secondary);">
          <strong>Unread:</strong>
          <span style="display:inline-flex; align-items:center; padding: 0.125rem 0.5rem; border-radius: 999px; background: rgba(239,68,68,0.1); color: #ef4444; font-weight: 600;">${unreadTotal}</span>
        </span>
        <span style="font-size: 0.8125rem;">
          <strong>High Priority:</strong>
          <span style="color:#ef4444; font-weight:600;">${highPriorityTotal} alerts</span>
        </span>
        <span style="font-size: 0.8125rem; color: var(--text-secondary);">
          <strong>Last Updated:</strong>
          <span>${lastUpdated}</span>
        </span>
      </div>
    `;
    
    const chips = items.length > 0
      ? `<div style="display: flex; flex-wrap: wrap; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">${items.join('')}</div>`
      : '';
    
    summaryContainer.innerHTML = topHighlights + chips;
  }
  
  // Display notifications in dropdown
  function displayNotifications(notifications) {
    if (notifications.length === 0) {
      notificationItems.innerHTML = `
        <div style="padding: 2.5rem 2rem; text-align: center; color: hsl(215 16% 47%);">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" style="opacity: 0.2; margin: 0 auto 1rem; stroke: currentColor; stroke-width: 1.5;">
            <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 style="font-size: 0.9375rem; font-weight: 600; color: hsl(0 0% 12%); margin: 0 0 0.5rem 0;">No notifications</h3>
          <p style="font-size: 0.8125rem; color: hsl(215 16% 47%); margin: 0; line-height: 1.5;">New notifications will appear here when you receive them</p>
        </div>
      `;
      return;
    }
    
    notificationItems.innerHTML = notifications.map((notif, index) => {
      const icon = getNotificationIcon(notif.type);
      
      return `
        <div class="dropdown-item" style="display: flex; gap: 0.75rem; padding: 0.875rem 1rem; background: transparent; border-bottom: 1px solid var(--border-color); transition: background-color 0.15s ease;" data-index="${index}" data-notification-id="${notif.id}">
          <div style="flex-shrink: 0; margin-top: 1px;">
            ${icon}
          </div>
          <div style="flex: 1; min-width: 0;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
              <h4 style="font-size: 0.875rem; font-weight: ${!notif.read ? '600' : '500'}; margin: 0; color: var(--text-primary); line-height: 1.3;">${notif.title}</h4>
              ${!notif.read ? '<span style="width: 6px; height: 6px; background: var(--color-primary); border-radius: 50%; flex-shrink: 0;"></span>' : ''}
            </div>
            <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0; line-height: 1.4;">${notif.message}</p>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
              <span style="font-size: 0.75rem; color: var(--text-muted);">${notif.time}</span>
              <div style="display: flex; gap: 0.5rem;">
                ${!notif.read ? `
                  <button class="notif-action-btn" onclick="markAsRead(${index})" style="font-size: 0.75rem; color: var(--color-primary); background: none; border: none; padding: 0.25rem 0.5rem; cursor: pointer; border-radius: var(--radius-sm); transition: background-color 0.15s ease;" onmouseover="this.style.background='rgba(59,130,246,0.1)'" onmouseout="this.style.background='none'">
                    Mark Read
                  </button>
                ` : ''}
                <button class="notif-action-btn" onclick="dismissNotification(${index})" style="font-size: 0.75rem; color: var(--text-muted); background: none; border: none; padding: 0.25rem 0.5rem; cursor: pointer; border-radius: var(--radius-sm); transition: all 0.15s ease;" onmouseover="this.style.background='var(--bg-secondary)'; this.style.color='var(--text-primary)'" onmouseout="this.style.background='none'; this.style.color='var(--text-muted)'">
                  Dismiss
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');
    
  }
  
  // Removed fallback demo notifications - now properly shows empty state
  
  // Update badge count
  function updateBadge(count) {
    if (badge) {
      if (count > 0) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = 'flex';
      } else {
        badge.style.display = 'none';
      }
    }
  }
  
  // Get notification icon
  function getNotificationIcon(type) {
    const color = getNotificationColor(type);
    const icons = {
      inventory: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M21 16V8C21 6.89543 20.1046 6 19 6H5C3.89543 6 3 6.89543 3 8V16C3 17.1046 3.89543 18 5 18H19C20.1046 18 21 17.1046 21 16Z"/><path d="M3 10H21"/></svg>`,
      expiry: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><circle cx="12" cy="12" r="10"/><path d="M12 6V12L16 14" stroke-linecap="round"/></svg>`,
      financial: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M12 2V22M17 5H9.5C8.57174 5 7.6815 5.36875 7.02513 6.02513C6.36875 6.6815 6 7.57174 6 8.5C6 9.42826 6.36875 10.3185 7.02513 10.9749C7.6815 11.6313 8.57174 12 9.5 12H14.5C15.4283 12 16.3185 12.3687 16.9749 13.0251C17.6313 13.6815 18 14.5717 18 15.5C18 16.4283 17.6313 17.3185 16.9749 17.9749C16.3185 18.6313 15.4283 19 14.5 19H6" stroke-linecap="round"/></svg>`,
      bir: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z"/><polyline points="14 2 14 8 20 8"/></svg>`,
      fda: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z"/><polyline points="14 2 14 8 20 8"/></svg>`,
      announcement: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z"/><polyline points="14 2 14 8 20 8"/></svg>`,
      success: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${color}" style="stroke-width: 2;"><path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke-linecap="round"/><path d="M22 4L12 14.01L9 11.01" stroke-linecap="round" stroke-linejoin="round"/></svg>`
    };
    return icons[type] || icons.announcement;
  }
  
  // Get notification color
  function getNotificationColor(type) {
    const colors = {
      inventory: '#f59e0b',
      expiry: '#ef4444',
      financial: '#ef4444',
      bir: '#3b82f6',
      fda: '#22c55e',
      announcement: '#3b82f6',
      success: '#22c55e'
    };
    return colors[type] || '#6b7280';
  }
  
  // Listen for dismissal events from other tabs
  window.addEventListener('notificationDismissed', () => {
    loadNotifications();
  });
  
  // Initial badge load
  loadNotifications();
})();

// Global notification action functions
function markAsRead(index) {
  event.stopPropagation();
  const item = document.querySelector(`[data-index="${index}"]`);
  if (!item) return;
  const notificationId = item.getAttribute('data-notification-id');
  if (!notificationId) return;
  
  fetch('/api/notifications.php?action=mark-read', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ notification_id: notificationId })
  })
  .then(r => r.json())
  .then(data => {
    if (data && data.success) {
      // Soft-update UI state
      item.querySelector('h4').style.fontWeight = '500';
      const dot = item.querySelector('span[style*="border-radius: 50%"]');
      if (dot) dot.remove();
      showToast('Notification marked as read', 'success');
      // Refresh dropdown to update counts and ordering
      loadNotifications();
    } else {
      showToast('Failed to mark as read', 'error');
    }
  })
  .catch(() => showToast('Failed to mark as read', 'error'));
}

function dismissNotification(index) {
  event.stopPropagation();
  const item = document.querySelector(`[data-index="${index}"]`);
  if (!item) return;
  
  // Get notification ID from item
  const notificationId = item.getAttribute('data-notification-id');
  if (!notificationId) return;
  
  // Call API to dismiss
  fetch('/api/notifications.php?action=dismiss', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({notification_id: notificationId})
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      item.style.opacity = '0';
      item.style.transform = 'translateX(100%)';
      setTimeout(() => {
        item.remove();
        showToast('Notification dismissed', 'success');
        
        // Update badge count
        const currentCount = parseInt(document.getElementById('notification-badge').textContent);
        if (currentCount > 0) {
          const newCount = currentCount - 1;
          const badge = document.getElementById('notification-badge');
          badge.textContent = newCount;
          if (newCount === 0) {
            badge.style.display = 'none';
          }
        }
        
        // Broadcast to other tabs/windows and pages
        window.dispatchEvent(new CustomEvent('notificationDismissed', {
          detail: {notificationId: notificationId}
        }));
        
        // Broadcast via storage event for cross-tab sync
        localStorage.setItem('lastDismissedNotification', JSON.stringify({
          id: notificationId,
          timestamp: new Date().toISOString()
        }));
      }, 200);
    }
  })
  .catch(error => {
    console.error('Error dismissing notification:', error);
    showToast('Failed to dismiss notification', 'error');
  });
}
</script>
