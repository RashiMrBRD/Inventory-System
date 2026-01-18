<?php
/**
 * API endpoint to get user sessions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Service\SessionService;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$user = $authController->getCurrentUser();
$sessionService = new SessionService();

try {
    $userId = (string)$user['_id'];
    
    // Get query parameter for type (active or all)
    $type = $_GET['type'] ?? 'active';
    
    if ($type === 'active') {
        $sessions = $sessionService->getActiveSessions($userId);
    } else {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $sessions = $sessionService->getAllSessions($userId, $limit);
    }
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'count' => count($sessions)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch sessions: ' . $e->getMessage()
    ]);
}
