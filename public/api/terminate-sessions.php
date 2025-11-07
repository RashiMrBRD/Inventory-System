<?php
/**
 * API endpoint to terminate other sessions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Service\SessionService;

// Start session
session_start();

$authController = new AuthController();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

$user = $authController->getCurrentUser();
$sessionService = new SessionService();

try {
    $userId = (string)$user['_id'];
    
    // Terminate all other sessions except current one
    $count = $sessionService->terminateOtherSessions($userId);
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully terminated {$count} session(s)",
        'terminated_count' => $count
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to terminate sessions: ' . $e->getMessage()
    ]);
}
