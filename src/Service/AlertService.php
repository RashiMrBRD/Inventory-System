<?php

namespace App\Service;

use MongoDB\BSON\UTCDateTime;

class AlertService
{
    private $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $this->collection = $db->getCollection('system_alerts');
        $this->ensureIndexes();
    }

    private function ensureIndexes(): void
    {
        try {
            $this->collection->createIndex(['created_at' => -1]);
            $this->collection->createIndex(['type' => 1]);
        } catch (\Throwable $e) {
        }
    }

    public function create(string $title, string $body, string $type = 'info', array $meta = []): bool
    {
        $doc = [
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'meta' => $meta,
            'created_at' => new UTCDateTime(),
        ];
        try {
            $this->collection->insertOne($doc);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function list(int $limit = 50): array
    {
        try {
            $cursor = $this->collection->find([], ['sort' => ['created_at' => -1], 'limit' => $limit]);
            $out = [];
            foreach ($cursor as $doc) {
                $out[] = [
                    'title' => $doc['title'] ?? '',
                    'body' => $doc['body'] ?? '',
                    'type' => $doc['type'] ?? 'info',
                    'time' => isset($doc['created_at']) && $doc['created_at'] instanceof UTCDateTime
                        ? $doc['created_at']->toDateTime()->format('Y-m-d H:i:s') : '',
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
