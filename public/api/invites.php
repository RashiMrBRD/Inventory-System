<?php
/**
 * Invite Link API Endpoints
 * Handles invite link generation, validation, and management
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\InviteController;
use App\Controller\AuthController;

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$isPublicValidation = ($method === 'GET' && $action === 'validate');

$inviteController = new InviteController();

if (!$isPublicValidation) {
    $authController = new AuthController();

    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Get current user
    $user = $authController->getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
}

try {
    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'generate':
                    // Generate new invite link
                    $input = json_decode(file_get_contents('php://input'), true);
                    $result = $inviteController->generateInvite($input);
                    echo json_encode($result);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
                    break;
            }
            break;

        case 'GET':
            switch ($action) {
                case 'list':
                    // Get all invites for current user
                    $result = $inviteController->getMyInvites();
                    echo json_encode($result);
                    break;

                case 'validate':
                    // Validate invite link
                    $token = $_GET['token'] ?? '';
                    if (empty($token)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Token is required']);
                        break;
                    }
                    $result = $inviteController->validateInvite($token);
                    echo json_encode($result);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
                    break;
            }
            break;

        case 'DELETE':
            switch ($action) {
                case 'revoke':
                    // Revoke invite link
                    $token = $_GET['token'] ?? '';
                    if (empty($token)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Token is required']);
                        break;
                    }
                    $result = $inviteController->revokeInvite($token);
                    echo json_encode($result);
                    break;

                case 'delete':
                    // Delete invite link
                    $token = $_GET['token'] ?? '';
                    if (empty($token)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Token is required']);
                        break;
                    }
                    $result = $inviteController->deleteInvite($token);
                    echo json_encode($result);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
                    break;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (\Exception $e) {
    error_log('Invite API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
