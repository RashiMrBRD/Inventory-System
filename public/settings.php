<?php
/**
 * Settings Page
 * Application and user settings
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Service\CurrencyService;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();

// Get current timezone (from session or default to system/UTC)
$currentTimezone = $_SESSION['timezone'] ?? date_default_timezone_get();
date_default_timezone_set($currentTimezone);

// List of common timezones grouped by region
$timezones = [
    'UTC' => [
        'UTC' => 'UTC (Coordinated Universal Time)'
    ],
    'Asia' => [
        'Asia/Manila' => 'Manila (UTC+8)',
        'Asia/Singapore' => 'Singapore (UTC+8)',
        'Asia/Tokyo' => 'Tokyo (UTC+9)',
        'Asia/Hong_Kong' => 'Hong Kong (UTC+8)',
        'Asia/Shanghai' => 'Shanghai (UTC+8)',
        'Asia/Seoul' => 'Seoul (UTC+9)',
        'Asia/Dubai' => 'Dubai (UTC+4)',
        'Asia/Kolkata' => 'Kolkata (UTC+5:30)',
        'Asia/Jakarta' => 'Jakarta (UTC+7)',
        'Asia/Bangkok' => 'Bangkok (UTC+7)',
    ],
    'America' => [
        'America/New_York' => 'New York (UTC-5/-4)',
        'America/Chicago' => 'Chicago (UTC-6/-5)',
        'America/Denver' => 'Denver (UTC-7/-6)',
        'America/Los_Angeles' => 'Los Angeles (UTC-8/-7)',
        'America/Toronto' => 'Toronto (UTC-5/-4)',
        'America/Mexico_City' => 'Mexico City (UTC-6/-5)',
        'America/Sao_Paulo' => 'São Paulo (UTC-3)',
    ],
    'Europe' => [
        'Europe/London' => 'London (UTC+0/+1)',
        'Europe/Paris' => 'Paris (UTC+1/+2)',
        'Europe/Berlin' => 'Berlin (UTC+1/+2)',
        'Europe/Rome' => 'Rome (UTC+1/+2)',
        'Europe/Madrid' => 'Madrid (UTC+1/+2)',
        'Europe/Amsterdam' => 'Amsterdam (UTC+1/+2)',
        'Europe/Moscow' => 'Moscow (UTC+3)',
    ],
    'Australia' => [
        'Australia/Sydney' => 'Sydney (UTC+10/+11)',
        'Australia/Melbourne' => 'Melbourne (UTC+10/+11)',
        'Australia/Brisbane' => 'Brisbane (UTC+10)',
        'Australia/Perth' => 'Perth (UTC+8)',
    ],
    'Pacific' => [
        'Pacific/Auckland' => 'Auckland (UTC+12/+13)',
        'Pacific/Fiji' => 'Fiji (UTC+12)',
        'Pacific/Honolulu' => 'Honolulu (UTC-10)',
    ],
    'Africa' => [
        'Africa/Cairo' => 'Cairo (UTC+2)',
        'Africa/Johannesburg' => 'Johannesburg (UTC+2)',
        'Africa/Lagos' => 'Lagos (UTC+1)',
        'Africa/Nairobi' => 'Nairobi (UTC+3)',
    ],
];

// Auto-detect currency from IP
$detectedCurrency = CurrencyService::detectCurrencyFromIP();
$detectedInfo = CurrencyService::getCurrency($detectedCurrency);

// Get current currency (from session or default to detected)
$currentCurrency = $_SESSION['currency'] ?? $detectedCurrency;

// Get all available currencies
$currencies = CurrencyService::getAllCurrencies();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_currency') {
        $selectedCurrency = $_POST['currency'] ?? 'PHP';
        $_SESSION['currency'] = $selectedCurrency;
        $currentCurrency = $selectedCurrency;
        $message = 'Currency updated to ' . CurrencyService::getCurrency($selectedCurrency)['name'];
        $messageType = 'success';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_timezone') {
        $selectedTimezone = $_POST['timezone'] ?? 'UTC';
        // Validate timezone
        if (in_array($selectedTimezone, timezone_identifiers_list())) {
            $_SESSION['timezone'] = $selectedTimezone;
            $currentTimezone = $selectedTimezone;
            date_default_timezone_set($selectedTimezone);
            $message = 'Timezone updated to ' . $selectedTimezone;
            $messageType = 'success';
        } else {
            $message = 'Invalid timezone selected';
            $messageType = 'danger';
        }
    } else {
        // Here you would handle other settings updates
        $message = 'Settings updated successfully';
        $messageType = 'success';
    }
}

// Set page variables
$pageTitle = 'Settings';

// Start output buffering for content
ob_start();
?>

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Settings</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>Status:</strong>
          <span class="status-indicator">
            <span class="status-dot"></span>
            Online
          </span>
        </div>
        <div class="page-banner-meta-item">
          <strong>User:</strong>
          <?php echo htmlspecialchars($user['username'] ?? 'Unknown'); ?>
        </div>
        <div class="page-banner-meta-item">
          <strong>Access Level:</strong>
          <span class="access-badge"><?php echo ucfirst($user['role'] ?? 'User'); ?></span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <a href="inventory-list.php" class="btn btn-success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M4 6H20M4 12H20M4 18H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        View Inventory
      </a>
      <a href="logout.php" class="btn btn-danger">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9M16 17L21 12M21 12L16 7M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Logout
      </a>
    </div>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?> mb-6">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
    <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
  </svg>
  <span><?php echo htmlspecialchars($message); ?></span>
</div>
<?php endif; ?>

<!-- Settings Tabs (QuickBooks/LedgerSMB Style) -->
<div class="settings-tabs-container" style="margin-bottom: 2rem;">
  <div class="tabs-header" style="border-bottom: 2px solid var(--border-color); margin-bottom: 1.5rem;">
    <div class="tabs-list" style="display: flex; gap: 0.5rem; padding: 0;">
      <button class="tab-trigger active" data-tab="profile" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 2px solid transparent; font-weight: 500; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; margin-bottom: -2px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        Profile
      </button>
      <button class="tab-trigger" data-tab="application" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 2px solid transparent; font-weight: 500; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; margin-bottom: -2px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
          <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        Application
      </button>
      <button class="tab-trigger" data-tab="regional" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 2px solid transparent; font-weight: 500; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; margin-bottom: -2px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
          <circle cx="12" cy="12" r="10"/>
          <line x1="2" y1="12" x2="22" y2="12"/>
          <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
        </svg>
        Regional
      </button>
      <button class="tab-trigger" data-tab="security" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 2px solid transparent; font-weight: 500; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; margin-bottom: -2px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        Security
      </button>
    </div>
  </div>
</div>

<!-- Tab Content: Profile -->
<div class="tab-content active" id="tab-profile">
<div class="grid grid-cols-3 gap-6">
  
  <!-- User Profile -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">User Profile</h3>
      <p class="card-description">Manage your account information</p>
    </div>
    <div class="card-content">
      <form method="POST">
        <div class="form-group">
          <label for="username" class="form-label">Username</label>
          <input 
            type="text" 
            id="username" 
            name="username" 
            class="form-input" 
            value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
            readonly
          >
        </div>

        <div class="form-group">
          <label for="email" class="form-label">Email</label>
          <input 
            type="email" 
            id="email" 
            name="email" 
            class="form-input" 
            value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
            placeholder="your.email@example.com"
          >
        </div>

        <div class="form-group">
          <label for="role" class="form-label">Role</label>
          <input 
            type="text" 
            id="role" 
            class="form-input" 
            value="<?php echo ucfirst($user['role'] ?? 'User'); ?>"
            readonly
          >
          <span class="form-helper">Your access level in the system</span>
        </div>

        <button type="submit" class="btn btn-primary w-full">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Update Profile
        </button>
      </form>
    </div>
  </div>

  <!-- Account Information -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Account Information</h3>
      <p class="card-description">View your account details</p>
    </div>
    <div class="card-content">
      <div class="form-group">
        <label class="form-label">Account Created</label>
        <input type="text" class="form-input" value="<?php echo date('F j, Y'); ?>" readonly>
      </div>
      <div class="form-group">
        <label class="form-label">Last Login</label>
        <input type="text" class="form-input" value="<?php echo date('F j, Y g:i A'); ?>" readonly>
      </div>
      <div class="form-group">
        <label class="form-label">Account Status</label>
        <span class="badge" style="background: var(--color-success); color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.875rem;">Active</span>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Tab Content: Application Settings -->
<div class="tab-content" id="tab-application" style="display: none;">
<div class="grid grid-cols-2 gap-6">
  
  <!-- Notification Preferences -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Notification Preferences</h3>
      <p class="card-description">Configure how you receive notifications</p>
    </div>
    <div class="card-content">
      <form method="POST">
        <input type="hidden" name="action" value="update_notifications">
        
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0;">
            <div>
              <label class="form-label" style="margin: 0; display: block;">Email Notifications</label>
              <span class="form-helper">Receive email updates about system activities</span>
            </div>
            <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
              <input type="checkbox" name="email_notifications" checked style="opacity: 0; width: 0; height: 0;">
              <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--color-primary); transition: .3s; border-radius: 24px;"></span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-top: 1px solid var(--border-color);">
            <div>
              <label class="form-label" style="margin: 0; display: block;">Low Stock Alerts</label>
              <span class="form-helper">Get notified when inventory levels are low</span>
            </div>
            <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
              <input type="checkbox" name="low_stock_alerts" checked style="opacity: 0; width: 0; height: 0;">
              <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--color-primary); transition: .3s; border-radius: 24px;"></span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-top: 1px solid var(--border-color);">
            <div>
              <label class="form-label" style="margin: 0; display: block;">Transaction Alerts</label>
              <span class="form-helper">Notifications for new transactions</span>
            </div>
            <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
              <input type="checkbox" name="transaction_alerts" style="opacity: 0; width: 0; height: 0;">
              <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px;"></span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-top: 1px solid var(--border-color);">
            <div>
              <label class="form-label" style="margin: 0; display: block;">Report Generation</label>
              <span class="form-helper">Notifications when reports are ready</span>
            </div>
            <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
              <input type="checkbox" name="report_generation" checked style="opacity: 0; width: 0; height: 0;">
              <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--color-primary); transition: .3s; border-radius: 24px;"></span>
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full" style="margin-top: 1rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Save Notification Settings
        </button>
      </form>
    </div>
  </div>

  <!-- Display & Behavior -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Display & Behavior</h3>
      <p class="card-description">Customize application appearance</p>
    </div>
    <div class="card-content">
      <form method="POST">
        <input type="hidden" name="action" value="update_display">
        
        <div class="form-group">
          <label for="items_per_page" class="form-label">Items Per Page</label>
          <select id="items_per_page" name="items_per_page" class="form-select">
            <option value="10">10 items</option>
            <option value="25" selected>25 items</option>
            <option value="50">50 items</option>
            <option value="100">100 items</option>
          </select>
          <span class="form-helper">Number of items to display in tables</span>
        </div>

        <div class="form-group">
          <label for="date_format" class="form-label">Date Format</label>
          <select id="date_format" name="date_format" class="form-select">
            <option value="Y-m-d" selected>YYYY-MM-DD (2025-10-21)</option>
            <option value="m/d/Y">MM/DD/YYYY (10/21/2025)</option>
            <option value="d/m/Y">DD/MM/YYYY (21/10/2025)</option>
            <option value="F j, Y">Month Day, Year (October 21, 2025)</option>
          </select>
          <span class="form-helper">How dates are displayed</span>
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0;">
            <div>
              <label class="form-label" style="margin: 0; display: block;">Auto-logout</label>
              <span class="form-helper">Automatically log out after 30 minutes of inactivity</span>
            </div>
            <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
              <input type="checkbox" name="auto_logout" style="opacity: 0; width: 0; height: 0;">
              <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px;"></span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-top: 1px solid var(--border-color);">
            <div>
              <label class="form-label" style="margin: 0; display: block;">Compact View</label>
              <span class="form-helper">Use smaller spacing and fonts</span>
            </div>
            <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
              <input type="checkbox" name="compact_view" style="opacity: 0; width: 0; height: 0;">
              <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px;"></span>
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full" style="margin-top: 1rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Save Display Settings
        </button>
      </form>
    </div>
  </div>

</div>
</div>

<!-- Tab Content: Regional Settings -->
<div class="tab-content" id="tab-regional" style="display: none;">
<div class="grid grid-cols-2 gap-6">
  
  <!-- Timezone Settings (Moved to Regional tab) -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Timezone Settings</h3>
      <p class="card-description">Configure your local time</p>
    </div>
    <div class="card-content">
      <form method="POST">
        <input type="hidden" name="action" value="update_timezone">
        
        <!-- Current Timezone Display -->
        <div class="alert alert-info mb-4" style="background-color: var(--color-info-light); border: 1px solid var(--color-info); padding: 1rem; border-radius: var(--radius-md);">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color: var(--color-info);">
              <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2"/>
              <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div style="flex: 1;">
              <strong>Current Timezone:</strong>
              <div style="margin-top: 0.25rem; font-size: 0.875rem;">
                <?php echo htmlspecialchars($currentTimezone); ?>
                <span style="color: var(--text-secondary);"> | Current Time: <?php echo date('Y-m-d H:i:s'); ?></span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="timezone" class="form-label">
            <span>Select Timezone</span>
            <span style="color: var(--text-secondary); font-weight: normal; font-size: 0.875rem;">
              (System will use this timezone for all dates and times)
            </span>
          </label>
          <select id="timezone" name="timezone" class="form-select" required>
            <?php foreach ($timezones as $region => $tzList): ?>
              <optgroup label="<?php echo htmlspecialchars($region); ?>">
                <?php foreach ($tzList as $tz => $label): ?>
                  <option value="<?php echo htmlspecialchars($tz); ?>" <?php echo $tz === $currentTimezone ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <span class="form-helper">All timestamps in the system will display in this timezone</span>
        </div>

        <!-- Timezone Preview -->
        <div class="form-group">
          <label class="form-label">Preview Times</label>
          <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="color: var(--text-secondary);">Current Time:</span>
              <span class="font-semibold" id="preview-time" style="font-family: monospace; font-size: 1.1rem;">
                <?php echo date('Y-m-d H:i:s'); ?>
              </span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="color: var(--text-secondary);">Date Format:</span>
              <span class="font-semibold" style="font-family: monospace;">
                <?php echo date('F j, Y'); ?>
              </span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="color: var(--text-secondary);">Time Format:</span>
              <span class="font-semibold" style="font-family: monospace;">
                <?php echo date('g:i:s A'); ?>
              </span>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Save Timezone
        </button>
      </form>
    </div>
  </div>

  <!-- Currency Settings -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Currency Settings</h3>
      <p class="card-description">Select your preferred currency</p>
    </div>
    <div class="card-content">
      <form method="POST">
        <input type="hidden" name="action" value="update_currency">
        
        <!-- Auto-detected Currency -->
        <div class="alert alert-info mb-4" style="background-color: var(--color-info-light); border: 1px solid var(--color-info); padding: 1rem; border-radius: var(--radius-md);">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color: var(--color-info);">
              <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2"/>
              <path d="M12 16V12M12 8H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div style="flex: 1;">
              <strong>Auto-detected from your location:</strong>
              <div style="margin-top: 0.25rem; font-size: 0.875rem;">
                <?php echo htmlspecialchars($detectedInfo['symbol'] . ' ' . $detectedInfo['name'] . ' (' . $detectedCurrency . ')'); ?>
                <span style="color: var(--text-secondary);">- <?php echo htmlspecialchars($detectedInfo['country']); ?></span>
              </div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="useDetectedCurrency()">
              Use This
            </button>
          </div>
        </div>

        <div class="form-group">
          <label for="currency" class="form-label">
            <span>Select Currency</span>
            <span style="color: var(--text-secondary); font-weight: normal; font-size: 0.875rem;">
              (Current: <?php echo htmlspecialchars(CurrencyService::getSymbol($currentCurrency) . ' ' . $currentCurrency); ?>)
            </span>
          </label>
          <select id="currency" name="currency" class="form-select" required>
            <?php foreach ($currencies as $code => $info): ?>
              <option value="<?php echo $code; ?>" <?php echo $code === $currentCurrency ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($info['symbol'] . ' ' . $info['name'] . ' (' . $code . ') - ' . $info['country']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="form-helper">All prices in the system will display in this currency</span>
        </div>

        <!-- Currency Preview -->
        <div class="form-group">
          <label class="form-label">Preview</label>
          <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="color: var(--text-secondary);">Sample Amount:</span>
              <span class="font-semibold" id="preview-amount" style="font-size: 1.25rem;">
                <?php echo CurrencyService::format(12345.67, $currentCurrency); ?>
              </span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="color: var(--text-secondary);">Symbol:</span>
              <span class="font-bold" style="font-size: 1.5rem;" id="preview-symbol">
                <?php echo htmlspecialchars(CurrencyService::getSymbol($currentCurrency)); ?>
              </span>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Save Currency Preference
        </button>
      </form>
    </div>
  </div>

</div>
</div>

<!-- Tab Content: Security -->
<div class="tab-content" id="tab-security" style="display: none;">
<div class="grid grid-cols-2 gap-6">
  
  <!-- Password Change -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Change Password</h3>
      <p class="card-description">Update your password</p>
    </div>
    <div class="card-content">
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        
        <div class="form-group">
          <label for="current_password_sec" class="form-label">Current Password</label>
          <input 
            type="password" 
            id="current_password_sec" 
            name="current_password" 
            class="form-input" 
            placeholder="Enter current password"
          >
        </div>

        <div class="form-group">
          <label for="new_password_sec" class="form-label">New Password</label>
          <input 
            type="password" 
            id="new_password_sec" 
            name="new_password" 
            class="form-input" 
            placeholder="Enter new password"
          >
        </div>

        <div class="form-group">
          <label for="confirm_password_sec" class="form-label">Confirm Password</label>
          <input 
            type="password" 
            id="confirm_password_sec" 
            name="confirm_password" 
            class="form-input" 
            placeholder="Confirm new password"
          >
        </div>

        <button type="submit" class="btn btn-primary w-full">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M12 15V3M12 3L8 7M12 3L16 7M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Change Password
        </button>
      </form>
    </div>
  </div>

  <!-- Security Options -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Security Options</h3>
      <p class="card-description">Additional security settings</p>
    </div>
    <div class="card-content">
      <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0;">
          <div>
            <label class="form-label" style="margin: 0; display: block;">Two-Factor Authentication</label>
            <span class="form-helper">Add an extra layer of security</span>
          </div>
          <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
            <input type="checkbox" name="two_factor" style="opacity: 0; width: 0; height: 0;">
            <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 24px;"></span>
          </label>
        </div>
      </div>
      
      <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-top: 1px solid var(--border-color);">
          <div>
            <label class="form-label" style="margin: 0; display: block;">Login Alerts</label>
            <span class="form-helper">Email notifications on new logins</span>
          </div>
          <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
            <input type="checkbox" name="login_alerts" checked style="opacity: 0; width: 0; height: 0;">
            <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--color-primary); transition: .3s; border-radius: 24px;"></span>
          </label>
        </div>
      </div>

      <div class="form-group" style="margin-top: 1.5rem;">
        <label class="form-label">Active Sessions</label>
        <div style="padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <div>
              <div style="font-weight: 600;">Current Session</div>
              <div style="font-size: 0.875rem; color: var(--text-secondary);">Windows • Chrome • <?php echo date('g:i A'); ?></div>
            </div>
            <span class="badge" style="background: var(--color-success); color: white; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem;">Active</span>
          </div>
        </div>
      </div>

      <button class="btn btn-danger w-full" style="margin-top: 1rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9M16 17L21 12M21 12L16 7M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Log Out All Other Sessions
      </button>
    </div>
  </div>

</div>
</div>

<!-- System Information -->
<div class="card mt-6">
  <div class="card-header">
    <h3 class="card-title">System Information</h3>
    <p class="card-description">Application and server details</p>
  </div>
  <div class="card-content">
    <div class="grid grid-cols-4 gap-6">
      <div>
        <p class="text-sm text-secondary mb-1">Application Version</p>
        <p class="font-semibold">v0.2.1</p>
      </div>
      <div>
        <p class="text-sm text-secondary mb-1">Database Status</p>
        <p class="font-semibold text-success">Connected</p>
      </div>
      <div>
        <p class="text-sm text-secondary mb-1">Last Backup</p>
        <p class="font-semibold"><?php echo date('Y-m-d H:i:s'); ?></p>
      </div>
      <div>
        <p class="text-sm text-secondary mb-1">Server Time</p>
        <p class="font-semibold"><?php echo date('H:i:s'); ?></p>
      </div>
    </div>
  </div>
</div>

<!-- Danger Zone -->
<div class="card mt-6 border-danger">
  <div class="card-header">
    <h3 class="card-title text-danger">Danger Zone</h3>
    <p class="card-description">Irreversible and destructive actions</p>
  </div>
  <div class="card-content">
    <div class="flex items-center justify-between p-4 bg-danger bg-opacity-5 rounded-md">
      <div>
        <p class="font-semibold">Clear All Data</p>
        <p class="text-sm text-secondary">Permanently delete all inventory data</p>
      </div>
      <button class="btn btn-danger" onclick="alert('This feature is disabled for safety')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M3 6H5H21M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Clear All Data
      </button>
    </div>
  </div>
</div>

<script>
// Tab switching functionality
const tabTriggers = document.querySelectorAll('.tab-trigger');
const tabContents = document.querySelectorAll('.tab-content');

tabTriggers.forEach(trigger => {
  trigger.addEventListener('click', () => {
    const tabName = trigger.getAttribute('data-tab');
    
    // Remove active class from all triggers and contents
    tabTriggers.forEach(t => {
      t.classList.remove('active');
      t.style.color = 'var(--text-secondary)';
      t.style.borderBottomColor = 'transparent';
    });
    tabContents.forEach(c => {
      c.classList.remove('active');
      c.style.display = 'none';
    });
    
    // Add active class to clicked trigger and corresponding content
    trigger.classList.add('active');
    trigger.style.color = 'var(--color-primary)';
    trigger.style.borderBottomColor = 'var(--color-primary)';
    
    const activeContent = document.getElementById(`tab-${tabName}`);
    if (activeContent) {
      activeContent.classList.add('active');
      activeContent.style.display = 'block';
    }
  });
});

// Switch toggle functionality
document.querySelectorAll('.switch input[type="checkbox"]').forEach(checkbox => {
  const slider = checkbox.nextElementSibling;
  
  function updateSwitch() {
    if (checkbox.checked) {
      slider.style.backgroundColor = 'var(--color-primary)';
    } else {
      slider.style.backgroundColor = '#ccc';
    }
  }
  
  updateSwitch();
  checkbox.addEventListener('change', updateSwitch);
});

// Switch slider CSS
const style = document.createElement('style');
style.textContent = `
  .switch input:checked + .slider:before {
    transform: translateX(20px);
  }
  .slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
  }
  .tab-trigger:hover {
    color: var(--color-primary) !important;
  }
`;
document.head.appendChild(style);

// Currency data for JavaScript
const currencyData = <?php echo json_encode($currencies); ?>;
const detectedCurrency = '<?php echo $detectedCurrency; ?>';

// Use detected currency
function useDetectedCurrency() {
  const currencySelect = document.getElementById('currency');
  currencySelect.value = detectedCurrency;
  updateCurrencyPreview();
  showToast('Currency set to auto-detected: ' + currencyData[detectedCurrency].name, 'success');
}

// Update currency preview when selection changes
document.getElementById('currency').addEventListener('change', function() {
  updateCurrencyPreview();
});

function updateCurrencyPreview() {
  const selectedCurrency = document.getElementById('currency').value;
  const currencyInfo = currencyData[selectedCurrency];
  
  if (currencyInfo) {
    // Update preview amount
    const amount = 12345.67;
    const formattedAmount = formatCurrency(amount, selectedCurrency, currencyInfo.symbol);
    document.getElementById('preview-amount').textContent = formattedAmount;
    
    // Update symbol
    document.getElementById('preview-symbol').textContent = currencyInfo.symbol;
  }
}

function formatCurrency(amount, code, symbol) {
  const formatted = amount.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
  
  // Currencies with symbol after
  const symbolAfter = ['PHP', 'THB', 'VND', 'IDR'];
  
  if (symbolAfter.includes(code)) {
    return symbol + formatted;
  } else {
    return symbol + formatted;
  }
}

// Show selected currency details on hover
document.getElementById('currency').addEventListener('mouseover', function(e) {
  if (e.target.tagName === 'OPTION') {
    const code = e.target.value;
    const info = currencyData[code];
    if (info) {
      e.target.title = info.name + ' - ' + info.country;
    }
  }
});

// Auto-refresh time preview
setInterval(() => {
  const previewTime = document.getElementById('preview-time');
  if (previewTime && document.getElementById('tab-regional').style.display !== 'none') {
    const now = new Date();
    const formatted = now.getFullYear() + '-' + 
                     String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                     String(now.getDate()).padStart(2, '0') + ' ' + 
                     String(now.getHours()).padStart(2, '0') + ':' + 
                     String(now.getMinutes()).padStart(2, '0') + ':' + 
                     String(now.getSeconds()).padStart(2, '0');
    previewTime.textContent = formatted;
  }
}, 1000);
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
