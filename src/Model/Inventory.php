<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

/**
 * Inventory Model
 * This class handles all inventory-related database operations
 */
class Inventory
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('inventory');
        $this->collection = $db->getCollection($collectionName);
    }

    /**
     * Get all inventory items
     * This method retrieves all items, optionally sorted by a specific field
     * 
     * @param array $sort Sorting criteria (e.g., ['date_added' => -1])
     * @return array
     */
    public function getAll(array $sort = ['date_added' => -1]): array
    {
        $items = $this->collection->find([], ['sort' => $sort])->toArray();
        return array_map(function($item) {
            return (array)$item;
        }, $items);
    }

    /**
     * Find an inventory item by ID
     * 
     * @param string $id
     * @return array|null
     */
    public function findById(string $id): ?array
    {
        try {
            $item = $this->collection->findOne(['_id' => new ObjectId($id)]);
            return $item ? (array)$item : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find an inventory item by barcode
     * 
     * @param string $barcode
     * @return array|null
     */
    public function findByBarcode(string $barcode): ?array
    {
        $item = $this->collection->findOne(['barcode' => $barcode]);
        return $item ? (array)$item : null;
    }

    /**
     * Create a new inventory item
     * 
     * @param array $itemData
     * @return string|null Returns the ID of the created item, or null on failure
     */
    public function create(array $itemData): ?string
    {
        try {
            $itemData['date_added'] = new \MongoDB\BSON\UTCDateTime();
            
            $result = $this->collection->insertOne($itemData);
            return $result->getInsertedId()->__toString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update an inventory item
     * 
     * @param string $id
     * @param array $itemData
     * @return bool
     */
    public function update(string $id, array $itemData): bool
    {
        try {
            $itemData['updated_at'] = new \MongoDB\BSON\UTCDateTime();
            
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => $itemData]
            );
            
            return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete an inventory item
     * 
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        try {
            $result = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get low stock items
     * This method returns items where the quantity is below a threshold
     * 
     * @param int $threshold
     * @return array
     */
    public function getLowStock(int $threshold = 5): array
    {
        $items = $this->collection->find(
            ['quantity' => ['$lte' => $threshold]],
            ['sort' => ['quantity' => 1]]
        )->toArray();
        
        return array_map(function($item) {
            return (array)$item;
        }, $items);
    }

    /**
     * Search inventory items
     * This method searches items by name, barcode, or type
     * 
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $regex = new \MongoDB\BSON\Regex($query, 'i');
        
        $items = $this->collection->find([
            '$or' => [
                ['name' => $regex],
                ['barcode' => $regex],
                ['type' => $regex]
            ]
        ])->toArray();
        
        return array_map(function($item) {
            return (array)$item;
        }, $items);
    }

    /**
     * Update item quantity
     * This method updates only the quantity field
     * 
     * @param string $id
     * @param int $quantity
     * @return bool
     */
    public function updateQuantity(string $id, int $quantity): bool
    {
        try {
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => ['quantity' => $quantity, 'updated_at' => new \MongoDB\BSON\UTCDateTime()]]
            );
            
            return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get inventory statistics
     * This method returns various statistics about the inventory
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        $pipeline = [
            [
                '$group' => [
                    '_id' => null,
                    'totalItems' => ['$sum' => 1],
                    'totalQuantity' => ['$sum' => '$quantity'],
                    'lowStockItems' => [
                        '$sum' => ['$cond' => [['$lte' => ['$quantity', 5]], 1, 0]]
                    ]
                ]
            ]
        ];
        
        $result = $this->collection->aggregate($pipeline)->toArray();
        
        if (empty($result)) {
            return [
                'totalItems' => 0,
                'totalQuantity' => 0,
                'lowStockItems' => 0
            ];
        }
        
        return (array)$result[0];
    }

    /**
     * Count total inventory items
     * 
     * @return int
     */
    public function count(): int
    {
        return $this->collection->countDocuments();
    }

    /**
     * Get count of low stock items
     * 
     * @param int $threshold
     * @return int
     */
    public function getLowStockCount(int $threshold = 5): int
    {
        return $this->collection->countDocuments(['quantity' => ['$lte' => $threshold]]);
    }

    /**
     * Get count of out of stock items
     * 
     * @return int
     */
    public function getOutOfStockCount(): int
    {
        return $this->collection->countDocuments(['quantity' => 0]);
    }

    /**
     * Get recent items
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentItems(int $limit = 5): array
    {
        $items = $this->collection->find(
            [],
            [
                'sort' => ['date_added' => -1],
                'limit' => $limit
            ]
        )->toArray();
        
        return array_map(function($item) {
            return (array)$item;
        }, $items);
    }

    /**
     * Count items added since given DateTime
     */
    public function countAddedSince(\DateTimeInterface $since): int
    {
        $utc = new \MongoDB\BSON\UTCDateTime($since->getTimestamp() * 1000);
        return $this->collection->countDocuments(['date_added' => ['$gte' => $utc]]);
    }

    /**
     * Count items created before a given DateTime
     */
    public function countBefore(\DateTimeInterface $dt): int
    {
        $utc = new \MongoDB\BSON\UTCDateTime($dt->getTimestamp() * 1000);
        return $this->collection->countDocuments(['date_added' => ['$lt' => $utc]]);
    }

    /**
     * Get map of YYYY-mm-dd => count for items added per day over last N days
     * @return array<string,int>
     */
    public function getDailyAddedCounts(int $days = 30): array
    {
        $startTs = strtotime('-' . max(1, $days - 1) . ' days 00:00:00');
        $start = new \MongoDB\BSON\UTCDateTime($startTs * 1000);
        $pipeline = [
            ['$match' => ['date_added' => ['$gte' => $start]]],
            ['$group' => [
                '_id' => [
                    '$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$date_added']
                ],
                'count' => ['$sum' => 1]
            ]],
            ['$sort' => ['_id' => 1]]
        ];
        $rows = $this->collection->aggregate($pipeline)->toArray();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['_id']] = (int)($r['count'] ?? 0);
        }
        return $map;
    }
}
