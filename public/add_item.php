<?php
/**
 * Add Item Page
 * This page allows users to add new inventory items
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Controller\InventoryController;

$authController = new AuthController();
$authController->requireLogin();

$inventoryController = new InventoryController();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemData = [
        'barcode' => $_POST['barcode'] ?? '',
        'name' => $_POST['name'] ?? '',
        'type' => $_POST['type'] ?? '',
        'lifespan' => $_POST['lifespan'] ?? '',
        'quantity' => (int)($_POST['quantity'] ?? 0)
    ];
    
    $result = $inventoryController->createItem($itemData);
    
    if ($result['success']) {
        session_start();
        $_SESSION['flash_message'] = 'Item added successfully!';
        $_SESSION['flash_type'] = 'success';
        header("Location: inventory-list.php");
        exit();
    } else {
        $error = $result['message'];
    }
}
$pageTitle = 'Add Item';
ob_start();
?>

<!-- Page Banner Header -->
<div class="page-banner">
  <div class="page-banner-content">
    <div class="page-banner-left">
      <h1 class="page-banner-title">Add New Item</h1>
      <div class="page-banner-meta">
        <div class="page-banner-meta-item">
          <strong>Form:</strong>
          <span>Quick Add</span>
        </div>
        <div class="page-banner-meta-item">
          <strong>Required Fields:</strong>
          <span class="text-warning">4</span>
        </div>
      </div>
    </div>
    <div class="page-banner-actions">
      <a href="inventory-list.php" class="btn btn-secondary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Back to List
      </a>
    </div>
  </div>
</div>

<div class="grid grid-cols-1" style="max-width: 800px; margin: 0 auto;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Item Information</h3>
      <p class="card-description">Fill in the details below to add a new inventory item</p>
    </div>
    <div class="card-content">
      <?php if ($error): ?>
      <div class="alert alert-danger mb-4">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php endif; ?>
      
      <form method="POST">
        <div class="form-group">
          <label for="barcode" class="form-label">
            Barcode <span class="required">*</span>
          </label>
          <div class="flex gap-2">
            <input 
              type="text" 
              id="barcode" 
              name="barcode" 
              class="form-input" 
              placeholder="Enter barcode"
              required
              autofocus
            >
            <button 
              type="button" 
              class="btn btn-secondary btn-icon" 
              onclick="openBarcodeScanner()"
              title="Scan barcode">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="12" r="1" fill="currentColor"/>
              </svg>
            </button>
          </div>
          <span class="form-helper">Click the camera icon to scan barcode or QR code</span>
        </div>
        
        <div class="form-group">
          <label for="name" class="form-label">
            Item Name <span class="required">*</span>
          </label>
          <input 
            type="text" 
            id="name" 
            name="name" 
            class="form-input" 
            placeholder="Enter item name"
            required
          >
        </div>
        
        <div class="form-group">
          <label for="type" class="form-label">
            Type <span class="required">*</span>
          </label>
          <select id="type" name="type" class="form-select" required>
            <option value="">Select Type</option>
            <option value="Packed Goods">Packed Goods</option>
            <option value="Fruits">Fruits</option>
            <option value="Vegetables">Vegetables</option>
            <option value="Pastries">Pastries</option>
            <option value="Beverages">Beverages</option>
            <option value="Electronics">Electronics</option>
            <option value="Other">Other</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="lifespan" class="form-label">
            Lifespan <span class="required">*</span>
          </label>
          <select id="lifespan" name="lifespan" class="form-select" required>
            <option value="">Select Lifespan</option>
            <option value="1 day">1 day</option>
            <option value="3 days">3 days</option>
            <option value="1 week">1 week</option>
            <option value="2 weeks">2 weeks</option>
            <option value="1 month">1 month</option>
            <option value="3 months">3 months</option>
            <option value="6 months">6 months</option>
            <option value="1 year">1 year</option>
            <option value="2 to 10 years">2 to 10 years</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="quantity" class="form-label">
            Quantity <span class="required">*</span>
          </label>
          <input 
            type="number" 
            id="quantity" 
            name="quantity" 
            class="form-input" 
            value="0" 
            min="0" 
            required
          >
          <span class="form-helper">Initial stock quantity</span>
        </div>
        
        <!-- Form Progress Indicator -->
        <div class="form-group">
          <div style="background: var(--bg-secondary); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span class="text-sm font-medium">Form Completion</span>
              <span class="text-sm font-semibold" id="progressText">0%</span>
            </div>
            <div style="width: 100%; height: 8px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden;">
              <div id="progressBar" style="height: 100%; width: 0%; background: var(--color-success); transition: width 0.3s ease;"></div>
            </div>
          </div>
        </div>
        
        <div class="flex gap-3 mt-6">
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Add Item
          </button>
          <a href="inventory-list.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Form validation and progress tracking
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('form');
  const requiredFields = form.querySelectorAll('[required]');
  const progressBar = document.getElementById('progressBar');
  const progressText = document.getElementById('progressText');
  const submitBtn = document.getElementById('submitBtn');
  
  // Update progress on input
  function updateProgress() {
    let filledFields = 0;
    requiredFields.forEach(field => {
      if (field.value.trim() !== '') {
        filledFields++;
      }
    });
    
    const progress = Math.round((filledFields / requiredFields.length) * 100);
    progressBar.style.width = progress + '%';
    progressText.textContent = progress + '%';
    
    // Change color based on progress
    if (progress < 50) {
      progressBar.style.background = 'var(--color-danger)';
    } else if (progress < 100) {
      progressBar.style.background = 'var(--color-warning)';
    } else {
      progressBar.style.background = 'var(--color-success)';
    }
    
    // Enable/disable submit button
    submitBtn.disabled = progress < 100;
    if (progress < 100) {
      submitBtn.style.opacity = '0.5';
      submitBtn.style.cursor = 'not-allowed';
    } else {
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
    }
  }
  
  // Add event listeners to all required fields
  requiredFields.forEach(field => {
    field.addEventListener('input', updateProgress);
    field.addEventListener('change', updateProgress);
  });
  
  // Initial progress check
  updateProgress();
  
  // Real-time validation feedback
  requiredFields.forEach(field => {
    field.addEventListener('blur', function() {
      if (this.value.trim() === '') {
        this.style.borderColor = 'var(--color-danger)';
      } else {
        this.style.borderColor = 'var(--color-success)';
      }
    });
    
    field.addEventListener('focus', function() {
      this.style.borderColor = 'var(--color-primary)';
    });
  });
  
  // Form submission
  form.addEventListener('submit', function(e) {
    // Show loading state
    submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg> Adding...';
    submitBtn.disabled = true;
  });
});

// Barcode scanner function
function openBarcodeScanner() {
  showToast('Opening barcode scanner...', 'info');
  // In production, integrate with barcode scanner library
  setTimeout(() => {
    const mockBarcode = 'BC' + Math.floor(Math.random() * 1000000);
    document.getElementById('barcode').value = mockBarcode;
    document.getElementById('barcode').dispatchEvent(new Event('input'));
    showToast('Barcode scanned: ' + mockBarcode, 'success');
  }, 1000);
}

// Add keyframe animation for loading spinner
const style = document.createElement('style');
style.textContent = `
  @keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }
`;
document.head.appendChild(style);
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
