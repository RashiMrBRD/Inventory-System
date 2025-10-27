<?php
/**
 * Inventory API - AJAX endpoint for dynamic inventory updates
 * Returns JSON data without page refresh
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Inventory;
use App\Helper\CurrencyHelper;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();
$inventoryModel = new Inventory();

// Get action
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Get filter parameters
            $searchQuery = $_GET['search'] ?? '';
            $categoryFilter = $_GET['category'] ?? 'all';
            $statusFilter = $_GET['status'] ?? 'all';
            $sortBy = $_GET['sort'] ?? 'name';
            $sortOrder = $_GET['order'] ?? 'asc';
            $currentPage = max(1, (int)($_GET['page'] ?? 1));
            $itemsPerPage = (int)($_GET['per_page'] ?? 6);
            
            // Get ALL inventory items first
            if (!empty($searchQuery)) {
                $allItems = $inventoryModel->search($searchQuery);
            } else {
                $allItems = $inventoryModel->getAll();
            }
            
            // Apply filters
            if ($categoryFilter !== 'all') {
                $allItems = array_filter($allItems, fn($item) => ($item['type'] ?? '') === $categoryFilter);
            }
            
            if ($statusFilter !== 'all') {
                $allItems = array_filter($allItems, function($item) use ($statusFilter) {
                    $qty = $item['quantity'] ?? 0;
                    if ($statusFilter === 'in_stock') return $qty > 5;
                    if ($statusFilter === 'low_stock') return $qty > 0 && $qty <= 5;
                    if ($statusFilter === 'out_of_stock') return $qty == 0;
                    return true;
                });
            }
            
            // Sorting with proper field mapping
            usort($allItems, function($a, $b) use ($sortBy, $sortOrder) {
                switch($sortBy) {
                    case 'barcode':
                    case 'sku':
                        $aVal = $a['barcode'] ?? '';
                        $bVal = $b['barcode'] ?? '';
                        break;
                    case 'name':
                        $aVal = $a['name'] ?? '';
                        $bVal = $b['name'] ?? '';
                        break;
                    case 'category':
                    case 'type':
                        $aVal = $a['type'] ?? '';
                        $bVal = $b['type'] ?? '';
                        break;
                    case 'price':
                        $aVal = (float)($a['price'] ?? 0);
                        $bVal = (float)($b['price'] ?? 0);
                        break;
                    case 'quantity':
                    case 'stock':
                        $aVal = (int)($a['quantity'] ?? 0);
                        $bVal = (int)($b['quantity'] ?? 0);
                        break;
                    case 'status':
                        $aQty = (int)($a['quantity'] ?? 0);
                        $bQty = (int)($b['quantity'] ?? 0);
                        $aStatus = $aQty == 0 ? 0 : ($aQty <= 5 ? 1 : 2);
                        $bStatus = $bQty == 0 ? 0 : ($bQty <= 5 ? 1 : 2);
                        $aVal = $aStatus;
                        $bVal = $bStatus;
                        break;
                    case 'value':
                        $aVal = ((int)($a['quantity'] ?? 0)) * ((float)($a['price'] ?? 0));
                        $bVal = ((int)($b['quantity'] ?? 0)) * ((float)($b['price'] ?? 0));
                        break;
                    case 'date':
                    case 'updated':
                        $aVal = isset($a['date_added']) ? $a['date_added']->toDateTime()->getTimestamp() : 0;
                        $bVal = isset($b['date_added']) ? $b['date_added']->toDateTime()->getTimestamp() : 0;
                        break;
                    default:
                        $aVal = $a[$sortBy] ?? '';
                        $bVal = $b[$sortBy] ?? '';
                }
                
                $result = $aVal <=> $bVal;
                return $sortOrder === 'desc' ? -$result : $result;
            });
            
            // Calculate totals
            $totalValue = 0;
            $totalQuantity = 0;
            $lowStockCount = 0;
            $outOfStockCount = 0;
            foreach ($allItems as $item) {
                $qty = $item['quantity'] ?? 0;
                $price = (float)($item['price'] ?? 0);
                $totalValue += $qty * $price;
                $totalQuantity += $qty;
                if ($qty == 0) $outOfStockCount++;
                elseif ($qty <= 5) $lowStockCount++;
            }
            
            // Calculate pagination
            $totalItems = count($allItems);
            $totalPages = max(1, ceil($totalItems / $itemsPerPage));
            $currentPage = min($currentPage, $totalPages);
            $offset = ($currentPage - 1) * $itemsPerPage;
            
            // Get items for current page
            $items = array_slice($allItems, $offset, $itemsPerPage);
            
            // Format items for JSON response
            $formattedItems = [];
            $counter = $offset + 1;
            foreach ($items as $item) {
                $itemId = (string)$item['_id'];
                $quantity = $item['quantity'] ?? 0;
                $price = (float)($item['price'] ?? 0);
                $itemValue = $quantity * $price;
                $dateAdded = isset($item['date_added']) ? $item['date_added']->toDateTime()->format('M d, Y') : 'N/A';
                
                $maxStock = 50;
                $stockPercent = min(100, ($quantity / $maxStock) * 100);
                $stockLevel = $quantity > 5 ? 'high' : ($quantity > 0 ? 'medium' : 'low');
                
                $formattedItems[] = [
                    'id' => $itemId,
                    'counter' => $counter++,
                    'barcode' => $item['barcode'] ?? 'SKU-' . $counter,
                    'name' => $item['name'] ?? 'Unnamed Item',
                    'type' => $item['type'] ?? 'General',
                    'lifespan' => $item['lifespan'] ?? '',
                    'quantity' => $quantity,
                    'price' => $price,
                    'price_formatted' => CurrencyHelper::format($price),
                    'value' => $itemValue,
                    'value_formatted' => CurrencyHelper::format($itemValue),
                    'date_added' => $dateAdded,
                    'stock_percent' => $stockPercent,
                    'stock_level' => $stockLevel,
                    'is_low_stock' => $quantity > 0 && $quantity <= 5,
                    'is_out_of_stock' => $quantity == 0,
                    'avatar_letter' => strtoupper(substr($item['name'] ?? 'I', 0, 1))
                ];
            }
            
            echo json_encode([
                'success' => true,
                'items' => $formattedItems,
                'pagination' => [
                    'current_page' => $currentPage,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                    'items_per_page' => $itemsPerPage,
                    'offset' => $offset,
                    'showing_from' => $offset + 1,
                    'showing_to' => min($offset + $itemsPerPage, $totalItems)
                ],
                'stats' => [
                    'total_items' => $totalItems,
                    'total_quantity' => $totalQuantity,
                    'total_value' => $totalValue,
                    'total_value_formatted' => CurrencyHelper::format($totalValue),
                    'low_stock_count' => $lowStockCount,
                    'out_of_stock_count' => $outOfStockCount,
                    'currency_symbol' => CurrencyHelper::symbol()
                ]
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
