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

// Ensure session is started
SessionHelper::start();

// Set cache control headers for dynamic pages
if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Default values
$pageTitle = $pageTitle ?? 'Inventory Management';
$pageContent = $pageContent ?? '';
$additionalCSS = $additionalCSS ?? [];
$additionalJS = $additionalJS ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta name="description" content="Professional Inventory Management System">
  <title><?php echo htmlspecialchars($pageTitle); ?> - Inventory System</title>
  
  <!-- Favicon -->
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><rect x='3' y='3' width='18' height='18' rx='2' fill='%232563eb'/></svg>">
  
  <!-- Core CSS -->
  <link rel="stylesheet" href="assets/css/core.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link rel="stylesheet" href="assets/css/utilities.css">
  
  <!-- Toast Notification System -->
  <link rel="stylesheet" href="assets/css/toast.css">
  
  <!-- Mobile Menu System -->
  <link rel="stylesheet" href="assets/css/mobile-menu.css">
  
  <!-- Bottom Navigation Bar -->
  <link rel="stylesheet" href="assets/css/bottom-nav.css">
  
  <!-- Font System (Offline-first) -->
  <link rel="stylesheet" href="assets/css/fonts.css">
  
  <!-- Custom Fonts (User uploaded) -->
  <?php if (file_exists(__DIR__ . '/../../public/assets/css/custom-fonts.css')): ?>
  <link rel="stylesheet" href="assets/css/custom-fonts.css">
  <?php endif; ?>
  
  <!-- Additional CSS -->
  <?php foreach ($additionalCSS as $css): ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
  <?php endforeach; ?>
</head>
<body data-font="<?php echo htmlspecialchars($_SESSION['font_family'] ?? 'system'); ?>">
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
  <script src="assets/js/error-handler.js"></script>
  
  <!-- Core JavaScript -->
  <script src="assets/js/sidebar.js"></script>
  
  <!-- Mobile Menu System -->
  <script src="assets/js/mobile-menu.js"></script>
  
  <!-- Toast Notification System -->
  <script src="assets/js/toast.js"></script>
  
  <!-- Number Format API -->
  <script src="assets/js/number-format.js"></script>
  
  <!-- Keyboard Shortcuts System -->
  <script src="assets/js/keyboard-shortcuts.js"></script>
  
  <!-- Additional JavaScript -->
  <?php foreach ($additionalJS as $js): ?>
  <script src="<?php echo htmlspecialchars($js); ?>"></script>
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
