<?php
/**
 * Edit Item Page
 * This page allows users to edit existing inventory items
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Controller\InventoryController;

$authController = new AuthController();
$authController->requireLogin();

$inventoryController = new InventoryController();
$error = '';
$item = null;

// Get item ID from URL
$itemId = $_GET['id'] ?? '';

if (empty($itemId)) {
    header("Location: inventory-list.php");
    exit();
}

// Fetch the item
$result = $inventoryController->getItem($itemId);
if (!$result['success']) {
    header("Location: inventory-list.php");
    exit();
}

$item = $result['data'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemData = [
        'barcode' => $_POST['barcode'] ?? '',
        'name' => $_POST['name'] ?? '',
        'type' => $_POST['type'] ?? '',
        'lifespan' => $_POST['lifespan'] ?? '',
        'quantity' => (int)($_POST['quantity'] ?? 0)
    ];
    
    $result = $inventoryController->updateItem($itemId, $itemData);
    
    if ($result['success']) {
        session_start();
        $_SESSION['flash_message'] = 'Item updated successfully!';
        $_SESSION['flash_type'] = 'success';
        header("Location: inventory-list.php");
        exit();
    } else {
        $error = $result['message'];
    }
}

$pageTitle = 'Edit Item';
ob_start();
?>

<!-- Page Header -->
<div class="content-header">
  <div>
    <nav class="breadcrumb">
      <a href="dashboard.php" class="breadcrumb-link">Dashboard</a>
      <span class="breadcrumb-separator">/</span>
      <a href="inventory-list.php" class="breadcrumb-link">Inventory</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current">Edit Item</span>
    </nav>
    <h1 class="content-title">Edit Item</h1>
  </div>
</div>

<div class="grid grid-cols-1" style="max-width: 600px;">
  <div class="card">
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
              value="<?php echo htmlspecialchars($item['barcode'] ?? ''); ?>"
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
            value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
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
            <option value="Packed Goods" <?php echo ($item['type'] ?? '') === 'Packed Goods' ? 'selected' : ''; ?>>Packed Goods</option>
            <option value="Fruits" <?php echo ($item['type'] ?? '') === 'Fruits' ? 'selected' : ''; ?>>Fruits</option>
            <option value="Vegetables" <?php echo ($item['type'] ?? '') === 'Vegetables' ? 'selected' : ''; ?>>Vegetables</option>
            <option value="Pastries" <?php echo ($item['type'] ?? '') === 'Pastries' ? 'selected' : ''; ?>>Pastries</option>
            <option value="Beverages" <?php echo ($item['type'] ?? '') === 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
            <option value="Electronics" <?php echo ($item['type'] ?? '') === 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
            <option value="Other" <?php echo ($item['type'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="lifespan" class="form-label">
            Lifespan <span class="required">*</span>
          </label>
          <select id="lifespan" name="lifespan" class="form-select" required>
            <option value="">Select Lifespan</option>
            <option value="1 day" <?php echo ($item['lifespan'] ?? '') === '1 day' ? 'selected' : ''; ?>>1 day</option>
            <option value="3 days" <?php echo ($item['lifespan'] ?? '') === '3 days' ? 'selected' : ''; ?>>3 days</option>
            <option value="1 week" <?php echo ($item['lifespan'] ?? '') === '1 week' ? 'selected' : ''; ?>>1 week</option>
            <option value="2 weeks" <?php echo ($item['lifespan'] ?? '') === '2 weeks' ? 'selected' : ''; ?>>2 weeks</option>
            <option value="1 month" <?php echo ($item['lifespan'] ?? '') === '1 month' ? 'selected' : ''; ?>>1 month</option>
            <option value="3 months" <?php echo ($item['lifespan'] ?? '') === '3 months' ? 'selected' : ''; ?>>3 months</option>
            <option value="6 months" <?php echo ($item['lifespan'] ?? '') === '6 months' ? 'selected' : ''; ?>>6 months</option>
            <option value="1 year" <?php echo ($item['lifespan'] ?? '') === '1 year' ? 'selected' : ''; ?>>1 year</option>
            <option value="2 to 10 years" <?php echo ($item['lifespan'] ?? '') === '2 to 10 years' ? 'selected' : ''; ?>>2 to 10 years</option>
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
            value="<?php echo htmlspecialchars($item['quantity'] ?? 0); ?>"
            min="0" 
            required
          >
          <span class="form-helper">Current stock quantity</span>
        </div>
        
        <div class="flex gap-3 mt-6">
          <button type="submit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Update Item
          </button>
          <a href="inventory-list.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
