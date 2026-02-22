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
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return [];
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Add user_id filter for non-admin users
        $filter = $isAdmin ? [] : ['user_id' => $userId];

        $items = $this->collection->find($filter, ['sort' => $sort])->toArray();
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
            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                return null;
            }

            $itemData['user_id'] = $userId;
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
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return 0;
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Add user_id filter for non-admin users
        $filter = $isAdmin ? [] : ['user_id' => $userId];

        return $this->collection->countDocuments($filter);
    }

    /**
     * Get count of low stock items
     *
     * @param int $threshold
     * @return int
     */
    public function getLowStockCount(int $threshold = 5): int
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return 0;
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Add user_id filter for non-admin users
        $filter = ['quantity' => ['$lte' => $threshold]];
        if (!$isAdmin) {
            $filter['user_id'] = $userId;
        }

        return $this->collection->countDocuments($filter);
    }

    /**
     * Get count of out of stock items
     *
     * @return int
     */
    public function getOutOfStockCount(): int
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return 0;
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Add user_id filter for non-admin users
        $filter = ['quantity' => 0];
        if (!$isAdmin) {
            $filter['user_id'] = $userId;
        }

        return $this->collection->countDocuments($filter);
    }

    /**
     * Get recent items
     *
     * @param int $limit
     * @return array
     */
    public function getRecentItems(int $limit = 5): array
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
        $filter = $isAdmin ? [] : ['user_id' => $userId];

        $items = $this->collection->find(
            $filter,
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
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return 0;
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        $utc = new \MongoDB\BSON\UTCDateTime($since->getTimestamp() * 1000);
        $filter = ['date_added' => ['$gte' => $utc]];
        if (!$isAdmin) {
            $filter['user_id'] = $userId;
        }

        return $this->collection->countDocuments($filter);
    }

    /**
     * Count items created before a given DateTime
     */
    public function countBefore(\DateTimeInterface $dt): int
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return 0;
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        $utc = new \MongoDB\BSON\UTCDateTime($dt->getTimestamp() * 1000);
        $filter = ['date_added' => ['$lt' => $utc]];
        if (!$isAdmin) {
            $filter['user_id'] = $userId;
        }

        return $this->collection->countDocuments($filter);
    }

    /**
     * Get paginated inventory items with server-side filtering, sorting
     * This is optimized for large datasets - uses MongoDB skip/limit
     *
     * @param array $options Pagination and filter options
     * @return array
     */
    public function getPaginated(array $options = []): array
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return ['items' => [], 'total' => 0];
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Build filter
        $filter = $isAdmin ? [] : ['user_id' => $userId];

        // Apply search filter
        $search = $options['search'] ?? '';
        if (!empty($search)) {
            $regex = new \MongoDB\BSON\Regex($search, 'i');
            $filter['$or'] = [
                ['name' => $regex],
                ['barcode' => $regex],
                ['type' => $regex],
                ['sku' => $regex]
            ];
        }

        // Apply category filter
        $category = $options['category'] ?? 'all';
        if ($category !== 'all' && !empty($category)) {
            $filter['type'] = $category;
        }

        // Apply status filter
        $status = $options['status'] ?? 'all';
        if ($status !== 'all') {
            switch ($status) {
                case 'in_stock':
                    $filter['quantity'] = ['$gt' => 5];
                    break;
                case 'low_stock':
                    $filter['quantity'] = ['$gt' => 0, '$lte' => 5];
                    break;
                case 'out_of_stock':
                    $filter['quantity'] = 0;
                    break;
            }
        }

        // Build sort options
        $sortBy = $options['sort'] ?? 'name';
        $sortOrder = $options['order'] ?? 'asc';
        $sortDir = $sortOrder === 'desc' ? -1 : 1;

        // Map sort field to MongoDB field
        $sortFieldMap = [
            'name' => 'name',
            'sku' => 'sku',
            'barcode' => 'barcode',
            'category' => 'type',
            'type' => 'type',
            'price' => 'sell_price',
            'stock' => 'quantity',
            'quantity' => 'quantity',
            'status' => 'quantity',
            'value' => 'sell_price', // Value is computed, approximate with price
            'updated' => 'date_added',
            'date' => 'date_added'
        ];
        $mongoSortField = $sortFieldMap[$sortBy] ?? $sortBy;
        $sort = [$mongoSortField => $sortDir];

        // Pagination
        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = max(1, (int)($options['per_page'] ?? 10));
        $skip = ($page - 1) * $perPage;

        // Get total count (for pagination info)
        $total = $this->collection->countDocuments($filter);

        // Get paginated items
        $items = $this->collection->find(
            $filter,
            [
                'sort' => $sort,
                'skip' => $skip,
                'limit' => $perPage
            ]
        )->toArray();

        $items = array_map(function($item) {
            return (array)$item;
        }, $items);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, ceil($total / $perPage))
        ];
    }

    /**
     * Get inventory statistics with optional filters
     * Optimized for large datasets using aggregation
     *
     * @param array $filters Optional filters to apply
     * @return array
     */
    public function getStatsWithFilters(array $filters = []): array
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return [
                'total_items' => 0,
                'total_quantity' => 0,
                'total_value' => 0,
                'low_stock_count' => 0,
                'out_of_stock_count' => 0,
                'categories' => []
            ];
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Build match filter
        $match = $isAdmin ? [] : ['user_id' => $userId];

        // Apply search filter
        $search = $filters['search'] ?? '';
        if (!empty($search)) {
            $regex = new \MongoDB\BSON\Regex($search, 'i');
            $match['$or'] = [
                ['name' => $regex],
                ['barcode' => $regex],
                ['type' => $regex],
                ['sku' => $regex]
            ];
        }

        // Apply category filter
        $category = $filters['category'] ?? 'all';
        if ($category !== 'all' && !empty($category)) {
            $match['type'] = $category;
        }

        // Apply status filter
        $status = $filters['status'] ?? 'all';
        if ($status !== 'all') {
            switch ($status) {
                case 'in_stock':
                    $match['quantity'] = ['$gt' => 5];
                    break;
                case 'low_stock':
                    $match['quantity'] = ['$gt' => 0, '$lte' => 5];
                    break;
                case 'out_of_stock':
                    $match['quantity'] = 0;
                    break;
            }
        }

        // Aggregation pipeline for stats
        $pipeline = [];
        
        // Only add $match if we have filters
        if (!empty($match)) {
            $pipeline[] = ['$match' => $match];
        }
        
        $pipeline[] = ['$group' => [
            '_id' => null,
            'totalItems' => ['$sum' => 1],
            'totalQuantity' => ['$sum' => '$quantity'],
            'totalValue' => ['$sum' => ['$multiply' => ['$quantity', '$sell_price']]],
            'lowStockCount' => [
                '$sum' => ['$cond' => [
                    ['$and' => [['$gt' => ['$quantity', 0]], ['$lte' => ['$quantity', 5]]]],
                    1, 0
                ]]
            ],
            'outOfStockCount' => [
                '$sum' => ['$cond' => [['$eq' => ['$quantity', 0]], 1, 0]]
            ]
        ]];

        $result = $this->collection->aggregate($pipeline)->toArray();

        if (empty($result)) {
            return [
                'total_items' => 0,
                'total_quantity' => 0,
                'total_value' => 0,
                'low_stock_count' => 0,
                'out_of_stock_count' => 0,
                'categories' => []
            ];
        }

        $stats = (array)$result[0];
        unset($stats['_id']);
        
        // Get unique categories for filter dropdown
        $categoryPipeline = [];
        if (!$isAdmin) {
            $categoryPipeline[] = ['$match' => ['user_id' => $userId]];
        }
        $categoryPipeline[] = ['$group' => ['_id' => '$type']];
        $categoryPipeline[] = ['$sort' => ['_id' => 1]];
        
        $categories = array_map(fn($r) => $r['_id'], $this->collection->aggregate($categoryPipeline)->toArray());
        $categories = array_filter($categories); // Remove nulls
        
        // Map aggregation keys to expected output keys
        return [
            'total_items' => $stats['totalItems'] ?? 0,
            'total_quantity' => $stats['totalQuantity'] ?? 0,
            'total_value' => $stats['totalValue'] ?? 0,
            'low_stock_count' => $stats['lowStockCount'] ?? 0,
            'out_of_stock_count' => $stats['outOfStockCount'] ?? 0,
            'categories' => $categories
        ];
    }

    /**
     * Get map of YYYY-mm-dd => count for items added per day over last N days
     * @return array<string,int>
     */
    public function getDailyAddedCounts(int $days = 30): array
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return [];
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        $startTs = strtotime('-' . max(1, $days - 1) . ' days 00:00:00');
        $start = new \MongoDB\BSON\UTCDateTime($startTs * 1000);

        $match = ['date_added' => ['$gte' => $start]];
        if (!$isAdmin) {
            $match['user_id'] = $userId;
        }

        $pipeline = [
            ['$match' => $match],
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

    /**
     * Get dashboard analytics using aggregation (efficient for large datasets)
     * @param int $daysBack Number of days for trend calculation
     * @return array
     */
    public function getDashboardAnalytics(int $daysBack = 30): array
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $this->emptyDashboardResult();
        }

        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Build base filter
        $baseFilter = $isAdmin ? [] : ['user_id' => $userId];

        // Single aggregation for all stats
        $pipeline = [];
        if (!empty($baseFilter)) {
            $pipeline[] = ['$match' => $baseFilter];
        }
        $pipeline[] = ['$group' => [
            '_id' => null,
            'total_items' => ['$sum' => 1],
            'total_quantity' => ['$sum' => '$quantity'],
            'total_value' => ['$sum' => ['$multiply' => ['$quantity', '$sell_price']]],
            'low_stock_count' => ['$sum' => ['$cond' => [
                ['$and' => [['$gt' => ['$quantity', 0]], ['$lte' => ['$quantity', 5]]]],
                1, 0
            ]]],
            'out_of_stock_count' => ['$sum' => ['$cond' => [['$eq' => ['$quantity', 0]], 1, 0]]
            ]]
        ];

        $result = $this->collection->aggregate($pipeline)->toArray();
        $stats = !empty($result) ? (array)$result[0] : [];

        // Items added in current period (for trend)
        $startTs = strtotime('-' . max(1, $daysBack - 1) . ' days 00:00:00');
        $start = new \MongoDB\BSON\UTCDateTime($startTs * 1000);
        
        $periodMatch = $baseFilter;
        $periodMatch['date_added'] = ['$gte' => $start];
        
        $periodPipeline = [
            ['$match' => $periodMatch],
            ['$count' => 'items_added']
        ];
        $periodResult = $this->collection->aggregate($periodPipeline)->toArray();
        $itemsAddedThisPeriod = !empty($periodResult) ? $periodResult[0]->items_added : 0;

        // Items added in previous period
        $prevStartTs = strtotime('-' . ($daysBack * 2) . ' days 00:00:00');
        $prevStart = new \MongoDB\BSON\UTCDateTime($prevStartTs * 1000);
        $prevEnd = $start;
        
        $prevMatch = $baseFilter;
        $prevMatch['date_added'] = ['$gte' => $prevStart, '$lt' => $prevEnd];
        
        $prevPipeline = [
            ['$match' => $prevMatch],
            ['$count' => 'items_added']
        ];
        $prevResult = $this->collection->aggregate($prevPipeline)->toArray();
        $itemsAddedPrevPeriod = !empty($prevResult) ? $prevResult[0]->items_added : 0;

        // Calculate trend
        $trend = $itemsAddedPrevPeriod > 0 
            ? round((($itemsAddedThisPeriod - $itemsAddedPrevPeriod) / $itemsAddedPrevPeriod) * 100, 1)
            : ($itemsAddedThisPeriod > 0 ? 100 : 0);

        // Type distribution via aggregation
        $typePipeline = [];
        if (!empty($baseFilter)) {
            $typePipeline[] = ['$match' => $baseFilter];
        }
        $typePipeline[] = ['$group' => ['_id' => '$type', 'count' => ['$sum' => 1]]];
        $typeResult = $this->collection->aggregate($typePipeline)->toArray();
        $typeData = [];
        foreach ($typeResult as $r) {
            if ($r['_id']) {
                $typeData[$r['_id']] = (int)$r['count'];
            }
        }

        return [
            'total_items' => $stats['total_items'] ?? 0,
            'total_quantity' => $stats['total_quantity'] ?? 0,
            'total_value' => $stats['total_value'] ?? 0,
            'low_stock_count' => $stats['low_stock_count'] ?? 0,
            'out_of_stock_count' => $stats['out_of_stock_count'] ?? 0,
            'items_added_this_period' => $itemsAddedThisPeriod,
            'items_added_prev_period' => $itemsAddedPrevPeriod,
            'trend' => $trend,
            'type_distribution' => $typeData,
            'stock_levels' => [
                'In Stock' => ($stats['total_items'] ?? 0) - ($stats['low_stock_count'] ?? 0) - ($stats['out_of_stock_count'] ?? 0),
                'Low Stock' => $stats['low_stock_count'] ?? 0,
                'Out of Stock' => $stats['out_of_stock_count'] ?? 0
            ]
        ];
    }

    private function emptyDashboardResult(): array
    {
        return [
            'total_items' => 0,
            'total_quantity' => 0,
            'total_value' => 0,
            'low_stock_count' => 0,
            'out_of_stock_count' => 0,
            'items_added_this_period' => 0,
            'items_added_prev_period' => 0,
            'trend' => 0,
            'type_distribution' => [],
            'stock_levels' => ['In Stock' => 0, 'Low Stock' => 0, 'Out of Stock' => 0]
        ];
    }
}
