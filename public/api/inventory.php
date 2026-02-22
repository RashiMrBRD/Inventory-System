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
            
            // Use server-side pagination for large datasets
            $options = [
                'search' => $searchQuery,
                'category' => $categoryFilter,
                'status' => $statusFilter,
                'sort' => $sortBy,
                'order' => $sortOrder,
                'page' => $currentPage,
                'per_page' => $itemsPerPage
            ];
            
            $result = $inventoryModel->getPaginated($options);
            $items = $result['items'];
            $totalItems = $result['total'];
            $totalPages = $result['total_pages'];
            $offset = ($currentPage - 1) * $itemsPerPage;
            
            // Get stats with same filters (for accurate counts)
            $statsFilters = [
                'search' => $searchQuery,
                'category' => $categoryFilter,
                'status' => $statusFilter
            ];
            $stats = $inventoryModel->getStatsWithFilters($statsFilters);
            
            // Format items for JSON response
            $formattedItems = [];
            $counter = $offset + 1;
            foreach ($items as $item) {
                $itemId = (string)$item['_id'];
                $quantity = $item['quantity'] ?? 0;
                $price = (float)($item['sell_price'] ?? $item['price'] ?? 0);
                $itemValue = $quantity * $price;
                $dateAdded = isset($item['date_added']) ? $item['date_added']->toDateTime()->format('M d, Y') : 'N/A';
                
                $maxStock = 50;
                $stockPercent = min(100, ($quantity / $maxStock) * 100);
                $stockLevel = $quantity > 5 ? 'high' : ($quantity > 0 ? 'medium' : 'low');
                
                $formattedItems[] = [
                    'id' => $itemId,
                    'counter' => $counter++,
                    'sku' => $item['sku'] ?? '',
                    'barcode' => $item['barcode'] ?? '',
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
                    'total_items' => $stats['total_items'],
                    'total_quantity' => $stats['total_quantity'],
                    'total_value' => $stats['total_value'],
                    'total_value_formatted' => CurrencyHelper::format($stats['total_value']),
                    'low_stock_count' => $stats['low_stock_count'],
                    'out_of_stock_count' => $stats['out_of_stock_count'],
                    'currency_symbol' => CurrencyHelper::symbol(),
                    'categories' => $stats['categories']
                ]
            ]);
            break;
            
        case 'get_item':
            // Get single item details
            $itemId = $_GET['id'] ?? '';
            
            if (empty($itemId)) {
                throw new Exception('Item ID is required');
            }
            
            $item = $inventoryModel->findById($itemId);
            
            if (!$item) {
                http_response_code(404);
                throw new Exception('Item not found');
            }
            
            // Format dates
            $dateAdded = isset($item['date_added']) ? $item['date_added']->toDateTime()->format('M d, Y') : 'N/A';
            
            // Format item data
            $formattedItem = [
                'id' => (string)$item['_id'],
                'barcode' => $item['barcode'] ?? '',
                'sku' => $item['sku'] ?? '',
                'name' => $item['name'] ?? '',
                'type' => $item['type'] ?? '',
                'category' => $item['category'] ?? '',
                'description' => $item['description'] ?? '',
                'lifespan' => $item['lifespan'] ?? '',
                'unit_of_measure' => $item['unit_of_measure'] ?? 'pcs',
                'cost_price' => (float)($item['cost_price'] ?? 0),
                'sell_price' => (float)($item['sell_price'] ?? 0),
                'tax_rate' => (float)($item['tax_rate'] ?? 0),
                'quantity' => (int)($item['quantity'] ?? 0),
                'min_stock' => (int)($item['min_stock'] ?? 0),
                'max_stock' => (int)($item['max_stock'] ?? 0),
                'reorder_point' => (int)($item['reorder_point'] ?? 0),
                'location' => $item['location'] ?? '',
                'supplier' => $item['supplier'] ?? '',
                'manufacturer' => $item['manufacturer'] ?? '',
                'brand' => $item['brand'] ?? '',
                'model_number' => $item['model_number'] ?? '',
                'tracking_type' => $item['tracking_type'] ?? 'none',
                'condition' => $item['condition'] ?? 'new',
                'warranty_period' => $item['warranty_period'] ?? '',
                'country_origin' => $item['country_origin'] ?? '',
                'sales_account' => $item['sales_account'] ?? '',
                'purchase_account' => $item['purchase_account'] ?? '',
                'tags' => $item['tags'] ?? '',
                'internal_notes' => $item['internal_notes'] ?? '',
                'date_added_formatted' => $dateAdded
            ];
            
            echo json_encode([
                'success' => true,
                'item' => $formattedItem
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
