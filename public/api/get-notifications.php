<?php
/**
 * Get Notifications API
 * Returns recent unread notifications from database
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\NotificationRepository;
use App\Controller\AuthController;

try {
    // Check authentication
    $authController = new AuthController();
    if (!$authController->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $user = $authController->getCurrentUser();
    $userId = $user['id'] ?? 'admin';
    
    // Get notifications from database
    $repo = new NotificationRepository($userId);
    
    // Get ALL notifications (same as notifications.php - not just unread)
    // This ensures header shows same data as notifications.php page
    $allNotifications = $repo->getAll([
        'dismissed' => 'false',
        'deleted' => 'false',
        'limit' => 100,  // Match notifications.php limit
        'skip' => 0
    ]);
    
    // Categorize and prioritize notifications
    // Keep track of order within each category
    $categorized = [
        'critical' => [],      // High priority + financial/compliance
        'urgent' => [],        // High priority + inventory/expiry
        'important' => [],     // Medium priority
        'normal' => []         // Normal priority
    ];
    
    foreach ($allNotifications as $notif) {
        $type = $notif['type'];
        $priority = $notif['priority'];
        
        // Categorize based on type and priority
        if ($priority === 'high' && in_array($type, ['financial', 'bir', 'fda'])) {
            $categorized['critical'][] = $notif;
        } elseif ($priority === 'high' && in_array($type, ['inventory', 'expiry'])) {
            $categorized['urgent'][] = $notif;
        } elseif ($priority === 'medium' || ($priority === 'high' && $type === 'announcement')) {
            $categorized['important'][] = $notif;
        } else {
            $categorized['normal'][] = $notif;
        }
    }
    
    // Sort each category by created_at descending (newest first) for consistent ordering
    foreach ($categorized as &$category) {
        usort($category, function($a, $b) {
            $timeA = strtotime($a['created_at']);
            $timeB = strtotime($b['created_at']);
            return $timeB - $timeA;  // Descending (newest first)
        });
    }
    
    // Merge in priority order: critical first, then urgent, then important, then normal
    // Each category is already sorted by date (newest first)
    $mergedByPriority = array_merge(
        $categorized['critical'],
        $categorized['urgent'],
        $categorized['important'],
        $categorized['normal']
    );
    
    // Sort merged list by priority first, then date (like notifications.php)
    usort($mergedByPriority, function($a, $b) {
        $priorityOrder = ['high' => 3, 'medium' => 2, 'normal' => 1];
        $priorityA = $priorityOrder[$a['priority']] ?? 0;
        $priorityB = $priorityOrder[$b['priority']] ?? 0;
        
        // First sort by priority (high first)
        if ($priorityA !== $priorityB) {
            return $priorityB - $priorityA;  // Higher priority first
        }
        
        // Then sort by date (newest first)
        $timeA = strtotime($a['created_at']);
        $timeB = strtotime($b['created_at']);
        return $timeB - $timeA;  // Newer first
    });
    
    // Take top 10 notifications (priority first, then newest)
    $prioritizedNotifications = array_slice($mergedByPriority, 0, 10);
    
    // Format notifications with all required fields for header display
    $formattedNotifications = array_map(function($notif) {
        return [
            'id' => $notif['id'],
            'title' => $notif['title'],
            'message' => $notif['message'],
            'type' => $notif['type'],
            'priority' => $notif['priority'],
            'read' => $notif['read'],
            'dismissed' => $notif['dismissed'],
            'deleted' => $notif['deleted'],
            'created_at' => $notif['created_at'],
            'time' => $notif['time']  // Time ago format from DB
        ];
    }, $prioritizedNotifications);
    
    // Get unread count
    $unreadCount = $repo->getUnreadCount();
    
    // Additional highlight counts to mirror notifications.php
    $highPriorityCount = count(array_filter($allNotifications, fn($n) => ($n['priority'] ?? '') === 'high'));
    $mediumPriorityCount = count(array_filter($allNotifications, fn($n) => ($n['priority'] ?? '') === 'medium'));
    $todayHighMedCount = count(array_filter($allNotifications, function($n) {
        $created = strtotime($n['created_at'] ?? 'now');
        return $created > strtotime('-1 day') && in_array($n['priority'] ?? '', ['high','medium']);
    }));
    
    // Get notification type counts for header display (from ALL notifications, not just unread)
    $outstandingInvoices = count(array_filter($allNotifications, fn($n) => $n['type'] === 'financial' && in_array($n['title'], ['Invoice Overdue', 'Outstanding Invoice', 'Payment Due', 'Invoice Pending'])));
    $expiringStock = count(array_filter($allNotifications, fn($n) => $n['type'] === 'expiry'));
    $lowStock = count(array_filter($allNotifications, fn($n) => $n['type'] === 'inventory'));
    $complianceAlerts = count(array_filter($allNotifications, fn($n) => in_array($n['type'], ['bir', 'fda'])));
    $maintenance = count(array_filter($allNotifications, fn($n) => $n['type'] === 'announcement'));
    
    // Compute last updated text
    $latestTimestamp = 0;
    foreach ($allNotifications as $n) {
        $ts = strtotime($n['created_at'] ?? '');
        if ($ts && $ts > $latestTimestamp) { $latestTimestamp = $ts; }
    }
    $lastUpdated = 'just now';
    if ($latestTimestamp) {
        $diff = time() - $latestTimestamp;
        if ($diff < 60) $lastUpdated = 'just now';
        elseif ($diff < 3600) $lastUpdated = floor($diff/60) . ' mins ago';
        elseif ($diff < 86400) $lastUpdated = floor($diff/3600) . ' hours ago';
        else $lastUpdated = floor($diff/86400) . ' days ago';
    }
    
    echo json_encode([
        'success' => true,
        'total' => $unreadCount, // used for badge
        'notifications' => $formattedNotifications,
        'summary' => [
            // Page banner style highlights
            'unread_total' => $unreadCount,
            'high_priority_total' => $highPriorityCount,
            'medium_priority_total' => $mediumPriorityCount,
            'today_high_med' => $todayHighMedCount,
            'last_updated' => $lastUpdated,
            // Category chips
            'outstanding_invoices' => $outstandingInvoices,
            'expiring_stock' => $expiringStock,
            'low_stock' => $lowStock,
            'compliance_alerts' => $complianceAlerts,
            'maintenance' => $maintenance
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load notifications',
        'message' => $e->getMessage()
    ]);
}
