<?php
/**
 * Invoices API
 * Handles CRUD operations for invoices
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Model\Invoice;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $invoiceModel = new Invoice();
    
    switch ($method) {
        case 'GET':
            if ($action === 'search') {
                // Search invoices
                $searchQuery = $_GET['search'] ?? '';
                if (!empty($searchQuery)) {
                    $invoices = $invoiceModel->search($searchQuery);
                    // Convert _id to id string
                    foreach ($invoices as &$invoice) {
                        if (isset($invoice['_id'])) {
                            $invoice['id'] = (string)$invoice['_id'];
                        }
                    }
                    unset($invoice);
                    echo json_encode([
                        'success' => true,
                        'data' => $invoices
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Search query required']);
                }
            } elseif (isset($_GET['id'])) {
                // Get single invoice by ID
                $invoice = $invoiceModel->findById($_GET['id']);
                if ($invoice) {
                    // Convert _id to id string
                    if (isset($invoice['_id'])) {
                        $invoice['id'] = (string)$invoice['_id'];
                    }
                    echo json_encode(['success' => true, 'data' => $invoice]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Invoice not found']);
                }
            } else {
                // Get all invoices with pagination
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = intval($_GET['limit'] ?? 50);
                $filter = [];
                
                // Apply status filter if provided
                if (isset($_GET['status']) && $_GET['status'] !== 'all') {
                    $filter['status'] = $_GET['status'];
                }
                
                $result = $invoiceModel->getPaginated($page, $limit, $filter);
                
                // Convert _id to id string for all invoices
                foreach ($result['items'] as &$invoice) {
                    if (isset($invoice['_id'])) {
                        $invoice['id'] = (string)$invoice['_id'];
                    }
                }
                unset($invoice);
                
                echo json_encode([
                    'success' => true,
                    'data' => $result['items'],
                    'pagination' => [
                        'page' => $result['page'],
                        'limit' => $result['limit'],
                        'total' => $result['total'],
                        'totalPages' => $result['totalPages']
                    ]
                ]);
            }
            break;
            
        case 'POST':
            // Create new invoice
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
                break;
            }
            
            try {
                $invoiceId = $invoiceModel->create($data);
                if ($invoiceId) {
                    $invoice = $invoiceModel->findById($invoiceId);
                    if (isset($invoice['_id'])) {
                        $invoice['id'] = (string)$invoice['_id'];
                    }
                    echo json_encode([
                        'success' => true,
                        'message' => 'Invoice created successfully',
                        'data' => $invoice
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Failed to create invoice']);
                }
            } catch (\Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'PUT':
            // Update invoice
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $_GET['id'] ?? '';
            
            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
                break;
            }
            
            try {
                $success = $invoiceModel->update($id, $data);
                if ($success) {
                    $invoice = $invoiceModel->findById($id);
                    if (isset($invoice['_id'])) {
                        $invoice['id'] = (string)$invoice['_id'];
                    }
                    echo json_encode([
                        'success' => true,
                        'message' => 'Invoice updated successfully',
                        'data' => $invoice
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Failed to update invoice']);
                }
            } catch (\Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'DELETE':
            // Delete invoice
            $id = $_GET['id'] ?? '';
            
            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
                break;
            }
            
            try {
                $success = $invoiceModel->delete($id);
                if ($success) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Invoice deleted successfully'
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Failed to delete invoice']);
                }
            } catch (\Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (\Exception $e) {
    error_log('Invoices API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
