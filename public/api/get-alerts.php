<?php
/**
 * Get Alerts API
 * Returns count of critical alerts (low stock, out of stock, expiring items)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Model\Inventory;

try {
    $inventoryModel = new Inventory();
    
    // Get low stock items (quantity <= 10)
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $expiringCount = 0;
    
    $allItems = $inventoryModel->getAll();
    
    foreach ($allItems as $item) {
        $quantity = $item['quantity'] ?? 0;
        
        if ($quantity == 0) {
            $outOfStockCount++;
        } elseif ($quantity <= 10) {
            $lowStockCount++;
        }
        
        // Check for expiring items (if expiry date exists)
        if (isset($item['expiry_date'])) {
            $expiryDate = strtotime($item['expiry_date']);
            $today = strtotime('today');
            $daysUntilExpiry = ($expiryDate - $today) / (60 * 60 * 24);
            
            if ($daysUntilExpiry > 0 && $daysUntilExpiry <= 30) {
                $expiringCount++;
            }
        }
    }
    
    $totalAlerts = $lowStockCount + $outOfStockCount + $expiringCount;
    
    echo json_encode([
        'success' => true,
        'total' => $totalAlerts,
        'alerts' => [
            'low_stock' => $lowStockCount,
            'out_of_stock' => $outOfStockCount,
            'expiring' => $expiringCount
        ]
    ]);
    
} catch (Exception $e) {
    // Return error response instead of mock data
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'total' => 0,
        'alerts' => [
            'low_stock' => 0,
            'out_of_stock' => 0,
            'expiring' => 0
        ]
    ]);
}
