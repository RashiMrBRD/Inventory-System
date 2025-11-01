<?php
/**
 * Add Item Page - Professional Bio-Page Inspired Design
 * Comprehensive inventory item creation with Xero/QuickBooks/LedgerSMB features
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Controller\InventoryController;
use App\Helper\SessionHelper;

$authController = new AuthController();
$authController->requireLogin();

$inventoryController = new InventoryController();
$error = '';
$success = '';

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
    
    $result = $inventoryController->createItem($itemData);
    
    if ($result['success']) {
        SessionHelper::setFlash('Item added successfully!', 'success');
        header("Location: inventory-list.php");
        exit();
    } else {
        $error = $result['message'];
    }
}
$pageTitle = 'Add Inventory Item';
ob_start();
?>

<!-- Bio-Page Inspired Header -->
<div style="background: #7194A5; color: white; padding: 2rem 0; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
  <div class="container">
    <div style="display: flex; align-items: center; gap: 1.5rem;">
      <div style="width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 32px; backdrop-filter: blur(10px);">
        📦
      </div>
      <div style="flex: 1;">
        <h1 style="font-size: 1.875rem; font-weight: 700; margin: 0 0 0.25rem 0; color: white;">Add New Inventory Item</h1>
        <p style="font-size: 0.875rem; margin: 0; opacity: 0.9;">Create a comprehensive product entry with advanced features</p>
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
          <!-- BASIC INFO TAB -->
          <div class="tab-content active" id="tab-basic" style="padding: 2rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
              <!-- Barcode -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Barcode / UPC <span style="color: var(--color-danger);">*</span>
                </label>
                <div style="display: flex; gap: 0.5rem;">
                  <input type="text" id="barcode" name="barcode" class="form-input" placeholder="Enter barcode" required autofocus style="flex: 1;">
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
                <input type="text" id="sku" name="sku" class="form-input" placeholder="e.g., PRD-001" required>
              </div>
            </div>

            <!-- Item Name -->
            <div class="form-group" style="margin-top: 1.5rem;">
              <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                Item Name <span style="color: var(--color-danger);">*</span>
              </label>
              <input type="text" id="name" name="name" class="form-input" placeholder="Enter product name" required>
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
                    <option value="Packed Goods">📦 Packed Goods</option>
                    <option value="Fruits">🍎 Fresh Fruits</option>
                    <option value="Vegetables">🥕 Fresh Vegetables</option>
                    <option value="Pastries">🥐 Bakery & Pastries</option>
                    <option value="Beverages">🥤 Beverages</option>
                    <option value="Frozen Foods">🧊 Frozen Foods</option>
                    <option value="Dairy">🥛 Dairy Products</option>
                    <option value="Meat">🥩 Meat & Poultry</option>
                    <option value="Seafood">🐟 Seafood</option>
                  </optgroup>
                  <optgroup label="💻 Electronics & Tech">
                    <option value="Electronics">💻 Electronics</option>
                    <option value="Computers">🖥️ Computers & Laptops</option>
                    <option value="Mobile Devices">📱 Mobile Devices</option>
                    <option value="Accessories">🎧 Accessories</option>
                    <option value="Software">💿 Software</option>
                  </optgroup>
                  <optgroup label="👔 Apparel & Fashion">
                    <option value="Clothing">👕 Clothing</option>
                    <option value="Footwear">👟 Footwear</option>
                    <option value="Accessories-Fashion">👜 Fashion Accessories</option>
                  </optgroup>
                  <optgroup label="🏠 Home & Living">
                    <option value="Furniture">🛋️ Furniture</option>
                    <option value="Home Decor">🖼️ Home Decor</option>
                    <option value="Kitchen">🍳 Kitchenware</option>
                    <option value="Bedding">🛏️ Bedding & Linens</option>
                  </optgroup>
                  <optgroup label="🏗️ Industrial & Hardware">
                    <option value="Hardware">🔧 Hardware & Tools</option>
                    <option value="Building Materials">🧱 Building Materials</option>
                    <option value="Industrial">⚙️ Industrial Supplies</option>
                    <option value="Electrical">💡 Electrical Supplies</option>
                  </optgroup>
                  <optgroup label="📚 Office & Education">
                    <option value="Office Supplies">📎 Office Supplies</option>
                    <option value="Stationery">✏️ Stationery</option>
                    <option value="Books">📚 Books</option>
                  </optgroup>
                  <optgroup label="🎮 Entertainment & Media">
                    <option value="Games">🎮 Games & Toys</option>
                    <option value="Sports">⚽ Sports Equipment</option>
                    <option value="Music">🎵 Music & Instruments</option>
                  </optgroup>
                  <optgroup label="🏥 Health & Beauty">
                    <option value="Health">💊 Health Products</option>
                    <option value="Beauty">💄 Beauty & Cosmetics</option>
                    <option value="Personal Care">🧴 Personal Care</option>
                  </optgroup>
                  <optgroup label="🚗 Automotive">
                    <option value="Auto Parts">🔩 Auto Parts</option>
                    <option value="Vehicle Accessories">🚙 Vehicle Accessories</option>
                  </optgroup>
                  <optgroup label="📦 Other">
                    <option value="Services">🛠️ Services</option>
                    <option value="Digital Products">📲 Digital Products</option>
                    <option value="Raw Materials">🏭 Raw Materials</option>
                    <option value="Other">📂 Other</option>
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
                    <option value="Finished Goods">Finished Goods</option>
                    <option value="Raw Materials">Raw Materials</option>
                    <option value="Work in Progress">Work in Progress</option>
                    <option value="Components">Components & Parts</option>
                    <option value="Consumables">Consumables</option>
                    <option value="Packaging">Packaging Materials</option>
                  </optgroup>
                  <optgroup label="Asset Type">
                    <option value="Current Asset">Current Asset (< 1 year)</option>
                    <option value="Fixed Asset">Fixed Asset (> 1 year)</option>
                    <option value="Non-Stock Item">Non-Stock Item</option>
                  </optgroup>
                  <optgroup label="Perishability">
                    <option value="Perishable">Perishable</option>
                    <option value="Non-Perishable">Non-Perishable</option>
                    <option value="Hazardous">Hazardous Materials</option>
                  </optgroup>
                </select>
              </div>
            </div>

            <!-- Description -->
            <div class="form-group" style="margin-top: 1.5rem;">
              <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                Description
              </label>
              <textarea id="description" name="description" class="form-input" rows="3" placeholder="Enter product description (optional)" style="resize: vertical;"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <!-- Unit of Measure -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Unit of Measure <span style="color: var(--color-danger);">*</span>
                </label>
                <select id="unit_of_measure" name="unit_of_measure" class="form-select" required>
                  <optgroup label="Count/Quantity">
                    <option value="pcs" selected>Pieces (pcs)</option>
                    <option value="unit">Unit</option>
                    <option value="each">Each</option>
                    <option value="dozen">Dozen (12)</option>
                    <option value="gross">Gross (144)</option>
                    <option value="pair">Pair</option>
                    <option value="set">Set</option>
                  </optgroup>
                  <optgroup label="Weight (Metric)">
                    <option value="kg">Kilogram (kg)</option>
                    <option value="g">Gram (g)</option>
                    <option value="mg">Milligram (mg)</option>
                    <option value="metric_ton">Metric Ton (1000 kg)</option>
                  </optgroup>
                  <optgroup label="Weight (Imperial)">
                    <option value="lb">Pound (lb)</option>
                    <option value="oz">Ounce (oz)</option>
                    <option value="ton">Ton (2000 lb)</option>
                  </optgroup>
                  <optgroup label="Volume (Metric)">
                    <option value="L">Liter (L)</option>
                    <option value="mL">Milliliter (mL)</option>
                    <option value="cubic_meter">Cubic Meter (m³)</option>
                  </optgroup>
                  <optgroup label="Volume (Imperial)">
                    <option value="gallon">Gallon (gal)</option>
                    <option value="quart">Quart (qt)</option>
                    <option value="pint">Pint (pt)</option>
                    <option value="fluid_oz">Fluid Ounce (fl oz)</option>
                  </optgroup>
                  <optgroup label="Length">
                    <option value="meter">Meter (m)</option>
                    <option value="cm">Centimeter (cm)</option>
                    <option value="mm">Millimeter (mm)</option>
                    <option value="foot">Foot (ft)</option>
                    <option value="inch">Inch (in)</option>
                    <option value="yard">Yard (yd)</option>
                  </optgroup>
                  <optgroup label="Area">
                    <option value="sq_meter">Square Meter (m²)</option>
                    <option value="sq_foot">Square Foot (ft²)</option>
                  </optgroup>
                  <optgroup label="Packaging">
                    <option value="box">Box</option>
                    <option value="carton">Carton</option>
                    <option value="case">Case</option>
                    <option value="pack">Pack</option>
                    <option value="bag">Bag</option>
                    <option value="pallet">Pallet</option>
                    <option value="container">Container</option>
                    <option value="crate">Crate</option>
                    <option value="bundle">Bundle</option>
                  </optgroup>
                  <optgroup label="Time-based">
                    <option value="hour">Hour</option>
                    <option value="day">Day</option>
                    <option value="month">Month</option>
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
                  <option value="1 day">1 day</option>
                  <option value="3 days">3 days</option>
                  <option value="1 week">1 week</option>
                  <option value="2 weeks">2 weeks</option>
                  <option value="1 month">1 month</option>
                  <option value="3 months">3 months</option>
                  <option value="6 months">6 months</option>
                  <option value="1 year">1 year</option>
                  <option value="2 to 10 years">2-10 years</option>
                </select>
              </div>
            </div>
          </div>

          <!-- PRICING TAB -->
          <div class="tab-content" id="tab-pricing" style="padding: 2rem; display: none;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
              <!-- Cost Price -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Cost Price <span style="color: var(--color-danger);">*</span>
                </label>
                <input type="number" id="cost_price" name="cost_price" class="form-input" placeholder="0.00" step="0.01" min="0" required oninput="calculateMarkup()">
              </div>

              <!-- Selling Price -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Selling Price <span style="color: var(--color-danger);">*</span>
                </label>
                <input type="number" id="sell_price" name="sell_price" class="form-input" placeholder="0.00" step="0.01" min="0" required oninput="calculateMarkup()">
              </div>

              <!-- Markup % -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Markup %
                </label>
                <input type="text" id="markup" class="form-input" placeholder="0%" readonly style="background: hsl(240 5% 96%);">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <!-- Tax Rate -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Tax Rate (%)
                </label>
                <select id="tax_rate" name="tax_rate" class="form-select">
                  <option value="0">No Tax / Tax Exempt (0%)</option>
                  <optgroup label="Philippines (PH)">
                    <option value="12" selected>VAT 12% (Standard)</option>
                    <option value="0-ph">VAT Zero-Rated (Exports)</option>
                  </optgroup>
                  <optgroup label="United States (US)">
                    <option value="5">Sales Tax 5%</option>
                    <option value="7">Sales Tax 7%</option>
                    <option value="8.5">Sales Tax 8.5%</option>
                  </optgroup>
                  <optgroup label="European Union (EU)">
                    <option value="19">VAT 19% (Germany)</option>
                    <option value="20">VAT 20% (UK/France)</option>
                    <option value="21">VAT 21% (Netherlands)</option>
                    <option value="23">VAT 23% (Poland)</option>
                    <option value="25">VAT 25% (Denmark/Sweden)</option>
                  </optgroup>
                  <optgroup label="Asia Pacific">
                    <option value="10">GST 10% (Australia)</option>
                    <option value="15">GST 15% (New Zealand)</option>
                    <option value="7-sg">GST 7% (Singapore)</option>
                    <option value="5-jp">Consumption Tax 5% (Japan)</option>
                  </optgroup>
                  <optgroup label="Custom Rates">
                    <option value="3">3%</option>
                    <option value="6">6%</option>
                    <option value="13">13%</option>
                    <option value="15">15%</option>
                    <option value="18">18%</option>
                  </optgroup>
                </select>
              </div>

              <!-- Final Price (with tax) -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Price (incl. Tax)
                </label>
                <input type="text" id="price_with_tax" class="form-input" readonly style="background: hsl(240 5% 96%); font-weight: 600;">
              </div>
            </div>
          </div>

          <!-- INVENTORY TAB -->
          <div class="tab-content" id="tab-inventory" style="padding: 2rem; display: none;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem;">
              <!-- Initial Quantity -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Initial Quantity <span style="color: var(--color-danger);">*</span>
                </label>
                <input type="number" id="quantity" name="quantity" class="form-input" value="0" min="0" required>
              </div>

              <!-- Min Stock Level -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Min Stock Level
                </label>
                <input type="number" id="min_stock" name="min_stock" class="form-input" placeholder="10" min="0">
              </div>

              <!-- Reorder Point -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Reorder Point
                </label>
                <input type="number" id="reorder_point" name="reorder_point" class="form-input" placeholder="20" min="0">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <!-- Max Stock Level -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Max Stock Level
                </label>
                <input type="number" id="max_stock" name="max_stock" class="form-input" placeholder="100" min="0">
              </div>

              <!-- Location/Warehouse -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Location/Warehouse
                </label>
                <select id="location" name="location" class="form-select">
                  <option value="">Select Location</option>
                  <optgroup label="Warehouses">
                    <option value="Main Warehouse">Main Warehouse (HQ)</option>
                    <option value="Warehouse North">Warehouse North</option>
                    <option value="Warehouse South">Warehouse South</option>
                    <option value="Warehouse East">Warehouse East</option>
                    <option value="Warehouse West">Warehouse West</option>
                    <option value="Central Distribution Center">Central Distribution Center</option>
                  </optgroup>
                  <optgroup label="Retail Stores">
                    <option value="Store A - Mall">Store A - Shopping Mall</option>
                    <option value="Store B - Downtown">Store B - Downtown</option>
                    <option value="Store C - Airport">Store C - Airport</option>
                    <option value="Flagship Store">Flagship Store</option>
                  </optgroup>
                  <optgroup label="Facilities">
                    <option value="Manufacturing Plant">Manufacturing Plant</option>
                    <option value="Assembly Line">Assembly Line</option>
                    <option value="Quality Control">Quality Control Area</option>
                    <option value="Cold Storage">Cold Storage Facility</option>
                    <option value="Hazmat Storage">Hazmat Storage</option>
                  </optgroup>
                  <optgroup label="Transit">
                    <option value="In Transit">In Transit</option>
                    <option value="Receiving Dock">Receiving Dock</option>
                    <option value="Shipping Dock">Shipping Dock</option>
                    <option value="Quarantine">Quarantine Area</option>
                  </optgroup>
                  <optgroup label="External">
                    <option value="Third Party Logistics">Third Party Logistics (3PL)</option>
                    <option value="Consignment">Consignment Stock</option>
                    <option value="Supplier Location">Supplier Location</option>
                  </optgroup>
                </select>
              </div>
            </div>
          </div>

          <!-- ADVANCED TAB -->
          <div class="tab-content" id="tab-advanced" style="padding: 2rem; display: none;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
              <!-- Supplier -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Supplier / Vendor
                </label>
                <select id="supplier" name="supplier" class="form-select">
                  <option value="">Select Supplier</option>
                  <optgroup label="Domestic Suppliers">
                    <option value="Metro Supply Co">Metro Supply Co</option>
                    <option value="Central Trading Inc">Central Trading Inc</option>
                    <option value="Prime Distributors">Prime Distributors</option>
                    <option value="Quality Wholesale">Quality Wholesale</option>
                    <option value="Local Goods Ltd">Local Goods Ltd</option>
                  </optgroup>
                  <optgroup label="International Suppliers">
                    <option value="Global Imports LLC">Global Imports LLC</option>
                    <option value="Asia Pacific Trading">Asia Pacific Trading</option>
                    <option value="Euro Supplies GmbH">Euro Supplies GmbH</option>
                    <option value="Americas Wholesale">Americas Wholesale</option>
                  </optgroup>
                  <optgroup label="Manufacturers">
                    <option value="Direct from Manufacturer">Direct from Manufacturer</option>
                    <option value="OEM Partner">OEM Partner</option>
                  </optgroup>
                  <optgroup label="Other">
                    <option value="Multiple Suppliers">Multiple Suppliers</option>
                    <option value="TBD">To Be Determined</option>
                  </optgroup>
                </select>
              </div>

              <!-- Manufacturer -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Manufacturer
                </label>
                <input type="text" id="manufacturer" name="manufacturer" class="form-input" placeholder="e.g., Samsung, Nike, Nestle">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <!-- Brand -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Brand
                </label>
                <input type="text" id="brand" name="brand" class="form-input" placeholder="Product brand name">
              </div>

              <!-- Model Number -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Model / Part Number
                </label>
                <input type="text" id="model_number" name="model_number" class="form-input" placeholder="Model or part number">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <!-- Tracking Type -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Inventory Tracking
                </label>
                <select id="tracking_type" name="tracking_type" class="form-select">
                  <option value="none" selected>No Tracking</option>
                  <option value="serial">Serial Number Tracking</option>
                  <option value="lot">Lot Number Tracking</option>
                  <option value="batch">Batch Tracking</option>
                  <option value="expiry">Expiry Date Tracking</option>
                  <option value="fifo">FIFO (First In First Out)</option>
                  <option value="lifo">LIFO (Last In First Out)</option>
                  <option value="weighted">Weighted Average Cost</option>
                </select>
              </div>

              <!-- Condition -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Condition
                </label>
                <select id="condition" name="condition" class="form-select">
                  <option value="new" selected>New</option>
                  <option value="refurbished">Refurbished</option>
                  <option value="used-like-new">Used - Like New</option>
                  <option value="used-good">Used - Good</option>
                  <option value="used-acceptable">Used - Acceptable</option>
                  <option value="damaged">Damaged</option>
                  <option value="open-box">Open Box</option>
                </select>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <!-- Warranty Period -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Warranty Period
                </label>
                <select id="warranty_period" name="warranty_period" class="form-select">
                  <option value="">No Warranty</option>
                  <option value="30 days">30 Days</option>
                  <option value="90 days">90 Days</option>
                  <option value="6 months">6 Months</option>
                  <option value="1 year">1 Year</option>
                  <option value="2 years">2 Years</option>
                  <option value="3 years">3 Years</option>
                  <option value="5 years">5 Years</option>
                  <option value="lifetime">Lifetime Warranty</option>
                </select>
              </div>

              <!-- Country of Origin -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Country of Origin
                </label>
                <select id="country_origin" name="country_origin" class="form-select">
                  <option value="">Select Country</option>
                  <option value="PH">🇵🇭 Philippines</option>
                  <option value="US">🇺🇸 United States</option>
                  <option value="CN">🇨🇳 China</option>
                  <option value="JP">🇯🇵 Japan</option>
                  <option value="KR">🇰🇷 South Korea</option>
                  <option value="DE">🇩🇪 Germany</option>
                  <option value="UK">🇬🇧 United Kingdom</option>
                  <option value="FR">🇫🇷 France</option>
                  <option value="IT">🇮🇹 Italy</option>
                  <option value="TH">🇹🇭 Thailand</option>
                  <option value="VN">🇻🇳 Vietnam</option>
                  <option value="MY">🇲🇾 Malaysia</option>
                  <option value="SG">🇸🇬 Singapore</option>
                  <option value="IN">🇮🇳 India</option>
                  <option value="AU">🇦🇺 Australia</option>
                </select>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
              <!-- Sales Account (Accounting Integration) -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Sales Account <small style="font-weight: 400; color: var(--text-secondary);">(Accounting)</small>
                </label>
                <select id="sales_account" name="sales_account" class="form-select">
                  <option value="">Select Account</option>
                  <option value="4000">4000 - Sales Revenue</option>
                  <option value="4100">4100 - Product Sales</option>
                  <option value="4200">4200 - Service Revenue</option>
                  <option value="4300">4300 - Other Income</option>
                </select>
              </div>

              <!-- Purchase Account -->
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                  Purchase Account <small style="font-weight: 400; color: var(--text-secondary);">(Accounting)</small>
                </label>
                <select id="purchase_account" name="purchase_account" class="form-select">
                  <option value="">Select Account</option>
                  <option value="5000">5000 - Cost of Goods Sold</option>
                  <option value="5100">5100 - Purchases</option>
                  <option value="5200">5200 - Direct Costs</option>
                  <option value="5300">5300 - Materials</option>
                </select>
              </div>
            </div>

            <!-- Tags -->
            <div class="form-group" style="margin-top: 1.5rem;">
              <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                Tags & Keywords
              </label>
              <input type="text" id="tags" name="tags" class="form-input" placeholder="e.g., organic, imported, bestseller, seasonal">
              <small style="color: var(--text-secondary); font-size: 0.75rem; display: block; margin-top: 0.25rem;">Separate tags with commas for better searchability</small>
            </div>

            <!-- Internal Notes -->
            <div class="form-group" style="margin-top: 1.5rem;">
              <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">
                Internal Notes <small style="font-weight: 400; color: var(--text-secondary);">(Not visible to customers)</small>
              </label>
              <textarea id="internal_notes" name="internal_notes" class="form-input" rows="3" placeholder="Add internal notes, handling instructions, or special requirements..." style="resize: vertical;"></textarea>
            </div>
          </div>

          <!-- Form Actions (shown on all tabs) -->
          <div style="padding: 1.5rem 2rem; background: hsl(240 5% 96%); border-top: 1px solid hsl(240 6% 90%); display: flex; justify-content: space-between; align-items: center;">
            <a href="inventory-list.php" style="color: var(--text-secondary); text-decoration: none; font-weight: 500;">Cancel</a>
            <div style="display: flex; gap: 0.75rem;">
              <button type="button" onclick="saveDraft()" style="padding: 0.625rem 1.25rem; border: 1px solid var(--border-color); border-radius: 8px; background: white; cursor: pointer; font-weight: 500;">💾 Save Draft</button>
              <button type="submit" id="submitBtn" style="padding: 0.625rem 1.5rem; border: none; border-radius: 8px; background: var(--color-primary); color: white; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                <span>✓</span> Add Item
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- RIGHT COLUMN: Status & Quick Actions -->
    <div style="position: sticky; top: 2rem;">
      <!-- Form Status Card -->
      <div style="background: white; border: 1px solid hsl(240 6% 90%); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 1.5rem;">
        <!-- Card Header -->
        <div style="padding: 1.25rem 1.5rem; background: linear-gradient(135deg, hsl(220 20% 97%) 0%, white 100%); border-bottom: 1px solid hsl(240 6% 90%);">
          <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 36px; height: 36px; background: var(--color-primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(113, 148, 165, 0.2);">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
              </svg>
            </div>
            <div>
              <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: hsl(240 10% 10%);">Form Status</h3>
              <p style="font-size: 0.6875rem; margin: 0; color: hsl(240 5% 50%); font-weight: 500;">Track your progress</p>
            </div>
          </div>
        </div>
        
        <!-- Card Body -->
        <div style="padding: 1.5rem;">
          <!-- Progress Bar -->
          <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.625rem;">
              <span style="font-size: 0.75rem; font-weight: 700; color: hsl(240 5% 50%); text-transform: uppercase; letter-spacing: 0.05em;">Completion</span>
              <span id="progressPercent" style="font-size: 1.5rem; font-weight: 800; color: var(--color-primary); line-height: 1;">0%</span>
            </div>
            <div style="width: 100%; height: 8px; background: hsl(240 5% 96%); border-radius: 999px; overflow: hidden; border: 1px solid hsl(240 6% 90%);">
              <div id="progressBar" style="height: 100%; width: 0%; background: linear-gradient(90deg, var(--color-primary), var(--color-success)); transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);"></div>
            </div>
          </div>

          <!-- Field Status Grid -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="padding: 0.875rem; background: hsl(240 5% 98%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; text-align: center;">
              <div style="font-size: 0.6875rem; font-weight: 700; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Required</div>
              <div id="requiredCount" style="font-size: 1.125rem; font-weight: 800; color: hsl(25 95% 35%);">0/9</div>
            </div>
            <div style="padding: 0.875rem; background: hsl(240 5% 98%); border: 1px solid hsl(240 6% 90%); border-radius: 8px; text-align: center;">
              <div style="font-size: 0.6875rem; font-weight: 700; color: hsl(240 5% 50%); margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em;">Optional</div>
              <div id="optionalCount" style="font-size: 1.125rem; font-weight: 800; color: hsl(240 5% 40%);">0/21</div>
            </div>
          </div>

          <!-- Status Alert -->
          <div id="formStatusBadge" style="padding: 0.875rem 1rem; background: hsl(48 96% 89%); border: 1px solid hsl(48 96% 75%); border-radius: 8px; display: flex; align-items: start; gap: 0.75rem;">
            <div style="flex-shrink: 0; margin-top: 0.125rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(25 95% 35%)" stroke-width="2.5">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01" stroke-linecap="round"/>
              </svg>
            </div>
            <div style="flex: 1;">
              <div style="font-size: 0.8125rem; font-weight: 700; color: hsl(25 95% 20%); margin-bottom: 0.125rem;" id="statusTitle">Incomplete Form</div>
              <div style="font-size: 0.75rem; color: hsl(25 95% 25%); font-weight: 500; line-height: 1.4;" id="statusMessage">Fill required fields to proceed</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Selection Card -->
      <div style="background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
          ⚡ Quick Actions
        </h3>
        
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
          <button type="button" onclick="autoGenerateSKU()" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: white; cursor: pointer; text-align: left; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
            🔢 Auto-Generate SKU
          </button>
          <button type="button" onclick="copyFromRecent()" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: white; cursor: pointer; text-align: left; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
            📋 Copy from Recent
          </button>
          <button type="button" onclick="uploadImage()" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: white; cursor: pointer; text-align: left; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
            📸 Upload Image
          </button>
          <button type="button" onclick="importFromFile()" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: white; cursor: pointer; text-align: left; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
            📥 Import from File
          </button>
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
// BIO-PAGE INSPIRED INVENTORY ITEM FORM
// Xero + QuickBooks + LedgerSMB Features
// ============================================

// Tab Switching
function switchTab(tabName) {
  // Hide all tabs
  document.querySelectorAll('.tab-content').forEach(tab => {
    tab.style.display = 'none';
  });
  
  // Remove active class from all buttons
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.style.background = 'transparent';
    btn.style.borderBottom = '3px solid transparent';
    btn.style.color = 'var(--text-secondary)';
    btn.classList.remove('active');
  });
  
  // Show selected tab
  document.getElementById('tab-' + tabName).style.display = 'block';
  
  // Mark button as active
  const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
  activeBtn.style.background = 'white';
  activeBtn.style.borderBottom = '3px solid var(--color-primary)';
  activeBtn.style.color = 'var(--color-primary)';
  activeBtn.classList.add('active');
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
  console.log('Barcode scanned:', mockBarcode);
}

// Auto-Generate SKU
function autoGenerateSKU() {
  const type = document.getElementById('type').value;
  if (!type) {
    if (typeof Toast !== 'undefined') {
      Toast.warning('Please select a product type first');
    } else {
      alert('Please select a product type first');
    }
    return;
  }
  
  const prefix = type.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, '');
  const timestamp = Date.now().toString().slice(-6);
  const sku = prefix + '-' + timestamp;
  document.getElementById('sku').value = sku;
  document.getElementById('sku').dispatchEvent(new Event('input'));
  
  if (typeof Toast !== 'undefined') {
    Toast.success('SKU generated: ' + sku);
  }
  console.log('SKU generated:', sku);
}

// Copy from Recent (Mock)
function copyFromRecent() {
  console.log('Copy from recent items feature - would show modal with recent items');
  if (typeof Toast !== 'undefined') {
    Toast.info('Copy from Recent: Feature would show modal with recent items to duplicate');
  } else {
    alert('This would show a list of recent items to copy from');
  }
}

// Upload Image (Mock)
function uploadImage() {
  console.log('Upload image feature - would open file picker');
  if (typeof Toast !== 'undefined') {
    Toast.info('Image Upload: Would open file picker for product images');
  } else {
    alert('Image upload functionality would be implemented here');
  }
}

// Import from File (Mock)
function importFromFile() {
  console.log('Import from file feature - would allow CSV/Excel import');
  if (typeof Toast !== 'undefined') {
    Toast.info('Bulk Import: Would allow CSV/Excel file import for multiple items');
  } else {
    alert('Bulk import from CSV/Excel would be available here');
  }
}

// Save Draft
function saveDraft() {
  const formData = new FormData(document.getElementById('itemForm'));
  const draftData = Object.fromEntries(formData);
  
  // Save to localStorage
  localStorage.setItem('inventory_item_draft', JSON.stringify(draftData));
  
  console.log('Draft saved:', draftData);
  if (typeof Toast !== 'undefined') {
    Toast.success('Draft saved successfully!');
  } else {
    alert('Draft saved locally!');
  }
}

// Load Draft on page load
function loadDraft() {
  const draft = localStorage.getItem('inventory_item_draft');
  if (draft) {
    const confirmed = confirm('Found a saved draft. Would you like to load it?');
    if (confirmed) {
      const draftData = JSON.parse(draft);
      Object.keys(draftData).forEach(key => {
        const field = document.getElementById(key);
        if (field && draftData[key]) {
          field.value = draftData[key];
          field.dispatchEvent(new Event('input'));
          field.dispatchEvent(new Event('change'));
        }
      });
      if (typeof Toast !== 'undefined') {
        Toast.success('Draft loaded successfully!');
      }
    }
  }
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

// Add slideDown animation if not exists
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

// Progress Tracking
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('itemForm');
  const requiredFields = form.querySelectorAll('[required]');
  const optionalFields = form.querySelectorAll('input:not([required]), select:not([required]), textarea:not([required])');
  const progressBar = document.getElementById('progressBar');
  const progressPercent = document.getElementById('progressPercent');
  const requiredCount = document.getElementById('requiredCount');
  const optionalCount = document.getElementById('optionalCount');
  const formStatusBadge = document.getElementById('formStatusBadge');
  const submitBtn = document.getElementById('submitBtn');
  
  function updateProgress() {
    // Count filled required fields
    let filledRequired = 0;
    requiredFields.forEach(field => {
      if (field.value && field.value.trim() !== '') {
        filledRequired++;
      }
    });
    
    // Count filled optional fields
    let filledOptional = 0;
    optionalFields.forEach(field => {
      if (field.value && field.value.trim() !== '') {
        filledOptional++;
      }
    });
    
    // Update counts
    requiredCount.textContent = filledRequired + '/' + requiredFields.length;
    optionalCount.textContent = filledOptional + '/' + optionalFields.length;
    
    // Calculate progress
    const progress = Math.round((filledRequired / requiredFields.length) * 100);
    progressBar.style.width = progress + '%';
    progressPercent.textContent = progress + '%';
    
    // Update status badge
    // Update status alert
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    const statusIcon = formStatusBadge.querySelector('svg');
    
    if (progress === 100) {
      // Success state
      formStatusBadge.style.background = 'hsl(143 85% 96%)';
      formStatusBadge.style.borderColor = 'hsl(143 85% 80%)';
      statusIcon.style.stroke = 'hsl(140 61% 35%)';
      statusIcon.innerHTML = '<circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>';
      statusTitle.style.color = 'hsl(140 61% 20%)';
      statusTitle.textContent = 'Form Complete';
      statusMessage.style.color = 'hsl(140 61% 25%)';
      statusMessage.textContent = 'All required fields filled. Ready to submit!';
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
    } else {
      // Warning state
      formStatusBadge.style.background = 'hsl(48 96% 89%)';
      formStatusBadge.style.borderColor = 'hsl(48 96% 75%)';
      statusIcon.style.stroke = 'hsl(25 95% 35%)';
      statusIcon.innerHTML = '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01" stroke-linecap="round"/>';
      statusTitle.style.color = 'hsl(25 95% 20%)';
      statusTitle.textContent = 'Incomplete Form';
      statusMessage.style.color = 'hsl(25 95% 25%)';
      statusMessage.textContent = 'Fill required fields to proceed';
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.5';
    }
  }
  
  // Add event listeners
  [...requiredFields, ...optionalFields].forEach(field => {
    field.addEventListener('input', updateProgress);
    field.addEventListener('change', updateProgress);
  });
  
  // Real-time validation
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
  
  // Tax rate change listener
  document.getElementById('tax_rate').addEventListener('change', calculateMarkup);
  
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
    submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg> Adding...';
    submitBtn.disabled = true;
    console.log('✅ Form validation passed - Submitting...');
  });
  
  // Initial update
  updateProgress();
  
  // Check for draft on page load
  loadDraft();
});

console.log('%c📦 Inventory Item Form Loaded - Enterprise Edition', 'color: #3b82f6; font-size: 14px; font-weight: bold;');
console.log('%cFeatures: 4 Tabs • 25+ Fields • Progress Tracking • Auto-calculations • Quick Actions • Draft Save/Load', 'color: #6b7280; font-size: 12px;');
console.log('%cXero + QuickBooks + LedgerSMB Features Integrated', 'color: #10b981; font-size: 12px;');
</script>

<?php
$pageContent = ob_get_clean();
include __DIR__ . '/components/layout.php';
?>
