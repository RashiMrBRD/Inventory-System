<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;

/**
 * Invoice Model
 * Simple CRUD/read methods for invoices collection
 */
class Invoice
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('invoices');
        $this->collection = $db->getCollection($collectionName);
    }

    /**
     * Get all invoices with optional filter and options
     */
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
            'sort' => ['date' => -1],
            'limit' => 200
        ], $options);

        $cursor = $this->collection->find($filter, $options);
        $items = [];
        foreach ($cursor as $doc) {
            $items[] = (array)$doc;
        }
        return $items;
    }

    /**
     * Search invoices by invoice number, customer name, or status
     * This method searches invoices by invoice number, customer name, or status
     *
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return [];
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        $regex = new \MongoDB\BSON\Regex($query, 'i');

        $filter = [
            '$or' => [
                ['invoice_number' => $regex],
                ['customer_name' => $regex],
                ['customer_email' => $regex],
                ['status' => $regex]
            ]
        ];

        // Add user_id filter for non-admin users
        if (!$isAdmin) {
            $filter['user_id'] = $userId;
        }

        $items = $this->collection->find($filter)->toArray();

        return array_map(function($item) {
            return (array)$item;
        }, $items);
    }

    /**
     * Generate a unique invoice number
     */
    public function generateInvoiceNumber(): string
    {
        try {
            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                throw new \Exception("User not authenticated");
            }

            $year = date('Y');
            $month = date('m');

            error_log("DEBUG: Generating invoice number for year: $year, month: $month");

            // Get the count of invoices for this month and user
            $monthlyCount = $this->collection->countDocuments([
                'invoice_number' => ['$regex' => "^INV-{$year}{$month}"],
                'user_id' => $userId
            ]);

            error_log("DEBUG: Found $monthlyCount existing invoices for this month");

            // Generate sequential number (starting from 1)
            $sequence = $monthlyCount + 1;
            $sequencePadded = str_pad($sequence, 3, '0', STR_PAD_LEFT);

            $invoiceNumber = "INV-{$year}{$month}-{$sequencePadded}";
            error_log("DEBUG: Generated invoice number: $invoiceNumber");

            return $invoiceNumber;
        } catch (\Exception $e) {
            error_log("ERROR: Failed to generate invoice number: " . $e->getMessage());
            // Fallback to timestamp-based number if there's an error
            return "INV-" . date('Ym') . "-" . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Create a new invoice
     */
    public function create(array $data): string
    {
        try {
            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                throw new \Exception("User not authenticated");
            }

            error_log("DEBUG: Invoice create called with data: " . json_encode($data));

            // Add timestamps and defaults
            $data['user_id'] = $userId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['status'] = $data['status'] ?? 'draft';
            $data['paid'] = 0; // Default no payment made yet
            $data['outstanding'] = (float)($data['total'] ?? 0);

            error_log("DEBUG: Final invoice data before insert: " . json_encode($data));

            // Insert into database
            $result = $this->collection->insertOne($data);

            $invoiceId = (string)$result->getInsertedId();
            error_log("DEBUG: Invoice inserted with ID: $invoiceId");

            // Return the inserted ID as string
            return $invoiceId;
        } catch (\Exception $e) {
            error_log("ERROR: Failed to create invoice: " . $e->getMessage());
            error_log("ERROR: Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-throw to let caller handle it
        }
    }

    /**
     * Update an existing invoice
     */
    public function update(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $result = $this->collection->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id)],
            ['$set' => $data]
        );
        
        return $result->getModifiedCount() > 0;
    }

    /**
     * Delete an invoice
     */
    public function delete(string $id): bool
    {
        $result = $this->collection->deleteOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id)]
        );
        
        return $result->getDeletedCount() > 0;
    }

    /**
     * Get invoice by ID
     */
    public function getById(string $id): ?array
    {
        $invoice = $this->collection->findOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id)]
        );
        
        return $invoice ? (array)$invoice : null;
    }

    /**
     * Get invoice by ID (alias for getById)
     */
    public function findById(string $id): ?array
    {
        return $this->getById($id);
    }

    /**
     * Calculate totals (total, paid, outstanding)
     */
    public function totals(array $filter = []): array
    {
        try {
            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                return ['total' => 0, 'paid' => 0, 'outstanding' => 0];
            }

            // Check if user is admin
            $user = (new User())->findById($userId);
            $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

            // Add user_id filter for non-admin users
            if (!$isAdmin) {
                $filter['user_id'] = $userId;
            }

            $pipeline = [
                ['$match' => $filter],
                ['$group' => [
                    '_id' => null,
                    'total' => ['$sum' => ['$toDouble' => '$total']],
                    'paid' => ['$sum' => ['$toDouble' => '$paid']]
                ]]
            ];
            $res = $this->collection->aggregate($pipeline)->toArray();
            $row = $res[0] ?? [];
            $total = (float)($row['total'] ?? 0);
            $paid = (float)($row['paid'] ?? 0);
            return [
                'total' => $total,
                'paid' => $paid,
                'outstanding' => max(0, $total - $paid)
            ];
        } catch (\Throwable $e) {
            $all = $this->getAll($filter, ['limit' => 10000]);
            $total = 0; $paid = 0;
            foreach ($all as $inv) {
                $total += (float)($inv['total'] ?? 0);
                $paid += (float)($inv['paid'] ?? 0);
            }
            return [
                'total' => $total,
                'paid' => $paid,
                'outstanding' => max(0, $total - $paid)
            ];
        }
    }

    /**
     * Get paginated invoices with total count
     */
    public function getPaginated(int $page = 1, int $perPage = 6, array $filter = [], array $sort = ['date' => -1]): array
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage, 'totalPages' => 0];
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Add user_id filter for non-admin users
        if (!$isAdmin) {
            $filter['user_id'] = $userId;
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $skip = ($page - 1) * $perPage;

        $totalCount = $this->collection->countDocuments($filter);

        $options = [
            'sort' => $sort,
            'skip' => $skip,
            'limit' => $perPage
        ];

        $cursor = $this->collection->find($filter, $options);
        $items = [];
        foreach ($cursor as $doc) {
            $items[] = (array)$doc;
        }

        return [
            'items' => $items,
            'total' => $totalCount,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($totalCount / $perPage)
        ];
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
        $baseFilter = $isAdmin ? [] : ['user_id' => $userId];

        // Single aggregation for all invoice stats
        $pipeline = [];
        if (!empty($baseFilter)) {
            $pipeline[] = ['$match' => $baseFilter];
        }
        $pipeline[] = ['$group' => [
            '_id' => null,
            'total_revenue' => ['$sum' => ['$toDouble' => '$total']],
            'paid_revenue' => ['$sum' => ['$cond' => [
                ['$eq' => ['$status', 'paid']],
                ['$toDouble' => '$total'],
                0
            ]]],
            'invoice_count' => ['$sum' => 1]
        ]];

        $result = $this->collection->aggregate($pipeline)->toArray();
        $stats = !empty($result) ? (array)$result[0] : [];

        $totalRevenue = (float)($stats['total_revenue'] ?? 0);
        $paidRevenue = (float)($stats['paid_revenue'] ?? 0);
        $invoiceCount = (int)($stats['invoice_count'] ?? 0);

        // Revenue in current period
        $startTs = strtotime('-' . max(1, $daysBack - 1) . ' days 00:00:00');
        $start = new \MongoDB\BSON\UTCDateTime($startTs * 1000);

        $periodFilter = $baseFilter;
        $periodFilter['date'] = ['$gte' => $start];

        $periodPipeline = [];
        if (!empty($periodFilter)) {
            $periodPipeline[] = ['$match' => $periodFilter];
        }
        $periodPipeline[] = ['$group' => [
            '_id' => null,
            'revenue' => ['$sum' => ['$toDouble' => '$total']]
        ]];

        $periodResult = $this->collection->aggregate($periodPipeline)->toArray();
        $revenueInPeriod = !empty($periodResult) ? (float)$periodResult[0]->revenue : 0;

        // Previous period revenue
        $prevStartTs = strtotime('-' . ($daysBack * 2) . ' days 00:00:00');
        $prevStart = new \MongoDB\BSON\UTCDateTime($prevStartTs * 1000);
        $prevEnd = $start;

        $prevFilter = $baseFilter;
        $prevFilter['date'] = ['$gte' => $prevStart, '$lt' => $prevEnd];

        $prevPipeline = [];
        if (!empty($prevFilter)) {
            $prevPipeline[] = ['$match' => $prevFilter];
        }
        $prevPipeline[] = ['$group' => [
            '_id' => null,
            'revenue' => ['$sum' => ['$toDouble' => '$total']],
            'paid' => ['$sum' => ['$cond' => [
                ['$eq' => ['$status', 'paid']],
                ['$toDouble' => '$total'],
                0
            ]]]
        ]];

        $prevResult = $this->collection->aggregate($prevPipeline)->toArray();
        $prevRevenue = !empty($prevResult) ? (float)$prevResult[0]->revenue : 0;
        $prevPaid = !empty($prevResult) ? (float)$prevResult[0]->paid : 0;

        // Calculate trends
        $revenueTrend = $prevRevenue > 0 
            ? round((($revenueInPeriod - $prevRevenue) / $prevRevenue) * 100, 1)
            : ($revenueInPeriod > 0 ? 100 : 0);

        $collectionRate = $totalRevenue > 0 ? round(($paidRevenue / $totalRevenue) * 100, 1) : 0;
        $prevCollectionRate = $prevRevenue > 0 ? round(($prevPaid / $prevRevenue) * 100, 1) : 0;
        $collectionTrend = $prevCollectionRate > 0 
            ? round((($collectionRate - $prevCollectionRate) / $prevCollectionRate) * 100, 1)
            : 0;

        // Revenue by period for chart
        $revenueByPeriod = $this->getRevenueByPeriod($daysBack, $baseFilter);

        return [
            'total_revenue' => $totalRevenue,
            'paid_revenue' => $paidRevenue,
            'outstanding_revenue' => $totalRevenue - $paidRevenue,
            'invoice_count' => $invoiceCount,
            'revenue_in_period' => $revenueInPeriod,
            'revenue_trend' => $revenueTrend,
            'collection_rate' => $collectionRate,
            'collection_trend' => $collectionTrend,
            'revenue_by_period' => $revenueByPeriod
        ];
    }

    /**
     * Get revenue grouped by day/month for chart
     */
    private function getRevenueByPeriod(int $daysBack, array $baseFilter): array
    {
        $startTs = strtotime('-' . max(1, $daysBack - 1) . ' days 00:00:00');
        $start = new \MongoDB\BSON\UTCDateTime($startTs * 1000);

        $filter = $baseFilter;
        $filter['date'] = ['$gte' => $start];

        // Group by day
        $pipeline = [
            ['$match' => $filter],
            ['$group' => [
                '_id' => [
                    '$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$date']
                ],
                'revenue' => ['$sum' => ['$toDouble' => '$total']]
            ]],
            ['$sort' => ['_id' => 1]]
        ];

        $result = $this->collection->aggregate($pipeline)->toArray();
        $byPeriod = [];
        foreach ($result as $r) {
            if ($r['_id']) {
                $byPeriod[$r['_id']] = (float)$r['revenue'];
            }
        }
        return $byPeriod;
    }

    private function emptyDashboardResult(): array
    {
        return [
            'total_revenue' => 0,
            'paid_revenue' => 0,
            'outstanding_revenue' => 0,
            'invoice_count' => 0,
            'revenue_in_period' => 0,
            'revenue_trend' => 0,
            'collection_rate' => 0,
            'collection_trend' => 0,
            'revenue_by_period' => []
        ];
    }
}
