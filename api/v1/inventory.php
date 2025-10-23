<?php
/**
 * Inventory API Endpoint
 * This file handles all inventory-related API requests
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Controller\InventoryController;

// Set headers for JSON API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login first.'
    ]);
    exit();
}

$inventoryController = new InventoryController();
$response = [];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($method) {
        case 'GET':
            // Get all items or search
            if (isset($_GET['search'])) {
                $query = $_GET['search'];
                $response = $inventoryController->searchItems($query);
                
            } elseif (isset($_GET['id'])) {
                $id = $_GET['id'];
                $response = $inventoryController->getItem($id);
                
            } elseif (isset($_GET['low_stock'])) {
                $threshold = intval($_GET['threshold'] ?? 5);
                $response = $inventoryController->getLowStockItems($threshold);
                
            } elseif (isset($_GET['statistics'])) {
                $response = $inventoryController->getStatistics();
                
            } else {
                $response = $inventoryController->getAllItems();
            }
            break;

        case 'POST':
            // Create new item
            if (empty($input)) {
                http_response_code(400);
                $response = [
                    'success' => false,
                    'message' => 'Request body is required'
                ];
            } else {
                $response = $inventoryController->createItem($input);
                if ($response['success']) {
                    http_response_code(201);
                } else {
                    http_response_code(400);
                }
            }
            break;

        case 'PUT':
            // Update item
            if (!isset($_GET['id'])) {
                http_response_code(400);
                $response = [
                    'success' => false,
                    'message' => 'Item ID is required'
                ];
            } elseif (empty($input)) {
                http_response_code(400);
                $response = [
                    'success' => false,
                    'message' => 'Request body is required'
                ];
            } else {
                $id = $_GET['id'];
                $response = $inventoryController->updateItem($id, $input);
                if (!$response['success']) {
                    http_response_code(400);
                }
            }
            break;

        case 'DELETE':
            // Delete item
            if (!isset($_GET['id'])) {
                http_response_code(400);
                $response = [
                    'success' => false,
                    'message' => 'Item ID is required'
                ];
            } else {
                $id = $_GET['id'];
                $response = $inventoryController->deleteItem($id);
                if (!$response['success']) {
                    http_response_code(404);
                }
            }
            break;

        default:
            http_response_code(405);
            $response = [
                'success' => false,
                'message' => 'Method not allowed'
            ];
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ];
}

echo json_encode($response);
