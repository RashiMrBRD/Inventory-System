<?php

namespace App\Service;

use MongoDB\BSON\UTCDateTime;

class SecurityEventService
{
    private $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        // Use a dedicated collection; falls back to key name if no mapping
        $this->collection = $db->getCollection('security_events');
        $this->ensureIndexes();
    }

    private function ensureIndexes(): void
    {
        try {
            $this->collection->createIndex(['user_id' => 1, 'created_at' => -1]);
            $this->collection->createIndex(['type' => 1]);
        } catch (\Throwable $e) {
            // indexes may already exist
        }
    }

    /**
     * Log a security event for a user
     * @param string $userId
     * @param string $event Human-readable description
     * @param string $type Category (e.g., 'security', 'session')
     * @param array $meta Optional metadata (ip, path, ua, etc.)
     */
    public function log(string $userId, string $event, string $type = 'security', array $meta = []): bool
    {
        try {
            $doc = [
                'user_id' => $userId,
                'event' => $event,
                'type' => $type,
                'meta' => $meta,
                'created_at' => new UTCDateTime(),
            ];
            $this->collection->insertOne($doc);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * List recent events for a user
     */
    public function list(string $userId, int $limit = 20): array
    {
        try {
            $cursor = $this->collection->find(
                ['user_id' => $userId],
                ['sort' => ['created_at' => -1], 'limit' => $limit]
            );
            $out = [];
            foreach ($cursor as $doc) {
                $out[] = [
                    'event' => $doc['event'] ?? '',
                    'type' => $doc['type'] ?? 'security',
                    'time' => isset($doc['created_at']) ? $doc['created_at']->toDateTime()->format('Y-m-d H:i:s') : '',
                    'meta' => $doc['meta'] ?? [],
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
