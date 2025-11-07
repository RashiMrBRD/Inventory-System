<?php

namespace App\Service;

use MongoDB\BSON\UTCDateTime;

class ConversationService
{
    private $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $this->collection = $db->getCollection('conversations');
        $this->ensureIndexes();
    }

    private function ensureIndexes(): void
    {
        try {
            $this->collection->createIndex(['team_id' => 1, 'channel' => 1, 'created_at' => -1]);
            $this->collection->createIndex(['participants' => 1]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function postMessage(string $userId, string $username, string $message, array $opts = []): bool
    {
        $doc = [
            'message' => $message,
            'user_id' => $userId,
            'username' => $username,
            'team_id' => $opts['team_id'] ?? null,
            'channel' => $opts['channel'] ?? 'general',
            'participants' => $opts['participants'] ?? [],
            'created_at' => new UTCDateTime(),
        ];
        try {
            $this->collection->insertOne($doc);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function listMessages(array $filter = [], int $limit = 50): array
    {
        $query = [];
        if (!empty($filter['team_id'])) $query['team_id'] = $filter['team_id'];
        if (!empty($filter['channel'])) $query['channel'] = $filter['channel'];
        try {
            $cursor = $this->collection->find($query, ['sort' => ['created_at' => -1], 'limit' => $limit]);
            $out = [];
            foreach ($cursor as $doc) {
                $out[] = [
                    'message' => $doc['message'] ?? '',
                    'username' => $doc['username'] ?? 'User',
                    'user_id' => $doc['user_id'] ?? '',
                    'team_id' => $doc['team_id'] ?? null,
                    'channel' => $doc['channel'] ?? 'general',
                    'time' => isset($doc['created_at']) && $doc['created_at'] instanceof UTCDateTime
                        ? $doc['created_at']->toDateTime()->format('Y-m-d H:i:s') : '',
                ];
            }
            // newest last for UI
            return array_reverse($out);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
