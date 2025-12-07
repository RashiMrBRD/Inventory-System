<?php
/**
 * Shipments API Endpoint
 * Handles CRUD operations for shipments
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Shipment;
use App\Model\Order;
use App\Model\Inventory;
use App\Service\DatabaseService;

// Authenticate
$authController = new AuthController();
$isLoggedIn = $authController->isLoggedIn();
error_log('Shipments API auth check: ' . ($isLoggedIn ? 'LOGGED IN' : 'NOT LOGGED IN'));

if (!$isLoggedIn) {
    error_log('Shipments API: Unauthorized access attempt');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = $authController->getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

// Helper function to convert MongoDB documents to JSON-safe format
function convertMongoDocument($doc) {
    if (is_array($doc)) {
        foreach ($doc as $key => $value) {
            if ($key === '_id' && is_object($value)) {
                $doc['id'] = (string)$value;
                $doc['_id'] = (string)$value;
            } elseif (is_array($value) || is_object($value)) {
                $doc[$key] = convertMongoDocument($value);
            }
        }
    }
    return $doc;
}

try {
    $shipmentModel = new Shipment();
    $orderModel = new Order();
    $inventoryModel = new Inventory();
    
    // Helper function to remove inventory items from an order
    function removeInventoryItemsFromOrder($orderModel, $inventoryModel, $orderId) {
        try {
            error_log("DEBUG: Starting inventory removal for order: $orderId");
            $order = $orderModel->findById($orderId);
            if (!$order) {
                error_log("DEBUG: Order not found: $orderId");
                return;
            }
            
            if (!isset($order['items']) || empty($order['items'])) {
                error_log("DEBUG: Order has no items: $orderId");
                return;
            }
            
            error_log("DEBUG: Order has " . count($order['items']) . " items");
            
            foreach ($order['items'] as $index => $item) {
                error_log("DEBUG: Processing item $index: " . json_encode($item));
                // Try to find inventory item by various identifiers
                $inventoryItem = null;
                
                // Try by product_id first
                if (!empty($item['product_id'])) {
                    error_log("DEBUG: Trying to find by product_id: " . $item['product_id']);
                    $inventoryItem = $inventoryModel->findById($item['product_id']);
                }
                
                // Try by barcode if not found
                if (!$inventoryItem && !empty($item['barcode'])) {
                    error_log("DEBUG: Trying to find by barcode: " . $item['barcode']);
                    $inventoryItem = $inventoryModel->findByBarcode($item['barcode']);
                }
                
                // Try by name/SKU as last resort
                if (!$inventoryItem && !empty($item['description'])) {
                    error_log("DEBUG: Trying to find by description: " . $item['description']);
                    $allItems = $inventoryModel->getAll();
                    foreach ($allItems as $invItem) {
                        if (($invItem['name'] === $item['description'] || $invItem['barcode'] === $item['description']) && 
                            isset($invItem['_id'])) {
                            $inventoryItem = $invItem;
                            error_log("DEBUG: Found matching inventory item: " . json_encode($invItem));
                            break;
                        }
                    }
                }
                
                // Remove the inventory item if found
                if ($inventoryItem && isset($inventoryItem['_id'])) {
                    $inventoryId = (string)$inventoryItem['_id'];
                    $success = $inventoryModel->delete($inventoryId);
                    error_log("DEBUG: " . ($success ? "SUCCESS" : "FAILED") . " - Removed inventory item ID: $inventoryId for order: $orderId");
                } else {
                    error_log("DEBUG: No matching inventory item found for order item: " . json_encode($item));
                }
            }
        } catch (\Exception $e) {
            error_log("DEBUG: Error removing inventory items for order $orderId: " . $e->getMessage());
        }
    }
    
    switch ($method) {
        case 'GET':
            // Get all shipments or single shipment by ID
            if (isset($_GET['action']) && $_GET['action'] === 'recent') {
                // Get recent shipments for BIR RAMSAY 307
                error_log('Shipments API: Fetching recent shipments');
                
                // Try without sorting first
                $shipments = $shipmentModel->getAll();
                error_log('Shipments API: Found ' . count($shipments) . ' shipments without sorting');
                
                // Try with sorting
                $sortedShipments = $shipmentModel->getAll(['createdAt' => -1]);
                error_log('Shipments API: Found ' . count($sortedShipments) . ' shipments with sorting');
                
                // Use the sorted shipments if available, otherwise use unsorted
                $shipmentsToUse = !empty($sortedShipments) ? $sortedShipments : $shipments;
                
                if (empty($shipmentsToUse)) {
                    error_log('Shipments API: No shipments found, checking database directly');
                    $db = DatabaseService::getInstance();
                    $collection = $db->getCollection('shipments');
                    $direct = $collection->find()->toArray();
                    error_log('Shipments API: Direct database query found ' . count($direct) . ' shipments');
                    $shipmentsToUse = $direct;
                }
                
                $shipments = array_map('convertMongoDocument', $shipmentsToUse);
                error_log('Shipments API: After conversion, have ' . count($shipments) . ' shipments');
                
                // Debug: Log the first shipment structure
                if (!empty($shipments)) {
                    error_log('Shipments API: First shipment keys: ' . implode(', ', array_keys($shipments[0])));
                }
                
                // Return only first 5 recent shipments
                $recentShipments = array_slice($shipments, 0, 5);
                error_log('Shipments API: Returning ' . count($recentShipments) . ' recent shipments');
                echo json_encode(['success' => true, 'data' => $recentShipments]);
            } elseif (isset($_GET['search'])) {
                // Search shipments
                $searchQuery = $_GET['search'] ?? '';
                if (!empty($searchQuery)) {
                    $shipments = $shipmentModel->search($searchQuery);
                    $shipments = array_map('convertMongoDocument', $shipments);
                    echo json_encode(['success' => true, 'data' => $shipments]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Search query required']);
                }
            } elseif (isset($_GET['id'])) {
                $shipment = $shipmentModel->findById($_GET['id']);
                if ($shipment) {
                    $shipment = convertMongoDocument($shipment);
                    echo json_encode(['success' => true, 'data' => $shipment]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Shipment not found']);
                }
            } else {
                $shipments = $shipmentModel->getAll();
                $shipments = array_map('convertMongoDocument', $shipments);
                echo json_encode(['success' => true, 'data' => $shipments]);
            }
            break;
            
        case 'POST':
            // Create new shipment
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }
            
            // Validate required fields
            $requiredFields = ['order', 'customer', 'carrier', 'service_type', 'date', 'expected_delivery', 'address', 'tracking', 'value_of_goods'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                    exit;
                }
            }
            
            // Validate packages
            if (!isset($input['packages']) || !is_array($input['packages']) || count($input['packages']) === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'At least one package is required']);
                exit;
            }
            
            // Generate shipment number (format: SHP-YYYY-NNNNN)
            $input['shipment_number'] = $shipmentModel->generateShipmentNumber();
            
            // Set status and timestamps
            $input['status'] = 'pending';
            $input['status_history'] = [
                [
                    'status' => 'pending',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'note' => 'Shipment created',
                    'user' => $user['email'] ?? 'system'
                ]
            ];
            
            // Add user and financial info
            $input['created_by'] = $user['email'] ?? 'system';
            $input['shipping_cost'] = floatval($input['shipping_cost'] ?? 0);
            $input['insurance_amount'] = floatval($input['insurance'] ?? 0);
            
            // Calculate total cost
            $input['total_cost'] = $input['shipping_cost'] + $input['insurance_amount'];
            
            // Add notification preferences
            $input['notifications'] = [
                'email_enabled' => true,
                'sms_enabled' => false,
                'recipient_email' => $input['customer_email'] ?? '',
            ];
            
            // Create shipment
            $shipment = $shipmentModel->create($input);
            $shipment = convertMongoDocument($shipment);
            
            echo json_encode([
                'success' => true,
                'message' => 'Shipment created successfully',
                'data' => $shipment
            ]);
            break;
            
        case 'PUT':
            // Update shipment status or details
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Shipment ID is required']);
                exit;
            }
            
            $shipmentId = $_GET['id'];
            
            // Check if it's a status update
            if (isset($input['status'])) {
                $result = $shipmentModel->updateStatus(
                    $shipmentId,
                    $input['status'],
                    $input['note'] ?? '',
                    $user['email'] ?? 'system'
                );
                
                if ($result) {
                    // If status is being set to 'delivered', remove inventory items
                    if ($input['status'] === 'delivered') {
                        error_log("DEBUG: Shipment $shipmentId marked as delivered, removing inventory items");
                        $shipment = $shipmentModel->findById($shipmentId);
                        if ($shipment && isset($shipment['order'])) {
                            error_log("DEBUG: Found order ID: " . $shipment['order']);
                            removeInventoryItemsFromOrder($orderModel, $inventoryModel, $shipment['order']);
                        } else {
                            error_log("DEBUG: No order found for shipment: $shipmentId");
                        }
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Status updated successfully'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
            } else {
                // General update
                $result = $shipmentModel->update($shipmentId, $input);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Shipment updated successfully'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to update shipment']);
                }
            }
            break;
            
        case 'PATCH':
            // Batch operations (status update, carrier assignment, etc.)
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['ids']) || !is_array($input['ids'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'IDs array is required']);
                exit;
            }
            
            $operation = $input['operation'] ?? '';
            $updated = 0;
            
            switch ($operation) {
                case 'update_status':
                    $status = $input['status'] ?? '';
                    $note = $input['note'] ?? 'Batch status update';
                    
                    foreach ($input['ids'] as $id) {
                        if ($shipmentModel->updateStatus($id, $status, $note, $user['email'] ?? 'system')) {
                            $updated++;
                            
                            // If status is being set to 'delivered', remove inventory items
                            if ($status === 'delivered') {
                                error_log("DEBUG: Batch - Shipment $id marked as delivered, removing inventory items");
                                $shipment = $shipmentModel->findById($id);
                                if ($shipment && isset($shipment['order'])) {
                                    error_log("DEBUG: Batch - Found order ID: " . $shipment['order']);
                                    removeInventoryItemsFromOrder($orderModel, $inventoryModel, $shipment['order']);
                                } else {
                                    error_log("DEBUG: Batch - No order found for shipment: $id");
                                }
                            }
                        }
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "$updated shipments status updated",
                        'updated_count' => $updated
                    ]);
                    break;
                    
                case 'assign_carrier':
                    $carrier = $input['carrier'] ?? '';
                    
                    foreach ($input['ids'] as $id) {
                        if ($shipmentModel->update($id, ['carrier' => $carrier])) {
                            $updated++;
                        }
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "$updated shipments assigned to $carrier",
                        'updated_count' => $updated
                    ]);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid operation']);
                    break;
            }
            break;
            
        case 'DELETE':
            // Delete shipment(s)
            if (isset($_GET['id'])) {
                // Single delete
                $result = $shipmentModel->delete($_GET['id']);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Shipment deleted successfully'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to delete shipment']);
                }
            } else {
                // Batch delete
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($input['ids']) || !is_array($input['ids'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'IDs array is required']);
                    exit;
                }
                
                $deleted = 0;
                foreach ($input['ids'] as $id) {
                    if ($shipmentModel->delete($id)) {
                        $deleted++;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => "$deleted shipments deleted successfully",
                    'deleted_count' => $deleted
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
