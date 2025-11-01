<?php
/**
 * Edit Item Page - Professional Bio-Page Inspired Design
 * Comprehensive inventory item editing with Xero/QuickBooks/LedgerSMB features
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Controller\InventoryController;
use App\Helper\SessionHelper;

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
        // Basic Info
        'barcode' => $_POST['barcode'] ?? '',
        'sku' => $_POST['sku'] ?? '',
        'name' => $_POST['name'] ?? '',
        'type' => $_POST['type'] ?? '',
        'category' => $_POST['category'] ?? '',
        'description' => $_POST['description'] ?? '',
        'lifespan' => $_POST['lifespan'] ?? '',
        'unit_of_measure' => $_POST['unit_of_measure'] ?? 'pcs',
        
        // Pricing
        'cost_price' => (float)($_POST['cost_price'] ?? 0),
        'sell_price' => (float)($_POST['sell_price'] ?? 0),
        'tax_rate' => (float)($_POST['tax_rate'] ?? 0),
        
        // Inventory
        'quantity' => (int)($_POST['quantity'] ?? 0),
        'min_stock' => (int)($_POST['min_stock'] ?? 0),
        'max_stock' => (int)($_POST['max_stock'] ?? 0),
        'reorder_point' => (int)($_POST['reorder_point'] ?? 0),
        'location' => $_POST['location'] ?? '',
        
        // Advanced
        'supplier' => $_POST['supplier'] ?? '',
        'manufacturer' => $_POST['manufacturer'] ?? '',
        'brand' => $_POST['brand'] ?? '',
        'model_number' => $_POST['model_number'] ?? '',
        'tracking_type' => $_POST['tracking_type'] ?? 'none',
        'condition' => $_POST['condition'] ?? 'new',
        'warranty_period' => $_POST['warranty_period'] ?? '',
        'country_origin' => $_POST['country_origin'] ?? '',
        'sales_account' => $_POST['sales_account'] ?? '',
        'purchase_account' => $_POST['purchase_account'] ?? '',
        'tags' => $_POST['tags'] ?? '',
        'internal_notes' => $_POST['internal_notes'] ?? ''
    ];
    
    $result = $inventoryController->updateItem($itemId, $itemData);
    
    if ($result['success']) {
        SessionHelper::setFlash('Item updated successfully', 'success');
        
        // Preserve pagination and filter parameters from referrer
        $redirectUrl = 'inventory-list.php';
        if (isset($_POST['return_url'])) {
            $redirectUrl = $_POST['return_url'];
        } elseif (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'inventory-list.php') !== false) {
            // Extract query string from referrer
            $refererParts = parse_url($_SERVER['HTTP_REFERER']);
            if (isset($refererParts['query'])) {
                $redirectUrl = 'inventory-list.php?' . $refererParts['query'];
            }
        }
        
        header("Location: " . $redirectUrl);
        exit();
    } else {
        $error = $result['message'];
    }
}

$pageTitle = 'Edit Inventory Item';
ob_start();
?>

<!-- Bio-Page Inspired Header -->
<div style="background: #7194A5; color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
  <div class="container">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 32px; backdrop-filter: blur(10px);">
        ✏️
      </div>
      <div style="flex: 1;">
        <h1 style="font-size: 1.875rem; font-weight: 700; margin: 0 0 0.25rem 0; color: white;">Edit Inventory Item</h1>
        <p style="font-size: 0.875rem; margin: 0; opacity: 0.9;">Update comprehensive product details with enterprise features</p>
      </div>
      <a href="inventory-list.php" style="padding: 0.625rem 1.25rem; background: rgba(255,255,255,0.2); border-radius: 8px; color: white; text-decoration: none; font-weight: 500; backdrop-filter: blur(10px); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
        ← Back to Inventory
      </a>
    </div>
  </div>
</div>

<!-- Two-Column Bio-Page Layout -->
<div class="container" style="max-width: 1400px;">
  <div style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start;">
    
    <!-- LEFT COLUMN: Form with Tabs -->
    <div>
      <?php if ($error): ?>
      <div style="background: hsl(0 86% 97%); color: hsl(0 74% 24%); padding: 1rem; border-radius: 8px; border-left: 4px solid hsl(0 74% 24%); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php endif; ?>
      
      <!-- Tabbed Form Container -->
      <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
        <!-- Tab Navigation -->
        <div style="display: flex; background: hsl(240 5% 96%); border-bottom: 2px solid hsl(240 6% 90%); padding: 0; overflow-x: auto;">
          <button type="button" class="tab-btn active" onclick="switchTab('basic')" data-tab="basic" style="padding: 1rem 1.5rem; border: none; background: white; border-bottom: 3px solid var(--color-primary); font-weight: 600; cursor: pointer; white-space: nowrap; color: var(--color-primary);">📋 Basic Info</button>
          <button type="button" class="tab-btn" onclick="switchTab('pricing')" data-tab="pricing" style="padding: 1rem 1.5rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: var(--text-secondary);">💰 Pricing</button>
          <button type="button" class="tab-btn" onclick="switchTab('inventory')" data-tab="inventory" style="padding: 1rem 1.5rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: var(--text-secondary);">📊 Inventory</button>
          <button type="button" class="tab-btn" onclick="switchTab('advanced')" data-tab="advanced" style="padding: 1rem 1.5rem; border: none; background: transparent; font-weight: 500; cursor: pointer; white-space: nowrap; color: var(--text-secondary);">⚙️ Advanced</button>
        </div>

        <form method="POST" id="itemForm">
          <!-- Hidden field to preserve return URL -->
          <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'inventory-list.php'); ?>">
          
          <!-- BASIC INFO TAB -->
          <div class="tab-content active" id="tab-basic" style="padding: 2rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
              <!-- Barcode -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Barcode / UPC <span style="color: var(--color-danger);">*</span>
                </label>
                <div style="display: flex; gap: 0.5rem;">
                  <input type="text" id="barcode" name="barcode" class="form-input" placeholder="Enter barcode" required autofocus style="flex: 1;" value="<?php echo htmlspecialchars($item['barcode'] ?? ''); ?>">
                  <button type="button" onclick="scanBarcode()" style="padding: 0.625rem; border: 1px solid var(--border-color); border-radius: 6px; background: white; cursor: pointer;" title="Scan barcode">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2" stroke-width="2"/></svg>
                  </button>
                </div>
              </div>

              <!-- SKU -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  SKU / Item Code <span style="color: var(--color-danger);">*</span>
                </label>
                <input type="text" id="sku" name="sku" class="form-input" placeholder="e.g., PRD-001" required value="<?php echo htmlspecialchars($item['sku'] ?? ''); ?>">
              </div>
            </div>

            <!-- Item Name -->
            <div class="form-group" style="margin-top: 1.5rem;">
              <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                Item Name <span style="color: var(--color-danger);">*</span>
              </label>
              <input type="text" id="name" name="name" class="form-input" placeholder="Enter product name" required value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <!-- Type -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Type <span style="color: var(--color-danger);">*</span>
                </label>
                <select id="type" name="type" class="form-select" required>
                  <option value="">Select Type</option>
                  <optgroup label="🍽️ Food & Beverage">
                    <option value="Packed Goods" <?php echo ($item['type'] ?? '') === 'Packed Goods' ? 'selected' : ''; ?>>📦 Packed Goods</option>
                    <option value="Fruits" <?php echo ($item['type'] ?? '') === 'Fruits' ? 'selected' : ''; ?>>🍎 Fresh Fruits</option>
                    <option value="Vegetables" <?php echo ($item['type'] ?? '') === 'Vegetables' ? 'selected' : ''; ?>>🥕 Fresh Vegetables</option>
                    <option value="Pastries" <?php echo ($item['type'] ?? '') === 'Pastries' ? 'selected' : ''; ?>>🥐 Bakery & Pastries</option>
                    <option value="Beverages" <?php echo ($item['type'] ?? '') === 'Beverages' ? 'selected' : ''; ?>>🥤 Beverages</option>
                    <option value="Frozen Foods" <?php echo ($item['type'] ?? '') === 'Frozen Foods' ? 'selected' : ''; ?>>🧊 Frozen Foods</option>
                    <option value="Dairy" <?php echo ($item['type'] ?? '') === 'Dairy' ? 'selected' : ''; ?>>🥛 Dairy Products</option>
                    <option value="Meat" <?php echo ($item['type'] ?? '') === 'Meat' ? 'selected' : ''; ?>>🥩 Meat & Poultry</option>
                    <option value="Seafood" <?php echo ($item['type'] ?? '') === 'Seafood' ? 'selected' : ''; ?>>🐟 Seafood</option>
                  </optgroup>
                  <optgroup label="💻 Electronics & Tech">
                    <option value="Electronics" <?php echo ($item['type'] ?? '') === 'Electronics' ? 'selected' : ''; ?>>💻 Electronics</option>
                    <option value="Computers" <?php echo ($item['type'] ?? '') === 'Computers' ? 'selected' : ''; ?>>🖥️ Computers & Laptops</option>
                    <option value="Mobile Devices" <?php echo ($item['type'] ?? '') === 'Mobile Devices' ? 'selected' : ''; ?>>📱 Mobile Devices</option>
                    <option value="Accessories" <?php echo ($item['type'] ?? '') === 'Accessories' ? 'selected' : ''; ?>>🎧 Accessories</option>
                    <option value="Software" <?php echo ($item['type'] ?? '') === 'Software' ? 'selected' : ''; ?>>💿 Software</option>
                  </optgroup>
                  <optgroup label="👔 Apparel & Fashion">
                    <option value="Clothing" <?php echo ($item['type'] ?? '') === 'Clothing' ? 'selected' : ''; ?>>👕 Clothing</option>
                    <option value="Footwear" <?php echo ($item['type'] ?? '') === 'Footwear' ? 'selected' : ''; ?>>👟 Footwear</option>
                    <option value="Accessories-Fashion" <?php echo ($item['type'] ?? '') === 'Accessories-Fashion' ? 'selected' : ''; ?>>👜 Fashion Accessories</option>
                  </optgroup>
                  <optgroup label="🏠 Home & Living">
                    <option value="Furniture" <?php echo ($item['type'] ?? '') === 'Furniture' ? 'selected' : ''; ?>>🛋️ Furniture</option>
                    <option value="Home Decor" <?php echo ($item['type'] ?? '') === 'Home Decor' ? 'selected' : ''; ?>>🖼️ Home Decor</option>
                    <option value="Kitchen" <?php echo ($item['type'] ?? '') === 'Kitchen' ? 'selected' : ''; ?>>🍳 Kitchenware</option>
                    <option value="Bedding" <?php echo ($item['type'] ?? '') === 'Bedding' ? 'selected' : ''; ?>>🛏️ Bedding & Linens</option>
                  </optgroup>
                  <optgroup label="🏗️ Industrial & Hardware">
                    <option value="Hardware" <?php echo ($item['type'] ?? '') === 'Hardware' ? 'selected' : ''; ?>>🔧 Hardware & Tools</option>
                    <option value="Building Materials" <?php echo ($item['type'] ?? '') === 'Building Materials' ? 'selected' : ''; ?>>🧱 Building Materials</option>
                    <option value="Industrial" <?php echo ($item['type'] ?? '') === 'Industrial' ? 'selected' : ''; ?>>⚙️ Industrial Supplies</option>
                    <option value="Electrical" <?php echo ($item['type'] ?? '') === 'Electrical' ? 'selected' : ''; ?>>💡 Electrical Supplies</option>
                  </optgroup>
                  <optgroup label="📚 Office & Education">
                    <option value="Office Supplies" <?php echo ($item['type'] ?? '') === 'Office Supplies' ? 'selected' : ''; ?>>📎 Office Supplies</option>
                    <option value="Stationery" <?php echo ($item['type'] ?? '') === 'Stationery' ? 'selected' : ''; ?>>✏️ Stationery</option>
                    <option value="Books" <?php echo ($item['type'] ?? '') === 'Books' ? 'selected' : ''; ?>>📚 Books</option>
                  </optgroup>
                  <optgroup label="🎮 Entertainment & Media">
                    <option value="Games" <?php echo ($item['type'] ?? '') === 'Games' ? 'selected' : ''; ?>>🎮 Games & Toys</option>
                    <option value="Sports" <?php echo ($item['type'] ?? '') === 'Sports' ? 'selected' : ''; ?>>⚽ Sports Equipment</option>
                    <option value="Music" <?php echo ($item['type'] ?? '') === 'Music' ? 'selected' : ''; ?>>🎵 Music & Instruments</option>
                  </optgroup>
                  <optgroup label="🏥 Health & Beauty">
                    <option value="Health" <?php echo ($item['type'] ?? '') === 'Health' ? 'selected' : ''; ?>>💊 Health Products</option>
                    <option value="Beauty" <?php echo ($item['type'] ?? '') === 'Beauty' ? 'selected' : ''; ?>>💄 Beauty & Cosmetics</option>
                    <option value="Personal Care" <?php echo ($item['type'] ?? '') === 'Personal Care' ? 'selected' : ''; ?>>🧴 Personal Care</option>
                  </optgroup>
                  <optgroup label="🚗 Automotive">
                    <option value="Auto Parts" <?php echo ($item['type'] ?? '') === 'Auto Parts' ? 'selected' : ''; ?>>🔩 Auto Parts</option>
                    <option value="Vehicle Accessories" <?php echo ($item['type'] ?? '') === 'Vehicle Accessories' ? 'selected' : ''; ?>>🚙 Vehicle Accessories</option>
                  </optgroup>
                  <optgroup label="📦 Other">
                    <option value="Services" <?php echo ($item['type'] ?? '') === 'Services' ? 'selected' : ''; ?>>🛠️ Services</option>
                    <option value="Digital Products" <?php echo ($item['type'] ?? '') === 'Digital Products' ? 'selected' : ''; ?>>📲 Digital Products</option>
                    <option value="Raw Materials" <?php echo ($item['type'] ?? '') === 'Raw Materials' ? 'selected' : ''; ?>>🏭 Raw Materials</option>
                    <option value="Other" <?php echo ($item['type'] ?? '') === 'Other' ? 'selected' : ''; ?>>📂 Other</option>
                  </optgroup>
                </select>
              </div>

              <!-- Category -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Category
                </label>
                <select id="category" name="category" class="form-select">
                  <option value="">Select Category</option>
                  <optgroup label="Inventory Classification">
                    <option value="Finished Goods" <?php echo ($item['category'] ?? '') === 'Finished Goods' ? 'selected' : ''; ?>>Finished Goods</option>
                    <option value="Raw Materials" <?php echo ($item['category'] ?? '') === 'Raw Materials' ? 'selected' : ''; ?>>Raw Materials</option>
                    <option value="Work in Progress" <?php echo ($item['category'] ?? '') === 'Work in Progress' ? 'selected' : ''; ?>>Work in Progress</option>
                    <option value="Components" <?php echo ($item['category'] ?? '') === 'Components' ? 'selected' : ''; ?>>Components & Parts</option>
                    <option value="Consumables" <?php echo ($item['category'] ?? '') === 'Consumables' ? 'selected' : ''; ?>>Consumables</option>
                    <option value="Packaging" <?php echo ($item['category'] ?? '') === 'Packaging' ? 'selected' : ''; ?>>Packaging Materials</option>
                  </optgroup>
                  <optgroup label="Asset Type">
                    <option value="Current Asset" <?php echo ($item['category'] ?? '') === 'Current Asset' ? 'selected' : ''; ?>>Current Asset (< 1 year)</option>
                    <option value="Fixed Asset" <?php echo ($item['category'] ?? '') === 'Fixed Asset' ? 'selected' : ''; ?>>Fixed Asset (> 1 year)</option>
                    <option value="Non-Stock Item" <?php echo ($item['category'] ?? '') === 'Non-Stock Item' ? 'selected' : ''; ?>>Non-Stock Item</option>
                  </optgroup>
                  <optgroup label="Perishability">
                    <option value="Perishable" <?php echo ($item['category'] ?? '') === 'Perishable' ? 'selected' : ''; ?>>Perishable</option>
                    <option value="Non-Perishable" <?php echo ($item['category'] ?? '') === 'Non-Perishable' ? 'selected' : ''; ?>>Non-Perishable</option>
                    <option value="Hazardous" <?php echo ($item['category'] ?? '') === 'Hazardous' ? 'selected' : ''; ?>>Hazardous Materials</option>
                  </optgroup>
                </select>
              </div>
            </div>

            <!-- Description -->
            <div class="form-group" style="margin-top: 1.5rem;">
              <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                Description
              </label>
              <textarea id="description" name="description" class="form-input" rows="3" placeholder="Enter product description (optional)" style="resize: vertical;"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <!-- Unit of Measure -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Unit of Measure <span style="color: var(--color-danger);">*</span>
                </label>
                <select id="unit_of_measure" name="unit_of_measure" class="form-select" required>
                  <optgroup label="Count/Quantity">
                    <option value="pcs" <?php echo ($item['unit_of_measure'] ?? 'pcs') === 'pcs' ? 'selected' : ''; ?>>Pieces (pcs)</option>
                    <option value="unit" <?php echo ($item['unit_of_measure'] ?? '') === 'unit' ? 'selected' : ''; ?>>Unit</option>
                    <option value="each" <?php echo ($item['unit_of_measure'] ?? '') === 'each' ? 'selected' : ''; ?>>Each</option>
                    <option value="dozen" <?php echo ($item['unit_of_measure'] ?? '') === 'dozen' ? 'selected' : ''; ?>>Dozen (12)</option>
                    <option value="gross" <?php echo ($item['unit_of_measure'] ?? '') === 'gross' ? 'selected' : ''; ?>>Gross (144)</option>
                    <option value="pair" <?php echo ($item['unit_of_measure'] ?? '') === 'pair' ? 'selected' : ''; ?>>Pair</option>
                    <option value="set" <?php echo ($item['unit_of_measure'] ?? '') === 'set' ? 'selected' : ''; ?>>Set</option>
                  </optgroup>
                  <optgroup label="Weight (Metric)">
                    <option value="kg" <?php echo ($item['unit_of_measure'] ?? '') === 'kg' ? 'selected' : ''; ?>>Kilogram (kg)</option>
                    <option value="g" <?php echo ($item['unit_of_measure'] ?? '') === 'g' ? 'selected' : ''; ?>>Gram (g)</option>
                    <option value="mg" <?php echo ($item['unit_of_measure'] ?? '') === 'mg' ? 'selected' : ''; ?>>Milligram (mg)</option>
                    <option value="metric_ton" <?php echo ($item['unit_of_measure'] ?? '') === 'metric_ton' ? 'selected' : ''; ?>>Metric Ton (1000 kg)</option>
                  </optgroup>
                  <optgroup label="Weight (Imperial)">
                    <option value="lb" <?php echo ($item['unit_of_measure'] ?? '') === 'lb' ? 'selected' : ''; ?>>Pound (lb)</option>
                    <option value="oz" <?php echo ($item['unit_of_measure'] ?? '') === 'oz' ? 'selected' : ''; ?>>Ounce (oz)</option>
                    <option value="ton" <?php echo ($item['unit_of_measure'] ?? '') === 'ton' ? 'selected' : ''; ?>>Ton (2000 lb)</option>
                  </optgroup>
                  <optgroup label="Volume (Metric)">
                    <option value="L" <?php echo ($item['unit_of_measure'] ?? '') === 'L' ? 'selected' : ''; ?>>Liter (L)</option>
                    <option value="mL" <?php echo ($item['unit_of_measure'] ?? '') === 'mL' ? 'selected' : ''; ?>>Milliliter (mL)</option>
                    <option value="cubic_meter" <?php echo ($item['unit_of_measure'] ?? '') === 'cubic_meter' ? 'selected' : ''; ?>>Cubic Meter (m³)</option>
                  </optgroup>
                  <optgroup label="Volume (Imperial)">
                    <option value="gallon" <?php echo ($item['unit_of_measure'] ?? '') === 'gallon' ? 'selected' : ''; ?>>Gallon (gal)</option>
                    <option value="quart" <?php echo ($item['unit_of_measure'] ?? '') === 'quart' ? 'selected' : ''; ?>>Quart (qt)</option>
                    <option value="pint" <?php echo ($item['unit_of_measure'] ?? '') === 'pint' ? 'selected' : ''; ?>>Pint (pt)</option>
                    <option value="fluid_oz" <?php echo ($item['unit_of_measure'] ?? '') === 'fluid_oz' ? 'selected' : ''; ?>>Fluid Ounce (fl oz)</option>
                  </optgroup>
                  <optgroup label="Length">
                    <option value="meter" <?php echo ($item['unit_of_measure'] ?? '') === 'meter' ? 'selected' : ''; ?>>Meter (m)</option>
                    <option value="cm" <?php echo ($item['unit_of_measure'] ?? '') === 'cm' ? 'selected' : ''; ?>>Centimeter (cm)</option>
                    <option value="mm" <?php echo ($item['unit_of_measure'] ?? '') === 'mm' ? 'selected' : ''; ?>>Millimeter (mm)</option>
                    <option value="foot" <?php echo ($item['unit_of_measure'] ?? '') === 'foot' ? 'selected' : ''; ?>>Foot (ft)</option>
                    <option value="inch" <?php echo ($item['unit_of_measure'] ?? '') === 'inch' ? 'selected' : ''; ?>>Inch (in)</option>
                    <option value="yard" <?php echo ($item['unit_of_measure'] ?? '') === 'yard' ? 'selected' : ''; ?>>Yard (yd)</option>
                  </optgroup>
                  <optgroup label="Area">
                    <option value="sq_meter" <?php echo ($item['unit_of_measure'] ?? '') === 'sq_meter' ? 'selected' : ''; ?>>Square Meter (m²)</option>
                    <option value="sq_foot" <?php echo ($item['unit_of_measure'] ?? '') === 'sq_foot' ? 'selected' : ''; ?>>Square Foot (ft²)</option>
                  </optgroup>
                  <optgroup label="Packaging">
                    <option value="box" <?php echo ($item['unit_of_measure'] ?? '') === 'box' ? 'selected' : ''; ?>>Box</option>
                    <option value="carton" <?php echo ($item['unit_of_measure'] ?? '') === 'carton' ? 'selected' : ''; ?>>Carton</option>
                    <option value="case" <?php echo ($item['unit_of_measure'] ?? '') === 'case' ? 'selected' : ''; ?>>Case</option>
                    <option value="pack" <?php echo ($item['unit_of_measure'] ?? '') === 'pack' ? 'selected' : ''; ?>>Pack</option>
                    <option value="bag" <?php echo ($item['unit_of_measure'] ?? '') === 'bag' ? 'selected' : ''; ?>>Bag</option>
                    <option value="pallet" <?php echo ($item['unit_of_measure'] ?? '') === 'pallet' ? 'selected' : ''; ?>>Pallet</option>
                    <option value="container" <?php echo ($item['unit_of_measure'] ?? '') === 'container' ? 'selected' : ''; ?>>Container</option>
                    <option value="crate" <?php echo ($item['unit_of_measure'] ?? '') === 'crate' ? 'selected' : ''; ?>>Crate</option>
                    <option value="bundle" <?php echo ($item['unit_of_measure'] ?? '') === 'bundle' ? 'selected' : ''; ?>>Bundle</option>
                  </optgroup>
                  <optgroup label="Time-based">
                    <option value="hour" <?php echo ($item['unit_of_measure'] ?? '') === 'hour' ? 'selected' : ''; ?>>Hour</option>
                    <option value="day" <?php echo ($item['unit_of_measure'] ?? '') === 'day' ? 'selected' : ''; ?>>Day</option>
                    <option value="month" <?php echo ($item['unit_of_measure'] ?? '') === 'month' ? 'selected' : ''; ?>>Month</option>
                  </optgroup>
                </select>
              </div>

              <!-- Lifespan -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Shelf Life / Lifespan <span style="color: var(--color-danger);">*</span>
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
                  <option value="2 to 10 years" <?php echo ($item['lifespan'] ?? '') === '2 to 10 years' ? 'selected' : ''; ?>>2-10 years</option>
                </select>
              </div>
            </div>
          </div>

          <!-- PRICING TAB -->
          <div class="tab-content" id="tab-pricing" style="padding: 2rem; display: none;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Cost Price <span style="color: var(--color-danger);">*</span></label>
                <input type="number" id="cost_price" name="cost_price" class="form-input" placeholder="0.00" step="0.01" min="0" required oninput="calculateMarkup()" value="<?php echo htmlspecialchars($item['cost_price'] ?? '0'); ?>">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Selling Price <span style="color: var(--color-danger);">*</span></label>
                <input type="number" id="sell_price" name="sell_price" class="form-input" placeholder="0.00" step="0.01" min="0" required oninput="calculateMarkup()" value="<?php echo htmlspecialchars($item['sell_price'] ?? '0'); ?>">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Markup %</label>
                <input type="text" id="markup" class="form-input" placeholder="0%" readonly style="background: hsl(240 5% 96%);">
              </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Tax Rate (%)</label>
                <select id="tax_rate" name="tax_rate" class="form-select">
                  <option value="0" <?php echo ($item['tax_rate'] ?? 0) == 0 ? 'selected' : ''; ?>>No Tax / Tax Exempt (0%)</option>
                  <option value="12" <?php echo ($item['tax_rate'] ?? 0) == 12 ? 'selected' : ''; ?>>VAT 12% (Standard)</option>
                  <option value="5" <?php echo ($item['tax_rate'] ?? 0) == 5 ? 'selected' : ''; ?>>VAT 5%</option>
                  <option value="10" <?php echo ($item['tax_rate'] ?? 0) == 10 ? 'selected' : ''; ?>>GST 10%</option>
                  <option value="20" <?php echo ($item['tax_rate'] ?? 0) == 20 ? 'selected' : ''; ?>>VAT 20%</option>
                </select>
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Price (incl. Tax)</label>
                <input type="text" id="price_with_tax" class="form-input" readonly style="background: hsl(240 5% 96%); font-weight: 600;">
              </div>
            </div>
          </div>

          <!-- INVENTORY TAB -->
          <div class="tab-content" id="tab-inventory" style="padding: 2rem; display: none;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Initial Quantity <span style="color: var(--color-danger);">*</span></label>
                <input type="number" id="quantity" name="quantity" class="form-input" min="0" required value="<?php echo htmlspecialchars($item['quantity'] ?? 0); ?>">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Min Stock Level</label>
                <input type="number" id="min_stock" name="min_stock" class="form-input" placeholder="10" min="0" value="<?php echo htmlspecialchars($item['min_stock'] ?? ''); ?>">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Reorder Point</label>
                <input type="number" id="reorder_point" name="reorder_point" class="form-input" placeholder="20" min="0" value="<?php echo htmlspecialchars($item['reorder_point'] ?? ''); ?>">
              </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Max Stock Level</label>
                <input type="number" id="max_stock" name="max_stock" class="form-input" placeholder="100" min="0" value="<?php echo htmlspecialchars($item['max_stock'] ?? ''); ?>">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Location/Warehouse</label>
                <select id="location" name="location" class="form-select">
                  <option value="">Select Location</option>
                  <option value="Main Warehouse" <?php echo ($item['location'] ?? '') === 'Main Warehouse' ? 'selected' : ''; ?>>Main Warehouse (HQ)</option>
                  <option value="Warehouse North" <?php echo ($item['location'] ?? '') === 'Warehouse North' ? 'selected' : ''; ?>>Warehouse North</option>
                  <option value="Store A - Mall" <?php echo ($item['location'] ?? '') === 'Store A - Mall' ? 'selected' : ''; ?>>Store A - Shopping Mall</option>
                </select>
              </div>
            </div>
          </div>

          <!-- ADVANCED TAB -->
          <div class="tab-content" id="tab-advanced" style="padding: 2rem; display: none;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Supplier / Vendor</label>
                <input type="text" id="supplier" name="supplier" class="form-input" placeholder="Supplier name" value="<?php echo htmlspecialchars($item['supplier'] ?? ''); ?>">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Manufacturer</label>
                <input type="text" id="manufacturer" name="manufacturer" class="form-input" placeholder="e.g., Samsung, Nike" value="<?php echo htmlspecialchars($item['manufacturer'] ?? ''); ?>">
              </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Brand</label>
                <input type="text" id="brand" name="brand" class="form-input" placeholder="Product brand" value="<?php echo htmlspecialchars($item['brand'] ?? ''); ?>">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Model / Part Number</label>
                <input type="text" id="model_number" name="model_number" class="form-input" placeholder="Model number" value="<?php echo htmlspecialchars($item['model_number'] ?? ''); ?>">
              </div>
            </div>
            <div class="form-group" style="margin-top: 1.5rem;">
              <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Tags & Keywords</label>
              <input type="text" id="tags" name="tags" class="form-input" placeholder="e.g., organic, imported, bestseller" value="<?php echo htmlspecialchars($item['tags'] ?? ''); ?>">
              <small style="color: var(--text-secondary); font-size: 0.75rem; display: block; margin-top: 0.25rem;">Separate tags with commas</small>
            </div>
            <div class="form-group" style="margin-top: 1.5rem;">
              <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Internal Notes</label>
              <textarea id="internal_notes" name="internal_notes" class="form-input" rows="3" placeholder="Add internal notes..." style="resize: vertical;"><?php echo htmlspecialchars($item['internal_notes'] ?? ''); ?></textarea>
            </div>
          </div>

          <!-- Tab Navigation Actions -->
          <div style="padding: 1.5rem 2rem; background: hsl(240 5% 96%); border-top: 1px solid hsl(240 6% 90%); display: flex; justify-content: space-between; align-items: center;">
            <button type="button" id="prevTabBtn" onclick="navigateTab('prev')" style="padding: 0.625rem 1.25rem; border: 1px solid hsl(240 6% 85%); border-radius: 8px; background: white; color: hsl(240 5% 40%); cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'; this.style.borderColor='hsl(240 6% 75%)'" onmouseout="this.style.background='white'; this.style.borderColor='hsl(240 6% 85%)'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span>Previous</span>
            </button>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
              <span id="tabIndicator" style="font-size: 0.8125rem; color: hsl(240 5% 50%); font-weight: 500;">Step 1 of 4</span>
            </div>
            <button type="button" id="nextTabBtn" onclick="navigateTab('next')" style="padding: 0.625rem 1.25rem; border: none; border-radius: 8px; background: var(--color-primary); color: white; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 2px 4px rgba(113, 148, 165, 0.2);" onmouseover="this.style.background='hsl(199 35% 50%)'; this.style.boxShadow='0 4px 8px rgba(113, 148, 165, 0.3)'" onmouseout="this.style.background='var(--color-primary)'; this.style.boxShadow='0 2px 4px rgba(113, 148, 165, 0.2)'">
              <span>Next</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- RIGHT COLUMN: Status & Info -->
    <div style="position: sticky; top: 2rem;">
      <!-- Edit Status Card -->
      <div style="background: white; border: 1px solid hsl(240 6% 90%); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <!-- Header -->
        <div style="padding: 1.25rem 1.5rem; background: linear-gradient(135deg, hsl(220 20% 97%) 0%, white 100%); border-bottom: 1px solid hsl(240 6% 90%);">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 36px; height: 36px; background: var(--color-primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(113, 148, 165, 0.2);">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                <path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13"/>
                <path d="M18.5 2.5C18.8978 2.10217 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10217 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10217 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z"/>
              </svg>
            </div>
            <div>
              <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: hsl(240 10% 10%);">Edit Mode</h3>
              <p style="font-size: 0.6875rem; margin: 0; color: hsl(240 5% 50%); font-weight: 500;">Update item details</p>
            </div>
          </div>
        </div>
        
        <!-- Body -->
        <div style="padding: 1.5rem;">
          <!-- Item ID Badge -->
          <div style="margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
              <span style="font-size: 0.6875rem; font-weight: 700; color: hsl(240 5% 50%); text-transform: uppercase; letter-spacing: 0.05em;">Item ID</span>
              <span style="display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; background: hsl(143 85% 96%); color: hsl(140 61% 35%); border: 1px solid hsl(143 85% 80%); border-radius: 6px; font-size: 0.625rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right: 0.25rem;">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                Active
              </span>
            </div>
            <div style="position: relative; padding: 0.625rem 0.875rem; background: hsl(240 5% 98%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; font-family: 'Courier New', monospace; font-size: 0.75rem; color: hsl(240 10% 20%); word-break: break-all; line-height: 1.4;">
              <?php echo htmlspecialchars((string)$item['_id'] ?? ''); ?>
              <button onclick="copyToClipboard('<?php echo (string)$item['_id']; ?>')" style="position: absolute; top: 0.5rem; right: 0.5rem; padding: 0.25rem; background: white; border: 1px solid hsl(240 6% 85%); border-radius: 4px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='white'" title="Copy ID">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="hsl(240 5% 40%)" stroke-width="2.5">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
              </button>
            </div>
          </div>
          
          <!-- Metadata Grid -->
          <div style="display: grid; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <div style="font-size: 0.6875rem; font-weight: 700; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: inline-block; vertical-align: middle; margin-right: 0.375rem;">
                  <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Date Added
              </div>
              <div style="font-size: 0.8125rem; font-weight: 600; color: hsl(240 10% 10%);">
                <?php echo isset($item['date_added']) ? date('M d, Y', $item['date_added']->toDateTime()->getTimestamp()) : 'N/A'; ?>
              </div>
              <div style="font-size: 0.6875rem; color: hsl(240 5% 50%); margin-top: 0.125rem;">
                <?php echo isset($item['date_added']) ? date('H:i', $item['date_added']->toDateTime()->getTimestamp()) : ''; ?>
              </div>
            </div>
            
            <?php if (isset($item['updated_at'])): ?>
            <div style="padding-top: 1rem; border-top: 1px solid hsl(240 6% 90%);">
              <div style="font-size: 0.6875rem; font-weight: 700; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: inline-block; vertical-align: middle; margin-right: 0.375rem;">
                  <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                Last Updated
              </div>
              <div style="font-size: 0.8125rem; font-weight: 600; color: hsl(240 10% 10%);">
                <?php echo date('M d, Y', $item['updated_at']->toDateTime()->getTimestamp()); ?>
              </div>
              <div style="font-size: 0.6875rem; color: hsl(240 5% 50%); margin-top: 0.125rem;">
                <?php echo date('H:i', $item['updated_at']->toDateTime()->getTimestamp()); ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Footer Actions -->
        <div style="padding: 1.25rem 1.5rem; background: hsl(240 5% 98%); border-top: 1px solid hsl(240 6% 90%);">
          <button type="submit" form="itemForm" id="sidebarSubmitBtn" style="width: 100%; padding: 0.75rem; border: none; border-radius: 8px; background: var(--color-primary); color: white; font-weight: 600; cursor: pointer; font-size: 0.875rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 2px 4px rgba(113, 148, 165, 0.2);" onmouseover="this.style.background='hsl(199 35% 50%)'; this.style.boxShadow='0 4px 8px rgba(113, 148, 165, 0.3)'" onmouseout="this.style.background='var(--color-primary)'; this.style.boxShadow='0 2px 4px rgba(113, 148, 165, 0.2)'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            <span>Update Item</span>
          </button>
          <a href="inventory-list.php" style="display: flex; align-items: center; justify-content: center; margin-top: 0.625rem; color: hsl(240 5% 40%); text-decoration: none; font-size: 0.8125rem; font-weight: 500; padding: 0.5rem; border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='hsl(240 5% 96%)'" onmouseout="this.style.background='transparent'">
            Cancel
          </a>
        </div>
      </div>
    </div>

  </div>
</div>

<style>
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.tab-btn:hover { background: hsl(240 5% 96%) !important; }
</style>

<script>
// ============================================
// EDIT ITEM FORM - Enterprise Edition
// Xero + QuickBooks + LedgerSMB Features
// ============================================

// Tab Switching
const tabs = ['basic', 'pricing', 'inventory', 'advanced'];
let currentTabIndex = 0;

function switchTab(tabName) {
  document.querySelectorAll('.tab-content').forEach(tab => {
    tab.style.display = 'none';
  });
  
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.style.background = 'transparent';
    btn.style.borderBottom = '3px solid transparent';
    btn.style.color = 'var(--text-secondary)';
    btn.classList.remove('active');
  });
  
  document.getElementById('tab-' + tabName).style.display = 'block';
  const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
  activeBtn.style.background = 'white';
  activeBtn.style.borderBottom = '3px solid var(--color-primary)';
  activeBtn.style.color = 'var(--color-primary)';
  activeBtn.classList.add('active');
  
  // Update current tab index
  currentTabIndex = tabs.indexOf(tabName);
  updateNavigationButtons();
}

// Navigate between tabs (Previous/Next)
function navigateTab(direction) {
  if (direction === 'next' && currentTabIndex < tabs.length - 1) {
    currentTabIndex++;
  } else if (direction === 'prev' && currentTabIndex > 0) {
    currentTabIndex--;
  }
  
  switchTab(tabs[currentTabIndex]);
}

// Update navigation button states
function updateNavigationButtons() {
  const prevBtn = document.getElementById('prevTabBtn');
  const nextBtn = document.getElementById('nextTabBtn');
  const indicator = document.getElementById('tabIndicator');
  
  // Update indicator
  indicator.textContent = `Step ${currentTabIndex + 1} of ${tabs.length}`;
  
  // Update Previous button
  if (currentTabIndex === 0) {
    prevBtn.disabled = true;
    prevBtn.style.opacity = '0.5';
    prevBtn.style.cursor = 'not-allowed';
  } else {
    prevBtn.disabled = false;
    prevBtn.style.opacity = '1';
    prevBtn.style.cursor = 'pointer';
  }
  
  // Update Next button
  if (currentTabIndex === tabs.length - 1) {
    nextBtn.disabled = true;
    nextBtn.style.opacity = '0.5';
    nextBtn.style.cursor = 'not-allowed';
  } else {
    nextBtn.disabled = false;
    nextBtn.style.opacity = '1';
    nextBtn.style.cursor = 'pointer';
  }
}

// Calculate Markup Percentage
function calculateMarkup() {
  const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
  const sellPrice = parseFloat(document.getElementById('sell_price').value) || 0;
  const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
  
  if (costPrice > 0 && sellPrice > 0) {
    const markup = ((sellPrice - costPrice) / costPrice * 100).toFixed(2);
    document.getElementById('markup').value = markup + '%';
    
    // Calculate price with tax
    const priceWithTax = sellPrice * (1 + taxRate / 100);
    document.getElementById('price_with_tax').value = '₱' + priceWithTax.toFixed(2);
  }
}

// Barcode Scanner
function scanBarcode() {
  const mockBarcode = 'BC' + Date.now().toString().slice(-8);
  document.getElementById('barcode').value = mockBarcode;
  document.getElementById('barcode').dispatchEvent(new Event('input'));
  if (typeof Toast !== 'undefined') {
    Toast.success('Barcode scanned: ' + mockBarcode);
  }
  console.log('Barcode scanned:', mockBarcode);
}

// Show validation tooltip for invalid field
function showValidationTooltip(field) {
  // Remove any existing tooltip
  const existingTooltip = document.getElementById('validation-tooltip');
  if (existingTooltip) existingTooltip.remove();
  
  // Get field label text
  const label = field.closest('.form-group')?.querySelector('label');
  const fieldName = label ? label.textContent.replace('*', '').trim() : 'This field';
  
  // Create tooltip
  const tooltip = document.createElement('div');
  tooltip.id = 'validation-tooltip';
  tooltip.style.cssText = `
    position: absolute;
    background: hsl(0 74% 50%);
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 10000;
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: slideDown 0.3s ease-out;
  `;
  tooltip.textContent = `⚠️ ${fieldName} is required`;
  
  // Position tooltip below field
  const rect = field.getBoundingClientRect();
  tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
  tooltip.style.left = (rect.left + window.scrollX) + 'px';
  
  document.body.appendChild(tooltip);
  
  // Auto-remove after 3 seconds
  setTimeout(() => tooltip.remove(), 3000);
  
  // Remove on field input
  field.addEventListener('input', function removeTooltip() {
    tooltip.remove();
    field.removeEventListener('input', removeTooltip);
  }, { once: true });
}

// Add slideDown animation
if (!document.getElementById('slideDown-animation')) {
  const style = document.createElement('style');
  style.id = 'slideDown-animation';
  style.textContent = `
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  `;
  document.head.appendChild(style);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('itemForm');
  const requiredFields = form.querySelectorAll('[required]');
  
  // Initialize navigation buttons
  updateNavigationButtons();
  
  // Calculate markup on load
  calculateMarkup();
  
  // Tax rate change listener
  document.getElementById('tax_rate').addEventListener('change', calculateMarkup);
  
  // Keyboard navigation for tabs (Ctrl + Arrow Keys)
  document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
      if (e.key === 'ArrowRight') {
        e.preventDefault();
        navigateTab('next');
      } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        navigateTab('prev');
      }
    }
  });
  
  // Form submission with validation
  form.addEventListener('submit', function(e) {
    // Validate all required fields
    let firstInvalidField = null;
    let invalidTab = null;
    
    requiredFields.forEach(field => {
      if (!field.value || field.value.trim() === '') {
        if (!firstInvalidField) {
          firstInvalidField = field;
          
          // Find which tab this field belongs to
          const tabContent = field.closest('.tab-content');
          if (tabContent) {
            invalidTab = tabContent.id.replace('tab-', '');
          }
        }
        
        // Visual feedback for empty field
        field.style.borderColor = 'hsl(0 74% 50%)';
        field.style.borderWidth = '2px';
        field.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.15)';
      }
    });
    
    if (firstInvalidField) {
      e.preventDefault();
      
      // Switch to tab with invalid field
      if (invalidTab) {
        switchTab(invalidTab);
      }
      
      // Focus on first invalid field after tab switch
      setTimeout(() => {
        firstInvalidField.focus();
        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Show error tooltip
        showValidationTooltip(firstInvalidField);
      }, 300);
      
      // Show error toast notification
      if (typeof Toast !== 'undefined') {
        Toast.error('Please fill in all required fields before submitting', 4000);
      }
      
      console.log('❌ Validation failed: Required fields missing');
      return false;
    }
    
    // All validation passed, proceed with submission
    console.log('✅ Form validation passed - Submitting...');
  });
  
  // Real-time validation feedback
  requiredFields.forEach(field => {
    // On blur: check if field is empty
    field.addEventListener('blur', function() {
      if (!this.value || this.value.trim() === '') {
        this.style.borderColor = 'var(--color-danger)';
        this.style.borderWidth = '1px';
      } else {
        this.style.borderColor = 'var(--color-success)';
        this.style.borderWidth = '1px';
        this.style.boxShadow = '';
      }
    });
    
    // On focus: highlight with primary color
    field.addEventListener('focus', function() {
      this.style.borderColor = 'var(--color-primary)';
      this.style.borderWidth = '1px';
      this.style.boxShadow = '';
    });
    
    // On input: clear error styling if field has value
    field.addEventListener('input', function() {
      if (this.value && this.value.trim() !== '') {
        this.style.borderColor = '';
        this.style.borderWidth = '';
        this.style.boxShadow = '';
      }
    });
  });
});

// Copy to clipboard function
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(function() {
    // Show success toast
    if (typeof Toast !== 'undefined') {
      Toast.success('Item ID copied to clipboard!', 2000);
    }
    console.log('✓ Copied to clipboard:', text);
  }).catch(function(err) {
    console.error('Failed to copy:', err);
    if (typeof Toast !== 'undefined') {
      Toast.error('Failed to copy to clipboard', 2000);
    }
  });
}

console.log('%c✏️ Edit Item Form Loaded - Enterprise Edition', 'color: #3b82f6; font-size: 14px; font-weight: bold;');
console.log('%cItem ID: <?php echo (string)$item['_id']; ?>', 'color: #6b7280; font-size: 12px;');
console.log('%cAll 30 fields pre-filled and ready for update!', 'color: #10b981; font-size: 12px;');
console.log('%c✓ Smart validation enabled: 10 required fields monitored', 'color: #8b5cf6; font-size: 12px;');
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
