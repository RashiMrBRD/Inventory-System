<?php
/**
 * Notifications API
 * Handles all notification operations with database integration
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\NotificationRepository;
use App\Controller\AuthController;

header('Content-Type: application/json');

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $authController->getCurrentUser();

// Initialize repository with user ID
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

$repo = new NotificationRepository($userId);

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            $filters = [
                'read' => $_GET['read'] ?? null,
                'priority' => $_GET['priority'] ?? null,
                'type' => $_GET['type'] ?? null,
                'dismissed' => $_GET['dismissed'] ?? null,
                // IMPORTANT: forward deleted filter so API can exclude deleted items
                'deleted' => $_GET['deleted'] ?? null,
                'limit' => (int)($_GET['limit'] ?? 50),
                'skip' => (int)($_GET['skip'] ?? 0)
            ];
            
            $notifications = $repo->getAll($filters);
            $unreadCount = $repo->getUnreadCount();
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
                'total' => count($notifications)
            ]);
            break;

        case 'get-page':
            $page = max(1, (int)($_GET['page'] ?? 1));
            $itemsPerPage = 10;
            $skip = ($page - 1) * $itemsPerPage;
            
            // Get ALL notifications for total count
            $allNotifications = $repo->getAll([
                'dismissed' => 'false',
                'deleted' => 'false',
                'limit' => 10000,
                'skip' => 0
            ]);
            
            // Get paginated notifications
            $notifications = $repo->getAll([
                'dismissed' => 'false',
                'deleted' => 'false',
                'limit' => $itemsPerPage,
                'skip' => $skip
            ]);
            
            // Sort by priority and date
            usort($notifications, function($a, $b) {
                $priorityOrder = ['high' => 3, 'medium' => 2, 'normal' => 1];
                $priorityA = $priorityOrder[$a['priority']] ?? 0;
                $priorityB = $priorityOrder[$b['priority']] ?? 0;
                
                if ($priorityA !== $priorityB) {
                    return $priorityB - $priorityA;
                }
                
                $timeA = strtotime($a['created_at']);
                $timeB = strtotime($b['created_at']);
                return $timeB - $timeA;
            });
            
            $totalNotifications = count($allNotifications);
            $totalPages = ceil($totalNotifications / $itemsPerPage);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'totalPages' => $totalPages,
                'totalNotifications' => $totalNotifications,
                'showingCount' => count($notifications),
                'currentPage' => $page
            ]);
            break;

        case 'trash':
            $trash = $repo->getTrash();
            
            echo json_encode([
                'success' => true,
                'trash' => $trash,
                'total' => count($trash)
            ]);
            break;

        case 'mark-read':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['notification_id'] ?? null;
            
            if (!$notificationId) {
                http_response_code(400);
                echo json_encode(['error' => 'notification_id required']);
                exit;
            }
            
            $success = $repo->markAsRead($notificationId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Marked as read' : 'Failed to mark as read'
            ]);
            break;

        case 'mark-all-read':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $count = $repo->markAllAsRead();
            
            echo json_encode([
                'success' => true,
                'message' => "Marked $count notifications as read",
                'count' => $count
            ]);
            break;

        case 'dismiss':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['notification_id'] ?? null;
            
            if (!$notificationId) {
                http_response_code(400);
                echo json_encode(['error' => 'notification_id required']);
                exit;
            }
            
            $success = $repo->dismiss($notificationId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification dismissed' : 'Failed to dismiss'
            ]);
            break;

        case 'restore':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['notification_id'] ?? null;
            
            if (!$notificationId) {
                http_response_code(400);
                echo json_encode(['error' => 'notification_id required']);
                exit;
            }
            
            $success = $repo->restore($notificationId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification restored' : 'Failed to restore'
            ]);
            break;

        case 'delete':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['notification_id'] ?? null;
            
            if (!$notificationId) {
                http_response_code(400);
                echo json_encode(['error' => 'notification_id required']);
                exit;
            }
            
            $success = $repo->delete($notificationId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification deleted permanently' : 'Failed to delete'
            ]);
            break;

        case 'empty-trash':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $count = $repo->emptyTrash();
            
            echo json_encode([
                'success' => true,
                'message' => "Emptied trash ($count items deleted)",
                'count' => $count
            ]);
            break;

        case 'get':
            $notificationId = $_GET['id'] ?? null;
            
            if (!$notificationId) {
                http_response_code(400);
                echo json_encode(['error' => 'id required']);
                exit;
            }
            
            $notification = $repo->getById($notificationId);
            
            if (!$notification) {
                http_response_code(404);
                echo json_encode(['error' => 'Notification not found']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'notification' => $notification
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
