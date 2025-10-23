<?php

namespace App\Helper;

use App\Database\NotificationRepository;

/**
 * Notification Helper
 * Utility functions for displaying notification counts and widgets
 */
class NotificationHelper
{
    /**
     * Get notification summary for current user
     */
    public static function getSummary(string $userId): array
    {
        try {
            $repo = new NotificationRepository($userId);
            
            $allNotifications = $repo->getAll([
                'dismissed' => 'false',
                'deleted' => 'false',
                'limit' => 10000,
                'skip' => 0
            ]);
            
            $unreadCount = $repo->getUnreadCount();
            $highPriorityCount = count(array_filter($allNotifications, fn($n) => $n['priority'] === 'high'));
            $mediumPriorityCount = count(array_filter($allNotifications, fn($n) => $n['priority'] === 'medium'));
            $todayCount = count(array_filter($allNotifications, fn($n) => strtotime($n['created_at']) > strtotime('-1 day')));
            
            // Count by type
            $inventoryCount = count(array_filter($allNotifications, fn($n) => $n['type'] === 'inventory'));
            $expiryCount = count(array_filter($allNotifications, fn($n) => $n['type'] === 'expiry'));
            $financialCount = count(array_filter($allNotifications, fn($n) => $n['type'] === 'financial'));
            $birCount = count(array_filter($allNotifications, fn($n) => $n['type'] === 'bir'));
            $fdaCount = count(array_filter($allNotifications, fn($n) => $n['type'] === 'fda'));
            $complianceCount = $birCount + $fdaCount;
            
            return [
                'total' => count($allNotifications),
                'unread' => $unreadCount,
                'high_priority' => $highPriorityCount,
                'medium_priority' => $mediumPriorityCount,
                'today' => $todayCount,
                'by_type' => [
                    'inventory' => $inventoryCount,
                    'expiry' => $expiryCount,
                    'financial' => $financialCount,
                    'bir' => $birCount,
                    'fda' => $fdaCount,
                    'compliance' => $complianceCount
                ]
            ];
        } catch (\Exception $e) {
            error_log("Failed to get notification summary: " . $e->getMessage());
            return [
                'total' => 0,
                'unread' => 0,
                'high_priority' => 0,
                'medium_priority' => 0,
                'today' => 0,
                'by_type' => [
                    'inventory' => 0,
                    'expiry' => 0,
                    'financial' => 0,
                    'bir' => 0,
                    'fda' => 0,
                    'compliance' => 0
                ]
            ];
        }
    }
    
    /**
     * Get recent notifications for current user
     */
    public static function getRecent(string $userId, int $limit = 5): array
    {
        try {
            $repo = new NotificationRepository($userId);
            return $repo->getAll([
                'dismissed' => 'false',
                'deleted' => 'false',
                'limit' => $limit,
                'skip' => 0
            ]);
        } catch (\Exception $e) {
            error_log("Failed to get recent notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Render notification badge HTML
     */
    public static function renderBadge(int $count, string $type = 'danger'): string
    {
        if ($count === 0) {
            return '';
        }
        
        return '<span class="badge badge-' . htmlspecialchars($type) . '">' . number_format($count) . '</span>';
    }
}
