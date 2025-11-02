<?php
/**
 * Shipments API Endpoint
 * Handles CRUD operations for shipments
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Shipment;

// Authenticate
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
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
    
    switch ($method) {
        case 'GET':
            // Get all shipments or single shipment by ID
            if (isset($_GET['id'])) {
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
            $requiredFields = ['order', 'customer', 'carrier', 'service_type', 'date', 'expected_delivery', 'address', 'tracking'];
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
