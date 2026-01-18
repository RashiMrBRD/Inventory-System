<?php
/**
 * Inventory Notifications API
 * Checks inventory levels and creates notifications for low/out of stock items
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Database\NotificationRepository;
use App\Model\Inventory;

header('Content-Type: application/json');

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $authController->getCurrentUser();

// Initialize with user ID
$userId = isset($user['_id']) ? (string)$user['_id'] : null;
if (!$userId) {
    // Fallback to session user_id
    $userId = $_SESSION['user_id'] ?? null;
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

$inventoryModel = new Inventory();
$notificationRepo = new NotificationRepository($userId);

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'check_inventory':
            checkInventoryLevels($inventoryModel, $notificationRepo);
            break;
        
        case 'get_inventory_alerts':
            getInventoryAlerts($notificationRepo);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Inventory Notifications API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Check inventory levels and create notifications for low/out of stock items
 */
function checkInventoryLevels(Inventory $inventoryModel, NotificationRepository $notificationRepo) {
    try {
        $inventory = $inventoryModel->getAll();
        $alertsCreated = 0;
        $alertsUpdated = 0;
        
        // Define thresholds
        $lowStockThreshold = 10; // Alert when stock is 10 or less
        $outOfStockThreshold = 0; // Alert when stock is 0
        
        foreach ($inventory as $item) {
            $quantity = (int)($item['quantity'] ?? 0);
            $itemName = $item['name'] ?? 'Unknown Item';
            $itemId = (string)$item['_id'];
            $barcode = $item['barcode'] ?? '';
            
            // Skip items that are already out of stock and have existing notifications
            if ($quantity <= 0) {
                $existingNotification = findExistingNotification($notificationRepo, 'out_of_stock', $itemId);
                if (!$existingNotification) {
                    createOutOfStockNotification($notificationRepo, $item, $itemId, $itemName, $barcode);
                    $alertsCreated++;
                }
            } elseif ($quantity <= $lowStockThreshold) {
                $existingNotification = findExistingNotification($notificationRepo, 'low_stock', $itemId);
                if (!$existingNotification) {
                    createLowStockNotification($notificationRepo, $item, $itemId, $itemName, $barcode, $quantity);
                    $alertsCreated++;
                } else {
                    // Update existing low stock notification with current quantity
                    updateLowStockNotification($notificationRepo, $existingNotification['_id'], $quantity);
                    $alertsUpdated++;
                }
            } else {
                // Item is well stocked, remove any existing low stock notifications
                removeLowStockNotifications($notificationRepo, $itemId);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Inventory check completed',
            'alerts_created' => $alertsCreated,
            'alerts_updated' => $alertsUpdated,
            'items_checked' => count($inventory)
        ]);
        
    } catch (Exception $e) {
        error_log("Error checking inventory levels: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get inventory-related notifications
 */
function getInventoryAlerts(NotificationRepository $notificationRepo) {
    try {
        $inventoryNotifications = $notificationRepo->getAll([
            'type' => 'inventory',
            'dismissed' => false,
            'deleted' => false,
            'limit' => 50
        ]);
        
        echo json_encode([
            'success' => true,
            'notifications' => $inventoryNotifications,
            'count' => count($inventoryNotifications)
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting inventory alerts: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Find existing notification for an item
 */
function findExistingNotification(NotificationRepository $notificationRepo, string $alertType, string $itemId): ?array {
    $notifications = $notificationRepo->getAll([
        'type' => 'inventory',
        'dismissed' => false,
        'deleted' => false,
        'limit' => 100
    ]);
    
    foreach ($notifications as $notification) {
        $data = $notification['data'] ?? [];
        if (($data['item_id'] ?? '') === $itemId && ($data['alert_type'] ?? '') === $alertType) {
            return $notification;
        }
    }
    
    return null;
}

/**
 * Create out of stock notification
 */
function createOutOfStockNotification(NotificationRepository $notificationRepo, array $item, string $itemId, string $itemName, string $barcode) {
    $notificationData = [
        'type' => 'inventory',
        'priority' => 'high',
        'title' => 'Out of Stock Alert',
        'message' => "Item '{$itemName}' is out of stock!",
        'data' => [
            'alert_type' => 'out_of_stock',
            'item_id' => $itemId,
            'item_name' => $itemName,
            'barcode' => $barcode,
            'current_quantity' => 0,
            'threshold' => 0
        ],
        'action_url' => '/inventory-list.php',
        'action_text' => 'View Inventory'
    ];
    
    return $notificationRepo->create($notificationData);
}

/**
 * Create low stock notification
 */
function createLowStockNotification(NotificationRepository $notificationRepo, array $item, string $itemId, string $itemName, string $barcode, int $quantity) {
    $notificationData = [
        'type' => 'inventory',
        'priority' => 'medium',
        'title' => 'Low Stock Alert',
        'message' => "Item '{$itemName}' is running low on stock ({$quantity} units remaining)",
        'data' => [
            'alert_type' => 'low_stock',
            'item_id' => $itemId,
            'item_name' => $itemName,
            'barcode' => $barcode,
            'current_quantity' => $quantity,
            'threshold' => 10
        ],
        'action_url' => '/inventory-list.php',
        'action_text' => 'View Inventory'
    ];
    
    return $notificationRepo->create($notificationData);
}

/**
 * Update existing low stock notification
 */
function updateLowStockNotification(NotificationRepository $notificationRepo, string $notificationId, int $currentQuantity) {
    // Update the notification with new quantity
    $notification = $notificationRepo->findById($notificationId);
    if ($notification) {
        $data = $notification['data'] ?? [];
        $data['current_quantity'] = $currentQuantity;
        
        $notificationRepo->update($notificationId, [
            'data' => $data,
            'message' => "Item '{$data['item_name']}' is running low on stock ({$currentQuantity} units remaining)"
        ]);
    }
}

/**
 * Remove low stock notifications for an item that's now well stocked
 */
function removeLowStockNotifications(NotificationRepository $notificationRepo, string $itemId) {
    $notifications = $notificationRepo->getAll([
        'type' => 'inventory',
        'dismissed' => false,
        'deleted' => false,
        'limit' => 100
    ]);
    
    foreach ($notifications as $notification) {
        $data = $notification['data'] ?? [];
        if (($data['item_id'] ?? '') === $itemId && ($data['alert_type'] ?? '') === 'low_stock') {
            // Dismiss the low stock notification since item is now well stocked
            $notificationRepo->update($notification['_id'], [
                'dismissed' => true,
                'data' => array_merge($data, ['auto_dismissed' => true, 'dismiss_reason' => 'Stock replenished'])
            ]);
        }
    }
}
?>
