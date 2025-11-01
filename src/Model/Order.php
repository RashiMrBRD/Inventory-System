<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Order Model - Sales and Purchase Orders
 */
class Order
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('orders');
        $this->collection = $db->getCollection($collectionName);
    }

    public function getAll(array $filter = [], array $options = []): array
    {
        $options = array_merge([
            'sort' => ['date' => -1],
            'limit' => 500
        ], $options);
        $cursor = $this->collection->find($filter, $options);
        $out = [];
        foreach ($cursor as $doc) {
            $doc = (array)$doc;
            // Convert MongoDB UTCDateTime objects to ISO string format
            if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
                $doc['date'] = $doc['date']->toDateTime()->format('Y-m-d');
            }
            if (isset($doc['created_at']) && $doc['created_at'] instanceof UTCDateTime) {
                $doc['created_at'] = $doc['created_at']->toDateTime()->format('Y-m-d H:i:s');
            }
            if (isset($doc['updated_at']) && $doc['updated_at'] instanceof UTCDateTime) {
                $doc['updated_at'] = $doc['updated_at']->toDateTime()->format('Y-m-d H:i:s');
            }
            $out[] = $doc;
        }
        return $out;
    }

    public function findById(string $id): ?array
    {
        try {
            $doc = $this->collection->findOne(['_id' => new ObjectId($id)]);
            if (!$doc) return null;
            
            $doc = (array)$doc;
            // Convert MongoDB UTCDateTime objects to ISO string format
            if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
                $doc['date'] = $doc['date']->toDateTime()->format('Y-m-d');
            }
            if (isset($doc['created_at']) && $doc['created_at'] instanceof UTCDateTime) {
                $doc['created_at'] = $doc['created_at']->toDateTime()->format('Y-m-d H:i:s');
            }
            if (isset($doc['updated_at']) && $doc['updated_at'] instanceof UTCDateTime) {
                $doc['updated_at'] = $doc['updated_at']->toDateTime()->format('Y-m-d H:i:s');
            }
            return $doc;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function create(array $data): ?string
    {
        try {
            $data['created_at'] = new UTCDateTime();
            $data['updated_at'] = new UTCDateTime();
            if (!isset($data['date'])) {
                $data['date'] = new UTCDateTime();
            } else if (is_string($data['date'])) {
                $data['date'] = new UTCDateTime(strtotime($data['date']) * 1000);
            }
            $result = $this->collection->insertOne($data);
            return $result->getInsertedId()->__toString();
        } catch (\Exception $e) {
            error_log("Order create error: " . $e->getMessage());
            return null;
        }
    }

    public function update(string $id, array $data): bool
    {
        try {
            $data['updated_at'] = new UTCDateTime();
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => $data]
            );
            return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
        } catch (\Exception $e) {
            error_log("Order update error: " . $e->getMessage());
            return false;
        }
    }

    public function delete(string $id): bool
    {
        try {
            $result = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateStatus(string $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    public function getStats(): array
    {
        try {
            $all = $this->getAll();
            $total = count($all);
            $salesOrders = array_filter($all, fn($o) => ($o['type'] ?? '') === 'Sales');
            $purchaseOrders = array_filter($all, fn($o) => ($o['type'] ?? '') === 'Purchase');
            
            return [
                'total' => $total,
                'sales_count' => count($salesOrders),
                'purchase_count' => count($purchaseOrders),
                'sales_value' => array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $salesOrders)),
                'purchase_value' => array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $purchaseOrders)),
                'total_value' => array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $all)),
                'pending' => count(array_filter($all, fn($o) => ($o['status'] ?? '') === 'pending')),
                'processing' => count(array_filter($all, fn($o) => ($o['status'] ?? '') === 'processing')),
                'shipped' => count(array_filter($all, fn($o) => in_array(($o['status'] ?? ''), ['shipped', 'delivered', 'received']))),
                'cancelled' => count(array_filter($all, fn($o) => ($o['status'] ?? '') === 'cancelled'))
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'sales_count' => 0,
                'purchase_count' => 0,
                'sales_value' => 0,
                'purchase_value' => 0,
                'total_value' => 0,
                'pending' => 0,
                'processing' => 0,
                'shipped' => 0,
                'cancelled' => 0
            ];
        }
    }
}
