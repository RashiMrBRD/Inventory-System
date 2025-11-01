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
  
  <!-- Additional CSS -->
  <?php foreach ($additionalCSS as $css): ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
  <?php endforeach; ?>
  
  <!-- Google Fonts - Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
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

  <!-- Core JavaScript -->
  <script src="assets/js/sidebar.js"></script>
  
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
