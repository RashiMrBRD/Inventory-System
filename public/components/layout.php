<?php
/**
 * Layout Component
 * Main layout wrapper for the application
 * 
 * Usage:
 * $pageTitle = 'Dashboard';
 * $pageContent = '<div>Your content here</div>';
 * include 'components/layout.php';
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Helper\SessionHelper;
use App\Controller\AuthController;

// Ensure session is started
SessionHelper::start();

// Enforce authentication and valid user session for all layout-based pages
$auth = new AuthController();
if (!$auth->checkSessionTimeout()) {
    header('Location: /login');
    exit;
}
if (!$auth->isLoggedIn()) {
    header('Location: /login');
    exit;
}
$currentUser = $auth->getCurrentUser();
if (!$currentUser) {
    $auth->logout();
    header('Location: /login');
    exit;
}

// Normalize core session fields from current user to avoid implicit guest state
if (empty($_SESSION['username']) && !empty($currentUser['username'])) {
    $_SESSION['username'] = $currentUser['username'];
}
if (empty($_SESSION['full_name'])) {
    $normalizedName = '';
    if (!empty($currentUser['full_name'])) {
        $normalizedName = $currentUser['full_name'];
    } elseif (!empty($currentUser['username'])) {
        $normalizedName = $currentUser['username'];
    }
    if ($normalizedName !== '') {
        $_SESSION['full_name'] = $normalizedName;
    }
}
if (empty($_SESSION['access_level']) && !empty($currentUser['access_level'])) {
    $_SESSION['access_level'] = $currentUser['access_level'];
}

// Default values
$pageTitle = $pageTitle ?? 'Inventory Management';
$pageContent = $pageContent ?? '';
$additionalCSS = $additionalCSS ?? [];
$additionalJS = $additionalJS ?? [];
$dashboardScriptConfig = $dashboardScriptConfig ?? null;
$loadDashboardJS = $loadDashboardJS ?? false;

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        if (preg_match('/^(https?:)?\/\//i', $path)) {
            return $path;
        }

        static $baseUrl = null;
        static $basePath = null;

        if ($baseUrl === null || $basePath === null) {
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $dir = str_replace('\\', '/', dirname($scriptName));

            if ($dir === '\\' || $dir === '/') {
                $dir = '';
            }

            $dir = trim($dir, '/');
            if ($dir === '.') {
                $dir = '';
            }

            $basePath = $dir;

            $scheme = 'http';
            if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
            } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $scheme = 'https';
            }

            $host = $_SERVER['HTTP_HOST'] ?? '';

            if ($host !== '') {
                $baseUrl = rtrim(sprintf('%s://%s', $scheme, $host), '/');
                if ($basePath !== '') {
                    $baseUrl .= '/' . $basePath;
                }
            } else {
                $baseUrl = '';
            }
        }

        $normalizedPath = ltrim($path, '/');
        $relativePath = $normalizedPath;
        if ($basePath !== '' && strpos($normalizedPath, $basePath . '/') === 0) {
            $relativePath = substr($normalizedPath, strlen($basePath) + 1);
        }

        // Proxy assets and features via signed URL with no-cache headers
        if (preg_match('#^(assets|features)/#', $relativePath)) {
            static $signingKey = null;
            if ($signingKey === null) {
                // Load from config or env once
                $configPath = __DIR__ . '/../../config/app.php';
                $cfg = file_exists($configPath) ? require $configPath : [];
                $signingKey = $cfg['assets']['signing_key'] ?? (getenv('ASSET_SIGNING_KEY') ?: 'change-me-dev-key');
            }

            $exp = time() + 3600; // 1 hour expiry
            try {
                $nonce = bin2hex(random_bytes(12));
            } catch (\Throwable $e) {
                $nonce = bin2hex((string)mt_rand());
            }
            $b64 = rtrim(strtr(base64_encode($relativePath), '+/', '-_'), '=');
            $sig = hash_hmac('sha256', $relativePath . '|' . $exp . '|' . $nonce, $signingKey);

            $prefix = $baseUrl !== ''
                ? $baseUrl . '/'
                : '/' . ($basePath !== '' ? $basePath . '/' : '');

            return $prefix . 'asset?d=' . rawurlencode($b64) . '&e=' . $exp . '&n=' . rawurlencode($nonce) . '&s=' . $sig;
        }

        $url = $baseUrl !== ''
            ? $baseUrl . '/' . $relativePath
            : '/' . ($basePath !== '' ? $basePath . '/' : '') . $relativePath;

        $absolutePath = realpath(__DIR__ . '/../' . $normalizedPath);
        if ($absolutePath && is_file($absolutePath)) {
            $version = filemtime($absolutePath);
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . 'v=' . $version;
        }

        return $url;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script src="<?php echo asset_url('assets/js/debug-logger.js'); ?>"></script>
  <script src="<?php echo asset_url('assets/js/theme-sync.js'); ?>"></script>
  <script src="<?php echo asset_url('assets/js/navbar-interactions.js'); ?>"></script>
  <script src="<?php echo asset_url('assets/js/vendor/jsbarcode.min.js'); ?>"></script>
  <script src="<?php echo asset_url('assets/js/vendor/html5-qrcode.min.js'); ?>"></script>
  <script src="<?php echo asset_url('assets/js/barcode-scanner.js'); ?>"></script>
  <?php if ($dashboardScriptConfig): ?>
  <script>
    window.DASHBOARD_CONFIG = <?php echo json_encode($dashboardScriptConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  </script>
  <?php endif; ?>
  <?php if ($loadDashboardJS): ?>
  <script src="<?php echo asset_url('assets/js/dashboard.js'); ?>"></script>
  <?php endif; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Inventory Management System">
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/dashboard.css'); ?>">
  <title><?php echo htmlspecialchars($pageTitle); ?> - Inventory System</title>

  <!-- Favicon -->
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><rect x='3' y='3' width='18' height='18' rx='2' fill='%232563eb'/></svg>">
  
  <!-- Core CSS -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/core.css'); ?>">
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/components.css'); ?>">
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/layout.css'); ?>">
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/utilities.css'); ?>">
  
  <!-- Toast Notification System -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/toast.css'); ?>">
  
  <!-- Mobile Menu System -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/mobile-menu.css'); ?>">
  
  <!-- Bottom Navigation Bar -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/bottom-nav.css'); ?>">
  
  <!-- Mobile Support - Comprehensive responsive styles -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/mobile-support.css'); ?>">
  
  <!-- Pull-to-Refresh (Mobile Only) -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/pull-to-refresh.css'); ?>">
  
  <!-- Font System (Offline-first) -->
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/fonts.css'); ?>">
  
  <!-- Custom Fonts (User uploaded) -->
  <?php if (file_exists(__DIR__ . '/../../public/assets/css/custom-fonts.css')): ?>
  <link rel="stylesheet" href="<?php echo asset_url('assets/css/custom-fonts.css'); ?>">
  <?php endif; ?>
  
  <!-- Additional CSS -->
  <?php foreach ($additionalCSS as $css): ?>
  <link rel="stylesheet" href="<?php echo asset_url($css); ?>">
  <?php endforeach; ?>
</head>
<body data-font="<?php echo htmlspecialchars($_SESSION['font_family'] ?? 'system'); ?>" data-theme="<?php echo htmlspecialchars($_SESSION['theme'] ?? 'light'); ?>" data-resolved-theme="<?php echo htmlspecialchars($_SESSION['theme'] ?? 'light'); ?>">
  <!-- Mobile Menu -->
  <?php include __DIR__ . '/mobile-menu.php'; ?>

  <!-- Bottom Navigation (Mobile Only) -->
  <?php include __DIR__ . '/bottom-nav.php'; ?>

  <div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="main-container">
      <?php include __DIR__ . '/header.php'; ?>
      
      <main class="content">
        <?php echo $pageContent; ?>
      </main>
    </div>
  </div>

  <!-- Toast notification container -->
  <div class="toast-container" id="toast-container"></div>

  <!-- Barcode Scanner Modal -->
  <?php include __DIR__ . '/barcode-scanner.php'; ?>

  <!-- Error Handler (Load first to catch all errors) -->
  <script src="<?php echo asset_url('assets/js/error-handler.js'); ?>"></script>
  
  <!-- Theme API (Load early for theme-dependent components) -->
  <script src="<?php echo asset_url('assets/js/theme-api.js'); ?>"></script>

  <!-- Core JavaScript -->
  <script src="<?php echo asset_url('assets/js/sidebar.js'); ?>"></script>
  
  <!-- Mobile Menu System -->
  <script src="<?php echo asset_url('assets/js/mobile-menu.js'); ?>"></script>
  
  <!-- Toast Notification System -->
  <script src="<?php echo asset_url('assets/js/toast.js'); ?>"></script>
  
  <!-- Universal Search Fix -->
  <script src="<?php echo asset_url('assets/js/search-fix.js'); ?>"></script>
  
  <!-- Number Format API -->
  <script src="<?php echo asset_url('assets/js/number-format.js'); ?>"></script>
  
  <!-- Keyboard Shortcuts System -->
  <script src="<?php echo asset_url('assets/js/keyboard-shortcuts.js'); ?>"></script>
  
  <!-- Pull-to-Refresh (Mobile Only) -->
  <script src="<?php echo asset_url('assets/js/pull-to-refresh.js'); ?>"></script>
  
  <!-- Additional JavaScript -->
  <?php foreach ($additionalJS as $js): ?>
  <script src="<?php echo asset_url($js); ?>"></script>
  <?php endforeach; ?>

  <?php if (isset($_SESSION['flash_message'])): ?>
  <script>
  // Display flash message
  showToast(
    '<?php echo addslashes($_SESSION['flash_message']); ?>',
    '<?php echo $_SESSION['flash_type'] ?? 'info'; ?>'
  );
  </script>
  <?php 
  unset($_SESSION['flash_message']);
  unset($_SESSION['flash_type']);
  endif; 
  ?>
</body>
</html>
