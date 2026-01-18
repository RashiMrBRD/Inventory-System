<?php
/**
 * Generate Notifications API
 * Auto-generates contextual notifications based on business data
 * Can be called manually or via cron job
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Service\NotificationService;

header('Content-Type: application/json');

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $authController->getCurrentUser();

// Initialize with user ID
$userId = isset($user['_id']) ? (string)$user['_id'] : null;
if (!$userId) {
    // Fallback to session user_id
    $userId = $_SESSION['user_id'] ?? null;
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

try {
    $notificationService = new NotificationService($userId);
    
    // Generate all alerts
    $results = $notificationService->generateAllAlerts();
    
    $totalGenerated = array_sum($results);
    
    echo json_encode([
        'success' => true,
        'message' => "Generated {$totalGenerated} notifications",
        'details' => $results,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate notifications',
        'message' => $e->getMessage()
    ]);
}
