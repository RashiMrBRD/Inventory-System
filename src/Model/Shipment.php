<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;

class Shipment
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('shipments');
        $this->collection = $db->getCollection($collectionName);
    }

    public function getAll(array $filter = [], array $options = []): array
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return [];
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Add user_id filter for non-admin users
        if (!$isAdmin) {
            $filter['user_id'] = $userId;
        }

        $options = array_merge([
            'sort' => ['createdAt' => -1],
            'limit' => 500
        ], $options);
        $cursor = $this->collection->find($filter, $options);
        $out = [];
        foreach ($cursor as $doc) { $out[] = (array)$doc; }
        return $out;
    }

    /**
     * Search shipments by tracking number, customer, or status
     * This method searches shipments by tracking number, customer name, or status
     * 
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $regex = new \MongoDB\BSON\Regex($query, 'i');
        
        $items = $this->collection->find([
            '$or' => [
                ['shipment_number' => $regex],
                ['tracking_number' => $regex],
                ['customer_name' => $regex],
                ['destination_address' => $regex],
                ['status' => $regex]
            ]
        ])->toArray();
        
        return array_map(function($item) {
            return (array)$item;
        }, $items);
    }

    public function create(array $data): array
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new \Exception("User not authenticated");
        }

        $data['user_id'] = $userId;
        $data['createdAt'] = new \MongoDB\BSON\UTCDateTime();
        $data['updatedAt'] = new \MongoDB\BSON\UTCDateTime();

        $result = $this->collection->insertOne($data);

        if ($result->getInsertedCount() > 0) {
            $data['_id'] = $result->getInsertedId();
            return $data;
        }

        throw new \Exception('Failed to create shipment');
    }

    public function findById(string $id): ?array
    {
        try {
            $objectId = new \MongoDB\BSON\ObjectId($id);
            $doc = $this->collection->findOne(['_id' => $objectId]);
            return $doc ? (array)$doc : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function generateShipmentNumber(): string
    {
        $year = date('Y');
        $prefix = "SHP-{$year}-";
        
        // Find the last shipment number for this year
        $lastShipment = $this->collection->findOne(
            ['shipment_number' => ['$regex' => "^{$prefix}"]],
            ['sort' => ['shipment_number' => -1]]
        );
        
        if ($lastShipment && isset($lastShipment['shipment_number'])) {
            $lastNumber = intval(substr($lastShipment['shipment_number'], -5));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    public function updateStatus(string $id, string $status, string $note = '', string $user = 'system'): bool
    {
        try {
            $objectId = new \MongoDB\BSON\ObjectId($id);
            
            $statusEntry = [
                'status' => $status,
                'timestamp' => date('Y-m-d H:i:s'),
                'note' => $note,
                'user' => $user
            ];
            
            $result = $this->collection->updateOne(
                ['_id' => $objectId],
                [
                    '$set' => [
                        'status' => $status,
                        'updatedAt' => new \MongoDB\BSON\UTCDateTime()
                    ],
                    '$push' => ['status_history' => $statusEntry]
                ]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function update(string $id, array $data): bool
    {
        try {
            $objectId = new \MongoDB\BSON\ObjectId($id);
            $data['updatedAt'] = new \MongoDB\BSON\UTCDateTime();
            
            $result = $this->collection->updateOne(
                ['_id' => $objectId],
                ['$set' => $data]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function delete(string $id): bool
    {
        try {
            $objectId = new \MongoDB\BSON\ObjectId($id);
            
            $result = $this->collection->deleteOne(['_id' => $objectId]);
            
            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            error_log("Delete shipment error: " . $e->getMessage());
            return false;
        }
    }
}
