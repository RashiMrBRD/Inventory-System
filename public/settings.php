<?php
/**
 * Settings Page
 * Application and user settings
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Service\CurrencyService;
use App\Service\FontService;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();

// Load current SMTP configuration
$appConfig = require __DIR__ . '/../config/app.php';
$smtpConfigured = !empty($appConfig['mail']['host']) && !empty($appConfig['mail']['username']);

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

// Get current font (from session or default to 'system')
$currentFont = $_SESSION['font_family'] ?? 'system';

// Get current theme (from session or default to 'system')
$currentTheme = $_SESSION['theme'] ?? 'system';

// Initialize FontService (works with or without database)
$fontService = new FontService();

// Get custom fonts (from database or JSON file)
$customFonts = [];
try {
    $dbCustomFonts = $fontService->getAllCustomFonts();
    foreach ($dbCustomFonts as $dbFont) {
        $fontKey = $fontService->sanitizeFileName($dbFont['font_family']);
        $customFonts[$fontKey] = [
            'id' => $dbFont['id'],
            'name' => $dbFont['font_name'],
            'description' => 'Custom uploaded font - ' . ucfirst($dbFont['font_category']),
            'sample' => 'The quick brown fox jumps over the lazy dog',
            'stack' => $dbFont['font_family'],
            'is_custom' => true
        ];
    }
} catch (Exception $e) {
    // Silently continue - fonts will use JSON fallback
}

// Available font families (system + custom)
$availableFonts = [
    'system' => [
        'name' => 'System UI',
        'description' => 'Native system fonts (Segoe UI, San Francisco, Roboto)',
        'sample' => 'The quick brown fox jumps over the lazy dog',
        'stack' => '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto'
    ],
    'geometric' => [
        'name' => 'Geometric Sans',
        'description' => 'Clean and modern (Arial, Helvetica)',
        'sample' => 'The quick brown fox jumps over the lazy dog',
        'stack' => 'Arial, Helvetica Neue, Helvetica'
    ],
    'humanist' => [
        'name' => 'Humanist Sans',
        'description' => 'Friendly and readable (Segoe UI, Tahoma, Verdana)',
        'sample' => 'The quick brown fox jumps over the lazy dog',
        'stack' => 'Segoe UI, Tahoma, Geneva, Verdana'
    ],
    'transitional' => [
        'name' => 'Transitional',
        'description' => 'Professional balance (Trebuchet MS)',
        'sample' => 'The quick brown fox jumps over the lazy dog',
        'stack' => 'Trebuchet MS, Lucida Grande'
    ],
    'monospace' => [
        'name' => 'Monospace',
        'description' => 'Fixed-width for technical displays (Consolas, Monaco)',
        'sample' => 'The quick brown fox jumps over the lazy dog',
        'stack' => 'Consolas, Monaco, Courier New'
    ],
    'serif' => [
        'name' => 'Classical Serif',
        'description' => 'Traditional and elegant (Georgia, Times)',
        'sample' => 'The quick brown fox jumps over the lazy dog',
        'stack' => 'Georgia, Times New Roman, Times'
    ],
    'modern-serif' => [
        'name' => 'Modern Serif',
        'description' => 'Contemporary serif (Cambria)',
        'sample' => 'The quick brown fox jumps over the lazy dog',
        'stack' => 'Cambria, Hoefler Text, Liberation Serif'
    ]
];

// Merge system fonts with custom fonts
$availableFonts = array_merge($availableFonts, $customFonts);

// Handle form submission
// Check if user is using demo credentials
$isDemoUser = ($user['username'] === 'admin' && isset($_POST['verify_demo']));
if (!$isDemoUser && $user['username'] === 'admin') {
    // Verify if this is actually the demo account by checking password
    $testUser = $authController->login('admin', 'admin123');
    $isDemoUser = $testUser['success'] ?? false;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        
        // Prevent demo users from changing username/email
        if ($isDemoUser) {
            $_SESSION['flash_message'] = 'Demo account cannot modify username or email';
            $_SESSION['flash_type'] = 'error';
        } else {
            // Validate email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash_message'] = 'Valid email address is required';
                $_SESSION['flash_type'] = 'error';
            } else {
                // Validate role
                $allowedRoles = ['user', 'admin', 'manager', 'viewer'];
                if (!in_array($role, $allowedRoles)) {
                    $role = 'user';
                }
                
                // Update user data
                $updateData = [
                    'email' => $email,
                    'role' => $role
                ];
                
                $result = $authController->updateUserProfile((string)$user['_id'], $updateData);
                
                if ($result['success']) {
                    // Refresh user data
                    $user = $authController->getCurrentUser();
                    $_SESSION['flash_message'] = 'Profile updated successfully';
                    $_SESSION['flash_type'] = 'success';
                    
                    // Store active tab for persistence
                    $_SESSION['active_settings_tab'] = $_POST['active_tab'] ?? 'tab-profile';
                } else {
                    $_SESSION['flash_message'] = $result['message'] ?? 'Failed to update profile';
                    $_SESSION['flash_type'] = 'error';
                }
            }
        }
        
        // Redirect to prevent form resubmission (POST-redirect-GET pattern)
        header('Location: settings.php');
        exit();
    } else
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
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        // Handle password change
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Prevent demo users from changing password
        if ($isDemoUser) {
            $_SESSION['flash_message'] = 'Demo account cannot change password. Please use a personal account.';
            $_SESSION['flash_type'] = 'error';
            $_SESSION['active_settings_tab'] = 'tab-security';
        } else {
            // Validate inputs
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $_SESSION['flash_message'] = 'All password fields are required';
                $_SESSION['flash_type'] = 'error';
                $_SESSION['active_settings_tab'] = 'tab-security';
            } elseif ($newPassword !== $confirmPassword) {
                $_SESSION['flash_message'] = 'New passwords do not match';
                $_SESSION['flash_type'] = 'error';
                $_SESSION['active_settings_tab'] = 'tab-security';
            } elseif (strlen($newPassword) < 6) {
                $_SESSION['flash_message'] = 'New password must be at least 6 characters';
                $_SESSION['flash_type'] = 'error';
                $_SESSION['active_settings_tab'] = 'tab-security';
            } else {
                // Verify current password
                $loginResult = $authController->login($user['username'], $currentPassword);
                
                if ($loginResult['success']) {
                    // Update password
                    $result = $authController->updatePassword((string)$user['_id'], $newPassword);
                    
                    if ($result['success']) {
                        $_SESSION['flash_message'] = 'Password changed successfully';
                        $_SESSION['flash_type'] = 'success';
                        $_SESSION['active_settings_tab'] = 'tab-security';
                    } else {
                        $_SESSION['flash_message'] = $result['message'] ?? 'Failed to change password';
                        $_SESSION['flash_type'] = 'error';
                        $_SESSION['active_settings_tab'] = 'tab-security';
                    }
                } else {
                    $_SESSION['flash_message'] = 'Current password is incorrect';
                    $_SESSION['flash_type'] = 'error';
                    $_SESSION['active_settings_tab'] = 'tab-security';
                }
            }
        }
        
        // Redirect to prevent form resubmission
        header('Location: settings.php');
        exit();
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_font') {
        $selectedFont = $_POST['font_family'] ?? 'system';
        // Validate font selection
        if (array_key_exists($selectedFont, $availableFonts)) {
            $_SESSION['font_family'] = $selectedFont;
            $currentFont = $selectedFont;
            $message = 'Font updated to ' . $availableFonts[$selectedFont]['name'];
            $messageType = 'success';
        } else {
            $message = 'Invalid font selected';
            $messageType = 'danger';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'upload_font') {
        // Handle font upload
        if (isset($_FILES['font_file']) && $_FILES['font_file']['error'] === UPLOAD_ERR_OK) {
            $fontName = trim($_POST['font_name'] ?? '');
            $fontFamily = trim($_POST['font_family_name'] ?? '');
            $fontCategory = $_POST['font_category'] ?? 'sans-serif';
            
            if (empty($fontName) || empty($fontFamily)) {
                $message = 'Font name and family are required';
                $messageType = 'danger';
            } else {
                $result = $fontService->uploadFont(
                    $_FILES['font_file'],
                    $fontName,
                    $fontFamily,
                    $fontCategory,
                    $user['id'] ?? null
                );
                
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                
                // Reload custom fonts if successful
                if ($result['success']) {
                    header('Location: settings.php?tab=regional&upload=success');
                    exit;
                }
            }
        } else {
            $message = 'Please select a font file to upload';
            $messageType = 'danger';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_font') {
        $fontId = (int)($_POST['font_id'] ?? 0);
        if ($fontId > 0) {
            $result = $fontService->deleteFont($fontId);
            $message = $result ? 'Font deleted successfully' : 'Failed to delete font';
            $messageType = $result ? 'success' : 'danger';
            
            if ($result) {
                header('Location: settings.php?tab=regional&delete=success');
                exit;
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_theme') {
        $selectedTheme = $_POST['theme'] ?? 'system';
        $allowedThemes = ['light', 'dark', 'system'];
        
        if (in_array($selectedTheme, $allowedThemes)) {
            $_SESSION['theme'] = $selectedTheme;
            $currentTheme = $selectedTheme;
            $message = 'Theme updated to ' . ucfirst($selectedTheme);
            $messageType = 'success';
        } else {
            $message = 'Invalid theme selected';
            $messageType = 'danger';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_smtp') {
        // SMTP Configuration Update
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = trim($_POST['smtp_port'] ?? '587');
        $smtpUsername = trim($_POST['smtp_username'] ?? '');
        $smtpPassword = $_POST['smtp_password'] ?? '';
        $smtpEncryption = $_POST['smtp_encryption'] ?? 'tls';
        $smtpFromAddress = trim($_POST['smtp_from_address'] ?? '');
        $smtpFromName = trim($_POST['smtp_from_name'] ?? '');
        
        // If password is blank, keep the existing password from config
        if (empty($smtpPassword) && !empty($appConfig['mail']['password'])) {
            $smtpPassword = $appConfig['mail']['password'];
        }
        
        // Validate required fields
        if (empty($smtpHost) || empty($smtpUsername) || empty($smtpFromAddress)) {
            $message = 'SMTP Host, Username, and From Address are required';
            $messageType = 'danger';
        } else {
            // Update config/app.php file
            $configPath = __DIR__ . '/../config/app.php';
            $configContent = file_get_contents($configPath);
            
            // Build new mail configuration with getenv() structure
            $newMailConfig = "    'mail' => [\n";
            $newMailConfig .= "        'driver' => getenv('MAIL_DRIVER') ?: 'smtp', // smtp, sendmail, mailgun, etc\n";
            $newMailConfig .= "        'host' => getenv('MAIL_HOST') ?: '" . addslashes($smtpHost) . "',\n";
            $newMailConfig .= "        'port' => getenv('MAIL_PORT') ?: " . intval($smtpPort) . ",\n";
            $newMailConfig .= "        'username' => getenv('MAIL_USERNAME') ?: '" . addslashes($smtpUsername) . "',\n";
            $newMailConfig .= "        'password' => getenv('MAIL_PASSWORD') ?: '" . addslashes($smtpPassword) . "',\n";
            $newMailConfig .= "        'encryption' => getenv('MAIL_ENCRYPTION') ?: '" . addslashes($smtpEncryption) . "',\n";
            $newMailConfig .= "        'from' => [\n";
            $newMailConfig .= "            'address' => getenv('MAIL_FROM_ADDRESS') ?: '" . addslashes($smtpFromAddress) . "',\n";
            $newMailConfig .= "            'name' => getenv('MAIL_FROM_NAME') ?: '" . addslashes($smtpFromName) . "'\n";
            $newMailConfig .= "        ]\n";
            $newMailConfig .= "    ]";
            
            // Replace the mail configuration section
            // Match the entire 'mail' => [...] block including nested arrays
            $pattern = "/'mail'\s*=>\s*\[\s*(?:[^[\]]*|\[(?:[^[\]]*|\[[^\]]*\])*\])*\s*\]/s";
            $configContent = preg_replace($pattern, $newMailConfig, $configContent);
            
            // Write back to file
            if (file_put_contents($configPath, $configContent)) {
                $message = 'SMTP configuration updated successfully. Email features are now enabled!';
                $messageType = 'success';
                $smtpConfigured = true;
                // Reload config
                $appConfig = require $configPath;
            } else {
                $message = 'Failed to update SMTP configuration. Check file permissions for config/app.php.';
                $messageType = 'danger';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'test_smtp') {
        // SMTP Connection Test
        header('Content-Type: application/json');
        
        // Get SMTP credentials from form or config
        $smtpHost = trim($_POST['smtp_host'] ?? $appConfig['mail']['host'] ?? '');
        $smtpPort = intval($_POST['smtp_port'] ?? $appConfig['mail']['port'] ?? 587);
        $smtpUsername = trim($_POST['smtp_username'] ?? $appConfig['mail']['username'] ?? '');
        $smtpPassword = $_POST['smtp_password'] ?? $appConfig['mail']['password'] ?? '';
        $smtpEncryption = $_POST['smtp_encryption'] ?? $appConfig['mail']['encryption'] ?? 'tls';
        
        if (empty($smtpHost) || empty($smtpUsername)) {
            echo json_encode([
                'success' => false,
                'message' => 'Please fill in SMTP Host and Username before testing'
            ]);
            exit();
        }
        
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = $smtpEncryption;
            $mail->Port = $smtpPort;
            $mail->Timeout = 10;
            
            // Test connection
            if ($mail->smtpConnect()) {
                $mail->smtpClose();
                echo json_encode([
                    'success' => true,
                    'message' => 'SMTP connection successful! Your email configuration is working correctly.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'SMTP connection failed. Please check your credentials and try again.'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'SMTP Test Error: ' . $mail->ErrorInfo
            ]);
        }
        exit();
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
      <a href="dashboard.php" class="btn" style="background: var(--color-primary); color: white; border: 1px solid var(--color-primary); transition: all 0.2s;" onmouseover="this.style.background='hsl(221 83% 48%)'; this.style.borderColor='hsl(221 83% 48%)'" onmouseout="this.style.background='var(--color-primary)'; this.style.borderColor='var(--color-primary)'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        Back to Dashboard
      </a>
    </div>
  </div>
</div>

<!-- Flash messages will be displayed via Toast notifications -->

<!-- Settings Tabs -->
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
      <button class="tab-trigger" data-tab="system" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 2px solid transparent; font-weight: 500; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; margin-bottom: -2px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
          <path d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
          <path d="M4 12c0 2.21 3.582 4 8 4s8-1.79 8-4"/>
        </svg>
        System Configuration
      </button>
    </div>
  </div>
</div>

<!-- Tab Content: System Configuration -->
<div class="tab-content" id="tab-system" style="display: none;">

<!-- System Overview Cards -->
<div class="grid grid-cols-3 gap-6" style="margin-bottom: 2rem;">
  
  <!-- Email Status Card -->
  <div class="card" style="border: 1px solid var(--border-color); background: <?php echo $smtpConfigured ? 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)' : 'linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)'; ?>;">
    <div class="card-content" style="padding: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <div style="width: 48px; height: 48px; background: <?php echo $smtpConfigured ? '#10b981' : '#f59e0b'; ?>; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="M22 7L13.03 12.7C12.7237 12.8934 12.3663 12.9972 12 12.9972C11.6337 12.9972 11.2763 12.8934 10.97 12.7L2 7"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 0.375rem; font-weight: 500;">Email Service</div>
          <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"><?php echo $smtpConfigured ? 'Active' : 'Inactive'; ?></div>
        </div>
      </div>
      <?php if ($smtpConfigured): ?>
        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: #d1fae5; border-radius: 8px; font-size: 0.875rem; color: #065f46; border: 1px solid #a7f3d0;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          Ready to send emails
        </div>
      <?php else: ?>
        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: #fef3c7; border-radius: 8px; font-size: 0.875rem; color: #92400e; border: 1px solid #fde68a;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          Configuration required
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Database Status Card -->
  <div class="card" style="border: 1px solid var(--border-color); background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
    <div class="card-content" style="padding: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <div style="width: 48px; height: 48px; background: #10b981; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <ellipse cx="12" cy="5" rx="9" ry="3"/>
            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 0.375rem; font-weight: 500;">Database</div>
          <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">Connected</div>
        </div>
      </div>
      <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: #d1fae5; border-radius: 8px; font-size: 0.875rem; color: #065f46; border: 1px solid #a7f3d0;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        MongoDB operational
      </div>
    </div>
  </div>

  <!-- System Health Card -->
  <div class="card" style="border: 1px solid var(--border-color); background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
    <div class="card-content" style="padding: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <div style="width: 48px; height: 48px; background: #10b981; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M12 20V10"/>
            <path d="M18 20V4"/>
            <path d="M6 20v-4"/>
          </svg>
        </div>
        <div style="flex: 1;">
          <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 0.375rem; font-weight: 500;">System Health</div>
          <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);">Healthy</div>
        </div>
      </div>
      <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: #d1fae5; border-radius: 8px; font-size: 0.875rem; color: #065f46; border: 1px solid #a7f3d0;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        All systems operational
      </div>
    </div>
  </div>

</div>

<!-- Configuration Cards -->
<div class="grid grid-cols-1 gap-6" style="align-items: start;">
  
  <!-- SMTP Email Configuration -->
  <div class="card" style="height: fit-content;">
    <div class="card-header">
      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
          <h3 class="card-title" style="margin-bottom: 0.25rem;">SMTP Email Configuration</h3>
          <p class="card-description" style="margin: 0;">Configure email server settings for sending emails</p>
        </div>
        <div>
          <?php if ($smtpConfigured): ?>
            <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: hsl(143 85% 96%); color: hsl(140 61% 13%); border-radius: 8px; font-size: 0.875rem; font-weight: 600;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
              </svg>
              SMTP Configured
            </span>
          <?php else: ?>
            <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: hsl(48 96% 89%); color: hsl(25 95% 16%); border-radius: 8px; font-size: 0.875rem; font-weight: 600;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              Not Configured
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card-content" style="padding: 1.5rem;">
      <form method="POST" id="smtpConfigForm">
        <input type="hidden" name="action" value="update_smtp">
        
        <!-- Quick Setup Guide -->
        <div style="background: var(--bg-secondary); border: 2px solid var(--border-color); padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
          <div style="display: flex; align-items: start; gap: 1rem;">
            <div style="width: 40px; height: 40px; background: var(--color-primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                <path d="M12 2L2 7L12 12L22 7L12 2Z"/>
                <path d="M2 17L12 22L22 17"/>
                <path d="M2 12L12 17L22 12"/>
              </svg>
            </div>
            <div style="flex: 1;">
              <h4 style="margin: 0 0 0.75rem 0; font-size: 1.0625rem; font-weight: 600; color: var(--text-primary);">Email Setup Guide</h4>
              <p style="margin: 0 0 1rem 0; font-size: 0.875rem; line-height: 1.6; color: var(--text-secondary);">Configure your SMTP server to enable email features across the application (invoices, orders, quotations, and notifications).</p>
              
              <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1rem;">
                <div style="padding: 1rem; background: white; border-radius: 8px; border: 1px solid var(--border-color);">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="width: 24px; height: 24px; background: var(--bg-secondary); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-primary)" stroke-width="2.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                      </svg>
                    </div>
                    <strong style="font-size: 0.8125rem; color: var(--text-primary);">Gmail</strong>
                  </div>
                  <p style="margin: 0; font-size: 0.75rem; color: var(--text-secondary); line-height: 1.5;">smtp.gmail.com<br>Port: 587 (TLS)<br><em>Use App Password</em></p>
                </div>
                
                <div style="padding: 1rem; background: white; border-radius: 8px; border: 1px solid var(--border-color);">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="width: 24px; height: 24px; background: var(--bg-secondary); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-primary)" stroke-width="2.5">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <path d="M22 6l-10 7L2 6"/>
                      </svg>
                    </div>
                    <strong style="font-size: 0.8125rem; color: var(--text-primary);">Outlook</strong>
                  </div>
                  <p style="margin: 0; font-size: 0.75rem; color: var(--text-secondary); line-height: 1.5;">smtp.office365.com<br>Port: 587 (TLS)<br><em>Office365 account</em></p>
                </div>
                
                <div style="padding: 1rem; background: white; border-radius: 8px; border: 1px solid var(--border-color);">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="width: 24px; height: 24px; background: var(--bg-secondary); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-primary)" stroke-width="2.5">
                        <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                      </svg>
                    </div>
                    <strong style="font-size: 0.8125rem; color: var(--text-primary);">Brevo</strong>
                  </div>
                  <p style="margin: 0; font-size: 0.75rem; color: var(--text-secondary); line-height: 1.5;">smtp-relay.brevo.com<br>Port: 587 (TLS)<br><em>SMTP Key required</em></p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
          <div class="form-group">
            <label for="smtp_host" class="form-label" style="display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.5rem;">
              SMTP Host <span style="color: hsl(0 74% 50%);">*</span>
            </label>
            <input 
              type="text" 
              id="smtp_host" 
              name="smtp_host" 
              class="form-input" 
              placeholder="smtp.gmail.com" 
              value="<?php echo htmlspecialchars($appConfig['mail']['host'] ?? ''); ?>"
              required
              style="width: 100%;"
            >
            <span class="form-helper" style="display: block; margin-top: 0.375rem;">Your SMTP server address</span>
          </div>

          <div class="form-group">
            <label for="smtp_port" class="form-label" style="display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.5rem;">
              SMTP Port <span style="color: hsl(0 74% 50%);">*</span>
            </label>
            <input 
              type="number" 
              id="smtp_port" 
              name="smtp_port" 
              class="form-input" 
              placeholder="587" 
              value="<?php echo htmlspecialchars($appConfig['mail']['port'] ?? '587'); ?>"
              required
              style="width: 100%;"
            >
            <span class="form-helper" style="display: block; margin-top: 0.375rem;">Usually 587 (TLS) or 465 (SSL)</span>
          </div>

          <div class="form-group">
            <label for="smtp_username" class="form-label" style="display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.5rem;">
              SMTP Username <span style="color: hsl(0 74% 50%);">*</span>
            </label>
            <input 
              type="text" 
              id="smtp_username" 
              name="smtp_username" 
              class="form-input" 
              placeholder="your-email@example.com" 
              value="<?php echo htmlspecialchars($appConfig['mail']['username'] ?? ''); ?>"
              required
              style="width: 100%;"
            >
            <span class="form-helper" style="display: block; margin-top: 0.375rem;">Your email address or username</span>
          </div>

          <div class="form-group">
            <label for="smtp_password" class="form-label" style="display: block; margin-bottom: 0.5rem;">SMTP Password</label>
            <input 
              type="password" 
              id="smtp_password" 
              name="smtp_password" 
              class="form-input" 
              placeholder="<?php echo !empty($appConfig['mail']['password']) ? '••••••••' : 'Enter password'; ?>" 
              value=""
              style="width: 100%;"
            >
            <span class="form-helper" style="display: block; margin-top: 0.375rem;">Leave blank to keep current password</span>
          </div>

          <div class="form-group">
            <label for="smtp_encryption" class="form-label" style="display: block; margin-bottom: 0.5rem;">Encryption Method</label>
            <select id="smtp_encryption" name="smtp_encryption" class="form-select" style="width: 100%;">
              <option value="tls" <?php echo ($appConfig['mail']['encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (Port 587)</option>
              <option value="ssl" <?php echo ($appConfig['mail']['encryption'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
            </select>
            <span class="form-helper" style="display: block; margin-top: 0.375rem;">Encryption protocol for secure connection</span>
          </div>

          <div class="form-group">
            <label for="smtp_from_address" class="form-label" style="display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.5rem;">
              From Email Address <span style="color: hsl(0 74% 50%);">*</span>
            </label>
            <input 
              type="email" 
              id="smtp_from_address" 
              name="smtp_from_address" 
              class="form-input" 
              placeholder="noreply@inventory.local" 
              value="<?php echo htmlspecialchars($appConfig['mail']['from']['address'] ?? ''); ?>"
              required
              style="width: 100%;"
            >
            <span class="form-helper" style="display: block; margin-top: 0.375rem;">Email address shown as sender</span>
          </div>

          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="smtp_from_name" class="form-label" style="display: block; margin-bottom: 0.5rem;">From Name</label>
            <input 
              type="text" 
              id="smtp_from_name" 
              name="smtp_from_name" 
              class="form-input" 
              placeholder="Inventory Management System" 
              value="<?php echo htmlspecialchars($appConfig['mail']['from']['name'] ?? ''); ?>"
              style="width: 100%;"
            >
            <span class="form-helper" style="display: block; margin-top: 0.375rem;">Name displayed as sender</span>
          </div>
        </div>

        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 1rem; flex-wrap: wrap; justify-content: flex-end;">
          <button type="button" id="testConnectionBtn" class="btn btn-secondary" onclick="testSmtpConnection()" style="display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;">
            <svg id="testConnectionIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="transition: all 0.3s ease;">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <span id="testConnectionText">Test Connection</span>
          </button>
          <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Save SMTP Configuration
          </button>
        </div>
      </form>
    </div>
  </div>

</div>
</div>

<!-- Tab Content: Profile -->
<div class="tab-content active" id="tab-profile">
<div class="grid grid-cols-2 gap-6" style="align-items: start;">
  
  <!-- User Profile -->
  <div class="card" style="height: fit-content;">
    <div class="card-header">
      <h3 class="card-title">User Profile</h3>
      <p class="card-description">Manage your account information</p>
      <?php if ($isDemoUser): ?>
      <div style="margin-top: 0.75rem; padding: 0.75rem; background: hsl(48 96% 89%); border: 1px solid hsl(25 95% 53%); border-radius: var(--radius-md);">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="color: hsl(25 95% 16%);">
            <path d="M12 9V13M12 17H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span style="font-size: 0.875rem; color: hsl(25 95% 16%); font-weight: 500;">Demo Account - Username and Email cannot be modified</span>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-content">
      <form method="POST" id="profileForm">
        <input type="hidden" name="action" value="update_profile">
        <input type="hidden" name="active_tab" id="activeTabInput" value="tab-profile">
        
        <div class="grid grid-cols-2 gap-6">
          <div class="form-group">
            <label for="username" class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
              Username
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="color: var(--text-secondary);">
                <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
                <path d="M12 17V17.01M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </label>
            <input 
              type="text" 
              id="username" 
              name="username" 
              class="form-input" 
              value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
              readonly
              style="background-color: var(--bg-secondary); cursor: not-allowed; opacity: 0.7;"
            >
            <span class="form-helper" style="color: var(--text-secondary); font-size: 0.8125rem;">Cannot be changed</span>
          </div>

          <div class="form-group">
            <label for="email" class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
              Email
              <?php if ($isDemoUser): ?>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="color: var(--text-secondary);">
                <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
                <path d="M12 17V17.01M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
              <?php endif; ?>
            </label>
            <input 
              type="email" 
              id="email" 
              name="email" 
              class="form-input" 
              value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
              placeholder="your.email@example.com"
              <?php echo $isDemoUser ? 'readonly' : ''; ?>
              style="<?php echo $isDemoUser ? 'background-color: var(--bg-secondary); cursor: not-allowed; opacity: 0.7;' : ''; ?>"
              <?php echo $isDemoUser ? '' : 'required'; ?>
            >
            <?php if ($isDemoUser): ?>
            <span class="form-helper" style="color: var(--text-secondary); font-size: 0.8125rem;">Demo account restriction</span>
            <?php endif; ?>
          </div>

          <div class="form-group" style="grid-column: span 2;">
            <label for="role" class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
              Role
              <div class="role-help-tooltip" style="position: relative; display: inline-flex;">
                <button type="button" class="help-icon" style="display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background: var(--color-primary); color: white; border: none; cursor: help; font-size: 0.75rem; font-weight: 700; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.1)'; this.style.backgroundColor='hsl(214 95% 40%)'" onmouseout="this.style.transform='scale(1)'; this.style.backgroundColor='var(--color-primary)'">
                  ?
                </button>
                <div class="tooltip-content" style="position: fixed; width: 320px; background: hsl(240 6% 10%); color: white; padding: 0.875rem 1rem; border-radius: var(--radius-md); font-size: 0.8125rem; line-height: 1.5; opacity: 0; visibility: hidden; pointer-events: none; transition: all 0.2s; z-index: 9999; box-shadow: 0 10px 25px rgba(0,0,0,0.3); white-space: normal;">
                  <div style="margin-bottom: 0.625rem;">
                    <strong style="color: hsl(214 95% 70%); display: block; margin-bottom: 0.25rem;">👤 User</strong>
                    <span style="color: hsl(0 0% 85%);">Standard access - Basic system operations</span>
                  </div>
                  <div style="margin-bottom: 0.625rem;">
                    <strong style="color: hsl(0 84% 70%); display: block; margin-bottom: 0.25rem;">⚡ Admin</strong>
                    <span style="color: hsl(0 0% 85%);">Full system access - All permissions</span>
                  </div>
                  <div style="margin-bottom: 0.625rem;">
                    <strong style="color: hsl(142 76% 65%); display: block; margin-bottom: 0.25rem;">👥 Manager</strong>
                    <span style="color: hsl(0 0% 85%);">Team management - Oversee operations</span>
                  </div>
                  <div>
                    <strong style="color: hsl(48 96% 65%); display: block; margin-bottom: 0.25rem;">👁 Viewer</strong>
                    <span style="color: hsl(0 0% 85%);">Read-only access - View data only</span>
                  </div>
                  <div class="tooltip-arrow" style="position: absolute; width: 12px; height: 12px; background: hsl(240 6% 10%); transform: rotate(45deg);"></div>
                </div>
              </div>
            </label>
            <select 
              id="role" 
              name="role" 
              class="form-input" 
              style="width: 100%; height: 44px; padding: 0.5rem 2.5rem 0.5rem 0.875rem; border: 2px solid var(--border-color); border-radius: var(--radius-md); background-color: var(--bg-primary); color: var(--text-primary); font-size: 0.9375rem; line-height: 1.6; cursor: pointer; transition: all 0.2s; appearance: none; -webkit-appearance: none; -moz-appearance: none; background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg width=\'14\' height=\'14\' viewBox=\'0 0 14 14\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'%3e%3cpath d=\'M3.5 5.25L7 8.75L10.5 5.25\' stroke=\'%23666\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.875rem center; background-size: 14px 14px; vertical-align: middle; display: flex; align-items: center;"
              onmouseover="this.style.borderColor='var(--color-primary)'"
              onmouseout="this.style.borderColor='var(--border-color)'"
              onfocus="this.style.borderColor='var(--color-primary)'; this.style.boxShadow='0 0 0 3px hsl(214 95% 93%)'"
              onblur="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none'"
            >
              <option value="user" <?php echo ($user['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>User</option>
              <option value="admin" <?php echo ($user['role'] ?? 'user') === 'admin' ? 'selected' : ''; ?>>Admin</option>
              <option value="manager" <?php echo ($user['role'] ?? 'user') === 'manager' ? 'selected' : ''; ?>>Manager</option>
              <option value="viewer" <?php echo ($user['role'] ?? 'user') === 'viewer' ? 'selected' : ''; ?>>Viewer</option>
            </select>
            <span class="form-helper" style="display: flex; align-items: center; gap: 0.375rem; margin-top: 0.5rem;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="color: var(--color-primary);">
                <path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Your access level in the system - Changes apply immediately
            </span>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full" style="margin-top: 1.5rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Update Profile
        </button>
      </form>
    </div>
  </div>

  <!-- Account Information -->
  <div class="card" style="height: fit-content;">
    <div class="card-header">
      <h3 class="card-title">Account Information</h3>
      <p class="card-description">View your account details</p>
    </div>
    <div class="card-content">
      <div class="form-group">
        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
          Account Created
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="color: var(--text-secondary);">
            <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
            <path d="M12 17V17.01M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </label>
        <input 
          type="text" 
          class="form-input" 
          value="<?php echo isset($user['created_at']) ? date('F j, Y', $user['created_at']->toDateTime()->getTimestamp()) : date('F j, Y'); ?>" 
          readonly
          style="background-color: var(--bg-secondary); cursor: not-allowed; opacity: 0.7;"
        >
        <span class="form-helper" style="color: var(--text-secondary); font-size: 0.8125rem;">Read-only field</span>
      </div>
      <div class="form-group">
        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
          Last Login
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="color: var(--text-secondary);">
            <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
            <path d="M12 17V17.01M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </label>
        <input 
          type="text" 
          class="form-input" 
          value="<?php echo date('F j, Y g:i A'); ?>" 
          readonly
          style="background-color: var(--bg-secondary); cursor: not-allowed; opacity: 0.7;"
        >
        <span class="form-helper" style="color: var(--text-secondary); font-size: 0.8125rem;">Read-only field</span>
      </div>
      <div class="form-group">
        <label class="form-label">Account Status</label>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <span class="badge" style="display: inline-flex; align-items: center; gap: 0.375rem; background: hsl(143 85% 96%); color: hsl(140 61% 13%); padding: 0.375rem 0.75rem; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 600; border: 1px solid hsl(140 61% 80%);">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <circle cx="12" cy="12" r="10" fill="currentColor"/>
            </svg>
            Active
          </span>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Tab Content: Application Settings -->
<div class="tab-content" id="tab-application" style="display: none;">
<div class="grid grid-cols-2 gap-6" style="align-items: start;">
  
  <!-- Notification Preferences -->
  <div class="card" style="height: fit-content;">
    <div class="card-header">
      <h3 class="card-title">Notification Preferences</h3>
      <p class="card-description">Configure how you receive notifications</p>
    </div>
    <div class="card-content">
      <form method="POST">
        <input type="hidden" name="action" value="update_notifications">
        
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0;">
            <div style="flex: 1;">
              <label class="form-label" style="margin: 0; display: block; color: <?php echo $smtpConfigured ? 'var(--text-primary)' : 'var(--text-secondary)'; ?>;">
                Email Notifications
                <?php if (!$smtpConfigured): ?>
                  <span style="color: hsl(25 95% 45%); font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem;">⚠ SMTP Required</span>
                <?php endif; ?>
              </label>
              <span class="form-helper" style="color: <?php echo $smtpConfigured ? 'var(--text-secondary)' : 'hsl(215 16% 60%)'; ?>;">
                Receive email updates about system activities
                <?php if (!$smtpConfigured): ?>
                  <a href="#" onclick="switchToSystemTab(); return false;" style="color: var(--color-primary); text-decoration: underline; margin-left: 0.25rem;">Configure SMTP</a>
                <?php endif; ?>
              </span>
            </div>
            <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px; opacity: <?php echo $smtpConfigured ? '1' : '0.5'; ?>;">
              <input type="checkbox" name="email_notifications" <?php echo $smtpConfigured ? 'checked' : 'disabled'; ?> style="opacity: 0; width: 0; height: 0;">
              <span class="slider" style="position: absolute; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $smtpConfigured ? 'var(--color-primary)' : 'hsl(215 16% 70%)'; ?>; transition: .3s; border-radius: 24px;"></span>
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
  <div class="card" style="height: fit-content;">
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
<div class="grid grid-cols-2 gap-6" style="align-items: start;">
  
  <!-- Theme Settings -->
  <div class="card" style="height: fit-content;">
    <div class="card-header">
      <h3 class="card-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 0.5rem;">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1" x2="12" y2="3"/>
          <line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1" y1="12" x2="3" y2="12"/>
          <line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
        Theme
      </h3>
      <p class="card-description">Choose your interface theme</p>
    </div>
    <div class="card-content">
      
      <!-- Warning Notice (Hidden by default, shown on change attempt) -->
      <div id="theme-warning" class="alert" style="display: none; background: hsl(48 96% 89%); border: 2px solid hsl(45 93% 47%); padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; animation: slideDown 0.3s ease-out;">
        <div style="display: flex; align-items: flex-start; gap: 1rem;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 45%)" stroke-width="2" style="flex-shrink: 0; margin-top: 0.125rem;">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
          <div style="flex: 1;">
            <strong style="font-size: 0.9375rem; color: hsl(25 95% 16%); display: block; margin-bottom: 0.5rem;">Theme Support Not Yet Implemented</strong>
            <p style="font-size: 0.875rem; color: hsl(25 95% 20%); margin: 0; line-height: 1.5;">
              Dark mode and theme customization features are currently unavailable. Please contact your system administrator to implement this feature.
            </p>
          </div>
          <button type="button" onclick="closeThemeWarning()" style="background: none; border: none; cursor: pointer; padding: 0.25rem; color: hsl(25 95% 35%); transition: all 0.2s; flex-shrink: 0; margin-left: auto;" 
                  onmouseover="this.style.color='hsl(25 95% 20%)'; this.style.transform='scale(1.1)'" 
                  onmouseout="this.style.color='hsl(25 95% 35%)'; this.style.transform='scale(1)'"
                  aria-label="Close warning">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
      </div>
      
      <form method="POST" id="themeForm">
        <input type="hidden" name="action" value="update_theme">
        
        <!-- Current Theme Display -->
        <div class="alert alert-info mb-4" style="background: hsl(214 95% 93%); border: 1px solid hsl(214 84% 56%); padding: 1rem; border-radius: var(--radius-md);">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="hsl(222 47% 17%)" stroke-width="2">
              <circle cx="12" cy="12" r="5"/>
              <line x1="12" y1="1" x2="12" y2="3"/>
              <line x1="12" y1="21" x2="12" y2="23"/>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
              <line x1="1" y1="12" x2="3" y2="12"/>
              <line x1="21" y1="12" x2="23" y2="12"/>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
            <div style="flex: 1;">
              <strong style="color: hsl(222 47% 17%);">Current Theme:</strong>
              <div style="margin-top: 0.25rem; font-size: 0.875rem; color: hsl(222 47% 17%);">
                Light Mode (Default)
              </div>
            </div>
          </div>
        </div>

        <!-- Theme Options -->
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
          
          <!-- Light Theme -->
          <label class="theme-option" style="display: flex; align-items: flex-start; gap: 1rem; padding: 1rem; border: 2px solid var(--color-primary); border-radius: var(--radius-lg); cursor: pointer; transition: all 0.2s; background: hsl(214 95% 97%);">
            <input type="radio" name="theme" value="light" checked
                   style="margin-top: 0.125rem; width: 18px; height: 18px; cursor: pointer; accent-color: var(--color-primary);" 
                   onchange="handleThemeChange(this)">
            <div style="flex: 1;">
              <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.375rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="5"/>
                  <line x1="12" y1="1" x2="12" y2="3"/>
                  <line x1="12" y1="21" x2="12" y2="23"/>
                  <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                  <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                  <line x1="1" y1="12" x2="3" y2="12"/>
                  <line x1="21" y1="12" x2="23" y2="12"/>
                  <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                  <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                </svg>
                <strong style="font-size: 0.9375rem;">Light Mode</strong>
                <span style="font-size: 0.75rem; color: hsl(214 84% 46%); background: hsl(214 95% 93%); padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-weight: 600;">Active</span>
              </div>
              <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
                Bright and clear interface optimized for well-lit environments
              </p>
            </div>
          </label>

          <!-- Dark Theme -->
          <label class="theme-option" style="display: flex; align-items: flex-start; gap: 1rem; padding: 1rem; border: 2px solid var(--border-color); border-radius: var(--radius-lg); cursor: pointer; transition: all 0.2s;">
            <input type="radio" name="theme" value="dark"
                   style="margin-top: 0.125rem; width: 18px; height: 18px; cursor: pointer; accent-color: var(--color-primary);" 
                   onchange="handleThemeChange(this)">
            <div style="flex: 1;">
              <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.375rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
                <strong style="font-size: 0.9375rem;">Dark Mode</strong>
              </div>
              <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
                Reduced eye strain in low-light conditions with dark backgrounds
              </p>
            </div>
          </label>

          <!-- System Theme -->
          <label class="theme-option" style="display: flex; align-items: flex-start; gap: 1rem; padding: 1rem; border: 2px solid var(--border-color); border-radius: var(--radius-lg); cursor: pointer; transition: all 0.2s;">
            <input type="radio" name="theme" value="system"
                   style="margin-top: 0.125rem; width: 18px; height: 18px; cursor: pointer; accent-color: var(--color-primary);" 
                   onchange="handleThemeChange(this)">
            <div style="flex: 1;">
              <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.375rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                  <line x1="8" y1="21" x2="16" y2="21"/>
                  <line x1="12" y1="17" x2="12" y2="21"/>
                </svg>
                <strong style="font-size: 0.9375rem;">System Preference</strong>
                <span style="font-size: 0.75rem; color: hsl(142 76% 36%); background: hsl(142 76% 95%); padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-weight: 600;">Recommended</span>
              </div>
              <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0; line-height: 1.5;">
                Automatically match your operating system's theme settings. When implemented, this will sync with your OS dark/light mode preference.
              </p>
            </div>
          </label>

        </div>

        <button type="button" onclick="showThemeWarningPersistent()" class="btn btn-primary w-full" style="margin-top: 1.5rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M5 13L9 17L19 7" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Apply Theme
        </button>
      </form>
    </div>
  </div>
  
  <!-- Timezone Settings (Moved to Regional tab) -->
  <div class="card" style="height: fit-content;">
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
  <div class="card" style="height: fit-content; grid-column: span 2;">
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

  <!-- Font Selection -->
  <div class="card" style="grid-column: span 2; height: fit-content;">
    <div class="card-header">
      <h3 class="card-title">Font Settings</h3>
      <p class="card-description">Choose your preferred font family</p>
    </div>
    <div class="card-content">
      <form method="POST">
        <input type="hidden" name="action" value="update_font">
        
        <!-- Current Font Display -->
        <div class="alert alert-info mb-4" style="background-color: var(--color-info-light); border: 1px solid var(--color-info); padding: 1rem; border-radius: var(--radius-md);">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="color: var(--color-info);">
              <path d="M4 7V4H20V7M9 20H15M12 4V20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div style="flex: 1;">
              <strong>Current Font:</strong>
              <div style="margin-top: 0.25rem; font-size: 0.875rem;">
                <?php echo htmlspecialchars($availableFonts[$currentFont]['name']); ?>
                <span style="color: var(--text-secondary);">- <?php echo htmlspecialchars($availableFonts[$currentFont]['description']); ?></span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <label for="font_family" class="form-label" style="margin: 0;">
              <span>Select Font Family</span>
              <span style="color: var(--text-secondary); font-weight: normal; font-size: 0.875rem;">
                (Works offline - uses system fonts)
              </span>
            </label>
            <button type="button" id="toggleFontList" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem;">
              <span id="toggleFontText">Show All Fonts</span>
              <svg id="toggleFontIcon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="transition: transform 0.3s;">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </button>
          </div>
          
          <!-- Font Options Grid (Collapsible) -->
          <div id="fontOptionsContainer" style="display: none; transition: all 0.3s ease;">
            <div style="display: grid; gap: 0.75rem; margin-top: 0.75rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color); max-height: 600px; overflow-y: auto;">
              <?php foreach ($availableFonts as $fontKey => $fontInfo): ?>
              <label class="font-option-card" style="display: block; padding: 1rem; border: 2px solid <?php echo $fontKey === $currentFont ? 'var(--color-primary)' : 'var(--border-color)'; ?>; border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s; background: <?php echo $fontKey === $currentFont ? 'hsl(214 95% 93%)' : 'var(--bg-primary)'; ?>;">
                <input type="radio" name="font_family" value="<?php echo htmlspecialchars($fontKey); ?>" <?php echo $fontKey === $currentFont ? 'checked' : ''; ?> style="margin-right: 0.75rem; vertical-align: middle;">
                <div style="display: inline-block; vertical-align: middle; width: calc(100% - 2rem);">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <strong style="font-size: 0.9375rem;"><?php echo htmlspecialchars($fontInfo['name']); ?></strong>
                    <?php if ($fontKey === $currentFont): ?>
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.125rem 0.5rem; background: var(--color-primary); color: white; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"/>
                      </svg>
                      Active
                    </span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 0.625rem;">
                    <?php echo htmlspecialchars($fontInfo['description']); ?>
                  </div>
                  <div style="padding: 0.625rem; background: var(--bg-secondary); border-radius: 4px; font-family: <?php echo $fontInfo['stack']; ?>; font-size: 0.9375rem; color: var(--text-primary); border: 1px solid var(--border-color);">
                    <?php echo htmlspecialchars($fontInfo['sample']); ?>
                  </div>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
            
            <span class="form-helper" style="display: block; margin-top: 0.75rem;">
              All fonts are system fonts - no internet connection required
            </span>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full" style="margin-top: 1rem;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Save Font Preference
        </button>
      </form>

      <!-- Detect System Fonts -->
      <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid var(--border-color);">
        <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          Detect System Fonts
        </h4>
        <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
          Scan your system for installed fonts and see which ones are available
        </p>
        <button type="button" id="detectFontsBtn" class="btn btn-secondary" style="width: 100%;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          Scan for Available Fonts
        </button>
        
        <!-- Detected Fonts Display -->
        <div id="detectedFonts" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color); max-height: 300px; overflow-y: auto;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <strong style="font-size: 0.9375rem;">Detected Fonts</strong>
            <span id="fontCount" style="font-size: 0.875rem; color: var(--text-secondary);"></span>
          </div>
          <div id="detectedFontsList" style="display: grid; gap: 0.5rem;"></div>
        </div>
      </div>

      <!-- Upload Custom Font -->
      <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid var(--border-color);">
        <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
          </svg>
          Upload Custom Font
          <?php if ($fontService->isDatabaseAvailable()): ?>
          <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.125rem 0.5rem; background: hsl(143 85% 96%); color: hsl(140 61% 13%); border: 1px solid hsl(140 61% 13%); border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
            DB
          </span>
          <?php else: ?>
          <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.125rem 0.5rem; background: hsl(214 95% 93%); color: hsl(222 47% 17%); border: 1px solid hsl(222 47% 17%); border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
              <polyline points="13 2 13 9 20 9"/>
            </svg>
            JSON
          </span>
          <?php endif; ?>
        </h4>
        <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;">
          Upload your own font files (WOFF, WOFF2, TTF, OTF) - Maximum 5MB
          <?php if (!$fontService->isDatabaseAvailable()): ?>
          <br><strong>Note:</strong> Using file-based storage (database not configured)
          <?php endif; ?>
        </p>
        
        <form method="POST" enctype="multipart/form-data" id="uploadFontForm">
          <input type="hidden" name="action" value="upload_font">
          
          <div class="form-group">
            <label for="font_name" class="form-label">Font Display Name</label>
            <input type="text" id="font_name" name="font_name" class="form-input" placeholder="e.g., My Custom Font" required>
            <span class="form-helper">Friendly name shown in the font selector</span>
          </div>

          <div class="form-group">
            <label for="font_family_name" class="form-label">Font Family Name</label>
            <input type="text" id="font_family_name" name="font_family_name" class="form-input" placeholder="e.g., CustomFont" required>
            <span class="form-helper">CSS font-family value (no spaces recommended)</span>
          </div>

          <div class="form-group">
            <label for="font_category" class="form-label">Font Category</label>
            <select id="font_category" name="font_category" class="form-select">
              <option value="sans-serif">Sans-Serif</option>
              <option value="serif">Serif</option>
              <option value="monospace">Monospace</option>
              <option value="display">Display</option>
              <option value="handwriting">Handwriting</option>
            </select>
          </div>

          <div class="form-group">
            <label for="font_file" class="form-label">Font File</label>
            <input type="file" id="font_file" name="font_file" class="form-input" accept=".woff,.woff2,.ttf,.otf" required>
            <span class="form-helper">Supported formats: WOFF, WOFF2, TTF, OTF (max 5MB)</span>
          </div>

          <button type="submit" class="btn btn-primary w-full">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="17 8 12 3 7 8"/>
              <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Upload Font
          </button>
        </form>
      </div>

      <!-- Manage Custom Fonts -->
      <?php if (!empty($customFonts)): ?>
      <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid var(--border-color);">
        <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 7H4"/>
            <path d="M10 11v6"/>
            <path d="M14 11v6"/>
            <path d="M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12"/>
            <path d="M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
          </svg>
          Manage Custom Fonts
        </h4>
        <div style="display: grid; gap: 0.75rem; margin-top: 1rem;">
          <?php foreach ($customFonts as $fontKey => $fontInfo): ?>
          <?php if (isset($fontInfo['is_custom']) && $fontInfo['is_custom']): ?>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="flex: 1;">
              <div style="font-weight: 600; font-size: 0.9375rem;"><?php echo htmlspecialchars($fontInfo['name']); ?></div>
              <div style="font-size: 0.8125rem; color: var(--text-secondary);"><?php echo htmlspecialchars($fontInfo['description']); ?></div>
            </div>
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this font?');">
              <input type="hidden" name="action" value="delete_font">
              <input type="hidden" name="font_id" value="<?php echo $fontInfo['id']; ?>">
              <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.375rem 0.75rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                Delete
              </button>
            </form>
          </div>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>

<!-- Tab Content: Security -->
<div class="tab-content" id="tab-security" style="display: none;">
<div class="grid grid-cols-2 gap-6" style="align-items: start;">
  
  <!-- Password Change -->
  <div class="card" style="height: fit-content;">
    <div class="card-header">
      <h3 class="card-title">Change Password</h3>
      <p class="card-description">Update your password</p>
    </div>
    <div class="card-content">
      
      <?php if ($isDemoUser): ?>
      <!-- Demo Account Warning -->
      <div class="alert" style="background: hsl(48 96% 89%); border: 2px solid hsl(45 93% 47%); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 45%)" stroke-width="2" style="flex-shrink: 0; margin-top: 0.125rem;">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
          <div style="flex: 1;">
            <strong style="font-size: 0.875rem; color: hsl(25 95% 16%); display: block; margin-bottom: 0.25rem;">Demo Account Restriction</strong>
            <p style="font-size: 0.8125rem; color: hsl(25 95% 20%); margin: 0; line-height: 1.5;">
              Password changes are disabled for demo accounts. Please request a personal account to manage your password.
            </p>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <form method="POST" <?php echo $isDemoUser ? 'onsubmit="event.preventDefault(); Toast.error(\"Demo account cannot change password. Please use a personal account.\", 4000); return false;"' : ''; ?>>
        <input type="hidden" name="action" value="change_password">
        
        <div class="form-group">
          <label for="current_password_sec" class="form-label" style="color: <?php echo $isDemoUser ? 'var(--text-secondary)' : 'var(--text-primary)'; ?>;">Current Password</label>
          <input 
            type="password" 
            id="current_password_sec" 
            name="current_password" 
            class="form-input" 
            placeholder="Enter current password"
            <?php echo $isDemoUser ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : ''; ?>
            required
          >
        </div>

        <div class="form-group">
          <label for="new_password_sec" class="form-label" style="color: <?php echo $isDemoUser ? 'var(--text-secondary)' : 'var(--text-primary)'; ?>;">New Password</label>
          <input 
            type="password" 
            id="new_password_sec" 
            name="new_password" 
            class="form-input" 
            placeholder="Enter new password"
            <?php echo $isDemoUser ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : ''; ?>
            required
            minlength="6"
          >
        </div>

        <div class="form-group">
          <label for="confirm_password_sec" class="form-label" style="color: <?php echo $isDemoUser ? 'var(--text-secondary)' : 'var(--text-primary)'; ?>;">Confirm Password</label>
          <input 
            type="password" 
            id="confirm_password_sec" 
            name="confirm_password" 
            class="form-input" 
            placeholder="Confirm new password"
            <?php echo $isDemoUser ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : ''; ?>
            required
          >
        </div>

        <button type="submit" class="btn <?php echo $isDemoUser ? 'btn-secondary' : 'btn-primary'; ?> w-full" <?php echo $isDemoUser ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : ''; ?>>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <?php if ($isDemoUser): ?>
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
            <?php else: ?>
            <path d="M19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V11C3 10.4696 3.21071 9.96086 3.58579 9.58579C3.96086 9.21071 4.46957 9 5 9H19C19.5304 9 20.0391 9.21071 20.4142 9.58579C20.7893 9.96086 21 10.4696 21 11V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21Z"/>
            <path d="M7 9V5C7 3.93913 7.42143 2.92172 8.17157 2.17157C8.92172 1.42143 9.93913 1 11 1H13C14.0609 1 15.0783 1.42143 15.8284 2.17157C16.5786 2.92172 17 3.93913 17 5V9"/>
            <?php endif; ?>
          </svg>
          <?php echo $isDemoUser ? 'Unavailable for Demo' : 'Change Password'; ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Security Options -->
  <div class="card" style="height: fit-content;">
    <div class="card-header">
      <h3 class="card-title">Security Options</h3>
      <p class="card-description">Additional security settings</p>
    </div>
    <div class="card-content">
      <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0;">
          <div style="flex: 1;">
            <label class="form-label" style="margin: 0; display: block; color: var(--text-secondary);">
              Two-Factor Authentication
              <span style="color: hsl(25 95% 45%); font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem;">⚠ Not Implemented</span>
            </label>
            <span class="form-helper" style="color: var(--text-secondary);">
              Two-factor authentication support is not yet implemented. Contact your administrator for more information.
            </span>
          </div>
          <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px; opacity: 0.5;">
            <input type="checkbox" name="two_factor" disabled onclick="Toast.warning('Two-factor authentication is not yet implemented. Contact your administrator.', 4000); return false;" style="opacity: 0; width: 0; height: 0; cursor: not-allowed;">
            <span class="slider" style="position: absolute; cursor: not-allowed; top: 0; left: 0; right: 0; bottom: 0; background-color: hsl(215 16% 70%); transition: .3s; border-radius: 24px;"></span>
          </label>
        </div>
      </div>
      
      <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-top: 1px solid var(--border-color);">
          <div style="flex: 1;">
            <label class="form-label" style="margin: 0; display: block; color: <?php echo $smtpConfigured ? 'var(--text-primary)' : 'var(--text-secondary)'; ?>;">
              Login Alerts
              <?php if (!$smtpConfigured): ?>
                <span style="color: hsl(25 95% 45%); font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem;">⚠ SMTP Required</span>
              <?php endif; ?>
            </label>
            <span class="form-helper" style="color: var(--text-secondary);">
              <?php if ($smtpConfigured): ?>
                Receive email notifications when new login attempts occur on your account
              <?php else: ?>
                Email notifications require SMTP configuration. Visit System tab to configure email settings.
              <?php endif; ?>
            </span>
          </div>
          <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px; opacity: <?php echo $smtpConfigured ? '1' : '0.5'; ?>;">
            <input type="checkbox" name="login_alerts" <?php echo $smtpConfigured ? 'checked' : 'disabled'; ?> <?php echo !$smtpConfigured ? 'onclick="Toast.warning(\"SMTP must be configured first. Visit System tab.\", 4000); return false;"' : ''; ?> style="opacity: 0; width: 0; height: 0; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>;">
            <span class="slider" style="position: absolute; cursor: <?php echo $smtpConfigured ? 'pointer' : 'not-allowed'; ?>; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $smtpConfigured ? 'var(--color-primary)' : 'hsl(215 16% 70%)'; ?>; transition: .3s; border-radius: 24px;"></span>
          </label>
        </div>
      </div>

      <!-- Active Sessions (Minimal) -->
      <div class="form-group" style="margin-top: 1.5rem;">
        <label class="form-label">Active Sessions</label>
        
        <!-- Current Session Only -->
        <div id="current-session-display" style="padding: 0.875rem 1rem; background: var(--bg-secondary); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 0.75rem;">
          <div style="display: flex; align-items: center; justify-content: center; padding: 1rem; color: var(--text-secondary); font-size: 0.875rem;">
            Loading...
          </div>
        </div>

        <!-- View All Button -->
        <button onclick="openSessionsModal()" class="btn btn-outline w-full" style="margin-bottom: 0.75rem;">
          View All Sessions
        </button>

        <!-- Danger Action -->
        <div style="padding: 1rem; background: hsl(0 86% 97%); border-radius: var(--radius-md); border: 1px solid var(--color-danger);">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="flex: 1;">
              <p style="font-weight: 600; font-size: 0.875rem; margin: 0 0 0.25rem 0;">Log Out All Other Sessions</p>
              <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0;">Terminate all active sessions except your current one</p>
            </div>
            <button onclick="terminateOtherSessions()" class="btn btn-danger" style="margin-left: 1rem; white-space: nowrap;">
              Log Out All
            </button>
          </div>
        </div>
      </div>

      <!-- Sessions Modal -->
      <div id="sessions-modal" onclick="closeSessionsModal()" style="display: none; position: fixed; inset: 0; z-index: 50; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);">
        <div onclick="event.stopPropagation()" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--bg-primary); border-radius: var(--radius-lg); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); width: 95%; max-width: 1200px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; border: 1px solid var(--border-color);">
          
          <!-- Modal Header -->
          <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <div>
              <h3 style="font-size: 1.125rem; font-weight: 600; margin: 0 0 0.25rem 0;">All Active Sessions</h3>
              <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">View and manage your active sessions across devices</p>
            </div>
            <button onclick="closeSessionsModal()" style="padding: 0.5rem; border: none; background: transparent; cursor: pointer; color: var(--text-secondary); border-radius: var(--radius-sm); transition: all 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='transparent'">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Modal Body (Side by Side) -->
          <div style="flex: 1; overflow: hidden; display: grid; grid-template-columns: 1fr 1fr; gap: 0;">
            
            <!-- Left Side: Map -->
            <div style="padding: 1.5rem; border-right: 1px solid var(--border-color); overflow-y: auto;">
              <div style="margin-bottom: 1rem;">
                <h4 style="font-size: 0.9375rem; font-weight: 600; margin: 0 0 0.5rem 0;">Session Locations</h4>
                <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0;">Geographic distribution of active sessions</p>
              </div>
              <div id="session-map" style="height: calc(100% - 4rem); min-height: 400px; border-radius: var(--radius-md); overflow: hidden;"></div>
            </div>
            
            <!-- Right Side: Sessions List -->
            <div style="padding: 1.5rem; overflow-y: auto; background: var(--bg-secondary);">
              <div style="margin-bottom: 1rem;">
                <h4 style="font-size: 0.9375rem; font-weight: 600; margin: 0 0 0.5rem 0;">Active Sessions</h4>
                <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0;">All currently logged in devices</p>
              </div>
              <div id="sessions-container">
                <div style="display: flex; align-items: center; justify-content: center; padding: 2rem; color: var(--text-secondary);">
                  Loading sessions...
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.75rem; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.8125rem; color: var(--text-secondary);">
              <span id="session-count">0 sessions</span>
            </div>
            <div style="display: flex; gap: 0.75rem;">
              <button onclick="refreshSessions()" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                </svg>
                Refresh
              </button>
              <button onclick="closeSessionsModal()" class="btn btn-primary">
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<!-- System Information -->
<div class="card" style="margin-top: 2rem;">
  <div class="card-header">
    <h3 class="card-title">System Information</h3>
    <p class="card-description">Application and server details</p>
  </div>
  <div class="card-content">
    <div class="grid grid-cols-4 gap-6" style="align-items: start;">
      <div>
        <p class="text-sm text-secondary mb-1">Application Version</p>
        <p class="font-semibold">v<?php echo $appConfig['app_version'] ?? '0.3.1'; ?></p>
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
<div class="card" style="margin-top: 2rem; border: 2px solid var(--color-danger);">
  <div class="card-header">
    <h3 class="card-title" style="color: var(--color-danger);">Danger Zone</h3>
    <p class="card-description">Irreversible and destructive actions</p>
  </div>
  <div class="card-content">
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: hsl(0 86% 97%); border-radius: var(--radius-md);">
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

<style>
/* Theme Animation Styles */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeOut {
  from {
    opacity: 1;
    transform: translateY(0);
  }
  to {
    opacity: 0;
    transform: translateY(-10px);
  }
}

@keyframes signalFade {
  0%, 100% {
    opacity: 1;
    filter: blur(0px);
  }
  50% {
    opacity: 0.4;
    filter: blur(1px);
  }
}

@keyframes signalBar {
  0% {
    transform: translateX(-100%);
    opacity: 0;
  }
  50% {
    opacity: 1;
  }
  100% {
    transform: translateX(100%);
    opacity: 0;
  }
}

/* Theme loading state */
.theme-option.theme-loading {
  border-color: var(--color-primary) !important;
  background: hsl(214 95% 97%) !important;
  position: relative;
  overflow: hidden;
}

/* Blur and fade the content */
.theme-option.theme-loading > div {
  animation: signalFade 2s ease-in-out infinite;
}

/* Signal loading bar effect */
.theme-option.theme-loading::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  border-radius: var(--radius-lg);
  background: linear-gradient(
    90deg, 
    transparent 0%, 
    rgba(59, 130, 246, 0.3) 25%,
    rgba(59, 130, 246, 0.5) 50%,
    rgba(59, 130, 246, 0.3) 75%,
    transparent 100%
  );
  animation: signalBar 2s ease-in-out infinite;
  pointer-events: none;
}

@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

.theme-spinner {
  display: inline-flex;
  align-items: center;
  color: var(--color-primary);
}
</style>

<script>
// ============================================
// FLASH MESSAGE TOAST NOTIFICATIONS
// ============================================

// Display flash message on page load
document.addEventListener('DOMContentLoaded', function() {
  <?php if (isset($_SESSION['flash_message'])): ?>
    const flashMessage = <?php echo json_encode($_SESSION['flash_message']); ?>;
    const flashType = <?php echo json_encode($_SESSION['flash_type']); ?>;
    
    // Display toast notification
    if (typeof Toast !== 'undefined') {
      switch (flashType) {
        case 'success':
          Toast.success(flashMessage, 4000);
          break;
        case 'error':
        case 'danger':
          Toast.error(flashMessage, 5000);
          break;
        case 'warning':
          Toast.warning(flashMessage, 4000);
          break;
        case 'info':
          Toast.info(flashMessage, 4000);
          break;
        default:
          Toast.show(flashMessage, flashType, 4000);
      }
    }
    
    <?php 
      // Clear flash message after displaying
      unset($_SESSION['flash_message']);
      unset($_SESSION['flash_type']);
    ?>
  <?php endif; ?>
});

// ============================================
// THEME HANDLING FUNCTIONS
// ============================================

let originalThemeSelection = 'light'; // Track original selection
let themeChangeTimeout = null;
let currentAttemptedTheme = null;

function handleThemeChange(radioElement) {
  const themeWarning = document.getElementById('theme-warning');
  const selectedValue = radioElement.value;
  
  // Only show warning if user selects something other than light
  if (selectedValue !== 'light') {
    // Store the attempted theme
    currentAttemptedTheme = selectedValue;
    
    // Add loading animation to the selected option
    const selectedLabel = radioElement.closest('.theme-option');
    selectedLabel.classList.add('theme-loading');
    
    // Add spinner icon to the label
    const labelDiv = selectedLabel.querySelector('div > div');
    let spinnerSpan = selectedLabel.querySelector('.theme-spinner');
    if (!spinnerSpan) {
      spinnerSpan = document.createElement('span');
      spinnerSpan.className = 'theme-spinner';
      spinnerSpan.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
          <circle cx="12" cy="12" r="10" opacity="0.25"/>
          <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
        </svg>
      `;
      spinnerSpan.style.cssText = 'display: inline-flex; align-items: center; margin-left: 0.5rem;';
      labelDiv.appendChild(spinnerSpan);
    }
    
    // Show warning with animation
    themeWarning.style.display = 'block';
    themeWarning.style.animation = 'slideDown 0.3s ease-out';
    
    // Smooth scroll to warning
    setTimeout(() => {
      themeWarning.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
    
    // Show toast notification
    if (typeof Toast !== 'undefined') {
      Toast.warning('Processing theme change...', 2000);
    }
    
    // Don't revert immediately - keep showing loading state
    // Will revert only when warning is closed
  } else {
    // Hide warning if light is selected
    closeThemeWarning();
  }
}

function closeThemeWarning() {
  const themeWarning = document.getElementById('theme-warning');
  
  // Fade out animation
  themeWarning.style.animation = 'fadeOut 0.3s ease-out';
  
  setTimeout(() => {
    themeWarning.style.display = 'none';
    themeWarning.style.animation = '';
    
    // Remove loading states from all theme options
    document.querySelectorAll('.theme-option').forEach(option => {
      option.classList.remove('theme-loading');
      const spinner = option.querySelector('.theme-spinner');
      if (spinner) {
        spinner.remove();
      }
    });
    
    // Revert to light theme
    const lightRadio = document.querySelector('input[name="theme"][value="light"]');
    if (lightRadio) {
      lightRadio.checked = true;
    }
    
    currentAttemptedTheme = null;
  }, 300);
}

function showThemeWarningPersistent() {
  const themeWarning = document.getElementById('theme-warning');
  const selectedTheme = document.querySelector('input[name="theme"]:checked');
  
  if (!selectedTheme || selectedTheme.value === 'light') {
    // Show info toast that theme is already set
    if (typeof Toast !== 'undefined') {
      Toast.info('Light theme is currently active and working correctly.', 3000);
    }
  } else {
    // Trigger the same behavior as clicking the theme
    handleThemeChange(selectedTheme);
  }
}

// ============================================
// SMTP FUNCTIONS
// ============================================

function testSmtpConnection() {
  const btn = document.getElementById('testConnectionBtn');
  const icon = document.getElementById('testConnectionIcon');
  const text = document.getElementById('testConnectionText');
  const form = document.getElementById('smtpConfigForm');
  
  if (!btn || !icon || !text || !form) return;
  
  // Save original state
  const originalText = text.textContent;
  
  // Start loading animation
  btn.disabled = true;
  btn.style.filter = 'blur(0.5px)';
  btn.style.opacity = '0.7';
  icon.style.animation = 'spin 1s linear infinite';
  text.textContent = 'Testing...';
  
  // Add spin animation if not exists
  if (!document.getElementById('spinAnimation')) {
    const style = document.createElement('style');
    style.id = 'spinAnimation';
    style.textContent = `
      @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
      }
    `;
    document.head.appendChild(style);
  }
  
  // Get form data
  const formData = new FormData(form);
  formData.set('action', 'test_smtp');
  
  // Send AJAX request to test SMTP
  fetch('settings.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    // Stop loading animation
    btn.disabled = false;
    btn.style.filter = 'none';
    btn.style.opacity = '1';
    icon.style.animation = 'none';
    text.textContent = originalText;
    
    // Show result
    if (data.success) {
      if (typeof Toast !== 'undefined') {
        Toast.success(data.message);
      } else {
        alert(data.message);
      }
    } else {
      if (typeof Toast !== 'undefined') {
        Toast.error(data.message);
      } else {
        alert(data.message);
      }
    }
  })
  .catch(error => {
    // Stop loading animation
    btn.disabled = false;
    btn.style.filter = 'none';
    btn.style.opacity = '1';
    icon.style.animation = 'none';
    text.textContent = originalText;
    
    // Show error
    if (typeof Toast !== 'undefined') {
      Toast.error('Connection test failed: ' + error.message);
    } else {
      alert('Connection test failed: ' + error.message);
    }
  });
}

function switchToSystemTab() {
  switchTab('system');
  return false;
}

// ============================================
// TAB SWITCHING WITH PERSISTENCE
// ============================================

const tabTriggers = document.querySelectorAll('.tab-trigger');
const tabContents = document.querySelectorAll('.tab-content');

// Function to switch tabs
function switchTab(tabName) {
  // Remove active class from all triggers
  tabTriggers.forEach(t => {
    t.classList.remove('active');
    t.style.borderBottomColor = 'transparent';
    t.style.color = 'var(--text-secondary)';
  });
  
  // Remove active class from all contents
  tabContents.forEach(c => {
    c.classList.remove('active');
    c.style.display = 'none';
  });
  
  // Find and activate the target tab
  const targetTrigger = document.querySelector(`.tab-trigger[data-tab="${tabName}"]`);
  const targetContent = document.getElementById('tab-' + tabName);
  
  if (targetTrigger && targetContent) {
    // Add active class to trigger
    targetTrigger.classList.add('active');
    targetTrigger.style.borderBottomColor = 'var(--color-primary)';
    targetTrigger.style.color = 'var(--color-primary)';
    
    // Show target content
    targetContent.classList.add('active');
    targetContent.style.display = 'block';
    
    // Update hidden input for persistence
    const activeTabInput = document.getElementById('activeTabInput');
    if (activeTabInput) {
      activeTabInput.value = 'tab-' + tabName;
    }
    
    // Store in sessionStorage for persistence
    sessionStorage.setItem('activeSettingsTab', tabName);
  }
}

// Add click listeners to tab triggers
tabTriggers.forEach(trigger => {
  trigger.addEventListener('click', function() {
    const targetTab = this.getAttribute('data-tab');
    switchTab(targetTab);
  });
});

// Restore active tab on page load
document.addEventListener('DOMContentLoaded', function() {
  // Check PHP session first (from form submission)
  const phpActiveTab = '<?php echo $_SESSION["active_settings_tab"] ?? ""; ?>';
  // Then check sessionStorage (from tab clicks)
  const storedTab = sessionStorage.getItem('activeSettingsTab');
  
  // Determine which tab to activate
  let activeTab = 'profile'; // default
  
  if (phpActiveTab && phpActiveTab.startsWith('tab-')) {
    activeTab = phpActiveTab.replace('tab-', '');
  } else if (storedTab) {
    activeTab = storedTab;
  }
  
  // Always switch to the determined tab (including default profile)
  switchTab(activeTab);
});

// Update active tab input on all form submissions
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', function() {
    const activeTabInput = this.querySelector('#activeTabInput') || this.querySelector('input[name="active_tab"]');
    if (activeTabInput) {
      const currentTab = document.querySelector('.tab-content.active')?.id || 'tab-profile';
      activeTabInput.value = currentTab;
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

// ============================================
// FONT DROPDOWN TOGGLE
// ============================================

// Toggle font options dropdown
document.getElementById('toggleFontList')?.addEventListener('click', function() {
  const container = document.getElementById('fontOptionsContainer');
  const text = document.getElementById('toggleFontText');
  const icon = document.getElementById('toggleFontIcon');
  
  if (container.style.display === 'none' || container.style.display === '') {
    container.style.display = 'block';
    text.textContent = 'Hide Fonts';
    icon.style.transform = 'rotate(180deg)';
  } else {
    container.style.display = 'none';
    text.textContent = 'Show All Fonts';
    icon.style.transform = 'rotate(0deg)';
  }
});

// ============================================
// THEME MANAGEMENT
// ============================================

// Add hover effects to theme options
document.querySelectorAll('.theme-option').forEach(option => {
  const input = option.querySelector('input[type="radio"]');
  
  option.addEventListener('mouseenter', function() {
    this.style.borderColor = 'var(--color-primary)';
    this.style.backgroundColor = 'var(--bg-secondary)';
  });
  
  option.addEventListener('mouseleave', function() {
    if (!input.checked) {
      this.style.borderColor = 'var(--border-color)';
      this.style.backgroundColor = 'transparent';
    }
  });
  
  // Update on selection
  input.addEventListener('change', function() {
    document.querySelectorAll('.theme-option').forEach(opt => {
      opt.style.borderColor = 'var(--border-color)';
      opt.style.backgroundColor = 'transparent';
    });
    
    if (this.checked) {
      this.closest('.theme-option').style.borderColor = 'var(--color-primary)';
      this.closest('.theme-option').style.backgroundColor = 'var(--bg-secondary)';
    }
  });
  
  // Set initial state
  if (input.checked) {
    option.style.borderColor = 'var(--color-primary)';
    option.style.backgroundColor = 'var(--bg-secondary)';
  }
});

// Apply theme immediately on selection (optional live preview)
document.querySelectorAll('input[name="theme"]').forEach(radio => {
  radio.addEventListener('change', function() {
    const theme = this.value;
    // You can add live preview here if desired
    console.log('Theme selected:', theme);
  });
});

// ============================================
// FONT DETECTION & MANAGEMENT
// ============================================

// Load FontDetector
const fontDetectorScript = document.createElement('script');
fontDetectorScript.src = 'assets/js/font-detector.js';
fontDetectorScript.onload = function() {
  console.log('FontDetector loaded');
};
document.head.appendChild(fontDetectorScript);

// Detect Fonts Button Handler
document.getElementById('detectFontsBtn').addEventListener('click', async function() {
  const btn = this;
  const btnText = btn.innerHTML;
  
  // Show loading state
  btn.disabled = true;
  btn.innerHTML = `
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
      <path d="M21 12a9 9 0 11-6.219-8.56"/>
    </svg>
    Scanning...
  `;
  
  // Add spin animation
  const spinStyle = document.createElement('style');
  spinStyle.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
  document.head.appendChild(spinStyle);
  
  try {
    // Wait for FontDetector to load
    if (typeof FontDetector === 'undefined') {
      await new Promise(resolve => setTimeout(resolve, 500));
    }
    
    const detector = new FontDetector();
    const fonts = await detector.detectAvailableFonts();
    
    // Display results
    displayDetectedFonts(fonts);
    
    if (typeof Toast !== 'undefined') {
      Toast.success(`Detected ${fonts.length} available fonts!`);
    }
  } catch (error) {
    console.error('Font detection error:', error);
    if (typeof Toast !== 'undefined') {
      Toast.error('Failed to detect fonts. Check console for details.');
    }
  } finally {
    // Restore button
    btn.disabled = false;
    btn.innerHTML = btnText;
  }
});

function displayDetectedFonts(fonts) {
  const container = document.getElementById('detectedFonts');
  const fontsList = document.getElementById('detectedFontsList');
  const fontCount = document.getElementById('fontCount');
  
  // Update count
  fontCount.textContent = `${fonts.length} fonts found`;
  
  // Group by category
  const categorized = {
    'sans-serif': [],
    'serif': [],
    'monospace': []
  };
  
  fonts.forEach(font => {
    if (categorized[font.category]) {
      categorized[font.category].push(font);
    }
  });
  
  // Build HTML
  let html = '';
  
  Object.keys(categorized).forEach(category => {
    if (categorized[category].length > 0) {
      html += `
        <div style="margin-bottom: 1rem;">
          <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.5rem; color: var(--color-primary); text-transform: uppercase;">
            ${category} (${categorized[category].length})
          </div>
          <div style="display: grid; gap: 0.5rem;">
      `;
      
      categorized[category].forEach(font => {
        html += `
          <div style="padding: 0.625rem; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px;">
            <div style="font-weight: 500; font-size: 0.875rem; margin-bottom: 0.25rem;">${font.name}</div>
            <div style="font-family: ${font.stack}; font-size: 0.8125rem; color: var(--text-secondary);">
              The quick brown fox jumps over the lazy dog
            </div>
          </div>
        `;
      });
      
      html += `
          </div>
        </div>
      `;
    }
  });
  
  fontsList.innerHTML = html;
  container.style.display = 'block';
}

// Font Option Card Hover Effects
document.querySelectorAll('.font-option-card').forEach(card => {
  card.addEventListener('mouseenter', function() {
    if (!this.querySelector('input[type="radio"]').checked) {
      this.style.borderColor = 'var(--color-primary)';
      this.style.transform = 'translateY(-2px)';
      this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
    }
  });
  
  card.addEventListener('mouseleave', function() {
    if (!this.querySelector('input[type="radio"]').checked) {
      this.style.borderColor = 'var(--border-color)';
      this.style.transform = 'translateY(0)';
      this.style.boxShadow = 'none';
    }
  });
});

// ============================================
// ROLE HELP TOOLTIP WITH SMART POSITIONING
// ============================================

const helpIcon = document.querySelector('.help-icon');
const tooltipContent = document.querySelector('.tooltip-content');
const tooltipArrow = document.querySelector('.tooltip-arrow');

if (helpIcon && tooltipContent && tooltipArrow) {
  
  function positionTooltip() {
    const iconRect = helpIcon.getBoundingClientRect();
    const tooltipRect = tooltipContent.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    const tooltipWidth = 320;
    const tooltipHeight = tooltipContent.offsetHeight || 200; // Approximate height
    const gap = 12; // Gap between icon and tooltip
    
    let top, left;
    let arrowPosition = 'bottom'; // Default: arrow at bottom (tooltip above)
    
    // Determine vertical position
    const spaceAbove = iconRect.top;
    const spaceBelow = viewportHeight - iconRect.bottom;
    
    if (spaceAbove >= tooltipHeight + gap) {
      // Position above
      top = iconRect.top - tooltipHeight - gap;
      arrowPosition = 'bottom';
    } else if (spaceBelow >= tooltipHeight + gap) {
      // Position below
      top = iconRect.bottom + gap;
      arrowPosition = 'top';
    } else {
      // Not enough space either way, position above and let it scroll
      top = Math.max(10, iconRect.top - tooltipHeight - gap);
      arrowPosition = 'bottom';
    }
    
    // Determine horizontal position (centered on icon)
    left = iconRect.left + (iconRect.width / 2) - (tooltipWidth / 2);
    
    // Keep tooltip within viewport horizontally
    const padding = 16;
    if (left < padding) {
      left = padding;
    } else if (left + tooltipWidth > viewportWidth - padding) {
      left = viewportWidth - tooltipWidth - padding;
    }
    
    // Apply positions
    tooltipContent.style.top = top + 'px';
    tooltipContent.style.left = left + 'px';
    
    // Position arrow
    const arrowLeft = iconRect.left + (iconRect.width / 2) - left - 6; // 6 = half arrow width
    
    if (arrowPosition === 'bottom') {
      // Arrow at bottom (pointing down)
      tooltipArrow.style.bottom = '-6px';
      tooltipArrow.style.top = 'auto';
      tooltipArrow.style.left = arrowLeft + 'px';
    } else {
      // Arrow at top (pointing up)
      tooltipArrow.style.top = '-6px';
      tooltipArrow.style.bottom = 'auto';
      tooltipArrow.style.left = arrowLeft + 'px';
    }
  }
  
  function showTooltip() {
    positionTooltip();
    tooltipContent.style.opacity = '1';
    tooltipContent.style.visibility = 'visible';
    tooltipContent.style.pointerEvents = 'auto';
  }
  
  function hideTooltip() {
    tooltipContent.style.opacity = '0';
    tooltipContent.style.visibility = 'hidden';
    tooltipContent.style.pointerEvents = 'none';
  }
  
  // Show tooltip on icon hover
  helpIcon.addEventListener('mouseenter', showTooltip);
  helpIcon.addEventListener('mouseleave', function() {
    // Delay hiding to allow moving to tooltip
    setTimeout(() => {
      if (!tooltipContent.matches(':hover') && !helpIcon.matches(':hover')) {
        hideTooltip();
      }
    }, 100);
  });
  
  // Keep tooltip visible when hovering over it
  tooltipContent.addEventListener('mouseenter', showTooltip);
  tooltipContent.addEventListener('mouseleave', function() {
    setTimeout(() => {
      if (!tooltipContent.matches(':hover') && !helpIcon.matches(':hover')) {
        hideTooltip();
      }
    }, 100);
  });
  
  // Reposition on scroll/resize
  window.addEventListener('scroll', () => {
    if (tooltipContent.style.opacity === '1') {
      positionTooltip();
    }
  });
  
  window.addEventListener('resize', () => {
    if (tooltipContent.style.opacity === '1') {
      positionTooltip();
    }
  });
}

// ============================================
// FONT FILE UPLOAD PREVIEW
// ============================================

// File Upload Preview
document.getElementById('font_file')?.addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
    // Validate file size
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
      if (typeof Toast !== 'undefined') {
        Toast.error('File size exceeds 5MB limit');
      }
      this.value = '';
      return;
    }
    
    // Show file info
    if (typeof Toast !== 'undefined') {
      const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
      Toast.info(`Selected: ${file.name} (${sizeMB}MB)`);
    }
    
    // Auto-fill font name if empty
    const fontNameInput = document.getElementById('font_name');
    if (!fontNameInput.value) {
      const baseName = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
      fontNameInput.value = baseName.charAt(0).toUpperCase() + baseName.slice(1);
    }
    
    // Auto-fill font family if empty
    const fontFamilyInput = document.getElementById('font_family_name');
    if (!fontFamilyInput.value) {
      const familyName = file.name.replace(/\.[^/.]+$/, '').replace(/\s+/g, '');
      fontFamilyInput.value = familyName;
    }
  }
});

// Check for upload/delete success in URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('upload') === 'success' && typeof Toast !== 'undefined') {
  Toast.success('Font uploaded successfully!');
  // Remove parameter from URL
  window.history.replaceState({}, document.title, window.location.pathname + '?tab=regional');
}
if (urlParams.get('delete') === 'success' && typeof Toast !== 'undefined') {
  Toast.success('Font deleted successfully!');
  window.history.replaceState({}, document.title, window.location.pathname + '?tab=regional');
}

// Switch to regional tab if URL parameter present
if (urlParams.get('tab') === 'regional') {
  const regionalTab = document.querySelector('[data-tab="regional"]');
  if (regionalTab) {
    setTimeout(() => regionalTab.click(), 100);
  }
}

// ============================================
// SESSION MANAGEMENT WITH MAP INTEGRATION
// ============================================

let sessionMap = null;
let sessionMarkers = [];

// Initialize Leaflet map
function initSessionMap() {
  if (!document.getElementById('session-map')) {
    return;
  }

  // Initialize map centered on world
  sessionMap = L.map('session-map', {
    zoomControl: true,
    scrollWheelZoom: true,
    doubleClickZoom: true,
    touchZoom: true
  }).setView([20, 0], 2);

  // Add OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 18
  }).addTo(sessionMap);
}

// Load and display sessions
async function loadSessions() {
  try {
    const response = await fetch('api/get-sessions.php?type=active');
    const data = await response.json();

    if (data.success && data.sessions) {
      displayCurrentSession(data.sessions);
      displaySessions(data.sessions);
      updateSessionMap(data.sessions);
    } else {
      showError('Failed to load sessions');
    }
  } catch (error) {
    console.error('Error loading sessions:', error);
    showError('Error loading sessions');
  }
}

// Display only current session (minimal view)
function displayCurrentSession(sessions) {
  const container = document.getElementById('current-session-display');
  
  if (!container) return;
  
  const currentSession = sessions.find(s => s.is_current);
  
  if (!currentSession) {
    container.innerHTML = `
      <div style="text-align: center; padding: 1rem; color: var(--text-secondary); font-size: 0.875rem;">
        No active session
      </div>
    `;
    return;
  }

  const location = currentSession.location;
  const locationText = location.city !== 'Unknown' 
    ? `${location.city}, ${location.country}`
    : location.country;

  container.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div style="flex: 1;">
        <div style="font-weight: 500; font-size: 0.9375rem; margin-bottom: 0.375rem;">${locationText}</div>
        <div style="font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.5;">
          <div style="margin-bottom: 0.125rem;">${currentSession.os} · ${currentSession.browser}</div>
          <div style="font-family: monospace; font-size: 0.75rem;">IP: ${currentSession.ip_address}</div>
        </div>
      </div>
      <span style="background: var(--color-success); color: white; padding: 0.25rem 0.625rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 600; white-space: nowrap; margin-left: 1rem;">
        Active
      </span>
    </div>
  `;
}

// Display sessions in list
function displaySessions(sessions) {
  const container = document.getElementById('sessions-container');
  const countElement = document.getElementById('session-count');
  
  // Update session count
  if (countElement) {
    countElement.textContent = `${sessions.length} session${sessions.length !== 1 ? 's' : ''}`;
  }
  
  if (sessions.length === 0) {
    container.innerHTML = `
      <div style="text-align: center; padding: 3rem 2rem; color: var(--text-secondary);">
        <div style="width: 48px; height: 48px; margin: 0 auto 1rem; border-radius: 50%; background: var(--bg-primary); display: flex; align-items: center; justify-content: center;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </div>
        <p style="margin: 0; font-weight: 500;">No active sessions</p>
      </div>
    `;
    return;
  }

  let html = '';
  sessions.forEach((session, index) => {
    const isCurrent = session.is_current;
    const location = session.location;
    const locationText = location.city !== 'Unknown' 
      ? `${location.city}, ${location.country}`
      : location.country;

    html += `
      <div class="session-card" data-session-id="${index}" style="background: var(--bg-primary); border-radius: var(--radius-md); border: ${isCurrent ? '2px solid var(--color-primary)' : '1px solid var(--bg-tertiary)'}; margin-bottom: 0.75rem; overflow: hidden; box-shadow: ${isCurrent ? '0 2px 8px rgba(0,0,0,0.08)' : '0 1px 3px rgba(0,0,0,0.04)'}; transition: box-shadow 0.2s;">
        
        <!-- Session Header (Clickable) -->
        <div onclick="toggleSessionDetails(${index})" style="padding: 1rem; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='var(--bg-secondary)'; this.closest('.session-card').style.boxShadow='0 4px 12px rgba(0,0,0,0.12)'" onmouseout="this.style.background='var(--bg-primary)'; this.closest('.session-card').style.boxShadow='${isCurrent ? '0 2px 8px rgba(0,0,0,0.08)' : '0 1px 3px rgba(0,0,0,0.04)'}'">
          <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
            <div style="flex: 1; min-width: 0;">
              <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
                <strong style="font-size: 0.9375rem;">${locationText}</strong>
                ${isCurrent ? '<span style="background: var(--color-primary); color: white; padding: 0.125rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 600;">Current</span>' : ''}
              </div>
              <div style="font-size: 0.8125rem; color: var(--text-secondary);">
                ${session.os} · ${session.browser}
              </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              ${isCurrent ? `
                <span style="background: var(--color-success); color: white; padding: 0.375rem 0.625rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 600; white-space: nowrap;">
                  Active
                </span>
              ` : ''}
              <svg id="chevron-${index}" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="transition: transform 0.2s; color: var(--text-secondary);">
                <path d="M6 9l6 6 6-6"/>
              </svg>
            </div>
          </div>
        </div>

        <!-- Session Details (Expandable) -->
        <div id="session-details-${index}" style="max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out;">
          <div style="padding: 0 1rem 1rem 1rem; border-top: 1px solid var(--border-color);">
            <div style="padding-top: 1rem;">
              
              <!-- IP Address -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">IP Address</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary); font-family: monospace;">${session.ip_address}</div>
              </div>

              <!-- Operating System -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">Operating System</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary);">${session.os}</div>
              </div>

              <!-- Browser -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">Browser</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary);">${session.browser}</div>
              </div>

              <!-- Location Details -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">Location</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary);">
                  ${location.city}, ${location.region ? location.region + ', ' : ''}${location.country}
                </div>
              </div>

              <!-- ISP -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">ISP</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary);">${location.isp || 'Unknown'}</div>
              </div>

              <!-- Timezone -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">Timezone</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary);">${location.timezone || 'Unknown'}</div>
              </div>

              <!-- Coordinates -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">Coordinates</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary); font-family: monospace;">${location.latitude.toFixed(4)}, ${location.longitude.toFixed(4)}</div>
              </div>

              <!-- Login Time -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">Login Time</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary);">${formatDateTime(session.login_time)}</div>
              </div>

              <!-- Last Activity -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">Last Activity</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary);">${formatDateTime(session.last_activity)} (${formatRelativeTime(session.last_activity)})</div>
              </div>

              <!-- Session ID -->
              <div style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem;">
                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-primary);">Session ID</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary); font-family: monospace; word-break: break-all;">${session.session_id}</div>
              </div>

            </div>
          </div>
        </div>

      </div>
    `;
  });

  container.innerHTML = html;
}

// Update map with session markers
function updateSessionMap(sessions) {
  if (!sessionMap) {
    return;
  }

  // Clear existing markers
  sessionMarkers.forEach(marker => marker.remove());
  sessionMarkers = [];

  // Add markers for each session
  const bounds = [];
  sessions.forEach(session => {
    const location = session.location;
    const lat = parseFloat(location.latitude);
    const lon = parseFloat(location.longitude);

    if (lat && lon && lat !== 0 && lon !== 0) {
      const isCurrent = session.is_current;
      
      // Create custom icon
      const icon = L.divIcon({
        className: 'session-marker',
        html: `
          <div style="
            width: ${isCurrent ? '32px' : '24px'}; 
            height: ${isCurrent ? '32px' : '24px'}; 
            background: ${isCurrent ? 'var(--color-primary)' : 'hsl(220 13% 40%)'}; 
            border: 3px solid white; 
            border-radius: 50%; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: ${isCurrent ? '14px' : '10px'};
            font-weight: bold;
            animation: ${isCurrent ? 'pulse 2s ease-in-out infinite' : 'none'};
          ">
            ${isCurrent ? '●' : '○'}
          </div>
        `,
        iconSize: [isCurrent ? 32 : 24, isCurrent ? 32 : 24],
        iconAnchor: [isCurrent ? 16 : 12, isCurrent ? 16 : 12]
      });

      // Add marker
      const marker = L.marker([lat, lon], { icon }).addTo(sessionMap);
      
      // Add popup
      const locationText = location.city !== 'Unknown' 
        ? `${location.city}, ${location.country}`
        : location.country;
      
      marker.bindPopup(`
        <div style="min-width: 200px;">
          <strong style="font-size: 1rem; display: block; margin-bottom: 0.5rem;">
            ${locationText}
            ${isCurrent ? '<span style="color: var(--color-primary);"> (Current)</span>' : ''}
          </strong>
          <div style="font-size: 0.875rem; line-height: 1.6;">
            <div><strong>OS:</strong> ${session.os}</div>
            <div><strong>Browser:</strong> ${session.browser}</div>
            <div><strong>IP:</strong> ${session.ip_address}</div>
            <div><strong>ISP:</strong> ${location.isp}</div>
            <div><strong>Login:</strong> ${formatDateTime(session.login_time)}</div>
          </div>
        </div>
      `);

      sessionMarkers.push(marker);
      bounds.push([lat, lon]);
    }
  });

  // Fit map to show all markers
  if (bounds.length > 0) {
    sessionMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 10 });
  }
}

// Track if sessions have been loaded
let sessionsLoaded = false;

// Modal functions
function openSessionsModal() {
  const modal = document.getElementById('sessions-modal');
  if (modal) {
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Load Leaflet.js and initialize map if not already done
    if (!sessionMap) {
      // Check if Leaflet is loaded
      if (typeof L === 'undefined') {
        // Load Leaflet CSS
        const leafletCSS = document.createElement('link');
        leafletCSS.rel = 'stylesheet';
        leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        leafletCSS.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
        leafletCSS.crossOrigin = '';
        document.head.appendChild(leafletCSS);

        // Load Leaflet JS
        const leafletJS = document.createElement('script');
        leafletJS.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        leafletJS.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
        leafletJS.crossOrigin = '';
        leafletJS.onload = function() {
          setTimeout(() => {
            initSessionMap();
            if (!sessionsLoaded) {
              loadSessions();
              sessionsLoaded = true;
            }
          }, 100);
        };
        document.head.appendChild(leafletJS);
      } else {
        // Leaflet already loaded, just initialize
        if (!sessionsLoaded) {
          setTimeout(() => {
            initSessionMap();
            loadSessions();
            sessionsLoaded = true;
          }, 100);
        } else {
          // Map exists, just invalidate size for proper rendering
          setTimeout(() => {
            if (sessionMap) {
              sessionMap.invalidateSize();
            }
          }, 100);
        }
      }
    } else {
      // Map already exists, just invalidate size
      setTimeout(() => {
        sessionMap.invalidateSize();
      }, 100);
    }
  }
}

function closeSessionsModal() {
  const modal = document.getElementById('sessions-modal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('sessions-modal');
    if (modal && modal.style.display === 'block') {
      closeSessionsModal();
    }
  }
});

// Toggle session details (accordion)
function toggleSessionDetails(index) {
  const detailsDiv = document.getElementById(`session-details-${index}`);
  const chevron = document.getElementById(`chevron-${index}`);
  
  if (!detailsDiv || !chevron) return;
  
  const isExpanded = detailsDiv.style.maxHeight && detailsDiv.style.maxHeight !== '0px';
  
  if (isExpanded) {
    // Collapse
    detailsDiv.style.maxHeight = '0px';
    chevron.style.transform = 'rotate(0deg)';
  } else {
    // Expand
    detailsDiv.style.maxHeight = detailsDiv.scrollHeight + 'px';
    chevron.style.transform = 'rotate(180deg)';
  }
}

// Refresh sessions
async function refreshSessions() {
  const button = event?.target?.closest('button');
  
  if (button) {
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = 'Refreshing...';
    
    await loadSessions();
    
    setTimeout(() => {
      button.disabled = false;
      button.innerHTML = originalHTML;
      if (typeof Toast !== 'undefined') {
        Toast.success('Sessions refreshed', 2000);
      }
    }, 300);
  } else {
    await loadSessions();
  }
}

// Terminate other sessions
async function terminateOtherSessions() {
  if (!confirm('Are you sure you want to log out all other sessions? This will immediately end all active sessions except your current one.')) {
    return;
  }

  try {
    const response = await fetch('api/terminate-sessions.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      }
    });

    const data = await response.json();

    if (data.success) {
      if (typeof Toast !== 'undefined') {
        Toast.success(data.message, 4000);
      }
      
      // Reload sessions
      setTimeout(() => loadSessions(), 1000);
    } else {
      if (typeof Toast !== 'undefined') {
        Toast.error(data.message, 4000);
      }
    }
  } catch (error) {
    console.error('Error terminating sessions:', error);
    if (typeof Toast !== 'undefined') {
      Toast.error('Failed to terminate sessions', 4000);
    }
  }
}

// Format datetime
function formatDateTime(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  });
}

// Format relative time
function formatRelativeTime(dateStr) {
  const now = new Date();
  const date = new Date(dateStr);
  const diffInSeconds = Math.floor((now - date) / 1000);

  if (diffInSeconds < 60) {
    return 'Just now';
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60);
    return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600);
    return `${hours} hour${hours > 1 ? 's' : ''} ago`;
  } else {
    const days = Math.floor(diffInSeconds / 86400);
    return `${days} day${days > 1 ? 's' : ''} ago`;
  }
}

// Show error
function showError(message) {
  const container = document.getElementById('sessions-container');
  container.innerHTML = `
    <div style="text-align: center; padding: 2rem; color: var(--color-danger);">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 1rem;">
        <circle cx="12" cy="12" r="10"/>
        <line x1="15" y1="9" x2="9" y2="15"/>
        <line x1="9" y1="9" x2="15" y2="15"/>
      </svg>
      <p style="margin: 0; font-weight: 500;">${message}</p>
    </div>
  `;
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
  // Load current session only on page load (minimal view)
  if (document.getElementById('current-session-display')) {
    loadSessions(); // This will populate the current session display
  }
  
  // Leaflet.js will be loaded when modal is opened
  // This improves initial page load performance
});

</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
