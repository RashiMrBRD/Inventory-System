<?php
/**
 * Notification Repository
 * Handles all database operations for notifications
 */

namespace App\Database;

use MongoDB\Client;
use MongoDB\Collection;
use App\Service\DatabaseService;

class NotificationRepository
{
    private Collection $collection;
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = $userId;
        // Use centralized DatabaseService to ensure authenticated connection
        $dbService = DatabaseService::getInstance();
        $collectionName = $dbService->getCollectionName('notifications');
        $this->collection = $dbService->getCollection($collectionName);
    }

    /**
     * Get all notifications for user
     */
    public function getAll(array $filters = []): array
    {
        $query = ['user_id' => $this->userId];
        
        // Apply filters (handle both booleans and 'true'/'false' strings)
        if (array_key_exists('read', $filters) && $filters['read'] !== null && $filters['read'] !== '') {
            $read = is_string($filters['read']) ? ($filters['read'] === 'true') : (bool)$filters['read'];
            $query['read'] = $read;
        }
        if (array_key_exists('priority', $filters) && $filters['priority'] !== null && $filters['priority'] !== '') {
            $query['priority'] = $filters['priority'];
        }
        if (array_key_exists('type', $filters) && $filters['type'] !== null && $filters['type'] !== '') {
            $query['type'] = $filters['type'];
        }
        if (array_key_exists('dismissed', $filters)) {
            $dismissed = is_string($filters['dismissed']) ? ($filters['dismissed'] === 'true') : (bool)$filters['dismissed'];
            // When filtering for false, treat missing field as false as well
            $query['dismissed'] = $dismissed ? true : ['$ne' => true];
        }
        if (array_key_exists('deleted', $filters)) {
            $deleted = is_string($filters['deleted']) ? ($filters['deleted'] === 'true') : (bool)$filters['deleted'];
            // When filtering for false, treat missing field as false as well
            $query['deleted'] = $deleted ? true : ['$ne' => true];
        }
        
        // Always sort by created_at descending (newest first)
        $options = [
            'sort' => ['created_at' => -1],
            'limit' => $filters['limit'] ?? 100,
            'skip' => $filters['skip'] ?? 0
        ];
        
        $cursor = $this->collection->find($query, $options);
        $notifications = [];
        
        foreach ($cursor as $doc) {
            $notifications[] = $this->formatNotification($doc);
        }
        
        return $notifications;
    }

    /**
     * Count notifications for user with filters
     */
    public function countBy(array $filters = []): int
    {
        $query = ['user_id' => $this->userId];
        
        if (array_key_exists('read', $filters) && $filters['read'] !== null && $filters['read'] !== '') {
            $read = is_string($filters['read']) ? ($filters['read'] === 'true') : (bool)$filters['read'];
            $query['read'] = $read;
        }
        if (array_key_exists('priority', $filters) && $filters['priority'] !== null && $filters['priority'] !== '') {
            $query['priority'] = $filters['priority'];
        }
        if (array_key_exists('type', $filters) && $filters['type'] !== null && $filters['type'] !== '') {
            $query['type'] = $filters['type'];
        }
        if (array_key_exists('dismissed', $filters)) {
            $dismissed = is_string($filters['dismissed']) ? ($filters['dismissed'] === 'true') : (bool)$filters['dismissed'];
            $query['dismissed'] = $dismissed ? true : ['$ne' => true];
        }
        if (array_key_exists('deleted', $filters)) {
            $deleted = is_string($filters['deleted']) ? ($filters['deleted'] === 'true') : (bool)$filters['deleted'];
            $query['deleted'] = $deleted ? true : ['$ne' => true];
        }
        
        return $this->collection->countDocuments($query);
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(): int
    {
        return $this->collection->countDocuments([
            'user_id' => $this->userId,
            'read' => false,
            // Exclude dismissed/deleted; treat missing fields as not dismissed/deleted
            'dismissed' => ['$ne' => true],
            'deleted' => ['$ne' => true]
        ]);
    }

    /**
     * Get dismissed notifications (trash)
     */
    public function getTrash(): array
    {
        $cursor = $this->collection->find(
            [
                'user_id' => $this->userId,
                'dismissed' => true,
                // Only not-deleted (treat missing as not deleted)
                'deleted' => ['$ne' => true]
            ],
            ['sort' => ['dismissed_at' => -1, 'created_at' => -1]]
        );
        
        $notifications = [];
        foreach ($cursor as $doc) {
            $notifications[] = $this->formatNotification($doc);
        }
        
        return $notifications;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId): bool
    {
        $result = $this->collection->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($notificationId), 'user_id' => $this->userId],
            ['$set' => ['read' => true, 'read_at' => new \MongoDB\BSON\UTCDateTime()]]
        );
        
        return $result->getModifiedCount() > 0;
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): int
    {
        $result = $this->collection->updateMany(
            ['user_id' => $this->userId, 'read' => false, 'dismissed' => false],
            ['$set' => ['read' => true, 'read_at' => new \MongoDB\BSON\UTCDateTime()]]
        );
        
        return $result->getModifiedCount();
    }

    /**
     * Dismiss notification (move to trash)
     */
    public function dismiss(string $notificationId): bool
    {
        $result = $this->collection->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($notificationId), 'user_id' => $this->userId],
            ['$set' => ['dismissed' => true, 'dismissed_at' => new \MongoDB\BSON\UTCDateTime()]]
        );
        
        return $result->getModifiedCount() > 0;
    }

    /**
     * Restore notification from trash
     */
    public function restore(string $notificationId): bool
    {
        $result = $this->collection->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($notificationId), 'user_id' => $this->userId],
            ['$set' => ['dismissed' => false, 'dismissed_at' => null]]
        );
        
        return $result->getModifiedCount() > 0;
    }

    /**
     * Delete notification permanently
     */
    public function delete(string $notificationId): bool
    {
        $result = $this->collection->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($notificationId), 'user_id' => $this->userId],
            ['$set' => ['deleted' => true, 'deleted_at' => new \MongoDB\BSON\UTCDateTime()]]
        );
        
        return $result->getModifiedCount() > 0;
    }

    /**
     * Empty trash (permanently delete all dismissed)
     */
    public function emptyTrash(): int
    {
        $result = $this->collection->updateMany(
            ['user_id' => $this->userId, 'dismissed' => true, 'deleted' => false],
            ['$set' => ['deleted' => true, 'deleted_at' => new \MongoDB\BSON\UTCDateTime()]]
        );
        
        return $result->getModifiedCount();
    }

    /**
     * Get notification by ID
     */
    public function getById(string $notificationId): ?array
    {
        $doc = $this->collection->findOne([
            '_id' => new \MongoDB\BSON\ObjectId($notificationId),
            'user_id' => $this->userId
        ]);
        
        return $doc ? $this->formatNotification($doc) : null;
    }

    /**
     * Create notification
     */
    public function create(array $data): string
    {
        $data['user_id'] = $this->userId;
        $data['created_at'] = new \MongoDB\BSON\UTCDateTime();
        $data['read'] = false;
        $data['dismissed'] = false;
        $data['deleted'] = false;
        
        $result = $this->collection->insertOne($data);
        return (string)$result->getInsertedId();
    }

    /**
     * Format notification for API response
     */
    private function formatNotification($doc): array
    {
        return [
            'id' => (string)$doc['_id'],
            'type' => $doc['type'] ?? 'announcement',
            'title' => $doc['title'] ?? '',
            'message' => $doc['message'] ?? '',
            'priority' => $doc['priority'] ?? 'normal',
            'read' => $doc['read'] ?? false,
            'dismissed' => $doc['dismissed'] ?? false,
            'deleted' => $doc['deleted'] ?? false,
            'created_at' => $doc['created_at']?->toDateTime()->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
            'read_at' => isset($doc['read_at']) ? $doc['read_at']?->toDateTime()->format('Y-m-d H:i:s') : null,
            'dismissed_at' => isset($doc['dismissed_at']) ? $doc['dismissed_at']?->toDateTime()->format('Y-m-d H:i:s') : null,
            'time' => $this->getTimeAgo($doc['created_at'] ?? new \MongoDB\BSON\UTCDateTime())
        ];
    }

    /**
     * Get time ago string
     */
    private function getTimeAgo(\MongoDB\BSON\UTCDateTime $date): string
    {
        $timestamp = $date->toDateTime()->getTimestamp();
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        
        return date('M d, Y', $timestamp);
    }
}
