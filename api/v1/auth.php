<?php
/**
 * Authentication API Endpoint
 * This file handles all authentication-related API requests
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;

// Set headers for JSON API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$authController = new AuthController();
$response = [];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($method) {
        case 'POST':
            // Determine the action based on the endpoint or input
            if (isset($_GET['action'])) {
                $action = $_GET['action'];
                
                if ($action === 'login') {
                    $username = $input['username'] ?? '';
                    $password = $input['password'] ?? '';
                    $response = $authController->login($username, $password);
                    
                } elseif ($action === 'logout') {
                    $response = $authController->logout();
                    
                } elseif ($action === 'check') {
                    if ($authController->isLoggedIn()) {
                        $user = $authController->getCurrentUser();
                        $response = [
                            'success' => true,
                            'isLoggedIn' => true,
                            'user' => $user
                        ];
                    } else {
                        $response = [
                            'success' => true,
                            'isLoggedIn' => false
                        ];
                    }
                    
                } else {
                    http_response_code(400);
                    $response = [
                        'success' => false,
                        'message' => 'Invalid action'
                    ];
                }
            } else {
                http_response_code(400);
                $response = [
                    'success' => false,
                    'message' => 'Action parameter is required'
                ];
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
