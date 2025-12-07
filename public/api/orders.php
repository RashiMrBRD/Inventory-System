<?php
/**
 * Orders API Endpoint
 * Handles CRUD operations for sales and purchase orders
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Order;
use App\Helper\CurrencyHelper;

// Authenticate
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = $authController->getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $orderModel = new Order();
    
    // Handle action parameter for specific operations
    $action = $_GET['action'] ?? 'default';
    
    switch ($action) {
        case 'get_active_orders':
            // Get active orders (exclude 100% paid ones)
            $activeOrders = $orderModel->getActiveOrders();
            echo json_encode(['success' => true, 'orders' => $activeOrders]);
            break;
            
        default:
            // Original logic
            switch ($method) {
                case 'GET':
                    // Get all orders or single order by ID
                    if (isset($_GET['id'])) {
                        $order = $orderModel->findById($_GET['id']);
                        if ($order) {
                            echo json_encode(['success' => true, 'data' => $order]);
                        } else {
                            http_response_code(404);
                            echo json_encode(['success' => false, 'message' => 'Order not found']);
                        }
                    } else {
                        $orders = $orderModel->getAll();
                        echo json_encode(['success' => true, 'data' => $orders]);
                    }
                    break;
                    
                case 'POST':
                    // Create new order
                    $input = json_decode(file_get_contents('php://input'), true);
                    
                    if (!$input) {
                        $input = $_POST;
                    }
                    
                    // Validate required fields
                    if (empty($input['order_number']) || empty($input['customer']) || empty($input['date']) || empty($input['type'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Missing required fields: order_number, customer, date, type']);
                        exit;
                    }
                    
                    // Parse line items
                    $items = [];
                    $subtotal = 0;
                    
                    if (isset($input['items']) && is_array($input['items'])) {
                        foreach ($input['items'] as $item) {
                            if (isset($item['description']) && isset($item['quantity']) && isset($item['price'])) {
                                $qty = (float)$item['quantity'];
                                $price = (float)$item['price'];
                                $lineTotal = $qty * $price;
                                
                                $items[] = [
                                    'description' => trim($item['description']),
                                    'quantity' => $qty,
                                    'unit_price' => $price,
                                    'total' => $lineTotal
                                ];
                                
                                $subtotal += $lineTotal;
                            }
                        }
                    }
                    
                    // Calculate totals
                    $taxRate = 10; // 10% tax
                    $tax = $subtotal * ($taxRate / 100);
                    $discountPercent = isset($input['discount_percent']) ? (float)$input['discount_percent'] : 0;
                    $discountAmount = $subtotal * ($discountPercent / 100);
                    $total = $subtotal + $tax - $discountAmount;
            
            // Prepare order data
                    $orderData = [
                        'order_number' => trim($input['order_number']),
                        'type' => trim($input['type']), // Sales or Purchase
                        'customer' => trim($input['customer']),
                        'date' => $input['date'],
                        'status' => isset($input['status']) ? trim($input['status']) : 'pending',
                        'items' => $items,
                        'subtotal' => $subtotal,
                        'tax_rate' => $taxRate,
                        'tax' => $tax,
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount,
                        'total' => $total,
                        'payment_terms' => isset($input['payment_terms']) ? trim($input['payment_terms']) : 'net_30',
                        'reference' => isset($input['reference']) ? trim($input['reference']) : '',
                        'notes' => isset($input['notes']) ? trim($input['notes']) : '',
                        'email' => isset($input['email']) ? trim($input['email']) : '',
                        'phone' => isset($input['phone']) ? trim($input['phone']) : '',
                        'company' => isset($input['company']) ? trim($input['company']) : '',
                        'shipping_address' => isset($input['shipping_address']) ? trim($input['shipping_address']) : '',
                        'billing_address' => isset($input['billing_address']) ? trim($input['billing_address']) : '',
                        'currency' => isset($input['currency']) ? $input['currency'] : CurrencyHelper::getCurrentCurrency(),
                        'created_by' => $user['email'] ?? 'system'
                    ];
                    
                    // Save to database
                    $id = $orderModel->create($orderData);
                    
                    if ($id) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Order created successfully',
                            'id' => $id,
                            'order_number' => $orderData['order_number'],
                            'total' => $total
                        ]);
                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Failed to create order']);
                    }
                    break;
                    
                case 'PUT':
                    // Update existing order or record payment
                    $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing order ID']);
                exit;
            }
            
            $id = $input['id'];
            
            // Regular order update
            unset($input['id']);
                
                // Recalculate totals if items are updated
                if (isset($input['items'])) {
                    $subtotal = 0;
                    foreach ($input['items'] as $item) {
                        $subtotal += (float)$item['total'];
                    }
                    $taxRate = isset($input['tax_rate']) ? (float)$input['tax_rate'] : 10;
                    $tax = $subtotal * ($taxRate / 100);
                    $discountPercent = isset($input['discount_percent']) ? (float)$input['discount_percent'] : 0;
                    $discountAmount = $subtotal * ($discountPercent / 100);
                    $input['subtotal'] = $subtotal;
                    $input['tax'] = $tax;
                    $input['discount_amount'] = $discountAmount;
                    $input['total'] = $subtotal + $tax - $discountAmount;
                }
                
                $success = $orderModel->update($id, $input);
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to update order']);
                }
            break;
            
        case 'DELETE':
            // Delete order
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing order ID']);
                exit;
            }
            
            $success = $orderModel->delete($input['id']);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Order deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete order']);
            }
            break;
                    
            } // End method switch
            break;
    } // End action switch
    
} catch (\Exception $e) {
    error_log("Orders API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
