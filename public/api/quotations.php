<?php
/**
 * Quotations API Endpoint
 * Handle CRUD operations for quotations
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Quotation;
use App\Helper\CurrencyHelper;

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$quotationModel = new Quotation();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            if ($action === 'create') {
                // Create new quotation
                $data = json_decode(file_get_contents('php://input'), true);
                
                $quotationData = [
                    'quote_number' => $data['quote_number'] ?? 'QT-' . date('Ymd') . '-' . rand(1000, 9999),
                    'customer' => $data['customer'] ?? '',
                    'customer_email' => $data['customer_email'] ?? '',
                    'customer_phone' => $data['customer_phone'] ?? '',
                    'customer_company' => $data['customer_company'] ?? '',
                    'date' => $data['date'] ?? date('Y-m-d'),
                    'validity_days' => (int)($data['validity_days'] ?? 30),
                    'items' => $data['items'] ?? [],
                    'subtotal' => (float)($data['subtotal'] ?? 0),
                    'tax' => (float)($data['tax'] ?? 0),
                    'total' => (float)($data['total'] ?? 0),
                    'notes' => $data['notes'] ?? '',
                    'status' => 'pending'
                ];
                
                $id = $quotationModel->create($quotationData);
                
                if ($id) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Quotation created successfully',
                        'id' => $id
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Failed to create quotation']);
                }
            } elseif ($action === 'update_status') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                $status = $data['status'] ?? '';
                
                $success = $quotationModel->updateStatus($id, $status);
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'Status updated successfully' : 'Failed to update status'
                ]);
            } elseif ($action === 'convert_to_order') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                
                $orderData = $quotationModel->convertToOrder($id);
                if ($orderData) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Quotation converted to order',
                        'order_data' => $orderData
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Failed to convert quotation']);
                }
            } elseif ($action === 'approve') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                
                if (empty($id)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Quotation ID is required']);
                    break;
                }
                
                // Check if quotation exists
                $quote = $quotationModel->findById($id);
                if (!$quote) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Quotation not found']);
                    break;
                }
                
                $success = $quotationModel->updateStatus($id, 'approved');
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'Quotation approved successfully' : 'Failed to approve quotation'
                ]);
            } elseif ($action === 'void') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                
                if (empty($id)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Quotation ID is required']);
                    break;
                }
                
                // Check if quotation exists
                $quote = $quotationModel->findById($id);
                if (!$quote) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Quotation not found']);
                    break;
                }
                
                $success = $quotationModel->updateStatus($id, 'rejected');
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'Quotation voided successfully' : 'Failed to void quotation'
                ]);
            } elseif ($action === 'duplicate') {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';
                
                if (empty($id)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Quotation ID is required']);
                    break;
                }
                
                $original = $quotationModel->findById($id);
                if (!$original) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Original quotation not found']);
                    break;
                }
                
                // Create new quotation with copied data
                $newQuotation = [
                    'quote_number' => 'QT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'customer' => $original['customer'],
                    'customer_email' => $original['customer_email'] ?? '',
                    'customer_phone' => $original['customer_phone'] ?? '',
                    'customer_company' => $original['customer_company'] ?? '',
                    'date' => date('Y-m-d'),
                    'validity_days' => $original['validity_days'] ?? 30,
                    'items' => $original['items'] ?? [],
                    'subtotal' => $original['subtotal'] ?? 0,
                    'tax' => $original['tax'] ?? 0,
                    'total' => $original['total'] ?? 0,
                    'notes' => $original['notes'] ?? '',
                    'status' => 'pending'
                ];
                
                $newId = $quotationModel->create($newQuotation);
                if ($newId) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Quotation duplicated successfully',
                        'id' => $newId
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to create duplicate quotation']);
                }
            } elseif ($action === 'bulk_approve') {
                $data = json_decode(file_get_contents('php://input'), true);
                $ids = $data['ids'] ?? [];
                
                if (empty($ids) || !is_array($ids)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'No quotation IDs provided']);
                    break;
                }
                
                $successCount = 0;
                $totalCount = count($ids);
                foreach ($ids as $id) {
                    // Check if quotation exists before updating
                    if ($quotationModel->findById($id) && $quotationModel->updateStatus($id, 'approved')) {
                        $successCount++;
                    }
                }
                
                if ($successCount === 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to approve any quotations. They may not exist or already be in the target status.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => "$successCount of $totalCount quotation(s) approved successfully",
                        'count' => $successCount
                    ]);
                }
            } elseif ($action === 'bulk_void') {
                $data = json_decode(file_get_contents('php://input'), true);
                $ids = $data['ids'] ?? [];
                
                if (empty($ids) || !is_array($ids)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'No quotation IDs provided']);
                    break;
                }
                
                $successCount = 0;
                $totalCount = count($ids);
                foreach ($ids as $id) {
                    // Check if quotation exists before updating
                    if ($quotationModel->findById($id) && $quotationModel->updateStatus($id, 'rejected')) {
                        $successCount++;
                    }
                }
                
                if ($successCount === 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to void any quotations. They may not exist or already be voided.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => "$successCount of $totalCount quotation(s) voided successfully",
                        'count' => $successCount
                    ]);
                }
            } elseif ($action === 'bulk_delete') {
                $data = json_decode(file_get_contents('php://input'), true);
                $ids = $data['ids'] ?? [];
                
                if (empty($ids) || !is_array($ids)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'No quotation IDs provided']);
                    break;
                }
                
                $successCount = 0;
                $totalCount = count($ids);
                foreach ($ids as $id) {
                    if ($quotationModel->delete($id)) {
                        $successCount++;
                    }
                }
                
                if ($successCount === 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to delete any quotations. They may not exist.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => "$successCount of $totalCount quotation(s) deleted successfully",
                        'count' => $successCount
                    ]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            break;
            
        case 'GET':
            if ($action === 'list') {
                $quotations = $quotationModel->getAll();
                // Convert _id to id string
                foreach ($quotations as &$quote) {
                    if (isset($quote['_id'])) {
                        $quote['id'] = (string)$quote['_id'];
                    }
                }
                unset($quote);
                echo json_encode([
                    'success' => true,
                    'data' => $quotations
                ]);
            } elseif ($action === 'get') {
                $id = $_GET['id'] ?? '';
                if (empty($id)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Quotation ID is required']);
                    break;
                }
                
                $quotation = $quotationModel->findById($id);
                if ($quotation) {
                    // Convert _id to id string
                    if (isset($quotation['_id'])) {
                        $quotation['id'] = (string)$quotation['_id'];
                    }
                    echo json_encode([
                        'success' => true,
                        'data' => $quotation
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Quotation not found']);
                }
            } elseif ($action === 'stats') {
                $stats = $quotationModel->getStats();
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? '';
            unset($data['id']);
            
            $success = $quotationModel->update($id, $data);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Quotation updated successfully' : 'Failed to update quotation'
            ]);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? $_GET['id'] ?? '';
            
            $success = $quotationModel->delete($id);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Quotation deleted successfully' : 'Failed to delete quotation'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
